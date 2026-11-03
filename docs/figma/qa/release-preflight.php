<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Isolated production boot/route-cache check. Never connect to an application database.
$root = dirname(__DIR__, 3);
$relativeDirectory = 'storage/framework/testing/release-preflight-'.bin2hex(random_bytes(8));
$directory = $root.'/'.$relativeDirectory;
mkdir($directory, 0770, true);
foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'SESSION_SECURE_COOKIE' => 'false',
    'APP_URL' => 'https://toyota.example.invalid', 'DEALER_PUBLIC_BASE_URL' => 'https://toyota.example.invalid',
    'DB_CONNECTION' => 'disabled-for-release-qa', 'DATABASE_URL' => '',
    'APP_CONFIG_CACHE' => $relativeDirectory.'/config.php', 'APP_ROUTES_CACHE' => $relativeDirectory.'/routes.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$check = function (bool $condition, string $label): void {
    if (! $condition) {
        throw new RuntimeException($label);
    }
    echo 'PASS '.$label.PHP_EOL;
};
try {
    $check(app()->environment('production') && config('app.debug') === false, 'Production boot forces debug off');
    $check(config('session.secure') === true && config('session.http_only') === true, 'Production cookies secure and HTTP-only');
    $check(config('database.default') === 'disabled-for-release-qa', 'No application database connection');
    $check(! Route::has('dealer-registration.preview'), 'Production has no preview route');
    $check(Artisan::call('route:cache') === 0, 'Production route cache compiles');
    $check(! str_contains(file_get_contents($directory.'/routes.php'), 'dealer-registration.preview'), 'Compiled production routes exclude previews');
    $manifest = json_decode(file_get_contents($root.'/public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['resources/css/dealer-registration.css', 'resources/js/dealer-registration.js'] as $entry) {
        $check(isset($manifest[$entry]['file']) && is_file($root.'/public/build/'.$manifest[$entry]['file']), 'Production artifact '.$entry);
    }
    $check(rtrim(config('dealer-registration.group_url'), '/') === 'https://www.facebook.com/groups/376631239337942', 'Official Facebook Group configured');
} finally {
    foreach (['routes.php', 'config.php'] as $file) {
        if (is_file($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    rmdir($directory);
}
