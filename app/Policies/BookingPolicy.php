<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Admins see every booking, customers their own, staff those on their agenda.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || $this->isOwner($user, $booking)
            || $this->isAssignedOperator($user, $booking);
    }

    /**
     * Customers book for themselves; admins book on behalf of customers.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Customer) || $user->isAdmin();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
            return false;
        }

        return $user->isAdmin() || $this->isOwner($user, $booking);
    }

    /**
     * Marking a booking completed or as a no-show is done by whoever ran the appointment.
     */
    public function updateStatus(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $this->isAssignedOperator($user, $booking);
    }

    private function isOwner(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id;
    }

    private function isAssignedOperator(User $user, Booking $booking): bool
    {
        return $user->hasRole(UserRole::Staff)
            && $booking->operator->user_id === $user->id;
    }
}
