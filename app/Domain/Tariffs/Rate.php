<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

/**
 * A price (inc. VAT) valid between two instants. Unit rates are pence per kWh;
 * standing charges are pence per day.
 */
final readonly class Rate
{
    public function __construct(
        public Money $valueIncVat,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo,
    ) {}

    public function covers(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->validFrom)
            && ($this->validTo === null || $instant->lessThan($this->validTo));
    }
}
