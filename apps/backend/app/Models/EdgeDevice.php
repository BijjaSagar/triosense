<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EdgeDevice extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'edge_device_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'device_uid',
        'hardware_id',
        'ip_address',
        'firmware_version',
        'last_heartbeat_at',
        'status',
        'config_json',
        'api_key_hash',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'config_json' => 'array',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    /**
     * @return HasMany<Camera, $this>
     */
    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class, 'edge_device_id', 'edge_device_id');
    }
}
