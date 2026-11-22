<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * A quantity of energy in kWh, persisted as integer watt-hours.
 */
final readonly class Energy
{
    private function __construct(public BigDecimal $kwh) {}

    public static function zero(): self
    {
        return new self(BigDecimal::zero());
    }

    public static function ofKwh(BigNumber|int|string $kwh): self
    {
        return new self(BigDecimal::of($kwh));
    }

    public static function ofWattHours(int $wh): self
    {
        return new self(BigDecimal::of($wh)->dividedBy(1000, 3));
    }

    public function plus(self $other): self
    {
        return new self($this->kwh->plus($other->kwh));
    }

    public function toWattHours(): int
    {
        return $this->kwh->multipliedBy(1000)->toScale(0, RoundingMode::HalfEven)->toInt();
    }
}
