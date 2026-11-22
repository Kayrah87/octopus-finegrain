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
        public bool $hasData = true,
    ) {}

    /**
     * Buckets without readings (e.g. days not yet published) are null, not zero,
     * so charts show a gap rather than a misleading drop.
     *
     * @return array{bucket: string, kwh: float|null, cost: float|null, unitCost: float|null, standing: float|null}
     */
    public function toArray(): array
    {
        if (! $this->hasData) {
            return ['bucket' => $this->bucket, 'kwh' => null, 'cost' => null, 'unitCost' => null, 'standing' => null];
        }

        return [
            'bucket' => $this->bucket,
            'kwh' => $this->totals->energy->kwh->toFloat(),
            'cost' => (float) $this->totals->totalCost()->toPoundsString(4),
            'unitCost' => (float) $this->totals->unitCost->toPoundsString(4),
            'standing' => (float) $this->totals->standingCharge->toPoundsString(4),
        ];
    }
}
