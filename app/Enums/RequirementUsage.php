<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequirementUsage: string implements HasColor, HasLabel
{
    case Eliminatory = 'eliminatory';
    case Mandatory = 'mandatory';
    case Desired = 'desired';
    case Scored = 'scored';
    case Informative = 'informative';

    /**
     * An eliminatory criterion must be linked to a form field and a rule (§3.1).
     */
    public function requiresRule(): bool
    {
        return $this === self::Eliminatory;
    }

    public function hasWeight(): bool
    {
        return $this === self::Scored;
    }

    public function getLabel(): string
    {
        return __('enums.requirement_usage.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Eliminatory => 'danger',
            self::Mandatory => 'warning',
            self::Desired => 'info',
            self::Scored => 'primary',
            self::Informative => 'gray',
        };
    }
}
