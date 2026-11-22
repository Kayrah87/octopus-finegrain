<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Data\SeriesPoint;
use App\Application\Data\TotalsView;
use App\Application\Ports\LiveTelemetryGateway;
use App\Application\Ports\SyncLog;
use App\Application\Ports\UsageReadModel;
use App\Domain\Analysis\PeriodComparison;
use App\Domain\Metering\MeterPoint;
use App\Domain\Pricing\UnitRateResolver;
use App\Domain\Shared\Granularity;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffRateRepository;
use Carbon\CarbonImmutable;

/**
 * Headline figures for each meter point, anchored on the latest day with data
 * (Octopus usually publishes readings a day late).
 */
final readonly class GetOverview
{
    public function __construct(
        private MeterPointCatalogue $catalogue,
        private UsageReadModel $usage,
        private TariffRateRepository $rates,
        private UnitRateResolver $rateResolver,
        private LocalCalendar $calendar,
        private SyncLog $syncLog,
        private LiveTelemetryGateway $live,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'meters' => array_map($this->meterSummary(...), $this->catalogue->sorted()),
            'sync' => $this->syncLog->latest(),
            'liveEnabled' => $this->live->isConfigured(),
        ];
    }

    /** @return array<string, mixed> */
    private function meterSummary(MeterPoint $meterPoint): array
    {
        $ids = [(int) $meterPoint->id];
        [, $lastDate] = $this->usage->coverage($ids);
        $anchor = $lastDate === null ? $this->calendar->today()->subDay() : $this->calendar->date($lastDate);

        return [
            'id' => $meterPoint->id,
            'label' => $meterPoint->label(),
            'fuel' => $meterPoint->fuel->value,
            'direction' => $meterPoint->direction->value,
            'latestDate' => $lastDate,
            'periods' => [
                'day' => $this->compare($ids, $anchor, $anchor, $anchor->subDay(), $anchor->subDay()),
                'week' => $this->compare($ids, $anchor->startOfWeek(), $anchor, $anchor->startOfWeek()->subWeek(), $anchor->subWeek()),
                'month' => $this->compare($ids, $anchor->startOfMonth(), $anchor, $anchor->startOfMonth()->subMonthNoOverflow(), $anchor->subMonthNoOverflow()),
            ],
            'last14Days' => array_map(
                static fn (SeriesPoint $p): array => $p->toArray(),
                $this->usage->series($ids, $anchor->subDays(13)->toDateString(), $anchor->toDateString(), Granularity::Day),
            ),
            'tariff' => $this->currentTariff($meterPoint),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function compare(array $ids, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $baseFrom, CarbonImmutable $baseTo): array
    {
        $comparison = new PeriodComparison(
            $this->usage->totals($ids, $baseFrom->toDateString(), $baseTo->toDateString()),
            $this->usage->totals($ids, $from->toDateString(), $to->toDateString()),
        );

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'current' => TotalsView::of($comparison->current),
            'baseline' => TotalsView::of($comparison->baseline),
            'energyChangePercent' => $comparison->energyChange()->percent?->toFloat(),
            'costChangePercent' => $comparison->costChange()->percent?->toFloat(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function currentTariff(MeterPoint $meterPoint): ?array
    {
        $now = CarbonImmutable::now('UTC');
        $agreement = $meterPoint->agreementAt($now);

        if ($agreement === null) {
            return null;
        }

        $kind = $this->rateResolver->kindAt($agreement->tariff, $now);

        return [
            'code' => (string) $agreement->tariff,
            'product' => $agreement->tariff->productCode,
            'since' => $agreement->validFrom->toIso8601String(),
            'until' => $agreement->validTo?->toIso8601String(),
            'unitRate' => $this->rates->schedule($agreement->tariff, $kind)->rateAt($now)?->valueIncVat->pence->toFloat(),
            'standingCharge' => $this->rates->schedule($agreement->tariff, RateKind::StandingCharge)->rateAt($now)?->valueIncVat->pence->toFloat(),
        ];
    }
}
