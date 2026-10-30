<?php

namespace App\Jobs;

use App\Jobs\Middleware\RestoreOrganizationContext;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for queued jobs that touch organisation data (cahier des
 * charges §2.2): the organisation is taken from the model being processed
 * when the job is created, carried in the payload, and restored before
 * handle() runs. Never pass an organisation id received from a client.
 */
abstract class OrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $organizationId = null;

    protected function forOrganizationOf(Model $model): void
    {
        $this->organizationId = $model instanceof Organization
            ? (int) $model->getKey()
            : (int) $model->getAttribute('organization_id');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RestoreOrganizationContext];
    }
}
