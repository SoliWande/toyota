<?php

return [
    'demo_password' => env('DEV_DEMO_PASSWORD'),
    'admin' => [
        'name' => env('DEV_ADMIN_NAME', 'Development Admin'),
        'email' => env('DEV_ADMIN_EMAIL'),
        'password' => env('DEV_ADMIN_PASSWORD'),
    ],
];
