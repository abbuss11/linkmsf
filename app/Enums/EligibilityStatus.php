<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Overall eligibility of an application (§5.3): ineligible as soon as one
 * eliminatory criterion is not met; "to check" when a criterion could not
 * be verified automatically and none failed.
 */
enum EligibilityStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case ToCheck = 'to_check';
    case Ineligible = 'ineligible';

    public function getLabel(): string
    {
        return __('enums.eligibility_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Eligible => 'success',
            self::ToCheck => 'warning',
            self::Ineligible => 'danger',
        };
    }
}
