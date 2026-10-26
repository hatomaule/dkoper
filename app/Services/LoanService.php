<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Borrower;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function createApprovedLoan(User $actor, int $borrowerId, int $principalAmount): Loan
    {
        return $this->createLoan($actor, $borrowerId, $principalAmount);
    }

    public function createLoan(User $actor, int $borrowerId, int $principalAmount): Loan
    {
        if (! $actor->isManager() && ! $actor->isCashier()) {
            throw new RuntimeException('User tidak memiliki akses membuat pinjaman.');
        }

        if ($principalAmount <= 0) {
            throw ValidationException::withMessages(['principal_amount' => 'Pokok pinjaman harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($actor, $borrowerId, $principalAmount) {
            $borrower = Borrower::query()->lockForUpdate()->findOrFail($borrowerId);
            if ($borrower->status !== 'active') {
                throw ValidationException::withMessages(['borrower_id' => 'Nasabah tidak aktif.']);
            }

            $administrationFee = (int) round($principalAmount * 0.07);
            $netDisbursement = $principalAmount - $administrationFee;
            $interestAmount = (int) round($principalAmount * 0.20);
            $totalReceivable = $principalAmount + $interestAmount;
            $scheduledAmount = (int) ceil($totalReceivable / 12);

            $loan = Loan::create([
                'borrower_id' => $borrowerId,
                'created_by' => $actor->id,
                'approved_by' => $actor->isManager() ? $actor->id : null,
                'loan_code' => 'TEMP_' . Str::uuid(),
                'principal_amount' => $principalAmount,
                'administration_fee' => $administrationFee,
                'net_disbursement' => $netDisbursement,
                'interest_amount' => $interestAmount,
                'total_receivable' => $totalReceivable,
                'installment_count' => 12,
                'status' => $actor->isManager() ? 'active' : 'pending',
                'approved_at' => $actor->isManager() ? now() : null,
            ]);
            $loan->update(['loan_code' => 'LN_' . str_pad((string) $loan->id, 4, '0', STR_PAD_LEFT)]);

            $cumulativeInterest = 0;
            $cumulativeOwnerProfit = 0;

            for ($sequence = 1; $sequence <= 12; $sequence++) {
                $index = $sequence - 1;
                $amount = $sequence === 12
                    ? $totalReceivable - ($scheduledAmount * 11)
                    : $scheduledAmount;
                $installmentInterest = intdiv($interestAmount, 12) + ($index < $interestAmount % 12 ? 1 : 0);
                $cumulativeInterest += $installmentInterest;

                $cumulativeOwnerTarget = intdiv($cumulativeInterest, 5) * 3
                    + intdiv(($cumulativeInterest % 5) * 3 + 2, 5);
                $ownerInstallmentProfit = $cumulativeOwnerTarget - $cumulativeOwnerProfit;
                $cumulativeOwnerProfit = $cumulativeOwnerTarget;

                Installment::create([
                    'loan_id' => $loan->id,
                    'sequence' => $sequence,
                    'scheduled_amount' => $amount,
                    'interest_amount' => $installmentInterest,
                    'alan_profit_amount' => $ownerInstallmentProfit,
                    'chalo_profit_amount' => $installmentInterest - $ownerInstallmentProfit,
                    'paid_amount' => 0,
                    'outstanding_amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            if ($actor->isManager()) {
                $this->recordDisbursement($actor, $loan);
            } else {
                ApprovalRequest::create([
                    'request_type' => 'loan',
                    'requested_by' => $actor->id,
                    'approvable_type' => Loan::class,
                    'approvable_id' => $loan->id,
                    'payload' => ['principal_amount' => $principalAmount],
                    'status' => 'pending',
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

    public function approveLoan(User $manager, int $loanId): Loan
    {
        if (! $manager->isManager()) {
            throw new RuntimeException('Hanya Manager yang dapat menyetujui pinjaman.');
        }

        return DB::transaction(function () use ($manager, $loanId) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);

            if ($loan->status !== 'pending') {
                throw ValidationException::withMessages(['loan' => 'Pinjaman tidak lagi menunggu persetujuan.']);
            }

            $loan->forceFill([
                'status' => 'active',
                'approved_by' => $manager->id,
                'approved_at' => now(),
            ])->save();

            $this->recordDisbursement($manager, $loan);
            $this->reviewApproval($manager, $loan, 'approved');

            ActivityLog::create([
                'user_id' => $manager->id,
                'action' => 'loan.approved',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'metadata' => ['principal_amount' => $loan->principal_amount],
                'ip_address' => request()?->ip(),
            ]);

            return $loan->fresh();
        });
    }

    public function rejectLoan(User $manager, int $loanId, string $reason): Loan
    {
        if (! $manager->isManager()) {
            throw new RuntimeException('Hanya Manager yang dapat menolak pinjaman.');
        }

        return DB::transaction(function () use ($manager, $loanId, $reason) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);

            if ($loan->status !== 'pending') {
                throw ValidationException::withMessages(['loan' => 'Pinjaman tidak lagi menunggu persetujuan.']);
            }

            $loan->forceFill([
                'status' => 'rejected',
                'rejected_by' => $manager->id,
                'rejected_at' => now(),
            ])->save();

            $this->reviewApproval($manager, $loan, 'rejected');
            $approval = ApprovalRequest::query()
                ->where('approvable_type', Loan::class)
                ->where('approvable_id', $loan->id)
                ->where('status', 'rejected')
                ->latest('id')
                ->first();
            if ($approval) {
                $approval->payload = array_merge((array) $approval->payload, ['rejection_reason' => $reason]);
                $approval->save();
            }

            ActivityLog::create([
                'user_id' => $manager->id,
                'action' => 'loan.rejected',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'metadata' => ['principal_amount' => $loan->principal_amount, 'rejection_reason' => $reason],
                'ip_address' => request()?->ip(),
            ]);

            return $loan->fresh();
        });
    }

    private function recordDisbursement(User $actor, Loan $loan): void
    {
        $cash = app(CashLedgerService::class);
        $cash->post(
            $actor,
            'loan_disbursement',
            'loan',
            'out',
            (int) $loan->principal_amount,
            $loan->id,
            $loan->loan_code . '-PAYOUT',
            'Pencairan pokok pinjaman bersih dan biaya administrasi dicatat terpisah',
        );
        $cash->post(
            $actor,
            'administration_fee',
            'loan',
            'in',
            (int) $loan->administration_fee,
            $loan->id,
            $loan->loan_code . '-ADMIN',
            'Pendapatan administrasi pinjaman',
        );
    }

    private function reviewApproval(User $manager, Loan $loan, string $status): void
    {
        ApprovalRequest::query()
            ->where('approvable_type', Loan::class)
            ->where('approvable_id', $loan->id)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'reviewed_by' => $manager->id,
                'reviewed_at' => now(),
            ]);
    }

}
