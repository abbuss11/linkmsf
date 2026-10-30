<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of an offer (cahier des charges §3.2). Every status change goes
 * through canTransitionTo(): a transition not listed here is refused.
 */
enum JobPostingStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Published, self::Archived],
            self::PendingApproval => [self::Published, self::Draft],
            self::Published => [self::Suspended, self::Closed],
            self::Suspended => [self::Published, self::Closed],
            self::Closed => [self::Published, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statuses counted against the organisation's active offers limit.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Published, self::Suspended], true);
    }

    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }

    public function getLabel(): string
    {
        return __('enums.job_posting_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Published => 'success',
            self::Suspended => 'warning',
            self::Closed => 'danger',
            self::Archived => 'gray',
        };
    }
}
