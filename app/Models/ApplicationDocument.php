<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File sent with an application, received in chunks (status "uploading")
 * then checked and stored privately (status "complete").
 *
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property int $application_id
 * @property string $field_key
 * @property string $status
 * @property string $disk
 * @property string|null $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property int $received_bytes
 * @property string|null $sha256
 * @property-read Application $application
 */
class ApplicationDocument extends Model
{
    use BelongsToOrganization, HasUlids;

    public const UPLOADING = 'uploading';

    public const COMPLETE = 'complete';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'received_bytes' => 'integer'];
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

    public function isComplete(): bool
    {
        return $this->status === self::COMPLETE;
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class)->withoutGlobalScopes();
    }
}
