<?php

namespace App\Policies;

use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ApplicationDocumentPolicy
{
    public function view(User $user, ApplicationDocument $document): bool
    {
        return $document->isComplete() && Gate::forUser($user)->allows('view', $document->application);
    }

    public function delete(User $user, ApplicationDocument $document): bool
    {
        return Gate::forUser($user)->allows('update', $document->application);
    }
}
