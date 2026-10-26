<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Services\CashLedgerService;
use App\Services\LoanService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CooperativeRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_pages_share_the_five_row_table_and_card_controls(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('cooperative.approvals'))
            ->assertOk()
            ->assertSee('value="5" selected', false)
            ->assertSee('data-view-mode="cards"', false)
            ->assertSee('data-table-view="cards"', false);

        $this->actingAs($owner)
            ->get(route('cooperative.finances'))
            ->assertOk()
            ->assertSee('value="5" selected', false)
            ->assertSee('data-view-mode="cards"', false)
            ->assertSee('data-table-view="cards"', false);
    }

    public function test_admin_can_open_bonus_and_activity_but_not_owner_management_pages(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('cooperative.profits'))
            ->assertOk()
            ->assertSee('Bonus')
            ->assertDontSee('Kelola password Owner dan Admin')
            ->assertSee('Ganti password saya');

        $this->actingAs($admin)
            ->get(route('cooperative.activity-logs'))
            ->assertOk()
            ->assertSee('Log Aktivitas');

        $this->actingAs($admin)->get(route('cooperative.approvals'))->assertForbidden();
        $this->actingAs($admin)->get(route('cooperative.finances'))->assertForbidden();

        $newPassword = 'Admin-Updated-Password-2026';
        $this->actingAs($admin)
            ->post(route('cooperative.staff.update-password', $admin), [
                '_password_form' => 'self',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check($newPassword, $admin->fresh()->password));

        $this->actingAs($admin)->get(route('cooperative.borrowers'))->assertSee('Registrasi');
    }

    public function test_owner_password_management_is_not_repeated_on_bonus_page(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        User::factory()->create([
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('cooperative.profits'))
            ->assertOk()
            ->assertDontSee('Kelola password Owner dan Admin');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ganti password Admin')
            ->assertSee('Ganti password saya');
    }

    public function test_finance_chart_shows_twelve_months_and_uses_business_timezone(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $this->travelTo(Carbon::parse('2026-11-01 00:15:00', 'Asia/Singapore'));
        $entry = app(CashLedgerService::class)->post($owner, 'cash_adjustment', 'manual', 'in', 1_234_567);
        $exit = app(CashLedgerService::class)->post($owner, 'cash_adjustment', 'manual', 'out', 2_000_000);
        $entry->forceFill(['created_at' => Carbon::parse('2026-10-31 16:30:00', 'UTC')])->save();
        $exit->forceFill(['created_at' => Carbon::parse('2026-10-31 16:30:00', 'UTC')])->save();

        $response = $this->actingAs($owner)->get(route('cooperative.finances'))->assertOk();

        $this->assertSame(12, substr_count($response->getContent(), 'class="dk-finance-chart-row"'));
        $response->assertSee('Nov')
            ->assertSee('Masuk Rp 1.234.567')
            ->assertSee('Keluar Rp 2.000.000');
    }

    public function test_newest_loan_is_listed_first_and_paid_loan_stays_in_borrower_history(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $borrowerUser = User::factory()->create([
            'username' => 'nasabah-riwayat',
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'user_id' => $borrowerUser->id,
            'borrower_code' => 'DK_HISTORY',
            'full_name' => 'Nasabah Riwayat',
            'phone' => '081200000009',
            'status' => 'active',
        ]);
        $this->travelTo(Carbon::parse('2026-10-26 12:00:00', 'Asia/Singapore'));
        $olderLoan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $newerLoan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);

        $this->actingAs($owner)
            ->get(route('cooperative.loans'))
            ->assertOk()
            ->assertViewHas('loans', fn ($loans) => $loans->getCollection()->pluck('id')->all() === [$newerLoan->id, $olderLoan->id]);

        $olderLoan->forceFill(['status' => 'paid'])->save();
        $this->actingAs($borrowerUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($olderLoan->loan_code)
            ->assertSee($newerLoan->loan_code)
            ->assertSee('Lunas');
    }

    public function test_borrower_list_shows_totals_for_active_and_paid_contracts(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $borrower = Borrower::create([
            'borrower_code' => 'DK_TOTALS',
            'full_name' => 'Nasabah Total',
            'phone' => '081200000010',
            'status' => 'active',
        ]);
        $activeLoan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        $activeLoan->installments()->orderBy('sequence')->firstOrFail()->forceFill([
            'paid_amount' => 25_000,
            'outstanding_amount' => 75_000,
            'status' => 'partial',
        ])->save();

        $paidLoan = app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 500_000);
        $paidLoan->installments()->get()->each(function ($installment): void {
            $installment->forceFill([
                'paid_amount' => $installment->scheduled_amount,
                'outstanding_amount' => 0,
                'status' => 'paid',
            ])->save();
        });
        $paidLoan->forceFill(['status' => 'paid'])->save();
        app(LoanService::class)->createLoan($admin, $borrower->id, 750_000);

        $this->actingAs($owner)
            ->get(route('cooperative.borrowers'))
            ->assertOk()
            ->assertSee('Total tagihan')
            ->assertSee('Total dibayar')
            ->assertSee('Rp 1.800.000')
            ->assertSee('Rp 625.000');
    }

    public function test_borrower_is_limited_to_their_own_dashboard(): void
    {
        $borrowerUser = User::factory()->create([
            'username' => 'nasabah-test',
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        Borrower::create([
            'user_id' => $borrowerUser->id,
            'borrower_code' => 'DK_TEST',
            'full_name' => 'Nasabah Uji',
            'phone' => '081200000001',
            'status' => 'active',
        ]);
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        app(LoanService::class)->createApprovedLoan($owner, $borrowerUser->borrower->id, 1_000_000);

        $this->actingAs($borrowerUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Informasi Pinjaman Saya')
            ->assertSee('data-view-mode="cards"', false)
            ->assertSee('data-mobile-default="cards"', false)
            ->assertDontSee('data-local-page-size', false)
            ->assertSee('Cicilan ke-', false)
            ->assertSee('data-table-view="table"', false)
            ->assertSee('data-table-view="cards"', false);

        $this->actingAs($borrowerUser)->get(route('cooperative.borrowers'))->assertForbidden();
        $this->actingAs($borrowerUser)->get(route('cooperative.profits'))->assertForbidden();
        $this->actingAs($borrowerUser)->get(route('cooperative.activity-logs'))->assertForbidden();
    }
}
