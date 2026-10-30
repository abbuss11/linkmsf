<?php

namespace App\Forms\Dynamic;

use App\Enums\FormFieldType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\TextSize;

/**
 * Turns a form schema into Filament form components: used for the preview in
 * the back-office and, in phase 3, for the candidate form (§9.2). Validation
 * rules come from RulesBuilder so both sides enforce exactly the same rules.
 */
class FilamentFormRenderer
{
    public function __construct(private readonly RulesBuilder $rulesBuilder) {}

    /**
     * Components are meant to live inside a container whose state path is
     * the answers array, so conditions read sibling fields by key.
     *
     * @return list<Component|Field>
     */
    public function components(FormSchema $schema, bool $storeFiles = false): array
    {
        return array_map(fn (FieldDefinition $field) => $this->component($schema, $field, $storeFiles), $schema->fields);
    }

    private function component(FormSchema $schema, FieldDefinition $field, bool $storeFiles): Component|Field
    {
        if ($field->type === FormFieldType::Section) {
            return Text::make($field->label)->size(TextSize::Large);
        }

        if ($field->type === FormFieldType::HelpText) {
            return Text::make((string) $field->help);
        }

        $component = $this->input($field, $storeFiles)
            ->label($field->label)
            ->required($field->required);

        if ($field->help !== null) {
            $component->helperText($field->help);
        }

        if ($field->type !== FormFieldType::File) {
            $component->rules($this->rulesBuilder->fieldRules($field));
        }

        if ($field->visibleIf !== null) {
            $component->visible(function (Get $get) use ($schema, $field): bool {
                $answers = [];
                foreach ($schema->keys() as $key) {
                    $answers[$key] = $get($key);
                }

                return $schema->isVisible($field, $answers);
            })->live();
        }

        return $component;
    }

    private function input(FieldDefinition $field, bool $storeFiles): Field
    {
        $key = (string) $field->key;

        return match ($field->type) {
            FormFieldType::ShortText => TextInput::make($key),
            FormFieldType::LongText => Textarea::make($key)->rows(5),
            FormFieldType::Email => TextInput::make($key)->email(),
            FormFieldType::Phone => TextInput::make($key)->tel()->placeholder('+227 90 00 00 00'),
            FormFieldType::Number => TextInput::make($key)->numeric(),
            // Native pickers and selects are lighter on entry-level phones.
            FormFieldType::Date => DatePicker::make($key)->native()->format('Y-m-d'),
            FormFieldType::Select => Select::make($key)->native()->options($field->optionsForSelect()),
            FormFieldType::Radio => Radio::make($key)->options($field->optionsForSelect()),
            FormFieldType::CheckboxList => CheckboxList::make($key)->options($field->optionsForSelect())
                ->nestedRecursiveRules($this->rulesBuilder->itemRules($field)),
            FormFieldType::Checkbox => Checkbox::make($key),
            FormFieldType::YesNo => Radio::make($key)->inline()->options([
                'yes' => __('forms.yes'),
                'no' => __('forms.no'),
            ]),
            FormFieldType::Url => TextInput::make($key)->url(),
            FormFieldType::File => FileUpload::make($key)
                ->multiple($field->file['max_files'] > 1)
                ->maxFiles($field->file['max_files'])
                ->maxSize($field->file['max_kb'])
                ->acceptedFileTypes($this->rulesBuilder->mimeTypes($field->file['extensions']))
                ->storeFiles($storeFiles),
            FormFieldType::Section, FormFieldType::HelpText => throw new \LogicException('Not an input field.'),
        };
    }
}
