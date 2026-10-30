<?php

namespace App\Policies;

use App\Models\CandidateDocument;
use App\Models\User;

class CandidateDocumentPolicy
{
    public function view(User $user, CandidateDocument $document): bool
    {
        return $user->isCandidate() && $document->user_id === $user->getKey();
    }

    public function delete(User $user, CandidateDocument $document): bool
    {
        return $this->view($user, $document);
    }
}
