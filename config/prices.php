<?php

declare(strict_types=1);

return [
    'zone' => 'ee',

    'display_timezone' => 'Europe/Tallinn',

    'tariff' => [
        'vat_rate' => 0.24,
        'grid_fee_snt_kwh' => 4.5,
        'seller_margin_snt_kwh' => 0.5,
    ],

    'window' => [
        'min_hours' => 1,
        'max_hours' => 6,
        'default_hours' => 3,
    ],

    'calendar' => [
        'past_days' => 30,
        'future_days' => 1,
    ],

    'elering' => [
        'base_url' => 'https://dashboard.elering.ee',
        'timeout_seconds' => 5,
        'retries' => 2,
        'retry_delay_ms' => 200,
    ],

    'cache' => [
        'unsettled_ttl_seconds' => 900,
        'settled_ttl_seconds' => 2592000,
        'upstream_down_ttl_seconds' => 30,
    ],

    'submission' => [
        'recipient' => 'jobs@qilowatt.eu',
    ],
];
