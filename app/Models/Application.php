<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DuplicatePolicy;
use App\Enums\EditAfterSubmitPolicy;
use App\Enums\PublicApplicationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An application to an offer (§4.4). Organisation data: the organisation
 * comes from the offer. Candidates reach their own applications across
 * organisations through forCandidate().
 *
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property int $job_posting_id
 * @property int|null $candidate_user_id
 * @property int|null $application_form_version_id
 * @property string|null $reference
 * @property ApplicationStatus $status
 * @property PublicApplicationStatus|null $public_status
 * @property array<string, mixed>|null $profile_snapshot
 * @property string|null $source
 * @property int $last_step
 * @property Carbon|null $consent_at
 * @property string|null $consent_text
 * @property Carbon|null $submitted_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $draft_reminded_at
 * @property-read JobPosting $jobPosting
 * @property-read ApplicationFormVersion|null $formVersion
 */
class Application extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'draft',
        'last_step' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'public_status' => PublicApplicationStatus::class,
            'profile_snapshot' => 'array',
            'last_step' => 'integer',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'draft_reminded_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The candidate's own applications, across organisations.
     *
     * @return Builder<static>
     */
    public static function forCandidate(User $candidate): Builder
    {
        return static::queryAcrossOrganizations()->where('candidate_user_id', $candidate->getKey());
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /**
     * @return BelongsTo<ApplicationFormVersion, $this>
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'application_form_version_id')->withoutGlobalScopes();
    }

    /**
     * @return HasMany<ApplicationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<ApplicationRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ApplicationRevision::class)->withoutGlobalScopes();
    }

    /**
     * Drafts can be completed, and submitted applications modified, only
     * while the offer accepts applications and, for the latter, if the offer
     * allows it (§3.1 step 3, §4.5).
     */
    public function canStillBeChanged(): bool
    {
        $posting = $this->jobPosting;

        if (! $posting->acceptsApplicationsAt(now())) {
            return false;
        }

        return match ($this->status) {
            ApplicationStatus::Draft => true,
            ApplicationStatus::Submitted => $posting->editAfterSubmitPolicy() === EditAfterSubmitPolicy::UntilClose
                || $posting->duplicatePolicy() === DuplicatePolicy::ReplaceAllowed,
            ApplicationStatus::Withdrawn => false,
        };
    }

    public function isDraft(): bool
    {
        return $this->status === ApplicationStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === ApplicationStatus::Submitted;
    }
}
