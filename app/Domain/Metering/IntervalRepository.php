<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use Carbon\CarbonImmutable;

interface IntervalRepository
{
    /**
     * Insert or replace readings. Replaced readings lose their price so they are re-priced.
     *
     * @param  iterable<Interval>  $intervals
     */
    public function upsert(int $meterPointId, iterable $intervals): int;

    public function latestStart(int $meterPointId): ?CarbonImmutable;

    /** @return iterable<list<Interval>> Unpriced intervals, in chunks. */
    public function unpricedChunks(int $meterPointId, int $chunkSize = 2000): iterable;

    /** @param  list<Interval>  $intervals  Intervals carrying their unit rate and cost. */
    public function savePrices(int $meterPointId, array $intervals): void;

    /** @return list<string> Local dates (Y-m-d) that have readings but no standing charge recorded. */
    public function datesMissingStandingCharge(int $meterPointId): array;
}
