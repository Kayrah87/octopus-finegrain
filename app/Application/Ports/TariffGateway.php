<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Shared\DateRange;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffCode;

interface TariffGateway
{
    /** @return list<Rate> */
    public function rates(TariffCode $tariff, RateKind $kind, DateRange $range): array;
}
