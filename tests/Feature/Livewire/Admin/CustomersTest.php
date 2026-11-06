<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Customers;
use App\Livewire\Admin\ShowCustomer;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_see_customer_records(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.customers'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.customers.show', $customer))->assertForbidden();
    }

    public function test_admin_who_loses_the_role_cannot_keep_searching(): void
    {
        $admin = User::factory()->admin()->create();
        $component = Livewire::actingAs($admin)->test(Customers::class);
        $admin->forceFill(['role' => 'staff'])->save();

        $component->set('search', 'luca')->assertForbidden();
    }

    public function test_admin_cannot_open_a_staff_account_as_a_customer_record(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.customers.show', User::factory()->staff()->create()))
            ->assertForbidden();
    }

    public function test_search_finds_customers_by_name_or_email_and_flags_blocked_ones(): void
    {
        $blocked = User::factory()->create(['name' => 'Luca Verdi', 'email' => 'luca@example.com']);
        Booking::factory()->count(Booking::NO_SHOW_LIMIT)->for($blocked, 'customer')->noShow()->create();
        User::factory()->create(['name' => 'Paolo Neri', 'email' => 'paolo@example.com']);
        User::factory()->staff()->create(['name' => 'Luca Barbiere']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Customers::class)
            ->set('search', 'luca')
            ->assertSee('Luca Verdi')
            ->assertSee('Bloccato')
            ->assertDontSee('Paolo Neri')
            ->assertDontSee('Luca Barbiere')
            ->set('search', 'paolo@')
            ->assertSee('Paolo Neri')
            ->assertDontSee('Luca Verdi');
    }

    public function test_last_visit_is_the_last_completed_appointment(): void
    {
        $customer = User::factory()->create(['name' => 'Luca Verdi']);
        Booking::factory()->for($customer, 'customer')->completed()->create(['starts_at' => '2026-10-01 09:00:00', 'ends_at' => '2026-10-01 10:00:00']);
        Booking::factory()->for($customer, 'customer')->cancelled()->create(['starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 10:00:00']);
        Booking::factory()->for($customer, 'customer')->confirmed()->create(['starts_at' => '2026-12-20 09:00:00', 'ends_at' => '2026-12-20 10:00:00']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Customers::class)
            ->assertSee('01/10/2026')
            ->assertDontSee('20/10/2026')
            ->assertDontSee('20/12/2026');
    }

    public function test_customer_record_shows_history_and_totals(): void
    {
        $customer = User::factory()->create();
        Booking::factory()->for($customer, 'customer')->completed()->create(['price_cents' => 2000, 'notes' => 'Sfumatura alta']);
        Booking::factory()->for($customer, 'customer')->noShow()->create(['price_cents' => 5000]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ShowCustomer::class, ['customer' => $customer])
            ->assertSee('Sfumatura alta')
            ->assertSet('totals', ['completed' => 1, 'cancelled' => 0, 'no_shows' => 1, 'spent_cents' => 2000]);
    }

    public function test_admin_enables_online_booking_again_for_a_blocked_customer(): void
    {
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::NO_SHOW_LIMIT)->for($customer, 'customer')->noShow()->create([
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHour(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ShowCustomer::class, ['customer' => $customer])
            ->assertSee('Prenotazione online sospesa')
            ->call('clearNoShows')
            ->assertDontSee('Prenotazione online sospesa');

        $this->assertFalse($customer->fresh()->isBlockedForNoShows());
    }
}
