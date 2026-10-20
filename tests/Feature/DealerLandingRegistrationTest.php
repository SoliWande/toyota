<?php

namespace Tests\Feature;

use App\Actions\ApproveCustomerSubmission;
use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\AwardResults;
use App\Services\DealerProfile;
use App\Services\LeaderboardService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerLandingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];

        return array_replace([
            'customer_name' => 'PRIVATE_QR_CUSTOMER', 'phone' => '0901234567',
            'address_line' => 'PRIVATE_QR_ADDRESS', 'province_code' => $province['code'],
            'ward_code' => $directory->wards($province['code'])[0]['code'],
            'vehicle_description' => 'PRIVATE_QR_VEHICLE', 'consent' => '1',
        ], $overrides);
    }

    private function intake(Dealer $dealer, array $overrides = [])
    {
        return $this->postJson(route('dealers.registrations.store', ['slug' => $dealer->slug]), $this->payload($overrides));
    }

    private function register(?Dealer $dealer = null): CustomerSubmission
    {
        $dealer ??= Dealer::factory()->create();
        $this->intake($dealer)->assertCreated();

        return CustomerSubmission::where('source', 'dealer_qr')->latest('id')->firstOrFail();
    }

    public function test_landing_form_uses_correct_dealer_and_stable_existing_qr(): void
    {
        $dealer = Dealer::factory()->create(['phone' => 'PUBLIC_DEALER_PHONE']);
        $url = app(DealerProfile::class)->url($dealer);
        $qr = $this->get(route('dealers.qr', ['dealer' => $dealer, 'download' => 1]))->assertOk()->getContent();
        $dealer->update(['name' => 'New Dealer Name', 'address' => 'New Dealer Address']);
        $this->get($url)->assertOk()->assertSee('New Dealer Name')->assertSee('PUBLIC_DEALER_PHONE')
            ->assertSee('name="consent"', false)->assertSee('name="vehicle_description"', false)
            ->assertSee(route('dealers.registrations.store', ['slug' => $dealer->slug]))
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($url, app(DealerProfile::class)->url($dealer->fresh()));
        $this->assertSame($qr, $this->get(route('dealers.qr', $dealer))->assertOk()->getContent());
    }

    public function test_intake_creates_pending_without_sales_facebook_evidence_or_points(): void
    {
        $dealer = Dealer::factory()->create();
        $accounts = User::count();
        $submission = $this->register($dealer);
        $this->assertSame($dealer->id, $submission->dealer_id);
        $this->assertNull($submission->sales_id);
        $this->assertNull($submission->facebook_url);
        $this->assertNull($submission->approved_facebook_identity);
        $this->assertNull($submission->evidence_image_path);
        $this->assertSame('pending', $submission->status->value);
        $this->assertSame(config('dealer.consent_version'), $submission->consent_version);
        $this->assertNotNull($submission->consented_at);
        $this->assertSame($accounts, User::count());
        $this->assertSame(0, app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->count());
        $this->assertSame(0, app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->count());
    }

    public static function invalidFields(): array
    {
        return [
            'name missing' => ['customer_name', ''], 'name array' => ['customer_name', ['not-a-string']],
            'phone missing' => ['phone', ''], 'phone invalid' => ['phone', 'PRIVATE_INVALID_PHONE'],
            'address missing' => ['address_line', ''], 'province missing' => ['province_code', ''],
            'province invalid' => ['province_code', '99'], 'ward missing' => ['ward_code', ''],
            'ward invalid' => ['ward_code', '99999'], 'vehicle missing' => ['vehicle_description', ''],
            'vehicle long' => ['vehicle_description', 'x'.str_repeat('a', 255)],
            'consent missing' => ['consent', null], 'consent refused' => ['consent', '0'],
            'honeypot' => ['website', 'https://spam.example.test'],
            'dealer spoof' => ['dealer_id', 999], 'sales spoof' => ['sales_id', 999],
            'source spoof' => ['source', 'sales'], 'approved spoof' => ['status', 'approved'],
            'reviewer spoof' => ['reviewed_by', 1], 'points spoof' => ['points', 999],
            'identity spoof' => ['facebook_url', 'https://facebook.com/spoofed.profile'],
            'consent time spoof' => ['consented_at', '2020-01-01'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_or_forged_input_is_rejected_without_echoing_personal_data(string $field, mixed $value): void
    {
        $response = $this->intake(Dealer::factory()->create(), [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        foreach (['PRIVATE_QR_CUSTOMER', '0901234567', 'PRIVATE_QR_ADDRESS', 'PRIVATE_INVALID_PHONE', 'PRIVATE_QR_VEHICLE'] as $private) {
            $response->assertDontSee($private);
        }
        $this->assertSame(0, CustomerSubmission::count());
    }

    public function test_ward_must_belong_to_selected_province_and_names_are_server_supplied(): void
    {
        $directory = app(AdministrativeDivisions::class);
        $otherProvince = $directory->provinces()[1];
        $dealer = Dealer::factory()->create();
        $this->intake($dealer, ['ward_code' => $directory->wards($otherProvince['code'])[0]['code']])->assertUnprocessable();
        $submission = $this->register($dealer);
        $this->assertSame($directory->province($submission->province_code)['name'], $submission->province_name);
        $this->assertSame($directory->ward($submission->province_code, $submission->ward_code)['name'], $submission->ward_name);
        $this->getJson(route('dealers.address.wards', ['slug' => $dealer->slug, 'province' => $submission->province_code]))
            ->assertOk()->assertExactJson(['wards' => $directory->wards($submission->province_code)]);
    }

    public function test_inactive_and_unknown_dealers_cannot_receive_registrations(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => false]);
        $this->intake($dealer)->assertNotFound();
        $this->postJson('/unknown-dealer/registrations', $this->payload())->assertNotFound();
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertDontSee('name="consent"', false);
        $this->assertSame(0, CustomerSubmission::count());
        $this->get('/logout')->assertStatus(405);
    }

    public function test_phone_variants_share_spam_cooldown_and_do_not_create_repeat_rows(): void
    {
        $dealer = Dealer::factory()->create();
        $this->intake($dealer, ['phone' => '+84 901 234 567'])->assertCreated();
        $this->intake($dealer, ['phone' => '0901.234.567'])->assertStatus(429)->assertDontSee('0901234567');
        $this->assertSame(1, CustomerSubmission::count());
        $this->assertSame('0901234567', CustomerSubmission::sole()->phone_normalized);
    }

    public function test_ip_rate_limit_covers_invalid_requests_and_different_dealers(): void
    {
        $dealer = Dealer::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->intake($dealer, ['consent' => '0'])->assertUnprocessable();
        }
        $this->intake(Dealer::factory()->create())->assertStatus(429);
        $this->assertSame(0, CustomerSubmission::count());
    }

    public function test_admin_approval_requires_verified_facebook_then_scores_dealer_only(): void
    {
        $manager = User::factory()->dealer()->create();
        $submission = $this->register($manager->dealer);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.submissions.index'))->assertOk()->assertSee('Khách tự đăng ký qua QR');
        $this->get(route('admin.submissions.show', $submission))->assertOk()->assertSee('name="verified_facebook_url"', false);
        $this->postJson(route('admin.submissions.approve', $submission))->assertUnprocessable()->assertJsonValidationErrors('verified_facebook_url');
        $this->postJson(route('admin.submissions.approve', $submission), ['verified_facebook_url' => 'https://evil.example.test/identity'])
            ->assertUnprocessable()->assertJsonValidationErrors('verified_facebook_url');
        $this->post(route('admin.submissions.approve', $submission), ['verified_facebook_url' => 'https://facebook.com/verified.qr.customer'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $submission->fresh()->status->value);
        $this->assertSame(1, $submission->moderationReviews()->count());
        $this->assertNull($submission->fresh()->sales_id);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->first()->score);
        $this->assertSame(0, app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->count());
        $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()->assertViewHas('standings', fn ($items) => (int) $items['all']->score === 1);
    }

    public function test_existing_approved_identity_cannot_be_approved_again_via_qr(): void
    {
        $existing = CustomerSubmission::factory()->approved()->create();
        $submission = $this->register();
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.submissions.approve', $submission), [
            'verified_facebook_url' => $existing->facebook_url,
        ])->assertUnprocessable()->assertJsonValidationErrors('duplicate');
        $this->assertSame('pending', $submission->fresh()->status->value);
        $this->assertNull($submission->fresh()->facebook_url);
        $this->assertSame(0, $submission->moderationReviews()->count());
    }

    public function test_approved_qr_identity_blocks_subsequent_sales_approval(): void
    {
        $qr = $this->register();
        $admin = User::factory()->admin()->create();
        $url = 'https://facebook.com/shared.verified.identity';
        app(ApproveCustomerSubmission::class)->execute($admin, $qr, null, $url);
        $salesSubmission = CustomerSubmission::factory()->create(['facebook_url' => $url]);
        $this->actingAs($admin)->get(route('admin.submissions.show', $salesSubmission))->assertOk()->assertSee('Khách tự đăng ký qua QR');
        $this->postJson(route('admin.submissions.approve', $salesSubmission))->assertUnprocessable()->assertJsonValidationErrors('duplicate');
        $this->assertSame('pending', $salesSubmission->fresh()->status->value);
    }

    public function test_admin_can_reject_qr_without_facebook_and_no_points_are_awarded(): void
    {
        $submission = $this->register();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.reject', $submission), ['rejection_reason' => 'Unable to verify'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $submission->fresh()->status->value);
        $this->assertSame(1, $submission->moderationReviews()->count());
        $this->assertSame(0, app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->count());
    }

    public function test_internal_access_is_scoped_and_public_never_returns_customer_records(): void
    {
        $manager = User::factory()->dealer()->create();
        $other = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $submission = $this->register($manager->dealer);
        $this->getJson(route('dealer.customers.show', $submission))->assertUnauthorized();
        $this->actingAs($manager)->get(route('dealer.customers.index'))->assertOk()->assertSee($submission->customer_name)->assertSee('Khách tự đăng ký qua QR');
        $this->get(route('dealer.customers.show', $submission))->assertOk()->assertSee($submission->vehicle_description);
        $this->postJson(route('admin.submissions.approve', $submission))->assertForbidden();
        $this->actingAs($other)->get(route('dealer.customers.show', $submission))->assertNotFound();
        $this->actingAs($sales)->get(route('sales.submissions.show', $submission))->assertForbidden();
        $this->get(route('submissions.evidence', $submission))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dealers.show', $manager->dealer_id))->assertOk()->assertSee($submission->customer_name);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('logout'));
        foreach ([false, true] as $json) {
            $url = app(DealerProfile::class)->url($manager->dealer);
            $response = $json ? $this->getJson($url) : $this->get($url);
            $response->assertOk()->assertDontSee($submission->customer_name)->assertDontSee($submission->phone)
                ->assertDontSee($submission->address_line)->assertDontSee($submission->vehicle_description);
        }
        $this->getJson('/'.$manager->dealer->slug.'/registrations')->assertStatus(405);
    }

    public function test_html_redirects_and_validation_do_not_store_or_echo_personal_input(): void
    {
        $dealer = Dealer::factory()->create();
        $url = route('dealers.registrations.store', ['slug' => $dealer->slug]);
        $response = $this->post($url, $this->payload(['consent' => '0']))->assertSessionHasErrors('consent');
        $this->assertStringNotContainsString('PRIVATE_', $response->headers->get('Location'));
        $this->assertEmpty(session()->getOldInput());
        $this->get($response->headers->get('Location'))->assertOk()->assertDontSee('PRIVATE_QR_ADDRESS')->assertDontSee('0901234567');
        $response = $this->post($url, $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(route('dealers.profile', ['slug' => $dealer->slug]).'#registration', $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Đã nhận đăng ký')->assertDontSee('PRIVATE_QR_CUSTOMER');
    }

    public function test_public_address_fields_never_restore_private_sales_old_input(): void
    {
        $dealer = Dealer::factory()->create();
        $this->withSession(['_old_input' => $this->payload()])->get(app(DealerProfile::class)->url($dealer))
            ->assertOk()->assertDontSee('PRIVATE_QR_ADDRESS');
    }

    public function test_database_rejects_approval_without_identity_even_when_model_is_bypassed(): void
    {
        $submission = $this->register();
        $admin = User::factory()->admin()->create();
        $this->expectException(QueryException::class);
        DB::table('customer_submissions')->where('id', $submission->id)->update([
            'status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
    }

    public function test_qr_source_sales_and_dealer_attribution_cannot_change(): void
    {
        $submission = $this->register();
        $this->expectException(\LogicException::class);
        $submission->forceFill(['source' => 'sales', 'sales_id' => User::factory()->active()->create()->id])->save();
    }

    public function test_database_errors_never_expose_personal_data_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $fail = true;
        CustomerSubmission::creating(function ($submission) use (&$fail) {
            if ($fail && $submission->source === 'dealer_qr') {
                throw new \RuntimeException('PRIVATE_QR_CUSTOMER PRIVATE_QR_ADDRESS');
            }
        });
        $dealer = Dealer::factory()->create();
        $this->intake($dealer)->assertStatus(500)->assertDontSee('PRIVATE_')->assertDontSee('trace');
        $fail = false;
        $this->intake($dealer)->assertCreated();
    }

    public function test_public_post_requires_real_csrf_token(): void
    {
        $dealer = Dealer::factory()->create();
        app()->detectEnvironment(fn () => 'local');
        $this->withSession(['_token' => 'real-csrf-token']);
        $this->intake($dealer)->assertStatus(419)->assertDontSee('PRIVATE_QR_CUSTOMER');
        $this->intake($dealer, ['_token' => 'real-csrf-token'])->assertCreated();
    }

    public function test_database_unique_identity_guards_qr_even_when_duplicate_finder_is_bypassed(): void
    {
        $qr = $this->register();
        $admin = User::factory()->admin()->create();
        $existing = CustomerSubmission::factory()->approved()->create();
        $this->expectException(QueryException::class);
        DB::table('customer_submissions')->where('id', $qr->id)->update([
            'facebook_url' => $existing->facebook_url, 'facebook_url_normalized' => $existing->facebook_url_normalized,
            'status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
    }

    public function test_qr_counts_in_dealer_awards_but_never_sales_awards(): void
    {
        $qr = $this->register();
        app(ApproveCustomerSubmission::class)->execute(User::factory()->admin()->create(), $qr, null, 'https://facebook.com/award.qr.customer');
        $award = Award::factory()->weekly()->create();
        $results = app(AwardResults::class)->preview($award);
        $this->assertCount(0, $results['sales']);
        $this->assertCount(1, $results['dealers']);
        $this->assertSame($qr->dealer_id, $results['dealers'][0]['dealer_id']);
        $this->assertSame(1, $results['dealers'][0]['score']);
    }

    public function test_browser_fixture_uses_test_database_only(): void
    {
        TestDatabase::assertIsolated();
        $dealer = Dealer::factory()->create(['name' => 'Toyota An Sương · Đăng ký chiến dịch', 'phone' => '19007339', 'address' => '382 Lê Quang Đạo, TP. Hồ Chí Minh']);
        $url = app(DealerProfile::class)->url($dealer);
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];
        $wardsUrl = route('dealers.address.wards', ['slug' => $dealer->slug, 'province' => $province['code']]);
        $output = base_path('.laravel-tools/dealer-landing/qa');
        File::ensureDirectoryExists($output);
        file_put_contents($output.'/page.html', $this->get($url)->assertOk()->getContent());
        file_put_contents($output.'/qr.png', app(DealerProfile::class)->qr($dealer));
        file_put_contents($output.'/wards.json', $this->getJson($wardsUrl)->assertOk()->getContent());
        file_put_contents($output.'/manifest.json', json_encode([
            'url' => $url, 'dealerId' => $dealer->id, 'province' => $province['code'],
            'ward' => $directory->wards($province['code'])[0]['code'], 'wardsUrl' => $wardsUrl,
            'postUrl' => route('dealers.registrations.store', ['slug' => $dealer->slug]),
        ], JSON_PRETTY_PRINT));
    }
}
