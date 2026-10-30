<?php

namespace App\Filament\App\Resources\JobPostings\Schemas;

use App\Actions\JobPostings\AddApplicationFormField;
use App\Enums\EducationLevel;
use App\Enums\FormFieldType;
use App\Enums\RequirementUsage;
use App\Enums\RuleOperator;
use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Wizard step 2: criteria (§3.1). An eliminatory criterion is linked to a
 * form field and a rule; the field can be created from here.
 */
class RequirementsStep
{
    /**
     * @param  Closure(): FormSchema  $schema  current working copy of the form
     * @param  Closure(string, string): string  $createField  label, kind => new key
     * @return list<mixed>
     */
    public static function components(Closure $schema, Closure $createField): array
    {
        $field = fn (Get $get): ?FieldDefinition => filled($get('field_key')) ? $schema()->find((string) $get('field_key')) : null;
        $operator = fn (Get $get): ?RuleOperator => RuleOperator::tryFrom((string) $get('operator'));

        return [
            Repeater::make('requirements')
                ->label(__('job_postings.fields.requirements'))
                ->addActionLabel(__('job_postings.actions.add_requirement'))
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->defaultItems(0)
                ->maxItems(50)
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('label')->label(__('job_postings.fields.requirement_label'))->required()->maxLength(255),
                        Select::make('usage')
                            ->label(__('job_postings.fields.usage'))
                            ->options(RequirementUsage::class)
                            ->required()
                            ->native()
                            ->live(),
                    ]),
                    Textarea::make('description')->label(__('job_postings.fields.requirement_description'))->rows(2)->maxLength(2000),
                    Grid::make(2)->schema([
                        Select::make('field_key')
                            ->label(__('job_postings.fields.field_key'))
                            ->helperText(__('job_postings.help.field_key'))
                            ->options(fn (): array => collect($schema()->inputFields())
                                ->mapWithKeys(fn (FieldDefinition $f) => [$f->key => $f->label])
                                ->all())
                            ->required(fn (Get $get): bool => $get('usage') === RequirementUsage::Eliminatory->value)
                            ->live()
                            ->createOptionForm([
                                TextInput::make('label')->label(__('job_postings.fields.new_field_label'))->required()->maxLength(255),
                                Select::make('kind')
                                    ->label(__('job_postings.fields.new_field_kind'))
                                    ->options(collect(AddApplicationFormField::KINDS)->mapWithKeys(fn (string $kind) => [$kind => __('job_postings.field_kinds.'.$kind)])->all())
                                    ->required()
                                    ->native(),
                            ])
                            ->createOptionUsing(fn (array $data): string => $createField($data['label'], $data['kind'])),
                        Select::make('operator')
                            ->label(__('job_postings.fields.operator'))
                            ->native()
                            ->live()
                            ->visible(fn (Get $get): bool => $field($get) !== null)
                            ->required(fn (Get $get): bool => $get('usage') === RequirementUsage::Eliminatory->value)
                            ->options(function (Get $get) use ($field): array {
                                $definition = $field($get);

                                return $definition === null ? [] : collect(RuleOperator::cases())
                                    ->filter(fn (RuleOperator $op) => $op->supportsField($definition->type, $definition->profileField))
                                    ->mapWithKeys(fn (RuleOperator $op) => [$op->value => $op->getLabel()])
                                    ->all();
                            }),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('value')
                            ->label(__('job_postings.fields.rule_value'))
                            ->visible(fn (Get $get): bool => in_array($operator($get), [RuleOperator::Equals, RuleOperator::NotEquals, RuleOperator::GreaterOrEqual, RuleOperator::LessOrEqual], true)
                                && ! self::hasChoices($field($get)))
                            ->required(),
                        Select::make('value_choice')
                            ->label(__('job_postings.fields.rule_value'))
                            ->native()
                            ->visible(fn (Get $get): bool => (in_array($operator($get), [RuleOperator::Equals, RuleOperator::NotEquals, RuleOperator::Contains], true) && self::hasChoices($field($get)))
                                || $operator($get) === RuleOperator::EducationAtLeast)
                            ->options(fn (Get $get): array => self::choices($field($get), $operator($get)))
                            ->required(),
                        Select::make('values')
                            ->label(__('job_postings.fields.rule_values'))
                            ->multiple()
                            ->visible(fn (Get $get): bool => $operator($get) === RuleOperator::In)
                            ->options(fn (Get $get): array => self::choices($field($get), $operator($get)))
                            ->required(),
                        TextInput::make('min')
                            ->label(__('job_postings.fields.rule_min'))
                            ->numeric()
                            ->visible(fn (Get $get): bool => in_array($operator($get), [RuleOperator::Between, RuleOperator::AgeBetween], true))
                            ->required(),
                        TextInput::make('max')
                            ->label(__('job_postings.fields.rule_max'))
                            ->numeric()
                            ->visible(fn (Get $get): bool => in_array($operator($get), [RuleOperator::Between, RuleOperator::AgeBetween], true))
                            ->required(),
                        TextInput::make('weight')
                            ->label(__('job_postings.fields.weight'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get): bool => $get('usage') === RequirementUsage::Scored->value),
                    ]),
                ]),
        ];
    }

    private static function hasChoices(?FieldDefinition $field): bool
    {
        return $field !== null && ($field->type->hasOptions() || $field->type === FormFieldType::YesNo);
    }

    /**
     * @return array<string, string>
     */
    private static function choices(?FieldDefinition $field, ?RuleOperator $operator): array
    {
        if ($operator === RuleOperator::EducationAtLeast) {
            return EducationLevel::options();
        }

        if ($field?->type === FormFieldType::YesNo) {
            return ['yes' => __('forms.yes'), 'no' => __('forms.no')];
        }

        return $field?->optionsForSelect() ?? [];
    }
}
