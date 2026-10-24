<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_dashboard(): void
    {
        $manager = User::factory()->create([
            'username' => 'alan',
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk();
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
}
