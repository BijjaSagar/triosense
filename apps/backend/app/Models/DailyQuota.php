<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DailyQuota extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'daily_quota_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'quota_date',
        'quota',
        'issued',
        'opened_at',
        'closed_at',
        'closed_reason',
        'notes',
    ];

    protected $casts = [
        'quota_date' => 'date',
        'quota' => 'integer',
        'issued' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
