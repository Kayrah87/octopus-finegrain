# octopus-finegrain — design

_2026-11-22_

## Intent

A private, read-only dashboard for one Octopus Energy account, run locally in a single Docker
container. It lets the owner drill into all historic and current electricity and gas usage and
cost, and compare periods (days, weeks, months) against each other.

## Goals

- Show all available history, from half-hours up to months, for every meter point (import, export, gas).
- Show cost as well as usage, priced from the actual tariff that was in force for each half-hour
  (time-of-use tariffs such as Agile and Go included), plus daily standing charges.
- Compare any two periods of the same length side by side.
- Show live demand from an Octopus Home Mini, when `OCTOPUS_GRAPHQL_API_KEY` is set.
- Ask for a login only when both `DASHBOARD_USERNAME` and `DASHBOARD_PASSWORD` are set.

## Non-goals and anti-goals

- No writes to the Octopus account. The only GraphQL mutation used is `obtainKrakenToken`.
- No multi-user support, no user table, and no hosting on the public internet.
- Never call Octopus while rendering a page. Pages read only from the local SQLite copy.

## Approaches considered

| | Approach | Trade-off |
|---|---|---|
| **A (chosen)** | Laravel 13 + Inertia 3 + Vue 3 + Tailwind 4, SQLite, single FrankenPHP container | Same stack as the owner's other projects. Larger image. |
| B | FastAPI + Vue SPA | Good for data work, but a new language and toolchain. |
| C | Sync service + Grafana | Quick to build, but hard to do tariff-aware costs and period comparisons. |

## Architecture: clean/onion layers

The code is split into layers, and dependencies only point inwards: Http/Console → Application → Domain ← Infrastructure.

| Layer | Namespace | May depend on |
|---|---|---|
| Domain | `App\Domain` | PHP, `brick/math`, `Carbon` only. No Laravel. |
| Application | `App\Application` | Domain |
| Infrastructure | `App\Infrastructure` | Domain, Application, Laravel |
| Presentation | `App\Http`, `App\Console` | Application (+ Laravel) |

Pest architecture tests enforce these rules.

**Domain** holds the core rules and has no framework code:
- Value objects: `Fuel`, `FlowDirection`, `TariffCode` (which parses the product code and the register type), `Money` (backed by BigDecimal), `Energy` (kWh), `DateRange`, `Granularity`.
- Rate lookup: `RateSchedule` finds the unit rate in force at a given instant.
- Pricing: `IntervalPricer` works out the cost of each half-hour.
- Gas: `GasVolumeConverter` turns m³ into kWh.
- Comparison: `PeriodComparison` produces deltas between periods.
- Ports (interfaces the outer layers implement): the gateway interfaces (`AccountGateway`, `ConsumptionGateway`, `TariffGateway`, `LiveTelemetryGateway`) and the repository and read-model interfaces.

**Application** holds one class per use case, each with a single `handle()` method:
- `SyncAccount`, `SyncConsumption`, `SyncTariffRates`, `PriceIntervals`, `RunFullSync`
- `GetOverview`, `GetUsageSeries`, `ComparePeriods`, `GetTariffHistory`, `GetLiveDemand`

These return plain readonly DTOs.

**Infrastructure** holds everything that talks to the outside world:
- The Octopus REST client and Kraken GraphQL client, built on the Laravel HTTP client.
- Eloquent models, repository implementations, and SQL read models.
- Binding of ports to implementations, done in a service provider.

**Presentation** is the thin outer shell:
- Inertia controllers and the `octopus:sync` console command.
- `RequireDashboardLogin` middleware and the login controller.

## Data

All data is stored in SQLite at `/data/database.sqlite`, on a volume. Times are stored in UTC.

| Table | What it holds |
|---|---|
| `meter_points` | fuel, direction, mpxn (unique) |
| `meters` | meter_point_id, serial |
| `agreements` | meter_point_id, tariff_code, product_code, valid_from, valid_to |
| `tariff_rates` | tariff_code, kind (standard/day/night/standing), value_inc_vat, valid_from, valid_to |
| `intervals` | one row per meter point per half-hour (see below) |
| `sync_runs` | start and finish times, status, message |

The `intervals` table has these columns:
- `meter_point_id` and `interval_start`, which together are unique, plus `interval_end`
- `raw_consumption` and `kwh`
- `unit_rate` and `cost` (the cost is null until the half-hour is priced)
- `local_date` and `local_slot` (Europe/London), so day, week and month queries need no time-zone maths

Money in the database is held as decimal strings with 6 dp and converted to `Money` in the domain.
Floats are never used for money.

## Sync

Sync runs on the scheduler every `SYNC_INTERVAL_MINUTES`, with overlapping runs prevented. It can
also be run with `php artisan octopus:sync` or from the "Sync now" button, which queues a job. Each run:

1. Fetches the account, then upserts meter points, meters and agreements.
2. Fetches consumption per meter point. On the first run it goes back from each agreement's start
   date; after that it starts 2 days before the latest stored half-hour, because Octopus revises
   recent data. It requests pages of 25,000 rows.
3. Fetches unit rates and standing charges for each tariff over the dates of its agreement.
4. Prices any unpriced half-hours.

## Live data

Live data is only available when `OCTOPUS_GRAPHQL_API_KEY` is set:
- A Kraken token is fetched and cached for 50 minutes.
- The Home Mini `deviceId` is found from the account's smart devices.
- `smartMeterTelemetry` provides recent demand, shown on the overview and refreshed every 30 seconds.

With no key, `liveEnabled = false` and the UI hides the live features.

## Login

`RequireDashboardLogin` passes every request through unless both credentials are set. When they
are set:
- An unauthenticated visitor is redirected to `/login`.
- Credentials are checked with `hash_equals`.
- Login attempts are rate-limited by IP.
- The session is regenerated on login, and `/logout` ends it.

## UI pages

- **Overview**: yesterday, this week and this month to date versus the previous period; live demand;
  current tariffs and rates; when the last sync ran.
- **Explore**: a fuel/meter picker, a date range and an automatic level of detail. Clicking a bar drills
  down a level (month → day → half-hour). A heatmap shows days against half-hour slots. You can
  switch between usage and cost.
- **Compare**: choose a granularity and two periods. Shows the two periods overlaid, their totals and
  the change, and the average p/kWh.
- **Tariffs**: agreement history and a chart of unit rates.

Charts use ECharts through `vue-echarts`.

## Config (env)

Required: `OCTOPUS_API_KEY`, `OCTOPUS_ACCOUNT_NUMBER`.

Optional:
- `OCTOPUS_GRAPHQL_API_KEY`
- `DASHBOARD_USERNAME`, `DASHBOARD_PASSWORD`
- `SYNC_INTERVAL_MINUTES` (60)
- `OCTOPUS_GAS_METER_UNIT` (`m3` or `kwh`, default `m3`)
- `OCTOPUS_GAS_CALORIFIC_VALUE` (39.5)
- `OCTOPUS_E7_NIGHT_START` / `OCTOPUS_E7_NIGHT_END` (00:30 and 07:30 UTC)
- `TZ`

## Container

The image is a multi-stage build:
1. A Node stage builds the frontend assets.
2. A Composer stage runs `--no-dev`.
3. The runtime stage is `dunglas/frankenphp`.

Supervisord runs FrankenPHP, `schedule:work` and `queue:work` (the queue uses the database driver).
On boot, the entrypoint creates the SQLite file, generates `APP_KEY` into `/data` if it's missing,
migrates and caches config. The compose file maps port `${DASHBOARD_PORT:-8080}` and mounts `/data`.

## Quality

- `declare(strict_types=1)` everywhere and final classes by default.
- Pint (strict preset), Larastan level 8, Pest 5 (unit, feature and arch tests).
- Laravel Boost installed for development, but not in the production image.
- The Octopus API is faked in tests with `Http::fake()`.

## Left to the builder

- Exact chart styling.
- Whether Economy 7 night windows also need per-region overrides. Start with the env window.
- How the tariff comparison ("what would tariff X have cost") is designed. It is a later feature.
