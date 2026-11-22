<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

interface StandingChargeRepository
{
    /** @param  list<DailyStandingCharge>  $charges */
    public function save(int $meterPointId, array $charges): void;
}
