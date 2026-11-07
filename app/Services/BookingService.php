<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
use App\Exceptions\BookingCannotBeMovedException;
use App\Exceptions\BookingNotStartedException;
use App\Exceptions\CancellationWindowClosedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\SlotNotAvailableException;
use App\Exceptions\TooManyNoShowsException;
use App\Exceptions\TooManyOpenBookingsException;
use App\Exceptions\TooManyUnpaidBookingsException;
use App\Jobs\RefundDeposit;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRescheduled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
     * Customers booking for themselves may hold only a few upcoming bookings;
     * their row is locked too, so two parallel requests cannot both pass the
     * limit. Customers with too many recent no-shows must book through the
     * barbershop. Admins booking at the desk are not limited.
     *
     * When deposits are on, a customer's own booking starts as pending: it
     * holds the slot for a short payment window and is confirmed, with its
     * email, only when Stripe reports the deposit paid.
     *
     * @throws SlotNotAvailableException
     * @throws TooManyOpenBookingsException
     * @throws TooManyNoShowsException
     * @throws TooManyUnpaidBookingsException
     */
    public function book(User $customer, Operator $operator, Service $service, CarbonImmutable $startsAt, User $bookedBy, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($customer, $operator, $service, $startsAt, $bookedBy, $notes): Booking {
            // Locks first, plain reads after: on MySQL the transaction's snapshot is taken at
            // the first plain read, which must already see bookings committed while we waited.
            // The order is always operator then customer, so two requests cannot deadlock.
            $lockedOperator = Operator::query()->whereKey($operator->id)->lockForUpdate()->firstOrFail();

            if (! $bookedBy->isAdmin()) {
                $lockedCustomer = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                if ($lockedCustomer->isBlockedForNoShows()) {
                    throw new TooManyNoShowsException;
                }

                if ($this->upcomingBookingsCount($customer) >= Booking::MAX_UPCOMING_PER_CUSTOMER) {
                    throw new TooManyOpenBookingsException;
                }

                if ($this->takesDeposit($bookedBy) && $this->recentUnpaidLapsesCount($customer) >= Booking::UNPAID_LAPSES_PER_DAY) {
                    throw new TooManyUnpaidBookingsException;
                }
            }

            if (! $this->isFreeSlot($lockedOperator, $service, $startsAt)) {
                throw new SlotNotAvailableException;
            }

            $reminderDueAt = $startsAt->utc()->subHours(Booking::REMINDER_HOURS);
            $takesDeposit = $this->takesDeposit($bookedBy);
            $status = $takesDeposit ? BookingStatus::Pending : BookingStatus::Confirmed;

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'operator_id' => $lockedOperator->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->utc()->addMinutes($service->duration_minutes),
                'status' => $status,
                'price_cents' => $service->price_cents,
                'deposit_cents' => $takesDeposit ? Booking::DEPOSIT_CENTS : null,
                'deposit_status' => $takesDeposit ? DepositStatus::Unpaid : null,
                'payment_due_at' => $takesDeposit ? Date::now()->addMinutes(Booking::PAYMENT_WINDOW_MINUTES) : null,
                'notes' => $notes,
                'reminder_due_at' => $reminderDueAt->isFuture() ? $reminderDueAt : null,
            ]);

            $booking->statusHistory()->create([
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $bookedBy->id,
            ]);

            if (! $takesDeposit) {
                $customer->notify(new BookingConfirmed($booking));
            }

            return $booking;
        });
    }

    /**
     * "First available barber": the operators offering the service are tried
     * from the least busy that day, each through book(), so the same locking
     * rules apply. A slot taken meanwhile simply moves on to the next operator.
     *
     * @throws SlotNotAvailableException
     * @throws TooManyOpenBookingsException
     * @throws TooManyNoShowsException
     */
    public function bookWithAnyOperator(User $customer, Service $service, CarbonImmutable $startsAt, User $bookedBy, ?string $notes = null): Booking
    {
        foreach ($this->operatorsByPreference($service, $startsAt) as $operator) {
            try {
                return $this->book($customer, $operator, $service, $startsAt, $bookedBy, $notes);
            } catch (SlotNotAvailableException) {
                continue;
            }
        }

        throw new SlotNotAvailableException;
    }

    /**
     * Who "first available barber" would give the customer at that time, so the
     * page can say it before they confirm. Same order as bookWithAnyOperator().
     */
    public function firstAvailableOperator(Service $service, CarbonImmutable $startsAt): ?Operator
    {
        return $this->operatorsByPreference($service, $startsAt)
            ->first(fn (Operator $operator): bool => $this->isFreeSlot($operator, $service, $startsAt));
    }

    /**
     * The operators offering the service, least busy that day first, so work
     * is spread evenly. Ties keep the alphabetical order.
     *
     * @return EloquentCollection<int, Operator>
     */
    private function operatorsByPreference(Service $service, CarbonImmutable $startsAt): EloquentCollection
    {
        $localDay = $startsAt->setTimezone(config('app.business_timezone'))->startOfDay();

        return $this->availability->operatorsOffering($service)
            ->loadCount(['bookings' => fn ($query) => $query
                ->whereIn('status', BookingStatus::slotOccupyingCases())
                ->whereBetweenStarts($localDay, $localDay->addDay())])
            ->sortBy('bookings_count')
            ->values();
    }

    /**
     * Move a booking to another free time with the same operator and service.
     * Customers may do it within the same window as a free cancellation.
     *
     * @throws BookingCannotBeMovedException
     * @throws SlotNotAvailableException
     */
    public function reschedule(Booking $booking, CarbonImmutable $startsAt, User $movedBy): Booking
    {
        if (! $movedBy->isAdmin() && Date::now()->greaterThanOrEqualTo($booking->freeCancellationEndsAt())) {
            throw BookingCannotBeMovedException::windowClosed();
        }

        $moved = DB::transaction(function () use ($booking, $startsAt): Booking {
            $lockedOperator = Operator::query()->whereKey($booking->operator_id)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->occupiesSlot() || $locked->starts_at->isPast()) {
                throw BookingCannotBeMovedException::notOpen();
            }

            if (! $this->isFreeSlot($lockedOperator, $locked->service, $startsAt, ignoring: $locked)) {
                throw new SlotNotAvailableException;
            }

            $reminderDueAt = $startsAt->utc()->subHours(Booking::REMINDER_HOURS);

            $locked->update([
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->utc()->addMinutes($locked->service->duration_minutes),
                'reminder_due_at' => $reminderDueAt->isFuture() ? $reminderDueAt : null,
                'reminder_sent_at' => null,
            ]);

            return $locked;
        });

        $moved->customer?->notify(new BookingRescheduled($moved));

        return $moved;
    }

    /**
     * Customers may cancel for free until the cancellation window closes;
     * admins may cancel at any time, for example when the studio closes.
     * The reason, when given, is included in the email to the customer.
     * A paid deposit is refunded: the cancellation is within the rules.
     *
     * @throws CancellationWindowClosedException
     * @throws InvalidBookingTransitionException
     */
    public function cancel(Booking $booking, User $cancelledBy, ?string $reason = null): Booking
    {
        if (! $cancelledBy->isAdmin() && Date::now()->greaterThanOrEqualTo($booking->freeCancellationEndsAt())) {
            throw new CancellationWindowClosedException;
        }

        $cancelled = $this->transition($booking, BookingStatus::Cancelled, $cancelledBy);
        $cancelled->customer?->notify(new BookingCancelled($cancelled, $reason));
        $this->refundDepositIfPaid($cancelled);

        return $cancelled;
    }

    /**
     * Stripe reported the deposit paid. A booking still pending is confirmed.
     * A payment that arrives after the booking was released, because the
     * payment window ran out or the customer cancelled, is refunded.
     * Repeated reports change nothing.
     */
    public function confirmDeposit(Booking $booking, string $paymentIntentId): Booking
    {
        $confirmed = DB::transaction(function () use ($booking, $paymentIntentId): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->deposit_status !== DepositStatus::Unpaid) {
                return $locked;
            }

            $locked->update(['deposit_status' => DepositStatus::Paid, 'stripe_payment_intent_id' => $paymentIntentId]);

            if ($locked->status !== BookingStatus::Pending) {
                return $locked;
            }

            // A reminder that fell due while the customer was paying would arrive
            // right after the confirmation: skip it, as for a last-minute booking.
            $locked->update([
                'status' => BookingStatus::Confirmed,
                'payment_due_at' => null,
                'reminder_due_at' => $locked->reminder_due_at?->isFuture() ? $locked->reminder_due_at : null,
            ]);
            $locked->statusHistory()->create([
                'from_status' => BookingStatus::Pending,
                'to_status' => BookingStatus::Confirmed,
                'changed_by' => null,
            ]);
            $locked->customer?->notify(new BookingConfirmed($locked));

            return $locked;
        });

        if ($confirmed->status === BookingStatus::Cancelled) {
            $this->refundDepositIfPaid($confirmed);
        }

        return $confirmed;
    }

    /**
     * Free the slot of a booking whose deposit was never paid. The customer
     * is emailed only when they had their chance to pay: not when the payment
     * page could not even be opened.
     *
     * @throws InvalidBookingTransitionException
     */
    public function releaseUnpaid(Booking $booking, bool $notifyCustomer = true): Booking
    {
        $released = $this->transition($booking, BookingStatus::Cancelled, null, onlyIf: fn (Booking $locked): bool => $locked->deposit_status === DepositStatus::Unpaid);

        if ($notifyCustomer) {
            $released->customer?->notify(new BookingCancelled($released, __('The deposit was not paid in time, so the time has been released.')));
        }

        return $released;
    }

    /**
     * When an operator takes time off, the upcoming open bookings it covers are
     * cancelled and their customers told why, so nobody turns up to an absent
     * barber. Past appointments are left alone: time off recorded after the
     * fact must not rewrite visits that happened.
     *
     * @return int How many bookings were cancelled.
     */
    public function cancelForTimeOff(Operator $operator, CarbonImmutable $from, CarbonImmutable $to, User $cancelledBy): int
    {
        $affected = $operator->bookings()
            ->whereIn('status', BookingStatus::slotOccupyingCases())
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->where('starts_at', '>', Date::now())
            ->get();

        $reason = __(':operator is not available on that day.', ['operator' => $operator->name]);
        $cancelled = 0;

        foreach ($affected as $booking) {
            try {
                $this->cancel($booking, $cancelledBy, $reason);
                $cancelled++;
            } catch (InvalidBookingTransitionException) {
                // Cancelled or closed by someone else since the query: nothing left to do.
            }
        }

        return $cancelled;
    }

    /**
     * @throws BookingNotStartedException
     * @throws InvalidBookingTransitionException
     */
    public function markCompleted(Booking $booking, User $changedBy): Booking
    {
        $this->ensureStarted($booking);

        return $this->transition($booking, BookingStatus::Completed, $changedBy, settleDepositAs: DepositStatus::Applied);
    }

    /**
     * @throws BookingNotStartedException
     * @throws InvalidBookingTransitionException
     */
    public function markNoShow(Booking $booking, User $changedBy): Booking
    {
        $this->ensureStarted($booking);

        return $this->transition($booking, BookingStatus::NoShow, $changedBy, settleDepositAs: DepositStatus::Kept);
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

    /**
     * Customers booking online pay a deposit when Stripe is configured;
     * admins booking at the desk never do.
     */
    public function takesDeposit(User $bookedBy): bool
    {
        return ! $bookedBy->isAdmin() && filled(config('services.stripe.secret'));
    }

    private function refundDepositIfPaid(Booking $booking): void
    {
        if ($booking->deposit_status === DepositStatus::Paid) {
            RefundDeposit::dispatch($booking);
        }
    }

    private function recentUnpaidLapsesCount(User $customer): int
    {
        return $customer->bookings()
            ->where('status', BookingStatus::Cancelled)
            ->where('deposit_status', DepositStatus::Unpaid)
            ->where('cancelled_at', '>', Date::now()->subDay())
            ->count();
    }

    private function upcomingBookingsCount(User $customer): int
    {
        return $customer->bookings()
            ->whereIn('status', BookingStatus::slotOccupyingCases())
            ->where('starts_at', '>', Date::now())
            ->count();
    }

    private function isFreeSlot(Operator $operator, Service $service, CarbonImmutable $startsAt, ?Booking $ignoring = null): bool
    {
        $localDate = $startsAt->setTimezone(config('app.business_timezone'))->toDateString();

        return $this->availability
            ->availableSlots($operator, $service, $localDate, $ignoring)
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));
    }

    /**
     * The booking row is locked and re-read, so two concurrent status changes
     * cannot both pass the transition check. A paid deposit is settled in the
     * same step: taken off the bill, or kept after a no-show.
     *
     * @param  User|null  $changedBy  Null when the system acts, for example after a Stripe event.
     * @param  (callable(Booking): bool)|null  $onlyIf  An extra check on the locked row.
     *
     * @throws InvalidBookingTransitionException
     */
    private function transition(Booking $booking, BookingStatus $to, ?User $changedBy, ?DepositStatus $settleDepositAs = null, ?callable $onlyIf = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $changedBy, $settleDepositAs, $onlyIf): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to) || ($onlyIf !== null && ! $onlyIf($locked))) {
                throw new InvalidBookingTransitionException($from, $to);
            }

            $locked->update([
                'status' => $to,
                'cancelled_at' => $to === BookingStatus::Cancelled ? Date::now() : $locked->cancelled_at,
                'deposit_status' => $settleDepositAs !== null && $locked->deposit_status === DepositStatus::Paid ? $settleDepositAs : $locked->deposit_status,
            ]);

            $locked->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $changedBy?->id,
            ]);

            return $locked;
        });
    }
}
