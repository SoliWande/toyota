<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait UsesMigratedDealerDatabase
{
    use RestoresVerifiedDealerDatabase { setUp as private restoreVerifiedDatabase; }

    private static ?string $database = null;

    protected function setUp(): void
    {
        if (self::$database === null) {
            $this->restoreVerifiedDatabase();
            (require database_path('migrations/2026_10_16_000015_prepare_dealer_only_database.php'))->up();
            self::$database = DB::connection()->getDatabaseName();
        } else {
            parent::setUp();
            config(['database.connections.mysql.database' => self::$database, 'database.connections.mysql.url' => null]);
            DB::purge('mysql');
        }
        DB::beginTransaction();
        Storage::fake('submission_evidence');
    }

    protected function tearDown(): void
    {
        if (self::$database !== null && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }
}
