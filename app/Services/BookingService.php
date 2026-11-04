<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingNotStartedException;
use App\Exceptions\CancellationWindowClosedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Creates bookings and moves them through their lifecycle.
 *
 * Authorization belongs to BookingPolicy; this class enforces the business
 * invariants that must hold whoever performs the action.
 */
class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * Book a slot without ever double-booking the operator.
     * A booking made less than a day ahead gets no reminder: the confirmation is recent enough.
     *
     * Locking the operator row serializes concurrent bookings for the same
     * operator, so the availability check and the insert act as one step.
     * Bookings for different operators do not wait for each other.
     *
     * @throws SlotNotAvailableException
     */
    public function book(User $customer, Operator $operator, Service $service, CarbonImmutable $startsAt, User $bookedBy, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($customer, $operator, $service, $startsAt, $bookedBy, $notes): Booking {
            $lockedOperator = Operator::query()->whereKey($operator->id)->lockForUpdate()->firstOrFail();

            if (! $this->isFreeSlot($lockedOperator, $service, $startsAt)) {
                throw new SlotNotAvailableException;
            }

            $reminderDueAt = $startsAt->utc()->subHours(Booking::REMINDER_HOURS);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'operator_id' => $lockedOperator->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->utc()->addMinutes($service->duration_minutes),
                'status' => BookingStatus::Confirmed,
                'price_cents' => $service->price_cents,
                'notes' => $notes,
                'reminder_due_at' => $reminderDueAt->isFuture() ? $reminderDueAt : null,
            ]);

            $booking->statusHistory()->create([
                'from_status' => null,
                'to_status' => BookingStatus::Confirmed,
                'changed_by' => $bookedBy->id,
            ]);

            $customer->notify(new BookingConfirmed($booking));

            return $booking;
        });
    }

    /**
     * Customers may cancel for free until the cancellation window closes;
     * admins may cancel at any time, for example when the studio closes.
     *
     * @throws CancellationWindowClosedException
     * @throws InvalidBookingTransitionException
     */
    public function cancel(Booking $booking, User $cancelledBy): Booking
    {
        if (! $cancelledBy->isAdmin() && Date::now()->greaterThanOrEqualTo($booking->freeCancellationEndsAt())) {
            throw new CancellationWindowClosedException;
        }

        $cancelled = $this->transition($booking, BookingStatus::Cancelled, $cancelledBy);
        $cancelled->customer->notify(new BookingCancelled($cancelled));

        return $cancelled;
    }

    /**
     * @throws BookingNotStartedException
     * @throws InvalidBookingTransitionException
     */
    public function markCompleted(Booking $booking, User $changedBy): Booking
    {
        $this->ensureStarted($booking);

        return $this->transition($booking, BookingStatus::Completed, $changedBy);
    }

    /**
     * @throws BookingNotStartedException
     * @throws InvalidBookingTransitionException
     */
    public function markNoShow(Booking $booking, User $changedBy): Booking
    {
        $this->ensureStarted($booking);

        return $this->transition($booking, BookingStatus::NoShow, $changedBy);
    }

    /**
     * Whether a customer showed up can only be known once the appointment has begun.
     *
     * @throws BookingNotStartedException
     */
    private function ensureStarted(Booking $booking): void
    {
        if ($booking->starts_at->isFuture()) {
            throw new BookingNotStartedException;
        }
    }

    private function isFreeSlot(Operator $operator, Service $service, CarbonImmutable $startsAt): bool
    {
        $localDate = $startsAt->setTimezone(config('app.business_timezone'))->toDateString();

        return $this->availability
            ->availableSlots($operator, $service, $localDate)
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));
    }

    /**
     * The booking row is locked and re-read, so two concurrent status changes
     * cannot both pass the transition check.
     *
     * @throws InvalidBookingTransitionException
     */
    private function transition(Booking $booking, BookingStatus $to, User $changedBy): Booking
    {
        return DB::transaction(function () use ($booking, $to, $changedBy): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidBookingTransitionException($from, $to);
            }

            $locked->update([
                'status' => $to,
                'cancelled_at' => $to === BookingStatus::Cancelled ? Date::now() : $locked->cancelled_at,
            ]);

            $locked->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $changedBy->id,
            ]);

            return $locked;
        });
    }
}
