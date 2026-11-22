<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Data\TelemetryPoint;
use App\Domain\Shared\DateRange;

interface LiveTelemetryGateway
{
    public function isConfigured(): bool;

    /** @return list<TelemetryPoint> Empty when no Home Mini is found. */
    public function telemetry(DateRange $range): array;
}
