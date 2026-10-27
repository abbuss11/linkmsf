<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A user is either a member of the internal side (organisations, platform)
 * or a candidate. The two are exclusive (cahier des charges §9.1).
 */
enum UserType: string implements HasLabel
{
    case Internal = 'internal';
    case Candidate = 'candidate';

    public function getLabel(): string
    {
        return __('enums.user_type.'.$this->value);
    }
}
