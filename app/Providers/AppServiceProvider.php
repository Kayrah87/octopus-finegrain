<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Auth\DashboardGate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DashboardGate::class, fn (): DashboardGate => new DashboardGate(
            self::nullableString(config('dashboard.username')),
            self::nullableString(config('dashboard.password')),
        ));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA journal_mode = WAL;');
            DB::statement('PRAGMA busy_timeout = 5000;');
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
