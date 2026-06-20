<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Camera extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'camera_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'edge_device_id',
        'name',
        'role',
        'rtsp_url',
        'tripwire_json',
        'status',
        'last_frame_at',
    ];

    protected $casts = [
        'tripwire_json' => 'array',
        'last_frame_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EdgeDevice, $this>
     */
    public function edgeDevice(): BelongsTo
    {
        return $this->belongsTo(EdgeDevice::class, 'edge_device_id', 'edge_device_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
