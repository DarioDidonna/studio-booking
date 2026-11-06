<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rome is UTC+1 in mid-March 2027, so the local day 2027-03-15
 * runs from 2027-03-14 23:00 to 2027-03-15 23:00 UTC.
 */
class DashboardTest extends TestCase
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
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admins_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_staff_are_sent_to_their_agenda(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('agenda'));
    }

    public function test_customers_are_sent_to_their_bookings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('bookings.index'));
    }

    public function test_admins_see_todays_appointments_of_every_operator_on_the_local_day(): void
    {
        $this->booking(BookingStatus::Confirmed, '2027-03-14 23:30')->customer->update(['name' => 'Early Bird']);
        $this->booking(BookingStatus::Cancelled, '2027-03-15 15:00')->customer->update(['name' => 'Cancelled Customer']);
        $this->booking(BookingStatus::Confirmed, '2027-03-14 22:30')->customer->update(['name' => 'Yesterday Customer']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertViewHas('todayCount', 1)
            ->assertSeeInOrder(['Early Bird', 'Cancelled Customer'])
            ->assertDontSee('Yesterday Customer');
    }

    public function test_upcoming_count_covers_the_next_seven_days_of_open_bookings(): void
    {
        $this->booking(BookingStatus::Confirmed, '2027-03-15 09:00');
        $this->booking(BookingStatus::Confirmed, '2027-03-15 15:00');
        $this->booking(BookingStatus::Pending, '2027-03-21 10:00');
        $this->booking(BookingStatus::Cancelled, '2027-03-18 10:00');
        $this->booking(BookingStatus::Confirmed, '2027-03-23 10:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertViewHas('upcomingCount', 2);
    }

    public function test_month_revenue_counts_only_bookings_that_are_or_will_be_paid(): void
    {
        $this->booking(BookingStatus::Completed, '2027-03-01 09:00', 2000);
        $this->booking(BookingStatus::Confirmed, '2027-03-31 20:00', 3550);
        $this->booking(BookingStatus::Cancelled, '2027-03-20 10:00', 10000);
        $this->booking(BookingStatus::NoShow, '2027-03-10 10:00', 10000);
        $this->booking(BookingStatus::Confirmed, '2027-03-31 23:00', 10000);
        $this->booking(BookingStatus::Completed, '2027-02-28 22:30', 10000);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertViewHas('monthRevenueCents', 5550)
            ->assertSee('55,50');
    }

    public function test_no_show_rate_compares_no_shows_with_attended_appointments_of_the_last_thirty_days(): void
    {
        $this->booking(BookingStatus::NoShow, '2027-03-10 10:00');
        $this->booking(BookingStatus::Completed, '2027-03-11 10:00');
        $this->booking(BookingStatus::Completed, '2027-03-12 10:00');
        $this->booking(BookingStatus::Completed, '2027-02-14 13:00');
        $this->booking(BookingStatus::NoShow, '2027-02-10 10:00');
        $this->booking(BookingStatus::Cancelled, '2027-03-13 10:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertViewHas('noShowRate', 25)
            ->assertSee('25%');
    }

    public function test_no_show_rate_is_empty_without_past_appointments(): void
    {
        $this->booking(BookingStatus::Confirmed, '2027-03-20 10:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertViewHas('noShowRate', null);
    }

    private function booking(BookingStatus $status, string $startsAtUtc, int $priceCents = 3000): Booking
    {
        $startsAt = CarbonImmutable::parse($startsAtUtc, 'UTC');

        return Booking::factory()->create([
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'price_cents' => $priceCents,
        ]);
    }
}
