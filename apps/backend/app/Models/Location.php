<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Location extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'location_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'short_code',
        'address',
        'latitude',
        'longitude',
        'opens_at',
        'closes_at',
        'default_quota',
        'mode',
        'festival_mode',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'default_quota' => 'integer',
        'festival_mode' => 'boolean',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    /**
     * @return HasMany<CutoffEvent, $this>
     */
    public function cutoffEvents(): HasMany
    {
        return $this->hasMany(CutoffEvent::class, 'location_id', 'location_id');
    }
}
