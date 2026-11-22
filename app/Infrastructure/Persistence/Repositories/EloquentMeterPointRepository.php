<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Metering\Agreement;
use App\Domain\Metering\FlowDirection;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\MeterPoint;
use App\Domain\Metering\MeterPointRepository;
use App\Domain\Tariffs\TariffCode;
use App\Infrastructure\Persistence\Eloquent\AgreementRecord;
use App\Infrastructure\Persistence\Eloquent\MeterPointRecord;
use App\Infrastructure\Persistence\Eloquent\MeterRecord;
use Illuminate\Support\Facades\DB;

final class EloquentMeterPointRepository implements MeterPointRepository
{
    public function save(MeterPoint $meterPoint): MeterPoint
    {
        return DB::transaction(function () use ($meterPoint): MeterPoint {
            $record = MeterPointRecord::query()->updateOrCreate(
                ['mpxn' => $meterPoint->mpxn],
                ['fuel' => $meterPoint->fuel->value, 'direction' => $meterPoint->direction->value],
            );

            $record->meters()->delete();
            $record->meters()->createMany(array_map(
                static fn (string $serial): array => ['serial' => $serial],
                array_values(array_unique($meterPoint->meterSerials)),
            ));

            $record->agreements()->delete();
            $record->agreements()->createMany(array_map(static fn (Agreement $a): array => [
                'tariff_code' => (string) $a->tariff,
                'product_code' => $a->tariff->productCode,
                'valid_from' => $a->validFrom->utc(),
                'valid_to' => $a->validTo?->utc(),
            ], $meterPoint->agreements));

            return $meterPoint->withId($record->id);
        });
    }

    public function all(): array
    {
        return array_values(MeterPointRecord::query()
            ->with(['meters', 'agreements'])
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all());
    }

    public function find(int $id): ?MeterPoint
    {
        $record = MeterPointRecord::query()->with(['meters', 'agreements'])->find($id);

        return $record === null ? null : $this->toDomain($record);
    }

    private function toDomain(MeterPointRecord $record): MeterPoint
    {
        return new MeterPoint(
            $record->id,
            Fuel::from($record->fuel),
            FlowDirection::from($record->direction),
            $record->mpxn,
            array_values($record->meters->map(static fn (MeterRecord $m): string => $m->serial)->all()),
            array_values($record->agreements->map(static fn (AgreementRecord $a): Agreement => new Agreement(
                TariffCode::parse($a->tariff_code),
                $a->valid_from,
                $a->valid_to,
            ))->all()),
        );
    }
}
