<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One answer, stored in a typed column so that phase 4 filters can use indexes.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property string $field_key
 * @property string|null $value_text
 * @property list<string>|null $value_json
 * @property string|null $value_number
 * @property Carbon|null $value_date
 */
class ApplicationAnswer extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'value_date' => 'date:Y-m-d'];
    }

    /**
     * The answer as the form and the validation rules expect it.
     */
    public function value(): mixed
    {
        return match (true) {
            $this->value_json !== null => $this->value_json,
            $this->value_date !== null => $this->value_date->format('Y-m-d'),
            // Decimal column: 25.00 is returned as 25, 2.5 stays 2.5.
            $this->value_number !== null => (float) $this->value_number == (int) $this->value_number
                ? (int) $this->value_number
                : (float) $this->value_number,
            default => $this->value_text,
        };
    }
}
