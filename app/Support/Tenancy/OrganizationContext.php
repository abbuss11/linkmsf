<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use Closure;

/**
 * Holds the organisation whose data the current request or job may read.
 *
 * Bound as a scoped singleton: it is reset for every HTTP request and every
 * queued job. Nothing sent by the client ever sets it directly; it is set by
 * the Filament tenant middleware or from the model a job is processing.
 */
class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    /**
     * Run a callback with the given organisation active, then restore the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Organization $organization, Closure $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
