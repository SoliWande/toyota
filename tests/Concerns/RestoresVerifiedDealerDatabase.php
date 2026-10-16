<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Process\Process;

trait RestoresVerifiedDealerDatabase
{
    protected array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $manifestPath = getenv('DEALER_INTEGRITY_MANIFEST');
        if (! $manifestPath) {
            $this->markTestSkipped('Supply DEALER_INTEGRITY_MANIFEST for a verified database/evidence backup.');
        }
        $this->manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        config(['dealer_migration.backup_manifest' => $manifestPath]);
        $directory = dirname($manifestPath);
        $dump = $directory.'/toyota.sql';
        $this->assertSame('toyota', $this->manifest['source_database']);
        $this->assertSame($this->manifest['dump_sha256'], hash_file('sha256', $dump));
        foreach ($this->manifest['evidence'] as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', $directory.'/evidence/'.$path));
        }

        $connection = config('database.connections.mysql');
        $database = 'toyota_dealer_integrity_test_'.bin2hex(random_bytes(8));
        if (! app()->environment('testing') || ! preg_match('/^toyota_dealer_integrity_test_[a-f0-9]{16}$/D', $database)) {
            throw new LogicException('Integrity tests require an isolated newly created schema.');
        }
        // Server-only connection creates a new schema; it never selects toyota/toyota_testing.
        config(['database.connections.dealer_integrity_server' => array_merge($connection, ['database' => null, 'url' => null])]);
        DB::connection('dealer_integrity_server')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $input = fopen($dump, 'rb');
        try {
            $restore = new Process([
                rtrim(getenv('MYSQL_BIN_DIR') ?: 'D:/OSPanel/modules/database/MySQL-8.0-Win10/bin', '/\\').'/mysql.exe',
                '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'],
                '--default-character-set=utf8mb4', $database,
            ], base_path(), ['MYSQL_PWD' => $connection['password']], $input, 120);
            $restore->run();
            $this->assertTrue($restore->isSuccessful(), 'Verified backup restore failed.');
        } finally {
            fclose($input);
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS name')->name);

        $tables = $this->manifest['tables'];
        unset($tables['_trigger_definitions']);
        foreach ($tables as $table => $expected) {
            $rows = DB::connection()->getPdo()->query('SELECT * FROM `'.$table.'`')->fetchAll(\PDO::FETCH_ASSOC);
            $hashes = array_map(fn ($row) => hash('sha256', serialize($row)), $rows);
            sort($hashes);
            $this->assertSame($expected['rows'], count($rows));
            $this->assertSame($expected['sha256'], hash('sha256', implode('', $hashes)), 'Restored table differs: '.$table);
        }
    }
}
