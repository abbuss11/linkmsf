<?php

namespace App\Actions\Applications;

use App\Models\ApplicationDocument;
use App\Models\User;
use App\Support\Applications\DocumentFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RemoveApplicationDocument
{
    public function handle(User $candidate, ApplicationDocument $document): void
    {
        Gate::forUser($candidate)->authorize('delete', $document);

        self::purge($document);
    }

    /**
     * Deletes the stored file, any partial upload and the row.
     */
    public static function purge(ApplicationDocument $document): void
    {
        if ($document->path !== null) {
            Storage::disk($document->disk)->delete($document->path);
        }

        @unlink(DocumentFiles::chunkPath($document->ulid));
        $document->delete();
    }
}
