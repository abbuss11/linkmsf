<?php

namespace App\Forms\Dynamic;

use App\Enums\FormFieldType;
use Illuminate\Validation\Rule;

/**
 * Turns a form schema into Laravel validation rules. This is the server-side
 * truth for every submission (§3.5): the Filament preview uses the same
 * per-field rules, and phase 3 validates candidate answers with rules().
 */
class RulesBuilder
{
    /**
     * MIME types checked against the real file content for each extension.
     */
    public const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /**
     * Rules for answers submitted under "{$prefix}.{key}". Hidden fields are
     * excluded: they are neither required nor kept.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, list<mixed>>
     */
    public function rules(FormSchema $schema, array $answers, string $prefix = 'answers'): array
    {
        $rules = [];

        foreach ($schema->inputFields() as $field) {
            $path = $prefix.'.'.$field->key;

            if (! $schema->isVisible($field, $answers)) {
                $rules[$path] = ['exclude'];
                $rules[$path.'.*'] = ['exclude'];

                continue;
            }

            $rules[$path] = [$field->required ? 'required' : 'nullable', ...$this->fieldRules($field)];

            if (($itemRules = $this->itemRules($field)) !== []) {
                $rules[$path.'.*'] = $itemRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(FormSchema $schema, string $prefix = 'answers'): array
    {
        $attributes = [];

        foreach ($schema->inputFields() as $field) {
            $attributes[$prefix.'.'.$field->key] = $field->label;
            $attributes[$prefix.'.'.$field->key.'.*'] = $field->label;
        }

        return $attributes;
    }

    /**
     * Rules for the field value itself, without required/nullable.
     *
     * @return list<mixed>
     */
    public function fieldRules(FieldDefinition $field): array
    {
        $min = $field->validationValue('min');
        $max = $field->validationValue('max');

        return match ($field->type) {
            FormFieldType::ShortText => array_filter([
                'string',
                'max:'.min((int) ($field->validationValue('max_length') ?? 255), 255),
                $field->validationValue('min_length') !== null ? 'min:'.(int) $field->validationValue('min_length') : null,
            ]),
            FormFieldType::LongText => array_values(array_filter([
                'string',
                'max:'.min((int) ($field->validationValue('max_length') ?? 5000), 10000),
                $field->validationValue('min_length') !== null ? 'min:'.(int) $field->validationValue('min_length') : null,
                $field->validationValue('max_words') !== null ? new MaxWords((int) $field->validationValue('max_words')) : null,
            ])),
            FormFieldType::Email => ['string', 'email:rfc', 'max:255'],
            FormFieldType::Phone => ['string', 'max:32', 'regex:/^\+?[0-9 ().-]{6,32}$/'],
            FormFieldType::Number => array_values(array_filter([
                'numeric',
                $min !== null ? 'min:'.$min : null,
                $max !== null ? 'max:'.$max : null,
            ], fn ($rule) => $rule !== null)),
            FormFieldType::Date => array_values(array_filter([
                'date_format:Y-m-d',
                $field->validationValue('min_date') !== null ? 'after_or_equal:'.$field->validationValue('min_date') : null,
                $field->validationValue('max_date') !== null ? 'before_or_equal:'.$field->validationValue('max_date') : null,
            ])),
            FormFieldType::Select, FormFieldType::Radio => ['string', Rule::in($field->optionValues())],
            FormFieldType::CheckboxList => array_values(array_filter([
                'array',
                $field->validationValue('min_selected') !== null ? 'min:'.(int) $field->validationValue('min_selected') : null,
                $field->validationValue('max_selected') !== null ? 'max:'.(int) $field->validationValue('max_selected') : null,
            ])),
            FormFieldType::Checkbox => $field->required ? ['accepted'] : ['boolean'],
            FormFieldType::YesNo => [Rule::in(['yes', 'no'])],
            FormFieldType::Url => ['string', 'max:2048', 'url:http,https'],
            FormFieldType::File => ['array', 'max:'.$field->file['max_files']],
            FormFieldType::Section, FormFieldType::HelpText => [],
        };
    }

    /**
     * Rules for each element of array answers (choices, files).
     *
     * @return list<mixed>
     */
    public function itemRules(FieldDefinition $field): array
    {
        return match ($field->type) {
            FormFieldType::CheckboxList => ['string', 'distinct', Rule::in($field->optionValues())],
            FormFieldType::File => [
                'file',
                'max:'.$field->file['max_kb'],
                // mimetypes checks the type detected from the content, not the client name.
                'mimetypes:'.implode(',', $this->mimeTypes($field->file['extensions'])),
                'extensions:'.implode(',', $this->extensionsWithAliases($field->file['extensions'])),
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    public function mimeTypes(array $extensions): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (string $extension) => self::MIME_TYPES[$extension] ?? [],
            $extensions,
        ))));
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function extensionsWithAliases(array $extensions): array
    {
        return in_array('jpg', $extensions, true) ? [...$extensions, 'jpeg'] : $extensions;
    }
}
