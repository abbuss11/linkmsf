<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CandidateDocumentType: string implements HasLabel
{
    case Cv = 'cv';
    case Diploma = 'diploma';
    case Certificate = 'certificate';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('enums.candidate_document_type.'.$this->value);
    }
}
