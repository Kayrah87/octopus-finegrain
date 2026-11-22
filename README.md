# octopus-finegrain

A local-only, read-only dashboard for your own Octopus Energy account. Drill into
historic and current electricity and gas usage and costs, and compare them across days,
weeks and months.

> **Status:** scaffolding. The architecture is being agreed before any code is written.

## Configuration

All configuration is through environment variables. See [`.env.example`](.env.example).

| Variable | Required | Purpose |
|---|---|---|
| `OCTOPUS_API_KEY` | yes | Read-only access to the Octopus REST API |
| `OCTOPUS_ACCOUNT_NUMBER` | yes | Your account, e.g. `A-1234ABCD` |
| `OCTOPUS_GRAPHQL_API_KEY` | no | Turns on live Home Mini data. Live features are hidden when it's unset |
| `DASHBOARD_USERNAME` / `DASHBOARD_PASSWORD` | no | When both are set, the dashboard requires login |

## Running

Coming soon: `docker compose up -d`.
