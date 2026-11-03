<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use App\Services\DealerRegistrationDraft;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CampaignRulesTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    private string $cachePath;

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'app.debug' => false]);
        Cache::forgetDriver('array');
        $this->cachePath = storage_path('framework/testing/rules-'.bin2hex(random_bytes(8)));
        config(['cache.stores.file.path' => $this->cachePath]);
        Cache::forgetDriver('file');
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    protected function tearDown(): void
    {
        if (isset($this->cachePath)) {
            File::deleteDirectory($this->cachePath);
        }
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    public function test_public_page_is_database_free_semantic_html_with_native_anchors_and_stable_route(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $response = $this->get(route('campaign.rules'))->assertOk()->assertSee('Thể lệ đầy đủ')
            ->assertDontSee('Bản tham khảo thiết kế — chưa được phê duyệt')->assertDontSee('(nội dung mẫu)')
            ->assertDontSee('dr-rules-notice')->assertDontSee('chưa khả dụng')->assertDontSee('Thể lệ chính thức đang được cập nhật')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertGuest();
        $this->assertSame(0, $queries, 'Rules GET must not query/write application data.');
        $this->assertSame('/chuong-trinh/the-le', route('campaign.rules', absolute: false));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(8, $xpath->query('//article/section')->length);
        // One content CTA is shared across desktop/sidebar and mobile/end-of-article.
        $this->assertSame(1, $xpath->query('//main//a[normalize-space(text())="Đăng ký tham gia"]')->length);
        $this->assertSame(1, $xpath->query('//main//aside[contains(@class,"dr-rules-toc")]//a[contains(@class,"dr-rules-join")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"dr-rules-mobile-join")]')->length);
        $this->assertSame(1, $xpath->query('//article/following-sibling::aside[contains(@class,"dr-rules-toc")]')->length);
        foreach ($xpath->query('//span[contains(@class,"dr-rules-number")]') as $index => $number) {
            $this->assertSame(sprintf('%02d', $index + 1), $number->textContent);
        }
        $this->assertSame(0, $xpath->query('//form|//script')->length);
        $document = require resource_path('content/campaign-rules.php');
        foreach ($document['sections'] as $section) {
            $response->assertSee($section['title'])->assertSee($section['body']);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Mục lục thể lệ"]//a[@href="#rules-'.$section['id'].'"]')->length);
            $this->assertSame(1, $xpath->query('//section[@id="rules-'.$section['id'].'" and @tabindex="-1"]')->length);
        }
    }

    public function test_all_environments_render_figma_copy_without_developer_notices_or_alternate_ctas(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $response = $this->get(route('campaign.rules').'?sample=1&preview=1')->assertOk()
            ->assertSee('01/11/2026')->assertSee('31/01/2027')->assertSee('rules-thoi-gian')
            ->assertDontSee('Bản tham khảo thiết kế')->assertDontSee('(nội dung mẫu)')
            ->assertDontSee('Thể lệ chính thức đang được cập nhật')->assertDontSee('Xem các đại lý Toyota')
            ->assertDontSee('chưa được phê duyệt')->assertDontSee('sample=1');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(8, $xpath->query('//article/section')->length);
        $this->assertSame(1, $xpath->query('//main//a[normalize-space(text())="Đăng ký tham gia"]')->length);
        $document = require resource_path('content/campaign-rules.php');
        foreach ($document['sections'] as $section) {
            $response->assertSee($section['body']);
        }
        $this->assertGuest();
    }

    public function test_campaign_route_does_not_capture_existing_dealer_slug_the_le_or_its_qr_destination(): void
    {
        $dealer = Dealer::factory()->create(['name' => 'The Le']);
        $this->assertSame('the-le', $dealer->slug);
        $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewIs('dealers.show');
        $response = $this->get(route('dealers.show', $dealer->id))->assertStatus(301);
        $this->assertSame('/the-le', parse_url(rtrim($response->headers->get('Location'), '/'), PHP_URL_PATH));
        $this->get(route('campaign.rules'))->assertOk()->assertViewIs('campaign.rules');
    }

    public function test_registration_and_lookup_links_use_named_rules_route_and_preserve_distinct_legal_urls(): void
    {
        $dealer = Dealer::factory()->create();
        config(['dealer-registration.terms_url' => 'https://legal.example.invalid/data-consent',
            'dealer-registration.privacy_url' => 'https://legal.example.invalid/privacy']);
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//a[contains(@class,"dr-rules-link") and @href="'.route('campaign.rules').'" and @target="_blank" and @rel="noopener noreferrer"]')->length);
        $views = [
            view('dealers.registration.step-two', ['dealer' => $dealer, 'preview' => false])->render(),
            view('dealers.registration.success', ['dealer' => $dealer])->render(),
        ];
        foreach (['not_found', 'draft', 'found', 'supplement', 'supplemented', 'not_requested', 'requested', 'member', 'blocked'] as $kind) {
            $views[] = view('dealers.registration.lookup', ['dealer' => $dealer, 'values' => [], 'errors' => [],
                'result' => ['kind' => $kind, 'masked_name' => 'S** O**', 'vehicle' => 'Toyota', 'reason' => 'Thông tin cần đối chiếu.']])->render();
        }
        foreach ($views as $html) {
            $xpath = $this->xpath($html);
            $this->assertGreaterThan(0, $xpath->query('//a[@href="'.route('campaign.rules').'" and @target="_blank" and @rel="noopener noreferrer"]')->length);
            $this->assertSame(0, $xpath->query('//a[@href="#" or starts-with(@href,"javascript:")]')->length);
            $this->assertSame(1, $xpath->query('//a[@href="https://legal.example.invalid/privacy"]')->length);
        }
        $this->assertTrue(str_contains($views[0], 'https://legal.example.invalid/data-consent'));
        $this->assertTrue(str_contains($views[0], config('dealer.consent_notice')));
    }

    public function test_opening_rules_preserves_full_draft_values_id_and_fixed_expiration(): void
    {
        $dealer = Dealer::factory()->create();
        $this->post(route('dealers.registrations.step-one', $dealer->slug), ['customer_name' => 'SYNTHETIC_RULES_OWNER',
            'phone' => '0990000061', 'vehicle_description' => 'FJ'])->assertRedirect();
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        $drafts = app(DealerRegistrationDraft::class);
        $draft = $drafts->read($request, $dealer);
        $draft['step_two'] = ['values' => ['address_line' => 'SYNTHETIC_RULES_ADDRESS', 'license_plate' => '51K90061',
            'facebook_url' => 'https://facebook.com/synthetic.rules.owner'], 'errors' => []];
        $drafts->update($request, $dealer, $draft);
        $this->get(route('campaign.rules'))->assertOk()->assertDontSee('SYNTHETIC_RULES_OWNER')->assertDontSee('SYNTHETIC_RULES_ADDRESS');
        $this->assertTrue($draft === $drafts->read($request, $dealer), 'Rules GET changed draft values or expiration.');
        $response = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk();
        $this->assertTrue($response->viewData('values')['address_line'] === 'SYNTHETIC_RULES_ADDRESS');
        $this->assertSame($draft['id'], $response->viewData('draftId'));
        $this->assertSame(0, CustomerSubmission::count());
    }

    public function test_rules_get_never_backfills_historical_consent_or_mutates_submission(): void
    {
        $record = CustomerSubmission::factory()->approved()->create(['consent_version' => 'synthetic-historical-consent', 'consented_at' => now()->subYear()]);
        $before = $record->refresh()->getRawOriginal();
        $this->get(route('campaign.rules'))->assertOk();
        $this->assertTrue($before === $record->fresh()->getRawOriginal(), 'Rules GET mutated historical consent or submission.');
        $this->assertSame('dealer-qr-2026-10-20', config('dealer.consent_version'));
        $this->assertNull((require resource_path('content/campaign-rules.php'))['version']);
    }

    public function test_opening_rules_is_not_consent_and_final_still_requires_acceptance_with_existing_version(): void
    {
        $dealer = Dealer::factory()->create();
        $this->post(route('dealers.registrations.step-one', $dealer->slug), ['customer_name' => 'SYNTHETIC_RULES_OWNER',
            'phone' => '0990000061', 'vehicle_description' => 'Hilux'])->assertRedirect();
        $id = $this->get(route('dealers.registrations.step-two', $dealer->slug))->viewData('draftId');
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0]['code'];
        $data = ['_flow' => 'two-step', '_draft' => $id, 'province_code' => $province,
            'ward_code' => $directory->wards($province)[0]['code'], 'address_line' => 'SYNTHETIC_RULES_ADDRESS',
            'license_plate' => '51K90061', 'first_registration_year' => 2024,
            'facebook_url' => 'https://facebook.com/synthetic.rules.owner'];
        $this->get(route('campaign.rules'))->assertOk();
        $this->post(route('dealers.registrations.store', $dealer->slug), $data)->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $this->assertSame(0, CustomerSubmission::count());
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertSee('Vui lòng đồng ý điều khoản để tiếp tục.');
        $this->post(route('dealers.registrations.store', $dealer->slug), $data + ['consent' => '1'])
            ->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $submission = CustomerSubmission::sole();
        $this->assertSame(config('dealer.consent_version'), $submission->consent_version);
        $this->assertNotNull($submission->consented_at);
        $this->assertSame('pending', $submission->status->value);
    }
}
