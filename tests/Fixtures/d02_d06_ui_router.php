<?php

// Local fixture server only; run with public/ as document root and the no-DDL bootstrap.
require __DIR__.'/p09_final_bootstrap.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\CallAuditFreezeFixtures;

if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.'/../../public'.$path);
if ($file && str_starts_with($file, realpath(__DIR__.'/../../public').DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
$slugs = explode(',', getenv('D02_FIXTURE_SLUGS') ?: '');
if (! in_array(explode('/', trim($path, '/'))[0], $slugs, true)) {
    http_response_code(404);
    exit;
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
CallAuditFreezeFixtures::assertLocal();
$directory = getenv('D02_FIXTURE_DIRECTORY');
if (! is_string($directory) || ! is_dir($directory)) {
    throw new RuntimeException('Missing isolated fixture directory.');
}
file_put_contents($directory.'/server.pid', (string) getmypid());
config(['session.driver' => 'file', 'session.files' => $directory.'/sessions', 'session.secure' => false,
    'session.domain' => null, 'session.cookie' => 'd02_d06_fixture', 'cache.default' => 'file',
    'cache.stores.file.path' => $directory.'/cache', 'app.debug' => false]);
DB::connection()->beforeExecuting(static function (string $sql): void {
    if (preg_match('/^\s*(create|alter|drop|truncate|rename|set\s+foreign_key_checks)\b/i', $sql)) {
        throw new LogicException('UI fixtures forbid DDL/reset.');
    }
});
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
