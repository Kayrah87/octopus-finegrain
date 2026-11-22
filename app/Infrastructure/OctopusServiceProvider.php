<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Ports\ConsumptionGateway;
use App\Application\Ports\LiveTelemetryGateway;
use App\Application\Ports\OctopusAccountGateway;
use App\Application\Ports\SyncLog;
use App\Application\Ports\TariffGateway;
use App\Application\Ports\UsageReadModel;
use App\Domain\Metering\GasVolumeConverter;
use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\MeterPointRepository;
use App\Domain\Metering\ReadingNormaliser;
use App\Domain\Pricing\StandingChargeCalculator;
use App\Domain\Pricing\StandingChargeRepository;
use App\Domain\Shared\LocalCalendar;
use App\Domain\Tariffs\Economy7Window;
use App\Domain\Tariffs\TariffRateRepository;
use App\Infrastructure\Octopus\KrakenGraphqlClient;
use App\Infrastructure\Octopus\KrakenTelemetryGateway;
use App\Infrastructure\Octopus\OctopusRestClient;
use App\Infrastructure\Octopus\RestAccountGateway;
use App\Infrastructure\Octopus\RestConsumptionGateway;
use App\Infrastructure\Octopus\RestTariffGateway;
use App\Infrastructure\Persistence\Queries\SqlUsageReadModel;
use App\Infrastructure\Persistence\Repositories\DatabaseIntervalRepository;
use App\Infrastructure\Persistence\Repositories\DatabaseStandingChargeRepository;
use App\Infrastructure\Persistence\Repositories\DatabaseTariffRateRepository;
use App\Infrastructure\Persistence\Repositories\EloquentMeterPointRepository;
use App\Infrastructure\Persistence\Repositories\EloquentSyncLog;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Binds domain and application ports to their infrastructure adapters.
 */
final class OctopusServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MeterPointRepository::class => EloquentMeterPointRepository::class,
        IntervalRepository::class => DatabaseIntervalRepository::class,
        TariffRateRepository::class => DatabaseTariffRateRepository::class,
        StandingChargeRepository::class => DatabaseStandingChargeRepository::class,
        SyncLog::class => EloquentSyncLog::class,
        UsageReadModel::class => SqlUsageReadModel::class,
        OctopusAccountGateway::class => RestAccountGateway::class,
        ConsumptionGateway::class => RestConsumptionGateway::class,
        TariffGateway::class => RestTariffGateway::class,
    ];

    public function register(): void
    {
        $this->app->singleton(LocalCalendar::class, fn (): LocalCalendar => new LocalCalendar(self::string('dashboard.timezone', 'Europe/London')));

        $this->app->singleton(Economy7Window::class, fn (): Economy7Window => new Economy7Window(
            self::string('octopus.e7_night_start', '00:30'),
            self::string('octopus.e7_night_end', '07:30'),
        ));

        $this->app->singleton(ReadingNormaliser::class, fn (): ReadingNormaliser => new ReadingNormaliser(
            new GasVolumeConverter(self::string('octopus.gas_calorific_value', '39.5')),
            strtolower(self::string('octopus.gas_meter_unit', 'm3')) !== 'kwh',
            self::string('dashboard.timezone', 'Europe/London'),
        ));

        $this->app->singleton(StandingChargeCalculator::class, fn (): StandingChargeCalculator => new StandingChargeCalculator(
            self::string('dashboard.timezone', 'Europe/London'),
        ));

        $this->app->singleton(OctopusRestClient::class, fn (Application $app): OctopusRestClient => new OctopusRestClient(
            $app->make(HttpFactory::class),
            self::string('octopus.base_url', 'https://api.octopus.energy/v1/'),
            self::string('octopus.api_key', ''),
            (int) config('octopus.timeout', 60),
        ));

        $this->app->singleton(RestAccountGateway::class, fn (Application $app): RestAccountGateway => new RestAccountGateway(
            $app->make(OctopusRestClient::class),
            self::string('octopus.account_number', ''),
            $app->make('log'),
        ));

        $this->app->singleton(LiveTelemetryGateway::class, function (Application $app): LiveTelemetryGateway {
            $key = self::string('octopus.graphql_api_key', '');
            $client = $key === '' ? null : new KrakenGraphqlClient(
                $app->make(HttpFactory::class),
                $app->make(Cache::class),
                self::string('octopus.graphql_url', 'https://api.octopus.energy/v1/graphql/'),
                $key,
                (int) config('octopus.timeout', 60),
            );

            return new KrakenTelemetryGateway($client, $app->make(Cache::class), self::string('octopus.account_number', ''));
        });
    }

    private static function string(string $key, string $default): string
    {
        $value = config($key);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
