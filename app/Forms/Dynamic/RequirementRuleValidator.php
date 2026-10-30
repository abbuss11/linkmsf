<?php

namespace App\Forms\Dynamic;

use App\Enums\EducationLevel;
use App\Enums\FormFieldType;
use App\Enums\RequirementUsage;
use App\Enums\RuleOperator;

/**
 * Checks that a criterion's rule fits the form field it is linked to
 * (§3.1 step 2). Evaluation against applications comes in phase 4.
 */
class RequirementRuleValidator
{
    /**
     * @param  array<string, mixed>  $requirement  label, usage, field_key, rule
     * @param  bool  $strict  true at publication: an eliminatory criterion must be complete
     * @return list<string>
     */
    public function validate(array $requirement, FormSchema $schema, bool $strict = false): array
    {
        $label = (string) ($requirement['label'] ?? '');
        $usage = RequirementUsage::tryFrom((string) ($requirement['usage'] ?? ''));
        $fieldKey = $requirement['field_key'] ?? null;
        $rule = is_array($requirement['rule'] ?? null) ? $requirement['rule'] : null;

        if ($usage === null) {
            return [__('job_postings.errors.requirement_usage', ['label' => $label])];
        }

        if (blank($fieldKey)) {
            return $strict && $usage->requiresRule()
                ? [__('job_postings.errors.eliminatory_without_field', ['label' => $label])]
                : [];
        }

        $field = $schema->find((string) $fieldKey);

        if ($field === null || ! $field->type->isInput()) {
            return [__('job_postings.errors.requirement_field_missing', ['label' => $label])];
        }

        if ($rule === null || blank($rule['operator'] ?? null)) {
            return $strict && $usage->requiresRule()
                ? [__('job_postings.errors.eliminatory_without_rule', ['label' => $label])]
                : [];
        }

        $operator = RuleOperator::tryFrom((string) $rule['operator']);

        if ($operator === null || ! $operator->supportsField($field->type, $field->profileField)) {
            return [__('job_postings.errors.rule_incompatible', ['label' => $label, 'field' => $field->label])];
        }

        return $this->validateValues($operator, $rule, $field)
            ? []
            : [__('job_postings.errors.rule_value', ['label' => $label])];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function validateValues(RuleOperator $operator, array $rule, FieldDefinition $field): bool
    {
        $value = $rule['value'] ?? null;
        $options = $field->optionValues();

        return match ($operator) {
            RuleOperator::Equals, RuleOperator::NotEquals => match ($field->type) {
                FormFieldType::Select, FormFieldType::Radio => in_array((string) $value, $options, true),
                FormFieldType::YesNo => in_array($value, ['yes', 'no'], true),
                FormFieldType::Number => is_numeric($value),
                default => is_scalar($value) && trim((string) $value) !== '',
            },
            RuleOperator::In => is_array($value) && $value !== [] && array_diff(array_map('strval', $value), $options) === [],
            RuleOperator::Contains => in_array((string) $value, $options, true),
            RuleOperator::GreaterOrEqual, RuleOperator::LessOrEqual => is_numeric($value),
            RuleOperator::Between => is_numeric($rule['min'] ?? null) && is_numeric($rule['max'] ?? null) && $rule['min'] <= $rule['max'],
            RuleOperator::EducationAtLeast => EducationLevel::tryFrom((string) $value) !== null,
            RuleOperator::AgeBetween => $this->isAge($rule['min'] ?? null) && $this->isAge($rule['max'] ?? null) && $rule['min'] <= $rule['max'],
            RuleOperator::IsTrue, RuleOperator::FileProvided => true,
        };
    }

    private function isAge(mixed $value): bool
    {
        return is_numeric($value) && (int) $value >= 0 && (int) $value <= 120 && (int) $value == $value;
    }
}
