<?php

return [
    // Enable only with an authenticated private cleanup retirement receipt.
    'retired' => env('TOYOTA_DEMO_RETIRED', false),
    'enabled' => env('TOYOTA_DEMO_ENABLED', false),
    'allow_production' => env('TOYOTA_DEMO_ALLOW_PRODUCTION', false),
    'password' => env('TOYOTA_DEMO_PASSWORD'),
    // Opt in only on an isolated demo installation; real metrics exclude verified demo IDs.
    'include_in_metrics' => env('TOYOTA_DEMO_INCLUDE_IN_METRICS', false),
];
