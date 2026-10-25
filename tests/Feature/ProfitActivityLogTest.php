<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_from_admin_bonus_is_visible_in_activity_history(): void
    {
        $owner = User::factory()->create([
            'name' => 'Alan Owner',
            'username' => 'owner',
            'role' => 'manager',
            'must_change_password' => false,
        ]);
        $admin = User::factory()->create([
            'name' => 'Chalo Admin',
            'username' => 'admin',
            'role' => 'kasir',
            'must_change_password' => false,
        ]);

        $withdrawal = app(ProfitService::class)->withdraw($owner, $admin->id, 125_000);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'profit.withdrawn',
            'entity_id' => $withdrawal->id,
        ]);

        $this->actingAs($owner)
            ->get(route('cooperative.activity-logs'))
            ->assertOk()
            ->assertSee('Bonus ditarik')
            ->assertSee('Akun bonus: Chalo Admin')
            ->assertSee('Nominal: Rp 125.000');
    }
}
