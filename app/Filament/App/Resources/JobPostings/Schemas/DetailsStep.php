<?php

namespace App\Filament\App\Resources\JobPostings\Schemas;

use App\Enums\ContractType;
use App\Enums\EducationLevel;
use App\Enums\JobPostingType;
use App\Models\Organization;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Wizard step 1: general information (§3.1). Dates are typed in the
 * organisation's timezone and stored in UTC.
 */
class DetailsStep
{
    /**
     * @return list<mixed>
     */
    public static function components(Organization $organization): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('type')
                    ->label(__('job_postings.fields.type'))
                    ->options(JobPostingType::class)
                    ->required()
                    ->native(),
                TextInput::make('reference')
                    ->label(__('job_postings.fields.reference'))
                    ->helperText(__('job_postings.help.reference'))
                    ->maxLength(40)
                    ->regex('/^[A-Za-z0-9][A-Za-z0-9\/_-]*$/'),
                TextInput::make('title')
                    ->label(__('job_postings.fields.title'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),
            RichEditor::make('description')
                ->label(__('job_postings.fields.description'))
                ->helperText(__('job_postings.help.description'))
                ->fileAttachments(false)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3'],
                    ['blockquote', 'bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ]),
            Textarea::make('missions')
                ->label(__('job_postings.fields.missions'))
                ->rows(4)
                ->maxLength(10000),
            Section::make(__('job_postings.sections.profile'))->columns(2)->schema([
                TextInput::make('location')->label(__('job_postings.fields.location'))->maxLength(255),
                Select::make('contract_type')->label(__('job_postings.fields.contract_type'))->options(ContractType::class)->native(),
                Select::make('education_level')->label(__('job_postings.fields.education_level'))->options(EducationLevel::class)->native(),
                TextInput::make('experience_years_min')->label(__('job_postings.fields.experience_years_min'))->integer()->minValue(0)->maxValue(50),
                TagsInput::make('skills')->label(__('job_postings.fields.skills'))->columnSpanFull(),
                TextInput::make('places')->label(__('job_postings.fields.places'))->integer()->minValue(1),
            ]),
            Section::make(__('job_postings.sections.dates'))
                ->description(__('job_postings.help.dates', ['timezone' => $organization->timezone]))
                ->columns(2)
                ->schema([
                    DateTimePicker::make('opens_at')
                        ->label(__('job_postings.fields.opens_at'))
                        ->timezone($organization->timezone)
                        ->seconds(false)
                        ->native(false),
                    DateTimePicker::make('closes_at')
                        ->label(__('job_postings.fields.closes_at'))
                        ->timezone($organization->timezone)
                        ->seconds(false)
                        ->native(false)
                        ->after('opens_at'),
                ]),
        ];
    }

    /**
     * Keys handled by this step, read from the dehydrated form state.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['type', 'title', 'reference', 'description', 'missions', 'location', 'contract_type',
            'education_level', 'experience_years_min', 'skills', 'places', 'opens_at', 'closes_at'];
    }
}
