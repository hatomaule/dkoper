<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CashTransaction;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanService
{
    public function createApprovedLoan(User $actor, int $borrowerId, int $principalAmount): Loan
    {
        if (! $actor->isManager() && ! $actor->isCashier()) {
            throw new RuntimeException('User tidak memiliki akses membuat pinjaman.');
        }

        if ($principalAmount <= 0) {
            throw new RuntimeException('Pokok pinjaman tidak valid.');
        }

        return DB::transaction(function () use ($actor, $borrowerId, $principalAmount) {
            $administrationFee = (int) round($principalAmount * 0.07);
            $netDisbursement = $principalAmount - $administrationFee;
            $interestAmount = (int) round($principalAmount * 0.20);
            $totalReceivable = $principalAmount + $interestAmount;
            $scheduledAmount = (int) ceil($totalReceivable / 12);

            $loan = Loan::create([
                'borrower_id' => $borrowerId,
                'created_by' => $actor->id,
                'approved_by' => $actor->isManager() ? $actor->id : null,
                'loan_code' => $this->nextLoanCode(),
                'principal_amount' => $principalAmount,
                'administration_fee' => $administrationFee,
                'net_disbursement' => $netDisbursement,
                'interest_amount' => $interestAmount,
                'total_receivable' => $totalReceivable,
                'installment_count' => 12,
                'status' => $actor->isManager() ? 'active' : 'pending',
                'approved_at' => $actor->isManager() ? now() : null,
            ]);

            for ($sequence = 1; $sequence <= 12; $sequence++) {
                $amount = $sequence === 12
                    ? $totalReceivable - ($scheduledAmount * 11)
                    : $scheduledAmount;

                Installment::create([
                    'loan_id' => $loan->id,
                    'sequence' => $sequence,
                    'scheduled_amount' => $amount,
                    'paid_amount' => 0,
                    'outstanding_amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            if ($actor->isManager()) {
                $lastBalance = (int) (CashTransaction::query()->latest('id')->value('balance_after') ?? 0);

                CashTransaction::create([
                    'created_by' => $actor->id,
                    'type' => 'loan_disbursement',
                    'source' => 'loan',
                    'direction' => 'out',
                    'amount' => $principalAmount,
                    'balance_after' => $lastBalance - $principalAmount,
                    'loan_id' => $loan->id,
                    'reference' => $loan->loan_code . '-PAYOUT',
                    'description' => 'Pencairan pokok pinjaman bersih dan biaya administrasi dipisahkan',
                ]);

                CashTransaction::create([
                    'created_by' => $actor->id,
                    'type' => 'administration_fee',
                    'source' => 'loan',
                    'direction' => 'in',
                    'amount' => $administrationFee,
                    'balance_after' => $lastBalance - $principalAmount + $administrationFee,
                    'loan_id' => $loan->id,
                    'reference' => $loan->loan_code . '-ADMIN',
                    'description' => 'Pendapatan administrasi pinjaman',
                ]);
            }

            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => $actor->isManager() ? 'loan.approved' : 'loan.pending',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'metadata' => [
                    'principal_amount' => $principalAmount,
                    'administration_fee' => $administrationFee,
                    'net_disbursement' => $netDisbursement,
                    'total_receivable' => $totalReceivable,
                ],
                'ip_address' => request()?->ip(),
            ]);

            return $loan;
        });
    }

    private function nextLoanCode(): string
    {
        $number = ((int) Loan::query()->max('id')) + 1;
        return 'LN_' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
