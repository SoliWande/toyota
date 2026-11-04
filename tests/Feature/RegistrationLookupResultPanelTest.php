<?php

namespace Tests\Feature;

use App\Models\Dealer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationLookupResultPanelTest extends TestCase
{
    public static function panels(): array
    {
        return [
            'unknown is not a fabricated workflow state' => ['found', false, 'Chưa xác nhận', []],
            'D7 owned request' => ['supplement', true, 'Cần bổ sung', ['Gửi bổ sung']],
            'D8 receipt confirmation' => ['supplemented', false, null, ['Xem lại trạng thái', 'Về trang Cộng đồng Toyota']],
            'D10 absent registration' => ['not_found', false, null, ['Đăng ký tham gia']],
            'D11 continue actual draft' => ['draft', true, 'Chưa hoàn tất', ['Tiếp tục đăng ký']],
            'D12 request Group' => ['not_requested', true, 'Chờ vào group', ['Vào group Facebook']],
            'D13 wait without an action panel' => ['requested', false, 'Chờ duyệt', []],
            'D14 member opens Group' => ['member', true, 'Thành viên', ['Mở group Facebook']],
            'D15 real Dealer phone' => ['blocked', true, 'Chưa xác nhận', ['Gọi đại lý 0900000000']],
        ];
    }

    private function renderPanel(string $kind, array $extra = [], ?string $phone = '0900000000', bool $withValues = true): array
    {
        $this->withoutVite();
        $dealer = new Dealer;
        $dealer->forceFill(['id' => 1, 'slug' => 'synthetic-panel', 'name' => 'Synthetic Dealer', 'phone' => $phone, 'is_active' => true]);
        $result = array_replace(['kind' => $kind, 'masked_name' => 'S** O**', 'vehicle' => 'Hilux',
            'group_confirmed' => false, 'reason' => 'Lý do đã được backend xác nhận.',
            'supplement_key' => 'synthetic-view-only', 'verification_version' => str_repeat('a', 64)], $extra);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $html = view('dealers.registration.lookup', ['dealer' => $dealer, 'result' => $result,
            'values' => $withValues ? ['phone' => '0990000001', 'license_plate' => '51K90001'] : [], 'errors' => []])->render();
        $this->assertSame([], $queries, 'Presentation must not query customer or workflow data.');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return [$html, new DOMXPath($dom)];
    }

    #[DataProvider('panels')]
    public function test_result_panel_has_only_state_specific_copy_and_actions(string $kind, bool $sidebox, ?string $badge, array $ctas): void
    {
        [$html, $xpath] = $this->renderPanel($kind);
        foreach (['Đã tìm thấy đăng ký', 'Thông tin tra cứu khớp với một đăng ký.', '<h3>Cộng đồng Toyota Veloz &amp; Hilux</h3>', '48 giờ'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
        $this->assertSame($sidebox ? 1 : 0, $xpath->query('//*[@data-lookup-sidebox]')->length);
        $badges = $xpath->query('//*[@data-lookup-badge]');
        $this->assertSame($badge === null ? 0 : 1, $badges->length);
        if ($badge !== null) {
            $this->assertSame($badge, trim($badges->item(0)->textContent));
        }
        $buttons = $xpath->query('//*[@data-lookup-kind]//*[contains(concat(" ", normalize-space(@class), " "), " dr-button ")]');
        $this->assertSame($ctas, array_map(fn ($node) => trim($node->textContent), iterator_to_array($buttons)));
        if ($kind === 'not_requested') {
            $this->assertStringContainsString('Bước tiếp theo: vào group', $html);
            $this->assertStringContainsString('0990000001 và biển số 51K90001', $html);
        }
        if ($kind === 'blocked') {
            $this->assertStringContainsString('Lý do đã được backend xác nhận.', $html);
        }
    }

    public function test_supplement_without_permission_never_gains_an_edit_form(): void
    {
        [$html] = $this->renderPanel('supplement', ['supplement_key' => null]);
        $this->assertStringNotContainsString('data-registration-supplement', $html);
        $this->assertStringContainsString('Liên hệ để bổ sung', $html);
    }

    public function test_no_fake_call_button_when_dealer_phone_is_missing(): void
    {
        [$html] = $this->renderPanel('blocked', [], null);
        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringNotContainsString('1900 xxxx', $html);
    }

    public function test_group_instruction_never_prints_blank_or_invented_caller_identifiers(): void
    {
        [$html] = $this->renderPanel('not_requested', [], '0900000000', false);
        $this->assertStringContainsString('trả lời đúng số điện thoại và biển số bạn đã đăng ký.', $html);
        $this->assertStringNotContainsString('số điện thoại  và biển số', $html);
        $this->assertStringNotContainsString('0901 234 567', $html);
    }

    public function test_unconfigured_group_link_is_disabled_instead_of_an_invented_destination(): void
    {
        config(['dealer-registration.group_url' => null]);
        foreach (['not_requested', 'member'] as $kind) {
            [$html] = $this->renderPanel($kind);
            $this->assertStringContainsString('aria-disabled="true"', $html);
            $this->assertStringNotContainsString('href="https://www.facebook.com/groups/', $html);
        }
    }
}
