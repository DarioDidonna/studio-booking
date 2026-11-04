<?php

namespace Tests\Feature\Livewire\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\Services;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'customer' => [UserRole::Customer],
            'staff' => [UserRole::Staff],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_open_the_page(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.services'))
            ->assertForbidden();
    }

    public function test_admin_creates_a_service_with_the_price_in_cents(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Services::class)
            ->set('form.name', 'Piega')
            ->set('form.durationMinutes', '45')
            ->set('form.price', '19,99')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Piega',
            'duration_minutes' => 45,
            'price_cents' => 1999,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function prices(): array
    {
        return [
            'whole euros' => ['25', 2500],
            'dot decimals' => ['25.5', 2550],
            'comma decimals' => ['0,05', 5],
        ];
    }

    #[DataProvider('prices')]
    public function test_accepts_common_price_formats(string $typed, int $expectedCents): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Services::class)
            ->set('form.name', 'Piega')
            ->set('form.durationMinutes', '30')
            ->set('form.price', $typed)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('services', ['name' => 'Piega', 'price_cents' => $expectedCents]);
    }

    public function test_rejects_a_duration_that_is_not_a_multiple_of_five_minutes(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Services::class)
            ->set('form.name', 'Piega')
            ->set('form.durationMinutes', '42')
            ->set('form.price', '20')
            ->call('save')
            ->assertHasErrors(['form.durationMinutes' => 'multiple_of']);

        $this->assertDatabaseEmpty('services');
    }

    public function test_price_change_leaves_existing_bookings_untouched(): void
    {
        $service = Service::factory()->create(['price_cents' => 2000]);
        $booking = Booking::factory()->for($service)->create(['price_cents' => 2000]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Services::class)
            ->call('edit', $service->id)
            ->set('form.price', '30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3000, $service->fresh()->price_cents);
        $this->assertSame(2000, $booking->fresh()->price_cents);
    }

    public function test_admin_deactivates_a_service(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Services::class)
            ->call('toggleActive', $service->id);

        $this->assertFalse($service->fresh()->is_active);
    }
}
