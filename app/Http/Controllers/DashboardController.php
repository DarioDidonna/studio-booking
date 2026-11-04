<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
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

        return view('dashboard');
    }
}
