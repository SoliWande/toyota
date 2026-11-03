<?php

namespace Tests\Feature;

use App\Models\Dealer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/** View fixtures only; no database access. */
class DealerRegistrationSuccessPresentationTest extends TestCase
{
    public function test_success_guidance_and_group_link_do_not_depend_on_approval(): void
    {
        $this->withoutVite();
        $dealer = (new Dealer)->forceFill(['slug' => 'synthetic-dealer', 'name' => 'Synthetic Dealer', 'is_active' => true]);
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $confirmation = ['status' => $status, 'customer_name' => 'SYNTHETIC_PRIVATE_NAME',
                'vehicle_description' => 'SYNTHETIC_PRIVATE_VEHICLE', 'phone' => '0990000007', 'license_plate' => '51K98765'];
            $html = view('dealers.registration.success', compact('dealer', 'confirmation'))->render();
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($dom);
            $this->assertSame('Đăng ký thành công!', trim($xpath->query('//h1')->item(0)->textContent));
            $this->assertSame('Bước cuối: vào group Facebook của nhóm.', trim($xpath->query('//h1/following-sibling::p')->item(0)->textContent));
            $this->assertFalse(str_contains($html, $confirmation['customer_name']));
            $this->assertFalse(str_contains($html, $confirmation['vehicle_description']));
            $this->assertSame(0, $xpath->query('//dl')->length);
            $this->assertSame(1, $xpath->query('//div[@class="dr-success-heading"]/following-sibling::*[1][@class="dr-group"]')->length);
            $answers = $xpath->query('//div[@class="dr-group-questions"]/ol/li');
            $this->assertSame(2, $answers->length);
            $this->assertTrue(str_contains($answers->item(0)->textContent, $confirmation['phone']));
            $this->assertTrue(str_contains($answers->item(1)->textContent, $confirmation['license_plate']));
            $links = $xpath->query('//div[@class="dr-group-action"]/a');
            $this->assertSame(1, $links->length);
            $link = $links->item(0);
            $this->assertSame('https://www.facebook.com/groups/376631239337942', rtrim($link->getAttribute('href'), '/'));
            $this->assertSame('_blank', $link->getAttribute('target'));
            $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
            $this->assertFalse($link->hasAttribute('aria-disabled'));
            $this->assertSame('Dùng tài khoản Facebook bạn đã khai báo.', trim($xpath->query('//div[@class="dr-group-action"]/p')->item(0)->textContent));
            $this->assertSame('Chờ vào group', trim($xpath->query('//span[@class="dr-status-chip"]')->item(0)->textContent));
            $this->assertSame(3, $xpath->query('//ol[@class="dr-timeline"]/li')->length);
            $this->assertSame(1, $xpath->query('//li[@class="is-done"]')->length);
            $this->assertSame(1, $xpath->query('//li[@class="is-current" and @aria-current="step"]')->length);
            $this->assertSame(1, $xpath->query('//li[@class="is-upcoming"]')->length);
            $this->assertStringNotContainsString('Chờ xác minh', $html);
            $this->assertStringNotContainsString('48 giờ', $html);
        }
    }
}
