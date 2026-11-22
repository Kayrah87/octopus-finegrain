<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * Where a UTC instant falls on the local (wall-clock) calendar: its date and
 * its wall-clock half-hour slot (0 = 00:00, 47 = 23:30). On DST change days
 * some slots are skipped (spring) or occur twice (autumn).
 */
final readonly class LocalSlot
{
    public function __construct(
        public string $date,
        public int $slot,
    ) {}

    public static function of(CarbonImmutable $utc, string $timezone): self
    {
        $local = $utc->setTimezone($timezone);

        return new self($local->toDateString(), $local->hour * 2 + intdiv($local->minute, 30));
    }
}
