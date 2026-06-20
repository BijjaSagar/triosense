<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $primaryKey = 'audit_log_id';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'location_id',
        'action',
        'entity_type',
        'entity_id',
        'before_json',
        'after_json',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
