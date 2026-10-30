<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\DuplicatePolicy;
use App\Enums\EditAfterSubmitPolicy;
use App\Enums\EducationLevel;
use App\Enums\JobPostingStatus;
use App\Enums\JobPostingType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An offer or call for applications (cahier des charges §3).
 *
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property JobPostingType $type
 * @property string $title
 * @property string $slug
 * @property string $reference
 * @property string|null $description
 * @property string|null $missions
 * @property string|null $location
 * @property ContractType|null $contract_type
 * @property EducationLevel|null $education_level
 * @property int|null $experience_years_min
 * @property list<string>|null $skills
 * @property int|null $places
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property JobPostingStatus $status
 * @property array<string, mixed>|null $settings
 * @property int $wizard_step
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property-read Organization $organization
 * @property-read ApplicationForm|null $form
 */
#[Fillable([
    'type', 'title', 'description', 'missions', 'location', 'contract_type', 'education_level',
    'experience_years_min', 'skills', 'places', 'opens_at', 'closes_at', 'settings',
])]
class JobPosting extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<JobPostingFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const DEFAULT_SETTINGS = [
        'edit_after_submit' => 'never',
        'duplicate_policy' => 'single',
        'instructions' => null,
        'confirmation_message' => null,
        'notify_new_applications' => 'daily',
    ];

    protected $attributes = [
        'status' => 'draft',
        'wizard_step' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => JobPostingType::class,
            'contract_type' => ContractType::class,
            'education_level' => EducationLevel::class,
            'status' => JobPostingStatus::class,
            'skills' => 'array',
            'settings' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'experience_years_min' => 'integer',
            'places' => 'integer',
            'wizard_step' => 'integer',
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
     * @return HasOne<ApplicationForm, $this>
     */
    public function form(): HasOne
    {
        return $this->hasOne(ApplicationForm::class);
    }

    /**
     * @return HasMany<JobRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class)->orderBy('position');
    }

    /**
     * @return HasMany<JobPostingAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(JobPostingAssignment::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_posting_assignments')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setting(string $key): mixed
    {
        return data_get($this->settings, $key, self::DEFAULT_SETTINGS[$key] ?? null);
    }

    public function editAfterSubmitPolicy(): EditAfterSubmitPolicy
    {
        return EditAfterSubmitPolicy::tryFrom((string) $this->setting('edit_after_submit')) ?? EditAfterSubmitPolicy::Never;
    }

    public function duplicatePolicy(): DuplicatePolicy
    {
        return DuplicatePolicy::tryFrom((string) $this->setting('duplicate_policy')) ?? DuplicatePolicy::SingleApplication;
    }

    public function wasEverPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Whether a submission made at this instant is accepted. Based on the
     * deadline itself, not only on the status: a form opened before the
     * deadline and sent after it is refused even if the scheduled closing
     * task has not run yet (§3.2).
     */
    public function acceptsApplicationsAt(CarbonInterface $at): bool
    {
        return $this->status === JobPostingStatus::Published
            && $this->closes_at !== null
            && $at->lessThan($this->closes_at)
            && ($this->opens_at === null || $at->greaterThanOrEqualTo($this->opens_at));
    }

    public function isAssignedTo(User $user): bool
    {
        return JobPostingAssignment::queryForOrganization($this->organization()->firstOrFail())
            ->where('job_posting_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
