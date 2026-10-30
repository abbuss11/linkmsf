<?php

namespace App\Actions\Candidates;

use App\Models\CandidateDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a profile document. Applications keep their own copy (§4.3).
 */
class DeleteCandidateDocument
{
    public function handle(User $candidate, CandidateDocument $document): void
    {
        Gate::forUser($candidate)->authorize('delete', $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
    }
}
