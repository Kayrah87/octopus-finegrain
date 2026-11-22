<?php

declare(strict_types=1);

namespace App\Application\Data;

final readonly class TelemetryPoint
{
    public function __construct(
        public string $readAt,
        public ?float $demandWatts,
        public ?float $consumptionDeltaWh,
    ) {}
}
