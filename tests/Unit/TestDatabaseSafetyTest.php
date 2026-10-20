<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_application_database_cannot_be_used_by_tests(): void
    {
        config(['database.connections.mysql.database' => 'toyota']);
        DB::purge('mysql');

        $this->expectException(LogicException::class);
        TestDatabase::assertIsolated();
    }

    public function test_production_environment_cannot_run_database_tests(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(LogicException::class);
        TestDatabase::assertIsolated();
    }

    public function test_another_connection_cannot_bypass_the_database_guard(): void
    {
        config(['database.default' => 'sqlite']);

        $this->expectException(LogicException::class);
        TestDatabase::assertIsolated();
    }
}
