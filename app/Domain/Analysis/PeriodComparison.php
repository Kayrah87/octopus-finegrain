<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

/**
 * Compares a period's totals against a baseline period.
 */
final readonly class PeriodComparison
{
    public function __construct(
        public UsageTotals $baseline,
        public UsageTotals $current,
    ) {}

    public function energyChange(): Change
    {
        return Change::between($this->baseline->energy->kwh, $this->current->energy->kwh);
    }

    public function costChange(): Change
    {
        return Change::between($this->baseline->totalCost()->pence, $this->current->totalCost()->pence);
    }
}
