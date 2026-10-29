<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class ProfitService
{
    public function withdraw(User $actor, int $staffId, int $amount): ProfitTransaction
    {
        if (! $actor->isManager()) {
            throw new RuntimeException('Penarikan bonus hanya dapat dicatat Owner.');
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal penarikan harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($actor, $staffId, $amount) {
            $staff = User::query()->lockForUpdate()->findOrFail($staffId);
            if (! $staff->isManager() && ! $staff->isCashier()) {
                throw ValidationException::withMessages(['user' => 'Akun tujuan bukan akun staf.']);
            }

            ProfitBalance::query()->firstOrCreate(['user_id' => $staff->id]);
            $balance = ProfitBalance::query()->where('user_id', $staff->id)->lockForUpdate()->firstOrFail();
            $balance->withdrawn_amount = (int) $balance->withdrawn_amount + $amount;
            $balance->available_amount = (int) $balance->available_amount - $amount;
            $balance->save();

            $transaction = ProfitTransaction::create([
                'user_id' => $staff->id,
                'created_by' => $actor->id,
                'type' => 'withdrawal',
                'amount' => $amount,
                'balance_after' => $balance->available_amount,
                'description' => "Penarikan bonus untuk {$staff->name}",
            ]);

            app(CashLedgerService::class)->post(
                $actor,
                'staff_withdrawal',
                'profit',
                'out',
                $amount,
                description: "Penarikan bonus {$staff->name}",
            );

            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'profit.withdrawn',
                'entity_type' => ProfitTransaction::class,
                'entity_id' => $transaction->id,
                'metadata' => ['staff_id' => $staff->id, 'staff_name' => $staff->name, 'amount' => $amount],
                'ip_address' => request()?->ip(),
            ]);

            return $transaction;
        });
    }

    public function transferToCapital(User $actor, int $staffId, int $amount): ProfitTransaction
    {
        if (! $actor->isManager()) {
            throw new RuntimeException('Hanya Manager yang dapat memindahkan bonus ke modal.');
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal transfer harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($actor, $staffId, $amount) {
            $staff = User::query()->lockForUpdate()->findOrFail($staffId);
            if (! $staff->isManager()) {
                throw ValidationException::withMessages(['user' => 'Bonus Admin tidak dapat dipindahkan ke modal.']);
            }

            ProfitBalance::query()->firstOrCreate(['user_id' => $staff->id]);
            $balance = ProfitBalance::query()->where('user_id', $staff->id)->lockForUpdate()->firstOrFail();
            if ((int) $balance->available_amount < $amount) {
                throw ValidationException::withMessages(['amount' => 'Saldo bonus tersedia tidak mencukupi untuk transfer.']);
            }

            $balance->transferred_to_modal_amount = (int) $balance->transferred_to_modal_amount + $amount;
            $balance->available_amount = (int) $balance->available_amount - $amount;
            $balance->save();

            $transaction = ProfitTransaction::create([
                'user_id' => $staff->id,
                'created_by' => $actor->id,
                'type' => 'transfer_to_modal',
                'amount' => $amount,
                'balance_after' => $balance->available_amount,
                'description' => "Bonus {$staff->name} dipindahkan ke modal kas",
            ]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'profit.transferred_to_modal',
                'entity_type' => ProfitTransaction::class,
                'entity_id' => $transaction->id,
                'metadata' => ['staff_id' => $staff->id, 'amount' => $amount],
                'ip_address' => request()?->ip(),
            ]);

            return $transaction;
        });
    }
}
