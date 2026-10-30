<?php

namespace App\Actions\Candidates;

use App\Models\CandidateExperience;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SaveCandidateExperience
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $candidate, ?CandidateExperience $experience, array $data): CandidateExperience
    {
        abort_unless($candidate->isCandidate(), 403);
        abort_if($experience !== null && $experience->user_id !== $candidate->getKey(), 404);

        $data = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'employer' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_current' => ['boolean'],
            'description' => ['nullable', 'string', 'max:3000'],
        ])->validate();

        $experience ??= new CandidateExperience;
        $experience->fill($data);
        $experience->is_current = (bool) ($data['is_current'] ?? false);
        $experience->end_date = $experience->is_current ? null : ($data['end_date'] ?? null);
        $experience->user_id = $candidate->getKey();
        $experience->save();

        return $experience;
    }
}
