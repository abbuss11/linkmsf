<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Operators of eliminatory criteria rules. Each operator only applies to
 * some field types (supportsField); evaluation on applications comes with
 * automatic eligibility in phase 4.
 */
enum RuleOperator: string implements HasLabel
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case Contains = 'contains';
    case GreaterOrEqual = 'gte';
    case LessOrEqual = 'lte';
    case Between = 'between';
    case EducationAtLeast = 'education_at_least';
    case AgeBetween = 'age_between';
    case IsTrue = 'is_true';
    case FileProvided = 'file_provided';

    public function supportsField(FormFieldType $type, ?ProfileField $profileField = null): bool
    {
        return match ($this) {
            self::Equals, self::NotEquals => in_array($type, [FormFieldType::ShortText, FormFieldType::Select, FormFieldType::Radio, FormFieldType::YesNo, FormFieldType::Number], true),
            self::In => in_array($type, [FormFieldType::Select, FormFieldType::Radio], true),
            self::Contains => $type === FormFieldType::CheckboxList,
            self::GreaterOrEqual, self::LessOrEqual, self::Between => $type === FormFieldType::Number,
            self::EducationAtLeast => $profileField === ProfileField::EducationLevel,
            self::AgeBetween => $type === FormFieldType::Date,
            self::IsTrue => in_array($type, [FormFieldType::YesNo, FormFieldType::Checkbox], true),
            self::FileProvided => $type === FormFieldType::File,
        };
    }

    public function getLabel(): string
    {
        return __('enums.rule_operator.'.$this->value);
    }
}
