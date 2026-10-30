<?php

namespace App\Actions\Applications;

use App\Exceptions\ActionFailed;
use App\Exceptions\UploadOffsetMismatch;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Support\Applications\DocumentFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

/**
 * Appends one chunk. When the file is complete, checks its fingerprint and
 * real MIME type, then stores it privately under a UUID name (§10.1).
 */
class ReceiveDocumentChunk
{
    public function handle(User $candidate, ApplicationDocument $document, int $offset, string $bytes): ApplicationDocument
    {
        /** @var Application $application */
        $application = $document->application;
        Gate::forUser($candidate)->authorize('update', $application);

        $maxChunk = (int) config('link.applications.chunk_size_kb') * 1024;

        [$document, $failure] = DB::transaction(function () use ($application, $document, $offset, $bytes, $maxChunk) {
            /** @var ApplicationDocument $document */
            $document = ApplicationDocument::queryAcrossOrganizations()->lockForUpdate()->findOrFail($document->getKey());

            if ($document->isComplete()) {
                return [$document, null];
            }

            if ($offset !== $document->received_bytes) {
                throw new UploadOffsetMismatch($document->received_bytes);
            }

            $length = strlen($bytes);

            if ($length === 0 || $length > $maxChunk || $document->received_bytes + $length > $document->size) {
                throw ActionFailed::because('applications.errors.chunk');
            }

            $temp = DocumentFiles::chunkPath($document->ulid);
            File::ensureDirectoryExists(dirname($temp));
            $this->appendAt($temp, $document->received_bytes, $bytes);

            $document->received_bytes += $length;
            $document->save();

            return [$document, $document->received_bytes === $document->size ? $this->finalize($application, $document, $temp) : null];
        });

        // Outside the transaction, so that the refused upload is really removed.
        if ($failure !== null) {
            @unlink(DocumentFiles::chunkPath($document->ulid));
            $document->delete();
            $field = InitDocumentUpload::fileField($application, $document->field_key);

            throw ActionFailed::because($failure, ['allowed' => strtoupper(implode(', ', $field->file['extensions']))]);
        }

        return $document;
    }

    /**
     * Writes the chunk at the offset the database knows: bytes left over by
     * a request that failed after writing are overwritten, not duplicated.
     */
    private function appendAt(string $path, int $offset, string $bytes): void
    {
        $handle = fopen($path, 'c');

        if ($handle === false) {
            throw ActionFailed::because('applications.errors.chunk');
        }

        try {
            flock($handle, LOCK_EX);
            ftruncate($handle, $offset);
            fseek($handle, $offset);
            fwrite($handle, $bytes);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Checks the assembled file and stores it. Returns the translation key
     * of the refusal, if any.
     */
    private function finalize(Application $application, ApplicationDocument $document, string $temp): ?string
    {
        $field = InitDocumentUpload::fileField($application, $document->field_key);
        $sha256 = hash_file('sha256', $temp) ?: null;
        $mime = DocumentFiles::detectMime($temp);

        $failure = match (true) {
            filesize($temp) !== $document->size => 'applications.errors.chunk',
            $document->sha256 !== null && $document->sha256 !== $sha256 => 'applications.errors.checksum',
            ! DocumentFiles::acceptsMime($field, $mime) => 'applications.errors.file_content',
            default => null,
        };

        if ($failure !== null) {
            return $failure;
        }

        $document->path = DocumentFiles::store($application, $temp, (string) $mime);
        $document->mime = $mime;
        $document->sha256 = $sha256;
        $document->status = ApplicationDocument::COMPLETE;
        $document->save();

        @unlink($temp);

        return null;
    }
}
