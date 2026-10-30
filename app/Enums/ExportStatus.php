<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExportStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('enums.export_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'gray',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
