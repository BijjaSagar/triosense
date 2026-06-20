<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    protected $primaryKey = 'tenant_id';

    protected $fillable = [
        'name',
        'slug',
        'contact_email',
        'contact_phone',
        'timezone',
        'status',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'tenant_id', 'tenant_id');
    }
}
