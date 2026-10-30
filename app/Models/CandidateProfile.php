<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Global profile of a candidate (§4.2). Belongs to the candidate; an
 * organisation only sees the snapshot frozen in an application.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property Gender|null $gender
 * @property string|null $city
 * @property string|null $country
 * @property EducationLevel|null $education_level
 * @property string|null $diploma
 * @property list<string>|null $languages
 * @property int|null $years_of_experience
 * @property string|null $headline
 * @property string|null $summary
 * @property list<string>|null $skills
 * @property int|null $default_cv_document_id
 * @property-read User $user
 */
#[Fillable([
    'phone', 'birth_date', 'gender', 'city', 'country', 'education_level', 'diploma', 'languages',
    'years_of_experience', 'headline', 'summary', 'skills', 'default_cv_document_id',
])]
class CandidateProfile extends Model
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'education_level' => EducationLevel::class,
            'languages' => 'array',
            'skills' => 'array',
            'preferences' => 'array',
            'years_of_experience' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CandidateDocument, $this>
     */
    public function defaultCv(): BelongsTo
    {
        return $this->belongsTo(CandidateDocument::class, 'default_cv_document_id');
    }
}
