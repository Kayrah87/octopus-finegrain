<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The difference between a current and a baseline value.
 */
final readonly class Change
{
    public function __construct(
        public BigDecimal $delta,
        public ?BigDecimal $percent,
    ) {}

    public static function between(BigDecimal $baseline, BigDecimal $current): self
    {
        $delta = $current->minus($baseline);
        $percent = $baseline->isZero()
            ? null
            : $delta->multipliedBy(100)->dividedBy($baseline->abs(), 1, RoundingMode::HalfEven);

        return new self($delta, $percent);
    }
}
