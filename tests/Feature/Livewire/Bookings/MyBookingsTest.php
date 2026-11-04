<?php

namespace Tests\Feature\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Livewire\Bookings\MyBookings;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('bookings.index'))->assertRedirect(route('login'));
    }

    public function test_lists_only_the_customers_own_bookings(): void
    {
        $customer = User::factory()->create();
        Booking::factory()->for($customer, 'customer')->for(Service::factory()->state(['name' => 'Taglio']))->create();
        Booking::factory()->for(Service::factory()->state(['name' => 'Massaggio']))->create();

        $this->actingAs($customer)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Taglio')
            ->assertDontSee('Massaggio');
    }

    public function test_customer_cancels_an_upcoming_booking(): void
    {
        $booking = $this->confirmedBookingStartingIn(days: 3);

        Livewire::actingAs($booking->customer)
            ->test(MyBookings::class)
            ->call('cancel', $booking->id)
            ->assertHasNoErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_customer_cannot_cancel_another_customers_booking(): void
    {
        $booking = $this->confirmedBookingStartingIn(days: 3);

        Livewire::actingAs(User::factory()->create())
            ->test(MyBookings::class)
            ->call('cancel', $booking->id)
            ->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_late_cancellation_keeps_the_booking(): void
    {
        $booking = $this->confirmedBookingStartingIn(days: 0, hours: 5);

        Livewire::actingAs($booking->customer)
            ->test(MyBookings::class)
            ->call('cancel', $booking->id);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    private function confirmedBookingStartingIn(int $days, int $hours = 0): Booking
    {
        $startsAt = CarbonImmutable::now()->addDays($days)->addHours($hours);

        return Booking::factory()->confirmed()->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);
    }
}
