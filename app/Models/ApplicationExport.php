<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Export of applications built in the queue (§5.6, §8.3). Personal: only
 * the member who asked for it downloads it, through a signed link.
 *
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property int $job_posting_id
 * @property int|null $shortlist_id
 * @property int $user_id
 * @property ExportFormat $format
 * @property ExportStatus $status
 * @property array<string, mixed> $criteria
 * @property int|null $row_count
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $file_size
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['organization_id', 'job_posting_id', 'shortlist_id', 'user_id', 'format', 'status', 'criteria'])]
class ApplicationExport extends Model
{
    use BelongsToOrganization, HasUlids;

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'criteria' => 'array',
            'row_count' => 'integer',
            'file_size' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
