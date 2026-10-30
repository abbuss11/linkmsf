<?php

namespace App\Actions\Applications;

use App\Exceptions\ActionFailed;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\CandidateDocument;
use App\Models\User;
use App\Support\Applications\AnswerFormatter;
use App\Support\Applications\DocumentFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Reuses a document of the profile (e.g. default CV): the file is copied,
 * so that deleting it from the profile never alters the application (§4.3).
 */
class AttachProfileDocument
{
    public function handle(User $candidate, Application $application, string $fieldKey, CandidateDocument $source): ApplicationDocument
    {
        Gate::forUser($candidate)->authorize('update', $application);
        Gate::forUser($candidate)->authorize('view', $source);

        $field = InitDocumentUpload::fileField($application, $fieldKey);

        if (! DocumentFiles::acceptsMime($field, $source->mime)) {
            throw ActionFailed::because('applications.errors.file_type', ['allowed' => strtoupper(implode(', ', $field->file['extensions']))]);
        }

        if ($source->size > $field->file['max_kb'] * 1024) {
            throw ActionFailed::because('applications.errors.file_size', ['max' => AnswerFormatter::megabytes($field->file['max_kb'])]);
        }

        return DB::transaction(function () use ($application, $field, $source) {
            $documents = ApplicationDocument::queryAcrossOrganizations()
                ->where('application_id', $application->getKey())
                ->where('field_key', $field->key)
                ->lockForUpdate()
                ->get();

            if (($same = $documents->firstWhere('sha256', $source->sha256)) !== null) {
                return $same;
            }

            if ($documents->count() >= $field->file['max_files']) {
                throw ActionFailed::because('applications.errors.too_many_files', ['max' => $field->file['max_files']]);
            }

            $temp = tempnam(sys_get_temp_dir(), 'doc');
            file_put_contents($temp, Storage::disk($source->disk)->get($source->path));

            try {
                $path = DocumentFiles::store($application, $temp, $source->mime);
            } finally {
                @unlink($temp);
            }

            $document = new ApplicationDocument([
                'application_id' => $application->getKey(),
                'field_key' => $field->key,
                'status' => ApplicationDocument::COMPLETE,
                'disk' => DocumentFiles::disk(),
                'path' => $path,
                'original_name' => $source->original_name,
                'mime' => $source->mime,
                'size' => $source->size,
                'received_bytes' => $source->size,
                'sha256' => $source->sha256,
            ]);
            $document->organization_id = $application->organization_id;
            $document->save();

            return $document;
        });
    }
}
