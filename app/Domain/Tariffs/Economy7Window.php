<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use Carbon\CarbonImmutable;

/**
 * The off-peak window for two-rate (Economy 7 style) tariffs, in UTC wall-clock time.
 * Supports windows that cross midnight.
 */
final readonly class Economy7Window
{
    private int $startMinute;

    private int $endMinute;

    public function __construct(string $start, string $end)
    {
        $this->startMinute = self::minutes($start);
        $this->endMinute = self::minutes($end);
    }

    public function isNight(CarbonImmutable $instant): bool
    {
        $utc = $instant->utc();
        $minute = $utc->hour * 60 + $utc->minute;

        return $this->startMinute <= $this->endMinute
            ? $minute >= $this->startMinute && $minute < $this->endMinute
            : $minute >= $this->startMinute || $minute < $this->endMinute;
    }

    private static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [1 => '0']);

        return $h * 60 + $m;
    }
}
