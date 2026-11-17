<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Services\DealerCallPhone;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DealerPhonePresentationTest extends TestCase
{
    #[DataProvider('phoneLists')]
    public function test_all_contact_presentations_render_one_visible_call_per_valid_number(?string $raw, array $expected): void
    {
        $this->withoutVite();
        // View fixtures cannot fall back to a database or a Manager account.
        config(['database.default' => 'disabled-for-phone-views', 'dealer-registration.approved_call_phones' => [],
            'dealer-registration.manager_contacts' => []]);
        $dealer = (new Dealer)->forceFill(['id' => 999999, 'slug' => 'phone-view-fixture', 'name' => 'Đại lý kiểm thử',
            'is_active' => true, 'phone' => $raw, 'address' => 'Địa chỉ đại lý', 'opening_hours' => '08:00 – 17:00']);
        $dealer->exists = true;
        $views = [
            ['components.dealer-registration.dealer-card', []],
            ['components.dealer-registration.dealer-card', ['compact' => true]],
            ['dealers.registration.success', []],
            ['components.dealer-registration.lookup-next', ['result' => ['kind' => 'blocked', 'reason' => 'Cần đối chiếu']]],
            ['components.competition.dealer-profile', ['phones' => app(DealerCallPhone::class)->allForDealer($dealer),
                'months' => [['period' => '2026-09', 'label' => 'Tháng 9', 'score' => 0], ['period' => '2026-10', 'label' => 'Tháng 10', 'score' => 0], ['period' => '2026-11', 'label' => 'Tháng 11', 'score' => 0]]]],
        ];
        foreach ($views as [$view, $data]) {
            $html = view($view, ['dealer' => $dealer, ...$data])->render();
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $calls = (new DOMXPath($dom))->query('//a[starts-with(@href,"tel:")]');
            $this->assertCount(count($expected), $calls, $view);
            foreach ($expected as $index => $phone) {
                $this->assertSame('tel:'.$phone, $calls->item($index)->getAttribute('href'), $view);
                $this->assertStringContainsString($phone, $calls->item($index)->textContent, $view);
            }
            $this->assertSame($raw, $dealer->phone);
        }
    }

    public static function phoneLists(): array
    {
        return [
            ['02583891999', ['02583891999']],
            ['02583891999, 0912909032', ['02583891999', '0912909032']],
            ["+84 258 389 1999 / 0912 909 032;invalid\n+84 912909032", ['02583891999', '0912909032']],
            [null, []],
            ['invalid;123', []],
        ];
    }
}
