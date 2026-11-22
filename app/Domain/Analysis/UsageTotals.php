<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Shared\Energy;
use App\Domain\Shared\Money;
use Brick\Math\RoundingMode;

/**
 * Totals for a period: energy, cost of that energy, and standing charges.
 */
final readonly class UsageTotals
{
    public function __construct(
        public Energy $energy,
        public Money $unitCost,
        public Money $standingCharge,
    ) {}

    public static function empty(): self
    {
        return new self(Energy::zero(), Money::zero(), Money::zero());
    }

    public function totalCost(): Money
    {
        return $this->unitCost->plus($this->standingCharge);
    }

    /** Average unit price in pence per kWh, excluding standing charges. */
    public function averageUnitRate(): ?Money
    {
        if ($this->energy->kwh->isZero()) {
            return null;
        }

        return Money::ofPence($this->unitCost->pence->dividedBy($this->energy->kwh, 4, RoundingMode::HalfEven));
    }
}
