<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_deleted_customer_leaves_past_bookings_anonymized(): void
    {
        $user = User::factory()->create();
        $pastBooking = Booking::factory()->for($user, 'customer')->completed()->create([
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHour(),
        ]);

        Livewire::actingAs($user)
            ->test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('bookings', ['id' => $pastBooking->id, 'customer_id' => null]);
    }

    public function test_customer_with_upcoming_bookings_must_cancel_them_before_deleting_the_account(): void
    {
        $user = User::factory()->create();
        Booking::factory()->for($user, 'customer')->confirmed()->create();

        Livewire::actingAs($user)
            ->test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors('account');

        $this->assertNotNull($user->fresh());
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors('account');

        $this->assertNotNull($admin->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
