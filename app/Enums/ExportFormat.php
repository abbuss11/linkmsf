<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExportFormat: string implements HasLabel
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
    case Zip = 'zip';

    /**
     * The ZIP contains the candidates' documents: a separate permission.
     */
    public function containsDocuments(): bool
    {
        return $this === self::Zip;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
            self::Zip => 'application/zip',
        };
    }

    public function getLabel(): string
    {
        return __('enums.export_format.'.$this->value);
    }
}
