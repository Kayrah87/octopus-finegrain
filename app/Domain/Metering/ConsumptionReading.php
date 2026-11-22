<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * A raw reading as reported by the meter, in the meter's native unit.
 */
final readonly class ConsumptionReading
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $consumption,
    ) {}
}
