<?php

declare(strict_types=1);

namespace App\Application\Data;

use App\Domain\Analysis\UsageTotals;

/**
 * UsageTotals flattened for presentation (pounds and kWh as display numbers).
 */
final readonly class TotalsView
{
    /** @return array{kwh: float, cost: float, unitCost: float, standing: float, avgRate: float|null} */
    public static function of(UsageTotals $totals): array
    {
        return [
            'kwh' => round($totals->energy->kwh->toFloat(), 3),
            'cost' => (float) $totals->totalCost()->toPoundsString(),
            'unitCost' => (float) $totals->unitCost->toPoundsString(),
            'standing' => (float) $totals->standingCharge->toPoundsString(),
            'avgRate' => $totals->averageUnitRate()?->pence->toFloat(),
        ];
    }
}
