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
    public function correctApprovedPayment(User $manager, int $paymentId, int $newAmount): int
    {
        if (! $manager->isManager()) {
            throw new RuntimeException('Hanya Owner yang dapat mengoreksi setoran.');
        }

        if ($newAmount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal setoran harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($manager, $paymentId, $newAmount) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'approved') {
                throw ValidationException::withMessages(['payment' => 'Hanya setoran yang sudah disetujui yang dapat dikoreksi.']);
            }

            $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);
            $approvedPayments = Payment::query()
                ->where('loan_id', $loan->id)
                ->where('status', 'approved')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $installments = Installment::query()
                ->where('loan_id', $loan->id)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();
            $oldAmount = (int) $payment->amount;

            if ($oldAmount === $newAmount) {
                throw ValidationException::withMessages(['amount' => 'Nominal baru sama dengan nominal saat ini.']);
            }

            $earnedTransactions = ProfitTransaction::query()
                ->where('loan_id', $loan->id)
                ->whereIn('type', ['earned_interest', 'correction_reversal'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $netEarnedByInstallment = [];
            foreach ($earnedTransactions as $transaction) {
                $key = $transaction->user_id.':'.($transaction->installment_id ?? 'none');
                $sign = $transaction->type === 'earned_interest' ? 1 : -1;
                $netEarnedByInstallment[$key] = ($netEarnedByInstallment[$key] ?? 0) + ($sign * (int) $transaction->amount);
            }

            foreach ($netEarnedByInstallment as $key => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                [$userId, $installmentId] = explode(':', $key, 2);
                $balance = ProfitBalance::query()->where('user_id', $userId)->lockForUpdate()->first();
                if (! $balance) {
                    continue;
                }

                $balance->earned_amount = (int) $balance->earned_amount - $amount;
                $balance->available_amount = (int) $balance->available_amount - $amount;
                $balance->save();

                ProfitTransaction::create([
                    'user_id' => $userId,
                    'created_by' => $manager->id,
                    'loan_id' => $loan->id,
                    'installment_id' => $installmentId === 'none' ? null : $installmentId,
                    'type' => 'correction_reversal',
                    'amount' => $amount,
                    'balance_after' => $balance->available_amount,
                    'description' => 'Pembalikan bonus karena koreksi setoran',
                ]);
            }

            foreach ($installments as $installment) {
                $installment->forceFill([
                    'paid_amount' => 0,
                    'outstanding_amount' => $installment->scheduled_amount,
                    'status' => 'pending',
                    'paid_at' => null,
                ])->save();
            }

            $payment->forceFill([
                'amount' => $newAmount,
                'nominal_source' => 'manual',
            ])->save();

            BorrowerAdvanceTransaction::query()->where('loan_id', $loan->id)->delete();

            foreach ($approvedPayments as $approvedPayment) {
                if ((int) $approvedPayment->id === $paymentId) {
                    $approvedPayment->amount = $newAmount;
                }
                $remaining = (int) $approvedPayment->amount;
                $eligibleInstallments = $installments->when(
                    $approvedPayment->installment_id !== null,
                    fn ($items) => $items->where('id', $approvedPayment->installment_id),
                );
                $profitActor = User::query()->find($approvedPayment->approved_by) ?? $manager;

                foreach ($eligibleInstallments as $installment) {
                    if ($remaining <= 0 || (int) $installment->outstanding_amount <= 0) {
                        continue;
                    }

                    $allocated = min($remaining, (int) $installment->outstanding_amount);
                    $installment->paid_amount = (int) $installment->paid_amount + $allocated;
                    $installment->outstanding_amount = (int) $installment->outstanding_amount - $allocated;
                    $installment->status = (int) $installment->outstanding_amount === 0 ? 'paid' : 'partial';
                    $installment->paid_at = $installment->status === 'paid'
                        ? ($approvedPayment->approved_at ?? $approvedPayment->created_at)
                        : null;
                    $installment->save();
                    $remaining -= $allocated;

                    if ($installment->status === 'paid') {
                        $this->realizeProfit($profitActor, $loan, $installment);
                    }
                }

                if ($remaining > 0) {
                    BorrowerAdvanceTransaction::create([
                        'borrower_id' => $loan->borrower_id,
                        'loan_id' => $loan->id,
                        'payment_id' => $approvedPayment->id,
                        'created_by' => $approvedPayment->approved_by ?? $manager->id,
                        'amount' => $remaining,
                        'balance_after' => 0,
                    ])->forceFill([
                        'created_at' => $approvedPayment->approved_at ?? $approvedPayment->created_at,
                    ])->save();
                }
            }

            $advanceBalance = 0;
            foreach (BorrowerAdvanceTransaction::query()
                ->where('borrower_id', $loan->borrower_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get() as $advanceTransaction) {
                $advanceBalance += (int) $advanceTransaction->amount;
                $advanceTransaction->forceFill(['balance_after' => $advanceBalance])->save();
            }

            $difference = $newAmount - $oldAmount;
            if ($difference !== 0) {
                app(CashLedgerService::class)->post(
                    $manager,
                    'payment_correction',
                    'payment',
                    $difference > 0 ? 'in' : 'out',
                    abs($difference),
                    $loan->id,
                    description: "Koreksi nominal setoran #{$payment->id}: Rp ".number_format($oldAmount, 0, ',', '.')." menjadi Rp ".number_format($newAmount, 0, ',', '.'),
                );
            }

            $loan->status = $installments->every(fn (Installment $installment) => (int) $installment->outstanding_amount === 0)
                ? 'paid'
                : 'active';
            $loan->save();

            ActivityLog::create([
                'user_id' => $manager->id,
                'action' => 'payment.corrected',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'metadata' => ['old_amount' => $oldAmount, 'new_amount' => $newAmount],
                'ip_address' => request()?->ip(),
            ]);

            return $oldAmount;
        });
    }

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
