<?php

namespace App\Filament\App\Resources\JobPostings\Schemas;

use App\Enums\DuplicatePolicy;
use App\Enums\EditAfterSubmitPolicy;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Wizard step 3: application settings and requested documents (§3.1).
 */
class SettingsStep
{
    /**
     * @return list<mixed>
     */
    public static function components(): array
    {
        $extensions = collect((array) config('link.uploads.allowed_extensions'))
            ->mapWithKeys(fn (string $extension) => [$extension => strtoupper($extension)])
            ->all();
        $sizes = collect([1024, 2048, 5120, 10240])
            ->filter(fn (int $kb) => $kb <= (int) config('link.uploads.max_kb_cap'))
            ->mapWithKeys(fn (int $kb) => [$kb => __('job_postings.size_mb', ['size' => $kb / 1024])])
            ->all();

        return [
            Section::make(__('job_postings.sections.documents'))
                ->description(__('job_postings.help.documents'))
                ->schema([
                    Repeater::make('documents')
                        ->hiddenLabel()
                        ->addActionLabel(__('job_postings.actions.add_document'))
                        ->defaultItems(0)
                        ->maxItems(20)
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->schema([
                            Hidden::make('key'),
                            Grid::make(2)->schema([
                                TextInput::make('label')->label(__('job_postings.fields.document_label'))->required()->maxLength(255),
                                Toggle::make('required')->label(__('job_postings.fields.document_required'))->default(true)->inline(false),
                            ]),
                            TextInput::make('help')->label(__('job_postings.fields.document_help'))->maxLength(500),
                            Grid::make(3)->schema([
                                CheckboxList::make('extensions')
                                    ->label(__('job_postings.fields.extensions'))
                                    ->options($extensions)
                                    ->default(['pdf'])
                                    ->columns(2)
                                    ->required(),
                                Select::make('max_kb')
                                    ->label(__('job_postings.fields.max_size'))
                                    ->options($sizes)
                                    ->default((int) config('link.uploads.default_max_kb'))
                                    ->native()
                                    ->required(),
                                TextInput::make('max_files')
                                    ->label(__('job_postings.fields.max_files'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue((int) config('link.uploads.max_files_cap'))
                                    ->default(1)
                                    ->required(),
                            ]),
                        ]),
                ]),
            Section::make(__('job_postings.sections.rules'))->columns(2)->schema([
                Radio::make('edit_after_submit')
                    ->label(__('job_postings.fields.edit_after_submit'))
                    ->options(EditAfterSubmitPolicy::class)
                    ->required(),
                Radio::make('duplicate_policy')
                    ->label(__('job_postings.fields.duplicate_policy'))
                    ->options(DuplicatePolicy::class)
                    ->required(),
                Select::make('notify_new_applications')
                    ->label(__('job_postings.fields.notify_new_applications'))
                    ->options([
                        'none' => __('job_postings.notify_options.none'),
                        'each' => __('job_postings.notify_options.each'),
                        'daily' => __('job_postings.notify_options.daily'),
                    ])
                    ->native()
                    ->required(),
            ]),
            Section::make(__('job_postings.sections.messages'))->schema([
                Textarea::make('instructions')->label(__('job_postings.fields.instructions'))->rows(3)->maxLength(5000),
                Textarea::make('confirmation_message')->label(__('job_postings.fields.confirmation_message'))->rows(3)->maxLength(2000),
            ]),
        ];
    }
}
