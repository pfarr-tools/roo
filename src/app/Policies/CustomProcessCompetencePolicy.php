<?php

namespace App\Policies;

use App\Models\CustomProcessCompetence;
use App\Models\User;

class CustomProcessCompetencePolicy
{
    public function view(User $user, CustomProcessCompetence $competence): bool
    {
        return $user->organization_id === $competence->school->organization_id;
    }

    public function update(User $user, CustomProcessCompetence $competence): bool
    {
        return $this->view($user, $competence);
    }

    public function delete(User $user, CustomProcessCompetence $competence): bool
    {
        return $this->view($user, $competence);
    }
}
