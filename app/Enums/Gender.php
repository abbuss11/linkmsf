<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Optional, self-declared. Used only for diversity statistics (§8.3): never
 * to sort candidates and never sent to an AI model (§7.4).
 */
enum Gender: string implements HasLabel
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';
    case PreferNotToSay = 'undisclosed';

    public function getLabel(): string
    {
        return __('enums.gender.'.$this->value);
    }
}
