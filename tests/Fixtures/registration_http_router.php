<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;

// Test-only HTTP entrypoint, never a production route or public document root.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertIsolated();
if (getenv('TOYOTA_HTTP_RACE') !== '1' || DB::selectOne('SELECT DATABASE() AS n')->n !== 'toyota_testing') {
    http_response_code(403);
    exit;
}
$root = getenv('TOYOTA_HTTP_RACE_PATH');
file_put_contents($root.'/'.$_SERVER['SERVER_PORT'].'.pid', (string) getmypid());
config(['session.driver' => 'file', 'session.files' => $root.'/sessions', 'session.secure' => false,
    'session.domain' => null, 'session.cookie' => 'toyota_http_race', 'cache.default' => 'file',
    'cache.stores.file.path' => $root.'/cache', 'app.debug' => false, 'filesystems.disks.submission_evidence.root' => $root.'/evidence']);
$app->bind(VerifyCsrfToken::class, function ($app) {
    return new class($app, $app['encrypter']) extends VerifyCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
});
if (isset($_SERVER['HTTP_X_RACE_BARRIER'])) {
    touch($root.'/'.$_SERVER['SERVER_PORT'].'.ready');
    $deadline = microtime(true) + 10;
    while (count(glob($root.'/*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            http_response_code(503);
            exit;
        }
        usleep(10000);
    }
}
if (isset($_SERVER['HTTP_X_FIXTURE_DELAY'])) {
    usleep(900000);
}
$held = false;
DB::listen(function ($query) use (&$held) {
    if (! $held && str_contains($query->sql, 'submission_intake_mutex') && str_contains($query->sql, 'for update')) {
        $held = true;
        usleep(300000); // Deliberately keep the winner's actual database mutex occupied.
    }
    if (isset($_SERVER['HTTP_X_FIXTURE_FAIL']) && str_starts_with($query->sql, 'insert into `dealer_registration_receipts`')) {
        throw new RuntimeException('Synthetic failure before commit.');
    }
});
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
