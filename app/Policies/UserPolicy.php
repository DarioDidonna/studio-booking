<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Customer records are an admin tool.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $customer): bool
    {
        return $user->isAdmin() && $customer->hasRole(UserRole::Customer);
    }

    /**
     * Forgive past no-shows, so the customer can book online again.
     */
    public function clearNoShows(User $user, User $customer): bool
    {
        return $this->view($user, $customer);
    }
}
