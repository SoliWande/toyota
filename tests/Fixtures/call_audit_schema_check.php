<?php

// P09E: an explicit local-test-only tool; never runs down(), seed or schema reset.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;

$mode = $argv[1] ?? 'preflight';
if (! in_array($mode, ['preflight', 'prepare', 'apply', 'verify'], true)) {
    throw new RuntimeException('Unknown P09E operation.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
    'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
    'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '',
    'APP_CONFIG_CACHE' => __DIR__.'/.p09e-disabled-config-cache.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if (is_file(__DIR__.'/.p09e-disabled-config-cache.php')) {
    throw new RuntimeException('Isolated config-cache path must not exist.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection('mysql');
$config = $connection->getConfig();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! in_array($config['host'] ?? null, ['127.0.0.1', '::1'], true)
    || ($config['database'] ?? null) !== 'toyota_testing' || ! empty($config['url'])
    || ! empty($config['unix_socket']) || ! empty($config['read']) || ! empty($config['write'])) {
    echo json_encode(['rejected_before_PDO' => ['environment' => $app->environment(), 'driver' => config('database.default'), 'host' => $config['host'] ?? null, 'database' => $config['database'] ?? null, 'has_url' => ! empty($config['url']), 'has_socket' => ! empty($config['unix_socket']), 'has_read_write' => ! empty($config['read']) || ! empty($config['write']), 'config_cached' => $app->configurationIsCached()]], JSON_PRETTY_PRINT).PHP_EOL;
    throw new RuntimeException('P09E rejected effective configuration before opening PDO.');
}
TestDatabase::assertIsolated();
$identity = (array) $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@hostname AS hostname, @@port AS port, @@default_storage_engine AS default_engine, @@foreign_key_checks AS fk_checks');
$transport = $connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
if ($identity['db'] !== 'toyota_testing' || (int) $identity['port'] !== (int) $config['port']
    || (int) $identity['fk_checks'] !== 1 || ! str_contains($transport, '127.0.0.1')) {
    throw new RuntimeException('P09E rejected actual database/server/transport.');
}
$tables = ['call_audit_batches', 'call_audit_items', 'call_audit_attempts', 'call_audit_events'];
$paths = [];
foreach (array_combine(['000101', '000102', '000103', '000104'], $tables) as $sequence => $table) {
    $paths[] = database_path('migrations/2026_11_11_'.$sequence.'_create_'.$table.'_table.php');
}
$directory = storage_path('app/private/p09e-call-audit-schema');
$status = function () use ($connection, $tables): array {
    $result = [];
    foreach ($tables as $table) {
        $meta = $connection->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
        $result[$table] = ['exists' => $meta !== null, 'engine' => $meta?->ENGINE,
            'rows' => $meta ? $connection->table($table)->count() : null];
    }

    return $result;
};
$report = ['effective' => ['host' => $config['host'], 'port' => $config['port'], 'database' => $config['database']],
    'identity' => $identity, 'transport' => $transport, 'tables' => $status()];
if ($mode === 'preflight') {
    $report['parent_engines'] = $connection->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','dealers','customer_submissions','awards','migrations')");
    $report['test_references'] = [
        'active_admins' => $connection->table('users')->where('role', 'admin')->where('status', 'active')->count(),
        'submissions_with_dealer' => $connection->table('customer_submissions')->whereNotNull('dealer_id')->count(),
        'awards' => $connection->table('awards')->count(),
    ];
    echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

if ($mode === 'prepare') {
    foreach ($report['tables'] as $table => $state) {
        if ($state['exists']) {
            throw new RuntimeException('Existing audit table requires review; no automatic DDL: '.$table);
        }
    }
    if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
        throw new RuntimeException('Cannot create private schema backup directory.');
    }
    $snapshot = ['identity' => $identity, 'schema' => [], 'legacy' => [], 'migration_hashes' => []];
    $sql = '-- P09E toyota_testing schema only; never auto-restored.'.PHP_EOL;
    foreach ($connection->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
        $table = array_values((array) $row)[0];
        if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected table identifier.');
        }
        $create = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
        $ddl = array_values($create)[1];
        $snapshot['schema'][$table] = hash('sha256', $ddl);
        $sql .= $ddl.';'.PHP_EOL.PHP_EOL;
        if (! in_array($table, $tables, true) && $table !== 'migrations') {
            $snapshot['legacy'][$table] = ['count' => $connection->table($table)->count(),
                'checksum' => ((array) $connection->selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'))['Checksum']];
        }
    }
    foreach ($paths as $path) {
        $snapshot['migration_hashes'][basename($path)] = hash_file('sha256', $path);
    }
    // Pretend logs generated SQL; it does not execute any DDL or create a repository.
    $queries = $connection->pretend(function () use ($paths) {
        foreach ($paths as $path) {
            (require $path)->up();
        }
    });
    $drySql = implode(';'.PHP_EOL, array_column($queries, 'query')).';'.PHP_EOL;
    foreach ($queries as $query) {
        if (! preg_match('/^(create table|alter table) [`"]?(call_audit_batches|call_audit_items|call_audit_attempts|call_audit_events)[`"\s(]/i', $query['query'])) {
            throw new RuntimeException('Dry-run SQL escaped the four audit tables.');
        }
    }
    $stamp = gmdate('Ymd-His');
    $snapshot['backup'] = $directory.'/'.$stamp.'-before-schema.sql';
    $snapshot['dry_sql'] = $directory.'/'.$stamp.'-a1-dry-run.sql';
    file_put_contents($snapshot['backup'], $sql, LOCK_EX);
    file_put_contents($snapshot['dry_sql'], $drySql, LOCK_EX);
    $snapshot['backup_hash'] = hash_file('sha256', $snapshot['backup']);
    file_put_contents($directory.'/preflight.json', json_encode($snapshot, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['backup' => $snapshot['backup'], 'backup_hash' => $snapshot['backup_hash'],
        'dry_sql' => $snapshot['dry_sql'], 'statements' => count($queries), 'legacy_tables' => count($snapshot['legacy'])], JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

$snapshot = json_decode(file_get_contents($directory.'/preflight.json'), true, flags: JSON_THROW_ON_ERROR);
if ($mode === 'apply') {
    if (! is_file($snapshot['backup']) || hash_file('sha256', $snapshot['backup']) !== $snapshot['backup_hash']) {
        throw new RuntimeException('Missing or changed schema backup.');
    }
    foreach ($status() as $table => $state) {
        if ($state['exists']) {
            throw new RuntimeException('Stopped: table already exists, review required: '.$table);
        }
    }
    foreach ($paths as $path) {
        if ($snapshot['migration_hashes'][basename($path)] !== hash_file('sha256', $path)) {
            throw new RuntimeException('Migration changed after SQL review.');
        }
    }
    // Existing migration repository only; never install/alter it or run unrelated paths.
    try {
        $migrator = $app->make('migrator');
        $migrator->setConnection('mysql');
        $ran = $migrator->getRepository()->getRan();
        foreach ($paths as $path) {
            if (in_array(pathinfo($path, PATHINFO_FILENAME), $ran, true)) {
                throw new RuntimeException('Migration repository/table state conflict; review required.');
            }
        }
        foreach ($paths as $path) {
            $migrator->run([$path]);
            echo json_encode(['applied' => basename($path)]).PHP_EOL;
        }
    } catch (Throwable $error) {
        echo json_encode(['DDL_STOPPED' => get_class($error), 'code' => $error->getCode(), 'database_error' => $error instanceof QueryException ? $error->errorInfo : null, 'tables' => $status()], JSON_PRETTY_PRINT).PHP_EOL;
        // Do not invoke down(), drop, rollback or proceed to another migration.
        exit(1);
    }
}

$changed = [];
foreach ($snapshot['legacy'] as $table => $before) {
    $after = ['count' => $connection->table($table)->count(),
        'checksum' => ((array) $connection->selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'))['Checksum']];
    $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
    if ($after !== $before || hash('sha256', $ddl) !== $snapshot['schema'][$table]) {
        $changed[] = $table;
    }
}
$schemaChanged = [];
foreach ($snapshot['schema'] as $table => $beforeHash) {
    $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
    if ($table === 'migrations') {
        // Repository inserts advance AUTO_INCREMENT; that is bookkeeping, not schema DDL.
        $backupSql = file_get_contents($snapshot['backup']);
        if (! preg_match('/(CREATE TABLE `migrations`.*?);(?:\r?\n){2}/s', $backupSql, $match)) {
            throw new RuntimeException('Cannot verify original migration repository schema.');
        }
        $normalizeCounter = fn ($sql) => preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $sql);
        if ($normalizeCounter($match[1]) !== $normalizeCounter($ddl)) {
            $schemaChanged[] = $table;
        }

        continue;
    }
    if (hash('sha256', $ddl) !== $beforeHash) {
        $schemaChanged[] = $table;
    }
}
$verification = ['tables' => $status(), 'legacy_changed' => $changed, 'old_schema_changed' => $schemaChanged,
    'a1_migration_records' => $connection->table('migrations')->whereIn('migration', array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), $paths))->orderBy('migration')->get(['migration', 'batch'])->all(),
    'constraints' => $connection->select("SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('call_audit_batches','call_audit_items','call_audit_attempts','call_audit_events') ORDER BY TABLE_NAME, CONSTRAINT_NAME")];
file_put_contents($directory.'/verification.json', json_encode($verification, JSON_PRETTY_PRINT), LOCK_EX);
echo json_encode($verification, JSON_PRETTY_PRINT).PHP_EOL;
if ($changed !== [] || $schemaChanged !== []) {
    exit(1);
}
