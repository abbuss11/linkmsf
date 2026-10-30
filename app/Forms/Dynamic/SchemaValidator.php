<?php

namespace App\Forms\Dynamic;

use App\Enums\FormFieldType;
use App\Enums\ProfileField;

/**
 * Checks the structure of a form schema before it is saved or published.
 * Returns translated, user-facing messages; an empty list means valid.
 */
class SchemaValidator
{
    public const MAX_FIELDS = 200;

    public const MAX_OPTIONS = 100;

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $publishedKeyTypes  key => type of keys already published
     * @return list<string>
     */
    public function validate(array $raw, array $publishedKeyTypes = []): array
    {
        $fields = $raw['fields'] ?? null;

        if (! is_array($fields) || ! array_is_list($fields)) {
            return [__('forms.errors.invalid_structure')];
        }

        if (count($fields) > self::MAX_FIELDS) {
            return [__('forms.errors.too_many_fields', ['max' => self::MAX_FIELDS])];
        }

        $errors = [];
        $seenKeys = [];

        foreach ($fields as $index => $data) {
            $position = $index + 1;

            if (! is_array($data)) {
                $errors[] = __('forms.errors.invalid_field', ['position' => $position]);

                continue;
            }

            $type = FormFieldType::tryFrom((string) ($data['type'] ?? ''));

            if ($type === null) {
                $errors[] = __('forms.errors.unknown_type', ['position' => $position]);

                continue;
            }

            $label = trim((string) ($data['label'] ?? ''));
            $name = $label !== '' ? $label : '#'.$position;

            if ($label === '' && $type !== FormFieldType::HelpText) {
                $errors[] = __('forms.errors.label_required', ['position' => $position]);
            }

            if (mb_strlen($label) > 255) {
                $errors[] = __('forms.errors.label_too_long', ['field' => $name]);
            }

            if ($type === FormFieldType::HelpText && blank($data['help'] ?? null)) {
                $errors[] = __('forms.errors.help_text_empty', ['position' => $position]);
            }

            if (! $type->isInput()) {
                continue;
            }

            $key = (string) ($data['key'] ?? '');

            if (! preg_match('/^[a-z][a-z0-9_]{0,49}$/', $key)) {
                $errors[] = __('forms.errors.invalid_key', ['field' => $name]);
            } elseif (in_array($key, $seenKeys, true)) {
                $errors[] = __('forms.errors.duplicate_key', ['key' => $key]);
            } elseif (isset($publishedKeyTypes[$key]) && $publishedKeyTypes[$key] !== $type->value) {
                $errors[] = __('forms.errors.published_key_type_changed', ['key' => $key]);
            }

            array_push($errors, ...$this->validateOptions($type, $data, $name));
            array_push($errors, ...$this->validateBounds($type, (array) ($data['validation'] ?? []), $name));
            array_push($errors, ...$this->validateFile($type, (array) ($data['file'] ?? []), $name));
            array_push($errors, ...$this->validateProfileField($type, $data, $name));
            array_push($errors, ...$this->validateCondition($data, $fields, $seenKeys, $name));

            $seenKeys[] = $key;
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateOptions(FormFieldType $type, array $data, string $name): array
    {
        if (! $type->hasOptions()) {
            return [];
        }

        $options = (array) ($data['options'] ?? []);
        $values = [];

        if ($options === [] && ($data['profile_field'] ?? null) !== ProfileField::EducationLevel->value) {
            return [__('forms.errors.options_required', ['field' => $name])];
        }

        if (count($options) > self::MAX_OPTIONS) {
            return [__('forms.errors.too_many_options', ['field' => $name, 'max' => self::MAX_OPTIONS])];
        }

        foreach ($options as $option) {
            $value = is_array($option) ? trim((string) ($option['value'] ?? '')) : '';

            if ($value === '' || mb_strlen($value) > 100 || blank($option['label'] ?? null)) {
                return [__('forms.errors.invalid_option', ['field' => $name])];
            }

            $values[] = $value;
        }

        return count($values) !== count(array_unique($values))
            ? [__('forms.errors.duplicate_option', ['field' => $name])]
            : [];
    }

    /**
     * @param  array<string, mixed>  $validation
     * @return list<string>
     */
    private function validateBounds(FormFieldType $type, array $validation, string $name): array
    {
        $validation = array_filter($validation, fn ($value) => $value !== null && $value !== '');

        $pairs = match ($type) {
            FormFieldType::ShortText, FormFieldType::LongText => [['min_length', 'max_length']],
            FormFieldType::Number => [['min', 'max']],
            FormFieldType::Date => [['min_date', 'max_date']],
            FormFieldType::CheckboxList => [['min_selected', 'max_selected']],
            default => [],
        };

        foreach ($pairs as [$minKey, $maxKey]) {
            foreach ([$minKey, $maxKey] as $boundKey) {
                if (! isset($validation[$boundKey])) {
                    continue;
                }

                $valid = $type === FormFieldType::Date
                    ? is_string($validation[$boundKey]) && strtotime($validation[$boundKey]) !== false
                    : is_numeric($validation[$boundKey]) && ($type === FormFieldType::Number || $validation[$boundKey] >= 0);

                if (! $valid) {
                    return [__('forms.errors.invalid_bound', ['field' => $name])];
                }
            }

            if (isset($validation[$minKey], $validation[$maxKey])) {
                $min = $type === FormFieldType::Date ? strtotime((string) $validation[$minKey]) : (float) $validation[$minKey];
                $max = $type === FormFieldType::Date ? strtotime((string) $validation[$maxKey]) : (float) $validation[$maxKey];

                if ($min > $max) {
                    return [__('forms.errors.min_greater_than_max', ['field' => $name])];
                }
            }
        }

        if (isset($validation['max_words']) && (! is_numeric($validation['max_words']) || (int) $validation['max_words'] < 1)) {
            return [__('forms.errors.invalid_bound', ['field' => $name])];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return list<string>
     */
    private function validateFile(FormFieldType $type, array $file, string $name): array
    {
        if ($type !== FormFieldType::File) {
            return [];
        }

        $extensions = (array) ($file['extensions'] ?? config('link.uploads.allowed_extensions'));
        $allowed = (array) config('link.uploads.allowed_extensions');
        $maxKb = (int) ($file['max_kb'] ?? config('link.uploads.default_max_kb'));
        $maxFiles = (int) ($file['max_files'] ?? config('link.uploads.default_max_files'));

        $errors = [];

        if ($extensions === [] || array_diff($extensions, $allowed) !== []) {
            $errors[] = __('forms.errors.file_extensions', ['field' => $name, 'allowed' => implode(', ', $allowed)]);
        }

        if ($maxKb < 1 || $maxKb > (int) config('link.uploads.max_kb_cap')) {
            $errors[] = __('forms.errors.file_size', ['field' => $name, 'max' => (int) config('link.uploads.max_kb_cap') / 1024]);
        }

        if ($maxFiles < 1 || $maxFiles > (int) config('link.uploads.max_files_cap')) {
            $errors[] = __('forms.errors.file_count', ['field' => $name, 'max' => (int) config('link.uploads.max_files_cap')]);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateProfileField(FormFieldType $type, array $data, string $name): array
    {
        if (blank($data['profile_field'] ?? null)) {
            return [];
        }

        $profileField = ProfileField::tryFrom((string) $data['profile_field']);

        if ($profileField === null || $profileField->fieldType() !== $type) {
            return [__('forms.errors.profile_field_type', ['field' => $name])];
        }

        return [];
    }

    /**
     * The condition must point to an input field placed before this one, so
     * that visibility can always be computed and cycles are impossible.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, mixed>  $fields
     * @param  list<string>  $previousKeys
     * @return list<string>
     */
    private function validateCondition(array $data, array $fields, array $previousKeys, string $name): array
    {
        $condition = $data['visible_if'] ?? null;

        if (! is_array($condition) || blank($condition['field'] ?? null)) {
            return [];
        }

        if (! in_array($condition['field'], $previousKeys, true)) {
            return [__('forms.errors.condition_order', ['field' => $name])];
        }

        if (! in_array($condition['operator'] ?? 'equals', ['equals', 'not_equals'], true)) {
            return [__('forms.errors.condition_operator', ['field' => $name])];
        }

        if (blank($condition['value'] ?? null)) {
            return [__('forms.errors.condition_value', ['field' => $name])];
        }

        return [];
    }
}
