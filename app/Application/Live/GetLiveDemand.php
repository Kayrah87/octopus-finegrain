<?php

declare(strict_types=1);

namespace App\Application\Live;

use App\Application\Data\TelemetryPoint;
use App\Application\Ports\LiveTelemetryGateway;
use App\Domain\Shared\DateRange;
use Carbon\CarbonImmutable;

/**
 * Recent Home Mini telemetry. Returns enabled=false when not configured.
 */
final readonly class GetLiveDemand
{
    private const WINDOW_MINUTES = 15;

    public function __construct(private LiveTelemetryGateway $gateway) {}

    /** @return array{enabled: bool, latest: TelemetryPoint|null, points: list<TelemetryPoint>} */
    public function handle(): array
    {
        if (! $this->gateway->isConfigured()) {
            return ['enabled' => false, 'latest' => null, 'points' => []];
        }

        $now = CarbonImmutable::now('UTC');
        $points = $this->gateway->telemetry(new DateRange($now->subMinutes(self::WINDOW_MINUTES), $now));

        return [
            'enabled' => true,
            'latest' => $points === [] ? null : $points[array_key_last($points)],
            'points' => $points,
        ];
    }
}
