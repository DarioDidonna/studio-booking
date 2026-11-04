<?php

namespace App\Livewire\Staff;

use App\Exceptions\BookingNotStartedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\Operator;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Operator $operator
 * @property-read Collection<int, Operator> $operators
 * @property-read Collection<int, Booking> $bookings
 * @property-read CarbonImmutable $day
 */
#[Title('Agenda')]
class Agenda extends Component
{
    /**
     * The day in the business timezone, formatted as Y-m-d.
     */
    #[Url]
    public string $date = '';

    /**
     * Staff are pinned to their own operator profile; admins may switch.
     * The operator is authorized on every request, so tampering with it is harmless.
     */
    #[Url(as: 'operator')]
    public ?int $operatorId = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($this->operatorId === null) {
            $this->operatorId = $user->isAdmin()
                ? Operator::query()->where('is_active', true)->orderBy('name')->value('id')
                : $user->operator?->id;
        }

        abort_if($this->operatorId === null, 403);

        if (! CarbonImmutable::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            $this->date = $this->today()->toDateString();
        }
    }

    public function updatedDate(): void
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            $this->date = $this->today()->toDateString();
        }
    }

    public function previousDay(): void
    {
        $this->date = $this->day->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = $this->day->addDay()->toDateString();
    }

    public function goToToday(): void
    {
        $this->date = $this->today()->toDateString();
    }

    #[Computed]
    public function operator(): Operator
    {
        $operator = Operator::query()->findOrFail($this->operatorId);

        $this->authorize('viewAgenda', $operator);

        return $operator;
    }

    /**
     * @return Collection<int, Operator>
     */
    #[Computed]
    public function operators(): Collection
    {
        if (! Auth::user()->isAdmin()) {
            return new Collection;
        }

        return Operator::query()->orderBy('name')->get();
    }

    #[Computed]
    public function day(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->date, config('app.business_timezone'));
    }

    /**
     * Every booking of the operator that starts on the selected local day.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->operator->bookings()
            ->with(['customer', 'service'])
            ->where('starts_at', '>=', $this->day->utc())
            ->where('starts_at', '<', $this->day->addDay()->utc())
            ->orderBy('starts_at')
            ->get();
    }

    public function markCompleted(int $bookingId, BookingService $bookingService): void
    {
        $this->changeStatus($bookingId, fn (Booking $booking) => $bookingService->markCompleted($booking, Auth::user()));
    }

    public function markNoShow(int $bookingId, BookingService $bookingService): void
    {
        $this->changeStatus($bookingId, fn (Booking $booking) => $bookingService->markNoShow($booking, Auth::user()));
    }

    /**
     * @param  callable(Booking): Booking  $change
     */
    private function changeStatus(int $bookingId, callable $change): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        $this->authorize('updateStatus', $booking);

        try {
            $updated = $change($booking);
        } catch (BookingNotStartedException|InvalidBookingTransitionException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->bookings);
        Flux::toast(variant: 'success', text: __('Marked as :status.', ['status' => Str::lower($updated->status->label())]));
    }

    private function today(): CarbonImmutable
    {
        return Date::now()->setTimezone(config('app.business_timezone'));
    }
}
