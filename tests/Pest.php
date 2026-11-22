<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Fake the Octopus REST API with one electricity import and one gas meter point,
 * a single day (5 Jan 2026, UTC) of half-hourly data, and flat tariffs.
 */
function fakeOctopus(string $electricityKwh = '0.5', string $gasM3 = '0.1'): void
{
    config([
        'octopus.api_key' => 'sk_test',
        'octopus.account_number' => 'A-TEST1234',
        'octopus.graphql_api_key' => null,
    ]);

    $readings = static function (string $value): array {
        $start = CarbonImmutable::parse('2026-01-05 00:00', 'UTC');
        $rows = [];
        for ($i = 0; $i < 48; $i++) {
            $rows[] = [
                'consumption' => (float) $value,
                'interval_start' => $start->addMinutes(30 * $i)->toIso8601String(),
                'interval_end' => $start->addMinutes(30 * ($i + 1))->toIso8601String(),
            ];
        }

        return ['count' => 48, 'next' => null, 'previous' => null, 'results' => $rows];
    };

    $flat = static fn (float $pence): array => ['count' => 1, 'next' => null, 'previous' => null, 'results' => [[
        'value_exc_vat' => $pence / 1.05, 'value_inc_vat' => $pence,
        'valid_from' => '2026-01-01T00:00:00Z', 'valid_to' => null, 'payment_method' => null,
    ]]];

    Http::preventStrayRequests();
    Http::fake([
        'api.octopus.energy/v1/accounts/A-TEST1234/' => [
            'number' => 'A-TEST1234',
            'properties' => [[
                'electricity_meter_points' => [[
                    'mpan' => '1000000000001', 'is_export' => false,
                    'meters' => [['serial_number' => 'E1']],
                    'agreements' => [['tariff_code' => 'E-1R-VAR-22-11-01-C', 'valid_from' => '2026-01-01T00:00:00Z', 'valid_to' => null]],
                ]],
                'gas_meter_points' => [[
                    'mprn' => '2000000001',
                    'meters' => [['serial_number' => 'G1']],
                    'agreements' => [['tariff_code' => 'G-1R-VAR-22-11-01-C', 'valid_from' => '2026-01-01T00:00:00Z', 'valid_to' => null]],
                ]],
            ]],
        ],
        'api.octopus.energy/v1/electricity-meter-points/*' => $readings($electricityKwh),
        'api.octopus.energy/v1/gas-meter-points/*' => $readings($gasM3),
        'api.octopus.energy/v1/products/*/electricity-tariffs/*/standard-unit-rates/*' => $flat(24.5),
        'api.octopus.energy/v1/products/*/gas-tariffs/*/standard-unit-rates/*' => $flat(6.0),
        'api.octopus.energy/v1/products/*/standing-charges/*' => $flat(50.0),
    ]);
}
