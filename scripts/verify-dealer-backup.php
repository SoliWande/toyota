<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$source = $env['DB_DATABASE'];
if ($source !== 'toyota') {
    throw new RuntimeException('Expected the audited toyota source database.');
}
$pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stamp = gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$directory = $root.'/.laravel-tools/backups/dealer_'.$stamp;
mkdir($directory, 0700, true);
$dump = $directory.'/toyota.sql';
$bin = rtrim(getenv('MYSQL_BIN_DIR') ?: 'D:/OSPanel/modules/database/MySQL-8.0-Win10/bin', '/\\').'/';
$connection = ['--host='.$env['DB_HOST'], '--port='.$env['DB_PORT'], '--user='.$env['DB_USERNAME'], '--default-character-set=utf8mb4'];
$processEnv = ['MYSQL_PWD' => $env['DB_PASSWORD'] ?? ''];
$run = function (Process $process): void {
    $process->setTimeout(180);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Backup/restore process failed (exit '.$process->getExitCode().').');
    }
};
$fingerprint = function (string $database) use ($pdo): array {
    $pdo->exec('USE `'.$database.'`');
    $result = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $hashes = array_map(fn ($row) => hash('sha256', serialize($row)), $rows);
        sort($hashes);
        $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode('', $hashes))];
    }
    ksort($result);
    $triggers = $pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
    $result['_trigger_definitions'] = array_map(fn ($row) => [$row['Trigger'], $row['Timing'], $row['Event'], $row['Table'], $row['Statement']], $triggers);

    return $result;
};
$before = $fingerprint($source);
$run(new Process([$bin.'mysqldump.exe', ...$connection, '--single-transaction', '--skip-lock-tables', '--skip-add-drop-table', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--triggers', '--routines', '--events', '--result-file='.$dump, $source], $root, $processEnv));
if (! is_file($dump) || filesize($dump) === 0) {
    throw new RuntimeException('Empty database backup.');
}
$evidence = [];
$pdo->exec('USE `'.$source.'`');
foreach ($pdo->query('SELECT evidence_image_path FROM customer_submissions WHERE evidence_image_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $path) {
    if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, ':')) {
        throw new RuntimeException('Unsafe evidence path.');
    }
    $original = $root.'/storage/app/private/submission-evidence/'.$path;
    $copy = $directory.'/evidence/'.$path;
    if (! is_dir(dirname($copy))) {
        mkdir(dirname($copy), 0700, true);
    }
    if (! is_file($original) || ! copy($original, $copy) || hash_file('sha256', $original) !== hash_file('sha256', $copy)) {
        throw new RuntimeException('Evidence backup verification failed.');
    }
    $evidence[$path] = hash_file('sha256', $copy);
}
if ($before !== $fingerprint($source)) {
    throw new RuntimeException('Source changed while backing up; repeat during a quiet window.');
}
$restore = 'toyota_dealer_integrity_'.$stamp;
$pdo->exec('CREATE DATABASE `'.$restore.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$stream = fopen($dump, 'rb');
try {
    $run(new Process([$bin.'mysql.exe', ...$connection, $restore], $root, $processEnv, $stream));
} finally {
    fclose($stream);
}
$restored = $fingerprint($restore);
if ($restored !== $before) {
    throw new RuntimeException('Restored database does not match the source.');
}
$manifest = ['verified_at' => gmdate(DATE_ATOM), 'source_database' => $source, 'restore_database' => $restore, 'dump_sha256' => hash_file('sha256', $dump), 'tables' => $before, 'evidence' => $evidence];
file_put_contents($directory.'/verified.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
file_put_contents($root.'/.laravel-tools/dealer-integrity-backup.json', json_encode(['directory' => $directory, 'restore_database' => $restore], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['backup_directory' => $directory, 'restored_database' => $restore, 'verified_tables' => count($before) - 1, 'verified_evidence' => count($evidence), 'status' => 'verified'], JSON_PRETTY_PRINT), PHP_EOL;
