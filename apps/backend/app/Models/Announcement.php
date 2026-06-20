<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Announcement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'template_id',
        'trigger_type',
        'triggered_by',
        'text_played',
        'language',
        'cutoff_event_id',
        'played_at',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AnnouncementTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AnnouncementTemplate::class, 'template_id', 'template_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
