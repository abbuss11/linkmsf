<?php

use App\Forms\Dynamic\SchemaValidator;

function validateSchema(array $fields, array $published = []): array
{
    return app(SchemaValidator::class)->validate(['fields' => $fields], $published);
}

it('accepts a complete valid schema', function () {
    expect(validateSchema([
        ['type' => 'section', 'label' => 'Identité'],
        ['type' => 'short_text', 'key' => 'nom', 'label' => 'Nom', 'required' => true],
        ['type' => 'select', 'key' => 'ville', 'label' => 'Ville', 'options' => [['value' => 'niamey', 'label' => 'Niamey'], ['value' => 'zinder', 'label' => 'Zinder']]],
        ['type' => 'yes_no', 'key' => 'permis', 'label' => 'Permis ?'],
        ['type' => 'short_text', 'key' => 'categorie', 'label' => 'Catégorie', 'visible_if' => ['field' => 'permis', 'operator' => 'equals', 'value' => 'yes']],
        ['type' => 'number', 'key' => 'annees', 'label' => 'Années', 'validation' => ['min' => 0, 'max' => 40]],
        ['type' => 'file', 'key' => 'cv', 'label' => 'CV', 'file' => ['extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1], 'document' => true],
        ['type' => 'help_text', 'help' => 'Merci de répondre honnêtement.'],
    ]))->toBe([]);
});

it('rejects structural problems', function (array $fields, string $expected) {
    expect(implode(' | ', validateSchema($fields)))->toContain($expected);
})->with([
    'unknown type' => [[['type' => 'video', 'key' => 'v', 'label' => 'V']], 'type inconnu'],
    'missing label' => [[['type' => 'short_text', 'key' => 'nom']], 'n’a pas de libellé'],
    'invalid key' => [[['type' => 'short_text', 'key' => 'Nom Complet', 'label' => 'X']], 'clé technique de « X »'],
    'duplicate key' => [[['type' => 'short_text', 'key' => 'nom', 'label' => 'A'], ['type' => 'email', 'key' => 'nom', 'label' => 'B']], 'La clé « nom » est utilisée deux fois'],
    'select without options' => [[['type' => 'select', 'key' => 'ville', 'label' => 'X']], 'au moins une option'],
    'duplicate option values' => [[['type' => 'radio', 'key' => 'r', 'label' => 'X', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]]], 'même valeur'],
    'min above max' => [[['type' => 'number', 'key' => 'n', 'label' => 'X', 'validation' => ['min' => 10, 'max' => 1]]], 'dépasse le maximum'],
    'forbidden file type' => [[['type' => 'file', 'key' => 'f', 'label' => 'X', 'file' => ['extensions' => ['exe'], 'max_kb' => 100, 'max_files' => 1]]], 'Formats non autorisés'],
    'file too large' => [[['type' => 'file', 'key' => 'f', 'label' => 'X', 'file' => ['extensions' => ['pdf'], 'max_kb' => 999999, 'max_files' => 1]]], 'taille maximale'],
    'condition on a later field' => [[
        ['type' => 'short_text', 'key' => 'a', 'label' => 'A', 'visible_if' => ['field' => 'b', 'operator' => 'equals', 'value' => 'x']],
        ['type' => 'short_text', 'key' => 'b', 'label' => 'B'],
    ], 'champ placé avant'],
    'profile link with wrong type' => [[['type' => 'number', 'key' => 'tel', 'label' => 'X', 'profile_field' => 'phone']], 'lien au profil'],
]);

it('refuses to change the type of a key already published', function () {
    $errors = validateSchema([['type' => 'number', 'key' => 'age', 'label' => 'Âge']], ['age' => 'date']);

    expect($errors)->toContain(__('forms.errors.published_key_type_changed', ['key' => 'age']));
});

it('refuses a schema that is not a list of fields', function () {
    expect(app(SchemaValidator::class)->validate(['fields' => 'oops']))->toBe([__('forms.errors.invalid_structure')]);
});
