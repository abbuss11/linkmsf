<?php

use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RulesBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Cahier des charges §11.2 "Formulaires": server-side validation of each
 * field type, and conditional display.
 */
function schemaWith(array $field): FormSchema
{
    return FormSchema::fromArray(['fields' => [['key' => 'f', 'label' => 'Champ', 'required' => true, ...$field]]]);
}

function passes(FormSchema $schema, array $answers): bool
{
    $builder = app(RulesBuilder::class);

    return Validator::make(['answers' => $answers], $builder->rules($schema, $answers), [], $builder->attributes($schema))->passes();
}

dataset('field types', [
    'short text' => [['type' => 'short_text', 'validation' => ['max_length' => 5]], 'abc', 'abcdefgh'],
    'long text, max words' => [['type' => 'long_text', 'validation' => ['max_words' => 3]], 'un deux trois', 'un deux trois quatre'],
    'email' => [['type' => 'email'], 'awa@example.ne', 'awa@'],
    'phone' => [['type' => 'phone'], '+227 90 12 34 56', 'appelez-moi'],
    'number with bounds' => [['type' => 'number', 'validation' => ['min' => 18, 'max' => 35]], 25, 40],
    'date with bounds' => [['type' => 'date', 'validation' => ['min_date' => '2000-01-01']], '2001-05-03', '1990-01-01'],
    'select' => [['type' => 'select', 'options' => [['value' => 'niamey', 'label' => 'Niamey']]], 'niamey', 'paris'],
    'radio' => [['type' => 'radio', 'options' => [['value' => 'a', 'label' => 'A']]], 'a', 'b'],
    'multiple choice' => [['type' => 'checkbox_list', 'options' => [['value' => 'fr', 'label' => 'Français'], ['value' => 'ha', 'label' => 'Haoussa']], 'validation' => ['max_selected' => 1]], ['fr'], ['fr', 'ha']],
    'required checkbox' => [['type' => 'checkbox'], true, false],
    'yes/no' => [['type' => 'yes_no'], 'yes', 'peut-être'],
    'url' => [['type' => 'url'], 'https://link.ne', 'javascript:alert(1)'],
]);

it('validates each field type on the server', function (array $field, mixed $valid, mixed $invalid) {
    $schema = schemaWith($field);

    expect(passes($schema, ['f' => $valid]))->toBeTrue()
        ->and(passes($schema, ['f' => $invalid]))->toBeFalse();
})->with('field types');

it('requires mandatory fields and accepts empty optional ones', function () {
    expect(passes(schemaWith(['type' => 'short_text']), []))->toBeFalse()
        ->and(passes(schemaWith(['type' => 'short_text', 'required' => false]), []))->toBeTrue();
});

it('checks the real type and size of uploaded files', function () {
    $schema = schemaWith(['type' => 'file', 'file' => ['extensions' => ['pdf'], 'max_kb' => 100, 'max_files' => 1]]);

    // Real temporary files: Laravel's fake uploads derive the MIME type from
    // the name, which would hide what this test checks (content sniffing).
    $realUpload = function (string $name, string $content): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    };

    $pdf = $realUpload('cv.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $fakePdf = $realUpload('cv.pdf', '<?php echo "pwned";');
    $tooBig = $realUpload('gros.pdf', "%PDF-1.4\n".str_repeat('A', 200 * 1024));

    expect(passes($schema, ['f' => [$pdf]]))->toBeTrue()
        ->and(passes($schema, ['f' => [$fakePdf]]))->toBeFalse()
        ->and(passes($schema, ['f' => [$tooBig]]))->toBeFalse()
        ->and(passes($schema, ['f' => [$pdf, $pdf]]))->toBeFalse();
});

it('does not require a hidden field and drops its value', function () {
    $schema = FormSchema::fromArray(['fields' => [
        ['type' => 'yes_no', 'key' => 'permis', 'label' => 'Permis', 'required' => true],
        ['type' => 'short_text', 'key' => 'categorie', 'label' => 'Catégorie', 'required' => true, 'visible_if' => ['field' => 'permis', 'operator' => 'equals', 'value' => 'yes']],
    ]]);
    $builder = app(RulesBuilder::class);

    $hidden = ['permis' => 'no', 'categorie' => 'injected'];
    $validated = Validator::make(['answers' => $hidden], $builder->rules($schema, $hidden))->validate();

    expect($validated['answers'])->toBe(['permis' => 'no'])
        ->and(passes($schema, ['permis' => 'yes']))->toBeFalse()
        ->and(passes($schema, ['permis' => 'yes', 'categorie' => 'B']))->toBeTrue();
});

it('hides a field whose condition depends on a hidden field', function () {
    $schema = FormSchema::fromArray(['fields' => [
        ['type' => 'yes_no', 'key' => 'a', 'label' => 'A'],
        ['type' => 'yes_no', 'key' => 'b', 'label' => 'B', 'visible_if' => ['field' => 'a', 'operator' => 'equals', 'value' => 'yes']],
        ['type' => 'short_text', 'key' => 'c', 'label' => 'C', 'visible_if' => ['field' => 'b', 'operator' => 'equals', 'value' => 'yes']],
    ]]);

    expect($schema->isVisible($schema->find('c'), ['a' => 'no', 'b' => 'yes']))->toBeFalse()
        ->and($schema->isVisible($schema->find('c'), ['a' => 'yes', 'b' => 'yes']))->toBeTrue();
});

it('produces a stable hash and readable keys', function () {
    $schema = schemaWith(['type' => 'short_text']);

    expect($schema->hash())->toBe(FormSchema::fromArray($schema->toArray())->hash())
        ->and(FormSchema::generateKey('Date de naissance', []))->toBe('date_de_naissance')
        ->and(FormSchema::generateKey('Date de naissance', ['date_de_naissance']))->toBe('date_de_naissance_2')
        ->and(FormSchema::generateKey('2e langue', []))->toBe('f_2e_langue');
});
