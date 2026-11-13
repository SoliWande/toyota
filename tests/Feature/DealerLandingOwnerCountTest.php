<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\ResetDemoRetirement;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerLandingOwnerCountTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false, 'realistic_demo.include_in_metrics' => false]);
        $this->app->instance(ResetDemoRetirement::class, Mockery::mock(ResetDemoRetirement::class, fn ($mock) => $mock->shouldReceive('isPresent')->andReturn(false)));
    }

    private function approvedQr(Dealer $dealer): CustomerSubmission
    {
        $phone = fake()->unique()->numerify('09########');

        return CustomerSubmission::factory()->approved()->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id,
            'phone' => $phone, 'phone_normalized' => $phone, 'vehicle_description' => 'Toyota Hilux',
            'address_line' => 'Isolated CTA fixture', 'province_code' => '79', 'ward_code' => '26734',
            'consented_at' => now(), 'consent_version' => 'd01-cta-test']);
    }

    private function browserFixture(string $name, string $html): void
    {
        $directory = base_path('.laravel-tools/d01-cta-browser');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html);
    }

    public function test_hero_counts_only_approved_profiles_attributed_to_the_current_slug(): void
    {
        $dealer = Dealer::factory()->create();
        $other = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        $approved = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $this->approvedQr($dealer);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        $this->approvedQr($other);
        $sales->forceFill(['dealer_id' => $other->id])->save();
        config(['dealer-registration.member_count' => 1126]);

        $this->withVite();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk()
            ->assertViewHas('dealerOwnerCount', 2)->assertSee('2 chủ xe')->assertSee('Tham gia ngay')
            ->assertDontSee('1.126')->assertDontSee($approved->customer_name);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->browserFixture('owners-2', $response->getContent());
        // Repeated visits do not create a registration or inflate the count.
        $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewHas('dealerOwnerCount', 2);
        $this->get(route('dealers.profile', $other->slug))->assertOk()->assertViewHas('dealerOwnerCount', 1);
    }

    public function test_demo_profiles_are_excluded_even_when_demo_metrics_are_enabled(): void
    {
        $dealer = Dealer::factory()->create();
        $this->approvedQr($dealer);
        $demo = $this->approvedQr($dealer);
        // Ownership is supplied only by this isolated test double, never labels or runtime fake rows.
        $this->app->instance(ToyotaRealisticDemo::class, Mockery::mock(ToyotaRealisticDemo::class,
            fn ($mock) => $mock->shouldReceive('ownedSubmissionIds')->andReturn([$demo->id])));
        config(['realistic_demo.enabled' => true, 'realistic_demo.include_in_metrics' => true]);

        $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewHas('dealerOwnerCount', 1)->assertSee('1 chủ xe');
    }

    public function test_zero_is_real_and_unknown_slugs_do_not_fall_back_to_system_totals(): void
    {
        $dealer = Dealer::factory()->create();
        CustomerSubmission::factory()->approved()->create();
        config(['dealer-registration.member_count' => 1126]);
        $this->withVite();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewHas('dealerOwnerCount', 0)->assertSee('0 chủ xe')->assertDontSee('1.126');
        $this->browserFixture('owners-0', $response->getContent());
        $this->get(route('dealers.profile', 'not-a-real-dealer-cta'))->assertNotFound();
    }

    public function test_inactive_dealer_keeps_the_existing_registration_guard_with_a_real_count(): void
    {
        $dealer = Dealer::factory()->create();
        $this->approvedQr($dealer);
        $dealer->forceFill(['is_active' => false])->save();

        $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewHas('dealerOwnerCount', 1)
            ->assertSee('Mã QR không còn hiệu lực')->assertDontSee('data-step-one', false)->assertDontSee('class="dr-sticky-cta"', false);
    }
}
