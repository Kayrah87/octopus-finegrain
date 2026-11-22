<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use App\Application\Ports\OctopusAccountGateway;
use App\Domain\Metering\Agreement;
use App\Domain\Metering\FlowDirection;
use App\Domain\Metering\Fuel;
use App\Domain\Metering\MeterPoint;
use App\Domain\Tariffs\TariffCode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class RestAccountGateway implements OctopusAccountGateway
{
    public function __construct(
        private OctopusRestClient $client,
        private string $accountNumber,
        private LoggerInterface $logger,
    ) {}

    public function meterPoints(): array
    {
        $account = $this->client->get("accounts/{$this->accountNumber}/");
        $byMpxn = [];

        foreach ($account['properties'] ?? [] as $property) {
            foreach ($property['electricity_meter_points'] ?? [] as $point) {
                $direction = ($point['is_export'] ?? false) ? FlowDirection::Export : FlowDirection::Import;
                $this->merge($byMpxn, Fuel::Electricity, $direction, (string) $point['mpan'], $point);
            }

            foreach ($property['gas_meter_points'] ?? [] as $point) {
                $this->merge($byMpxn, Fuel::Gas, FlowDirection::Import, (string) $point['mprn'], $point);
            }
        }

        return array_values($byMpxn);
    }

    /**
     * Meter points can appear under several properties (e.g. after moving); combine them.
     *
     * @param  array<string, MeterPoint>  $byMpxn
     * @param  array<string, mixed>  $point
     */
    private function merge(array &$byMpxn, Fuel $fuel, FlowDirection $direction, string $mpxn, array $point): void
    {
        $serials = array_values(array_filter(array_map(
            static fn (array $meter): string => (string) ($meter['serial_number'] ?? ''),
            $point['meters'] ?? [],
        )));

        $agreements = array_values(array_filter(array_map($this->agreement(...), $point['agreements'] ?? [])));

        $existing = $byMpxn[$mpxn] ?? null;

        $byMpxn[$mpxn] = new MeterPoint(
            null,
            $fuel,
            $direction,
            $mpxn,
            array_values(array_unique([...($existing->meterSerials ?? []), ...$serials])),
            [...($existing->agreements ?? []), ...$agreements],
        );
    }

    /** @param  array<string, mixed>  $raw */
    private function agreement(array $raw): ?Agreement
    {
        try {
            return new Agreement(
                TariffCode::parse((string) $raw['tariff_code']),
                CarbonImmutable::parse((string) $raw['valid_from'])->utc(),
                isset($raw['valid_to']) ? CarbonImmutable::parse((string) $raw['valid_to'])->utc() : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Skipping agreement with unrecognised tariff code.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
