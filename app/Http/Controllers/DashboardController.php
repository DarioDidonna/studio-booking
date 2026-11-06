<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class DashboardController extends Controller
{
    /**
     * How far back the no-show rate looks.
     */
    private const NO_SHOW_WINDOW_DAYS = 30;

    /**
     * Customers land on their bookings, staff on their agenda, admins on the dashboard.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return to_route('bookings.index');
        }

        if ($request->user()->hasRole(UserRole::Staff)) {
            return to_route('agenda');
        }

        $now = Date::now();
        $today = $now->setTimezone(config('app.business_timezone'))->startOfDay();
        $monthStart = $today->startOfMonth();

        $todaysBookings = Booking::query()
            ->with(['customer', 'operator', 'service'])
            ->whereBetweenStarts($today, $today->addDay())
            ->orderBy('starts_at')
            ->get();

        return view('dashboard', [
            'today' => $today,
            'todaysBookings' => $todaysBookings,
            'todayCount' => $todaysBookings->reject(fn (Booking $booking): bool => $booking->status === BookingStatus::Cancelled)->count(),
            'upcomingCount' => Booking::query()
                ->whereIn('status', BookingStatus::slotOccupyingCases())
                ->whereBetweenStarts($now, $now->addDays(7))
                ->count(),
            'monthRevenueCents' => (int) Booking::query()
                ->whereIn('status', [...BookingStatus::slotOccupyingCases(), BookingStatus::Completed])
                ->whereBetweenStarts($monthStart, $monthStart->addMonth())
                ->sum('price_cents'),
            'noShowRate' => $this->noShowRate($now),
        ]);
    }

    /**
     * The share of past appointments in the window the customer did not show up to,
     * as a percentage, or null when there is nothing to measure yet.
     */
    private function noShowRate(CarbonImmutable $now): ?int
    {
        $counts = Booking::query()
            ->whereIn('status', [BookingStatus::Completed, BookingStatus::NoShow])
            ->whereBetweenStarts($now->subDays(self::NO_SHOW_WINDOW_DAYS), $now)
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as no_shows', [BookingStatus::NoShow->value])
            ->first();

        if ($counts === null || (int) $counts->total === 0) {
            return null;
        }

        return (int) round((int) $counts->no_shows / (int) $counts->total * 100);
    }
}
