<?php

namespace Tests\Feature\Livewire\Bookings;

use App\Enums\Weekday;
use App\Livewire\Bookings\RescheduleBooking;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 2027-03-15 is a Monday; 10:00 in Rome is 09:00 UTC on that day.
 */
class RescheduleBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
    }

    public function test_other_customers_cannot_move_a_booking(): void
    {
        $booking = $this->bookingAt('2027-03-15 08:00');

        $this->actingAs(User::factory()->create())
            ->get(route('bookings.reschedule', $booking))
            ->assertForbidden();
    }

    public function test_cancelled_booking_cannot_be_opened_for_moving(): void
    {
        $booking = $this->bookingAt('2027-03-15 08:00');
        $booking->update(['status' => 'cancelled']);

        $this->actingAs($booking->customer)
            ->get(route('bookings.reschedule', $booking))
            ->assertForbidden();
    }

    public function test_customer_cannot_open_the_move_page_once_the_window_has_closed(): void
    {
        $booking = $this->bookingAt('2027-03-15 08:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00', 'UTC'));

        $this->actingAs($booking->customer)
            ->get(route('bookings.reschedule', $booking))
            ->assertForbidden();
    }

    public function test_past_appointment_cannot_be_moved_even_by_an_admin(): void
    {
        $booking = $this->bookingAt('2027-03-15 08:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-16 12:00', 'UTC'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('bookings.reschedule', $booking))
            ->assertForbidden();
    }

    public function test_customer_moves_their_booking_to_another_free_time(): void
    {
        $booking = $this->bookingAt('2027-03-15 08:00');

        Livewire::actingAs($booking->customer)
            ->test(RescheduleBooking::class, ['booking' => $booking])
            ->assertSet('date', '2027-03-15')
            ->assertSee('09:30')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.index'));

        $this->assertSame('2027-03-15 08:30:00', $booking->fresh()->starts_at->toDateTimeString());
    }

    /**
     * A 30-minute booking with an operator who works on Mondays from 09:00 to 10:00, Rome time.
     */
    private function bookingAt(string $utcStart): Booking
    {
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $operator = Operator::factory()->hasAttached($service)->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);
        $startsAt = CarbonImmutable::parse($utcStart, 'UTC');

        return Booking::factory()->for($operator)->for($service)->confirmed()->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
        ]);
    }
}
