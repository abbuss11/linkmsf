<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Active stages make up the pipeline; exit stages take a file out of it
 * (a reason is required); the success stage ends it (§5.4).
 */
enum PipelineStageKind: string implements HasLabel
{
    case Active = 'active';
    case Exit = 'exit';
    case Success = 'success';

    public function requiresReason(): bool
    {
        return $this === self::Exit;
    }

    public function getLabel(): string
    {
        return __('enums.pipeline_stage_kind.'.$this->value);
    }
}
