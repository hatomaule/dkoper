<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Borrower;
use App\Models\BorrowerAdvanceTransaction;
use App\Models\CashTransaction;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use App\Services\CooperativeNotificationService;
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
                app(CooperativeNotificationService::class)->notifyBorrower(
                    $borrower,
                    'Pinjaman disetujui',
                    "Pinjaman {$loan->loan_code} telah disetujui dan dicatat.",
                    route('dashboard', [], false),
                );
                app(CooperativeNotificationService::class)->notifyRoles(
                    ['kasir'],
                    'Pinjaman baru disetujui',
                    "Owner mencatat pinjaman {$loan->loan_code} untuk {$borrower->full_name}.",
                    route('cooperative.loans', [], false),
                    $actor->id,
                );
            } else {
                ApprovalRequest::create([
                    'request_type' => 'loan',
                    'requested_by' => $actor->id,
                    'approvable_type' => Loan::class,
                    'approvable_id' => $loan->id,
                    'payload' => ['principal_amount' => $principalAmount],
                    'status' => 'pending',
                ]);
                app(CooperativeNotificationService::class)->notifyRoles(
                    ['manager'],
                    'Pengajuan pinjaman baru',
                    "{$borrower->full_name} mengajukan pinjaman Rp ".number_format($principalAmount, 0, ',', '.').'.',
                    route('cooperative.approvals', [], false),
                );
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

    public function correctLoan(User $owner, int $loanId, array $data): Loan
    {
        if (! $owner->isManager()) {
            throw new RuntimeException('Hanya Owner yang dapat mengoreksi pinjaman.');
        }

        return DB::transaction(function () use ($owner, $loanId, $data) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);
            if (! in_array($loan->status, ['pending', 'active', 'paid'], true)) {
                throw ValidationException::withMessages(['loan' => 'Pinjaman dengan status ini tidak dapat dikoreksi.']);
            }

            $installments = Installment::query()
                ->where('loan_id', $loan->id)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();
            $schedule = array_values($data['installments'] ?? []);
            $sequences = $installments->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->all();
            if ($sequences !== range(1, 12) || count($schedule) !== 12) {
                throw ValidationException::withMessages(['installments' => 'Koreksi harus memuat tepat 12 nominal setoran.']);
            }

            $principal = (int) $data['principal_amount'];
            $administrationFee = (int) $data['administration_fee'];
            $interest = (int) $data['interest_amount'];
            $totalReceivable = array_sum(array_map('intval', $schedule));
            if ($principal < 1_000 || $administrationFee > $principal || $interest < 0
                || $totalReceivable !== $principal + $interest
                || min(array_map('intval', $schedule)) < 1) {
                throw ValidationException::withMessages([
                    'installments' => 'Pokok, biaya, bunga, dan total 12 setoran tidak konsisten.',
                ]);
            }

            $oldValues = [
                'principal_amount' => (int) $loan->principal_amount,
                'administration_fee' => (int) $loan->administration_fee,
                'interest_amount' => (int) $loan->interest_amount,
                'installments' => $installments->pluck('scheduled_amount')->map(fn ($amount) => (int) $amount)->all(),
            ];
            $oldNetDisbursement = (int) $loan->net_disbursement;
            $borrower = $loan->borrower()->lockForUpdate()->firstOrFail();
            $approvedPayments = Payment::query()
                ->where('loan_id', $loan->id)
                ->where('status', 'approved')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($approvedPayments as $payment) {
                if ($payment->installment_id !== null
                    && ! $installments->contains('id', (int) $payment->installment_id)) {
                    throw ValidationException::withMessages([
                        'loan' => "Setoran #{$payment->id} merujuk ke cicilan yang tidak sesuai.",
                    ]);
                }
            }

            $netEarnedByInstallment = [];
            $earnedTransactions = ProfitTransaction::query()
                ->where('loan_id', $loan->id)
                ->whereIn('type', ['earned_interest', 'correction_reversal'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
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
                    'created_by' => $owner->id,
                    'loan_id' => $loan->id,
                    'installment_id' => $installmentId === 'none' ? null : $installmentId,
                    'type' => 'correction_reversal',
                    'amount' => $amount,
                    'balance_after' => $balance->available_amount,
                    'description' => 'Pembalikan bonus karena koreksi data pinjaman',
                ]);
            }

            $loan->forceFill([
                'principal_amount' => $principal,
                'administration_fee' => $administrationFee,
                'net_disbursement' => $principal - $administrationFee,
                'interest_amount' => $interest,
                'total_receivable' => $totalReceivable,
                'installment_count' => 12,
            ])->save();

            $cumulativeInterest = 0;
            $cumulativeOwnerProfit = 0;
            foreach ($installments as $index => $installment) {
                $installmentInterest = intdiv($interest, 12) + ($index < $interest % 12 ? 1 : 0);
                $cumulativeInterest += $installmentInterest;
                $cumulativeOwnerTarget = intdiv($cumulativeInterest, 5) * 3
                    + intdiv(($cumulativeInterest % 5) * 3 + 2, 5);
                $ownerProfit = $cumulativeOwnerTarget - $cumulativeOwnerProfit;
                $cumulativeOwnerProfit = $cumulativeOwnerTarget;
                $amount = (int) $schedule[$index];

                $installment->forceFill([
                    'scheduled_amount' => $amount,
                    'interest_amount' => $installmentInterest,
                    'alan_profit_amount' => $ownerProfit,
                    'chalo_profit_amount' => $installmentInterest - $ownerProfit,
                    'paid_amount' => 0,
                    'outstanding_amount' => $amount,
                    'status' => 'pending',
                    'paid_at' => null,
                ])->save();
            }

            $previousAdvances = BorrowerAdvanceTransaction::query()
                ->where('loan_id', $loan->id)
                ->lockForUpdate()
                ->get();
            foreach ($previousAdvances as $advance) {
                $amount = (int) ($advance->amount_delta ?? $advance->amount);
                if ($amount === 0) {
                    continue;
                }
                BorrowerAdvanceTransaction::create([
                    'borrower_id' => $advance->borrower_id,
                    'loan_id' => $advance->loan_id,
                    'payment_id' => $advance->payment_id,
                    'created_by' => $owner->id,
                    'amount' => 0,
                    'amount_delta' => -$amount,
                    'balance_after' => 0,
                    'description' => 'Pembalikan uang muka karena koreksi data pinjaman',
                ]);
            }

            foreach ($approvedPayments as $payment) {
                $remaining = (int) $payment->amount;
                $eligibleInstallments = $installments->when(
                    $payment->installment_id !== null,
                    fn ($items) => $items->where('id', $payment->installment_id),
                );
                $profitActor = User::query()->find($payment->approved_by) ?? $owner;

                foreach ($eligibleInstallments as $installment) {
                    if ($remaining <= 0 || (int) $installment->outstanding_amount <= 0) {
                        continue;
                    }

                    $allocated = min($remaining, (int) $installment->outstanding_amount);
                    $installment->paid_amount = (int) $installment->paid_amount + $allocated;
                    $installment->outstanding_amount = (int) $installment->outstanding_amount - $allocated;
                    $installment->status = $installment->outstanding_amount === 0 ? 'paid' : 'partial';
                    $installment->paid_at = $installment->status === 'paid'
                        ? ($payment->approved_at ?? $payment->created_at)
                        : null;
                    $installment->save();
                    $remaining -= $allocated;

                    if ($installment->status === 'paid') {
                        $this->realizeCorrectedLoanProfit($profitActor, $loan, $installment);
                    }
                }

                if ($remaining > 0) {
                    BorrowerAdvanceTransaction::create([
                        'borrower_id' => $borrower->id,
                        'loan_id' => $loan->id,
                        'payment_id' => $payment->id,
                        'created_by' => $payment->approved_by ?? $owner->id,
                        'amount' => $remaining,
                        'amount_delta' => null,
                        'balance_after' => 0,
                        'description' => 'Kelebihan setoran setelah koreksi data pinjaman',
                    ]);
                }
            }

            $advanceBalance = 0;
            foreach ($borrower->advanceTransactions()
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get() as $advance) {
                $advanceBalance += (int) ($advance->amount_delta ?? $advance->amount);
                $advance->forceFill(['balance_after' => $advanceBalance])->save();
            }

            if ($loan->status !== 'pending') {
                $isFullyPaid = $installments->every(fn (Installment $installment) => (int) $installment->outstanding_amount === 0);
                $loan->status = $isFullyPaid ? 'paid' : 'active';
                $loan->save();
            } else {
                ApprovalRequest::query()
                    ->where('approvable_type', Loan::class)
                    ->where('approvable_id', $loan->id)
                    ->where('status', 'pending')
                    ->update(['payload' => json_encode(['principal_amount' => $principal])]);
            }

            $cashDisbursementExists = CashTransaction::query()
                ->where('loan_id', $loan->id)
                ->where('type', 'loan_disbursement')
                ->exists();
            $cashDifference = $oldNetDisbursement - (int) $loan->net_disbursement;
            if ($cashDisbursementExists && $cashDifference !== 0) {
                app(CashLedgerService::class)->post(
                    $owner,
                    'loan_correction',
                    'loan',
                    $cashDifference > 0 ? 'in' : 'out',
                    abs($cashDifference),
                    $loan->id,
                    description: 'Koreksi nilai pencairan pinjaman '.$loan->loan_code,
                );
            }

            ActivityLog::create([
                'user_id' => $owner->id,
                'action' => 'loan.corrected',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'metadata' => [
                    'borrower_id' => $loan->borrower_id,
                    'loan_code' => $loan->loan_code,
                    'old' => $oldValues,
                    'new' => [
                        'principal_amount' => $principal,
                        'administration_fee' => $administrationFee,
                        'interest_amount' => $interest,
                        'installments' => array_map('intval', $schedule),
                    ],
                ],
                'ip_address' => request()?->ip(),
            ]);

            return $loan->fresh(['installments']);
        });
    }

    public function archiveLoan(User $owner, int $loanId): Loan
    {
        if (! $owner->isManager()) {
            throw new RuntimeException('Hanya Owner yang dapat mengarsipkan pinjaman.');
        }

        return DB::transaction(function () use ($owner, $loanId) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);
            $hasPayments = Payment::query()->where('loan_id', $loan->id)->exists();
            $isNewUnpaidLoan = ! $hasPayments
                && in_array($loan->status, ['pending', 'active'], true)
                && ($loan->status === 'pending' || $loan->created_at?->greaterThanOrEqualTo(now()->subDay()));
            if ($loan->status !== 'paid' && ! $isNewUnpaidLoan) {
                throw ValidationException::withMessages([
                    'loan' => 'Pinjaman hanya dapat diarsipkan jika lunas atau baru dicatat (maksimal 24 jam) dan belum memiliki setoran.',
                ]);
            }

            ActivityLog::create([
                'user_id' => $owner->id,
                'action' => 'loan.archived',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'metadata' => [
                    'loan_code' => $loan->loan_code,
                    'borrower_id' => $loan->borrower_id,
                    'status' => $loan->status,
                    'principal_amount' => (int) $loan->principal_amount,
                    'paid_amount' => (int) $loan->installments()->sum('paid_amount'),
                ],
                'ip_address' => request()?->ip(),
            ]);

            if ($loan->status === 'pending') {
                ApprovalRequest::query()
                    ->where('approvable_type', Loan::class)
                    ->where('approvable_id', $loan->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'reviewed_by' => $owner->id,
                        'reviewed_at' => now(),
                    ]);
            }

            $loan->delete();

            return $loan;
        });
    }

    private function realizeCorrectedLoanProfit(User $actor, Loan $loan, Installment $installment): void
    {
        foreach ([
            ['username' => 'owner', 'amount' => (int) $installment->alan_profit_amount],
            ['username' => 'admin', 'amount' => (int) $installment->chalo_profit_amount],
        ] as $share) {
            $staff = User::query()->where('username', $share['username'])->lockForUpdate()->first();
            if (! $staff) {
                continue;
            }

            ProfitBalance::query()->firstOrCreate(['user_id' => $staff->id]);
            $balance = ProfitBalance::query()->where('user_id', $staff->id)->lockForUpdate()->firstOrFail();
            $balance->earned_amount = (int) $balance->earned_amount + $share['amount'];
            $balance->available_amount = (int) $balance->available_amount + $share['amount'];
            $balance->save();

            ProfitTransaction::create([
                'user_id' => $staff->id,
                'created_by' => $actor->id,
                'loan_id' => $loan->id,
                'installment_id' => $installment->id,
                'type' => 'earned_interest',
                'amount' => $share['amount'],
                'balance_after' => $balance->available_amount,
                'description' => "Bonus bunga setoran {$installment->sequence} lunas setelah koreksi pinjaman",
            ]);
        }
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
            app(CooperativeNotificationService::class)->notifyUser(
                $loan->creator,
                'Pinjaman disetujui',
                "Pinjaman {$loan->loan_code} telah disetujui oleh Owner.",
                route('cooperative.loans', [], false),
            );
            app(CooperativeNotificationService::class)->notifyBorrower(
                $loan->borrower,
                'Pinjaman disetujui',
                "Pinjaman {$loan->loan_code} telah disetujui.",
                route('dashboard', [], false),
            );

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
            app(CooperativeNotificationService::class)->notifyUser(
                $loan->creator,
                'Pengajuan pinjaman ditolak',
                "Pinjaman {$loan->loan_code} ditolak oleh Owner. Alasan: {$reason}",
                route('cooperative.loans', [], false),
            );
            app(CooperativeNotificationService::class)->notifyBorrower(
                $loan->borrower,
                'Pengajuan pinjaman ditolak',
                "Pinjaman {$loan->loan_code} ditolak. Silakan hubungi Admin atau Owner.",
                route('dashboard', [], false),
            );

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
