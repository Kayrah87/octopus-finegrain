<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The dashboard's wall-clock calendar (Europe/London by default).
 */
final readonly class LocalCalendar
{
    public function __construct(public string $timezone) {}

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function date(string $ymd): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $ymd, $this->timezone)
            ?: throw new InvalidArgumentException("Invalid date [{$ymd}].");
    }

    /** UTC range covering local dates $from..$to inclusive. */
    public function utcRange(string $from, string $to): DateRange
    {
        return new DateRange($this->date($from)->utc(), $this->date($to)->addDay()->utc());
    }
}
