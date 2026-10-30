<?php

namespace App\Filament\App\Resources\JobPostings;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\JobPostingType;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\JobPostings\Pages\CreateJobPosting;
use App\Filament\App\Resources\JobPostings\Pages\EditJobPosting;
use App\Filament\App\Resources\JobPostings\Pages\ListJobPostings;
use App\Filament\App\Resources\JobPostings\Pages\PreviewJobPosting;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class JobPostingResource extends Resource
{
    protected static ?string $model = JobPosting::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'job-postings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('job_postings.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('job_postings.resource.plural_label');
    }

    /**
     * Recruiters of an organisation that restricts visibility only see the
     * offers they are assigned to (JobPostingPolicy::view says the same).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withCount(['applications as submitted_applications_count' => fn (Builder $applications) => $applications->where('status', ApplicationStatus::Submitted->value)]);
        $user = auth()->user();
        $organization = Filament::getTenant();

        if ($user instanceof User
            && $organization instanceof Organization
            && $user->roleIn($organization) === OrganizationRole::Recruiter
            && ! $organization->recruitersSeeAllJobPostings()) {
            $query->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $user->getKey()));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label(__('job_postings.fields.reference'))->searchable()->sortable(),
                TextColumn::make('title')->label(__('job_postings.fields.title'))->searchable()->limit(60)->sortable(),
                TextColumn::make('type')->label(__('job_postings.fields.type'))->badge()->color('gray'),
                TextColumn::make('status')->label(__('job_postings.fields.status'))->badge(),
                TextColumn::make('closes_at')
                    ->label(__('job_postings.fields.closes_at'))
                    ->dateTime('d/m/Y H:i', timezone: fn (JobPosting $record): string => Filament::getTenant() instanceof Organization ? Filament::getTenant()->timezone : config('link.display_timezone'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('submitted_applications_count')->label(__('job_postings.fields.applications_count'))->numeric()->sortable(),
                TextColumn::make('updated_at')->label(__('job_postings.fields.updated_at'))->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('job_postings.fields.status'))->options(JobPostingStatus::class),
                SelectFilter::make('type')->label(__('job_postings.fields.type'))->options(JobPostingType::class),
            ])
            ->recordActions([
                EditAction::make()->label(__('job_postings.actions.open')),
            ])
            ->recordUrl(fn (JobPosting $record): string => static::getUrl('edit', ['record' => $record]));
    }

    /**
     * The wizard is also the read-only view of an archived offer: opening it
     * needs "view"; every save calls an Action that checks "update".
     */
    public static function canEdit(Model $record): bool
    {
        return Gate::allows('view', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobPostings::route('/'),
            'create' => CreateJobPosting::route('/create'),
            'edit' => EditJobPosting::route('/{record}/edit'),
            'preview' => PreviewJobPosting::route('/{record}/preview'),
        ];
    }
}
