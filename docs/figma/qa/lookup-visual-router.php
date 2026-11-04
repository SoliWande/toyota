<?php

// Loopback-only presentation fixtures. No public route, database, or business-state changes.
use App\Models\Dealer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;

if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root.'/public'.$path);
if ($file && str_starts_with($file, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
if ($path === '/qa.css') {
    header('Content-Type: text/css; charset=utf-8');
    echo str_replace(['@import "tailwindcss";', '../../public/'], ['', '/'], file_get_contents($root.'/resources/css/dealer-registration.css'));
    exit;
}
$states = ['D07' => 'supplement', 'D08' => 'supplemented', 'D09' => 'rules', 'D10' => 'not_found', 'D11' => 'draft', 'D12' => 'not_requested', 'D13' => 'requested', 'D14' => 'member', 'D15' => 'blocked', 'FOUND' => 'found'];
$kind = $states[trim($path, '/')] ?? null;
if (! $kind) {
    http_response_code(404);
    exit;
}
putenv('APP_ENV=testing');
putenv('ASSET_URL=http://127.0.0.1:8766');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
putenv('CACHE_DRIVER=array');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'session.driver' => 'array', 'cache.default' => 'array', 'app.url' => 'http://127.0.0.1:8766', 'app.asset_url' => 'http://127.0.0.1:8766', 'app.key' => 'base64:'.base64_encode(str_repeat('q', 32))]);
$app->make(Vite::class)->useHotFile($root.'/.laravel-tools/lookup-qa-no-hot');
$app->make('request')->setLaravelSession($app->make('session')->driver());
$dealer = new Dealer;
$dealer->forceFill(['id' => 1, 'slug' => 'qa-dealer', 'name' => 'Toyota An Sương', 'phone' => '0900000000', 'is_active' => true]);
if (($_GET['phone'] ?? '') === 'none') {
    $dealer->phone = null;
}
if (($_GET['dealer'] ?? '') === 'inactive') {
    $dealer->is_active = false;
}
if (($_GET['group_url'] ?? '') === 'none') {
    config(['dealer-registration.group_url' => null]);
}
$result = ['kind' => $kind, 'masked_name' => 'N** V** A*', 'vehicle' => 'Hilux', 'group_confirmed' => true,
    'reason' => $kind === 'blocked' ? 'Biển số này đã được dùng cho một đăng ký khác.' : 'Link Facebook không mở được.',
    'supplement_key' => 'synthetic-visual-only', 'verification_version' => str_repeat('a', 64)];
if (($_GET['group'] ?? '') === 'unknown') {
    $result['group_confirmed'] = false;
}
if (($_GET['ownership'] ?? '') === 'none') {
    unset($result['supplement_key'], $result['verification_version']);
}
if (($_GET['text'] ?? '') === 'long') {
    $result['reason'] = str_repeat('Thông tin cần được đối chiếu lại với đại lý. ', 8);
}
if (($_GET['name'] ?? '') === 'long') {
    $result['masked_name'] = str_repeat('N** ', 10).'A*';
}
if (! in_array($kind, ['blocked', 'supplement'])) {
    unset($result['reason']);
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $kind === 'rules' ? view('campaign.rules')->render() : view('dealers.registration.lookup', [
    'dealer' => $dealer, 'values' => ['phone' => '0901 234 567', 'license_plate' => '51K-123.45'], 'errors' => [], 'result' => $result,
])->render();
