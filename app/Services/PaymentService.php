<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\BorrowerAdvanceTransaction;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function approvePayment(User $manager, int $paymentId): Payment
    {
        if (! $manager->isManager()) {
            throw new RuntimeException('Hanya Manager yang dapat menyetujui pembayaran.');
        }

        return DB::transaction(function () use ($manager, $paymentId) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status === 'approved') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran sudah disetujui.']);
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran tidak lagi menunggu persetujuan.']);
            }

            $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);
            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran hanya dapat disetujui untuk pinjaman aktif.']);
            }

            if ($payment->installment_id !== null) {
                $selectedInstallment = Installment::query()->findOrFail($payment->installment_id);
                if ((int) $selectedInstallment->loan_id !== (int) $loan->id) {
                    throw ValidationException::withMessages(['payment' => 'Cicilan tidak sesuai dengan pinjaman.']);
                }
            }

            $remaining = (int) $payment->amount;
            $allocations = [];

            $installments = Installment::query()
                ->where('loan_id', $loan->id)
                ->where('outstanding_amount', '>', 0)
                ->when($payment->installment_id, fn ($query) => $query->where('id', $payment->installment_id))
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) break;

                $allocated = min($remaining, (int) $installment->outstanding_amount);
                $installment->paid_amount = (int) $installment->paid_amount + $allocated;
                $installment->outstanding_amount = (int) $installment->outstanding_amount - $allocated;
                $installment->status = (int) $installment->outstanding_amount === 0 ? 'paid' : 'partial';
                $installment->paid_at = (int) $installment->outstanding_amount === 0 ? now() : $installment->paid_at;
                $installment->save();

                $remaining -= $allocated;
                $allocations[] = ['sequence' => $installment->sequence, 'amount' => $allocated];

                if ($installment->status === 'paid') {
                    $this->realizeProfit($manager, $loan, $installment);
                }
            }

            if ($remaining > 0) {
                $borrower = $loan->borrower()->lockForUpdate()->firstOrFail();
                $advanceBalance = (int) ($borrower->advanceTransactions()->latest('id')->value('balance_after') ?? 0);
                BorrowerAdvanceTransaction::create([
                    'borrower_id' => $borrower->id,
                    'loan_id' => $loan->id,
                    'payment_id' => $payment->id,
                    'created_by' => $manager->id,
                    'amount' => $remaining,
                    'balance_after' => $advanceBalance + $remaining,
                ]);
            }

            $payment->forceFill([
                'status' => 'approved',
                'approved_by' => $manager->id,
                'approved_at' => now(),
            ])->save();

            ApprovalRequest::query()
                ->where('approvable_type', Payment::class)
                ->where('approvable_id', $payment->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'reviewed_by' => $manager->id,
                    'reviewed_at' => now(),
                ]);

            app(CashLedgerService::class)->post(
                $manager,
                'installment_payment',
                'installment',
                'in',
                (int) $payment->amount,
                $loan->id,
                'PAY-' . str_pad((string) $payment->getKey(), 8, '0', STR_PAD_LEFT),
                'Pembayaran cicilan nasabah',
            );

            $loanPaid = $loan->installments()->sum('paid_amount');
            if ($loanPaid >= $loan->total_receivable) {
                $loan->status = 'paid';
                $loan->save();
            }

            ActivityLog::create([
                'user_id' => $manager->id,
                'action' => 'payment.approved',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'metadata' => ['amount' => $payment->amount, 'allocations' => $allocations, 'advance_credit' => $remaining],
                'ip_address' => request()?->ip(),
            ]);

            return $payment->fresh();
        });
    }

    public function rejectPayment(User $manager, int $paymentId): Payment
    {
        if (! $manager->isManager()) {
            throw new RuntimeException('Hanya Manager yang dapat menolak pembayaran.');
        }

        return DB::transaction(function () use ($manager, $paymentId) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran tidak lagi menunggu persetujuan.']);
            }

            $payment->forceFill(['status' => 'rejected'])->save();

            ApprovalRequest::query()
                ->where('approvable_type', Payment::class)
                ->where('approvable_id', $payment->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'reviewed_by' => $manager->id,
                    'reviewed_at' => now(),
                ]);

            ActivityLog::create([
                'user_id' => $manager->id,
                'action' => 'payment.rejected',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'metadata' => ['amount' => $payment->amount],
                'ip_address' => request()?->ip(),
            ]);

            return $payment->fresh();
        });
    }

    private function realizeProfit(User $manager, Loan $loan, Installment $installment): void
    {
        foreach ([
            ['username' => 'owner', 'amount' => (int) $installment->alan_profit_amount],
            ['username' => 'admin', 'amount' => (int) $installment->chalo_profit_amount],
        ] as $share) {
            $staff = User::query()->where('username', $share['username'])->lockForUpdate()->first();
            if (! $staff) continue;

            ProfitBalance::query()->firstOrCreate(['user_id' => $staff->id]);
            $balance = ProfitBalance::query()->where('user_id', $staff->id)->lockForUpdate()->firstOrFail();
            $balance->earned_amount = (int) $balance->earned_amount + $share['amount'];
            $balance->available_amount = (int) $balance->available_amount + $share['amount'];
            $balance->save();

            ProfitTransaction::create([
                'user_id' => $staff->id,
                'created_by' => $manager->id,
                'loan_id' => $loan->id,
                'installment_id' => $installment->id,
                'type' => 'earned_interest',
                'amount' => $share['amount'],
                'balance_after' => $balance->available_amount,
                'description' => "Bonus bunga setoran {$installment->sequence} lunas",
            ]);
        }
    }
}
