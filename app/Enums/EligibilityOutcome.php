<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EligibilityOutcome: string implements HasColor, HasLabel
{
    case Met = 'met';
    case NotMet = 'not_met';
    case Unverifiable = 'unverifiable';

    public function getLabel(): string
    {
        return __('enums.eligibility_outcome.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Met => 'success',
            self::NotMet => 'danger',
            self::Unverifiable => 'warning',
        };
    }
}
