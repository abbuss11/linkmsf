<?php

namespace App\Support\Applications;

use App\Enums\FormFieldType;
use App\Forms\Dynamic\FieldDefinition;
use App\Models\Application;
use App\Models\ApplicationAnswer;

/**
 * Reads and writes answers in typed columns (§9.1 application_answers).
 */
class AnswerStore
{
    /**
     * @return array<string, mixed> key => value
     */
    public static function load(Application $application): array
    {
        return ApplicationAnswer::queryAcrossOrganizations()
            ->where('application_id', $application->getKey())
            ->get()
            ->mapWithKeys(fn (ApplicationAnswer $answer) => [$answer->field_key => $answer->value()])
            ->all();
    }

    public static function save(Application $application, FieldDefinition $field, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            self::forget($application, [(string) $field->key]);

            return;
        }

        $columns = ['value_text' => null, 'value_json' => null, 'value_number' => null, 'value_date' => null];

        match ($field->type) {
            FormFieldType::CheckboxList => $columns['value_json'] = array_values(array_map('strval', (array) $value)),
            FormFieldType::Number => $columns['value_number'] = $value,
            FormFieldType::Date => $columns['value_date'] = $value,
            FormFieldType::Checkbox => $columns['value_number'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            default => $columns['value_text'] = (string) $value,
        };

        $answer = ApplicationAnswer::queryAcrossOrganizations()->firstOrNew([
            'application_id' => $application->getKey(),
            'field_key' => (string) $field->key,
        ]);
        $answer->organization_id = $application->organization_id;
        $answer->forceFill($columns)->save();
    }

    /**
     * @param  list<string>  $keys
     */
    public static function forget(Application $application, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        ApplicationAnswer::queryAcrossOrganizations()
            ->where('application_id', $application->getKey())
            ->whereIn('field_key', $keys)
            ->delete();
    }
}
