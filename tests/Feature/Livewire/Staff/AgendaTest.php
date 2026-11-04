<?php

namespace Tests\Feature\Livewire\Staff;

use App\Enums\BookingStatus;
use App\Livewire\Staff\Agenda;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rome is UTC+1 in mid-March 2027, so the local day 2027-03-15
 * runs from 2027-03-14 23:00 to 2027-03-15 23:00 UTC.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00', 'UTC'));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('agenda'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_open_the_agenda(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('agenda'))
            ->assertForbidden();
    }

    public function test_staff_see_their_bookings_of_the_local_day_only(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $this->bookingFor($operator, '2027-03-14 23:30', 'Early Bird');
        $this->bookingFor($operator, '2027-03-14 22:30', 'Yesterday Customer');
        $this->bookingFor(Operator::factory()->create(), '2027-03-15 09:00', 'Other Operator Customer');

        Livewire::actingAs($operator->user)
            ->test(Agenda::class)
            ->assertSet('date', '2027-03-15')
            ->assertSee('Early Bird')
            ->assertSee('00:30')
            ->assertDontSee('Yesterday Customer')
            ->assertDontSee('Other Operator Customer');
    }

    public function test_staff_cannot_open_another_operators_agenda(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $otherOperator = Operator::factory()->create();

        $this->actingAs($operator->user)
            ->get(route('agenda', ['operator' => $otherOperator->id]))
            ->assertForbidden();
    }

    public function test_staff_without_an_operator_profile_are_forbidden(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('agenda'))
            ->assertForbidden();
    }

    public function test_admin_can_switch_between_operators(): void
    {
        $first = Operator::factory()->create(['name' => 'Anna']);
        $second = Operator::factory()->create(['name' => 'Bruno']);
        $this->bookingFor($second, '2027-03-15 09:00', 'Bruno Customer');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Agenda::class)
            ->assertSet('operatorId', $first->id)
            ->assertDontSee('Bruno Customer')
            ->set('operatorId', $second->id)
            ->assertSee('Bruno Customer');
    }

    public function test_staff_mark_a_started_booking_completed(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = $this->bookingFor($operator, '2027-03-15 09:00', 'Mario Rossi');

        Livewire::actingAs($operator->user)
            ->test(Agenda::class)
            ->call('markCompleted', $booking->id);

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_staff_cannot_mark_a_booking_that_has_not_started(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = $this->bookingFor($operator, '2027-03-15 15:00', 'Mario Rossi');

        Livewire::actingAs($operator->user)
            ->test(Agenda::class)
            ->call('markNoShow', $booking->id);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_staff_cannot_change_bookings_of_another_operator(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = $this->bookingFor(Operator::factory()->create(), '2027-03-15 09:00', 'Mario Rossi');

        Livewire::actingAs($operator->user)
            ->test(Agenda::class)
            ->call('markNoShow', $booking->id)
            ->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    private function bookingFor(Operator $operator, string $utcStart, string $customerName): Booking
    {
        $startsAt = CarbonImmutable::parse($utcStart, 'UTC');

        return Booking::factory()
            ->for($operator)
            ->for(User::factory()->state(['name' => $customerName]), 'customer')
            ->for(Service::factory())
            ->confirmed()
            ->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHour()]);
    }
}
