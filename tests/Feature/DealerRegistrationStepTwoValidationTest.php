<?php

namespace Tests\Feature;

use App\Models\Dealer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/** Presentation fixtures only: no database queries, transactions or migrations. */
class DealerRegistrationStepTwoValidationTest extends TestCase
{
    private function render(array $values = [], array $errors = []): DOMXPath
    {
        $this->withoutVite();
        $dealer = (new Dealer)->forceFill(['slug' => 'synthetic-dealer', 'name' => 'Synthetic Dealer', 'is_active' => true]);
        $html = view('dealers.registration.step-two', ['dealer' => $dealer, 'preview' => true,
            'values' => $values, 'validationErrors' => $errors])->render();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    public function test_untouched_step_two_has_no_visible_summary_or_errors(): void
    {
        $xpath = $this->render();
        $this->assertSame(1, $xpath->query('//*[@data-error-summary]')->length);
        $this->assertTrue($xpath->query('//*[@data-error-summary]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(0, $xpath->query('//*[@aria-invalid="true"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-error-for and not(@hidden)]')->length);
    }

    public function test_backend_errors_are_specific_inline_announcements_and_keep_values(): void
    {
        $messages = [
            'province_code' => 'Vui lòng chọn Tỉnh/Thành phố.', 'ward_code' => 'Vui lòng chọn Phường/Xã.',
            'address_line' => 'Vui lòng nhập địa chỉ chi tiết.', 'vehicle_description' => 'Vui lòng chọn dòng xe.',
            'license_plate' => 'Biển số xe không hợp lệ.', 'first_registration_year' => 'Năm đăng ký không hợp lệ.',
            'odo' => 'Vui lòng nhập số km hợp lệ.', 'facebook_url' => 'Link chưa đúng. Dán link trang cá nhân, dạng facebook.com/...',
            'consent' => 'Bạn cần đồng ý điều khoản để gửi đăng ký.',
        ];
        $xpath = $this->render(['license_plate' => '!synthetic', 'first_registration_year' => '9999',
            'odo' => 'invalid', 'facebook_url' => 'synthetic-invalid'],
            array_fill_keys(array_keys($messages), ['Generic validation', 'Repeated validation']));
        $this->assertSame(1, $xpath->query('//*[@data-error-summary]')->length);
        foreach ($messages as $field => $message) {
            $nodes = $xpath->query('//*[@data-error-for="'.$field.'"]');
            $this->assertSame(1, $nodes->length);
            $this->assertSame($message, trim($nodes->item(0)->textContent));
            $this->assertSame('polite', $nodes->item(0)->getAttribute('aria-live'));
            $this->assertFalse($nodes->item(0)->hasAttribute('hidden'));
            $prefix = $field === 'vehicle_description' ? 'dr-step-two-' : 'dr-';
            $control = $xpath->query('//*[@id="'.$prefix.$field.'"]')->item(0);
            $this->assertSame('true', $control->getAttribute('aria-invalid'));
            $this->assertStringContainsString($prefix.$field.'-error', $control->getAttribute('aria-describedby'));
        }
        $this->assertTrue($xpath->query('//input[@name="odo"]')->item(0)->getAttribute('value') === 'invalid');
        $this->assertSame(1, $xpath->query('//select[@name="first_registration_year"]/option[@selected and @value="9999"]')->length);
    }

    public function test_plate_duplicate_is_only_displayed_when_supplied_by_backend(): void
    {
        $xpath = $this->render(['license_plate' => '51K-123.45'], ['license_plate' => ['unique']]);
        $this->assertSame('Biển số xe này đã được đăng ký.', trim($xpath->query('//*[@data-error-for="license_plate"]')->item(0)->textContent));
        $xpath = $this->render([], ['license_plate' => ['required']]);
        $this->assertSame('Vui lòng nhập biển số xe.', trim($xpath->query('//*[@data-error-for="license_plate"]')->item(0)->textContent));
        $xpath = $this->render(['license_plate' => '51K-123.45']);
        $this->assertTrue($xpath->query('//*[@data-error-for="license_plate"]')->item(0)->hasAttribute('hidden'));
    }

    public function test_figma_consent_uses_real_checked_state_and_configured_terms_link(): void
    {
        config(['dealer-registration.terms_url' => 'https://legal.example.invalid/d02-consent']);
        $xpath = $this->render();
        $copy = $xpath->query('//*[@class="dr-consent-copy"]')->item(0);
        $this->assertSame('Tôi đồng ý cho Toyota Việt Nam và Ô tô Sài Gòn dùng thông tin trên để xác nhận tham gia cộng đồng. Xem điều khoản', trim(preg_replace('/\s+/u', ' ', $copy->textContent)));
        $this->assertStringNotContainsString('Tôi đồng ý để đại lý', $copy->textContent);
        $checkbox = $xpath->query('//input[@name="consent"]')->item(0);
        $this->assertTrue($checkbox->hasAttribute('required'));
        $this->assertFalse($checkbox->hasAttribute('checked'));
        $this->assertSame(1, $xpath->query('//label[@for="dr-consent"]')->length);
        $link = $xpath->query('//a[@data-consent-terms]')->item(0);
        $this->assertSame('Xem điều khoản', $link->textContent);
        $this->assertSame('https://legal.example.invalid/d02-consent', $link->getAttribute('href'));
        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
        $this->assertTrue($this->render(['consent' => true])->query('//input[@name="consent"]')->item(0)->hasAttribute('checked'));
        $this->assertFalse($this->render(['consent' => false], ['consent' => ['required']])->query('//input[@name="consent"]')->item(0)->hasAttribute('checked'));
    }

    public function test_unconfigured_terms_remain_a_disabled_link_without_fabricated_destination(): void
    {
        config(['dealer-registration.terms_url' => null]);
        $link = $this->render()->query('//a[@data-consent-terms]')->item(0);
        $this->assertSame('link', $link->getAttribute('role'));
        $this->assertSame('true', $link->getAttribute('aria-disabled'));
        $this->assertFalse($link->hasAttribute('href'));
    }
}
