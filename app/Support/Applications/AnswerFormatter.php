<?php

namespace App\Support\Applications;

use App\Enums\FormFieldType;
use App\Forms\Dynamic\FieldDefinition;
use Illuminate\Support\Carbon;

/**
 * Human-readable answer, for the review step and the application page.
 */
class AnswerFormatter
{
    public static function display(FieldDefinition $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        $options = $field->optionsForSelect();

        return match ($field->type) {
            FormFieldType::Select, FormFieldType::Radio => $options[(string) $value] ?? (string) $value,
            FormFieldType::CheckboxList => implode(', ', array_map(fn ($v) => $options[(string) $v] ?? (string) $v, (array) $value)),
            FormFieldType::YesNo => $value === 'yes' ? __('forms.yes') : __('forms.no'),
            FormFieldType::Checkbox => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? __('forms.yes') : __('forms.no'),
            FormFieldType::Date => rescue(fn () => Carbon::parse((string) $value)->translatedFormat('j F Y'), (string) $value, false),
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', ' ').' Mo'
            : max(1, (int) round($bytes / 1024)).' Ko';
    }

    /**
     * 5120 => "5", 1536 => "1,5" (French decimal comma, unit added by the text).
     */
    public static function megabytes(int $kilobytes): string
    {
        return rtrim(rtrim(number_format($kilobytes / 1024, 1, ',', ' '), '0'), ',');
    }
}
