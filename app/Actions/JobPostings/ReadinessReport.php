<?php

namespace App\Actions\JobPostings;

/**
 * Result of the pre-publication check (§3.1 step 5).
 */
final readonly class ReadinessReport
{
    /**
     * @param  list<string>  $blocking
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $blocking,
        public array $warnings,
    ) {}

    public function isReady(): bool
    {
        return $this->blocking === [];
    }
}
