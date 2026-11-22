<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use App\Domain\Shared\DateRange;
use Carbon\CarbonImmutable;

interface TariffRateRepository
{
    /**
     * @param  iterable<Rate>  $rates
     * @return int Number of rates written.
     */
    public function upsert(TariffCode $tariff, RateKind $kind, iterable $rates): int;

    public function schedule(TariffCode $tariff, RateKind $kind): RateSchedule;

    /** Schedule restricted to rates overlapping the given range. */
    public function scheduleBetween(TariffCode $tariff, RateKind $kind, DateRange $range): RateSchedule;

    public function latestValidFrom(TariffCode $tariff, RateKind $kind): ?CarbonImmutable;
}
