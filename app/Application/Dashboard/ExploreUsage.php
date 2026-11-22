<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Data\HeatmapCell;
use App\Application\Data\SeriesPoint;
use App\Application\Data\TotalsView;
use App\Application\Ports\UsageReadModel;
use App\Domain\Shared\Granularity;
use App\Domain\Shared\LocalCalendar;

/**
 * A drillable usage/cost series for one meter point over a local date range.
 */
final readonly class ExploreUsage
{
    private const HEATMAP_MAX_DAYS = 92;

    private const DEFAULT_DAYS = 30;

    public function __construct(
        private MeterPointCatalogue $catalogue,
        private UsageReadModel $usage,
        private LocalCalendar $calendar,
    ) {}

    /** @return array<string, mixed> */
    public function handle(?int $meterPointId, ?string $from, ?string $to, ?Granularity $granularity): array
    {
        $meterPoint = $this->catalogue->resolve($meterPointId);

        if ($meterPoint === null) {
            return ['meters' => [], 'selection' => null];
        }

        $ids = [(int) $meterPoint->id];
        [$firstDate, $lastDate] = $this->usage->coverage($ids);

        $toDate = $to ?? $lastDate ?? $this->calendar->today()->toDateString();
        $fromDate = $from ?? $this->calendar->date($toDate)->subDays(self::DEFAULT_DAYS - 1)->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $days = $this->calendar->date($fromDate)->diffInDays($this->calendar->date($toDate)) + 1;
        $granularity ??= Granularity::forDays((int) $days);

        return [
            'meters' => $this->catalogue->options(),
            'selection' => [
                'meter' => $meterPoint->id,
                'from' => $fromDate,
                'to' => $toDate,
                'granularity' => $granularity->value,
                'finer' => $granularity->finer()?->value,
            ],
            'coverage' => ['from' => $firstDate, 'to' => $lastDate],
            'totals' => TotalsView::of($this->usage->totals($ids, $fromDate, $toDate)),
            'series' => array_map(
                static fn (SeriesPoint $p): array => $p->toArray(),
                $this->usage->series($ids, $fromDate, $toDate, $granularity),
            ),
            'heatmap' => $days <= self::HEATMAP_MAX_DAYS
                ? array_map(static fn (HeatmapCell $c): array => (array) $c, $this->usage->heatmap($ids, $fromDate, $toDate))
                : null,
        ];
    }
}
