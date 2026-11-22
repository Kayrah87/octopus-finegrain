<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Metering\Agreement;
use App\Domain\Metering\MeterPoint;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffRateRepository;
use Carbon\CarbonImmutable;

/**
 * Agreement history per meter point and the unit rates around now for the current tariff.
 */
final readonly class GetTariffHistory
{
    private const DAYS_BACK = 7;

    public function __construct(
        private MeterPointCatalogue $catalogue,
        private TariffRateRepository $rates,
        private LocalCalendar $calendar,
    ) {}

    /** @return array<string, mixed> */
    public function handle(?int $meterPointId): array
    {
        $selected = $this->catalogue->resolve($meterPointId);

        return [
            'meters' => $this->catalogue->options(),
            'selected' => $selected?->id,
            'agreements' => $selected === null ? [] : array_map(
                static fn (Agreement $a): array => [
                    'code' => (string) $a->tariff,
                    'product' => $a->tariff->productCode,
                    'from' => $a->validFrom->toIso8601String(),
                    'to' => $a->validTo?->toIso8601String(),
                    'current' => $a->isCurrent(CarbonImmutable::now('UTC')),
                ],
                array_reverse($selected->agreements),
            ),
            'rates' => $selected === null ? [] : $this->recentRates($selected),
        ];
    }

    /** @return array<string, list<array{from: string, to: string|null, pence: float}>> */
    private function recentRates(MeterPoint $meterPoint): array
    {
        $agreement = $meterPoint->agreementAt(CarbonImmutable::now('UTC'));

        if ($agreement === null) {
            return [];
        }

        $range = $this->calendar->utcRange(
            $this->calendar->today()->subDays(self::DAYS_BACK)->toDateString(),
            $this->calendar->today()->addDay()->toDateString(),
        );
        $result = [];

        foreach ([...$agreement->tariff->unitRateKinds(), RateKind::StandingCharge] as $kind) {
            $result[$kind->value] = array_map(static fn (Rate $r): array => [
                'from' => $r->validFrom->toIso8601String(),
                'to' => $r->validTo?->toIso8601String(),
                'pence' => $r->valueIncVat->pence->toFloat(),
            ], $this->rates->scheduleBetween($agreement->tariff, $kind, $range)->all());
        }

        return $result;
    }
}
