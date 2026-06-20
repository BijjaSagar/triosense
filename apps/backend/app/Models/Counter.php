<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Counter extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'counter_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'short_code',
        'status',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
