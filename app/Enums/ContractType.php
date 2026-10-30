<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    case Permanent = 'permanent';
    case FixedTerm = 'fixed_term';
    case Internship = 'internship';
    case Consultancy = 'consultancy';
    case Volunteering = 'volunteering';
    case Freelance = 'freelance';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('enums.contract_type.'.$this->value);
    }
}
