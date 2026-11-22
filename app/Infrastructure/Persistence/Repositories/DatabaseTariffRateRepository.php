<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Shared\DateRange;
use App\Domain\Shared\Money;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\RateSchedule;
use App\Domain\Tariffs\TariffCode;
use App\Domain\Tariffs\TariffRateRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DatabaseTariffRateRepository implements TariffRateRepository
{
    private const TABLE = 'tariff_rates';

    private const FORMAT = 'Y-m-d H:i:s';

    private const WRITE_CHUNK = 1000;

    public function upsert(TariffCode $tariff, RateKind $kind, iterable $rates): int
    {
        $buffer = [];
        $written = 0;

        foreach ($rates as $rate) {
            $buffer[] = [
                'tariff_code' => (string) $tariff,
                'kind' => $kind->value,
                'value_micropence' => $rate->valueIncVat->toMicropence(),
                'valid_from' => $rate->validFrom->utc()->format(self::FORMAT),
                'valid_to' => $rate->validTo?->utc()->format(self::FORMAT),
            ];

            if (count($buffer) >= self::WRITE_CHUNK) {
                $written += $this->flush($buffer);
                $buffer = [];
            }
        }

        return $written + $this->flush($buffer);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function flush(array $rows): int
    {
        if ($rows !== []) {
            DB::table(self::TABLE)->upsert($rows, ['tariff_code', 'kind', 'valid_from'], ['value_micropence', 'valid_to']);
        }

        return count($rows);
    }

    public function schedule(TariffCode $tariff, RateKind $kind): RateSchedule
    {
        return $this->toSchedule($this->query($tariff, $kind));
    }

    public function scheduleBetween(TariffCode $tariff, RateKind $kind, DateRange $range): RateSchedule
    {
        return $this->toSchedule(
            $this->query($tariff, $kind)
                ->where('valid_from', '<', $range->to->utc()->format(self::FORMAT))
                ->where(fn (Builder $q) => $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>', $range->from->utc()->format(self::FORMAT))),
        );
    }

    public function latestValidFrom(TariffCode $tariff, RateKind $kind): ?CarbonImmutable
    {
        $latest = $this->query($tariff, $kind)->max('valid_from');

        return is_string($latest) ? CarbonImmutable::parse($latest, 'UTC') : null;
    }

    private function query(TariffCode $tariff, RateKind $kind): Builder
    {
        return DB::table(self::TABLE)->where('tariff_code', (string) $tariff)->where('kind', $kind->value);
    }

    private function toSchedule(Builder $query): RateSchedule
    {
        return new RateSchedule(array_values($query->orderBy('valid_from')->get()->map(static fn (object $row): Rate => new Rate(
            Money::ofMicropence((int) $row->value_micropence),
            CarbonImmutable::parse($row->valid_from, 'UTC'),
            $row->valid_to === null ? null : CarbonImmutable::parse($row->valid_to, 'UTC'),
        ))->all()));
    }
}
