<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Internal notes (§5.5): private notes are only shown to their author.
 */
enum NoteVisibility: string implements HasLabel
{
    case Private = 'private';
    case Shared = 'shared';

    public function getLabel(): string
    {
        return __('enums.note_visibility.'.$this->value);
    }
}
