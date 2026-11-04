<?php

use App\Models\Dealer;
use Illuminate\Contracts\Console\Kernel;

// Private presentation fixtures only. No DB access or public QA routes.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'session.driver' => 'array', 'cache.default' => 'array', 'app.url' => 'http://127.0.0.1:8771', 'app.asset_url' => 'http://127.0.0.1:8771']);
app('url')->forceRootUrl('http://127.0.0.1:8771');
$app->make('request')->setLaravelSession($app->make('session')->driver());
$dealer = new Dealer;
$dealer->forceFill(['id' => 1, 'slug' => 'qa-dealer', 'name' => 'Toyota kiểm thử', 'is_active' => true]);
$directory = base_path('.laravel-tools/lookup-sticky-footer');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$states = ['default' => null, 'validation' => null, 'D07' => 'supplement', 'D08' => 'supplemented', 'D10' => 'not_found', 'D11' => 'draft', 'D12' => 'not_requested', 'D13' => 'requested', 'D14' => 'member', 'D15' => 'blocked', 'long' => 'blocked', 'found' => 'found'];
foreach ($states as $name => $kind) {
    $result = $kind === null ? null : [
        'kind' => $kind, 'masked_name' => 'N** V** A*', 'vehicle' => 'Hilux', 'group_confirmed' => true,
        'reason' => 'Thông tin cần được đối chiếu lại với đại lý.',
        'supplement_key' => 'synthetic-visual-only', 'verification_version' => str_repeat('a', 64),
    ];
    if ($name === 'long') {
        $result['reason'] = str_repeat('Thông tin cần được đối chiếu lại với đại lý trước khi xác nhận đăng ký. ', 35);
    }
    $errors = $name === 'validation' ? ['phone' => ['Vui lòng nhập số điện thoại hợp lệ.'], 'license_plate' => ['Vui lòng nhập biển số xe.'], 'lookup' => ['Vui lòng kiểm tra lại thông tin tra cứu.']] : [];
    file_put_contents($directory.'/'.$name.'.html', view('dealers.registration.lookup', [
        'dealer' => $dealer, 'values' => $name === 'default' ? [] : ['phone' => '0901 234 567', 'license_plate' => '51K-123.45'], 'errors' => $errors, 'result' => $result,
    ])->render());
}
file_put_contents($directory.'/rules.html', view('campaign.rules')->render());
echo 'PASS13 private Blade fixtures, database disabled; no runtime route or data write.'.PHP_EOL;
