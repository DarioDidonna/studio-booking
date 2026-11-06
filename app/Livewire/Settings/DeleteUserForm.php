<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\BookingStatus;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user. Past bookings stay, anonymized;
     * upcoming ones must be cancelled first, and an admin cannot remove themselves.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        if ($user->isAdmin()) {
            $this->addError('account', __('An administrator cannot delete their own account.'));

            return;
        }

        $hasUpcomingBookings = $user->bookings()
            ->whereIn('status', BookingStatus::slotOccupyingCases())
            ->where('starts_at', '>', Date::now())
            ->exists();

        if ($hasUpcomingBookings) {
            $this->addError('account', __('Cancel your upcoming bookings before deleting your account, so the barbershop knows the time is free.'));

            return;
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
