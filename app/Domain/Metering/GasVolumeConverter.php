<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use App\Domain\Shared\Energy;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Converts gas volume (m³) to energy (kWh) using the standard UK formula:
 * kWh = m³ × volume correction × calorific value ÷ 3.6.
 */
final readonly class GasVolumeConverter
{
    private const VOLUME_CORRECTION = '1.02264';

    public function __construct(private string $calorificValue) {}

    public function toEnergy(string $cubicMetres): Energy
    {
        return Energy::ofKwh(
            BigDecimal::of($cubicMetres)
                ->multipliedBy(self::VOLUME_CORRECTION)
                ->multipliedBy($this->calorificValue)
                ->dividedBy('3.6', 6, RoundingMode::HalfEven),
        );
    }
}
