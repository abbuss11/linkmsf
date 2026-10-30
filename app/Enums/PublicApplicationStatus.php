<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Status shown to the candidate (§4.5): never the internal pipeline stage.
 * Phase 3 uses Received and Withdrawn; the others come with the ATS.
 */
enum PublicApplicationStatus: string implements HasLabel
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case Invited = 'invited';
    case Selected = 'selected';
    case NotSelected = 'not_selected';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return __('enums.public_application_status.'.$this->value);
    }
}
