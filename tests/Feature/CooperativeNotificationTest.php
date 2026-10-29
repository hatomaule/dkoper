<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CooperativeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_creation_notifies_owner_without_an_approval_request(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'username' => 'owner', 'must_change_password' => false]);
        $admin = User::factory()->create(['role' => 'kasir', 'username' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)->post(route('cooperative.borrowers.store'), [
            'full_name' => 'Nasabah Baru',
            'phone' => '081234567801',
        ])->assertRedirect(route('cooperative.borrowers'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'title' => 'Akun nasabah baru',
        ]);
        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_admin_loan_is_hidden_until_approval_and_notifies_responsible_users(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'username' => 'owner', 'must_change_password' => false]);
        $admin = User::factory()->create(['role' => 'kasir', 'username' => 'admin', 'must_change_password' => false]);
        $borrowerUser = User::factory()->create(['role' => 'nasabah', 'must_change_password' => false]);
        $borrower = Borrower::create([
            'user_id' => $borrowerUser->id,
            'borrower_code' => 'DK_NOTIFY_001',
            'full_name' => 'Nasabah Notifikasi',
            'phone' => '081234567802',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('cooperative.loans.store'), [
            'borrower_id' => $borrower->id,
            'principal_amount' => 500_000,
        ])->assertRedirect(route('cooperative.loans'));

        $loan = Loan::query()->latest('id')->firstOrFail();
        $this->assertSame('pending', $loan->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'title' => 'Pengajuan pinjaman baru',
        ]);
        $this->actingAs($owner)->get(route('cooperative.loans'))->assertDontSee($loan->loan_code);

        $this->actingAs($owner)->post(route('cooperative.loans.approve', $loan))
            ->assertRedirect(route('cooperative.approvals'));

        $this->assertSame('active', $loan->fresh()->status);
        $this->actingAs($owner)->get(route('cooperative.loans'))->assertSee($loan->loan_code);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Pinjaman disetujui',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $borrowerUser->id,
            'title' => 'Pinjaman disetujui',
        ]);
    }

    public function test_rejected_loan_is_audited_not_listed_and_owner_decision_is_notified(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'username' => 'owner', 'must_change_password' => false]);
        $admin = User::factory()->create(['role' => 'kasir', 'username' => 'admin', 'must_change_password' => false]);
        $borrower = Borrower::create([
            'borrower_code' => 'DK_NOTIFY_002',
            'full_name' => 'Nasabah Ditolak',
            'phone' => '081234567803',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createLoan($admin, $borrower->id, 500_000);

        $this->actingAs($owner)->post(route('cooperative.loans.reject', $loan), [
            'reason' => 'Dokumen pengajuan belum lengkap.',
        ])->assertRedirect(route('cooperative.approvals'));

        $this->actingAs($owner)->get(route('cooperative.loans'))->assertDontSee($loan->loan_code);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.rejected',
            'entity_id' => $loan->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Pengajuan pinjaman ditolak',
        ]);
        $this->assertDatabaseMissing('cash_transactions', ['loan_id' => $loan->id]);
    }

    public function test_notifications_are_private_and_can_be_opened_and_marked_read(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'must_change_password' => false]);
        $admin = User::factory()->create(['role' => 'kasir', 'must_change_password' => false]);
        $notification = UserNotification::create([
            'user_id' => $owner->id,
            'title' => 'Notifikasi privat',
            'body' => 'Detail pengajuan pinjaman.',
            'url' => route('cooperative.loans', [], false),
        ]);

        $this->actingAs($admin)
            ->post(route('cooperative.notifications.open', $notification))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('cooperative.notifications'))
            ->assertOk()
            ->assertSee('Notifikasi privat');
        $this->actingAs($owner)
            ->post(route('cooperative.notifications.open', $notification))
            ->assertRedirect(route('cooperative.loans'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_data_entry_forms_open_from_table_toolbar_modals(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'username' => 'owner', 'must_change_password' => false]);

        $this->actingAs($owner)->get(route('cooperative.borrowers'))
            ->assertOk()->assertSee('data-open-dialog="#borrower-create-dialog"', false);
        $this->actingAs($owner)->get(route('cooperative.loans'))
            ->assertOk()
            ->assertSee('data-open-dialog="#loan-create-dialog"', false)
            ->assertSee('dk-loan-cards dk-table-view', false);
        $this->actingAs($owner)->get(route('cooperative.payments'))
            ->assertOk()->assertSee('data-open-dialog="#payment-create-dialog"', false);
        $this->actingAs($owner)->get(route('cooperative.finances'))
            ->assertOk()->assertSee('data-open-dialog="#cash-adjustment-dialog"', false);
    }

    public function test_dashboard_renders_monthly_then_yearly_transaction_charts(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'username' => 'owner', 'must_change_password' => false]);

        $response = $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();

        $this->assertSame(17, substr_count($content, 'class="dk-monthly-bar-row"'));
        $this->assertLessThan(strpos($content, 'Nominal transaksi per tahun'), strpos($content, 'Nominal transaksi per bulan'));
        $response->assertSee('12 bulan terakhir')
            ->assertSee('5 tahun terakhir')
            ->assertSee('Total nominal transaksi kas bulanan')
            ->assertSee('Total nominal transaksi kas tahunan');
    }
}
