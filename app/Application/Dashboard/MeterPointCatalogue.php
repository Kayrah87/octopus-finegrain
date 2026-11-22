<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Metering\FlowDirection;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\MeterPoint;
use App\Domain\Metering\MeterPointRepository;
use InvalidArgumentException;

/**
 * Lists meter points for pickers and resolves a requested one (or a sensible default).
 */
final readonly class MeterPointCatalogue
{
    public function __construct(private MeterPointRepository $meterPoints) {}

    /** @return list<array{id: int, label: string, fuel: string, direction: string, mpxn: string}> */
    public function options(): array
    {
        return array_map(static fn (MeterPoint $mp): array => [
            'id' => (int) $mp->id,
            'label' => $mp->label(),
            'fuel' => $mp->fuel->value,
            'direction' => $mp->direction->value,
            'mpxn' => $mp->mpxn,
        ], $this->sorted());
    }

    /** @return list<MeterPoint> Electricity import first, then gas, then export. */
    public function sorted(): array
    {
        $all = $this->meterPoints->all();
        usort($all, static fn (MeterPoint $a, MeterPoint $b): int => self::rank($a) <=> self::rank($b));

        return $all;
    }

    public function resolve(?int $id): ?MeterPoint
    {
        if ($id === null) {
            return $this->sorted()[0] ?? null;
        }

        return $this->meterPoints->find($id)
            ?? throw new InvalidArgumentException("Unknown meter point [{$id}].");
    }

    private static function rank(MeterPoint $mp): int
    {
        return match (true) {
            $mp->fuel === Fuel::Electricity && $mp->direction === FlowDirection::Import => 0,
            $mp->fuel === Fuel::Gas => 1,
            default => 2,
        };
    }
}
