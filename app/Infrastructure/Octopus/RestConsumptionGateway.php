<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use App\Application\Ports\ConsumptionGateway;
use App\Domain\Metering\ConsumptionReading;
use App\Domain\Metering\Fuel;
use App\Domain\Shared\DateRange;
use Carbon\CarbonImmutable;

final readonly class RestConsumptionGateway implements ConsumptionGateway
{
    private const PAGE_SIZE = 25000;

    public function __construct(private OctopusRestClient $client) {}

    public function readings(Fuel $fuel, string $mpxn, string $serial, DateRange $range): iterable
    {
        $path = sprintf('%s-meter-points/%s/meters/%s/consumption/', $fuel->value, rawurlencode($mpxn), rawurlencode($serial));

        $results = $this->client->paginate($path, [
            'period_from' => $range->from->utc()->toIso8601ZuluString(),
            'period_to' => $range->to->utc()->toIso8601ZuluString(),
            'page_size' => self::PAGE_SIZE,
            'order_by' => 'period',
        ]);

        foreach ($results as $row) {
            yield new ConsumptionReading(
                CarbonImmutable::parse((string) $row['interval_start'])->utc(),
                CarbonImmutable::parse((string) $row['interval_end'])->utc(),
                (string) $row['consumption'],
            );
        }
    }
}
