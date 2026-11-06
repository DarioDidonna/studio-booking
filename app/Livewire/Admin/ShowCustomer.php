<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, Booking> $bookings
 * @property-read array<string, int> $totals
 */
class ShowCustomer extends Component
{
    #[Locked]
    public User $customer;

    public function mount(User $customer): void
    {
        $this->authorize('view', $customer);

        $this->customer = $customer;
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->customer->bookings()
            ->with(['service', 'operator'])
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        $byStatus = $this->customer->bookings()
            ->toBase()
            ->selectRaw('status, count(*) as total, sum(price_cents) as price_cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $completed = $byStatus->get(BookingStatus::Completed->value);

        return [
            'completed' => (int) ($completed->total ?? 0),
            'cancelled' => (int) ($byStatus->get(BookingStatus::Cancelled->value)->total ?? 0),
            'no_shows' => (int) ($byStatus->get(BookingStatus::NoShow->value)->total ?? 0),
            'spent_cents' => (int) ($completed->price_cents ?? 0),
        ];
    }

    /**
     * Forgive the no-shows so far; only later ones count towards a new block.
     */
    public function clearNoShows(): void
    {
        $this->authorize('clearNoShows', $this->customer);

        $this->customer->forceFill(['no_shows_cleared_at' => Date::now()])->save();

        Flux::toast(variant: 'success', text: __('Online booking enabled again for :name.', ['name' => $this->customer->name]));
    }

    /**
     * Access is checked on every render, not only when the page is first opened.
     */
    public function render(): mixed
    {
        $this->authorize('view', $this->customer);

        return view('livewire.admin.show-customer')->title($this->customer->name);
    }
}
