<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use App\Application\Data\TelemetryPoint;
use App\Application\Ports\LiveTelemetryGateway;
use App\Domain\Shared\DateRange;
use Illuminate\Contracts\Cache\Repository as Cache;

final readonly class KrakenTelemetryGateway implements LiveTelemetryGateway
{
    private const DEVICE_QUERY = <<<'GQL'
        query Devices($account: String!) {
          account(accountNumber: $account) {
            electricityAgreements(active: true) {
              meterPoint { meters(includeInactive: false) { smartDevices { deviceId } } }
            }
          }
        }
        GQL;

    private const TELEMETRY_QUERY = <<<'GQL'
        query Telemetry($deviceId: String!, $start: DateTime!, $end: DateTime!) {
          smartMeterTelemetry(deviceId: $deviceId, grouping: TEN_SECONDS, start: $start, end: $end) {
            readAt demand consumptionDelta
          }
        }
        GQL;

    public function __construct(
        private ?KrakenGraphqlClient $client,
        private Cache $cache,
        private string $accountNumber,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    public function telemetry(DateRange $range): array
    {
        $deviceId = $this->deviceId();

        if ($this->client === null || $deviceId === null) {
            return [];
        }

        $data = $this->client->query(self::TELEMETRY_QUERY, [
            'deviceId' => $deviceId,
            'start' => $range->from->utc()->toIso8601ZuluString(),
            'end' => $range->to->utc()->toIso8601ZuluString(),
        ]);

        return array_values(array_map(static fn (array $row): TelemetryPoint => new TelemetryPoint(
            (string) $row['readAt'],
            isset($row['demand']) ? (float) $row['demand'] : null,
            isset($row['consumptionDelta']) ? (float) $row['consumptionDelta'] : null,
        ), $data['smartMeterTelemetry'] ?? []));
    }

    private function deviceId(): ?string
    {
        $client = $this->client;

        if ($client === null) {
            return null;
        }

        /** @var string|null */
        return $this->cache->remember('octopus:home-mini-device', 86400, function () use ($client): ?string {
            $data = $client->query(self::DEVICE_QUERY, ['account' => $this->accountNumber]);

            foreach ($data['account']['electricityAgreements'] ?? [] as $agreement) {
                foreach ($agreement['meterPoint']['meters'] ?? [] as $meter) {
                    foreach ($meter['smartDevices'] ?? [] as $device) {
                        if (! empty($device['deviceId'])) {
                            return (string) $device['deviceId'];
                        }
                    }
                }
            }

            return null;
        });
    }
}
