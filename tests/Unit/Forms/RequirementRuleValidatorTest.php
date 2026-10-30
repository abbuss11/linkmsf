<?php

use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RequirementRuleValidator;

beforeEach(function () {
    $this->schema = FormSchema::fromArray(['fields' => [
        ['type' => 'date', 'key' => 'naissance', 'label' => 'Date de naissance'],
        ['type' => 'yes_no', 'key' => 'permis', 'label' => 'Permis'],
        ['type' => 'select', 'key' => 'niveau', 'label' => 'Niveau', 'profile_field' => 'education_level'],
        ['type' => 'number', 'key' => 'annees', 'label' => 'Années'],
    ]])->normalized();
    $this->validator = app(RequirementRuleValidator::class);
});

it('accepts rules that fit their field', function (array $rule, string $field) {
    expect($this->validator->validate(['label' => 'X', 'usage' => 'eliminatory', 'field_key' => $field, 'rule' => $rule], $this->schema, strict: true))->toBe([]);
})->with([
    'age between 18 and 35' => [['operator' => 'age_between', 'min' => 18, 'max' => 35], 'naissance'],
    'answer = yes' => [['operator' => 'equals', 'value' => 'yes'], 'permis'],
    'level >= licence' => [['operator' => 'education_at_least', 'value' => 'licence'], 'niveau'],
    'at least 3 years' => [['operator' => 'gte', 'value' => 3], 'annees'],
]);

it('refuses rules that do not fit their field', function (array $rule, string $field) {
    expect($this->validator->validate(['label' => 'X', 'usage' => 'eliminatory', 'field_key' => $field, 'rule' => $rule], $this->schema))->not->toBe([]);
})->with([
    'age rule on a yes/no' => [['operator' => 'age_between', 'min' => 18, 'max' => 35], 'permis'],
    'unknown level' => [['operator' => 'education_at_least', 'value' => 'phd'], 'niveau'],
    'inverted age bounds' => [['operator' => 'age_between', 'min' => 40, 'max' => 18], 'naissance'],
    'yes/no value outside yes/no' => [['operator' => 'equals', 'value' => 'maybe'], 'permis'],
    'missing field' => [['operator' => 'equals', 'value' => 'yes'], 'disparu'],
]);

it('lets a draft keep an incomplete eliminatory criterion, but not a publication', function () {
    $requirement = ['label' => 'Âge', 'usage' => 'eliminatory', 'field_key' => null, 'rule' => null];

    expect($this->validator->validate($requirement, $this->schema))->toBe([])
        ->and($this->validator->validate($requirement, $this->schema, strict: true))
        ->toBe([__('job_postings.errors.eliminatory_without_field', ['label' => 'Âge'])]);
});
