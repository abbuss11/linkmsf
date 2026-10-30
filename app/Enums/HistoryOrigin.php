<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who changed the stage: a member, the platform (automatic eligibility) or
 * the candidate (withdrawal).
 */
enum HistoryOrigin: string implements HasLabel
{
    case Member = 'member';
    case System = 'system';
    case Candidate = 'candidate';

    public function getLabel(): string
    {
        return __('enums.history_origin.'.$this->value);
    }
}
