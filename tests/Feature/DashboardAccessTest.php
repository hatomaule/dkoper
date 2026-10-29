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

        $dashboard = $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Dashboard')
            ->assertSee('dk-dashboard-heading', false)
            ->assertSee('Saldo dan proyeksi bonus')
            ->assertSee('Nominal transaksi per bulan')
            ->assertSee('Nominal transaksi per tahun')
            ->assertDontSee('Debit dan kredit mingguan')
            ->assertSee('logout-title')
            ->assertDontSee('data-platform-auto-refresh')
            ->assertSee('dk-owner-theme', false)
            ->assertSee('FIDES')
            ->assertSee('class="dk-brand-platform-tag">PLATFORM</span>', false)
            ->assertDontSee('<small>Owner</small>', false)
            ->assertDontSee('<img src="'.asset('logo.svg').'" class="dk-brand-mark"', false);

        $content = $dashboard->getContent();
        $this->assertLessThan(strpos($content, 'Saldo dan proyeksi bonus'), strpos($content, 'Laporan pemasukan'));
    }

    public function test_admin_dashboard_shows_only_three_admin_bonus_cards(): void
    {
        $owner = User::factory()->create(['name' => 'Alan Owner', 'username' => 'owner', 'role' => 'manager']);
        $admin = User::factory()->create(['name' => 'Chalo Admin', 'username' => 'admin', 'role' => 'kasir']);
        ProfitBalance::create(['user_id' => $owner->id, 'earned_amount' => 12_000, 'available_amount' => 10_000]);
        ProfitBalance::create(['user_id' => $admin->id, 'earned_amount' => 8_000, 'withdrawn_amount' => 9_000, 'available_amount' => -1_000]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('BONUS ADMIN')
            ->assertSee('Bonus dari hasil pembayaran nasabah')
            ->assertSee('Rp 8.000')
            ->assertSee('Sudah ditarik')
            ->assertSee('Rp 9.000')
            ->assertSee('Utang bonus')
            ->assertSee('Rp -1.000')
            ->assertDontSee('Saldo dan proyeksi bonus')
            ->assertDontSee('Alan Owner')
            ->assertDontSee('TOTAL BONUS KESELURUHAN')
            ->assertDontSee('data-platform-auto-refresh')
            ->assertSee('dk-owner-theme', false)
            ->assertSee('FIDES')
            ->assertSee('class="dk-brand-platform-tag">PLATFORM</span>', false)
            ->assertDontSee('COOPERATIVE FINANCE')
            ->assertSee('<small>Admin</small>', false)
            ->assertDontSee('<img src="'.asset('logo.svg').'" class="dk-brand-mark"', false);
    }

    public function test_dashboard_counts_only_customer_accounts_with_loan_history(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        User::factory()->create([
            'name' => 'Nasabah Tanpa Pinjaman',
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $loanBorrower = Borrower::create([
            'full_name' => 'Nasabah Dengan Pinjaman',
            'borrower_code' => 'DK_DASHBOARD_LOAN',
            'status' => 'active',
        ]);
        Borrower::create([
            'full_name' => 'admin',
            'borrower_code' => 'DK_DASHBOARD_ADMIN',
            'status' => 'active',
        ]);

        $loan = app(LoanService::class)->createApprovedLoan($owner, $loanBorrower->id, 1_000_000);
        $loan->delete();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('1 orang');
    }

    public function test_dashboard_shows_realized_and_projected_bonus_as_the_overall_total(): void
    {
        $owner = User::factory()->create([
            'name' => 'Alan Owner',
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        User::factory()->create([
            'name' => 'Chalo Admin',
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'full_name' => 'Nasabah Bonus',
            'borrower_code' => 'DK_BONUS_TOTAL',
            'phone' => '081299998887',
            'status' => 'active',
        ]);
        app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        ProfitBalance::create(['user_id' => $owner->id, 'earned_amount' => 12_000, 'available_amount' => 10_000]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('TOTAL BONUS KESELURUHAN')
            ->assertSee('Terealisasi Rp 12.000 + proyeksi pinjaman aktif Rp 120.000')
            ->assertSee('Rp 132.000')
            ->assertSee('SALDO BONUS TERSEDIA')
            ->assertSee('Rp 10.000');
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
