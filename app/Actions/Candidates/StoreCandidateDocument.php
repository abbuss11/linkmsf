<?php

namespace App\Actions\Candidates;

use App\Enums\CandidateDocumentType;
use App\Exceptions\ActionFailed;
use App\Models\CandidateDocument;
use App\Models\User;
use App\Support\Applications\AnswerFormatter;
use App\Support\Applications\DocumentFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reusable document of the profile (CV, diplomas). Same rules as
 * application files: real MIME type, UUID name, private disk.
 */
class StoreCandidateDocument
{
    public const MAX_DOCUMENTS = 20;

    public function handle(User $candidate, UploadedFile $file, CandidateDocumentType $type, string $label): CandidateDocument
    {
        abort_unless($candidate->isCandidate(), 403);

        $maxKb = (int) config('link.uploads.default_max_kb');
        $mime = DocumentFiles::detectMime($file->getRealPath());

        if ($mime === null || ! array_key_exists($mime, DocumentFiles::EXTENSION_FOR_MIME)) {
            throw ActionFailed::because('applications.errors.file_content', ['allowed' => 'PDF, JPG, PNG, DOCX']);
        }

        if ($file->getSize() > $maxKb * 1024) {
            throw ActionFailed::because('applications.errors.file_size', ['max' => AnswerFormatter::megabytes($maxKb)]);
        }

        if ($candidate->candidateDocuments()->count() >= self::MAX_DOCUMENTS) {
            throw ActionFailed::because('candidate.documents.too_many', ['max' => self::MAX_DOCUMENTS]);
        }

        return DB::transaction(function () use ($candidate, $file, $type, $label, $mime) {
            $disk = DocumentFiles::disk();
            $path = sprintf('candidates/%d/%s.%s', $candidate->getKey(), Str::uuid(), DocumentFiles::EXTENSION_FOR_MIME[$mime]);
            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

            $document = CandidateDocument::create([
                'user_id' => $candidate->getKey(),
                'type' => $type,
                'label' => trim($label) !== '' ? trim($label) : $type->getLabel(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => DocumentFiles::cleanName($file->getClientOriginalName()),
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            ]);

            $profile = $candidate->profile();
            if ($type === CandidateDocumentType::Cv && $profile->default_cv_document_id === null) {
                $profile->update(['default_cv_document_id' => $document->getKey()]);
            }

            return $document;
        });
    }
}
