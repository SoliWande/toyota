<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\LeaderboardService;
use App\Services\SubmissionIntake;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DealerRegistrationFinalTest extends TestCase
{
    use DatabaseTransactions;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('f', 32)), 'app.debug' => false]);
        $this->cachePath = storage_path('framework/testing/final-'.bin2hex(random_bytes(8)));
        config(['cache.stores.file.path' => $this->cachePath]);
        Cache::forgetDriver('file');
        $this->withoutVite();
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
    }

    protected function tearDown(): void
    {
        if (isset($this->cachePath)) {
            File::deleteDirectory($this->cachePath);
        }
        parent::tearDown();
    }

    private function draft(Dealer $dealer, string $phone = '0990000007'): string
    {
        $this->post(route('dealers.registrations.step-one', $dealer->slug), [
            'customer_name' => 'SYNTHETIC_FINAL_PERSON', 'phone' => $phone, 'vehicle_description' => 'FJ',
        ])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));

        return $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->viewData('draftId');
    }

    private function payload(string $id, array $overrides = []): array
    {
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0]['code'];

        return array_replace(['_flow' => 'two-step', '_draft' => $id, 'province_code' => $province,
            'ward_code' => $directory->wards($province)[0]['code'], 'address_line' => 'SYNTHETIC_FINAL_ADDRESS',
            'license_plate' => '51k - 987.65', 'first_registration_year' => '2024', 'odo' => '',
            'facebook_url' => 'https://www.facebook.com/synthetic.final.customer', 'consent' => '1'], $overrides);
    }

    private function finish(Dealer $dealer, string $id, array $overrides = [])
    {
        return $this->post(route('dealers.registrations.store', $dealer->slug), $this->payload($id, $overrides));
    }

    public function test_pending_attribution_odo_confirmation_and_idempotent_retry(): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $this->finish($dealer, $id, ['odo' => '25000'])->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $this->assertSame(1, CustomerSubmission::count());
        $record = CustomerSubmission::sole();
        $this->assertSame('pending', $record->status->value);
        $this->assertSame('dealer_qr', $record->source);
        $this->assertSame($dealer->id, $record->dealer_id);
        $this->assertNull($record->sales_id);
        $this->assertNull($record->approved_facebook_identity);
        $this->assertNull($record->reviewed_by);
        $this->assertSame(25000, $record->odo);
        $this->assertSame('FJ', $record->vehicle_description);
        $this->assertTrue($record->license_plate === '51K98765');
        $this->assertSame(0, app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->count());
        $this->assertSame(0, $record->moderationReviews()->count());
        $this->finish($dealer, $id)->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        for ($i = 0; $i < 2; $i++) {
            $response = $this->get(route('dealers.registrations.success', $dealer->slug))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue(str_contains($response->getContent(), 'href="'.config('dealer-registration.group_url').'"'));
            $this->assertTrue(str_contains($response->getContent(), 'aria-current="step"'));
            $this->assertTrue($response->viewData('confirmation')['phone'] === $record->phone);
            $this->assertArrayNotHasKey('id', $response->viewData('confirmation'));
        }
        $this->assertSame(1, CustomerSubmission::count());
        $this->assertSame(1, DB::table('dealer_registration_receipts')->count());
        $this->assertFalse(str_contains(DB::table('dealer_registration_receipts')->value('confirmation'), $record->phone));
        $this->assertFalse(session()->has('_old_input'));
        $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertRedirect();
    }

    public function test_odo_null_and_step_one_spoof_is_rejected(): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $this->finish($dealer, $id, ['customer_name' => 'FORGED', 'phone' => '0990000008', 'vehicle_description' => 'Hilux'])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $this->assertSame(0, CustomerSubmission::count());
        $this->finish($dealer, $id)->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $this->assertNull(CustomerSubmission::sole()->odo);
        $this->assertTrue(CustomerSubmission::sole()->customer_name === 'SYNTHETIC_FINAL_PERSON');
    }

    public static function invalidFields(): array
    {
        return array_map(fn ($pair) => $pair, [
            ['province_code', ''], ['province_code', '99'], ['ward_code', ''], ['ward_code', '99999'],
            ['address_line', ''], ['license_plate', ''], ['license_plate', '!bad'],
            ['first_registration_year', ''], ['first_registration_year', '9999'], ['odo', '-1'], ['odo', '1.5'],
            ['odo', '4294967296'], ['facebook_url', ''], ['facebook_url', 'https://evil.test/profile'], ['consent', '0'],
        ]);
    }

    #[DataProvider('invalidFields')]
    public function test_field_validation_retains_data_without_old_input(string $field, string $value): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $this->finish($dealer, $id, [$field => $value])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $response = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk();
        $this->assertArrayHasKey($field, $response->viewData('validationErrors'));
        $this->assertFalse(session()->has('_old_input'));
        $this->assertSame(0, CustomerSubmission::count());
        $this->assertSame($id, $response->viewData('draftId'));
    }

    public function test_missing_expired_foreign_draft_and_inactive_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $this->finish($dealer, '00000000-0000-0000-0000-000000000000')->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
        $id = $this->draft($dealer);
        $other = Dealer::factory()->create();
        $this->finish($other, $id)->assertRedirect(route('dealers.profile', $other->slug).'#registration');
        $this->travel(31)->minutes();
        $this->finish($dealer, $id)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
        $id = $this->draft($dealer);
        $dealer->update(['is_active' => false]);
        $this->finish($dealer, $id)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
        $this->assertSame(0, CustomerSubmission::count());
    }

    public static function duplicateStates(): array
    {
        return [['phone', 'pending'], ['phone', 'approved'], ['phone', 'rejected'], ['license_plate', 'pending'], ['license_plate', 'approved'], ['license_plate', 'rejected']];
    }

    #[DataProvider('duplicateStates')]
    public function test_global_duplicates_and_historical_normalization(string $field, string $status): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $factory = CustomerSubmission::factory();
        $factory = $status === 'approved' ? $factory->approved() : ($status === 'rejected' ? $factory->rejected() : $factory);
        $factory->create([$field => $field === 'phone' ? '+84 (990) 000-007' : '51k-987.65', 'phone_normalized' => null]);
        $expected = $status === 'rejected' ? route('dealers.registrations.success', $dealer->slug)
            : ($field === 'phone' ? route('dealers.profile', $dealer->slug).'#registration' : route('dealers.registrations.step-two', $dealer->slug));
        $this->finish($dealer, $id)->assertRedirect($expected);
        $this->assertSame($status === 'rejected' ? 2 : 1, CustomerSubmission::count());
        if ($status !== 'rejected' && $field === 'phone') {
            $this->get(route('dealers.profile', $dealer->slug))->assertViewHas('duplicatePhone', true);
        }
    }

    public function test_confirmation_is_session_and_dealer_scoped(): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $this->finish($dealer, $id)->assertRedirect();
        $other = Dealer::factory()->create();
        $this->get(route('dealers.registrations.success', $other->slug))->assertRedirect();
        session()->invalidate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('dealers.registrations.success', $dealer->slug))->assertRedirect();
        $this->finish($dealer, $id)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
        $this->assertSame(1, CustomerSubmission::count());
    }

    public function test_csrf_honeypot_and_throttle_on_final(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $dealer = Dealer::factory()->create();
        $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $token = session()->token();
        $this->post(route('dealers.registrations.step-one', $dealer->slug), ['_token' => $token,
            'customer_name' => 'Synthetic', 'phone' => '0990000007', 'vehicle_description' => 'FJ'])->assertRedirect();
        $id = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->viewData('draftId');
        $this->finish($dealer, $id)->assertStatus(419);
        $this->finish($dealer, $id, ['_token' => 'wrong'])->assertStatus(419);
        $this->finish($dealer, $id, ['_token' => $token, 'website' => 'bot'])->assertStatus(422);
        $this->finish($dealer, $id, ['_token' => $token])->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        for ($i = 0; $i < 3; $i++) {
            $response = $this->finish($dealer, $id, ['_token' => $token]);
        }
        $response->assertStatus(429);
        $this->assertSame(1, CustomerSubmission::count());
    }

    public function test_every_model_insert_is_guarded_including_sales_and_legacy(): void
    {
        $first = CustomerSubmission::factory()->create(['phone' => '0990000007', 'license_plate' => '51K-987.65']);
        try {
            CustomerSubmission::factory()->create(['phone' => '+84 990 000 007']);
            $this->fail('An unguarded insert was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('phone', $error->errors());
        }
        $dealer = Dealer::factory()->create();
        $data = $this->payload('unused');
        unset($data['_draft'], $data['_flow'], $data['license_plate'], $data['facebook_url'], $data['first_registration_year'], $data['odo']);
        $this->postJson(route('dealers.registrations.store', $dealer->slug), $data + ['customer_name' => 'Synthetic',
            'phone' => '0990000007', 'vehicle_description' => 'FJ'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame(1, CustomerSubmission::count());
        $this->assertNotNull($first->fresh());
    }

    public function test_mutex_is_held_until_outer_transaction_ends(): void
    {
        app(SubmissionIntake::class)->locked(fn () => true);
        config(['database.connections.intake_probe' => config('database.connections.mysql')]);
        $probe = DB::connection('intake_probe');
        $this->assertSame('toyota_testing', $probe->selectOne('SELECT DATABASE() AS name')->name);
        $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $probe->beginTransaction();
        try {
            $probe->table('submission_intake_mutex')->where('id', 1)->lockForUpdate()->first();
            $this->fail('Concurrent connection bypassed the intake mutex.');
        } catch (QueryException $error) {
            $this->assertSame(1205, $error->errorInfo[1]);
        } finally {
            $probe->rollBack();
            DB::purge('intake_probe');
        }
    }

    public function test_sales_endpoint_and_admin_approval_keep_their_existing_contracts(): void
    {
        Storage::fake('submission_evidence');
        $sales = User::factory()->active()->create();
        $base = ['customer_name' => 'Synthetic Sales Customer', 'facebook_url' => 'https://facebook.com/synthetic.sales.customer',
            'customer_phone' => '0990000007', 'vehicle_model' => 'veloz', 'vehicle_color' => 'Synthetic',
            'first_registration_year' => '2024', 'license_plate' => '51K-987.65'];
        $this->actingAs($sales)->post(route('sales.submissions.store'), $base + ['evidence_image' => UploadedFile::fake()->image('synthetic.jpg')])->assertRedirect();
        $record = CustomerSubmission::sole();
        $this->assertSame('sales', $record->source);
        $this->assertSame($sales->id, $record->sales_id);
        $this->postJson(route('sales.submissions.store'), array_replace($base, [
            'facebook_url' => 'https://facebook.com/synthetic.sales.other', 'license_plate' => '51K-111.11',
            'evidence_image' => UploadedFile::fake()->image('synthetic-other.jpg'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('customer_phone');
        $this->assertSame(1, CustomerSubmission::count());
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.approve', $record))->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status->value);
        $this->assertSame(1, $record->moderationReviews()->count());
        $this->assertSame(1, app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->count());
    }

    public function test_receipt_expires_after_twenty_four_hours_without_reopening_consumed_draft(): void
    {
        $dealer = Dealer::factory()->create();
        $id = $this->draft($dealer);
        $this->finish($dealer, $id)->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $this->travel(23)->hours();
        $this->get(route('dealers.registrations.success', $dealer->slug))->assertOk();
        $this->finish($dealer, $id)->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $this->travel(2)->hours();
        $this->get(route('dealers.registrations.success', $dealer->slug))->assertRedirect();
        $this->finish($dealer, $id)->assertRedirect(route('dealers.profile', $dealer->slug).'#registration');
        $this->assertSame(1, CustomerSubmission::count());
        $this->artisan('dealer-registration:prune-receipts', ['--dry-run' => true])->expectsOutput('Eligible expired receipts: 1')->assertSuccessful();
        $this->assertSame(1, DB::table('dealer_registration_receipts')->count());
        $this->travelBack();
    }
}
