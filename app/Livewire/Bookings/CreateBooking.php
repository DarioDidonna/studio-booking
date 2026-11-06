<?php

namespace App\Livewire\Bookings;

use App\Enums\UserRole;
use App\Exceptions\SlotNotAvailableException;
use App\Exceptions\TooManyNoShowsException;
use App\Exceptions\TooManyOpenBookingsException;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read EloquentCollection<int, Service> $services
 * @property-read EloquentCollection<int, Operator> $operators
 * @property-read Service|null $selectedService
 * @property-read Collection<int, CarbonImmutable> $availableSlots
 * @property-read CarbonImmutable|null $selectedSlot
 * @property-read EloquentCollection<int, User> $customers
 * @property-read bool $isBlockedForNoShows
 */
#[Title('Book an appointment')]
class CreateBooking extends Component
{
    /**
     * Only admins choose the customer; everybody else books for themselves.
     */
    public ?int $customerId = null;

    /**
     * The operatorId that stands for "first available barber".
     */
    public const ANY_OPERATOR = 0;

    public ?int $serviceId = null;

    /**
     * An operator id, or ANY_OPERATOR to let the barbershop pick whoever is free.
     */
    public ?int $operatorId = null;

    /**
     * The day in the business timezone, formatted as Y-m-d.
     */
    public string $date = '';

    /**
     * The chosen start time in UTC, formatted as Y-m-d H:i:s.
     */
    public string $slot = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', Booking::class);

        $this->date = Date::now()->setTimezone(config('app.business_timezone'))->toDateString();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    #[Computed]
    public function customers(): EloquentCollection
    {
        if (! Auth::user()->isAdmin()) {
            return new EloquentCollection;
        }

        return User::query()->where('role', UserRole::Customer)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * Customers with too many no-shows see why they cannot book instead of the form.
     */
    #[Computed]
    public function isBlockedForNoShows(): bool
    {
        $user = Auth::user();

        return ! $user->isAdmin() && $user->isBlockedForNoShows();
    }

    /**
     * The previous operator may not offer the new service. When only one does, there is nothing to choose.
     */
    public function updatedServiceId(): void
    {
        $this->reset('operatorId', 'slot');
        unset($this->operators);

        if ($this->operators->count() === 1) {
            $this->operatorId = $this->operators->first()->id;
        }
    }

    public function updatedOperatorId(): void
    {
        $this->reset('slot');
    }

    public function updatedDate(): void
    {
        $this->reset('slot');
    }

    /**
     * @return EloquentCollection<int, Service>
     */
    #[Computed]
    public function services(): EloquentCollection
    {
        return Service::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, Operator>
     */
    #[Computed]
    public function operators(): EloquentCollection
    {
        if ($this->selectedService === null) {
            return new EloquentCollection;
        }

        return app(AvailabilityService::class)->operatorsOffering($this->selectedService);
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        return $this->services->firstWhere('id', $this->serviceId);
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    #[Computed]
    public function availableSlots(): Collection
    {
        if ($this->selectedService === null || ! $this->isValidDate($this->date)) {
            return collect();
        }

        if ($this->operatorId === self::ANY_OPERATOR) {
            return app(AvailabilityService::class)->availableSlotsWithAnyOperator($this->selectedService, $this->date, $this->operators);
        }

        $operator = $this->operators->firstWhere('id', $this->operatorId);

        if ($operator === null) {
            return collect();
        }

        return app(AvailabilityService::class)->availableSlots($operator, $this->selectedService, $this->date);
    }

    #[Computed]
    public function selectedSlot(): ?CarbonImmutable
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($this->slot, 'Y-m-d H:i:s')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $this->slot, 'UTC');
    }

    public function book(BookingService $bookingService): void
    {
        $this->authorize('create', Booking::class);

        $this->validate([
            'serviceId' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'operatorId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'date_format:Y-m-d H:i:s'],
            'notes' => ['nullable', 'string', 'max:500'],
            'customerId' => Auth::user()->isAdmin()
                ? ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)]
                : ['prohibited'],
        ]);

        $service = Service::query()->whereKey($this->serviceId)->firstOrFail();
        $anyOperator = $this->operatorId === self::ANY_OPERATOR;
        $operator = $anyOperator ? null : $service->operators()->where('is_active', true)->whereKey($this->operatorId)->first();

        if ((! $anyOperator && $operator === null) || $this->selectedSlot === null) {
            $this->addError('operatorId', __('This operator does not offer the selected service.'));

            return;
        }

        $bookedBy = Auth::user();
        $customer = $bookedBy->isAdmin() ? User::query()->findOrFail($this->customerId) : $bookedBy;

        $notes = $this->notes !== '' ? $this->notes : null;

        try {
            $booking = $operator === null
                ? $bookingService->bookWithAnyOperator($customer, $service, $this->selectedSlot, $bookedBy, $notes)
                : $bookingService->book($customer, $operator, $service, $this->selectedSlot, $bookedBy, $notes);
        } catch (SlotNotAvailableException $exception) {
            $this->reset('slot');
            unset($this->availableSlots);
            $this->addError('slot', $exception->getMessage());

            return;
        } catch (TooManyOpenBookingsException|TooManyNoShowsException $exception) {
            $this->addError('slot', $exception->getMessage());

            return;
        }

        if ($bookedBy->isAdmin()) {
            Flux::toast(variant: 'success', text: __('Booking confirmed. :name received an email with the details.', ['name' => $customer->name]));
            $this->redirectRoute('agenda', ['operator' => $booking->operator_id, 'date' => $this->date], navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: $anyOperator
            ? __('Booking confirmed with :operator. We sent you an email with the details.', ['operator' => $booking->operator->name])
            : __('Booking confirmed. We sent you an email with the details.'));

        $this->redirectRoute('bookings.index', navigate: true);
    }

    private function isValidDate(string $date): bool
    {
        return CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d');
    }
}
