<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

/**
 * Canonical Redis key names for per-location live FIFO state.
 *
 * @see ARCHITECTURE.md §4.2
 */
final class LocationRedisKeys
{
    public static function prefix(int $locationId): string
    {
        return "triosense:loc:{$locationId}";
    }

    public static function quota(int $locationId): string
    {
        return self::prefix($locationId).':quota';
    }

    public static function issued(int $locationId): string
    {
        return self::prefix($locationId).':issued';
    }

    public static function tokensRemaining(int $locationId): string
    {
        return self::prefix($locationId).':tokens_remaining';
    }

    public static function queueHead(int $locationId): string
    {
        return self::prefix($locationId).':queue_head';
    }

    public static function queueTail(int $locationId): string
    {
        return self::prefix($locationId).':queue_tail';
    }

    public static function cutoff(int $locationId): string
    {
        return self::prefix($locationId).':cutoff';
    }

    public static function status(int $locationId): string
    {
        return self::prefix($locationId).':status';
    }

    public static function issuanceRatePerMin(int $locationId): string
    {
        return self::prefix($locationId).':issuance_rate_per_min';
    }

    public static function arrivalRatePerMin(int $locationId): string
    {
        return self::prefix($locationId).':arrival_rate_per_min';
    }

    public static function lastEventAt(int $locationId): string
    {
        return self::prefix($locationId).':last_event_at';
    }
}
