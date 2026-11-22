<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use App\Domain\Metering\Fuel;
use InvalidArgumentException;
use Stringable;

/**
 * An Octopus tariff code such as "E-1R-AGILE-24-10-01-C":
 * fuel (E/G), register count (1R/2R), product code, and region letter.
 */
final readonly class TariffCode implements Stringable
{
    private function __construct(
        public string $code,
        public Fuel $fuel,
        public int $registers,
        public string $productCode,
        public string $region,
    ) {}

    public function __toString(): string
    {
        return $this->code;
    }

    public static function parse(string $code): self
    {
        if (preg_match('/^([EG])-(\d)R-(.+)-([A-P])$/', $code, $m) !== 1) {
            throw new InvalidArgumentException("Unrecognised tariff code [{$code}].");
        }

        return new self(
            $code,
            $m[1] === 'E' ? Fuel::Electricity : Fuel::Gas,
            (int) $m[2],
            $m[3],
            $m[4],
        );
    }

    public function isTwoRate(): bool
    {
        return $this->registers === 2;
    }

    /** @return list<RateKind> The unit-rate kinds this tariff publishes. */
    public function unitRateKinds(): array
    {
        return $this->isTwoRate() ? [RateKind::Day, RateKind::Night] : [RateKind::Standard];
    }
}
