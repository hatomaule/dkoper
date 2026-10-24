<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
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
            'username' => 'alan',
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
            'installment_id' => $second->id,
            'recorded_by' => $manager->id,
            'amount' => $first->fresh()->outstanding_amount + 120_000,
            'nominal_source' => 'system',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->approvePayment($manager, $paymentTwo->id);

        $this->assertSame(0, (int) $first->fresh()->outstanding_amount);
        $this->assertDatabaseHas('payments', ['id' => $paymentTwo->id, 'status' => 'approved']);
        $this->assertDatabaseHas('cash_transactions', ['loan_id' => $loan->id, 'type' => 'installment_payment']);
    }
}
