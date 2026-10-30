<?php

namespace App\Support\Applications;

use App\Enums\ProfileField;
use App\Models\User;

/**
 * Maps form fields linked to the profile to profile values, both ways:
 * pre-filling (§3.3) and "update my profile" after a correction.
 */
class ProfileFieldValues
{
    public static function read(User $candidate, ProfileField $field): mixed
    {
        $profile = $candidate->profile();

        return match ($field) {
            ProfileField::FullName => $candidate->name,
            ProfileField::Email => $candidate->email,
            ProfileField::Phone => $profile->phone,
            ProfileField::BirthDate => $profile->birth_date?->format('Y-m-d'),
            ProfileField::City => $profile->city,
            ProfileField::Country => $profile->country,
            ProfileField::EducationLevel => $profile->education_level?->value,
            ProfileField::Diploma => $profile->diploma,
            ProfileField::Languages => implode(', ', $profile->languages ?? []),
            ProfileField::YearsOfExperience => $profile->years_of_experience,
        };
    }

    /**
     * Applies corrected values to the profile. The account e-mail is never
     * changed from an application form.
     *
     * @param  array<string, mixed>  $values  ProfileField value => answer
     */
    public static function write(User $candidate, array $values): void
    {
        $profile = $candidate->profile();

        foreach ($values as $name => $value) {
            match (ProfileField::tryFrom($name)) {
                ProfileField::FullName => filled($value) ? $candidate->forceFill(['name' => (string) $value])->save() : null,
                ProfileField::Phone => $profile->phone = $value,
                ProfileField::BirthDate => $profile->birth_date = $value,
                ProfileField::City => $profile->city = $value,
                ProfileField::Country => $profile->country = is_string($value) ? mb_substr($value, 0, 2) : null,
                ProfileField::EducationLevel => $profile->education_level = $value,
                ProfileField::Diploma => $profile->diploma = $value,
                ProfileField::Languages => $profile->languages = array_values(array_filter(array_map('trim', explode(',', (string) $value)))),
                ProfileField::YearsOfExperience => $profile->years_of_experience = is_numeric($value) ? (int) $value : null,
                default => null,
            };
        }

        $profile->save();
    }
}
