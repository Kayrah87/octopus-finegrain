<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use App\Domain\Shared\Energy;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

/**
 * One half-hourly reading for a meter point, optionally priced.
 */
final readonly class Interval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public Energy $energy,
        public LocalSlot $local,
        public ?Money $unitRate = null,
        public ?Money $cost = null,
    ) {}

    public function priced(Money $unitRate): self
    {
        return new self(
            $this->start,
            $this->end,
            $this->energy,
            $this->local,
            $unitRate,
            $unitRate->multipliedBy($this->energy->kwh),
        );
    }
}
