<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open time range [from, to).
 */
final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {
        if ($to->lessThan($from)) {
            throw new InvalidArgumentException('A date range cannot end before it starts.');
        }
    }

    public function contains(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->from) && $instant->lessThan($this->to);
    }

    public function lengthInDays(): int
    {
        return (int) $this->from->diffInDays($this->to);
    }

    public function isEmpty(): bool
    {
        return $this->from->equalTo($this->to);
    }
}
