<?php

namespace App\Actions\Candidates;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Global profile (§4.2). Only the candidate edits it; organisations never
 * read it directly (they receive a snapshot with each application).
 */
class UpdateCandidateProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $candidate, array $data): CandidateProfile
    {
        abort_unless($candidate->isCandidate(), 403);

        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{6,32}$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:-14 years', 'after:1920-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'diploma' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'string', 'max:255'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'headline' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($candidate, $data) {
            $candidate->forceFill(['name' => trim($data['name'])])->save();

            $profile = $candidate->profile();
            $profile->fill([
                'phone' => $data['phone'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => isset($data['country']) ? strtoupper($data['country']) : null,
                'education_level' => $data['education_level'] ?? null,
                'diploma' => $data['diploma'] ?? null,
                'languages' => self::list($data['languages'] ?? null),
                'years_of_experience' => $data['years_of_experience'] ?? null,
                'headline' => $data['headline'] ?? null,
                'summary' => $data['summary'] ?? null,
                'skills' => self::list($data['skills'] ?? null),
            ])->save();

            return $profile;
        });
    }

    /**
     * "Français, Haoussa" => ['Français', 'Haoussa'].
     *
     * @return list<string>
     */
    private static function list(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $value)))));
    }
}
