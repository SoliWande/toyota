<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerStandeeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_download_is_a4_pdf_with_canonical_qr_and_logo_and_never_uses_request_host_or_dealer_id(): void
    {
        $manager = User::factory()->dealer()->create();
        $manager->dealer->update(['name' => 'Toyota Đại lý thử nghiệm tiếng Việt']);
        $foreign = User::factory()->dealer()->create();
        $profile = app(DealerProfile::class);
        $response = $this->actingAs($manager)->get(route('dealer.standee', ['dealer_id' => $foreign->dealer_id]), ['Host' => 'attacker.invalid'])
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Disposition', 'attachment; filename="dealer-'.$manager->dealer_id.'-standee-a4.pdf"');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.276 841.890]', $pdf);
        $this->assertStringContainsString(bin2hex($profile->url($manager->dealer)), $pdf);
        $this->assertStringNotContainsString(bin2hex($profile->url($foreign->dealer)), $pdf);
        $this->assertStringNotContainsString('attacker.invalid', $pdf);
        preg_match('/\/Filter \/FlateDecode \/Length (\d+)>>\nstream\n/', $pdf, $match, PREG_OFFSET_CAPTURE);
        $offset = $match[0][1] + strlen($match[0][0]);
        $pixels = gzuncompress(substr($pdf, $offset, (int) $match[1][0]));
        $this->assertSame(2480 * 3508 * 3, strlen($pixels));
        $qr = imagecreatefromstring($profile->qr($manager->dealer));
        // Compare the entire embedded QR at source-pixel centres, including its quiet zone.
        $mismatches = 0;
        for ($y = 0; $y < 600; $y++) {
            for ($x = 0; $x < 600; $x++) {
                $dx = 440 + (int) floor(($x + .5) * 1600 / 600);
                $dy = 1430 + (int) floor(($y + .5) * 1600 / 600);
                $pixelOffset = ($dy * 2480 + $dx) * 3;
                $expected = imagecolorat($qr, $x, $y);
                $rgb = chr(($expected >> 16) & 255).chr(($expected >> 8) & 255).chr($expected & 255);
                $mismatches += substr($pixels, $pixelOffset, 3) !== $rgb ? 1 : 0;
            }
        }
        $this->assertSame(0, $mismatches, 'PDF must contain the existing canonical QR without changes.');
        $logoRegion = substr($pixels, 350 * 2480 * 3, 2480 * 50 * 3);
        $this->assertNotSame(str_repeat("\xff", strlen($logoRegion)), $logoRegion);
        preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $xref);
        $this->assertSame('xref', substr($pdf, (int) $xref[1], 4));
        preg_match_all('/(\d{10}) 00000 n /', $pdf, $entries);
        $this->assertCount(6, $entries[1]);
        foreach ($entries[1] as $index => $entry) {
            $this->assertStringStartsWith(($index + 1).' 0 obj', substr($pdf, (int) $entry, 20));
        }
    }

    public function test_standee_keeps_role_and_account_status_permissions(): void
    {
        $this->getJson(route('dealer.standee'))->assertUnauthorized();
        foreach ([User::factory()->active()->create(), User::factory()->admin()->create(),
            User::factory()->dealer()->create(['status' => 'pending']),
            User::factory()->dealer()->create(['status' => 'blocked']),
            User::factory()->dealer()->create(['status' => 'rejected'])] as $user) {
            $this->actingAs($user)->getJson(route('dealer.standee'))->assertForbidden();
        }
    }
}
