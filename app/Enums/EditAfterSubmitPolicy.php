<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EditAfterSubmitPolicy: string implements HasLabel
{
    case Never = 'never';
    case UntilClose = 'until_close';

    public function getLabel(): string
    {
        return __('enums.edit_after_submit.'.$this->value);
    }
}
