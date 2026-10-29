<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_record_full_loan_payoff_using_all_remaining_installments(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Pelunasan',
            'borrower_code' => 'DK_300',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $firstInstallment = $loan->installments()->orderBy('sequence')->firstOrFail();

        $this->actingAs($owner)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'installment_id' => $firstInstallment->id,
            'nominal_source' => 'system',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cooperative.payments'));

        $remaining = (int) $loan->installments()->sum('outstanding_amount');
        $this->actingAs($owner)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'payment_type' => 'payoff',
            'nominal_source' => 'system',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cooperative.payments'));

        $payoff = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame($remaining, (int) $payoff->amount);
        $this->assertNull($payoff->installment_id);
        $this->assertSame('approved', $payoff->status);
        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertSame(0, (int) $loan->installments()->sum('outstanding_amount'));

        $this->actingAs($owner)->get(route('cooperative.payments'))
            ->assertOk()
            ->assertSee('Lunasin')
            ->assertSee('data-payment-mode-trigger="payoff"', false);
    }

    public function test_admin_full_payoff_waits_for_owner_approval_before_closing_loan(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner-payoff',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'username' => 'admin-payoff',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Admin Pelunasan',
            'borrower_code' => 'DK_299',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);

        $this->actingAs($admin)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'payment_type' => 'payoff',
            'nominal_source' => 'system',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cooperative.payments'));

        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('active', $loan->fresh()->status);

        $this->actingAs($owner)->post(route('cooperative.payments.approve', $payment))
            ->assertRedirect(route('cooperative.approvals'));

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertSame(0, (int) $loan->installments()->sum('outstanding_amount'));
    }

    public function test_admin_payoff_cannot_be_approved_after_remaining_balance_changes(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner-stale-payoff',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'username' => 'admin-stale-payoff',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Pelunasan Berubah',
            'borrower_code' => 'DK_298',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);

        $this->actingAs($admin)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'payment_type' => 'payoff',
            'nominal_source' => 'system',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cooperative.payments'));

        $payoff = Payment::query()->latest('id')->firstOrFail();
        $firstInstallment = $loan->installments()->orderBy('sequence')->firstOrFail();

        $this->actingAs($owner)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'installment_id' => $firstInstallment->id,
            'nominal_source' => 'system',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cooperative.payments'));

        $this->actingAs($owner)
            ->from(route('cooperative.approvals'))
            ->post(route('cooperative.payments.approve', $payoff))
            ->assertRedirect(route('cooperative.approvals'))
            ->assertSessionHasErrors('payment');

        $this->assertSame('pending', $payoff->fresh()->status);
        $this->assertSame('active', $loan->fresh()->status);
        $this->assertGreaterThan(0, (int) $loan->installments()->sum('outstanding_amount'));
    }

    public function test_system_nominal_uses_the_server_installment_amount_and_stores_transfer_proof(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Uji',
            'borrower_code' => 'DK_301',
            'phone' => '081234567890',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $installment = $loan->installments()->where('sequence', 2)->firstOrFail();

        $this->actingAs($owner)->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'nominal_source' => 'system',
            'installment_id' => $installment->id,
            'payment_method' => 'transfer',
            'proof' => $this->proofImage(),
        ])->assertRedirect(route('cooperative.payments'));

        $payment = \App\Models\Payment::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $installment->scheduled_amount, (int) $payment->amount);
        $this->assertSame('transfer', $payment->payment_method);
        $this->assertSame($installment->id, $payment->installment_id);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame('approved', $payment->status);

        $this->actingAs($owner)->get(route('cooperative.payments'))
            ->assertOk()
            ->assertDontSee('wa.me')
            ->assertDontSee('BUKTI PEMBAYARAN DKOPER')
            ->assertSee('Ref. DKP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('ke-2');
        $this->actingAs($owner)->get(route('cooperative.payments.proof', $payment))->assertOk();

        $admin = User::factory()->create(['username' => 'admin', 'role' => 'kasir', 'must_change_password' => false]);
        $this->actingAs($admin)->get(route('cooperative.payments.proof', $payment))->assertOk();
    }

    public function test_cash_payment_does_not_accept_a_transfer_proof(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Uji',
            'borrower_code' => 'DK_302',
            'phone' => '081234567891',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $installment = $loan->installments()->orderBy('sequence')->firstOrFail();

        $this->actingAs($owner)->from(route('cooperative.payments'))->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'installment_id' => $installment->id,
            'nominal_source' => 'manual',
            'amount' => 10_000,
            'payment_method' => 'cash',
            'proof' => $this->proofImage(),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_borrower_sees_only_own_payments_and_cannot_open_another_borrowers_proof(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'username' => 'owner-payments',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrowerUser = User::factory()->create([
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $otherBorrowerUser = User::factory()->create([
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'user_id' => $borrowerUser->id,
            'borrower_code' => 'DK_PAYMENT_OWN',
            'full_name' => 'Nasabah Pemilik',
            'phone' => '081234567811',
            'status' => 'active',
        ]);
        $otherBorrower = Borrower::create([
            'user_id' => $otherBorrowerUser->id,
            'borrower_code' => 'DK_PAYMENT_OTHER',
            'full_name' => 'Nasabah Lain',
            'phone' => '081234567812',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $otherLoan = app(LoanService::class)->createApprovedLoan($owner, $otherBorrower->id, 1_000_000);
        Storage::disk('local')->put('payments/owned-proof.png', 'owned proof');
        Storage::disk('local')->put('payments/other-proof.png', 'other proof');
        $ownedPayment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $owner->id,
            'amount' => 100_000,
            'payment_method' => 'transfer',
            'proof_path' => 'payments/owned-proof.png',
            'status' => 'approved',
        ]);
        $otherPayment = Payment::create([
            'loan_id' => $otherLoan->id,
            'recorded_by' => $owner->id,
            'amount' => 100_000,
            'payment_method' => 'transfer',
            'proof_path' => 'payments/other-proof.png',
            'status' => 'approved',
        ]);

        $this->actingAs($borrowerUser)->get(route('cooperative.payments'))
            ->assertOk()
            ->assertSee('Nasabah Pemilik')
            ->assertSee('Ref. DKP-'.str_pad((string) $ownedPayment->id, 6, '0', STR_PAD_LEFT))
            ->assertDontSee('Nasabah Lain')
            ->assertDontSee('Catat Pembayaran')
            ->assertDontSee('Lunasin');
        $this->actingAs($borrowerUser)->get(route('cooperative.payments.proof', $ownedPayment))
            ->assertOk();
        $this->actingAs($borrowerUser)->get(route('cooperative.payments.proof', $otherPayment))
            ->assertNotFound();
        $this->actingAs($borrowerUser)->post(route('cooperative.payments.store'), [])
            ->assertForbidden();
    }

    private function proofImage(): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');

        return UploadedFile::fake()->createWithContent('transfer.png', $content);
    }
}
