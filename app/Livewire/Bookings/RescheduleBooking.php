<?php

namespace App\Livewire\Bookings;

use App\Exceptions\BookingCannotBeMovedException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Booking;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, CarbonImmutable> $availableSlots
 */
#[Title('Move booking')]
class RescheduleBooking extends Component
{
    #[Locked]
    public Booking $booking;

    /**
     * The day in the business timezone, formatted as Y-m-d.
     */
    public string $date = '';

    /**
     * The chosen start time in UTC, formatted as Y-m-d H:i:s.
     */
    public string $slot = '';

    public function mount(Booking $booking): void
    {
        $this->authorize('reschedule', $booking);

        $this->booking = $booking->load(['service', 'operator']);
        $this->date = $booking->localStartsAt()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->reset('slot');
    }

    /**
     * Free times with the same operator; the booking's own time is left out.
     *
     * @return Collection<int, CarbonImmutable>
     */
    #[Computed]
    public function availableSlots(): Collection
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            return collect();
        }

        return app(AvailabilityService::class)
            ->availableSlots($this->booking->operator, $this->booking->service, $this->date, ignoring: $this->booking)
            ->reject(fn (CarbonImmutable $slot): bool => $slot->equalTo($this->booking->starts_at))
            ->values();
    }

    public function save(BookingService $bookingService): void
    {
        $this->authorize('reschedule', $this->booking);

        $this->validate(['slot' => ['required', 'date_format:Y-m-d H:i:s']]);

        try {
            $bookingService->reschedule($this->booking, CarbonImmutable::createFromFormat('Y-m-d H:i:s', $this->slot, 'UTC'), Auth::user());
        } catch (SlotNotAvailableException|BookingCannotBeMovedException $exception) {
            $this->reset('slot');
            unset($this->availableSlots);
            $this->addError('slot', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Booking moved. We sent an email with the new time.'));

        if (Auth::user()->isAdmin()) {
            $this->redirectRoute('agenda', ['operator' => $this->booking->operator_id, 'date' => $this->date], navigate: true);

            return;
        }

        $this->redirectRoute('bookings.index', navigate: true);
    }
}
