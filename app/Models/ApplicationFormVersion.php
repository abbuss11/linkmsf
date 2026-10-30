<?php

namespace App\Models;

use App\Forms\Dynamic\FormSchema;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Frozen copy of a form (§3.4). Each application will reference the version
 * it answered; a version never changes once written.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_form_id
 * @property int $version
 * @property array<string, mixed> $schema
 * @property string $schema_hash
 * @property Carbon $published_at
 * @property int|null $published_by
 */
class ApplicationFormVersion extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Published form versions are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Published form versions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function formSchema(): FormSchema
    {
        return FormSchema::fromArray($this->schema);
    }

    /**
     * @return BelongsTo<ApplicationForm, $this>
     */
    public function applicationForm(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class);
    }
}
