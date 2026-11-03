<?php

namespace Tests\Feature;

use App\Actions\ApproveCustomerSubmission;
use App\Actions\RejectCustomerSubmission;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerRegistrationReceipt;
use App\Services\LeaderboardService;
use App\Services\RegistrationLookupStateResolver;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class RegistrationLookupStatesTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

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
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function record(string $status = 'pending'): CustomerSubmission
    {
        $dealer = Dealer::factory()->create();
        $factory = CustomerSubmission::factory();
        if ($status !== 'pending') {
            $factory = $factory->$status();
        }

        return $factory->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id,
            'customer_name' => 'SYNTHETIC STATE OWNER', 'phone' => '0990000071', 'phone_normalized' => '0990000071',
            'license_plate' => '51K90071', 'vehicle_description' => 'Veloz', 'facebook_url' => 'https://facebook.com/synthetic.state.private',
            'address_line' => 'SYNTHETIC PRIVATE ADDRESS', 'province_code' => '79', 'ward_code' => '00001',
            'consented_at' => now(), 'consent_version' => 'synthetic-test']);
    }

    private function change(CustomerSubmission $record, string $action, array $fields = [], ?User $admin = null): void
    {
        $service = app(SubmissionVerification::class);
        $service->adminUpdate($admin ?? User::factory()->admin()->create(), $record,
            $fields + ['action' => $action, 'verification_version' => $service->version($record->fresh(), $service->state($record->id))]);
    }

    private function lookup(CustomerSubmission $record)
    {
        return $this->post(route('dealers.lookup.store', $record->dealer->slug), ['phone' => $record->phone, 'license_plate' => $record->license_plate])
            ->assertViewIs('dealers.registration.lookup')->assertDontSee('Chưa thể gửi đăng ký');
    }

    private function receipt(CustomerSubmission $record): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        DB::table('dealer_registration_receipts')->insert(['draft_key' => hash('sha256', Str::uuid()),
            'owner_key' => app(DealerRegistrationReceipt::class)->owner($request, $record->dealer), 'submission_id' => $record->id,
            'confirmation' => Crypt::encryptString('{}'), 'expires_at' => now()->addDay(), 'created_at' => now()]);
    }

    private function responsePayload(CustomerSubmission $record): array
    {
        $service = app(SubmissionVerification::class);
        $state = $service->state($record->id);

        return ['supplement_key' => $state->supplement_key, 'verification_version' => $service->version($record->fresh(), $state),
            'facebook_profile_url' => 'https://www.facebook.com/synthetic.corrected.profile'];
    }

    public static function progress(): array
    {
        return [['requested', 'requested', 'Chờ duyệt'], ['member', 'member', 'Thành viên'],
            ['not_requested', 'not_requested', 'Chờ vào group'], ['unknown', 'found', 'Đã tìm thấy đăng ký']];
    }

    #[DataProvider('progress')]
    public function test_manual_progress_maps_without_changing_submission_or_points(string $progress, string $kind, string $label): void
    {
        $record = $this->record();
        $before = $record->refresh()->getRawOriginal();
        $this->change($record, 'group', ['group_progress' => $progress]);
        $this->lookup($record)->assertOk()->assertSee($label)->assertDontSee('48 giờ')
            ->assertViewHas('result', fn ($dto) => $dto['kind'] === $kind);
        $this->assertTrue($before === $record->fresh()->getRawOriginal(), 'Verification changed moderation or submission fields.');
        $this->assertSame(0, DB::table('moderation_reviews')->count());
        $this->assertSame(0, CustomerSubmission::where('status', 'approved')->count());
        $this->assertSame(1, DB::table('submission_verification_audits')->count());
    }

    public function test_historical_unknown_and_generic_rejected_have_no_inferred_states_or_backfill(): void
    {
        $record = $this->record('rejected');
        $this->lookup($record)->assertOk()->assertViewHas('result', fn ($dto) => $dto['kind'] === 'found');
        $this->assertSame(0, DB::table('submission_verification_states')->count());
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
    }

    public function test_admin_confirmed_not_requested_renders_dedicated_d12_with_passive_official_group_link(): void
    {
        $record = $this->record();
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'found')
            ->assertSee('Đã tìm thấy đăng ký')->assertDontSee('class="dr-card dr-d12"', false);
        $before = $record->refresh()->getRawOriginal();
        $service = app(SubmissionVerification::class);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from(route('admin.submissions.show', $record))
            ->post(route('admin.submissions.verification', $record), [
                'action' => 'group', 'group_progress' => 'not_requested',
                'verification_version' => $service->version($record, $service->state($record->id)),
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('not_requested', $service->state($record->id)->group_progress);
        $auditCount = DB::table('submission_verification_audits')->count();
        $response = $this->lookup($record)->assertOk()
            ->assertViewHas('result', fn ($dto) => $dto['kind'] === 'not_requested')
            ->assertSee('class="dr-card dr-d12"', false)->assertSee('Chờ vào group')
            ->assertSee('Bước tiếp: vào group')->assertSee('Đã nhận thông tin của bạn')
            ->assertSee('Xin vào group và trả lời đúng số điện thoại, biển số.')
            ->assertSee('aria-current="step"', false)
            ->assertSee('href="https://www.facebook.com/groups/376631239337942"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee('Đã tìm thấy đăng ký')->assertDontSee('Mở group Facebook')
            ->assertDontSee('<h3>Cộng đồng Toyota Veloz &amp; Hilux</h3>', false);
        $this->assertSame(1, substr_count($response->getContent(), '>Vào group Facebook</a>'));
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $this->assertSame($auditCount, DB::table('submission_verification_audits')->count());
        $this->assertSame('not_requested', $service->state($record->id)->group_progress);
        $this->assertSame(0, DB::table('moderation_reviews')->count());
        $this->assertSame(0, CustomerSubmission::where('status', 'approved')->count());
    }

    public function test_rejected_member_is_neutral_and_evidence_and_moderation_are_retained(): void
    {
        $record = $this->record();
        $admin = User::factory()->admin()->create();
        $this->change($record, 'group', ['group_progress' => 'member'], $admin);
        app(RejectCustomerSubmission::class)->execute($admin, $record, 'Synthetic review');
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'found');
        $this->assertSame('member', app(SubmissionVerification::class)->state($record->id)->group_progress);
        $this->assertSame('rejected', $record->fresh()->status->value);
        $this->actingAs($admin)->get(route('admin.submissions.show', $record))->assertOk()->assertSee('Bằng chứng Group được giữ riêng');
    }

    public function test_admin_authorization_validation_and_stale_version_preserve_first_write(): void
    {
        $record = $this->record();
        $service = app(SubmissionVerification::class);
        $payload = ['action' => 'group', 'group_progress' => 'requested', 'verification_version' => $service->version($record)];
        $route = route('admin.submissions.verification', $record);
        $this->actingAs(User::factory()->active()->create())->post($route, $payload)->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from(route('admin.submissions.show', $record))->post($route, $payload)->assertRedirect();
        $this->actingAs(User::factory()->admin()->create())->post($route, array_replace($payload, ['group_progress' => 'member']))
            ->assertRedirect()->assertSessionHasErrors(['verification_version'], null, 'verification');
        $this->assertSame('requested', $service->state($record->id)->group_progress);
        $this->assertSame(1, DB::table('submission_verification_audits')->count());
        $this->post($route, array_replace($payload, ['group_progress' => 'fake']))->assertSessionHasErrors(['group_progress'], null, 'verification');
    }

    public function test_active_supplement_is_contact_only_without_owned_receipt_and_pair_never_grants_write(): void
    {
        $record = $this->record();
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $before = $record->fresh()->getRawOriginal();
        $this->lookup($record)->assertOk()->assertSee('Liên hệ để bổ sung')->assertDontSee('data-registration-supplement', false)
            ->assertViewHas('result', fn ($dto) => $dto['kind'] === 'supplement');
        $this->post(route('dealers.lookup.supplement', $record->dealer->slug), $this->responsePayload($record))->assertForbidden();
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $this->assertSame('open', app(SubmissionVerification::class)->state($record->id)->supplement_state);
    }

    public function test_owned_response_persists_then_redirects_to_d08_and_replay_cannot_write_twice(): void
    {
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $lookup = $this->lookup($record)->assertOk()->assertSee('data-registration-supplement', false);
        $this->assertFalse(str_contains($lookup->getContent(), $record->facebook_url), 'Lookup disclosed saved Facebook URL.');
        $payload = $this->responsePayload($record);
        $url = route('dealers.lookup.supplement', $record->dealer->slug);
        $this->post($url, $payload)->assertRedirect(route('dealers.lookup.supplemented', $record->dealer->slug));
        $this->get(route('dealers.lookup.supplemented', $record->dealer->slug))->assertOk()->assertSee('Đã gửi bổ sung')
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('48 giờ');
        $this->assertTrue($record->fresh()->facebook_url === $payload['facebook_profile_url']);
        $this->assertSame('pending', $record->fresh()->status->value);
        $this->assertNull($record->fresh()->approved_facebook_identity);
        $this->post($url, array_replace($payload, ['facebook_profile_url' => 'https://facebook.com/synthetic.replay']))->assertStatus(422);
        $this->assertTrue($record->fresh()->facebook_url === $payload['facebook_profile_url']);
        $this->assertSame(2, DB::table('submission_verification_audits')->count());
        $this->assertSame(0, DB::table('moderation_reviews')->count());
        $this->assertEmpty(session()->getOldInput());
        $audit = DB::table('submission_verification_audits')->get()->toJson();
        foreach ([$record->phone, $record->customer_name, $record->facebook_url] as $pii) {
            $this->assertFalse(str_contains($audit, $pii), 'Audit contains raw PII.');
        }
    }

    public function test_invalid_response_is_inline_retained_not_d08_and_not_flushed_to_session(): void
    {
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $payload = array_replace($this->responsePayload($record), ['facebook_profile_url' => 'https://example.invalid/synthetic']);
        $response = $this->post(route('dealers.lookup.supplement', $record->dealer->slug), $payload)->assertStatus(422)
            ->assertSee('Liên kết Facebook không hợp lệ.')->assertSee('aria-invalid="true"', false)
            ->assertDontSee('data-lookup-kind="supplemented"', false);
        $this->assertTrue($response->viewData('result')['supplement_value'] === $payload['facebook_profile_url']);
        $this->assertSame('open', app(SubmissionVerification::class)->state($record->id)->supplement_state);
        $this->assertEmpty(session()->getOldInput());
    }

    public static function invalidOwnership(): array
    {
        return [['expired'], ['session'], ['dealer'], ['inactive'], ['approved']];
    }

    #[DataProvider('invalidOwnership')]
    public function test_supplement_rechecks_ownership_expiration_dealer_and_moderation(string $case): void
    {
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $payload = $this->responsePayload($record);
        $dealer = $record->dealer;
        if ($case === 'expired') {
            DB::table('dealer_registration_receipts')->update(['expires_at' => now()->subSecond()]);
        }
        if ($case === 'session') {
            session()->migrate(true);
            $this->withCookie(config('session.cookie'), session()->getId());
        }
        if ($case === 'dealer') {
            $dealer = Dealer::factory()->create();
        }
        if ($case === 'inactive') {
            $dealer->update(['is_active' => false]);
        }
        if ($case === 'approved') { // Existing Admin approval behavior, not a customer-supplied status.
            $admin = User::factory()->admin()->create();
            app(ApproveCustomerSubmission::class)->execute($admin, $record, null, $record->facebook_url);
        }
        $before = $record->fresh()->getRawOriginal();
        $this->post(route('dealers.lookup.supplement', $dealer->slug), $payload)->assertStatus($case === 'approved' ? 422 : 403);
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $this->assertSame('open', app(SubmissionVerification::class)->state($record->id)->supplement_state);
    }

    public function test_structured_blocker_priority_and_admin_correction_audit(): void
    {
        $record = $this->record();
        $this->change($record, 'group', ['group_progress' => 'member']);
        $this->change($record, 'blocker', ['blocker_code' => 'identity_conflict']);
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'blocked');
        $this->change($record, 'supplement', ['supplement_reason' => 'missing_information']);
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'supplement')->assertDontSee('data-registration-supplement', false);
        $this->change($record, 'close_supplement');
        $this->change($record, 'blocker', ['blocker_code' => null]);
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        $result = app(RegistrationLookupStateResolver::class)->resolve($record->id, ['kind' => 'found'], $request, $record->dealer);
        $this->assertSame('member', $result['kind']);
        $this->assertSame(5, DB::table('submission_verification_audits')->count());
    }

    public function test_supplement_honeypot_system_fields_throttle_and_confirmation_privacy(): void
    {
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $url = route('dealers.lookup.supplement', $record->dealer->slug);
        $payload = $this->responsePayload($record);
        $this->post($url, $payload + ['website' => 'synthetic-bot'])->assertStatus(422);
        $this->post($url, $payload + ['group_progress' => 'member'])->assertStatus(422);
        $this->post($url, $payload)->assertRedirect();
        session()->migrate(true);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('dealers.lookup.supplemented', $record->dealer->slug))->assertForbidden()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        for ($i = 0; $i < 2; $i++) {
            $this->post($url, $payload)->assertForbidden();
        }
        $this->post($url, $payload)->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_real_csrf_required_for_admin_and_customer_writes(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $url = route('dealers.lookup.supplement', $record->dealer->slug);
        $payload = $this->responsePayload($record);
        $token = session()->token();
        $this->post($url, $payload)->assertStatus(419);
        $this->post($url, $payload + ['_token' => 'wrong'])->assertStatus(419);
        $this->post($url, $payload + ['_token' => $token])->assertRedirect();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $url = route('admin.submissions.verification', $record);
        $service = app(SubmissionVerification::class);
        $payload = ['action' => 'group', 'group_progress' => 'requested', 'verification_version' => $service->version($record->fresh(), $service->state($record->id))];
        $this->post($url, $payload)->assertStatus(419);
        $this->post($url, $payload + ['_token' => 'wrong'])->assertStatus(419);
        $this->post($url, $payload + ['_token' => $token])->assertRedirect();
    }

    public function test_profile_change_requires_new_manual_evidence_without_changing_recorded_progress(): void
    {
        $record = $this->record();
        $this->change($record, 'group', ['group_progress' => 'member']);
        $record->facebook_url = 'https://facebook.com/synthetic.another.profile';
        $record->save();
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'found');
        $this->assertSame('member', app(SubmissionVerification::class)->state($record->id)->group_progress);
        $this->change($record, 'group', ['group_progress' => 'member']);
        $this->lookup($record)->assertViewHas('result', fn ($dto) => $dto['kind'] === 'member');
    }

    public function test_approved_metrics_and_identity_unchanged_by_group_updates_and_guest_dealer_cannot_write(): void
    {
        $record = $this->record('approved');
        $before = $record->refresh()->getRawOriginal();
        $metrics = app(LeaderboardService::class)->dealerScores()->where('dealers.id', $record->dealer_id)->first();
        $this->assertNotNull($metrics);
        $this->assertSame(1, (int) $metrics->score);
        $this->change($record, 'group', ['group_progress' => 'member']);
        $this->change($record, 'group', ['group_progress' => 'not_requested']);
        $after = app(LeaderboardService::class)->dealerScores()->where('dealers.id', $record->dealer_id)->first();
        $this->assertEquals($metrics, $after);
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $service = app(SubmissionVerification::class);
        $payload = ['action' => 'group', 'group_progress' => 'member', 'verification_version' => $service->version($record, $service->state($record->id))];
        $this->post(route('admin.submissions.verification', $record), $payload)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->dealer()->create())->post(route('admin.submissions.verification', $record), $payload)->assertForbidden();
        $this->assertSame(2, DB::table('submission_verification_audits')->count());
    }

    public function test_customer_update_rolls_back_when_audit_cannot_be_committed(): void
    {
        $record = $this->record();
        $this->receipt($record);
        $this->change($record, 'supplement', ['supplement_reason' => 'facebook_unavailable']);
        $before = $record->fresh()->getRawOriginal();
        $payload = $this->responsePayload($record);
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'insert into `submission_verification_audits`')) {
                $fail = false;
                throw new \RuntimeException('Synthetic audit failure.');
            }
        });
        try {
            app(SubmissionVerification::class)->respond($request, $record->dealer, $payload['supplement_key'],
                $payload['verification_version'], $payload['facebook_profile_url']);
            $this->fail('Synthetic failure was not exercised.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic audit failure.', $error->getMessage());
        }
        $this->assertTrue($before === $record->fresh()->getRawOriginal());
        $this->assertSame('open', app(SubmissionVerification::class)->state($record->id)->supplement_state);
        $this->assertSame(1, DB::table('submission_verification_audits')->count());
    }
}
