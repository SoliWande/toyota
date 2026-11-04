<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\DealerRegistrationDraft;
use App\Services\RegistrationLookup;
use App\Services\RegistrationLookupStateResolver;
use App\Services\RegistrationPhone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class RegistrationLookupTest extends TestCase
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
        $this->cachePath = storage_path('framework/testing/lookup-'.bin2hex(random_bytes(8)));
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

    private function record(string $status = 'pending', int $number = 91): CustomerSubmission
    {
        $factory = CustomerSubmission::factory();
        if ($status !== 'pending') {
            $factory = $factory->$status();
        }

        return $factory->create(['customer_name' => 'SYNTHETIC LOOKUP OWNER',
            'phone' => '09900000'.$number, 'license_plate' => '51K900'.$number,
            'vehicle_model' => 'hilux', 'address_line' => 'SYNTHETIC_PRIVATE_ADDRESS',
            'evidence_image_path' => 'SYNTHETIC_PRIVATE_EVIDENCE.png']);
    }

    private function payload(CustomerSubmission $record): array
    {
        return ['phone' => RegistrationPhone::normalize($record->phone), 'license_plate' => $record->license_plate];
    }

    public function test_get_is_private_post_form_with_no_result_or_query_pii_reflection(): void
    {
        $dealer = Dealer::factory()->create();
        $this->get(route('dealers.lookup.show', $dealer->slug).'?phone=SYNTHETIC_QUERY_VALUE')->assertOk()->assertViewIs('dealers.registration.lookup')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('SYNTHETIC_QUERY_VALUE')
            ->assertSee('method="POST"', false)->assertSee('name="_token"', false)
            ->assertDontSee('data-lookup-kind=', false);
    }

    public static function missingWorkflowTables(): array
    {
        return [['submission_verification_states'], ['submission_verification_audits']];
    }

    #[DataProvider('missingWorkflowTables')]
    public function test_lookup_without_workflow_schema_returns_neutral_evidence_not_a_registration_error(string $missing): void
    {
        $record = $this->record();
        $before = $record->refresh()->getRawOriginal();
        Schema::shouldReceive('hasTable')->with('submission_verification_states')
            ->andReturn($missing !== 'submission_verification_states');
        Schema::shouldReceive('hasTable')->with('submission_verification_audits')
            ->andReturn($missing !== 'submission_verification_audits');
        $response = $this->post(route('dealers.lookup.store', $record->dealer->slug), $this->payload($record))
            ->assertOk()->assertViewIs('dealers.registration.lookup')
            ->assertViewHas('result', fn ($result) => $result['kind'] === 'found')
            ->assertSee('Đã tìm thấy đăng ký')->assertDontSee('Chưa thể gửi đăng ký')
            ->assertDontSee('data-lookup-kind="member"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse($response->headers->has('Location'));
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
    }

    public static function lookupFailureSources(): array
    {
        return [['query'], ['resolver']];
    }

    #[DataProvider('lookupFailureSources')]
    public function test_service_failure_keeps_lookup_view_status_and_private_headers_without_false_result(string $source): void
    {
        $record = $this->record();
        if ($source === 'query') {
            DB::listen(function ($query) {
                if (str_contains($query->sql, 'customer_submissions')) {
                    throw new \RuntimeException('SYNTHETIC_INTERNAL_ERROR');
                }
            });
        } else {
            $this->app->instance(RegistrationLookupStateResolver::class, new class
            {
                public function resolve(...$arguments): array
                {
                    throw new \RuntimeException('SYNTHETIC_INTERNAL_ERROR');
                }
            });
        }
        $response = $this->post(route('dealers.lookup.store', $record->dealer->slug), $this->payload($record))
            ->assertStatus(500)->assertViewIs('dealers.registration.lookup')
            ->assertSee('Chưa thể tra cứu. Vui lòng kiểm tra thông tin hoặc thử lại sau.')
            ->assertDontSee('Chưa thể gửi đăng ký')->assertDontSee('Quay lại đăng ký')
            ->assertDontSee('SYNTHETIC_INTERNAL_ERROR')->assertDontSee('data-lookup-kind=', false)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertFalse($response->headers->has('Location'));
        $this->assertEmpty(session()->getOldInput());
    }

    public static function statuses(): array
    {
        return ['pending' => ['pending'], 'approved' => ['approved'], 'rejected' => ['rejected']];
    }

    #[DataProvider('statuses')]
    public function test_matching_pair_returns_only_masked_dto_without_facebook_inference_or_writes(string $status): void
    {
        $record = $this->record($status);
        $before = $record->refresh()->getRawOriginal();
        $reviews = DB::table('moderation_reviews')->count();
        $response = $this->post(route('dealers.lookup.store', $record->dealer->slug), $this->payload($record))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $dto = $response->viewData('result');
        $this->assertSame(['kind', 'masked_name', 'vehicle'], array_keys($dto));
        $this->assertSame('found', $dto['kind']);
        $this->assertSame('Hilux', $dto['vehicle']);
        foreach ([$record->customer_name, $record->facebook_url, $record->address_line,
            $record->evidence_image_path, $record->sales->email] as $private) {
            $this->assertFalse(str_contains($response->getContent(), $private), 'Private field appeared in lookup HTML.');
        }
        $response->assertSee('Đã tìm thấy đăng ký')->assertDontSee('Bạn đã là thành viên chính thức')
            ->assertDontSee('Chờ vào group')->assertDontSee('Đã gửi yêu cầu vào group')->assertDontSee('48 giờ');
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $this->assertSame($reviews, DB::table('moderation_reviews')->count());
        $this->assertSame(0, DB::table('dealer_registration_receipts')->count());
        $this->assertEmpty(session()->getOldInput());
        if ($status === 'approved') {
            $this->assertNotNull($record->fresh()->approved_facebook_identity);
        }
        $this->get(route('dealers.lookup.show', $record->dealer->slug))->assertOk()->assertViewHas('result', null);
    }

    public function test_no_match_wrong_phone_and_wrong_plate_are_indistinguishable(): void
    {
        $record = $this->record();
        foreach ([['phone' => '0990000092', 'license_plate' => $record->license_plate],
            ['phone' => $record->phone, 'license_plate' => '51K90092'],
            ['phone' => '0990000092', 'license_plate' => '51K90092']] as $payload) {
            $response = $this->post(route('dealers.lookup.store', $record->dealer->slug), $payload)->assertOk()->assertViewIs('dealers.registration.lookup');
            $this->assertSame(['kind' => 'not_found'], $response->viewData('result'));
            $response->assertSee('Không tìm thấy đăng ký');
            $this->assertFalse(str_contains($response->getContent(), $record->customer_name));
        }
    }

    public function test_historical_canonical_formats_match_but_multiple_matches_never_choose_a_winner(): void
    {
        $first = $this->record();
        DB::table('customer_submissions')->where('id', $first->id)
            ->update(['phone' => '+84 990 000 091', 'license_plate' => '51k - 900.91']);
        $payload = ['phone' => '0084 (990) 000-091', 'license_plate' => '51K90091'];
        $this->assertSame('found', $this->post(route('dealers.lookup.store', $first->dealer->slug), $payload)->assertOk()->viewData('result')['kind']);
        $second = $this->record('rejected', 92);
        // Historical conflict fixture only; never run this SQL in application writers.
        DB::table('customer_submissions')->where('id', $second->id)->update(['phone' => $first->phone, 'license_plate' => $first->license_plate]);
        $response = $this->post(route('dealers.lookup.store', $first->dealer->slug), $payload)->assertOk();
        $this->assertSame(['kind' => 'not_found'], $response->viewData('result'));
    }

    public function test_inconsistent_phone_aliases_cannot_identify_a_record(): void
    {
        $record = $this->record();
        DB::table('customer_submissions')->where('id', $record->id)->update(['phone_normalized' => '0990000092']);
        $this->assertSame(['kind' => 'not_found'], app(RegistrationLookup::class)->find($record->phone, $record->license_plate));
    }

    public function test_validation_is_inline_retains_bounded_values_and_never_flashes_input(): void
    {
        $dealer = Dealer::factory()->create();
        $response = $this->post(route('dealers.lookup.store', $dealer->slug), [
            'phone' => 'SYNTHETIC_INVALID_PHONE', 'license_plate' => '',
        ])->assertStatus(422)->assertHeader('Cache-Control', 'no-store, private');
        $response->assertViewIs('dealers.registration.lookup')->assertDontSee('Chưa thể gửi đăng ký')
            ->assertSee('Số điện thoại không hợp lệ.')->assertSee('Vui lòng nhập biển số xe.')
            ->assertSee('aria-invalid="true"', false)->assertSee('aria-describedby="lookup-phone-error"', false)
            ->assertDontSee('Còn 2 trường');
        $this->assertTrue($response->viewData('values')['phone'] === 'SYNTHETIC_INVALID_PHONE');
        $this->assertEmpty(session()->getOldInput());
        $this->assertFalse(session()->has('errors'));
    }

    public function test_missing_malformed_and_system_fields_do_not_leak_values(): void
    {
        $dealer = Dealer::factory()->create();
        $this->post(route('dealers.lookup.store', $dealer->slug), [])->assertStatus(422)
            ->assertSee('Vui lòng nhập số điện thoại.')->assertSee('Vui lòng nhập biển số xe.');
        foreach ([['phone' => ['SYNTHETIC'], 'license_plate' => []],
            ['phone' => '0990000091', 'license_plate' => '51K90091', 'website' => 'SYNTHETIC_BOT'],
            ['phone' => '0990000091', 'license_plate' => '51K90091', 'status' => 'approved']] as $payload) {
            $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertStatus(422)->assertDontSee('data-lookup-kind=', false);
        }
        $this->assertEmpty(session()->getOldInput());
    }

    public function test_inactive_dealer_lookup_is_read_only_and_does_not_offer_registration(): void
    {
        $record = $this->record();
        $dealer = $record->dealer;
        $dealer->update(['is_active' => false]);
        $this->post(route('dealers.lookup.store', $dealer->slug), $this->payload($record))->assertOk()
            ->assertDontSee('Đăng ký ngay')->assertViewHas('result', fn ($value) => $value['kind'] === 'found');
        $this->assertSame('pending', $record->fresh()->status->value);
    }

    public function test_dealer_qr_registration_is_searchable_across_dealer_context_without_private_details(): void
    {
        $dealer = Dealer::factory()->create();
        $record = CustomerSubmission::factory()->create(['source' => 'dealer_qr', 'sales_id' => null,
            'dealer_id' => $dealer->id, 'phone' => '0990000091', 'phone_normalized' => '0990000091',
            'license_plate' => '51K90091', 'vehicle_description' => 'FJ', 'customer_name' => 'SYNTHETIC QR OWNER',
            'address_line' => 'SYNTHETIC_PRIVATE_ADDRESS', 'province_code' => '79', 'ward_code' => '00001',
            'consented_at' => now(), 'consent_version' => 'synthetic-test']);
        $other = Dealer::factory()->create();
        $response = $this->post(route('dealers.lookup.store', $other->slug), $this->payload($record))->assertOk();
        $this->assertSame('FJ', $response->viewData('result')['vehicle']);
        $this->assertFalse(str_contains($response->getContent(), $record->customer_name));
        $this->assertFalse(str_contains($response->getContent(), $record->address_line));
        $this->assertSame($dealer->id, $record->fresh()->dealer_id);
        $this->assertNull($record->fresh()->sales_id);
    }

    public function test_legacy_record_without_plate_is_not_disclosed_by_matching_phone(): void
    {
        $record = $this->record();
        $record->update(['license_plate' => null]);
        $this->post(route('dealers.lookup.store', $record->dealer->slug), ['phone' => $record->phone, 'license_plate' => '51K90091'])
            ->assertOk()->assertViewHas('result', ['kind' => 'not_found'])->assertDontSee('data-lookup-progress', false);
    }

    public function test_rate_limits_ip_and_normalized_phone_across_ips_and_dealers(): void
    {
        $a = Dealer::factory()->create();
        $b = Dealer::factory()->create();
        $payload = ['phone' => '0990000091', 'license_plate' => '51K90091'];
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$i])
                ->post(route('dealers.lookup.store', $a->slug), $payload)->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
            ->post(route('dealers.lookup.store', $b->slug), array_replace($payload, ['phone' => '+84 990 000 091']))
            ->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Retry-After');
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])->post(route('dealers.lookup.store', $a->slug),
                array_replace($payload, ['phone' => '098000000'.$i]))->assertOk();
        }
        $this->post(route('dealers.lookup.store', $a->slug), ['phone' => '0980000006', 'license_plate' => '51K90091'])
            ->assertStatus(429);
    }

    public function test_csrf_missing_wrong_and_valid_tokens_with_real_middleware(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $dealer = Dealer::factory()->create();
        $token = session()->token();
        $payload = ['phone' => '0990000091', 'license_plate' => '51K90091'];
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload + ['_token' => 'wrong'])->assertStatus(419);
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload + ['_token' => $token])->assertOk();
    }

    public function test_d11_requires_own_live_draft_with_matching_saved_step_two_plate(): void
    {
        $dealer = Dealer::factory()->create();
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        $drafts = app(DealerRegistrationDraft::class);
        $drafts->write($request, $dealer, ['customer_name' => 'SYNTHETIC DRAFT OWNER',
            'phone' => '0990000091', 'vehicle_description' => 'Hilux']);
        $draft = $drafts->read($request, $dealer);
        $draft['step_two'] = ['values' => ['license_plate' => '51K90091']];
        $drafts->update($request, $dealer, $draft);
        $payload = ['phone' => '0990000091', 'license_plate' => '51K-900.91'];
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertOk()
            ->assertViewHas('result', fn ($result) => $result['kind'] === 'draft')->assertSee('Tiếp tục đăng ký')
            ->assertSee('data-step="registration" data-state="current"', false)
            ->assertSee('data-step="group" data-state="pending"', false)
            ->assertSee('data-step="membership" data-state="pending"', false);
        $this->assertSame(0, CustomerSubmission::count());
        $other = Dealer::factory()->create();
        $this->post(route('dealers.lookup.store', $other->slug), $payload)->assertViewHas('result', ['kind' => 'not_found']);
        session()->migrate(true);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertViewHas('result', ['kind' => 'not_found']);
    }

    public function test_bare_or_expired_draft_cannot_be_discovered_by_phone(): void
    {
        $dealer = Dealer::factory()->create();
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        app(DealerRegistrationDraft::class)->write($request, $dealer, ['customer_name' => 'SYNTHETIC DRAFT OWNER',
            'phone' => '0990000091', 'vehicle_description' => 'Hilux']);
        $payload = ['phone' => '0990000091', 'license_plate' => '51K90091'];
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertViewHas('result', ['kind' => 'not_found']);
        $drafts = app(DealerRegistrationDraft::class);
        $draft = $drafts->read($request, $dealer);
        $expires = $draft['expires_at'];
        $draft['step_two'] = ['values' => ['license_plate' => '51K90091']];
        $drafts->update($request, $dealer, $draft);
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)
            ->assertViewHas('result', fn ($result) => $result['kind'] === 'draft');
        $this->assertSame($expires, $drafts->read($request, $dealer)['expires_at']);
        $this->travel(31)->minutes();
        $this->post(route('dealers.lookup.store', $dealer->slug), $payload)->assertViewHas('result', ['kind' => 'not_found']);
    }
}
