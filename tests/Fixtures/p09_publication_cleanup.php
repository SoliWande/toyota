<?php

// P09-FINAL read-only database/source snapshot. No DDL, fixtures or cleanup commands.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\Support\TestDatabase;

$mode = $argv[1] ?? 'before';
if (! in_array($mode, ['before', 'after'], true)) {
    throw new RuntimeException('Expected before or after.');
}
$cachePath = __DIR__.'/.p09-final-disabled-config-cache.php';
if (is_file($cachePath)) {
    throw new RuntimeException('Test config-cache path must not exist.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
    'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '', 'APP_CONFIG_CACHE' => $cachePath] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection('mysql');
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || $connection->getConfig('host') !== '127.0.0.1' || $connection->getConfig('database') !== 'toyota_testing'
    || $connection->getConfig('url') || $connection->getConfig('unix_socket')
    || $connection->getConfig('read') || $connection->getConfig('write')) {
    throw new RuntimeException('Rejected configuration before PDO.');
}
TestDatabase::assertIsolated();

$files = glob(storage_path('app/private/p09h-fixtures/*.json'));
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    if ($data['cleaned']) {
        continue;
    }
    if (! preg_match('/^p09h-[a-f0-9]{20}$/D', $data['tag']) || basename($file) !== $data['tag'].'.json') {
        throw new RuntimeException('Invalid journal');
    }
    foreach (DB::table('users')->whereIn('id', $data['ids']['users'] ?? [])->get() as $actor) {
        if ($actor->name !== $data['tag']) {
            throw new RuntimeException('Ownership mismatch');
        }
    }
    $reflection = new ReflectionClass(CallAuditFreezeFixtures::class);
    $fixtures = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('tag')->setValue($fixtures, $data['tag']);
    $reflection->getProperty('ids')->setValue($fixtures, $data['ids']);
    $fixtures->cleanup();
    echo 'Cleaned owned journal '.$data['tag'].PHP_EOL;
}
