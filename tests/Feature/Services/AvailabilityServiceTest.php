<?php

namespace Tests\Feature\Services;

use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\TimeOff;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2027-03-15 and 2027-03-29 are Mondays. Clocks in Rome move forward on
 * 2027-03-28, so 09:00 local is 08:00 UTC on the first and 07:00 UTC on the second.
 */
class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2027-03-15';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
    }

    public function test_returns_every_quarter_hour_at_which_the_service_fits_in_the_working_hours(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '11:00');

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['09:00', '09:15', '09:30', '09:45', '10:00'], $slots);
    }

    public function test_does_not_offer_slots_that_span_the_lunch_break(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '10:30');
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '14:00:00', 'end_time' => '15:00:00']);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['09:00', '09:15', '09:30', '14:00'], $slots);
    }

    public function test_returns_no_slots_on_a_day_without_working_hours(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '18:00');

        $slots = $this->slots($operator, $service, '2027-03-16');

        $this->assertSame([], $slots);
    }

    public function test_open_booking_blocks_overlapping_slots_but_not_adjacent_ones(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '12:00');
        Booking::factory()->confirmed()->for($operator)->create([
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 10:00', 'Europe/Rome')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 10:30', 'Europe/Rome')->utc(),
        ]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['09:00', '10:30', '10:45', '11:00'], $slots);
    }

    public function test_cancelled_booking_frees_its_slot(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '10:00');
        Booking::factory()->cancelled()->for($operator)->create([
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 09:00', 'Europe/Rome')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 10:00', 'Europe/Rome')->utc(),
        ]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['09:00'], $slots);
    }

    public function test_booking_of_another_operator_does_not_block_the_slot(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '10:00');
        Booking::factory()->confirmed()->create([
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 09:00', 'Europe/Rome')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 10:00', 'Europe/Rome')->utc(),
        ]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['09:00'], $slots);
    }

    public function test_time_off_blocks_the_slots_it_covers(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 30, from: '09:00', to: '11:00');
        TimeOff::factory()->for($operator)->create([
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 09:00', 'Europe/Rome')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 10:00', 'Europe/Rome')->utc(),
        ]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['10:00', '10:15', '10:30'], $slots);
    }

    public function test_does_not_offer_slots_in_the_past(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 30, from: '09:00', to: '11:00');
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 10:05', 'Europe/Rome'));

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame(['10:15', '10:30'], $slots);
    }

    public function test_keeps_local_working_hours_across_the_daylight_saving_change(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '10:00');

        $before = app(AvailabilityService::class)->availableSlots($operator, $service, self::MONDAY);
        $after = app(AvailabilityService::class)->availableSlots($operator, $service, '2027-03-29');

        $this->assertSame('2027-03-15 08:00:00', $before->sole()->toDateTimeString());
        $this->assertSame('2027-03-29 07:00:00', $after->sole()->toDateTimeString());
    }

    public function test_returns_no_slots_for_a_service_the_operator_does_not_offer(): void
    {
        [$operator] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '18:00');

        $slots = $this->slots($operator, Service::factory()->create(), self::MONDAY);

        $this->assertSame([], $slots);
    }

    public function test_returns_no_slots_for_an_inactive_operator(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '18:00');
        $operator->update(['is_active' => false]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame([], $slots);
    }

    public function test_returns_no_slots_for_an_inactive_service(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60, from: '09:00', to: '18:00');
        $service->update(['is_active' => false]);

        $slots = $this->slots($operator, $service, self::MONDAY);

        $this->assertSame([], $slots);
    }

    /**
     * An operator who offers one service and works on Mondays between the given local times.
     *
     * @return array{Operator, Service}
     */
    private function operatorOffering(int $durationMinutes, string $from, string $to): array
    {
        $service = Service::factory()->create(['duration_minutes' => $durationMinutes]);
        $operator = Operator::factory()->hasAttached($service)->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => "{$from}:00", 'end_time' => "{$to}:00"]);

        return [$operator, $service];
    }

    /**
     * The free start times as local wall-clock times.
     *
     * @return list<string>
     */
    private function slots(Operator $operator, Service $service, string $date): array
    {
        return app(AvailabilityService::class)
            ->availableSlots($operator, $service, $date)
            ->map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Europe/Rome')->format('H:i'))->all();
    }
}
