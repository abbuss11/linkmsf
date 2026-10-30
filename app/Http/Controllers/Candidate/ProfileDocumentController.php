<?php

namespace App\Http\Controllers\Candidate;

use App\Actions\Candidates\DeleteCandidateDocument;
use App\Actions\Candidates\StoreCandidateDocument;
use App\Enums\CandidateDocumentType;
use App\Exceptions\ActionFailed;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $candidate = $this->candidate($request);

        return view('candidate.documents.index', [
            'documents' => $candidate->candidateDocuments()->get(),
            'defaultCvId' => $candidate->profile()->default_cv_document_id,
        ]);
    }

    public function store(Request $request, StoreCandidateDocument $store): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('link.uploads.default_max_kb')],
            'type' => ['required', Rule::enum(CandidateDocumentType::class)],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $store->handle($this->candidate($request), $request->file('file'), CandidateDocumentType::from($data['type']), (string) ($data['label'] ?? ''));
        } catch (ActionFailed $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('candidate.documents')->with('status', __('candidate.documents.saved'));
    }

    public function destroy(Request $request, string $document, DeleteCandidateDocument $delete): RedirectResponse
    {
        $candidate = $this->candidate($request);
        $delete->handle($candidate, $candidate->candidateDocuments()->where('ulid', $document)->firstOrFail());

        return redirect()->route('candidate.documents')->with('status', __('candidate.documents.deleted'));
    }

    public function makeDefault(Request $request, string $document): RedirectResponse
    {
        $candidate = $this->candidate($request);
        $cv = $candidate->candidateDocuments()->where('ulid', $document)->where('type', CandidateDocumentType::Cv->value)->firstOrFail();
        $candidate->profile()->update(['default_cv_document_id' => $cv->getKey()]);

        return redirect()->route('candidate.documents')->with('status', __('candidate.documents.default_set'));
    }

    private function candidate(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isCandidate(), 403);

        return $user;
    }
}
