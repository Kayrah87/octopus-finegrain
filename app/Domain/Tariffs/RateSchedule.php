<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use Carbon\CarbonImmutable;

/**
 * An ordered, searchable list of rates for one tariff and kind.
 */
final readonly class RateSchedule
{
    /** @var list<Rate> */
    private array $rates;

    /** @param  list<Rate>  $rates */
    public function __construct(array $rates)
    {
        usort($rates, static fn (Rate $a, Rate $b): int => $a->validFrom <=> $b->validFrom);
        $this->rates = $rates;
    }

    public function isEmpty(): bool
    {
        return $this->rates === [];
    }

    /** Binary search for the rate in force at the given instant. */
    public function rateAt(CarbonImmutable $instant): ?Rate
    {
        $low = 0;
        $high = count($this->rates) - 1;
        $candidate = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->rates[$mid]->validFrom->lessThanOrEqualTo($instant)) {
                $candidate = $this->rates[$mid];
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $candidate?->covers($instant) ? $candidate : null;
    }

    /** @return list<Rate> */
    public function all(): array
    {
        return $this->rates;
    }
}
