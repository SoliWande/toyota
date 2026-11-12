<?php

// P09-FINAL read-only database/source snapshot. No DDL, fixtures or cleanup commands.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestDatabase;

$mode = $argv[1] ?? 'review';
if (! in_array($mode, ['review', 'apply'], true)) {
    throw new RuntimeException('Expected review or apply.');
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
$directory = storage_path('app/private/p09-final');
if (! is_file($directory.'/before.json')) {
    throw new RuntimeException('Initial schema backup required.');
}
$targets = ['dealer_penalty_entries', 'dealer_penalty_evidence', 'dealer_penalty_events'];
$files = [
    '2026_11_12_000201_create_dealer_penalty_entries_table.php',
    '2026_11_12_000202_create_dealer_penalty_evidence_table.php',
    '2026_11_12_000203_create_dealer_penalty_events_table.php',
];
$identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@foreign_key_checks AS fk, VERSION() AS version');
if ($identity->db !== 'toyota_testing' || (int) $identity->port !== 3306 || (int) $identity->fk !== 1
 || ! version_compare($identity->version, '8.0.16', '>=') || ! str_contains($connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS), '127.0.0.1')) {
    throw new RuntimeException('Actual test preflight failed.');
}
foreach ($targets as $table) {
    if (Schema::hasTable($table)) {
        throw new RuntimeException('DDL target exists; stop and review, never drop.');
    }
}
$migrations = array_map(fn ($file) => require base_path('database/migrations/'.$file), $files);
$sql = [];
foreach ($migrations as $migration) {
    foreach ($connection->pretend(fn () => $migration->up()) as $query) {
        if (! preg_match('/^(create|alter) table `?(dealer_penalty_entries|dealer_penalty_evidence|dealer_penalty_events)`?\b/i', $query['query'])) {
            throw new RuntimeException('Unexpected DDL.');
        }
        $sql[] = $query['query'];
    }
}
file_put_contents($directory.'/ddl-reviewed.sql', implode(";\n", $sql).";\n", LOCK_EX);
echo 'SQL reviewed: '.count($sql).' statements; three targets only.'.PHP_EOL;
if (($argv[1] ?? '') !== 'apply') {
    exit;
}
$connection->beforeExecuting(static function (string $sql): void {
    if (! preg_match('/^\s*(select|show|checksum)\b/i', $sql)
     && ! preg_match('/^(create|alter) table `?(dealer_penalty_entries|dealer_penalty_evidence|dealer_penalty_events)`?\b/i', $sql)
     && ! preg_match('/^insert into `migrations` /i', $sql)) {
        throw new RuntimeException('Forbidden operation in targeted DDL runner.');
    }
});
$repository = app('migration.repository');
$batch = $repository->getNextBatchNumber();
try {
    foreach ($migrations as $i => $migration) {
        $migration->up();
        $repository->log(pathinfo($files[$i], PATHINFO_FILENAME), $batch);
        echo 'Applied '.$files[$i].PHP_EOL;
    }
} catch (Throwable $error) {
    $state = [];
    foreach ($targets as $table) {
        $state[$table] = Schema::hasTable($table);
    }
    echo json_encode(['ddl_error_type' => get_class($error), 'state' => $state, 'instruction' => 'STOP; no automatic drop/rollback']).PHP_EOL;
    throw $error;
}
