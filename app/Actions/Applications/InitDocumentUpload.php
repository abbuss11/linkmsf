<?php

namespace App\Actions\Applications;

use App\Enums\FormFieldType;
use App\Exceptions\ActionFailed;
use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Support\Applications\AnswerFormatter;
use App\Support\Applications\DocumentFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Opens (or resumes) the upload of one file (§4.6): a file already received
 * is not sent again, an interrupted one resumes where it stopped.
 */
class InitDocumentUpload
{
    public const ABANDONED_AFTER_MINUTES = 30;

    public function handle(User $candidate, Application $application, string $fieldKey, string $fileName, int $size, ?string $sha256): ApplicationDocument
    {
        Gate::forUser($candidate)->authorize('update', $application);

        $field = self::fileField($application, $fieldKey);
        $sha256 = is_string($sha256) && preg_match('/^[a-f0-9]{64}$/', $sha256) ? $sha256 : null;

        if (! in_array(DocumentFiles::normalizedExtension($fileName), $field->file['extensions'], true)) {
            throw ActionFailed::because('applications.errors.file_type', ['allowed' => strtoupper(implode(', ', $field->file['extensions']))]);
        }

        if ($size < 1 || $size > $field->file['max_kb'] * 1024) {
            throw ActionFailed::because('applications.errors.file_size', ['max' => AnswerFormatter::megabytes($field->file['max_kb'])]);
        }

        return DB::transaction(function () use ($application, $field, $fileName, $size, $sha256) {
            $documents = ApplicationDocument::queryAcrossOrganizations()
                ->where('application_id', $application->getKey())
                ->where('field_key', $field->key)
                ->lockForUpdate()
                ->get();

            // Same file again (connection cut, page reloaded): resume it.
            $same = $documents->first(fn (ApplicationDocument $d) => $d->size === $size && ($sha256 !== null
                ? $d->sha256 === $sha256
                : $d->status === ApplicationDocument::UPLOADING && $d->original_name === DocumentFiles::cleanName($fileName)));

            if ($same !== null) {
                return $same;
            }

            // Uploads abandoned for a while do not block a new file.
            $abandoned = $documents->filter(fn (ApplicationDocument $d) => $d->status === ApplicationDocument::UPLOADING
                && $d->updated_at !== null && $d->updated_at->lt(now()->subMinutes(self::ABANDONED_AFTER_MINUTES)));

            foreach ($abandoned as $document) {
                @unlink(DocumentFiles::chunkPath($document->ulid));
                $document->delete();
            }

            $documents = $documents->diff($abandoned);

            if ($documents->count() >= $field->file['max_files']) {
                throw ActionFailed::because('applications.errors.too_many_files', ['max' => $field->file['max_files']]);
            }

            $document = new ApplicationDocument([
                'application_id' => $application->getKey(),
                'field_key' => $field->key,
                'status' => ApplicationDocument::UPLOADING,
                'disk' => DocumentFiles::disk(),
                'original_name' => DocumentFiles::cleanName($fileName),
                'size' => $size,
                'received_bytes' => 0,
                'sha256' => $sha256,
            ]);
            $document->organization_id = $application->organization_id;
            $document->save();

            return $document;
        });
    }

    public static function fileField(Application $application, string $fieldKey): FieldDefinition
    {
        $field = FormSchema::fromArray($application->formVersion?->schema)->find($fieldKey);

        if ($field === null || $field->type !== FormFieldType::File) {
            throw ActionFailed::because('applications.errors.unknown_field');
        }

        return $field;
    }
}
