<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, SchoolYear $year): bool
    {
        return $user->organization_id === $year->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, SchoolYear $year): bool
    {
        return $this->view($user, $year);
    }
}
