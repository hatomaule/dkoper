<?php

namespace Tests\Feature;

use App\Models\ProfitBalance;
use App\Models\User;
use App\Services\CashLedgerService;
use App\Services\ProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ProfitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_withdraw_staff_bonus_beyond_available_and_records_cash_debt(): void
    {
        $owner = $this->staff('manager', 'owner');
        $admin = $this->staff('kasir', 'admin');
        $this->balance($admin, 100_000);
        app(CashLedgerService::class)->post($owner, 'cash_adjustment', 'manual', 'in', 200_000);

        $transaction = app(ProfitService::class)->withdraw($owner, $admin->id, 150_000);

        $this->assertDatabaseHas('profit_balances', [
            'user_id' => $admin->id,
            'earned_amount' => 100_000,
            'withdrawn_amount' => 150_000,
            'available_amount' => -50_000,
        ]);
        $this->assertDatabaseHas('profit_transactions', [
            'id' => $transaction->id,
            'user_id' => $admin->id,
            'created_by' => $owner->id,
            'type' => 'withdrawal',
            'amount' => 150_000,
            'balance_after' => -50_000,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'created_by' => $owner->id,
            'type' => 'staff_withdrawal',
            'direction' => 'out',
            'amount' => 150_000,
            'balance_after' => 50_000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'profit.withdrawn',
            'entity_id' => $transaction->id,
        ]);
    }

    public function test_admin_can_withdraw_only_their_own_bonus(): void
    {
        $admin = $this->staff('kasir', 'admin');
        $owner = $this->staff('manager', 'owner');
        $this->balance($admin, 30_000);
        $this->balance($owner, 80_000);
        app(CashLedgerService::class)->post($admin, 'cash_adjustment', 'manual', 'in', 50_000);

        $this->actingAs($admin)
            ->post(route('cooperative.profits.withdraw', $admin), ['amount' => 35_000])
            ->assertRedirect(route('cooperative.profits'));

        $this->assertDatabaseHas('profit_balances', [
            'user_id' => $admin->id,
            'withdrawn_amount' => 35_000,
            'available_amount' => -5_000,
        ]);
        $this->assertDatabaseHas('profit_transactions', [
            'user_id' => $admin->id,
            'created_by' => $admin->id,
            'type' => 'withdrawal',
            'amount' => 35_000,
        ]);
        $this->actingAs($admin)
            ->get(route('cooperative.profits'))
            ->assertOk()
            ->assertSee('Catat penarikan')
            ->assertDontSee('Transfer ke modal')
            ->assertSee('Saldo tersedia / utang bonus')
            ->assertSee('Rp -5.000');
        $this->actingAs($owner)
            ->get(route('cooperative.profits'))
            ->assertOk()
            ->assertSee('Rp -5.000');
        $this->assertSame(0, (int) DB::table('profit_balances')->where('user_id', $owner->id)->value('withdrawn_amount'));
    }

    public function test_admin_cannot_withdraw_another_staff_members_bonus(): void
    {
        $admin = $this->staff('kasir', 'admin');
        $owner = $this->staff('manager', 'owner');
        $this->balance($admin, 30_000);
        $this->balance($owner, 80_000);

        try {
            app(ProfitService::class)->withdraw($admin, $owner->id, 10_000);
            $this->fail("An Admin must not withdraw another staff member's bonus.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Penarikan bonus hanya dapat dicatat Owner atau untuk bonus sendiri.', $exception->getMessage());
        }

        $this->assertDatabaseHas('profit_balances', [
            'user_id' => $owner->id,
            'withdrawn_amount' => 0,
            'available_amount' => 80_000,
        ]);
        $this->assertDatabaseCount('profit_transactions', 0);
    }

    public function test_owner_transfer_to_capital_reduces_bonus_without_double_counting_cash(): void
    {
        $owner = $this->staff('manager', 'owner');
        $admin = $this->staff('kasir', 'admin');
        $this->balance($owner, 100_000);
        $this->balance($admin, 40_000);
        $cash = app(CashLedgerService::class)->post($owner, 'cash_adjustment', 'manual', 'in', 250_000);

        $transaction = app(ProfitService::class)->transferToCapital($owner, $owner->id, 60_000);

        $this->assertDatabaseHas('profit_balances', [
            'user_id' => $owner->id,
            'earned_amount' => 100_000,
            'transferred_to_modal_amount' => 60_000,
            'available_amount' => 40_000,
        ]);
        $this->assertDatabaseHas('profit_transactions', [
            'id' => $transaction->id,
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'type' => 'transfer_to_modal',
            'amount' => 60_000,
            'balance_after' => 40_000,
        ]);
        $this->assertSame($cash->balance_after, app(CashLedgerService::class)->balance());
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'profit.transferred_to_modal',
            'entity_id' => $transaction->id,
        ]);
    }

    public function test_transfer_to_capital_cannot_exceed_available_owner_bonus(): void
    {
        $owner = $this->staff('manager', 'owner');
        $this->balance($owner, 50_000);

        try {
            app(ProfitService::class)->transferToCapital($owner, $owner->id, 50_001);
            $this->fail('A capital transfer must not exceed the available bonus.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseHas('profit_balances', [
            'user_id' => $owner->id,
            'transferred_to_modal_amount' => 0,
            'available_amount' => 50_000,
        ]);
        $this->assertDatabaseCount('profit_transactions', 0);
    }

    public function test_borrower_cannot_view_staff_bonus_or_financial_records(): void
    {
        $borrower = $this->staff('nasabah', 'borrower');

        $this->actingAs($borrower)
            ->get(route('cooperative.profits'))
            ->assertForbidden();
        $this->actingAs($borrower)
            ->get(route('cooperative.finances'))
            ->assertForbidden();
    }

    private function staff(string $role, string $username): User
    {
        return User::factory()->create([
            'name' => ucfirst($username),
            'username' => $username,
            'role' => $role,
            'must_change_password' => false,
        ]);
    }

    private function balance(User $user, int $available): ProfitBalance
    {
        return ProfitBalance::query()->create([
            'user_id' => $user->id,
            'earned_amount' => $available,
            'withdrawn_amount' => 0,
            'transferred_to_modal_amount' => 0,
            'available_amount' => $available,
        ]);
    }
}
