<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

/**
 * Candidate side (phase 3): a candidate only reaches their own applications.
 * Organisation access (snapshot only, submitted applications of their own
 * offers) comes with the ATS in phase 4.
 */
class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->isCandidate()
            && $application->candidate_user_id !== null
            && $application->candidate_user_id === $user->getKey();
    }

    public function update(User $user, Application $application): bool
    {
        return $this->view($user, $application) && $application->canStillBeChanged();
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $this->view($user, $application) && $application->isSubmitted();
    }

    public function delete(User $user, Application $application): bool
    {
        return false;
    }
}
