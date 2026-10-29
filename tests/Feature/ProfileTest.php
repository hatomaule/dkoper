<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Borrower;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk()
            ->assertSee('Edit informasi profil')
            ->assertSee('profile-information-dialog')
            ->assertSee('data-open-dialog="#profile-information-dialog"', false)
            ->assertDontSee('Update Password');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_borrower_can_update_phone_number_and_the_borrower_record_stays_in_sync(): void
    {
        $user = User::factory()->create([
            'role' => 'nasabah',
            'phone' => '081234567801',
        ]);
        $borrower = Borrower::create([
            'user_id' => $user->id,
            'borrower_code' => 'DK_PROFILE_PHONE',
            'full_name' => $user->name,
            'phone' => $user->phone,
            'status' => 'active',
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567899',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $this->assertSame('081234567899', $user->fresh()->phone);
        $this->assertSame('081234567899', $borrower->fresh()->phone);
    }

    public function test_borrower_cannot_delete_account_and_profile_hides_delete_account_control(): void
    {
        $user = User::factory()->create(['role' => 'nasabah']);
        $borrower = Borrower::create([
            'user_id' => $user->id,
            'borrower_code' => 'DK_PROFILE_DELETE',
            'full_name' => $user->name,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Delete Account')
            ->assertDontSee('Update Password')
            ->assertSee('Ganti password saya');
        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertForbidden();

        $this->assertNotNull($user->fresh());
        $this->assertNotNull($borrower->fresh());
    }

    public function test_borrower_can_change_password_from_account_menu(): void
    {
        $user = User::factory()->create(['role' => 'nasabah']);

        $this->actingAs($user)->post(route('profile.password'), [
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('NewStrongPass123!', $user->fresh()->password));
    }
}
