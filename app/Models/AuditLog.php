<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * Append-only audit entry (cahier des charges §9.1, table audit_logs).
 *
 * organization_id is null for platform events. This model does not use
 * BelongsToOrganization because of those platform rows: the /app panel
 * filters on the tenant explicitly and AuditLogPolicy re-checks it.
 *
 * @property int|null $organization_id
 * @property string|null $ip
 * @property string|null $user_agent
 */
class AuditLog extends Activity
{
    protected $table = 'audit_logs';

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit log entries are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit log entries cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function auditEvent(): ?AuditEvent
    {
        return AuditEvent::tryFrom((string) $this->event);
    }
}
