<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CashTransaction;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
                throw new RuntimeException('Pembayaran sudah disetujui.');
            }

            $remaining = $payment->amount;
            $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);
            $allocations = [];

            $installments = Installment::query()
                ->where('loan_id', $loan->id)
                ->where('outstanding_amount', '>', 0)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) break;

                $allocated = min($remaining, (int) $installment->outstanding_amount);
                $installment->paid_amount += $allocated;
                $installment->outstanding_amount -= $allocated;
                $installment->status = $installment->outstanding_amount === 0 ? 'paid' : 'partial';
                $installment->paid_at = $installment->outstanding_amount === 0 ? now() : $installment->paid_at;
                $installment->save();

                $remaining -= $allocated;
                $allocations[] = ['sequence' => $installment->sequence, 'amount' => $allocated];

                if ($installment->status === 'paid') {
                    $this->realizeProfit($manager, $loan, $installment);
                }
            }

            $payment->status = 'approved';
            $payment->approved_by = $manager->id;
            $payment->approved_at = now();
            $payment->save();

            $balance = (int) (CashTransaction::query()->latest('id')->value('balance_after') ?? 0);
            CashTransaction::create([
                'created_by' => $manager->id,
                'type' => 'installment_payment',
                'source' => 'installment',
                'direction' => 'in',
                'amount' => $payment->amount,
                'balance_after' => $balance + $payment->amount,
                'loan_id' => $loan->id,
                'reference' => $payment->getKey() ? 'PAY-' . str_pad((string) $payment->getKey(), 8, '0', STR_PAD_LEFT) : null,
                'description' => 'Pembayaran cicilan nasabah',
            ]);

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
                'metadata' => ['amount' => $payment->amount, 'allocations' => $allocations, 'credit_balance' => $remaining],
                'ip_address' => request()?->ip(),
            ]);

            return $payment->fresh();
        });
    }

    private function realizeProfit(User $manager, Loan $loan, Installment $installment): void
    {
        $interestPerInstallment = (int) intdiv($loan->interest_amount, 12);
        $alanShare = (int) round($interestPerInstallment * 0.60);
        $chaloShare = $interestPerInstallment - $alanShare;

        foreach ([['username' => 'alan', 'amount' => $alanShare], ['username' => 'chalo', 'amount' => $chaloShare]] as $share) {
            $staff = User::query()->where('username', $share['username'])->first();
            if (! $staff) continue;

            $balance = ProfitBalance::query()->firstOrCreate(['user_id' => $staff->id]);
            $balance->earned_amount += $share['amount'];
            $balance->available_amount += $share['amount'];
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
