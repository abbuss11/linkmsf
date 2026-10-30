<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShortlistKind: string implements HasLabel
{
    case Long = 'long';
    case Short = 'short';
    case Waiting = 'waiting';

    public function getLabel(): string
    {
        return __('enums.shortlist_kind.'.$this->value);
    }
}
