<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

/**
 * Operating mode for the FIFO engine at a location.
 *
 * SHADOW   — calculate everything, persist cutoff_events with mode=shadow,
 *            but DO NOT send MQTT commands to edge or trigger PA announcements.
 *            Used during pilot rollout to validate accuracy before going live.
 *
 * LIVE     — full effects: edge tripwire close commands + PA announcements
 *            + dashboard banner change. Only after shadow-mode validation.
 *
 * DISABLED — calculator does not run for this location at all.
 *            Use for maintenance windows.
 */
enum Mode: string
{
    case SHADOW = 'shadow';
    case LIVE = 'live';
    case DISABLED = 'disabled';
}
