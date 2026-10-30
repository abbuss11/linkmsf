<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JobPostingType: string implements HasLabel
{
    case Employment = 'employment';
    case Internship = 'internship';
    case Scholarship = 'scholarship';
    case Programme = 'programme';
    case Consultancy = 'consultancy';
    case CallForProjects = 'call_for_projects';

    public function getLabel(): string
    {
        return __('enums.job_posting_type.'.$this->value);
    }
}
