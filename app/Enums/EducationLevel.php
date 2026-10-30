<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Education levels of the Nigerien system, ordered so that rules such as
 * "level >= Licence" can be evaluated (rank()).
 */
enum EducationLevel: string implements HasLabel
{
    case None = 'none';
    case Cepe = 'cepe';
    case Bepc = 'bepc';
    case Bac = 'bac';
    case Bac2 = 'bac2';
    case Licence = 'licence';
    case Master = 'master';
    case Doctorate = 'doctorate';

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function getLabel(): string
    {
        return __('enums.education_level.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $level) => [$level->value => $level->getLabel()])->all();
    }
}
