<?php

declare(strict_types=1);

namespace App\Domain\Metering;

interface MeterPointRepository
{
    /** Insert or update by MPxN, replacing meters and agreements. Returns it with an id. */
    public function save(MeterPoint $meterPoint): MeterPoint;

    /** @return list<MeterPoint> */
    public function all(): array;

    public function find(int $id): ?MeterPoint;
}
