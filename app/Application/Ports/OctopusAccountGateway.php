<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Metering\MeterPoint;

interface OctopusAccountGateway
{
    /** @return list<MeterPoint> Meter points (without ids) currently on the account. */
    public function meterPoints(): array;
}
