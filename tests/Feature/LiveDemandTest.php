<?php

declare(strict_types=1);

use App\Application\Ports\LiveTelemetryGateway;
use App\Infrastructure\Octopus\KrakenGraphqlClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'octopus.account_number' => 'A-TEST1234',
        'octopus.graphql_api_key' => 'sk_graphql',
    ]);
    app()->forgetInstance(LiveTelemetryGateway::class);
});

it('fetches Home Mini demand through GraphQL when a key is set', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.octopus.energy/v1/graphql/' => function ($request) {
        $query = $request->data()['query'];

        return Http::response(match (true) {
            str_contains($query, 'obtainKrakenToken') => ['data' => ['obtainKrakenToken' => ['token' => 'jwt']]],
            str_contains($query, 'smartDevices') => ['data' => ['account' => ['electricityAgreements' => [
                ['meterPoint' => ['meters' => [['smartDevices' => [['deviceId' => 'dev-1']]]]]],
            ]]]],
            default => ['data' => ['smartMeterTelemetry' => [
                ['readAt' => '2026-01-06T09:00:00+00:00', 'demand' => '412.0', 'consumptionDelta' => '1.1'],
                ['readAt' => '2026-01-06T09:00:10+00:00', 'demand' => '980.0', 'consumptionDelta' => '2.7'],
            ]]],
        });
    }]);

    $this->getJson('/live')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('latest.demandWatts', 980)
        ->assertJsonCount(2, 'points');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'jwt')
        && str_contains($request->data()['query'], 'smartMeterTelemetry'));
});

it('degrades gracefully when GraphQL fails', function (): void {
    Http::fake(['*' => Http::response(['errors' => [['message' => 'Unauthorized']]], 200)]);

    $this->getJson('/live')->assertStatus(503)->assertJsonPath('enabled', true);
});

it('refuses to send any mutation other than token auth', function (): void {
    $client = app()->make(KrakenGraphqlClient::class, [
        'url' => 'https://example.test', 'apiKey' => 'k', 'timeout' => 5,
    ]);

    $client->query('mutation { updateSomething { ok } }');
})->throws(RuntimeException::class, 'read-only');
