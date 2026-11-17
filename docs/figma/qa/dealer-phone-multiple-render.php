<?php

use App\Models\Dealer;
use App\Services\DealerCallPhone;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ViewErrorBag;

// Synthetic, database-disabled view fixtures only. Never exposed through application routes.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-phone-views');
putenv('APP_CONFIG_CACHE='.__DIR__.'/nonexistent-phone-config.php');
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-phone-views', 'cache.default' => 'array', 'session.driver' => 'array',
    'dealer-registration.manager_contacts' => [], 'dealer-registration.approved_call_phones' => []]);
view()->share('errors', new ViewErrorBag);
$app->make('request')->setLaravelSession($app->make('session')->driver());
$directory = $root.'/.laravel-tools/dealer-phone-multiple/html';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$dealer = (new Dealer)->forceFill(['id' => 999999, 'slug' => 'phone-qa-fixture', 'name' => 'Đại lý kiểm thử số điện thoại',
    'code' => 'QA', 'province' => 'Địa phương kiểm thử', 'address' => 'Địa chỉ kiểm thử của đại lý',
    'opening_hours' => '08:00 – 17:00', 'is_active' => true]);
$dealer->exists = true;
foreach (['one' => '02583891999', 'two' => '02583891999, 0912909032', 'none' => null, 'invalid' => 'invalid;123'] as $variant => $raw) {
    $dealer->phone = $raw;
    $common = ['dealer' => $dealer, 'preview' => false, 'standing' => null];
    $screens = [
        'landing' => ['dealers.show', []],
        'd02' => ['dealers.registration.step-two', []],
        'd03' => ['dealers.registration.step-two', ['validationErrors' => ['license_plate' => ['Biển số không hợp lệ.']]]],
        'success' => ['dealers.registration.success', []],
        'd05' => ['dealers.show', ['duplicatePhone' => true, 'stepOneErrors' => ['phone' => ['Số điện thoại đã đăng ký.']]]],
        'detail' => ['dealers.competition', ['phones' => app(DealerCallPhone::class)->allForDealer($dealer), 'photoUrl' => null,
            'months' => [['period' => '2026-09', 'label' => 'Tháng 9', 'score' => 0], ['period' => '2026-10', 'label' => 'Tháng 10', 'score' => 0], ['period' => '2026-11', 'label' => 'Tháng 11', 'score' => 0]]]],
    ];
    foreach ($screens as $screen => [$view, $data]) {
        file_put_contents($directory.'/'.$screen.'-'.$variant.'.html', view($view, [...$common, ...$data])->render());
    }
}
$dealer->is_active = false;
$dealer->phone = '02583891999, 0912909032';
file_put_contents($directory.'/d06.html', view('dealers.show', ['dealer' => $dealer, 'preview' => true, 'standing' => null])->render());
echo "PASS: 25 isolated Blade fixtures, real Vite assets, database driver disabled.\n";
