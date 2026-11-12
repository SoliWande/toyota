<?php

// Isolated browser harness only; never a production route or authentication mechanism.
require __DIR__.'/../../vendor/autoload.php';

use App\Models\Award;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Support\CallAuditFreezeFixtures;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.'/../../public'.$path);
$public = realpath(__DIR__.'/../../public').DIRECTORY_SEPARATOR;
if ($file && str_starts_with($file, $public) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
if (! preg_match('#^/admin/(call-audits|awards)(/|$)#D', $path) || ! preg_match('/^p09h-[a-f0-9]{20}$/D', getenv('P09L_FIXTURE_TAG') ?: '')) {
    http_response_code(404);
    exit;
}
$pidDirectory = __DIR__.'/../../storage/app/private/p09l-ui-server';
if (! is_dir($pidDirectory)) {
    mkdir($pidDirectory, 0700, true);
}
file_put_contents($pidDirectory.'/'.getenv('P09L_FIXTURE_TAG').'.pid', (string) getmypid(), LOCK_EX);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
CallAuditFreezeFixtures::assertLocal();
$user = User::whereKey(getenv('P09L_ADMIN_ID'))->where('name', getenv('P09L_FIXTURE_TAG'))->firstOrFail();
if (preg_match('#^/admin/awards/([0-9]+)#', $path, $match) && ! Award::whereKey($match[1])->where('title', getenv('P09L_FIXTURE_TAG'))->exists()) {
    http_response_code(404);
    exit;
}
Auth::setUser($user);
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
