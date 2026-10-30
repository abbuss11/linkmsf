<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $degree
 * @property string $institution
 * @property string|null $field
 * @property int|null $start_year
 * @property int|null $end_year
 */
#[Fillable(['degree', 'institution', 'field', 'start_year', 'end_year'])]
class CandidateEducation extends Model
{
    protected $table = 'candidate_educations';

    protected function casts(): array
    {
        return ['start_year' => 'integer', 'end_year' => 'integer'];
    }
}
