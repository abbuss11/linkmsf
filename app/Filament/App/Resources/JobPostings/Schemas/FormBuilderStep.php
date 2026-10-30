<?php

namespace App\Filament\App\Resources\JobPostings\Schemas;

use App\Enums\FormFieldType;
use App\Enums\ProfileField;
use App\Forms\Dynamic\FormSchema;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Wizard step 4: the form builder (§3.3). One block per field type; files are
 * requested through the documents of step 3.
 */
class FormBuilderStep
{
    /**
     * @param  Closure(): array<string, string>  $publishedKeys  key => type of keys already published
     * @param  Closure(): array<string, string>  $conditionSources  key => label of fields usable in conditions
     * @return list<mixed>
     */
    public static function components(Closure $publishedKeys, Closure $conditionSources): array
    {
        $blocks = [];

        foreach (FormFieldType::cases() as $type) {
            if ($type === FormFieldType::File) {
                continue;
            }

            $blocks[] = Block::make($type->value)
                ->label(fn (?array $state): string => filled($state['label'] ?? null)
                    ? $type->getLabel().' · '.$state['label']
                    : $type->getLabel())
                ->schema(self::blockSchema($type, $publishedKeys, $conditionSources));
        }

        return [
            Builder::make('fields')
                ->hiddenLabel()
                ->blocks($blocks)
                ->addActionLabel(__('forms.builder.add_field'))
                ->blockNumbers(false)
                ->collapsible()
                // Long forms stay readable: existing questions open on demand.
                ->collapsed()
                ->cloneable()
                ->reorderable()
                ->maxItems(200),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function blockSchema(FormFieldType $type, Closure $publishedKeys, Closure $conditionSources): array
    {
        if ($type === FormFieldType::HelpText) {
            return [Textarea::make('help')->label(__('forms.builder.help_text'))->required()->rows(3)->maxLength(2000)];
        }

        if ($type === FormFieldType::Section) {
            return [
                TextInput::make('label')->label(__('forms.builder.section_title'))->required()->maxLength(255),
                Textarea::make('help')->label(__('forms.builder.section_intro'))->rows(2)->maxLength(2000),
            ];
        }

        $schema = [
            Grid::make(2)->schema([
                TextInput::make('label')
                    ->label(__('forms.builder.label'))
                    ->required()
                    ->maxLength(255)
                    // The key is proposed as soon as the label is typed, so the
                    // field can be used in conditions before saving.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($conditionSources): void {
                        if (blank($get('key')) && filled($state)) {
                            $set('key', FormSchema::generateKey($state, array_keys($conditionSources())));
                        }
                    }),
                TextInput::make('key')
                    ->label(__('forms.builder.key'))
                    ->helperText(__('forms.builder.key_help'))
                    ->regex('/^[a-z][a-z0-9_]{0,49}$/')
                    ->disabled(fn (Get $get): bool => filled($get('key')) && array_key_exists((string) $get('key'), $publishedKeys()))
                    ->dehydrated(),
            ]),
            Textarea::make('help')->label(__('forms.builder.help'))->rows(2)->maxLength(1000),
            Grid::make(2)->schema([
                Toggle::make('required')->label(__('forms.builder.required'))->inline(false),
                Select::make('profile_field')
                    ->label(__('forms.builder.profile_field'))
                    ->helperText(__('forms.builder.profile_field_help'))
                    ->native()
                    ->options(collect(ProfileField::cases())
                        ->filter(fn (ProfileField $field) => $field->fieldType() === $type)
                        ->mapWithKeys(fn (ProfileField $field) => [$field->value => $field->getLabel()])
                        ->all())
                    ->visible(collect(ProfileField::cases())->contains(fn (ProfileField $field) => $field->fieldType() === $type))
                    ->live(),
            ]),
        ];

        if ($type->hasOptions()) {
            $schema[] = Repeater::make('options')
                ->label(__('forms.builder.options'))
                ->addActionLabel(__('forms.builder.add_option'))
                ->hidden(fn (Get $get): bool => $get('profile_field') === ProfileField::EducationLevel->value)
                ->required(fn (Get $get): bool => $get('profile_field') !== ProfileField::EducationLevel->value)
                ->reorderable()
                ->grid(2)
                ->schema([
                    TextInput::make('label')->label(__('forms.builder.option_label'))->required()->maxLength(255),
                    TextInput::make('value')->label(__('forms.builder.option_value'))->helperText(__('forms.builder.option_value_help'))->maxLength(100),
                ]);
        }

        $validation = match ($type) {
            FormFieldType::ShortText => [
                TextInput::make('min_length')->label(__('forms.builder.min_length'))->integer()->minValue(0),
                TextInput::make('max_length')->label(__('forms.builder.max_length'))->integer()->minValue(1)->maxValue(255),
            ],
            FormFieldType::LongText => [
                TextInput::make('max_words')->label(__('forms.builder.max_words'))->integer()->minValue(1),
                TextInput::make('max_length')->label(__('forms.builder.max_length'))->integer()->minValue(1)->maxValue(10000),
            ],
            FormFieldType::Number => [
                TextInput::make('min')->label(__('forms.builder.min'))->numeric(),
                TextInput::make('max')->label(__('forms.builder.max'))->numeric(),
            ],
            FormFieldType::Date => [
                DatePicker::make('min_date')->label(__('forms.builder.min_date'))->native()->format('Y-m-d'),
                DatePicker::make('max_date')->label(__('forms.builder.max_date'))->native()->format('Y-m-d'),
            ],
            FormFieldType::CheckboxList => [
                TextInput::make('min_selected')->label(__('forms.builder.min_selected'))->integer()->minValue(0),
                TextInput::make('max_selected')->label(__('forms.builder.max_selected'))->integer()->minValue(1),
            ],
            default => [],
        };

        if ($validation !== []) {
            $schema[] = Fieldset::make(__('forms.builder.validation'))->columns(2)->schema($validation);
        }

        $schema[] = Fieldset::make(__('forms.builder.condition'))->columns(3)->schema([
            Select::make('visible_if_field')
                ->label(__('forms.builder.condition_field'))
                ->helperText(__('forms.builder.condition_help'))
                ->live()
                ->native()
                ->options(fn (Get $get): array => array_diff_key($conditionSources(), [(string) $get('key') => true])),
            Select::make('visible_if_operator')
                ->label(__('forms.builder.condition_operator'))
                ->native()
                ->options(['equals' => __('forms.builder.equals'), 'not_equals' => __('forms.builder.not_equals')])
                ->default('equals'),
            TextInput::make('visible_if_value')
                ->label(__('forms.builder.condition_value'))
                ->helperText(__('forms.builder.condition_value_help'))
                ->maxLength(255),
        ]);

        return $schema;
    }
}
