<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Data\SeriesPoint;
use App\Application\Ports\UsageReadModel;
use App\Domain\Shared\Energy;
use App\Domain\Shared\Granularity;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

/**
 * Running totals (day so far, week/month/year to date) as at the end of each series bucket.
 *
 * Totals include standing charges and are computed from all stored data, not just the
 * visible range, so "year to date" is right even when the range starts mid-year.
 * Sums use integer Wh and micropence prefix sums, so they stay exact.
 */
final readonly class CumulativeTotals
{
    public function __construct(
        private UsageReadModel $usage,
        private LocalCalendar $calendar,
    ) {}

    /**
     * @param  list<int>  $meterPointIds
     * @param  list<SeriesPoint>  $series  The visible series, ordered by bucket.
     * @return list<array<string, array{kwh: float, cost: float}>|null> One entry per series point; null when it has no data.
     */
    public function forSeries(array $meterPointIds, array $series, Granularity $granularity, string $toDate): array
    {
        if ($series === []) {
            return [];
        }

        $ends = array_map(fn (SeriesPoint $p): string => $this->lastLocalDate($p, $granularity, $toDate), $series);
        $first = $this->calendar->date(min($ends));
        // A week can start in the previous year (e.g. Thu 1 Jan is in the week of Mon 29 Dec).
        $earliest = $first->startOfYear()->min($first->startOfWeek(CarbonImmutable::MONDAY))->toDateString();
        $prefix = $this->dailyPrefix($meterPointIds, $earliest, max($ends));
        $intraday = $granularity === Granularity::HalfHour ? $this->intradayRunning($series) : [];

        $result = [];

        foreach ($series as $i => $point) {
            if (! $point->hasData) {
                $result[] = null;

                continue;
            }

            $end = $ends[$i];
            $day = $this->calendar->date($end);
            // Through the end of the bucket: whole previous days, plus today so far for half-hours.
            $throughBucket = $granularity === Granularity::HalfHour
                ? self::add($this->sum($prefix, $end, $end, standingOnly: true), $intraday[$i] ?? [0, 0])
                : null;

            $toDateFrom = fn (CarbonImmutable $periodStart): array => $throughBucket === null
                ? $this->sum($prefix, $periodStart->toDateString(), $end)
                : self::add($this->sum($prefix, $periodStart->toDateString(), $day->subDay()->toDateString()), $throughBucket);

            $periods = match ($granularity) {
                Granularity::HalfHour => ['day' => $throughBucket ?? [0, 0], 'week' => $toDateFrom($day->startOfWeek(CarbonImmutable::MONDAY))],
                Granularity::Day => ['week' => $toDateFrom($day->startOfWeek(CarbonImmutable::MONDAY))],
                default => [],
            };

            if ($granularity !== Granularity::Month) {
                $periods['month'] = $toDateFrom($day->startOfMonth());
            }

            $periods['year'] = $toDateFrom($day->startOfYear());

            $result[] = array_map(static fn (array $wm): array => [
                'kwh' => Energy::ofWattHours($wm[0])->kwh->toFloat(),
                'cost' => (float) Money::ofMicropence($wm[1])->toPoundsString(4),
            ], $periods);
        }

        return $result;
    }

    /** The last local date a bucket covers, clipped to the end of the visible range. */
    private function lastLocalDate(SeriesPoint $point, Granularity $granularity, string $toDate): string
    {
        if ($granularity === Granularity::HalfHour) {
            return CarbonImmutable::parse($point->bucket)->setTimezone($this->calendar->timezone)->toDateString();
        }

        $last = $granularity->next($this->calendar->date($point->bucket))->subDay()->toDateString();

        return min($last, $toDate);
    }

    /**
     * Running totals by local date as [Wh, total micropence, standing micropence], starting at $from:
     * "before" is the total up to the start of that date, "through" includes it.
     *
     * @param  list<int>  $ids
     * @return array{before: array<string, array{0: int, 1: int, 2: int}>, through: array<string, array{0: int, 1: int, 2: int}>}
     */
    private function dailyPrefix(array $ids, string $from, string $to): array
    {
        $before = [];
        $through = [];
        $running = [0, 0, 0];

        foreach ($this->usage->series($ids, $from, $to, Granularity::Day) as $day) {
            $before[$day->bucket] = $running;
            $running = [
                $running[0] + $day->totals->energy->toWattHours(),
                $running[1] + $day->totals->totalCost()->toMicropence(),
                $running[2] + $day->totals->standingCharge->toMicropence(),
            ];
            $through[$day->bucket] = $running;
        }

        return ['before' => $before, 'through' => $through];
    }

    /**
     * Inclusive sum over local dates [$from, $to] as [Wh, micropence]; with $standingOnly,
     * [0, standing micropence]. Dates before the prefix start count as zero.
     *
     * @param  array{before: array<string, array{0: int, 1: int, 2: int}>, through: array<string, array{0: int, 1: int, 2: int}>}  $prefix
     * @return array{0: int, 1: int}
     */
    private function sum(array $prefix, string $from, string $to, bool $standingOnly = false): array
    {
        $end = $prefix['through'][$to] ?? null;

        if ($to < $from || $end === null) {
            return [0, 0];
        }

        $start = $prefix['before'][$from] ?? [0, 0, 0];

        return $standingOnly
            ? [0, $end[2] - $start[2]]
            : [$end[0] - $start[0], $end[1] - $start[1]];
    }

    /**
     * Running energy and unit cost within each local day for half-hourly points.
     *
     * @param  list<SeriesPoint>  $series
     * @return array<int, array{0: int, 1: int}>
     */
    private function intradayRunning(array $series): array
    {
        $running = [];
        $currentDate = null;
        $wh = 0;
        $cost = 0;

        foreach ($series as $i => $point) {
            $date = CarbonImmutable::parse($point->bucket)->setTimezone($this->calendar->timezone)->toDateString();

            if ($date !== $currentDate) {
                [$currentDate, $wh, $cost] = [$date, 0, 0];
            }

            $wh += $point->totals->energy->toWattHours();
            $cost += $point->totals->unitCost->toMicropence();
            $running[$i] = [$wh, $cost];
        }

        return $running;
    }

    /**
     * @param  array{0: int, 1: int}  $a
     * @param  array{0: int, 1: int}  $b
     * @return array{0: int, 1: int}
     */
    private static function add(array $a, array $b): array
    {
        return [$a[0] + $b[0], $a[1] + $b[1]];
    }
}
