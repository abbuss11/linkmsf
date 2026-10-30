<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Technical state of an application. Organisations never see drafts.
 */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return __('enums.application_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'success',
            self::Withdrawn => 'danger',
        };
    }
}
