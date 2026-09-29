<?php

namespace App\Policies;

use App\Models\Borrowing;
use App\Models\User;

class BorrowingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Borrowing $borrowing): bool
    {
        return $user->isStaff() || $user->id === $borrowing->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isMember();
    }

    /**
     * Determine whether the user can manage borrowing status transitions.
     */
    public function manage(User $user): bool
    {
        return $user->isStaff();
    }
}
