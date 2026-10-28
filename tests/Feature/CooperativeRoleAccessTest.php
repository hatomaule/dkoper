<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\ProfitBalance;
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

    public function test_profile_avatar_opens_account_menu_for_every_role(): void
    {
        foreach (['manager', 'kasir', 'nasabah'] as $role) {
            $user = User::factory()->create([
                'name' => 'Akun Uji',
                'role' => $role,
                'must_change_password' => false,
            ]);

            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('aria-label="Buka menu akun"', false)
                ->assertSee('>A</button>', false)
                ->assertSee('Profil')
                ->assertSee('Keluar')
                ->assertDontSee('<circle cx="4" cy="10" r="1"/>', false);
        }
    }

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

    public function test_profit_cards_show_bonus_from_active_loans_before_withdrawals(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'borrower_code' => 'DK_PROFIT_RUNNING',
            'full_name' => 'Nasabah Bonus Berjalan',
            'phone' => '081200000015',
            'status' => 'active',
        ]);
        app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);
        ProfitBalance::create(['user_id' => $owner->id, 'earned_amount' => 120_000, 'available_amount' => 120_000]);
        ProfitBalance::create(['user_id' => $admin->id, 'earned_amount' => 80_000, 'available_amount' => 80_000]);

        $content = $this->actingAs($owner)
            ->get(route('cooperative.profits'))
            ->assertOk()
            ->assertSee('Bonus dari pinjaman berjalan')
            ->assertSee('Rp 120.000')
            ->assertSee('Rp 80.000')
            ->getContent();

        $this->assertLessThan(strpos($content, 'Sudah ditarik'), strpos($content, 'Bonus dari pinjaman berjalan'));
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

    public function test_loan_forms_only_offer_active_borrowers_not_staff_accounts(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'name' => 'Admin Staff',
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $customer = Borrower::create([
            'borrower_code' => 'DK_CUSTOMER',
            'full_name' => 'Nasabah Aktif',
            'phone' => '081200000011',
            'status' => 'active',
        ]);
        $staffBorrower = Borrower::create([
            'user_id' => $admin->id,
            'borrower_code' => 'DK_STAFF',
            'full_name' => 'Admin Staff',
            'phone' => '081200000012',
            'status' => 'active',
        ]);
        $legacyAdminUser = User::factory()->create([
            'name' => 'admin',
            'username' => 'admin1',
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $legacyAdminBorrower = Borrower::create([
            'user_id' => $legacyAdminUser->id,
            'borrower_code' => 'DK_LEGACY_ADMIN',
            'full_name' => 'admin',
            'phone' => '081200000016',
            'status' => 'active',
        ]);
        $customerLoan = app(LoanService::class)->createApprovedLoan($owner, $customer->id, 1_000_000);
        app(LoanService::class)->createApprovedLoan($owner, $staffBorrower->id, 1_000_000);
        app(LoanService::class)->createApprovedLoan($owner, $legacyAdminBorrower->id, 1_000_000);
        $firstInstallment = $customerLoan->installments()->orderBy('sequence')->firstOrFail();
        $firstInstallment->forceFill([
            'paid_amount' => 50_000,
            'outstanding_amount' => 50_000,
            'status' => 'partial',
        ])->save();

        $this->actingAs($owner)
            ->get(route('cooperative.loans'))
            ->assertOk()
            ->assertSee('Setoran')
            ->assertSee('Nasabah Aktif')
            ->assertSee('Rp 50.000')
            ->assertSee('TOTAL SELURUH DATA')
            ->assertViewHas('borrowers', fn ($borrowers) => $borrowers->pluck('id')->all() === [$customer->id])
            ->assertViewHas('loans', fn ($loans) => $loans->getCollection()->pluck('id')->all() === [$customerLoan->id]);

        $this->actingAs($owner)
            ->get(route('cooperative.payments'))
            ->assertOk()
            ->assertViewHas('paymentLoans', fn ($loans) => $loans->pluck('id')->all() === [$customerLoan->id])
            ->assertViewHas('correctionLoans', fn ($loans) => $loans->pluck('id')->all() === [$customerLoan->id]);

        $this->actingAs($owner)
            ->post(route('cooperative.loans.store'), [
                'borrower_id' => $staffBorrower->id,
                'principal_amount' => 1_000_000,
            ])
            ->assertSessionHasErrors('borrower_id');
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
            ->assertSee('Lunas')
            ->assertViewHas('borrower', fn ($loadedBorrower) => $loadedBorrower->loans->first()->installments->pluck('sequence')->all() === range(12, 1));
    }

    public function test_borrower_page_is_for_account_management_not_financial_totals(): void
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
            ->assertSee('Status akun')
            ->assertSee('Password')
            ->assertDontSee('Total tagihan')
            ->assertDontSee('Total dibayar')
            ->assertDontSee('Rp 1.800.000')
            ->assertDontSee('Rp 625.000');
    }

    public function test_owner_can_delete_customer_without_financial_history_with_confirmation_route(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $customerUser = User::factory()->create([
            'username' => 'hapus-akun',
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'user_id' => $customerUser->id,
            'borrower_code' => 'DK_DELETE',
            'full_name' => 'Nasabah Hapus',
            'phone' => '081200000013',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('cooperative.borrowers'))
            ->assertOk()
            ->assertSee('Hapus akun')
            ->assertSee('name="_method" value="DELETE"', false);

        $this->actingAs($owner)
            ->delete(route('cooperative.borrowers.destroy', $borrower))
            ->assertRedirect(route('cooperative.borrowers'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('borrowers', ['id' => $borrower->id]);
        $this->assertDatabaseMissing('users', ['id' => $customerUser->id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'borrower.deleted',
            'entity_id' => $borrower->id,
        ]);
    }

    public function test_customer_with_loan_history_cannot_be_deleted_and_admin_cannot_delete_accounts(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::create([
            'borrower_code' => 'DK_PROTECTED',
            'full_name' => 'Nasabah Riwayat',
            'phone' => '081200000014',
            'status' => 'active',
        ]);
        app(LoanService::class)->createApprovedLoan($owner, $borrower->id, 1_000_000);

        $this->actingAs($owner)
            ->delete(route('cooperative.borrowers.destroy', $borrower))
            ->assertRedirect(route('cooperative.borrowers'))
            ->assertSessionHasErrors('borrower');
        $this->assertDatabaseHas('borrowers', ['id' => $borrower->id]);

        $this->actingAs($admin)
            ->delete(route('cooperative.borrowers.destroy', $borrower))
            ->assertForbidden();
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
