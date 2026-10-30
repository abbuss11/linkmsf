<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Fields of the global candidate profile that a form field can be linked to.
 * Linked fields are pre-filled from the profile in phase 3 (§3.3).
 */
enum ProfileField: string implements HasLabel
{
    case FullName = 'full_name';
    case Email = 'email';
    case Phone = 'phone';
    case BirthDate = 'birth_date';
    case City = 'city';
    case Country = 'country';
    case EducationLevel = 'education_level';
    case Diploma = 'diploma';
    case Languages = 'languages';
    case YearsOfExperience = 'years_of_experience';

    /**
     * Field type a linked form field must have.
     */
    public function fieldType(): FormFieldType
    {
        return match ($this) {
            self::FullName, self::City, self::Country, self::Diploma, self::Languages => FormFieldType::ShortText,
            self::Email => FormFieldType::Email,
            self::Phone => FormFieldType::Phone,
            self::BirthDate => FormFieldType::Date,
            self::EducationLevel => FormFieldType::Select,
            self::YearsOfExperience => FormFieldType::Number,
        };
    }

    public function getLabel(): string
    {
        return __('enums.profile_field.'.$this->value);
    }
}
