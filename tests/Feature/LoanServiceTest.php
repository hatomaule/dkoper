<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\CashTransaction;
use App\Models\ApprovalRequest;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_twelve_installment_loan_with_separated_administration_fee(): void
    {
        $manager = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $borrower = Borrower::create([
            'full_name' => 'Nasabah Uji',
            'borrower_code' => 'DK_001',
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_000_000);

        $this->assertSame(1_000_000, $loan->principal_amount);
        $this->assertSame(70_000, $loan->administration_fee);
        $this->assertSame(930_000, $loan->net_disbursement);
        $this->assertSame(200_000, $loan->interest_amount);
        $this->assertSame(1_200_000, $loan->total_receivable);
        $this->assertSame(12, $loan->installments()->count());
        $this->assertSame(1_200_000, (int) $loan->installments()->sum('scheduled_amount'));
        $this->assertDatabaseHas('cash_transactions', ['loan_id' => $loan->id, 'type' => 'loan_disbursement', 'amount' => 1_000_000]);
        $this->assertDatabaseHas('cash_transactions', ['loan_id' => $loan->id, 'type' => 'administration_fee', 'amount' => 70_000]);
        $this->assertSame(120_000, (int) $loan->installments()->sum('alan_profit_amount'));
        $this->assertSame(80_000, (int) $loan->installments()->sum('chalo_profit_amount'));
    }

    public function test_installment_profit_shares_follow_cumulative_sixty_forty_split(): void
    {
        $manager = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Pembulatan',
            'borrower_code' => 'DK_002',
            'status' => 'active',
        ]);

        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 500_000);
        $cumulativeInterest = 0;
        $cumulativeAlanShare = 0;

        foreach ($loan->installments()->orderBy('sequence')->get() as $installment) {
            $cumulativeInterest += (int) $installment->interest_amount;
            $cumulativeAlanShare += (int) $installment->alan_profit_amount;
            $expectedAlanShare = intdiv($cumulativeInterest, 5) * 3
                + intdiv(($cumulativeInterest % 5) * 3 + 2, 5);

            $this->assertSame((int) $installment->interest_amount, (int) $installment->alan_profit_amount + (int) $installment->chalo_profit_amount);
            $this->assertSame($expectedAlanShare, $cumulativeAlanShare);

            if ($installment->sequence === 9) {
                $this->assertSame(75_001, $cumulativeInterest);
                $this->assertSame(45_001, $cumulativeAlanShare);
            }
        }

        $this->assertSame(60_000, (int) $loan->installments()->sum('alan_profit_amount'));
        $this->assertSame(40_000, (int) $loan->installments()->sum('chalo_profit_amount'));
    }

    public function test_cashier_loan_waits_for_manager_approval_before_cash_is_posted(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $cashier = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Approval',
            'borrower_code' => 'DK_101',
            'phone' => '081299998881',
            'status' => 'active',
        ]);

        $loan = app(LoanService::class)->createLoan($cashier, $borrower->id, 1_000_000);

        $this->assertSame('pending', $loan->status);
        $this->assertDatabaseHas('approval_requests', [
            'request_type' => 'loan',
            'approvable_id' => $loan->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('cash_transactions', ['loan_id' => $loan->id]);

        app(LoanService::class)->approveLoan($manager, $loan->id);

        $this->assertSame('active', $loan->fresh()->status);
        $this->assertDatabaseHas('approval_requests', [
            'request_type' => 'loan',
            'approvable_id' => $loan->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
        ]);
        $this->assertSame(-930_000, (int) \Illuminate\Support\Facades\DB::table('cash_accounts')->value('balance'));
    }

    public function test_rejected_loan_never_posts_cash_transactions(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $cashier = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Ditolak',
            'borrower_code' => 'DK_102',
            'phone' => '081299998882',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createLoan($cashier, $borrower->id, 500_000);

        app(LoanService::class)->rejectLoan($manager, $loan->id, 'Data pengajuan tidak lengkap.');

        $this->assertSame('rejected', $loan->fresh()->status);
        $this->assertDatabaseMissing('cash_transactions', ['loan_id' => $loan->id]);
        $this->assertDatabaseHas('approval_requests', ['approvable_id' => $loan->id, 'status' => 'rejected']);
        $approval = \App\Models\ApprovalRequest::where('approvable_id', $loan->id)->firstOrFail();
        $this->assertSame('Data pengajuan tidak lengkap.', $approval->payload['rejection_reason']);
        $this->assertSame(
            'Data pengajuan tidak lengkap.',
            \App\Models\ActivityLog::where('entity_id', $loan->id)->latest('id')->firstOrFail()->metadata['rejection_reason'],
        );
    }

    public function test_rejecting_a_loan_requires_an_auditable_reason(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $cashier = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Alasan',
            'borrower_code' => 'DK_108',
            'phone' => '081299998878',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createLoan($cashier, $borrower->id, 500_000);

        $this->actingAs($manager)
            ->from(route('cooperative.approvals'))
            ->post(route('cooperative.loans.reject', $loan))
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $loan->fresh()->status);
    }

    public function test_owner_can_correct_paid_loan_and_rebuild_financial_history_without_deleting_it(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Rekonstruksi',
            'borrower_code' => 'DK_REBUILD',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $admin->id,
            'amount' => 1_200_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);
        app(\App\Services\PaymentService::class)->approvePayment($owner, $payment->id);
        $installmentAmounts = array_fill(0, 12, 83_333);
        $installmentAmounts[11] = 83_337;

        app(LoanService::class)->correctLoan($owner, $loan->id, [
            'principal_amount' => 900_000,
            'administration_fee' => 63_000,
            'interest_amount' => 100_000,
            'installments' => $installmentAmounts,
        ]);

        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertSame(1_000_000, (int) $loan->fresh()->total_receivable);
        $this->assertSame(1_000_000, (int) $loan->installments()->sum('paid_amount'));
        $this->assertSame(0, (int) $loan->installments()->sum('outstanding_amount'));
        $this->assertSame(60_000, (int) ProfitBalance::where('user_id', $owner->id)->value('earned_amount'));
        $this->assertSame(40_000, (int) ProfitBalance::where('user_id', $admin->id)->value('earned_amount'));
        $this->assertSame(200_000, (int) \App\Models\BorrowerAdvanceTransaction::where('payment_id', $payment->id)->sum('amount'));
        $this->assertDatabaseHas('cash_transactions', [
            'loan_id' => $loan->id,
            'type' => 'loan_correction',
            'direction' => 'in',
            'amount' => 93_000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.corrected',
            'entity_id' => $loan->id,
        ]);
        $this->assertSame(1, Loan::withTrashed()->whereKey($loan->id)->count());
    }

    public function test_archiving_paid_loan_keeps_payments_cash_ledger_and_activity_history(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Arsip',
            'borrower_code' => 'DK_ARCHIVE',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $owner->id,
            'amount' => 1_200_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);
        app(\App\Services\PaymentService::class)->approvePayment($owner, $payment->id);
        $cashRowsBeforeArchive = CashTransaction::where('loan_id', $loan->id)->count();

        app(LoanService::class)->archiveLoan($owner, $loan->id);

        $this->assertSoftDeleted('loans', ['id' => $loan->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'loan_id' => $loan->id]);
        $this->assertSame($cashRowsBeforeArchive, CashTransaction::where('loan_id', $loan->id)->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'loan.archived', 'entity_id' => $loan->id]);
        $this->assertSame($loan->loan_code, $payment->fresh()->loan->loan_code);
        $this->assertTrue($borrower->fresh()->loans->contains('id', $loan->id));
    }

    public function test_unpaid_loan_with_payment_history_cannot_be_archived(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Belum Lunas',
            'borrower_code' => 'DK_NOT_PAID',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $owner->id,
            'amount' => 10_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LoanService::class)->archiveLoan($owner, $loan->id);
    }

    public function test_unpaid_loan_older_than_twenty_four_hours_cannot_be_archived_without_payments(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Aktif Lama',
            'borrower_code' => 'DK_OLD_ACTIVE',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $loan->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LoanService::class)->archiveLoan($owner, $loan->id);
    }
}
