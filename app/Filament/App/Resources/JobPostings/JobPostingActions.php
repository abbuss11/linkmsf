<?php

namespace App\Filament\App\Resources\JobPostings;

use App\Actions\JobPostings\ApproveJobPosting;
use App\Actions\JobPostings\ArchiveJobPosting;
use App\Actions\JobPostings\AssignRecruiter;
use App\Actions\JobPostings\CloseJobPosting;
use App\Actions\JobPostings\DuplicateJobPosting;
use App\Actions\JobPostings\PublishFormChanges;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\ReopenJobPosting;
use App\Actions\JobPostings\ResumeJobPosting;
use App\Actions\JobPostings\ReturnJobPostingToDraft;
use App\Actions\JobPostings\SuspendJobPosting;
use App\Actions\JobPostings\UnassignRecruiter;
use App\Enums\JobPostingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Filament\App\Resources\JobPostings\Pages\EditJobPosting;
use App\Filament\Support\RunsActions;
use App\Models\JobPosting;
use App\Models\OrganizationMembership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Lifecycle actions of an offer (§3.2), shown in the wizard header. Each one
 * calls a domain Action that authorises and checks the transition again.
 */
class JobPostingActions
{
    /**
     * @return list<Action|ActionGroup>
     */
    public static function all(EditJobPosting $page): array
    {
        $posting = $page->posting();

        return [
            self::preview($posting),
            self::publish($page),
            self::approve($page),
            self::returnToDraft($page),
            self::publishFormChanges($page),
            ActionGroup::make([
                self::suspend($page),
                self::resume($page),
                self::close($page),
                self::reopen($page),
                self::archive($page),
                self::assign($page),
                self::duplicate($page),
            ])->label(__('job_postings.actions.more'))->button()->color('gray'),
        ];
    }

    public static function preview(JobPosting $posting): Action
    {
        return Action::make('preview')
            ->label(__('job_postings.actions.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (): string => JobPostingResource::getUrl('preview', ['record' => $posting]))
            ->openUrlInNewTab();
    }

    private static function publish(EditJobPosting $page): Action
    {
        $needsApproval = fn (): bool => $page->organization()->requiresJobPostingApproval()
            && ! $page->actor()->hasPermissionIn($page->organization(), Permission::ApproveJobPostings);

        return Action::make('publish')
            ->label(fn (): string => $needsApproval() ? __('job_postings.actions.submit') : __('job_postings.actions.publish'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $needsApproval() ? __('job_postings.confirm.submit') : __('job_postings.confirm.publish'))
            ->visible(fn (): bool => Gate::allows('publish', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(PublishJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, $page->posting()->status === JobPostingStatus::PendingApproval ? 'submitted' : 'published');
            });
    }

    private static function approve(EditJobPosting $page): Action
    {
        return Action::make('approve')
            ->label(__('job_postings.actions.approve'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => Gate::allows('approve', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(ApproveJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, 'approved');
            });
    }

    private static function returnToDraft(EditJobPosting $page): Action
    {
        return Action::make('returnToDraft')
            ->label(__('job_postings.actions.return_to_draft'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (): bool => Gate::allows('approve', $page->posting()))
            ->schema([
                Textarea::make('reason')->label(__('job_postings.fields.reason'))->required()->maxLength(2000),
            ])
            ->action(function (array $data) use ($page): void {
                RunsActions::attempt(fn () => app(ReturnJobPostingToDraft::class)->handle($page->actor(), $page->posting(), $data['reason']));
                self::done($page, 'returned');
            });
    }

    private static function publishFormChanges(EditJobPosting $page): Action
    {
        return Action::make('publishFormChanges')
            ->label(__('job_postings.actions.publish_form_changes'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(__('job_postings.confirm.publish_form_changes'))
            ->visible(fn (): bool => in_array($page->posting()->status, [JobPostingStatus::Published, JobPostingStatus::Suspended, JobPostingStatus::Closed], true)
                && Gate::allows('update', $page->posting())
                && $page->applicationForm()->hasUnpublishedChanges())
            ->action(function () use ($page): void {
                $version = RunsActions::attempt(fn () => app(PublishFormChanges::class)->handle($page->actor(), $page->posting()));
                Notification::make()->success()->title(__('job_postings.notifications.form_version', ['version' => $version->version]))->send();
                $page->redirect(JobPostingResource::getUrl('edit', ['record' => $page->posting()]));
            });
    }

    private static function suspend(EditJobPosting $page): Action
    {
        return Action::make('suspend')
            ->label(__('job_postings.actions.suspend'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->requiresConfirmation()
            ->visible(fn (): bool => $page->posting()->status === JobPostingStatus::Published && Gate::allows('changeStatus', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(SuspendJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, 'suspended');
            });
    }

    private static function resume(EditJobPosting $page): Action
    {
        return Action::make('resume')
            ->label(__('job_postings.actions.resume'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->visible(fn (): bool => $page->posting()->status === JobPostingStatus::Suspended && Gate::allows('changeStatus', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(ResumeJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, 'resumed');
            });
    }

    private static function close(EditJobPosting $page): Action
    {
        return Action::make('close')
            ->label(__('job_postings.actions.close'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('job_postings.confirm.close'))
            ->visible(fn (): bool => in_array($page->posting()->status, [JobPostingStatus::Published, JobPostingStatus::Suspended], true)
                && Gate::allows('changeStatus', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(CloseJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, 'closed');
            });
    }

    private static function reopen(EditJobPosting $page): Action
    {
        return Action::make('reopen')
            ->label(__('job_postings.actions.reopen'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->visible(fn (): bool => $page->posting()->status === JobPostingStatus::Closed && Gate::allows('changeStatus', $page->posting()))
            ->schema([
                Textarea::make('reason')->label(__('job_postings.fields.reopen_reason'))->required()->maxLength(2000),
                DateTimePicker::make('closes_at')
                    ->label(__('job_postings.fields.new_closes_at'))
                    ->timezone($page->organization()->timezone)
                    ->seconds(false)
                    ->native(false)
                    ->after('now')
                    ->required(),
            ])
            ->action(function (array $data) use ($page): void {
                RunsActions::attempt(fn () => app(ReopenJobPosting::class)->handle(
                    $page->actor(),
                    $page->posting(),
                    $data['reason'],
                    Carbon::parse($data['closes_at']),
                ));
                self::done($page, 'reopened');
            });
    }

    private static function archive(EditJobPosting $page): Action
    {
        return Action::make('archive')
            ->label(__('job_postings.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->requiresConfirmation()
            ->visible(fn (): bool => in_array($page->posting()->status, [JobPostingStatus::Draft, JobPostingStatus::Closed], true)
                && Gate::allows('changeStatus', $page->posting()))
            ->action(function () use ($page): void {
                RunsActions::attempt(fn () => app(ArchiveJobPosting::class)->handle($page->actor(), $page->posting()));
                self::done($page, 'archived');
            });
    }

    private static function duplicate(EditJobPosting $page): Action
    {
        return Action::make('duplicate')
            ->label(__('job_postings.actions.duplicate'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalDescription(__('job_postings.confirm.duplicate'))
            ->visible(fn (): bool => Gate::allows('duplicate', $page->posting()))
            ->action(function () use ($page): void {
                $copy = RunsActions::attempt(fn () => app(DuplicateJobPosting::class)->handle($page->actor(), $page->posting()));
                Notification::make()->success()->title(__('job_postings.notifications.duplicated'))->send();
                $page->redirect(JobPostingResource::getUrl('edit', ['record' => $copy]));
            });
    }

    private static function assign(EditJobPosting $page): Action
    {
        $recruiters = fn (): array => OrganizationMembership::query()
            ->where('role', OrganizationRole::Recruiter->value)
            ->where('status', MembershipStatus::Active->value)
            ->with('user')
            ->get()
            ->mapWithKeys(fn (OrganizationMembership $membership) => [$membership->user_id => $membership->user->name])
            ->all();

        return Action::make('assign')
            ->label(__('job_postings.actions.assign'))
            ->icon(Heroicon::OutlinedUserGroup)
            ->visible(fn (): bool => Gate::allows('assign', $page->posting()))
            ->fillForm(fn (): array => ['recruiters' => $page->posting()->assignedUsers()->pluck('users.id')->all()])
            ->schema([
                Select::make('recruiters')
                    ->label(__('job_postings.fields.assigned_recruiters'))
                    ->helperText(__('job_postings.help.assigned_recruiters'))
                    ->multiple()
                    ->options($recruiters),
            ])
            ->action(function (array $data) use ($page, $recruiters): void {
                $wanted = array_map('intval', (array) ($data['recruiters'] ?? []));
                $current = $page->posting()->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
                $allowed = array_keys($recruiters());

                RunsActions::attempt(function () use ($page, $wanted, $current, $allowed): void {
                    foreach (array_diff($wanted, $current) as $userId) {
                        abort_unless(in_array($userId, $allowed, true), 403);
                        app(AssignRecruiter::class)->handle($page->actor(), $page->posting(), User::query()->findOrFail($userId));
                    }

                    foreach (array_diff($current, $wanted) as $userId) {
                        app(UnassignRecruiter::class)->handle($page->actor(), $page->posting(), User::query()->findOrFail($userId));
                    }
                });

                Notification::make()->success()->title(__('job_postings.notifications.assigned'))->send();
            });
    }

    private static function done(EditJobPosting $page, string $event): void
    {
        Notification::make()->success()->title(__('job_postings.notifications.'.$event))->send();
        $page->redirect(JobPostingResource::getUrl('edit', ['record' => $page->posting()]));
    }
}
