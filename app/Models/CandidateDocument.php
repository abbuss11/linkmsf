<?php

namespace App\Models;

use App\Enums\CandidateDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable document of a candidate (CV, diplomas). Stored privately under a
 * UUID name; served only through a signed, authorised route.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property CandidateDocumentType $type
 * @property string $label
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property string $sha256
 */
class CandidateDocument extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => CandidateDocumentType::class, 'size' => 'integer'];
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
}
