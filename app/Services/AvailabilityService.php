<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Computes the free start times of a service with an operator on a given day.
 *
 * Working hours are wall-clock times in the business timezone, while bookings
 * and time off are stored in UTC. Every interval is converted to UTC before it
 * is compared, so slots stay correct on daylight saving time changes.
 */
class AvailabilityService
{
    public function __construct(private int $slotIntervalMinutes = 15) {}

    /**
     * @param  string  $date  The day in the business timezone, formatted as Y-m-d.
     * @return Collection<int, CarbonImmutable> The free start times in UTC, in ascending order.
     */
    public function availableSlots(Operator $operator, Service $service, string $date): Collection
    {
        $timezone = config('app.business_timezone');
        $dayStart = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if (! $this->canPerform($operator, $service) || $this->isBeyondHorizon($dayStart)) {
            return collect();
        }

        $dayEnd = $dayStart->addDay();
        $busyIntervals = $this->busyIntervals($operator, $dayStart->utc(), $dayEnd->utc());
        $earliestStart = Date::now()->utc();

        return $operator->workingHours()
            ->where('weekday', Weekday::fromDate($dayStart))
            ->get()
            ->flatMap(fn (WorkingHour $workingHour): array => $this->slotsWithin(
                CarbonImmutable::parse("{$date} {$workingHour->start_time}", $timezone)->utc(),
                CarbonImmutable::parse("{$date} {$workingHour->end_time}", $timezone)->utc(),
                $service->duration_minutes,
            ))
            ->filter(fn (CarbonImmutable $start): bool => $start->greaterThan($earliestStart)
                && ! $this->overlapsAny($start, $start->addMinutes($service->duration_minutes), $busyIntervals))
            ->unique(fn (CarbonImmutable $start): int => $start->getTimestamp())
            ->sortBy(fn (CarbonImmutable $start): int => $start->getTimestamp())
            ->values();
    }

    /**
     * The agenda opens Booking::BOOKING_HORIZON_DAYS days ahead, counted in local days.
     */
    private function isBeyondHorizon(CarbonImmutable $dayStart): bool
    {
        $lastBookableDay = Date::now()->setTimezone($dayStart->getTimezone())->startOfDay()->addDays(Booking::BOOKING_HORIZON_DAYS);

        return $dayStart->greaterThan($lastBookableDay);
    }

    private function canPerform(Operator $operator, Service $service): bool
    {
        return $operator->is_active
            && $service->is_active
            && $operator->services()->whereKey($service->id)->exists();
    }

    /**
     * Every start time in the interval at which the whole service still fits.
     *
     * @return list<CarbonImmutable>
     */
    private function slotsWithin(CarbonImmutable $start, CarbonImmutable $end, int $durationMinutes): array
    {
        $slots = [];

        for ($slot = $start; $slot->addMinutes($durationMinutes)->lessThanOrEqualTo($end); $slot = $slot->addMinutes($this->slotIntervalMinutes)) {
            $slots[] = $slot;
        }

        return $slots;
    }

    /**
     * The open bookings and time off of the operator that overlap the day.
     *
     * @return array<int, array{CarbonImmutable, CarbonImmutable}>
     */
    private function busyIntervals(Operator $operator, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bookings = Booking::query()
            ->whereBelongsTo($operator)
            ->whereIn('status', BookingStatus::slotOccupyingCases())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        $timeOffs = $operator->timeOffs()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        return $bookings->concat($timeOffs)
            ->map(fn (Booking|TimeOff $interval): array => [
                $interval->starts_at,
                $interval->ends_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Two intervals overlap when each one starts before the other ends,
     * so a slot may end exactly when a booking starts.
     *
     * @param  array<int, array{CarbonImmutable, CarbonImmutable}>  $busyIntervals
     */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $busyIntervals): bool
    {
        foreach ($busyIntervals as [$busyStart, $busyEnd]) {
            if ($busyStart->lessThan($end) && $busyEnd->greaterThan($start)) {
                return true;
            }
        }

        return false;
    }
}
