<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CutoffEvent extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'cutoff_event_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'decided_at',
        'mode',
        'previous_status',
        'new_status',
        'queue_head',
        'queue_tail',
        'tokens_remaining',
        'cutoff_position',
        'issuance_rate',
        'arrival_rate',
        'reason',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'queue_head' => 'integer',
        'queue_tail' => 'integer',
        'tokens_remaining' => 'integer',
        'cutoff_position' => 'integer',
        'issuance_rate' => 'decimal:3',
        'arrival_rate' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
