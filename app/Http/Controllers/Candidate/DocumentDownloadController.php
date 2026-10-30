<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\CandidateDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files are never public: links are signed, short-lived, and the policy is
 * checked again when the link is opened (§10.1).
 */
class DocumentDownloadController extends Controller
{
    public static function applicationUrl(ApplicationDocument $document): string
    {
        return URL::temporarySignedRoute('documents.application', now()->addMinutes((int) config('link.applications.download_link_minutes')), ['document' => $document->ulid]);
    }

    public static function profileUrl(CandidateDocument $document): string
    {
        return URL::temporarySignedRoute('documents.profile', now()->addMinutes((int) config('link.applications.download_link_minutes')), ['document' => $document->ulid]);
    }

    public function application(Request $request, string $document): StreamedResponse
    {
        $document = ApplicationDocument::queryAcrossOrganizations()->where('ulid', $document)->firstOrFail();
        Gate::authorize('view', $document);

        return $this->stream($document->disk, (string) $document->path, $document->original_name, (string) $document->mime);
    }

    public function profile(Request $request, string $document): StreamedResponse
    {
        $document = CandidateDocument::query()->where('ulid', $document)->firstOrFail();
        Gate::authorize('view', $document);

        return $this->stream($document->disk, $document->path, $document->original_name, $document->mime);
    }

    private function stream(string $disk, string $path, string $name, string $mime): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
