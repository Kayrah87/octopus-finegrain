<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Data\HeatmapCell;
use App\Application\Data\SeriesPoint;
use App\Domain\Analysis\UsageTotals;
use App\Domain\Shared\Granularity;

/**
 * Fast, aggregated reads over the local store. Dates are local (Y-m-d), inclusive.
 */
interface UsageReadModel
{
    /** @param  list<int>  $meterPointIds */
    public function totals(array $meterPointIds, string $fromDate, string $toDate): UsageTotals;

    /**
     * @param  list<int>  $meterPointIds
     * @return list<SeriesPoint> Ordered by bucket.
     */
    public function series(array $meterPointIds, string $fromDate, string $toDate, Granularity $granularity): array;

    /**
     * @param  list<int>  $meterPointIds
     * @return list<HeatmapCell>
     */
    public function heatmap(array $meterPointIds, string $fromDate, string $toDate): array;

    /** @param  list<int>  $meterPointIds */
    public function daysWithData(array $meterPointIds, string $fromDate, string $toDate): int;

    /**
     * @param  list<int>  $meterPointIds
     * @return array{0: ?string, 1: ?string} First and last local dates with data.
     */
    public function coverage(array $meterPointIds): array;
}
