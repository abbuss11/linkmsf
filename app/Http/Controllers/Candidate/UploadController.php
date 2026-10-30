<?php

namespace App\Http\Controllers\Candidate;

use App\Actions\Applications\InitDocumentUpload;
use App\Actions\Applications\ReceiveDocumentChunk;
use App\Exceptions\ActionFailed;
use App\Exceptions\UploadOffsetMismatch;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints of the chunked, resumable upload (§4.6).
 */
class UploadController extends Controller
{
    public function init(Request $request, Application $application, InitDocumentUpload $initDocumentUpload): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'sha256' => ['nullable', 'string', 'size:64'],
        ]);

        try {
            $document = $initDocumentUpload->handle($this->candidate($request), $application, $data['field'], $data['name'], (int) $data['size'], $data['sha256'] ?? null);
        } catch (ActionFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->state($application, $document));
    }

    public function chunk(Request $request, Application $application, string $document, ReceiveDocumentChunk $receiveDocumentChunk): JsonResponse
    {
        $document = ApplicationDocument::queryAcrossOrganizations()
            ->where('application_id', $application->getKey())
            ->where('ulid', $document)
            ->firstOrFail();

        $offset = filter_var($request->header('X-Upload-Offset'), FILTER_VALIDATE_INT);
        abort_if($offset === false, 400);

        try {
            $document = $receiveDocumentChunk->handle($this->candidate($request), $document, (int) $offset, (string) $request->getContent());
        } catch (UploadOffsetMismatch $exception) {
            return response()->json(['status' => ApplicationDocument::UPLOADING, 'received_bytes' => $exception->receivedBytes], 409);
        } catch (ActionFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->state($application, $document));
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Application $application, ApplicationDocument $document): array
    {
        return [
            'ulid' => $document->ulid,
            'status' => $document->status,
            'received_bytes' => $document->isComplete() ? $document->size : $document->received_bytes,
            'chunk_url' => route('candidate.applications.documents.chunk', [$application->ulid, $document->ulid]),
        ];
    }

    private function candidate(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
