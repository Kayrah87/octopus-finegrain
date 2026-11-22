<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Metering\ConsumptionReading;
use App\Domain\Metering\Fuel;
use App\Domain\Shared\DateRange;

interface ConsumptionGateway
{
    /** @return iterable<ConsumptionReading> */
    public function readings(Fuel $fuel, string $mpxn, string $serial, DateRange $range): iterable;
}
