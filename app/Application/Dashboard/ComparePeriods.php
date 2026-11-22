<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Data\SeriesPoint;
use App\Application\Data\TotalsView;
use App\Application\Ports\UsageReadModel;
use App\Domain\Analysis\PeriodComparison;
use App\Domain\Shared\Granularity;
use App\Domain\Shared\LocalCalendar;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Compares up to four calendar periods of the same kind (day, week or month),
 * each broken down one level finer so their shapes can be overlaid.
 */
final readonly class ComparePeriods
{
    public const MAX_PERIODS = 4;

    public function __construct(
        private MeterPointCatalogue $catalogue,
        private UsageReadModel $usage,
        private LocalCalendar $calendar,
    ) {}

    /**
     * @param  list<string>  $periodDates  Any local date inside each period; first is the baseline.
     * @return array<string, mixed>
     */
    public function handle(?int $meterPointId, Granularity $granularity, array $periodDates): array
    {
        if ($granularity === Granularity::HalfHour) {
            throw new InvalidArgumentException('Periods must be days, weeks or months.');
        }

        $meterPoint = $this->catalogue->resolve($meterPointId);

        if ($meterPoint === null) {
            return ['meters' => [], 'selection' => null];
        }

        $ids = [(int) $meterPoint->id];

        if ($periodDates === []) {
            $periodDates = $this->defaultPeriods($ids, $granularity);
        }

        $periodDates = array_slice($periodDates, 0, self::MAX_PERIODS);
        $finer = $granularity->finer() ?? Granularity::Day;
        $periods = [];
        $baselineTotals = null;

        foreach ($periodDates as $date) {
            $start = $granularity->bucketStart($this->calendar->date($date));
            $end = $granularity->next($start)->subDay();
            $totals = $this->usage->totals($ids, $start->toDateString(), $end->toDateString());
            $baselineTotals ??= $totals;
            $comparison = new PeriodComparison($baselineTotals, $totals);

            $periods[] = [
                'date' => $date,
                'label' => $this->label($granularity, $start, $end),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'totals' => TotalsView::of($totals),
                'energyChangePercent' => $comparison->energyChange()->percent?->toFloat(),
                'costChangePercent' => $comparison->costChange()->percent?->toFloat(),
                'series' => array_map(
                    static fn (SeriesPoint $p): array => $p->toArray(),
                    $this->usage->series($ids, $start->toDateString(), $end->toDateString(), $finer),
                ),
            ];
        }

        return [
            'meters' => $this->catalogue->options(),
            'selection' => [
                'meter' => $meterPoint->id,
                'granularity' => $granularity->value,
                'finer' => $finer->value,
                'periods' => $periodDates,
                'maxPeriods' => self::MAX_PERIODS,
            ],
            'periods' => $periods,
        ];
    }

    /**
     * The latest period with data and the one before it.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function defaultPeriods(array $ids, Granularity $granularity): array
    {
        [, $lastDate] = $this->usage->coverage($ids);
        $latest = $lastDate === null ? $this->calendar->today() : $this->calendar->date($lastDate);
        $previous = $granularity->bucketStart($latest)->subDay();

        return [$previous->toDateString(), $latest->toDateString()];
    }

    private function label(Granularity $granularity, CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($granularity) {
            Granularity::Month => $start->format('F Y'),
            Granularity::Week => 'w/c '.$start->format('j M Y'),
            default => $start->format('D j M Y'),
        };
    }
}
