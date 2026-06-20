<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Records operator mutations to audit_logs.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Model $entity,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
        ?int $locationId = null,
        ?string $reason = null,
    ): AuditLog {
        $tenantId = (int) ($entity->getAttribute('tenant_id') ?? $actor?->tenant_id ?? 0);

        Log::info('AuditLogger.record', [
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
            'user_id' => $actor?->user_id,
            'location_id' => $locationId,
        ]);

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $actor?->user_id,
            'location_id' => $locationId,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'before_json' => $before,
            'after_json' => $after !== null && $reason !== null
                ? array_merge($after, ['reason' => $reason])
                : ($after ?? ($reason !== null ? ['reason' => $reason] : null)),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
