<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Field types of the dynamic application form (cahier des charges §3.3).
 */
enum FormFieldType: string implements HasLabel
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Radio = 'radio';
    case CheckboxList = 'checkbox_list';
    case Checkbox = 'checkbox';
    case YesNo = 'yes_no';
    case Url = 'url';
    case File = 'file';
    case Section = 'section';
    case HelpText = 'help_text';

    /**
     * Section titles and help texts carry no answer.
     */
    public function isInput(): bool
    {
        return ! in_array($this, [self::Section, self::HelpText], true);
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::CheckboxList], true);
    }

    public function getLabel(): string
    {
        return __('enums.form_field_type.'.$this->value);
    }
}
