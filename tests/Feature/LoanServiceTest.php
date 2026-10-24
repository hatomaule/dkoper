<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\CashTransaction;
use App\Models\Loan;
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
            'username' => 'alan',
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
    }
}
