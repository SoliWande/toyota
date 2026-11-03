<?php

use App\Http\Controllers\DealerRegistrationPreviewController;
use App\Models\Dealer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// View/route contract checks without PHPUnit bootstrapping, migrations, or a database driver.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
putenv('CACHE_DRIVER=array');
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'session.driver' => 'array', 'cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('q', 32))]);
$app->instance(Vite::class, new class extends Vite
{
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return new HtmlString('');
    }
});
view()->share('errors', new ViewErrorBag);
$session = $app->make('session')->driver();
$app->make('request')->setLaravelSession($session);
$dealer = new Dealer;
$dealer->forceFill(['id' => 1, 'slug' => 'qa-dealer', 'name' => 'Đại lý kiểm tra', 'phone' => '1900', 'address' => 'Địa chỉ công khai của đại lý', 'is_active' => true]);
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message.PHP_EOL;
};
$landing = view('dealers.show', ['dealer' => $dealer, 'standing' => null, 'preview' => false])->render();
$check(str_contains($landing, 'Đại lý kiểm tra'), 'Dealer data is dynamic');
$check(substr_count($landing, 'method="POST"') === 1 && str_contains($landing, '/qa-dealer/registrations/step-one'), 'Landing has only the real Step One POST form');
$check(str_contains($landing, 'name="_token"') && str_contains($landing, 'name="website"'), 'Step One keeps CSRF and honeypot protection');
$check(! str_contains($landing, 'registration-current') && ! str_contains($landing, 'dr-profile-details') && ! str_contains($landing, 'dr-phase-one-note'), 'Landing excludes developer-added panels and draft instructions');
$check(! str_contains($landing, '<noscript>'), 'No obsolete instruction to use a removed fallback form');
$check(! str_contains($landing, 'Toyota An Sương') && ! str_contains($landing, '1.126'), 'No Figma demo Dealer/member data');
$check(! str_contains($landing, 'data-preview'), 'Production landing has no preview UI');
$check(! str_contains($landing, 'data-step-two-screen') && str_contains($landing, '/qa-dealer/registrations/step-one'), 'Production uses server step-one endpoint, not embedded preview');
$check(substr_count($landing, 'id="registration"') === 1, 'Unique registration anchor');
$dealer->is_active = false;
$inactive = view('dealers.show', ['dealer' => $dealer, 'standing' => null, 'preview' => false])->render();
$check(str_contains($inactive, 'Mã QR không còn hiệu lực') && ! str_contains($inactive, 'method="POST"') && ! str_contains($inactive, 'data-step-one'), 'Inactive Dealer blocks registration and renders D06');
$dealer->is_active = true;
foreach (['step-two', 'success'] as $screen) {
    $html = view('dealers.registration.'.$screen, ['dealer' => $dealer, 'preview' => true])->render();
    $check(! str_contains($html, '0901 234 567') && ! str_contains($html, '51K-123.45</li>'), $screen.' has no fake confirmation PII');
}
$duplicate = view('components.dealer-registration.step-one', ['dealer' => $dealer, 'duplicatePhone' => true, 'validationErrors' => ['phone' => ['Số điện thoại đã đăng ký.']], 'values' => ['customer_name' => 'Khách kiểm tra', 'vehicle_description' => 'Hilux']])->render();
$check(str_contains($duplicate, 'dr-duplicate') && str_contains($duplicate, 'checked'), 'Backend-supplied D05 retains selected car');
$noDuplicate = view('components.dealer-registration.step-one', ['dealer' => $dealer])->render();
$check(! str_contains($noDuplicate, 'dr-duplicate'), 'No duplicate state inferred without backend result');
$invalidStepOne = view('components.dealer-registration.step-one', ['dealer' => $dealer, 'validationErrors' => [
    'customer_name' => ['Vui lòng nhập họ tên.'], 'phone' => ['Số điện thoại không hợp lệ.'],
    'vehicle_description' => ['Vui lòng chọn dòng xe.'],
], 'values' => ['phone' => 'synthetic-invalid']])->render();
$check(! str_contains($invalidStepOne, 'data-error-summary') && ! str_contains($invalidStepOne, 'dr-error-summary'), 'Step One has no validation summary box');
$check(str_contains($invalidStepOne, 'Vui lòng nhập họ tên.') && str_contains($invalidStepOne, 'Số điện thoại không hợp lệ.') && str_contains($invalidStepOne, 'Vui lòng chọn dòng xe.'), 'Step One renders specific inline errors');
$check(substr_count($invalidStepOne, 'aria-invalid="true"') === 6 && str_contains($invalidStepOne, 'value="synthetic-invalid"'), 'Step One text/radio errors expose aria state and preserve invalid text');
$stepTwoEmpty = view('dealers.registration.step-two', ['dealer' => $dealer, 'preview' => true])->render();
$check(! str_contains($stepTwoEmpty, 'data-error-summary') && ! str_contains($stepTwoEmpty, 'dr-error-summary'), 'Step Two has no validation summary or summary spacing');
$check(! str_contains($stepTwoEmpty, 'aria-invalid="true"'), 'Untouched Step Two has no initial errors');
$stepTwoErrors = view('dealers.registration.step-two', ['dealer' => $dealer, 'preview' => true,
    'validationErrors' => ['province_code' => ['required'], 'ward_code' => ['required'], 'address_line' => ['required'],
        'vehicle_description' => ['required'], 'license_plate' => ['unique', 'Duplicate message'],
        'first_registration_year' => ['invalid'], 'odo' => ['invalid'], 'facebook_url' => ['invalid'], 'consent' => ['accepted']],
    'values' => ['address_line' => '', 'license_plate' => 'DEMO-001', 'odo' => '-1', 'facebook_url' => 'synthetic-invalid'],
])->render();
$check(! str_contains($stepTwoErrors, 'data-error-summary') && ! str_contains($stepTwoErrors, 'Còn '), 'Backend Step Two errors never render a summary');
foreach (['Vui lòng chọn Tỉnh/Thành phố.', 'Vui lòng chọn Phường/Xã.', 'Vui lòng nhập địa chỉ chi tiết.',
    'Vui lòng chọn dòng xe.', 'Biển số xe này đã được đăng ký.', 'Vui lòng chọn năm đăng ký lần đầu.',
    'Vui lòng nhập số km hợp lệ.', 'Liên kết Facebook không hợp lệ.', 'Vui lòng đồng ý điều khoản để tiếp tục.'] as $message) {
    $check(substr_count($stepTwoErrors, $message) === 1, 'Step Two renders one specific message per supplied field');
}
$check(str_contains($stepTwoErrors, 'value="synthetic-invalid"') && str_contains($stepTwoErrors, 'value="-1"'), 'Step Two backend errors retain invalid editable values');
$check(substr_count($stepTwoErrors, 'aria-live="polite"') >= 9 && str_contains($stepTwoErrors, 'aria-describedby="dr-license_plate-error dr-license_plate-help"'), 'Step Two errors have live regions and descriptions');
$finalForm = view('dealers.registration.step-two', ['dealer' => $dealer, 'preview' => false, 'draftId' => 'synthetic-draft'])->render();
$check(str_contains($finalForm, 'method="POST"') && str_contains($finalForm, 'name="_draft"') && str_contains($finalForm, 'name="_token"'), 'Production final form has POST, draft identity and CSRF');
$check(! str_contains($finalForm, 'id="dr-step-two-notice"'), 'Production final form has no obsolete unsent notice');
$check($app['router']->getRoutes()->getByName('dealers.registrations.store')->uri() === '{slug}/registrations', 'Existing POST URL unchanged');
// Real Blade contracts for lookup states; QA fixtures never become public mock routes.
foreach (['found', 'not_found', 'draft', 'supplement', 'supplemented', 'not_requested', 'requested', 'member', 'blocked'] as $kind) {
    $result = ['kind' => $kind, 'masked_name' => 'S** O**', 'vehicle' => 'Toyota',
        'reason' => 'Thông tin cần được đối chiếu.'];
    $html = view('dealers.registration.lookup', ['dealer' => $dealer, 'values' => [], 'errors' => [], 'result' => $result])->render();
    $check(str_contains($html, 'data-lookup-kind="'.$kind.'"') && ! str_contains($html, '48 giờ'), 'Lookup renders '.$kind.' without an SLA claim');
    preg_match_all('/\bid="([^"]+)"/', $html, $ids);
    $check(count($ids[1]) === count(array_unique($ids[1])), 'Unique accessible IDs for lookup '.$kind);
}
$result = ['kind' => 'supplement', 'masked_name' => 'S** O**', 'vehicle' => 'Toyota',
    'reason' => 'Link Facebook không mở được.', 'supplement_key' => 'synthetic-body-key',
    'verification_version' => str_repeat('a', 64), 'supplement_errors' => ['facebook_profile_url' => ['Liên kết Facebook không hợp lệ.']]];
$html = view('dealers.registration.lookup', ['dealer' => $dealer, 'values' => [], 'errors' => [], 'result' => $result])->render();
$check(str_contains($html, 'data-registration-supplement') && str_contains($html, '/lookup/supplement') && str_contains($html, 'name="_token"'), 'Owned supplement form has real POST and CSRF');
$check(substr_count($html, 'Liên kết Facebook không hợp lệ.') === 1 && str_contains($html, 'aria-describedby="supplement-facebook_profile_url-error'), 'Supplement error appears once beneath accessible field');
$check(! str_contains($html, '?supplement_key=') && ! str_contains($html, 'data-error-summary'), 'Supplement has no URL token or error summary');
$rulesDocument = require resource_path('content/campaign-rules.php');
$rulesHtml = view('campaign.rules', ['document' => $rulesDocument])->render();
$check(substr_count($rulesHtml, 'tabindex="-1" aria-labelledby="rules-heading-') === 8, 'D09 renders eight semantic native-anchor sections');
$rulesDom = new DOMDocument;
@$rulesDom->loadHTML('<?xml encoding="UTF-8">'.$rulesHtml);
$rulesXpath = new DOMXPath($rulesDom);
$check($rulesXpath->query('//main//a[normalize-space(text())="Đăng ký tham gia"]')->length === 1, 'D09 renders one content CTA, not separate desktop/mobile copies');
$check($rulesXpath->query('//article/following-sibling::aside//a[contains(@class,"dr-rules-join")]')->length === 1, 'Single D09 CTA follows article in reading order and belongs to sidebar');
$check(! str_contains($rulesHtml, 'Bản tham khảo thiết kế') && ! str_contains($rulesHtml, '(nội dung mẫu)') && ! str_contains($rulesHtml, 'dr-rules-notice'), 'D09 has no developer notice or sample suffix');
$check(! str_contains($rulesHtml, '<form') && ! str_contains($rulesHtml, '<script'), 'D09 needs no form or JavaScript');
$rulesComplete = view('campaign.rules', ['document' => null])->render();
$check(str_contains($rulesComplete, '31/01/2027') && ! str_contains($rulesComplete, 'Thể lệ chính thức đang được cập nhật') && ! str_contains($rulesComplete, 'Xem các đại lý Toyota'), 'D09 always renders Figma document without an alternate approval panel');
$check(str_contains($stepTwoErrors, route('campaign.rules')) && str_contains($landing, route('campaign.rules')), 'D01 and D02 connect the named campaign rules route');
$app->detectEnvironment(fn () => 'production');
try {
    app(DealerRegistrationPreviewController::class)->show('step-two');
    throw new RuntimeException('Production preview must fail');
} catch (NotFoundHttpException) {
    echo 'PASS Preview controller refuses production, including cached routes'.PHP_EOL;
}
