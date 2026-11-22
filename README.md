# octopus-finegrain

A local-only, read-only dashboard for your own Octopus Energy account. It keeps a private copy of
every half-hourly reading Octopus has for you and prices each one against the tariff you were on at
the time, so you can see exactly where your money went:

- **Overview**: latest day, week to date and month to date against the period before, the last 14
  days, your current tariff and rates, and live demand if you have a Home Mini.
- **Explore**: any date range from half-hours to months. Click a bar to drill down
  (month → day → half-hour), switch between £ and kWh, and see a time-of-day heatmap.
- **Compare**: put up to four days, weeks or months side by side. Partial periods are compared on
  their daily average.
- **Tariffs**: your agreement history and a chart of unit rates, including Agile prices published
  for the day ahead.

Electricity import, export and gas are all supported. Gas readings in m³ are converted to kWh.

## Run it

```sh
cp .env.example .env        # then fill in OCTOPUS_API_KEY and OCTOPUS_ACCOUNT_NUMBER
docker compose up -d --build
open http://localhost:8080
```

On first start the container syncs straight away and backfills all your history, which can take a
few minutes. After that it syncs every `SYNC_INTERVAL_MINUTES`, and you can press **Sync now** at
any time. Data lives in the `finegrain-data` volume. Octopus usually publishes readings a day late,
so "latest day" normally means yesterday.

## Configuration

| Variable | Required | Default | Purpose |
|---|---|---|---|
| `OCTOPUS_API_KEY` | yes | | Your API key from the [Octopus developer dashboard](https://octopus.energy/dashboard/new/accounts/personal-details/api-access) |
| `OCTOPUS_ACCOUNT_NUMBER` | yes | | e.g. `A-1234ABCD` |
| `OCTOPUS_GRAPHQL_API_KEY` | no | | Turns on live Home Mini demand. Usually the same value as `OCTOPUS_API_KEY`. Leave empty to turn live data off |
| `DASHBOARD_USERNAME`, `DASHBOARD_PASSWORD` | no | | When **both** are set, a login page protects the dashboard. Otherwise it is open |
| `DASHBOARD_PORT` | no | `8080` | Host port |
| `SYNC_INTERVAL_MINUTES` | no | `60` | Minimum 5 |
| `DASHBOARD_TIMEZONE` | no | `Europe/London` | Used for days, weeks (Monday start) and months |
| `OCTOPUS_GAS_METER_UNIT` | no | `m3` | `m3` for SMETS2 meters, `kwh` for SMETS1 |
| `OCTOPUS_GAS_CALORIFIC_VALUE` | no | `39.5` | Used in the m³ → kWh conversion |
| `OCTOPUS_E7_NIGHT_START` / `_END` | no | `00:30` / `07:30` | Off-peak window (UTC) for two-rate tariffs |

The dashboard only ever reads from Octopus. The REST client sends GET requests only, and the GraphQL
client refuses every mutation except `obtainKrakenToken`, which it needs to log in.

## Architecture

The app is Laravel 13, Inertia 3, Vue 3, Tailwind 4 and ECharts, on SQLite, all in a single
FrankenPHP container. Supervisord runs the web server, the scheduler and a queue worker.

The PHP code follows a clean/onion layout, and Pest architecture tests enforce it:

```
app/
├── Domain/          Pure PHP: tariffs, rate schedules, pricing, gas conversion, comparisons, repository ports
├── Application/     Use cases (Sync*, GetOverview, ExploreUsage, ComparePeriods, …) and gateway/read-model ports
├── Infrastructure/  Octopus REST + Kraken GraphQL adapters, Eloquent/query-builder repositories, SQL read model
└── Http, Console/   Thin controllers, form requests, the optional login gate, the octopus:sync command
```

Each half-hour is priced once, at sync time, and stored as integer watt-hours and micropence. That
keeps every sum exact (there is no floating-point money anywhere), and pages only ever read the
local database.

See [`docs/design.md`](docs/design.md) for the full design.

## Development

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # seeds ~13 months of realistic demo data, no API key needed
composer dev                   # app + vite
composer check                 # pint, larastan (level 8), pest
npm run typecheck
php artisan octopus:sync       # sync from the real API (needs the OCTOPUS_* vars)
```

Laravel Boost is installed as a dev dependency, which gives AI assistants guidelines, skills and an
MCP server. It isn't included in the production image.
