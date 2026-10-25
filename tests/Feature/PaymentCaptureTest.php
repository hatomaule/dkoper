<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentCaptureTest extends TestCase
{
    use RefreshDatabase;

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
        $installment = $loan->installments()->orderBy('sequence')->firstOrFail();

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
            ->assertSee('wa.me/6281234567890')
            ->assertSee('ke-1');
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

        $this->actingAs($owner)->from(route('cooperative.payments'))->post(route('cooperative.payments.store'), [
            'loan_id' => $loan->id,
            'nominal_source' => 'manual',
            'amount' => 10_000,
            'payment_method' => 'cash',
            'proof' => $this->proofImage(),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    private function proofImage(): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');

        return UploadedFile::fake()->createWithContent('transfer.png', $content);
    }
}
