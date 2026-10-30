<?php

namespace App\Forms\Dynamic;

use App\Enums\EducationLevel;
use App\Enums\FormFieldType;
use App\Enums\ProfileField;
use Illuminate\Support\Str;

/**
 * An application form schema: an ordered list of fields, stored as JSON in
 * application_forms (working copy) and application_form_versions (frozen).
 */
final readonly class FormSchema
{
    /**
     * @param  list<FieldDefinition>  $fields
     */
    public function __construct(public array $fields) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $fields = [];

        foreach ((array) ($data['fields'] ?? []) as $field) {
            if (is_array($field) && ($definition = FieldDefinition::fromArray($field)) !== null) {
                $fields[] = $definition;
            }
        }

        return new self($fields);
    }

    /**
     * @return array{fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['fields' => array_map(fn (FieldDefinition $field) => $field->toArray(), $this->fields)];
    }

    /**
     * Stable fingerprint used to decide whether publishing creates a new version.
     */
    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function isEmpty(): bool
    {
        return $this->inputFields() === [];
    }

    /**
     * @return list<FieldDefinition>
     */
    public function inputFields(): array
    {
        return array_values(array_filter($this->fields, fn (FieldDefinition $field) => $field->type->isInput()));
    }

    public function find(string $key): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_filter(array_map(fn (FieldDefinition $field) => $field->key, $this->fields)));
    }

    /**
     * @return list<FieldDefinition>
     */
    public function documentFields(): array
    {
        return array_values(array_filter($this->fields, fn (FieldDefinition $field) => $field->document));
    }

    /**
     * @return list<FieldDefinition>
     */
    public function nonDocumentFields(): array
    {
        return array_values(array_filter($this->fields, fn (FieldDefinition $field) => ! $field->document));
    }

    /**
     * Whether a field is shown, given the answers. A field whose condition
     * points to a hidden field is hidden too.
     *
     * @param  array<string, mixed>  $answers
     */
    public function isVisible(FieldDefinition $field, array $answers, int $depth = 0): bool
    {
        if ($field->visibleIf === null) {
            return true;
        }

        $source = $this->find($field->visibleIf['field']);

        if ($source === null || $depth > 50 || ! $this->isVisible($source, $answers, $depth + 1)) {
            return false;
        }

        return self::conditionMatches($field->visibleIf, $answers[$source->key] ?? null);
    }

    /**
     * @param  array{field: string, operator: string, value: string|list<string>}  $condition
     */
    public static function conditionMatches(array $condition, mixed $answer): bool
    {
        $answers = array_map('strval', array_filter(is_array($answer) ? $answer : [$answer], fn ($value) => $value !== null && $value !== ''));
        $expected = array_map('strval', (array) $condition['value']);
        $matches = array_intersect($answers, $expected) !== [];

        return match ($condition['operator']) {
            'not_equals' => ! $matches,
            default => $matches,
        };
    }

    /**
     * Fills options that come from the platform (education levels), so that
     * eligibility rules on such fields stay comparable across offers.
     */
    public function normalized(): self
    {
        return new self(array_map(function (FieldDefinition $field): FieldDefinition {
            if ($field->profileField !== ProfileField::EducationLevel) {
                return $field;
            }

            $data = $field->toArray();
            $data['type'] = FormFieldType::Select->value;
            $data['options'] = collect(EducationLevel::cases())
                ->map(fn (EducationLevel $level) => ['value' => $level->value, 'label' => $level->getLabel()])
                ->all();

            return FieldDefinition::fromArray($data) ?? $field;
        }, $this->fields));
    }

    /**
     * Readable, stable key generated from a label; never reused within the form.
     *
     * @param  list<string>  $existingKeys
     */
    public static function generateKey(string $label, array $existingKeys): string
    {
        $base = Str::limit(Str::slug(Str::transliterate($label), '_'), 40, '') ?: 'champ';

        if (! preg_match('/^[a-z]/', $base)) {
            $base = 'f_'.$base;
        }

        $key = $base;
        $suffix = 2;

        while (in_array($key, $existingKeys, true)) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
