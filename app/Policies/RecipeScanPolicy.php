<?php

namespace App\Policies;

use App\Models\RecipeScan;
use App\Models\User;

class RecipeScanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecipeScan $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, RecipeScan $scan): bool
    {
        return $user->id === $scan->user_id;
    }
}
