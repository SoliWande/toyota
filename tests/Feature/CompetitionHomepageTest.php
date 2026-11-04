<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CompetitionHomepageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::assertIsolated();
    }

    public function test_home_has_b01_sections_and_only_real_navigation_targets(): void
    {
        $response = $this->get(route('home'))->assertOk();
        $response->assertSee('competition-site')->assertSee('competition/hero-mobile.png')
            ->assertSee('competition/hero-desktop.png')->assertSee('Lấy mã QR của đại lý')
            ->assertSee('Top đại lý tháng này')->assertSee('Giải thưởng mỗi tháng')
            ->assertSee(route('dealers.index'))->assertSee(route('campaign.rules'))
            ->assertDontSee('public-journey')->assertDontSee('1900 xxxx');
        $this->assertSame(11, substr_count($response->getContent(), '<details>'));
        $this->assertSame(4, substr_count($response->getContent(), 'class="wc-step"'));
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.route('register').'"'));
        $this->assertDoesNotMatchRegularExpression('/href="(?:|#|tel:undefined|tel:)"/', $response->getContent());
    }

    public function test_unconfirmed_prizes_and_dates_never_use_figma_sample_values(): void
    {
        config(['dealer-registration.campaign_start' => null, 'dealer-registration.campaign_end' => null]);
        $response = $this->get(route('home'))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'class="wc-stat-pending">[Chờ TMV]'));
        $response->assertDontSee('>87<', false)->assertDontSee('>1.126<', false);
    }

    public function test_configured_dates_are_used_and_invalid_dates_fail_safely(): void
    {
        config(['dealer-registration.campaign_start' => '2026-12-01', 'dealer-registration.campaign_end' => '2027-01-31']);
        $this->get(route('home'))->assertOk()->assertSee('01/12/2026 – 31/01/2027');
        config(['dealer-registration.campaign_start' => 'invalid-date']);
        $this->get(route('home'))->assertOk()->assertDontSee('invalid-date');
    }

    public function test_actual_timezone_is_preserved_in_ranking_copy(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->get(route('home'))->assertOk()->assertSee('Múi giờ UTC')->assertDontSee('Giờ Việt Nam');
    }

    public function test_footer_does_not_create_unsafe_privacy_or_hotline_links(): void
    {
        config(['dealer-registration.privacy_url' => 'javascript:alert(1)', 'dealer-registration.hotline' => 'undefined']);
        $this->get(route('home'))->assertOk()->assertDontSee('javascript:')->assertDontSee('href="tel:', false);
    }

    public function test_authenticated_menu_preserves_account_and_post_logout(): void
    {
        foreach (['sales' => User::factory()->active(), 'dealer' => User::factory()->dealer(),
            'admin' => User::factory()->admin(), 'pending' => User::factory()] as $role => $factory) {
            $user = $factory->create();
            $response = $this->actingAs($user)->get(route('home'))->assertOk()
                ->assertSee(route($user->homeRoute()))->assertSee('method="POST"', false)
                ->assertSee(route('logout'))->assertSee('name="_token"', false)
                ->assertDontSee(route('register'));
            // Opt-in private QA artifacts only; never expose fixture routes in the app.
            if (getenv('COMPETITION_EXPORT_QA_HTML') === '1') {
                $directory = base_path('.laravel-tools/competition/final-qa-states');
                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                file_put_contents($directory.'/'.$role.'.html', $response->getContent());
            }
        }
    }
}
