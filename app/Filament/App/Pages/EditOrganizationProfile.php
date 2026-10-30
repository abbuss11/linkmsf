<?php

namespace App\Filament\App\Pages;

use App\Actions\Organizations\UpdateOrganizationProfile;
use App\Filament\Support\RunsActions;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Organisation profile and career page settings (orgadmin).
 *
 * The logo is public by nature (career page): it is stored on the public
 * disk under a UUID name, restricted to raster images whose real MIME type
 * is checked. Candidate documents never use this disk.
 */
class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('organizations.profile.title');
    }

    public static function canView(Model $tenant): bool
    {
        return Gate::allows('updateProfile', $tenant);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('organizations.profile.identity'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('organizations.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('organizations.fields.slug'))
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('organizations.profile.slug_help')),
                        Select::make('timezone')
                            ->label(__('organizations.fields.timezone'))
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->required(),
                    ]),
                Section::make(__('organizations.profile.career_page'))
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label(__('organizations.fields.logo'))
                            ->disk('public')
                            ->directory('organization-logos')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(1024)
                            ->getUploadedFileNameForStorageUsing(
                                fn (UploadedFile $file): string => Str::uuid().'.'.($file->guessExtension() ?? 'png'),
                            ),
                        ColorPicker::make('primary_color')
                            ->label(__('organizations.fields.primary_color'))
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        ColorPicker::make('secondary_color')
                            ->label(__('organizations.fields.secondary_color'))
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        Toggle::make('listed_on_job_board')
                            ->label(__('organizations.fields.listed_on_job_board'))
                            ->helperText(__('organizations.profile.listed_help')),
                        RichEditor::make('about')
                            ->label(__('organizations.fields.about'))
                            ->fileAttachments(false)
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ]),
                        TextInput::make('website')
                            ->label(__('organizations.fields.website'))
                            ->url()
                            ->maxLength(255),
                    ]),
                Section::make(__('organizations.profile.job_postings'))
                    ->schema([
                        Toggle::make('job_postings_requires_approval')
                            ->label(__('organizations.fields.job_postings_requires_approval'))
                            ->helperText(__('organizations.profile.requires_approval_help')),
                        Toggle::make('job_postings_recruiters_see_all')
                            ->label(__('organizations.fields.job_postings_recruiters_see_all'))
                            ->helperText(__('organizations.profile.recruiters_see_all_help')),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Organization $organization */
        $organization = $this->tenant;
        $data['job_postings_requires_approval'] = $organization->requiresJobPostingApproval();
        $data['job_postings_recruiters_see_all'] = $organization->recruitersSeeAllJobPostings();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Organization $record */
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return RunsActions::attempt(fn () => app(UpdateOrganizationProfile::class)->handle($user, $record, $data));
    }
}
