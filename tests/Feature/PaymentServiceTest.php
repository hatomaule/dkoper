<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\User;
use App\Services\LoanService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_old_installment_is_covered_by_real_payment_and_exact_profit_is_realized_when_lunas(): void
    {
        $manager = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $borrower = Borrower::create([
            'full_name' => 'Nasabah Bayar',
            'borrower_code' => 'DK_100',
            'phone' => '081299998888',
            'status' => 'active',
        ]);

        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_200_000);
        $first = $loan->installments()->orderBy('sequence')->firstOrFail();
        $second = $loan->installments()->where('sequence', 2)->firstOrFail();

        $paymentOne = Payment::create([
            'loan_id' => $loan->id,
            'installment_id' => $first->id,
            'recorded_by' => $manager->id,
            'amount' => 100_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->approvePayment($manager, $paymentOne->id);
        $this->assertSame(100_000, (int) $first->fresh()->paid_amount);

        $paymentTwo = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $manager->id,
            'amount' => $first->fresh()->outstanding_amount + 120_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->approvePayment($manager, $paymentTwo->id);

        $this->assertSame(0, (int) $first->fresh()->outstanding_amount);
        $this->assertDatabaseHas('payments', ['id' => $paymentTwo->id, 'status' => 'approved']);
        $this->assertDatabaseHas('cash_transactions', ['loan_id' => $loan->id, 'type' => 'installment_payment']);
    }

    public function test_overpayment_is_credited_to_advance_and_profit_split_totals_are_exact(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $cashier = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Lebih Bayar',
            'borrower_code' => 'DK_103',
            'phone' => '081299998883',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_000_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $cashier->id,
            'amount' => 1_215_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->approvePayment($manager, $payment->id);

        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertDatabaseHas('borrower_advance_transactions', [
            'borrower_id' => $borrower->id,
            'payment_id' => $payment->id,
            'amount' => 15_000,
            'balance_after' => 15_000,
        ]);
        $this->assertSame(120_000, (int) ProfitBalance::where('user_id', $manager->id)->value('earned_amount'));
        $this->assertSame(80_000, (int) ProfitBalance::where('user_id', $cashier->id)->value('earned_amount'));
    }

    public function test_owner_correction_rebuilds_installments_profit_advance_and_cash_ledger(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Koreksi',
            'borrower_code' => 'DK_104',
            'phone' => '081299998882',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_000_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $manager->id,
            'amount' => 240_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);
        app(PaymentService::class)->approvePayment($manager, $payment->id);
        $cashBeforeCorrection = (int) \Illuminate\Support\Facades\DB::table('cash_accounts')->where('id', 1)->value('balance');

        app(PaymentService::class)->correctApprovedPayment($manager, $payment->id, 150_000);

        $first = $loan->installments()->where('sequence', 1)->firstOrFail()->fresh();
        $second = $loan->installments()->where('sequence', 2)->firstOrFail()->fresh();
        $this->assertSame(150_000, (int) $payment->fresh()->amount);
        $this->assertSame('paid', $first->status);
        $this->assertSame(50_000, (int) $second->paid_amount);
        $this->assertSame(50_000, (int) $second->outstanding_amount);
        $this->assertSame((int) $first->alan_profit_amount, (int) ProfitBalance::where('user_id', $manager->id)->value('earned_amount'));
        $this->assertSame((int) $first->chalo_profit_amount, (int) ProfitBalance::where('user_id', $admin->id)->value('earned_amount'));
        $this->assertSame($cashBeforeCorrection - 90_000, (int) \Illuminate\Support\Facades\DB::table('cash_accounts')->where('id', 1)->value('balance'));
        $this->assertDatabaseHas('cash_transactions', ['loan_id' => $loan->id, 'type' => 'payment_correction', 'direction' => 'out', 'amount' => 90_000]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.corrected', 'entity_id' => $payment->id]);
    }

    public function test_correcting_overpayment_rebuilds_borrower_advance_balance(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Koreksi Lebih Bayar',
            'borrower_code' => 'DK_105',
            'phone' => '081299998881',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_000_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $manager->id,
            'amount' => 1_215_000,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);
        app(PaymentService::class)->approvePayment($manager, $payment->id);

        app(PaymentService::class)->correctApprovedPayment($manager, $payment->id, 1_205_000);

        $this->assertDatabaseHas('borrower_advance_transactions', [
            'borrower_id' => $borrower->id,
            'loan_id' => $loan->id,
            'payment_id' => $payment->id,
            'amount' => 5_000,
            'amount_delta' => null,
            'balance_after' => 5_000,
        ]);
    }

    public function test_owner_correction_can_move_approved_payment_to_another_borrower_contract_and_installment(): void
    {
        $manager = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $oldBorrower = Borrower::create([
            'full_name' => 'Nasabah Kontrak Lama',
            'borrower_code' => 'DK_106',
            'phone' => '081299998880',
            'status' => 'active',
        ]);
        $newBorrower = Borrower::create([
            'full_name' => 'Nasabah Kontrak Baru',
            'borrower_code' => 'DK_107',
            'phone' => '081299998879',
            'status' => 'active',
        ]);
        $oldLoan = app(LoanService::class)->createApprovedLoan($manager, $oldBorrower->id, 1_000_000);
        $newLoan = app(LoanService::class)->createApprovedLoan($manager, $newBorrower->id, 1_000_000);
        $oldInstallment = $oldLoan->installments()->where('sequence', 1)->firstOrFail();
        $newInstallment = $newLoan->installments()->where('sequence', 2)->firstOrFail();
        $payment = Payment::create([
            'loan_id' => $oldLoan->id,
            'installment_id' => $oldInstallment->id,
            'recorded_by' => $manager->id,
            'amount' => $oldInstallment->scheduled_amount,
            'nominal_source' => 'manual',
            'status' => 'pending',
        ]);
        app(PaymentService::class)->approvePayment($manager, $payment->id);

        app(PaymentService::class)->correctApprovedPayment(
            $manager,
            $payment->id,
            (int) $newInstallment->scheduled_amount,
            $newLoan->id,
            $newInstallment->id,
        );

        $this->assertSame($newLoan->id, (int) $payment->fresh()->loan_id);
        $this->assertSame($newInstallment->id, (int) $payment->fresh()->installment_id);
        $this->assertSame('pending', $oldInstallment->fresh()->status);
        $this->assertSame('paid', $newInstallment->fresh()->status);
        $this->assertDatabaseHas('cash_transactions', [
            'reference' => 'PAY-' . str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
            'loan_id' => $newLoan->id,
        ]);
    }
}
