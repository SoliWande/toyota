<?php

use App\Actions\CreateDealerAccount;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('DEALER_ACCOUNT_TEST_DATABASE');
if (! $app->environment('testing') || ! preg_match('/^toyota_dealer_integrity_test_[a-f0-9]{16}$/D', $database ?: '')) {
    throw new LogicException('Account race fixture requires an isolated restored test database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
[$script, $adminId, $dealerId, $email, $barrier] = $argv;
if (realpath(dirname($barrier)) !== realpath(base_path('.laravel-tools'))
    || ! preg_match('/^dealer-account-race-[a-f0-9]{16}$/D', basename($barrier))) {
    throw new LogicException('Invalid test barrier.');
}
touch($barrier.'/'.$adminId.'.ready');
$deadline = microtime(true) + 15;
while (count(glob($barrier.'/*.ready')) < 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Account creation barrier timed out.');
    }
    usleep(20000);
}
try {
    $account = app(CreateDealerAccount::class)->execute(User::findOrFail($adminId), [
        'name' => 'Concurrent account fixture', 'email' => $email,
        'dealer_id' => $dealerId, 'password' => 'test-only-password',
    ]);
    echo 'created';
} catch (ValidationException $error) {
    if (! isset($error->errors()['dealer_id'])) {
        throw $error;
    }
    echo 'duplicate';
}
