<?php

use App\Models\Dealer;
use App\Services\DealerCallPhone;
use App\Services\RegistrationPhone;
use Illuminate\Contracts\Console\Kernel;

// Isolated view fixtures only. No database connection, migration or persisted Dealer.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
$dealer = new Dealer;
$dealer->forceFill(['slug' => 'qa-call-dealer', 'name' => 'Đại lý kiểm tra với tên dài để kiểm tra xuống dòng',
    'address' => 'Địa chỉ kiểm thử dài để kiểm tra bố cục trên màn hình nhỏ', 'opening_hours' => '08:00–17:00']);
$dealer->exists = true; // Simulate a hydrated row without touching a database.
$service = new DealerCallPhone;
$checks = 0;
$check = function (bool $ok, string $label) use (&$checks): void {
    if (! $ok) {
        throw new RuntimeException($label);
    }
    $checks++;
    echo 'PASS '.$label.PHP_EOL;
};
// Synthetic strings are confined to this database-disabled QA process, never called.
foreach (['0900000000', '02812345678', '19001234', '1800123456', '+84 (90) 000-0000', '0084 90 000 0000'] as $raw) {
    $dealer->phone = $raw;
    $expected = RegistrationPhone::normalize($raw);
    config(['dealer-registration.approved_call_phones' => [$dealer->slug => $expected]]);
    $html = view('components.dealer-registration.dealer-card', ['dealer' => $dealer])->render();
    $check($service->forDealer($dealer) === $expected && str_contains($html, 'href="tel:'.$expected.'"'), 'Approved normalized phone '.$raw);
}
foreach ([null, '', 'undefined', '+++', '1900', '123', '0900000000;ext=1', '0900000000<script>', '0900000000'."\n".'evil', '0000000000'] as $raw) {
    $dealer->phone = $raw;
    config(['dealer-registration.approved_call_phones' => [$dealer->slug => $raw]]);
    $html = view('components.dealer-registration.dealer-card', ['dealer' => $dealer])->render();
    $check(! str_contains($html, 'tel:') && ! str_contains($html, 'Gọi đại lý'), 'Invalid/missing phone hidden');
}
$dealer->phone = '0900000000';
foreach ([[], 'invalid', [$dealer->slug => '0900000001'], ['other-dealer' => $dealer->phone]] as $approval) {
    config(['dealer-registration.approved_call_phones' => $approval]);
    $check($service->forDealer($dealer) === null, 'No matching approval fails closed');
}
$dealer->slug = 'demo-phase-2a-active';
config(['dealer-registration.approved_call_phones' => ['production-dealer' => $dealer->phone]]);
$check($service->forDealer($dealer) === null, 'Demo cannot inherit production phone approval');
config(['dealer-registration.approved_call_phones' => [$dealer->slug => $dealer->phone]]);
$check($service->forDealer($dealer) === $dealer->phone, 'Demo requires explicit exact approval');
$dealer->phone = null;
$check($service->forDealer($dealer) === null, 'Demo without phone stays hidden even with approval');
$dealer->phone = '0900000000';
$dealer->exists = false;
$check($service->forDealer($dealer) === null, 'Unsaved preview cannot expose a phone');
$dealer->exists = true;
$dealer->is_active = false;
$check($service->forDealer($dealer) === null, 'Inactive Dealer hidden');
$dealer->is_active = true;
$html = '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body class="dr-site"><main style="max-width:1200px;margin:40px auto;padding:0 16px">';
foreach ([false, true] as $compact) {
    $html .= view('components.dealer-registration.dealer-card', compact('dealer', 'compact'))->render();
}
$html .= '</main></body></html>';
$dir = base_path('.laravel-tools/dealer-call');
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents($dir.'/cards.html', $html);
echo $checks.' checks passed; visual fixture generated outside public.'.PHP_EOL;
