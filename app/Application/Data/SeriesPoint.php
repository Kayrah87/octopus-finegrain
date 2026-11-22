<?php

declare(strict_types=1);

namespace App\Application\Data;

use App\Domain\Analysis\UsageTotals;

final readonly class SeriesPoint
{
    /** @param  string  $bucket  Bucket start: Y-m-d for day+, ISO-8601 UTC for half-hours. */
    public function __construct(
        public string $bucket,
        public UsageTotals $totals,
    ) {}

    /** @return array{bucket: string, kwh: float, cost: float, unitCost: float, standing: float} */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'kwh' => $this->totals->energy->kwh->toFloat(),
            'cost' => (float) $this->totals->totalCost()->toPoundsString(4),
            'unitCost' => (float) $this->totals->unitCost->toPoundsString(4),
            'standing' => (float) $this->totals->standingCharge->toPoundsString(4),
        ];
    }
}
