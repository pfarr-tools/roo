<?php

namespace App\Policies;

use App\Models\TeachingGroup;
use App\Models\User;

class TeachingGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->id !== null;
    }

    public function view(User $user, TeachingGroup $group): bool
    {
        return $user->id === $group->user_id;
    }

    public function create(User $user): bool
    {
        return $user->id !== null;
    }

    public function update(User $user, TeachingGroup $group): bool
    {
        return $this->view($user, $group);
    }

    public function delete(User $user, TeachingGroup $group): bool
    {
        return $this->view($user, $group);
    }
}
