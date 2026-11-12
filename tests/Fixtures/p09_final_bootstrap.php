<?php

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

require __DIR__.'/../../vendor/autoload.php';

// Existing schema only. Even legacy RefreshDatabase tests must never issue DDL/reset.
RefreshDatabaseState::$migrated = true;
Connection::resolverFor('mysql', static function ($pdo, $database, $prefix, $config) {
    if ($database !== 'toyota_testing' || ($config['host'] ?? null) !== '127.0.0.1'
        || ! empty($config['url']) || ! empty($config['unix_socket']) || ! empty($config['read']) || ! empty($config['write'])) {
        throw new RuntimeException('FINAL tests require exact isolated local configuration before PDO.');
    }
    $connection = new MySqlConnection($pdo, $database, $prefix, $config);
    $connection->beforeExecuting(static function (string $sql): void {
        if (preg_match('/^\s*(create|alter|drop|truncate|rename|set\s+foreign_key_checks)\b/i', $sql)) {
            throw new LogicException('FINAL regression checks forbid DDL/reset.');
        }
    });

    return $connection;
});
