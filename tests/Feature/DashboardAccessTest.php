<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\User;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_dashboard(): void
    {
        $manager = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Dashboard')
            ->assertSee('Saldo dan proyeksi bonus')
            ->assertSee('Debit dan kredit mingguan')
            ->assertSee('Nominal transaksi per bulan')
            ->assertSee('logout-title');
    }

    public function test_admin_dashboard_shows_owner_and_admin_bonus_totals(): void
    {
        $owner = User::factory()->create(['name' => 'Alan Owner', 'username' => 'owner', 'role' => 'manager']);
        $admin = User::factory()->create(['name' => 'Chalo Admin', 'username' => 'admin', 'role' => 'kasir']);
        ProfitBalance::create(['user_id' => $owner->id, 'earned_amount' => 12_000, 'available_amount' => 10_000]);
        ProfitBalance::create(['user_id' => $admin->id, 'earned_amount' => 8_000, 'available_amount' => 7_000]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Alan Owner')
            ->assertSee('Chalo Admin')
            ->assertSee('Rp 10.000')
            ->assertSee('Rp 7.000');
    }

    public function test_user_with_default_password_is_redirected_to_change_password(): void
    {
        $user = User::factory()->create([
            'username' => 'chalo-test',
            'role' => 'kasir',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    public function test_payment_reports_use_business_timezone_for_day_week_month_and_chart(): void
    {
        $this->travelTo(Carbon::parse('2026-01-02 00:30:00', 'Asia/Singapore'));

        $manager = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Zona Waktu',
            'borrower_code' => 'DK_200',
            'phone' => '081299998888',
            'status' => 'active',
        ]);
        $loan = app(LoanService::class)->createApprovedLoan($manager, $borrower->id, 1_200_000);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'recorded_by' => $manager->id,
            'amount' => 123_456,
            'nominal_source' => 'manual',
            'status' => 'approved',
        ]);

        $payment->forceFill([
            'created_at' => Carbon::parse('2026-01-01 16:30:00', 'UTC'),
        ])->saveQuietly();

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rp 123.456')
            ->assertSee('Rp 1.284.000')
            ->assertSee('Jan');
    }
}
