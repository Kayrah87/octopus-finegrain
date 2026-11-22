<?php

declare(strict_types=1);

return [
    'api_key' => env('OCTOPUS_API_KEY'),
    'account_number' => env('OCTOPUS_ACCOUNT_NUMBER'),
    'graphql_api_key' => env('OCTOPUS_GRAPHQL_API_KEY'),

    'base_url' => env('OCTOPUS_BASE_URL', 'https://api.octopus.energy/v1/'),
    'graphql_url' => env('OCTOPUS_GRAPHQL_URL', 'https://api.octopus.energy/v1/graphql/'),
    'timeout' => (int) env('OCTOPUS_TIMEOUT', 60),

    // SMETS2 gas meters report m³; SMETS1 report kWh.
    'gas_meter_unit' => env('OCTOPUS_GAS_METER_UNIT', 'm3'),
    'gas_calorific_value' => (string) env('OCTOPUS_GAS_CALORIFIC_VALUE', '39.5'),

    // Off-peak window for two-rate (Economy 7) tariffs, UTC.
    'e7_night_start' => env('OCTOPUS_E7_NIGHT_START', '00:30'),
    'e7_night_end' => env('OCTOPUS_E7_NIGHT_END', '07:30'),
];
