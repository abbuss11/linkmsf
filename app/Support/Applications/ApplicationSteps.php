<?php

namespace App\Support\Applications;

use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;

/**
 * Splits an offer's form into the 4 steps of the application (§4.4):
 * profile (fields linked to the profile), questions, documents, review.
 */
class ApplicationSteps
{
    public const PROFILE = 1;

    public const QUESTIONS = 2;

    public const DOCUMENTS = 3;

    public const REVIEW = 4;

    /**
     * Fields displayed on a step, in form order (sections and help texts
     * included for steps 1 and 2).
     *
     * @return list<FieldDefinition>
     */
    public static function fields(FormSchema $schema, int $step): array
    {
        return match ($step) {
            self::PROFILE => array_values(array_filter($schema->fields, fn (FieldDefinition $f) => $f->profileField !== null)),
            self::QUESTIONS => self::questions($schema),
            self::DOCUMENTS => $schema->documentFields(),
            default => [],
        };
    }

    /**
     * Keys answered on a step.
     *
     * @return list<string>
     */
    public static function keys(FormSchema $schema, int $step): array
    {
        return array_values(array_filter(array_map(
            fn (FieldDefinition $f) => $f->type->isInput() ? $f->key : null,
            self::fields($schema, $step),
        )));
    }

    /**
     * @return list<FieldDefinition>
     */
    private static function questions(FormSchema $schema): array
    {
        $fields = array_values(array_filter(
            $schema->fields,
            fn (FieldDefinition $f) => $f->profileField === null && ! $f->document,
        ));

        // A trailing section title without any question after it is noise.
        while ($fields !== [] && ! end($fields)->type->isInput()) {
            array_pop($fields);
        }

        return $fields;
    }
}
