<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Tariffs\Economy7Window;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffCode;
use Carbon\CarbonImmutable;

/**
 * Decides which kind of unit rate applies to a tariff at a given instant.
 */
final readonly class UnitRateResolver
{
    public function __construct(private Economy7Window $nightWindow) {}

    public function kindAt(TariffCode $tariff, CarbonImmutable $instant): RateKind
    {
        if (! $tariff->isTwoRate()) {
            return RateKind::Standard;
        }

        return $this->nightWindow->isNight($instant) ? RateKind::Night : RateKind::Day;
    }
}
