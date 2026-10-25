<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CooperativePasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_staff_password_but_admin_cannot(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'kasir']);
        $newPassword = 'Updated-Staff-Password-2026';

        $this->actingAs($owner)
            ->post(route('cooperative.staff.update-password', $admin), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('cooperative.profits'));

        $this->assertTrue(Hash::check($newPassword, $admin->fresh()->password));

        $this->actingAs($admin)
            ->post(route('cooperative.staff.update-password', $owner), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertForbidden();
    }

    public function test_owner_can_reset_borrower_password_to_default_and_require_change(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'role' => 'manager']);
        $borrowerUser = User::factory()->create(['username' => 'nasabah-test', 'role' => 'nasabah']);
        $borrower = Borrower::create([
            'user_id' => $borrowerUser->id,
            'full_name' => 'Nasabah Password',
            'borrower_code' => 'DK_900',
            'phone' => '081299990900',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('cooperative.borrowers.reset-password', $borrower))
            ->assertRedirect(route('cooperative.borrowers'));

        $this->assertTrue(Hash::check('123456', $borrowerUser->fresh()->password));
        $this->assertTrue($borrowerUser->fresh()->must_change_password);
    }
}
