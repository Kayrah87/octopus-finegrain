<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Metering\Interval;
use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\LocalSlot;
use App\Domain\Shared\Energy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bulk reads/writes of half-hourly intervals via the query builder (tens of thousands of rows).
 */
final class DatabaseIntervalRepository implements IntervalRepository
{
    private const TABLE = 'intervals';

    private const WRITE_CHUNK = 1000;

    private const FORMAT = 'Y-m-d H:i:s';

    public function upsert(int $meterPointId, iterable $intervals): int
    {
        $buffer = [];
        $written = 0;

        foreach ($intervals as $interval) {
            $buffer[] = [
                'meter_point_id' => $meterPointId,
                'interval_start' => $interval->start->utc()->format(self::FORMAT),
                'interval_end' => $interval->end->utc()->format(self::FORMAT),
                'energy_wh' => $interval->energy->toWattHours(),
                'local_date' => $interval->local->date,
                'local_slot' => $interval->local->slot,
                'unit_rate_micropence' => null,
                'cost_micropence' => null,
            ];

            if (count($buffer) >= self::WRITE_CHUNK) {
                $written += $this->flush($buffer);
                $buffer = [];
            }
        }

        return $written + $this->flush($buffer);
    }

    public function latestStart(int $meterPointId): ?CarbonImmutable
    {
        $latest = DB::table(self::TABLE)->where('meter_point_id', $meterPointId)->max('interval_start');

        return is_string($latest) ? CarbonImmutable::parse($latest, 'UTC') : null;
    }

    public function unpricedChunks(int $meterPointId, int $chunkSize = 2000): iterable
    {
        $lastId = 0;

        do {
            $rows = DB::table(self::TABLE)
                ->where('meter_point_id', $meterPointId)
                ->whereNull('cost_micropence')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $lastId = (int) $rows->last()->id;

            yield array_values($rows->map(static fn (object $row): Interval => new Interval(
                CarbonImmutable::parse($row->interval_start, 'UTC'),
                CarbonImmutable::parse($row->interval_end, 'UTC'),
                Energy::ofWattHours((int) $row->energy_wh),
                new LocalSlot((string) $row->local_date, (int) $row->local_slot),
            ))->all());
        } while ($rows->count() === $chunkSize);
    }

    public function savePrices(int $meterPointId, array $intervals): void
    {
        DB::transaction(function () use ($meterPointId, $intervals): void {
            foreach ($intervals as $interval) {
                DB::table(self::TABLE)
                    ->where('meter_point_id', $meterPointId)
                    ->where('interval_start', $interval->start->utc()->format(self::FORMAT))
                    ->update([
                        'unit_rate_micropence' => $interval->unitRate?->toMicropence(),
                        'cost_micropence' => $interval->cost?->toMicropence(),
                    ]);
            }
        });
    }

    public function datesMissingStandingCharge(int $meterPointId): array
    {
        return array_values(DB::table(self::TABLE.' as i')
            ->where('i.meter_point_id', $meterPointId)
            ->whereNotExists(static fn ($q) => $q->selectRaw('1')
                ->from('daily_standing_charges as d')
                ->whereColumn('d.meter_point_id', 'i.meter_point_id')
                ->whereColumn('d.local_date', 'i.local_date'))
            ->distinct()
            ->orderBy('i.local_date')
            ->pluck('i.local_date')
            ->map(static fn (mixed $d): string => (string) $d)
            ->all());
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function flush(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        DB::table(self::TABLE)->upsert(
            $rows,
            ['meter_point_id', 'interval_start'],
            ['interval_end', 'energy_wh', 'local_date', 'local_slot', 'unit_rate_micropence', 'cost_micropence'],
        );

        return count($rows);
    }
}
