<?php

namespace Tests\Unit;

use App\Models\Dealer;
use App\Services\DealerCallPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DealerCallPhoneTest extends TestCase
{
    #[DataProvider('phones')]
    public function test_parse_preserves_number_boundaries_and_deduplicates(?string $raw, array $expected): void
    {
        $this->assertSame($expected, (new DealerCallPhone)->parse($raw));
    }

    public static function phones(): array
    {
        return [
            'single' => ['02583891999', ['02583891999']],
            'two' => ['02583891999, 0912909032', ['02583891999', '0912909032']],
            'slash' => ['02583891999 / 0912909032', ['02583891999', '0912909032']],
            'semicolon' => ['02583891999;0912909032', ['02583891999', '0912909032']],
            'lines' => ["02583891999\r\n0912909032\n", ['02583891999', '0912909032']],
            'formatting' => [" 0258 389 1999 / 0912\t909 032 ", ['02583891999', '0912909032']],
            'international' => ['+84 258 389 1999, +84 (912) 909-032', ['02583891999', '0912909032']],
            'aliases' => ['0912909032,+84 912 909 032;0084 912909032/0912.909.032', ['0912909032']],
            'unicode spaces' => ["0912\u{00a0}909\u{202f}032", ['0912909032']],
            'mixed invalid' => ['invalid,02583891999;123/0912909032', ['02583891999', '0912909032']],
            'only whitespace is not a separator' => ['02583891999 0912909032', []],
            'invalid prefix' => ['+84+912909032', []],
            'injection' => ['0912909032<script>', []],
            'invalid digits' => ['0000000000;123;+84123;999999999999999999', []],
            'hotlines' => ['19001234 / 1800123456', ['19001234', '1800123456']],
            'empty' => ['', []],
            'null' => [null, []],
        ];
    }

    public function test_only_persisted_active_dealer_phone_is_read_without_mutating_it(): void
    {
        $raw = '02583891999, 0912909032';
        $dealer = (new Dealer)->forceFill(['phone' => $raw, 'is_active' => true]);
        $service = new DealerCallPhone;
        $this->assertSame([], $service->allForDealer($dealer));
        $dealer->exists = true;
        $this->assertSame(['02583891999', '0912909032'], $service->allForDealer($dealer));
        $this->assertSame($raw, $dealer->phone);
        $dealer->is_active = false;
        $this->assertSame([], $service->allForDealer($dealer));
    }
}
