<?php

use App\Actions\PublishDealerAward;
use App\Models\Award;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('DEALER_AWARD_TEST_DATABASE');
if (! $app->environment('testing') || ! preg_match('/^toyota_dealer_integrity_test_[a-f0-9]{16}$/D', $database ?: '')) {
    throw new LogicException('Award race fixture requires an isolated restored database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
[$script, $adminId, $awardId, $hash, $barrier] = $argv;
if (realpath(dirname($barrier)) !== realpath(base_path('.laravel-tools'))
    || ! preg_match('/^award-publication-race-[a-f0-9]{16}$/D', basename($barrier))) {
    throw new LogicException('Invalid award race barrier.');
}
touch($barrier.'/'.$adminId.'.ready');
$deadline = microtime(true) + 10;
while (count(glob($barrier.'/*.ready')) < 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Award race barrier timed out.');
    }
    usleep(20000);
}
try {
    $app->make(PublishDealerAward::class)->execute(User::findOrFail($adminId), Award::findOrFail($awardId), $hash);
    echo 'published';
} catch (ValidationException $error) {
    if (! isset($error->errors()['award']) || Award::findOrFail($awardId)->published_at === null) {
        throw $error;
    }
    echo 'already-published';
}
