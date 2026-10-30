<?php

namespace App\Forms\Dynamic;

use App\Enums\FormFieldType;
use App\Enums\ProfileField;

/**
 * One field of a form schema, read from its array form. Instances are built
 * from schemas already checked by SchemaValidator.
 */
final readonly class FieldDefinition
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  array<string, int|float|string|null>  $validation
     * @param  array{extensions: list<string>, max_kb: int, max_files: int}  $file
     * @param  array{field: string, operator: string, value: string|list<string>}|null  $visibleIf
     */
    public function __construct(
        public ?string $key,
        public FormFieldType $type,
        public string $label,
        public ?string $help,
        public bool $required,
        public array $options,
        public array $validation,
        public array $file,
        public ?ProfileField $profileField,
        public ?array $visibleIf,
        public bool $document,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = FormFieldType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === null) {
            return null;
        }

        $options = [];
        foreach ((array) ($data['options'] ?? []) as $option) {
            if (is_array($option) && isset($option['value'])) {
                $options[] = ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])];
            }
        }

        $file = (array) ($data['file'] ?? []);

        return new self(
            key: $type->isInput() ? (string) ($data['key'] ?? '') : null,
            type: $type,
            label: (string) ($data['label'] ?? ''),
            help: filled($data['help'] ?? null) ? (string) $data['help'] : null,
            required: $type->isInput() && (bool) ($data['required'] ?? false),
            options: $options,
            validation: array_filter((array) ($data['validation'] ?? []), fn ($value) => $value !== null && $value !== ''),
            file: [
                'extensions' => array_values(array_map('strval', (array) ($file['extensions'] ?? config('link.uploads.allowed_extensions')))),
                'max_kb' => (int) ($file['max_kb'] ?? config('link.uploads.default_max_kb')),
                'max_files' => (int) ($file['max_files'] ?? config('link.uploads.default_max_files')),
            ],
            profileField: ProfileField::tryFrom((string) ($data['profile_field'] ?? '')),
            visibleIf: is_array($data['visible_if'] ?? null) && filled($data['visible_if']['field'] ?? null) ? [
                'field' => (string) $data['visible_if']['field'],
                'operator' => (string) ($data['visible_if']['operator'] ?? 'equals'),
                'value' => is_array($data['visible_if']['value'] ?? null)
                    ? array_values(array_map('strval', $data['visible_if']['value']))
                    : (string) ($data['visible_if']['value'] ?? ''),
            ] : null,
            document: (bool) ($data['document'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->type->isInput() ? $this->required : null,
            'options' => $this->type->hasOptions() ? $this->options : null,
            'validation' => $this->validation ?: null,
            'file' => $this->type === FormFieldType::File ? $this->file : null,
            'profile_field' => $this->profileField?->value,
            'visible_if' => $this->visibleIf,
            'document' => $this->document ?: null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_column($this->options, 'value');
    }

    /**
     * @return array<string, string>
     */
    public function optionsForSelect(): array
    {
        return array_column($this->options, 'label', 'value');
    }

    public function validationValue(string $name): int|float|string|null
    {
        return $this->validation[$name] ?? null;
    }
}
