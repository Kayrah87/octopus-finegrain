<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Ports\TariffGateway;
use App\Domain\Metering\MeterPoint;
use App\Domain\Shared\DateRange;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffCode;
use App\Domain\Tariffs\TariffRateRepository;
use Carbon\CarbonImmutable;

/**
 * Fetches unit rates and standing charges for every tariff the account has been on.
 * Fetches a little into the future so day-ahead (e.g. Agile) prices are available.
 */
final readonly class SyncTariffRates
{
    private const LOOKAHEAD_DAYS = 2;

    public function __construct(
        private TariffGateway $gateway,
        private TariffRateRepository $rates,
    ) {}

    /**
     * @param  list<MeterPoint>  $meterPoints
     * @return int Number of rates written.
     */
    public function handle(array $meterPoints): int
    {
        $written = 0;

        foreach ($this->wantedRanges($meterPoints) as [$tariff, $kind, $range]) {
            $rates = $this->gateway->rates($tariff, $kind, $range);
            $this->rates->upsert($tariff, $kind, $rates);
            $written += count($rates);
        }

        return $written;
    }

    /**
     * One fetch range per (tariff, kind), resuming from what is already stored.
     *
     * @param  list<MeterPoint>  $meterPoints
     * @return list<array{0: TariffCode, 1: RateKind, 2: DateRange}>
     */
    private function wantedRanges(array $meterPoints): array
    {
        $horizon = CarbonImmutable::now('UTC')->addDays(self::LOOKAHEAD_DAYS)->startOfDay();
        $wanted = [];

        foreach ($meterPoints as $meterPoint) {
            foreach ($meterPoint->agreements as $agreement) {
                $kinds = [...$agreement->tariff->unitRateKinds(), RateKind::StandingCharge];

                foreach ($kinds as $kind) {
                    $key = "{$agreement->tariff}|{$kind->value}";
                    $from = $agreement->validFrom->utc();
                    $to = $agreement->validTo?->utc()->min($horizon) ?? $horizon;

                    if (isset($wanted[$key])) {
                        $from = $from->min($wanted[$key][2]->from);
                        $to = $to->max($wanted[$key][2]->to);
                    }

                    $wanted[$key] = [$agreement->tariff, $kind, new DateRange($from, $to->max($from))];
                }
            }
        }

        return array_values(array_map(fn (array $w): array => $this->resume(...$w), $wanted));
    }

    /** @return array{0: TariffCode, 1: RateKind, 2: DateRange} */
    private function resume(TariffCode $tariff, RateKind $kind, DateRange $range): array
    {
        $latest = $this->rates->latestValidFrom($tariff, $kind);
        $from = $latest === null ? $range->from : $latest->subDay()->max($range->from);

        return [$tariff, $kind, new DateRange($from->min($range->to), $range->to)];
    }
}
