<?php

// Read-only preflight. Never connect until effective configuration is isolated.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestDatabase;

$root = dirname(__DIR__, 3);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'toyota_testing',
    'DATABASE_URL' => '', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertIsolated();
if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'toyota_testing') {
    throw new RuntimeException('Actual database is not isolated.');
}
foreach (['customer_submissions', 'submission_intake_mutex', 'dealer_registration_receipts',
    'submission_verification_states', 'submission_verification_audits'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Missing test schema: '.$table.'. No migration was run.');
    }
}
echo "PASS: actual database toyota_testing; lookup/verification tables exist; no writes or migrations.\n";
