<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasRoles;
    use Notifiable;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'fcm_token',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    /**
     * @return HasMany<UserLocationAssignment, $this>
     */
    public function locationAssignments(): HasMany
    {
        return $this->hasMany(UserLocationAssignment::class, 'user_id', 'user_id');
    }

    /**
     * @return list<int>
     */
    public function assignedLocationIds(): array
    {
        return $this->locationAssignments()
            ->pluck('location_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
