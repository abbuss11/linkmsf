<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DuplicatePolicy: string implements HasLabel
{
    case SingleApplication = 'single';
    case ReplaceAllowed = 'replace';

    public function getLabel(): string
    {
        return __('enums.duplicate_policy.'.$this->value);
    }
}
