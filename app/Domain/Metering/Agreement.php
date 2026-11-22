<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use App\Domain\Tariffs\TariffCode;
use Carbon\CarbonImmutable;

/**
 * The tariff a meter point was on between two instants (open-ended when $validTo is null).
 */
final readonly class Agreement
{
    public function __construct(
        public TariffCode $tariff,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo,
    ) {}

    public function covers(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->validFrom)
            && ($this->validTo === null || $instant->lessThan($this->validTo));
    }

    public function isCurrent(CarbonImmutable $now): bool
    {
        return $this->covers($now);
    }
}
