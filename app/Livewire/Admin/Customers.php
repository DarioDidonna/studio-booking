<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, User> $customers
 */
#[Title('Customers')]
class Customers extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Searching re-renders without mount(), so access is checked on every render.
     */
    public function render(): mixed
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.customers');
    }

    /**
     * Customers with their booking totals, recent no-shows and last appointment.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->where('role', UserRole::Customer)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount([
                'bookings',
                'bookings as no_shows_count' => fn ($query) => $query->where('status', BookingStatus::NoShow),
                // Same rule as User::recentNoShowsCount(), in one query for the whole page.
                'bookings as recent_no_shows_count' => fn ($query) => $query
                    ->where('status', BookingStatus::NoShow)
                    ->where(fn ($query) => $query
                        ->whereNull('users.no_shows_cleared_at')
                        ->orWhereColumn('bookings.updated_at', '>', 'users.no_shows_cleared_at')),
            ])
            ->withMax(['bookings as last_visit_at' => fn ($query) => $query->where('status', BookingStatus::Completed)], 'starts_at')
            ->withCasts(['last_visit_at' => 'datetime'])
            ->orderBy('name')
            ->paginate(20);
    }
}
