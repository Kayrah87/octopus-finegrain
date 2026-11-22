<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;

/**
 * How finely a series is bucketed. Buckets for day and coarser are
 * calendar periods in the dashboard's local time zone; weeks start Monday.
 */
enum Granularity: string
{
    case HalfHour = 'half_hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /** The next finer granularity, used when drilling into a bucket. */
    public function finer(): ?self
    {
        return match ($this) {
            self::Month => self::Day,
            self::Week => self::Day,
            self::Day => self::HalfHour,
            self::HalfHour => null,
        };
    }

    /** A sensible granularity for a range of the given length. */
    public static function forDays(int $days): self
    {
        return match (true) {
            $days <= 3 => self::HalfHour,
            $days <= 92 => self::Day,
            $days <= 366 => self::Week,
            default => self::Month,
        };
    }

    /** The local-time start of the bucket containing the given local instant. */
    public function bucketStart(CarbonImmutable $local): CarbonImmutable
    {
        return match ($this) {
            self::HalfHour => $local->setTime($local->hour, $local->minute >= 30 ? 30 : 0),
            self::Day => $local->startOfDay(),
            self::Week => $local->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $local->startOfMonth(),
        };
    }

    /** The local-time start of the bucket after the one starting at $bucketStart. */
    public function next(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this) {
            self::HalfHour => $bucketStart->addMinutes(30),
            self::Day => $bucketStart->addDay(),
            self::Week => $bucketStart->addWeek(),
            self::Month => $bucketStart->addMonth(),
        };
    }
}
