<?php

namespace App\Filament\App\Resources\JobPostings\Support;

use App\Enums\RuleOperator;
use App\Models\JobRequirement;

/**
 * Converts criteria between their stored form and the flat fields of the
 * wizard's repeater (operator + value / values / min / max).
 */
class RequirementState
{
    /**
     * @return array<string, mixed>
     */
    public static function toState(JobRequirement $requirement): array
    {
        $rule = (array) ($requirement->rule ?? []);

        return [
            'label' => $requirement->label,
            'description' => $requirement->description,
            'usage' => $requirement->usage->value,
            'field_key' => $requirement->field_key,
            'operator' => $rule['operator'] ?? null,
            'value' => is_array($rule['value'] ?? null) ? null : ($rule['value'] ?? null),
            'value_choice' => is_array($rule['value'] ?? null) ? null : ($rule['value'] ?? null),
            'values' => is_array($rule['value'] ?? null) ? $rule['value'] : [],
            'min' => $rule['min'] ?? null,
            'max' => $rule['max'] ?? null,
            'weight' => $requirement->weight,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $state
     * @return list<array<string, mixed>>
     */
    public static function fromState(array $state): array
    {
        return array_values(array_map(function (array $row): array {
            $operator = RuleOperator::tryFrom((string) ($row['operator'] ?? ''));

            $rule = match ($operator) {
                null => null,
                RuleOperator::In => ['operator' => $operator->value, 'value' => array_values((array) ($row['values'] ?? []))],
                RuleOperator::Between, RuleOperator::AgeBetween => ['operator' => $operator->value, 'min' => self::number($row['min'] ?? null), 'max' => self::number($row['max'] ?? null)],
                RuleOperator::IsTrue, RuleOperator::FileProvided => ['operator' => $operator->value],
                RuleOperator::GreaterOrEqual, RuleOperator::LessOrEqual => ['operator' => $operator->value, 'value' => self::number($row['value'] ?? null)],
                default => ['operator' => $operator->value, 'value' => filled($row['value_choice'] ?? null) ? $row['value_choice'] : ($row['value'] ?? null)],
            };

            return [
                'label' => (string) ($row['label'] ?? ''),
                'description' => $row['description'] ?? null,
                'usage' => (string) ($row['usage'] ?? ''),
                'field_key' => filled($row['field_key'] ?? null) ? (string) $row['field_key'] : null,
                'rule' => $rule,
                'weight' => filled($row['weight'] ?? null) ? (int) $row['weight'] : null,
            ];
        }, array_filter($state, 'is_array')));
    }

    private static function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }
}
