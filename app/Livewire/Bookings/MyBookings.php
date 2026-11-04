<?php

namespace App\Livewire\Bookings;

use App\Exceptions\CancellationWindowClosedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Services\BookingService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Booking> $upcoming
 * @property-read Collection<int, Booking> $past
 */
#[Title('My bookings')]
class MyBookings extends Component
{
    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return Auth::user()->bookings()
            ->with(['service', 'operator'])
            ->where('starts_at', '>=', Date::now())
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function past(): Collection
    {
        return Auth::user()->bookings()
            ->with(['service', 'operator'])
            ->where('starts_at', '<', Date::now())
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();
    }

    public function cancel(int $bookingId, BookingService $bookingService): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        $this->authorize('cancel', $booking);

        try {
            $bookingService->cancel($booking, Auth::user());
        } catch (CancellationWindowClosedException|InvalidBookingTransitionException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->upcoming);
        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('Booking cancelled.'));
    }
}
