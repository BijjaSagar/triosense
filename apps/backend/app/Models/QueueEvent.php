<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QueueEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $primaryKey = 'queue_event_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'edge_device_id',
        'camera_id',
        'event_type',
        'occurred_at',
        'received_at',
        'track_id',
        'confidence',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'confidence' => 'decimal:3',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    /**
     * @return BelongsTo<EdgeDevice, $this>
     */
    public function edgeDevice(): BelongsTo
    {
        return $this->belongsTo(EdgeDevice::class, 'edge_device_id', 'edge_device_id');
    }
}
