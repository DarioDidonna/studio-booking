<?php

namespace App\Policies;

use App\Models\Operator;
use App\Models\User;

/**
 * Operators, their working hours and their time off are managed by admins only.
 */
class OperatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Operator $operator): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Operator $operator): bool
    {
        return $user->isAdmin();
    }

    /**
     * Staff see their own agenda; admins see everyone's.
     */
    public function viewAgenda(User $user, Operator $operator): bool
    {
        return $user->isAdmin() || ($operator->user_id !== null && $operator->user_id === $user->id);
    }
}
