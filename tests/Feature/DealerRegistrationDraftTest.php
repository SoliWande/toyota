<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\DealerRegistrationDraft;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DealerRegistrationDraftTest extends TestCase
{
    // Existing isolated schema only: do not run migrations, even in tests.
    use DatabaseTransactions;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32)), 'app.debug' => false]);
        $this->cachePath = storage_path('framework/testing/drafts-'.bin2hex(random_bytes(8)));
        config(['cache.stores.file.path' => $this->cachePath]);
        Cache::forgetDriver('file');
        $this->withoutVite();
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

    private function payload(array $overrides = []): array
    {
        return array_replace(['customer_name' => 'DRAFT_TEST_PERSON', 'phone' => '+84 901 234 567', 'vehicle_description' => 'FJ'], $overrides);
    }

    private function submit(Dealer $dealer, array $overrides = [])
    {
        return $this->post(route('dealers.registrations.step-one', $dealer->slug), $this->payload($overrides));
    }

    public function test_valid_draft_refresh_edit_and_no_submission(): void
    {
        $dealer = Dealer::factory()->create();
        $count = CustomerSubmission::count();
        $this->submit($dealer)->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        for ($i = 0; $i < 2; $i++) {
            $response = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue($response->viewData('values')['phone'] === '0901234567', 'Draft normalization failed.');
        }
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $this->assertTrue($response->viewData('stepOneValues')['vehicle_description'] === 'FJ');
        $this->submit($dealer, ['vehicle_description' => 'Hilux'])->assertRedirect();
        $response = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk();
        $this->assertTrue($response->viewData('values')['vehicle_description'] === 'Hilux');
        $this->assertSame($count, CustomerSubmission::count());
        $this->assertFalse(session()->has('_old_input'));
        $this->assertFalse(str_contains(serialize(session()->all()), $this->payload()['customer_name']), 'PII leaked into session.');
        foreach (File::allFiles($this->cachePath) as $file) {
            $this->assertFalse(str_contains($file->getContents(), $this->payload()['customer_name']), 'Draft stored without encryption.');
        }
    }

    public function test_expiry_does_not_extend_on_refresh(): void
    {
        $dealer = Dealer::factory()->create();
        $this->submit($dealer);
        $this->travel(29)->minutes();
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk();
        $this->travel(2)->minutes();
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertRedirect(route('dealers.profile', $dealer->slug).'#registration')->assertSessionHas('dealer_draft_notice');
    }

    public function test_dealer_and_session_isolation_and_multiple_tabs(): void
    {
        $a = Dealer::factory()->create();
        $b = Dealer::factory()->create();
        $this->submit($a);
        $this->get(route('dealers.registrations.step-two', $b->slug))->assertRedirect();
        $this->submit($b, ['vehicle_description' => 'Fortuner']);
        foreach ([$a->slug => 'FJ', $b->slug => 'Fortuner'] as $slug => $vehicle) {
            $response = $this->get(route('dealers.registrations.step-two', $slug))->assertOk();
            $this->assertTrue($response->viewData('values')['vehicle_description'] === $vehicle);
        }
        session()->invalidate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('dealers.registrations.step-two', $a->slug))->assertRedirect();
    }

    public function test_inactive_dealer_cannot_create_or_continue(): void
    {
        $dealer = Dealer::factory()->create();
        $this->submit($dealer);
        $dealer->update(['is_active' => false]);
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertRedirect();
        $this->submit($dealer)->assertNotFound();
        $dealer->update(['is_active' => true]);
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertRedirect();
    }

    public function test_global_pending_approved_block_and_rejected_does_not_block(): void
    {
        $dealer = Dealer::factory()->create();
        foreach (['pending', 'approved'] as $status) {
            $factory = CustomerSubmission::factory();
            $existing = ($status === 'approved' ? $factory->approved() : $factory)->create([
                'phone' => '0901.234.567', 'phone_normalized' => null, 'customer_name' => 'OTHER_PRIVATE_PERSON',
            ]);
            $this->submit($dealer)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
            $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk();
            $this->assertTrue($response->viewData('duplicatePhone'));
            $this->assertFalse(str_contains($response->getContent(), 'OTHER_PRIVATE_PERSON'), 'Owner PII leaked.');
            $this->assertTrue(str_contains($response->getContent(), 'dr-duplicate'));
            $this->assertTrue($response->viewData('stepOneValues')['vehicle_description'] === 'FJ');
            $existing->forceFill(['status' => 'rejected', 'reviewed_by' => User::factory()->admin()->create()->id,
                'reviewed_at' => now(), 'rejection_reason' => 'Synthetic rejection'])->save();
        }
        $this->submit($dealer)->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
    }

    public function test_validation_honeypot_and_system_fields_cannot_create_a_usable_draft(): void
    {
        $dealer = Dealer::factory()->create();
        foreach ([['customer_name' => ''], ['phone' => []], ['vehicle_description' => 'Other'], ['website' => 'bot'], ['dealer_id' => 999]] as $invalid) {
            $this->submit($dealer, $invalid)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
            $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertRedirect();
            $this->assertFalse(session()->has('_old_input'));
        }
    }

    public function test_step_one_has_specific_inline_errors_without_summary_and_retains_invalid_text(): void
    {
        $dealer = Dealer::factory()->create();
        $this->submit($dealer, ['customer_name' => '', 'phone' => '', 'vehicle_description' => ''])->assertRedirect();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $this->assertSame([
            'customer_name' => ['Vui lòng nhập họ tên.'], 'phone' => ['Vui lòng nhập số điện thoại.'],
            'vehicle_description' => ['Vui lòng chọn dòng xe.'],
        ], $response->viewData('stepOneErrors'));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//form[@data-step-one]//*[@data-error-summary]')->length);
        $this->assertSame(6, $xpath->query('//form[@data-step-one]//input[@aria-invalid="true"]')->length);
        $this->submit($dealer, ['phone' => 'invalid'])->assertRedirect();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $this->assertSame(['phone' => ['Số điện thoại không hợp lệ.']], $response->viewData('stepOneErrors'));
        $this->assertTrue($response->viewData('stepOneValues')['phone'] === 'invalid');
        $this->assertTrue($response->viewData('stepOneValues')['customer_name'] === $this->payload()['customer_name']);
        $this->assertFalse(session()->has('_old_input'));
    }

    public function test_throttle_is_shared_across_dealers_and_invalid_attempts(): void
    {
        $dealer = Dealer::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->submit($dealer, ['customer_name' => ''])->assertRedirect();
        }
        $this->submit(Dealer::factory()->create())->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_csrf_is_required_and_valid_token_is_accepted(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $dealer = Dealer::factory()->create();
        $this->submit($dealer)->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token']);
        $this->submit($dealer, ['_token' => 'test-csrf-token'])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
    }

    public function test_existing_routes_and_sales_vehicle_choices_are_unchanged(): void
    {
        $this->assertSame('App\\Http\\Controllers\\DealerRegistrationController@store', Route::getRoutes()->getByName('dealers.registrations.store')->getActionName());
        $this->get('/register')->assertOk();
        $this->get('/sales/dashboard')->assertRedirect('/login');
        $this->get('/admin/submissions')->assertRedirect('/login');
        $this->assertSame(['Veloz', 'Hilux', 'Fortuner', 'FJ'], DealerRegistrationDraft::VEHICLES);
        $this->assertArrayNotHasKey('fj', config('vehicles.toyota_models'));
        $this->assertArrayHasKey('camry', config('vehicles.toyota_models'));
    }

    public function test_all_four_dealer_vehicles_are_accepted(): void
    {
        $dealer = Dealer::factory()->create();
        foreach (DealerRegistrationDraft::VEHICLES as $vehicle) {
            $this->submit($dealer, ['vehicle_description' => $vehicle])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        }
    }

    public function test_legacy_fallback_still_creates_pending_and_blocks_new_draft_globally(): void
    {
        $dealer = Dealer::factory()->create();
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0]['code'];
        $count = CustomerSubmission::count();
        $this->postJson(route('dealers.registrations.store', $dealer->slug), $this->payload([
            'address_line' => 'SYNTHETIC_ADDRESS', 'province_code' => $province,
            'ward_code' => $directory->wards($province)[0]['code'], 'consent' => '1',
        ]))->assertCreated();
        $this->assertSame($count + 1, CustomerSubmission::count());
        $other = Dealer::factory()->create();
        $this->submit($other)->assertRedirect(route('dealers.profile', $other->slug).'#registration');
        $response = $this->get(route('dealers.profile', $other->slug))->assertOk();
        $this->assertTrue($response->viewData('duplicatePhone'));
    }
}
