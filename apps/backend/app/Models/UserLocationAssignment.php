<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserLocationAssignment extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'location_id',
        'can_override',
    ];

    protected $casts = [
        'can_override' => 'boolean',
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
