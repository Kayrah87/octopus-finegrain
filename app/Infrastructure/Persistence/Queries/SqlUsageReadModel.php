<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Data\HeatmapCell;
use App\Application\Data\SeriesPoint;
use App\Application\Ports\UsageReadModel;
use App\Domain\Analysis\UsageTotals;
use App\Domain\Shared\Energy;
use App\Domain\Shared\Granularity;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates over the precomputed local_date column, so no time-zone maths is needed in SQL.
 * Integer columns (Wh, micropence) keep SUMs exact.
 */
final readonly class SqlUsageReadModel implements UsageReadModel
{
    public function __construct(private LocalCalendar $calendar) {}

    public function totals(array $meterPointIds, string $fromDate, string $toDate): UsageTotals
    {
        $usage = $this->intervals($meterPointIds, $fromDate, $toDate)
            ->selectRaw('COALESCE(SUM(energy_wh), 0) AS wh, COALESCE(SUM(cost_micropence), 0) AS cost')
            ->first();

        $standing = (int) $this->standing($meterPointIds, $fromDate, $toDate)->sum('amount_micropence');

        return new UsageTotals(
            Energy::ofWattHours((int) ($usage->wh ?? 0)),
            Money::ofMicropence((int) ($usage->cost ?? 0)),
            Money::ofMicropence($standing),
        );
    }

    public function series(array $meterPointIds, string $fromDate, string $toDate, Granularity $granularity): array
    {
        $bucket = $this->bucketExpression($granularity);

        $usage = $this->intervals($meterPointIds, $fromDate, $toDate)
            ->selectRaw("{$bucket} AS bucket, SUM(energy_wh) AS wh, COALESCE(SUM(cost_micropence), 0) AS cost")
            ->groupByRaw($bucket)
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $standing = $granularity === Granularity::HalfHour
            ? collect()
            : $this->standing($meterPointIds, $fromDate, $toDate)
                ->selectRaw("{$bucket} AS bucket, SUM(amount_micropence) AS amount")
                ->groupByRaw($bucket)
                ->pluck('amount', 'bucket');

        $buckets = $granularity === Granularity::HalfHour
            ? $usage->keys()->all()
            : $this->calendarBuckets($fromDate, $toDate, $granularity);

        return array_values(array_map(function (string $key) use ($usage, $standing, $granularity): SeriesPoint {
            $row = $usage->get($key);

            return new SeriesPoint(
                $granularity === Granularity::HalfHour ? CarbonImmutable::parse($key, 'UTC')->toIso8601ZuluString() : $key,
                new UsageTotals(
                    Energy::ofWattHours((int) ($row->wh ?? 0)),
                    Money::ofMicropence((int) ($row->cost ?? 0)),
                    Money::ofMicropence((int) ($standing->get($key) ?? 0)),
                ),
                $row !== null,
            );
        }, array_map('strval', $buckets)));
    }

    public function heatmap(array $meterPointIds, string $fromDate, string $toDate): array
    {
        return array_values($this->intervals($meterPointIds, $fromDate, $toDate)
            ->selectRaw('local_date, local_slot, SUM(energy_wh) AS wh, COALESCE(SUM(cost_micropence), 0) AS cost')
            ->groupBy('local_date', 'local_slot')
            ->orderBy('local_date')
            ->orderBy('local_slot')
            ->get()
            ->map(static fn (object $row): HeatmapCell => new HeatmapCell(
                (string) $row->local_date,
                (int) $row->local_slot,
                round((int) $row->wh / 1000, 3),
                round((int) $row->cost / 100_000_000, 4),
            ))
            ->values()
            ->all());
    }

    public function daysWithData(array $meterPointIds, string $fromDate, string $toDate): int
    {
        return $this->intervals($meterPointIds, $fromDate, $toDate)->distinct()->count('local_date');
    }

    public function coverage(array $meterPointIds): array
    {
        $row = DB::table('intervals')
            ->whereIn('meter_point_id', $meterPointIds)
            ->selectRaw('MIN(local_date) AS first, MAX(local_date) AS last')
            ->first();

        return [
            $row?->first === null ? null : (string) $row->first,
            $row?->last === null ? null : (string) $row->last,
        ];
    }

    /** @param  list<int>  $meterPointIds */
    private function intervals(array $meterPointIds, string $fromDate, string $toDate): Builder
    {
        return DB::table('intervals')
            ->whereIn('meter_point_id', $meterPointIds)
            ->whereBetween('local_date', [$fromDate, $toDate]);
    }

    /** @param  list<int>  $meterPointIds */
    private function standing(array $meterPointIds, string $fromDate, string $toDate): Builder
    {
        return DB::table('daily_standing_charges')
            ->whereIn('meter_point_id', $meterPointIds)
            ->whereBetween('local_date', [$fromDate, $toDate]);
    }

    /**
     * SQLite bucket expressions; weeks start on Monday.
     *
     * @return literal-string
     */
    private function bucketExpression(Granularity $granularity): string
    {
        return match ($granularity) {
            Granularity::HalfHour => 'interval_start',
            Granularity::Day => 'local_date',
            Granularity::Week => "date(local_date, 'weekday 0', '-6 days')",
            Granularity::Month => "strftime('%Y-%m-01', local_date)",
        };
    }

    /** @return list<string> Every bucket start in range, so gaps show as zero. */
    private function calendarBuckets(string $fromDate, string $toDate, Granularity $granularity): array
    {
        $cursor = $granularity->bucketStart($this->calendar->date($fromDate));
        $end = $this->calendar->date($toDate);
        $buckets = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $buckets[] = $cursor->toDateString();
            $cursor = $granularity->next($cursor);
        }

        return $buckets;
    }
}
