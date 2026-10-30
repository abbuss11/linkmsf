<?php

namespace App\Filament\App\Resources\JobPostings\Support;

use App\Enums\FormFieldType;
use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;
use Illuminate\Support\Str;

/**
 * Converts between the stored schema and the state of Filament's Builder
 * (one block per field, flat inputs for options, validation and condition).
 */
class FormBuilderState
{
    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function toState(FormSchema $schema): array
    {
        return array_map(function (FieldDefinition $field): array {
            return [
                'type' => $field->type->value,
                'data' => [
                    'label' => $field->label,
                    'key' => $field->key,
                    'help' => $field->help,
                    'required' => $field->required,
                    'options' => $field->options,
                    'profile_field' => $field->profileField?->value,
                    'visible_if_field' => $field->visibleIf['field'] ?? null,
                    'visible_if_operator' => $field->visibleIf['operator'] ?? 'equals',
                    'visible_if_value' => is_array($field->visibleIf['value'] ?? null)
                        ? implode(', ', $field->visibleIf['value'])
                        : ($field->visibleIf['value'] ?? null),
                    ...$field->validation,
                ],
            ];
        }, $schema->nonDocumentFields());
    }

    /**
     * @param  array<array-key, mixed>  $state
     * @return list<array<string, mixed>>
     */
    public static function fromState(array $state): array
    {
        $fields = [];

        foreach ($state as $block) {
            $type = FormFieldType::tryFrom((string) ($block['type'] ?? ''));
            $data = (array) ($block['data'] ?? []);

            if ($type === null || $type === FormFieldType::File) {
                continue;
            }

            $field = [
                'type' => $type->value,
                'label' => trim((string) ($data['label'] ?? '')),
                'help' => filled($data['help'] ?? null) ? trim((string) $data['help']) : null,
            ];

            if ($type->isInput()) {
                $field['key'] = filled($data['key'] ?? null) ? (string) $data['key'] : null;
                $field['required'] = (bool) ($data['required'] ?? false);
                $field['profile_field'] = filled($data['profile_field'] ?? null) ? (string) $data['profile_field'] : null;
                $field['validation'] = array_filter(
                    array_intersect_key($data, array_flip(['min_length', 'max_length', 'max_words', 'min', 'max', 'min_date', 'max_date', 'min_selected', 'max_selected'])),
                    fn ($value) => $value !== null && $value !== '',
                );

                if (filled($data['visible_if_field'] ?? null)) {
                    $field['visible_if'] = [
                        'field' => (string) $data['visible_if_field'],
                        'operator' => (string) ($data['visible_if_operator'] ?? 'equals'),
                        'value' => self::conditionValue((string) ($data['visible_if_value'] ?? '')),
                    ];
                }
            }

            if ($type->hasOptions()) {
                $field['options'] = array_values(array_map(fn (array $option): array => [
                    'value' => filled($option['value'] ?? null)
                        ? trim((string) $option['value'])
                        : Str::slug(Str::transliterate((string) ($option['label'] ?? '')), '_'),
                    'label' => trim((string) ($option['label'] ?? '')),
                ], array_filter((array) ($data['options'] ?? []), 'is_array')));
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * "a, b" means one of several values.
     *
     * @return string|list<string>
     */
    private static function conditionValue(string $value): string|array
    {
        $values = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));

        return count($values) > 1 ? $values : ($values[0] ?? '');
    }
}
