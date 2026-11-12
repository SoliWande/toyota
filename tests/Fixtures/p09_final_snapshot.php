<?php

// P09-FINAL read-only database/source snapshot. No DDL, fixtures or cleanup commands.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
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
$connection->beforeExecuting(static function (string $sql): void {
    if (! preg_match('/^\s*(select|show|checksum)\b/i', $sql)) {
        throw new RuntimeException('P09-FINAL snapshots only permit read-only queries.');
    }
});
$identity = (array) $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@hostname AS hostname, @@port AS port, @@foreign_key_checks AS fk_checks');
$identity['host'] = $connection->getConfig('host');
$identity['transport'] = $connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
if ($identity['db'] !== 'toyota_testing' || (int) $identity['port'] !== 3306
    || (int) $identity['fk_checks'] !== 1 || ! str_contains($identity['transport'], '127.0.0.1')) {
    throw new RuntimeException('Actual database/transport failed preflight.');
}
$snapshot = ['identity' => $identity, 'tables' => [], 'source_hashes' => []];
foreach ($connection->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
    $table = array_values((array) $row)[0];
    if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
        throw new RuntimeException('Unexpected table identifier.');
    }
    $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
    $engine = $connection->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->ENGINE;
    $snapshot['tables'][$table] = [
        'engine' => $engine, 'rows' => $connection->table($table)->count(),
        'checksum' => ((array) $connection->selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'))['Checksum'],
        'schema_hash' => hash('sha256', preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $ddl)),
    ];
}
$auditTables = ['call_audit_batches', 'call_audit_items', 'call_audit_attempts', 'call_audit_events'];
foreach (array_merge($auditTables, ['users', 'dealers', 'customer_submissions', 'awards']) as $table) {
    if (($snapshot['tables'][$table]['engine'] ?? null) !== 'InnoDB') {
        throw new RuntimeException('Missing transactional table: '.$table);
    }
}
foreach ($auditTables as $table) {
    if ($snapshot['tables'][$table]['rows'] !== 0) {
        throw new RuntimeException('Audit table is not empty; do not remove existing data.');
    }
}
$directory = storage_path('app/private/p09-final');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.'/before.json';
if ($mode === 'before') {
    if (is_file($path)) {
        throw new RuntimeException('Do not overwrite initial schema backup.');
    }
    $snapshot['ddl'] = [];
    foreach (array_keys($snapshot['tables']) as $table) {
        $snapshot['ddl'][$table] = array_values((array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
    }
    $snapshot['source_hashes'] = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            $snapshot['source_hashes'][$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT), LOCK_EX);
    textOutput(['identity' => $identity, 'tables' => count($snapshot['tables']), 'audit_rows' => [0, 0, 0, 0],
        'penalty_tables' => array_values(array_intersect(array_keys($snapshot['tables']), ['dealer_penalty_entries', 'dealer_penalty_evidence', 'dealer_penalty_events'])), 'schema_backup' => $path]);
    exit;
}
$before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$changed = [];
foreach ($before['tables'] as $table => $old) {
    if ($table !== 'migrations' && $old !== ($snapshot['tables'][$table] ?? null)) {
        $changed[] = $table;
    }
}
$penalties = [];
foreach (['dealer_penalty_entries', 'dealer_penalty_evidence', 'dealer_penalty_events'] as $table) {
    $penalties[$table] = $snapshot['tables'][$table]['rows'] ?? null;
}
$sourceChanges = [];
foreach ($before['source_hashes'] as $relative => $hash) {
    if (hash_file('sha256', base_path($relative)) !== $hash) {
        $sourceChanges[] = $relative;
    }
}
file_put_contents($directory.'/after.json', json_encode($snapshot, JSON_PRETTY_PRINT), LOCK_EX);
textOutput(['changed_business_tables' => $changed, 'penalty_rows' => $penalties, 'source_changes' => $sourceChanges,
    'migration_rows_added' => $snapshot['tables']['migrations']['rows'] - $before['tables']['migrations']['rows']]);
if ($changed || array_filter($penalties, fn ($rows) => $rows !== 0)) {
    exit(1);
}
function textOutput(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT).PHP_EOL;
}
