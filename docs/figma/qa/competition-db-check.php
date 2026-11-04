<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only preflight. This script never migrates, seeds, truncates or writes data.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=toyota_testing');
putenv('DATABASE_URL=');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || ! $app->environment('testing')
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'toyota_testing'
    || config('database.connections.mysql.url')
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Refusing tests: database configuration is not explicitly isolated.');
}
$actual = DB::selectOne('SELECT DATABASE() AS name')->name;
if ($actual !== 'toyota_testing') {
    throw new RuntimeException('Refusing tests: actual database is not toyota_testing.');
}
$count = DB::selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE()')->total;
echo 'PASS isolated loopback MySQL: '.$actual.'; tables='.$count.'; config cache absent; existing TestDatabase guard reviewed.'.PHP_EOL;
