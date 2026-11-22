<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Metering\MeterPoint;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use Carbon\CarbonImmutable;

/**
 * Works out the standing charge for each local calendar day, using the
 * tariff in force at local midday (avoids ambiguity on switch-over days).
 */
final readonly class StandingChargeCalculator
{
    public function __construct(private string $timezone) {}

    /**
     * @param  list<string>  $dates  Local dates, Y-m-d.
     * @param  callable(TariffCode, RateKind): RateSchedule  $schedules
     * @return list<DailyStandingCharge>
     */
    public function forDates(MeterPoint $meterPoint, array $dates, callable $schedules): array
    {
        $charges = [];

        foreach ($dates as $date) {
            $midday = CarbonImmutable::parse("{$date} 12:00", $this->timezone)->utc();
            $agreement = $meterPoint->agreementAt($midday);
            $rate = $agreement === null
                ? null
                : $schedules($agreement->tariff, RateKind::StandingCharge)->rateAt($midday);

            if ($rate !== null) {
                $charges[] = new DailyStandingCharge($date, $rate->valueIncVat);
            }
        }

        return $charges;
    }
}
