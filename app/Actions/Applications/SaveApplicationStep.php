<?php

namespace App\Actions\Applications;

use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RulesBuilder;
use App\Models\Application;
use App\Models\User;
use App\Support\Applications\AnswerStore;
use App\Support\Applications\ApplicationSteps;
use App\Support\Applications\ProfileFieldValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Saves the answers of one step of the application (draft saved at every
 * step, §4.4). Hidden fields are dropped. Moving back saves without
 * requiring complete answers.
 */
class SaveApplicationStep
{
    public function __construct(private readonly RulesBuilder $rulesBuilder) {}

    /**
     * @param  array<string, mixed>  $answers  all current answers, by key
     */
    public function handle(User $candidate, Application $application, int $step, array $answers, bool $validate = true, bool $updateProfile = false): Application
    {
        Gate::forUser($candidate)->authorize('update', $application);

        $schema = FormSchema::fromArray($application->formVersion?->schema);
        $keys = ApplicationSteps::keys($schema, $step);
        $answers = array_intersect_key($answers, array_flip($schema->keys()));

        if ($validate && $keys !== []) {
            $this->validate($schema, $answers, $keys);
        }

        DB::transaction(function () use ($candidate, $application, $schema, $keys, $answers, $step, $updateProfile): void {
            $profileValues = [];

            foreach ($keys as $key) {
                $field = $schema->find($key);

                if ($field === null || $field->document) {
                    continue;
                }

                if (! $schema->isVisible($field, $answers)) {
                    AnswerStore::forget($application, [$key]);

                    continue;
                }

                AnswerStore::save($application, $field, $answers[$key] ?? null);

                if ($field->profileField !== null && array_key_exists($key, $answers)) {
                    $profileValues[$field->profileField->value] = $answers[$key];
                }
            }

            if ($updateProfile && $profileValues !== []) {
                ProfileFieldValues::write($candidate, $profileValues);
            }

            $application->last_step = max($application->last_step, min($step + 1, ApplicationSteps::REVIEW));
            $application->save();
        });

        return $application;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $keys
     */
    private function validate(FormSchema $schema, array $answers, array $keys): void
    {
        $rules = array_filter(
            $this->rulesBuilder->rules($schema, $answers),
            fn (string $path) => in_array(explode('.', $path)[1] ?? '', $keys, true),
            ARRAY_FILTER_USE_KEY,
        );

        Validator::make(['answers' => $answers], $rules, [], $this->rulesBuilder->attributes($schema))->validate();
    }
}
