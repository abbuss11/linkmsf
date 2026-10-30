<?php

namespace App\Support\Applications;

use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\User;

/**
 * Frozen copy of the profile stored in an application at submission (§4.3).
 * The organisation only ever sees this copy, never the live profile.
 */
class ProfileSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function of(User $candidate): array
    {
        $profile = $candidate->profile();

        return [
            'taken_at' => now()->toIso8601String(),
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $profile->phone,
            'birth_date' => $profile->birth_date?->format('Y-m-d'),
            'gender' => $profile->gender?->value,
            'city' => $profile->city,
            'country' => $profile->country,
            'education_level' => $profile->education_level?->value,
            'diploma' => $profile->diploma,
            'languages' => $profile->languages ?? [],
            'years_of_experience' => $profile->years_of_experience,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'skills' => $profile->skills ?? [],
            'educations' => $candidate->educations()->get()->map(fn (CandidateEducation $e) => [
                'degree' => $e->degree,
                'institution' => $e->institution,
                'field' => $e->field,
                'start_year' => $e->start_year,
                'end_year' => $e->end_year,
            ])->all(),
            'experiences' => $candidate->experiences()->get()->map(fn (CandidateExperience $e) => [
                'title' => $e->title,
                'employer' => $e->employer,
                'location' => $e->location,
                'start_date' => $e->start_date?->format('Y-m-d'),
                'end_date' => $e->end_date?->format('Y-m-d'),
                'is_current' => $e->is_current,
                'description' => $e->description,
            ])->all(),
        ];
    }
}
