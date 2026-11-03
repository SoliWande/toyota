<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

// Local QA only: php -S 127.0.0.1:8765 -t public docs/figma/qa/router.php
// Refuse external clients and any database connection. No application data is read or written.
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicFile = realpath($root.'/public'.$path);
if ($publicFile && str_starts_with($publicFile, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($publicFile)) {
    return false;
}
if ($path === '/resources/css/dealer-registration.css') {
    header('Content-Type: text/css; charset=utf-8');
    // CSS layout QA without unavailable Vite/Tailwind dependencies; not a production build substitute.
    echo str_replace('@import "tailwindcss";', '', file_get_contents($root.$path));
    exit;
}
if (in_array($path, ['/resources/js/dealer-registration.js', '/resources/js/customer-address.js'], true)) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo str_replace("'./customer-address'", "'./customer-address.js'", file_get_contents($root.$path));
    exit;
}
if (! str_starts_with($path, '/_preview/dealer-registration')) {
    http_response_code(404);
    exit;
}
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
putenv('CACHE_DRIVER=array');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'session.driver' => 'array', 'cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('q', 32))]);
$app->instance(Vite::class, new class extends Vite
{
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return new HtmlString('<link rel="stylesheet" href="/resources/css/dealer-registration.css"><script type="module" src="/resources/js/dealer-registration.js"></script>');
    }
});
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
