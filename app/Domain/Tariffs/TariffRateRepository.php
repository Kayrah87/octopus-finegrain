<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use App\Domain\Shared\DateRange;
use Carbon\CarbonImmutable;

interface TariffRateRepository
{
    /** @param  list<Rate>  $rates */
    public function upsert(TariffCode $tariff, RateKind $kind, array $rates): void;

    public function schedule(TariffCode $tariff, RateKind $kind): RateSchedule;

    /** Schedule restricted to rates overlapping the given range. */
    public function scheduleBetween(TariffCode $tariff, RateKind $kind, DateRange $range): RateSchedule;

    public function latestValidFrom(TariffCode $tariff, RateKind $kind): ?CarbonImmutable;
}
