<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->id !== null;
    }

    public function view(User $user, School $school): bool
    {
        return $user->id === $school->user_id;
    }

    public function create(User $user): bool
    {
        return $user->id !== null;
    }

    public function update(User $user, School $school): bool
    {
        return $this->view($user, $school);
    }

    public function delete(User $user, School $school): bool
    {
        return $this->view($user, $school);
    }
}
