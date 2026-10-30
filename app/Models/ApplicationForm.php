<?php

namespace App\Models;

use App\Forms\Dynamic\FormSchema;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Application form of an offer. `schema` is the working copy edited in the
 * builder; candidates always answer a frozen ApplicationFormVersion.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_posting_id
 * @property array<string, mixed> $schema
 * @property int|null $current_version_id
 * @property-read ApplicationFormVersion|null $currentVersion
 */
#[Fillable(['organization_id', 'job_posting_id', 'schema', 'updated_by'])]
class ApplicationForm extends Model
{
    use BelongsToOrganization;

    protected $attributes = [
        'schema' => '{"fields":[]}',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    public function draftSchema(): FormSchema
    {
        return FormSchema::fromArray($this->schema);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * @return HasMany<ApplicationFormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ApplicationFormVersion::class)->orderBy('version');
    }

    /**
     * @return BelongsTo<ApplicationFormVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'current_version_id');
    }

    /**
     * True when the working copy differs from the last published version.
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->currentVersion === null
            || $this->currentVersion->schema_hash !== $this->draftSchema()->hash();
    }

    /**
     * Keys used by any published version: they can never change type.
     *
     * @return array<string, string> key => field type
     */
    public function publishedKeyTypes(): array
    {
        $types = [];

        foreach ($this->versions()->get() as $version) {
            foreach ($version->formSchema()->inputFields() as $field) {
                $types[$field->key] = $field->type->value;
            }
        }

        return $types;
    }
}
