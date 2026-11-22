<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use App\Domain\Shared\Energy;

/**
 * Turns raw meter readings into local, kWh-denominated intervals.
 */
final readonly class ReadingNormaliser
{
    public function __construct(
        private GasVolumeConverter $gasConverter,
        private bool $gasReportsCubicMetres,
        private string $timezone,
    ) {}

    public function normalise(Fuel $fuel, ConsumptionReading $reading): Interval
    {
        $energy = $fuel === Fuel::Gas && $this->gasReportsCubicMetres
            ? $this->gasConverter->toEnergy($reading->consumption)
            : Energy::ofKwh($reading->consumption);

        return new Interval(
            $reading->start,
            $reading->end,
            $energy,
            LocalSlot::of($reading->start, $this->timezone),
        );
    }
}
