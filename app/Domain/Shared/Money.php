<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * An amount of money in pence (GBP), held as an arbitrary-precision decimal.
 *
 * Octopus quotes rates to several decimal places of a penny, so pence are
 * kept unrounded here and persisted as integer micropence (1p = 1,000,000).
 */
final readonly class Money
{
    private const MICRO = 1_000_000;

    private function __construct(public BigDecimal $pence) {}

    public static function zero(): self
    {
        return new self(BigDecimal::zero());
    }

    public static function ofPence(BigNumber|int|string $pence): self
    {
        return new self(BigDecimal::of($pence));
    }

    public static function ofMicropence(int $micropence): self
    {
        return new self(BigDecimal::of($micropence)->dividedBy(self::MICRO, 6));
    }

    public function plus(self $other): self
    {
        return new self($this->pence->plus($other->pence));
    }

    public function minus(self $other): self
    {
        return new self($this->pence->minus($other->pence));
    }

    public function multipliedBy(BigNumber|int|string $factor): self
    {
        return new self($this->pence->multipliedBy($factor));
    }

    public function isZero(): bool
    {
        return $this->pence->isZero();
    }

    public function toMicropence(): int
    {
        return $this->pence->multipliedBy(self::MICRO)->toScale(0, RoundingMode::HalfEven)->toInt();
    }

    /**
     * Pounds as a decimal string, e.g. "12.34".
     *
     * @param  int<0, max>  $scale
     */
    public function toPoundsString(int $scale = 2): string
    {
        return (string) $this->pence->dividedBy(100, $scale, RoundingMode::HalfEven);
    }
}
