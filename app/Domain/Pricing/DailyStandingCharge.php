<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\Money;

final readonly class DailyStandingCharge
{
    public function __construct(
        public string $date,
        public Money $amount,
    ) {}
}
