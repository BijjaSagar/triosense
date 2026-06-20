<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnnouncementTemplate extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'template_id';

    protected $fillable = [
        'tenant_id',
        'code',
        'language',
        'text',
        'audio_file_path',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * @return HasMany<Announcement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'template_id', 'template_id');
    }
}
