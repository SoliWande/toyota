<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AdminGroupLookup;
use App\Services\LeaderboardService;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminGroupLookupTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginIsolatedTransaction; }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->beginIsolatedTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function record(string $phone = '0901234567'): CustomerSubmission
    {
        return CustomerSubmission::factory()->create(['phone' => $phone, 'license_plate' => '51K-987.65', 'customer_name' => 'PRIVATE_CUSTOMER', 'notes' => 'SECRET_NOTE']);
    }

    private function lookup(string $phone = '0901234567')
    {
        $this->post(route('admin.group-lookup.search'), ['phone' => $phone])->assertRedirect(route('admin.group-lookup.index'));

        return $this->get(route('admin.group-lookup.index'))->assertOk();
    }

    private function payload(CustomerSubmission $submission): array
    {
        $verification = app(SubmissionVerification::class);

        return ['submission_id' => $submission->id, 'verification_version' => $verification->version($submission, $verification->state($submission->id)), 'plate_match' => '1', 'facebook_match' => '1'];
    }

    #[DataProvider('phoneFormats')]
    public function test_exact_phone_normalization_and_private_projection(string $phone): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup($phone)->assertViewHas('submission', fn ($row) => $row->is($record))
            ->assertSee('PRIVATE_CUSTOMER')->assertDontSee('SECRET_NOTE')->assertDontSee('51K-987.65')
            ->assertSee('Quản trị / Tra SĐT vào group')->assertDontSee('Link sales')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
    }

    public static function phoneFormats(): array
    {
        return [['0901234567'], ['0901 234 567'], ['+84 901 234 567'], ['0084(901).234-567']];
    }

    public function test_missing_ambiguous_and_inconsistent_aliases_never_select_a_customer(): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup('0901234568')->assertViewHas('match', ['kind' => 'not_found'])->assertViewHas('submission', null)->assertDontSee('PRIVATE_CUSTOMER');
        CustomerSubmission::factory()->rejected()->create(['phone' => $record->phone]);
        $this->lookup()->assertViewHas('match', ['kind' => 'ambiguous'])->assertViewHas('submission', null)->assertDontSee('PRIVATE_CUSTOMER')->assertDontSee('data-group-verify', false);
        // Explicit legacy-inconsistency fixture, only inside guarded test transaction.
        DB::table('customer_submissions')->where('id', $record->id)->update(['phone_normalized' => '0909999999']);
        $this->lookup('0909999999')->assertViewHas('match', ['kind' => 'ambiguous'])->assertViewHas('submission', null);
    }

    public function test_legacy_formatted_raw_phone_matches_exactly_but_substrings_do_not(): void
    {
        $record = $this->record();
        DB::table('customer_submissions')->where('id', $record->id)->update(['phone' => '+84 (901) 234-567', 'phone_normalized' => null]);
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup()->assertViewHas('submission', fn ($row) => $row->id === $record->id);
        foreach (['901234567', '%0901234567%', '090123', ['0901234567']] as $phone) {
            $this->postJson(route('admin.group-lookup.search'), ['phone' => $phone])->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
    }

    public function test_guests_sales_managers_and_blocked_admin_cannot_lookup_or_verify(): void
    {
        $this->get(route('admin.group-lookup.index'))->assertRedirect(route('login'));
        foreach (['search', 'verify'] as $action) {
            $this->post(route('admin.group-lookup.'.$action))->assertRedirect(route('login'));
        }
        foreach ([User::factory()->active()->create(), User::factory()->dealer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.group-lookup.index'))->assertForbidden();
            foreach (['search', 'verify'] as $action) {
                $this->post(route('admin.group-lookup.'.$action))->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->admin()->create(['status' => 'blocked']))->get(route('admin.group-lookup.index'))->assertRedirect(route('account.status'));
        $this->postJson(route('admin.group-lookup.search'), ['phone' => '0901234567'])->assertForbidden();
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
    }

    public function test_group_verification_persists_audit_without_approval_or_points_and_only_then_toast(): void
    {
        $record = $this->record();
        $before = $record->fresh()->getAttributes();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->lookup()->assertDontSee('data-group-success', false);
        $this->post(route('admin.group-lookup.verify'), $this->payload($record))->assertRedirect(route('admin.group-lookup.index'))
            ->assertSessionHas('group_lookup_success', 'Đã xác minh PRIVATE_CUSTOMER · bấm [Duyệt] bên Facebook');
        $this->assertSame($before, $record->fresh()->getAttributes());
        $event = DB::table('submission_verification_audits')->sole();
        $this->assertSame('group', $event->action);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('member', app(SubmissionVerification::class)->evidencedProgress($record, app(SubmissionVerification::class)->state($record->id)));
        $this->get(route('admin.group-lookup.index'))->assertOk()->assertSee('data-group-success', false)->assertViewHas('recent', fn ($rows) => count($rows) === 1 && $rows[0]['member']);
        $this->postJson(route('admin.group-lookup.verify'), $this->payload($record))->assertUnprocessable();
        $this->assertSame(1, DB::table('submission_verification_audits')->count());
    }

    public function test_stale_version_wrong_target_and_new_collision_do_not_write_or_toast(): void
    {
        $record = $this->record();
        $other = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup();
        $payload = $this->payload($record);
        $this->postJson(route('admin.group-lookup.verify'), [...$payload, 'submission_id' => $other->id])->assertUnprocessable();
        $this->postJson(route('admin.group-lookup.verify'), [...$payload, 'verification_version' => str_repeat('0', 64)])->assertUnprocessable();
        $this->postJson(route('admin.group-lookup.verify'), [...$payload, 'plate_match' => '0'])->assertUnprocessable();
        CustomerSubmission::factory()->rejected()->create(['phone' => $record->phone]);
        $this->postJson(route('admin.group-lookup.verify'), $payload)->assertUnprocessable();
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
        $this->get(route('admin.group-lookup.index'))->assertOk()->assertDontSee('data-group-success', false);
    }

    public function test_approved_scores_stay_unchanged_and_recent_requires_current_profile_evidence(): void
    {
        $admin = User::factory()->admin()->create();
        $record = CustomerSubmission::factory()->approved()->create(['phone' => '0901234567']);
        $leaderboard = app(LeaderboardService::class);
        $scores = $leaderboard->dealerScores()->get()->toArray();
        $this->actingAs($admin);
        $this->lookup();
        $this->post(route('admin.group-lookup.verify'), $this->payload($record))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($scores, $leaderboard->dealerScores()->get()->toArray());
        $this->get(route('admin.group-lookup.index'))->assertOk()->assertViewHas('recent', fn ($rows) => count($rows) === 1);
        // Historical profile-change fixture; an old audit must not prove the replacement profile.
        DB::table('customer_submissions')->where('id', $record->id)->update(['facebook_url' => 'https://www.facebook.com/replacement.profile', 'facebook_url_normalized' => 'https://www.facebook.com/replacement.profile']);
        $this->get(route('admin.group-lookup.index'))->assertOk()->assertViewHas('recent', []);
        // Unsafe profiles cannot be persisted under existing database constraints.
        $unsafe = (new CustomerSubmission)->forceFill(['facebook_url' => 'javascript:alert(1)', 'facebook_url_normalized' => null]);
        $this->assertNull(app(AdminGroupLookup::class)->profileUrl($unsafe));
    }

    public function test_missing_expired_or_other_actor_session_cannot_verify(): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson(route('admin.group-lookup.verify'), $this->payload($record))->assertUnprocessable();
        $this->lookup();
        $this->travel(11)->minutes();
        $this->postJson(route('admin.group-lookup.verify'), $this->payload($record))->assertUnprocessable();
        $this->lookup();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.group-lookup.index'))->assertOk()->assertViewHas('submission', null);
    }

    public function test_lookup_is_throttled_and_get_does_not_accept_phone_query(): void
    {
        $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.group-lookup.index', ['phone' => '0901234567']))->assertOk()->assertViewHas('submission', null);
        for ($i = 0; $i < 29; $i++) {
            $this->post(route('admin.group-lookup.search'), ['phone' => '0901234567'])->assertRedirect();
        }
        $this->postJson(route('admin.group-lookup.search'), ['phone' => '0901234567'])->assertStatus(429);
    }

    public function test_invalid_new_search_clears_previous_customer_and_cannot_verify_it(): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup();
        $this->postJson(route('admin.group-lookup.search'), ['phone' => 'invalid'])->assertUnprocessable();
        $this->get(route('admin.group-lookup.index'))->assertOk()->assertViewHas('submission', null)->assertDontSee('PRIVATE_CUSTOMER');
        $this->postJson(route('admin.group-lookup.verify'), $this->payload($record))->assertUnprocessable();
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
    }

    public function test_lookup_and_verification_require_real_csrf(): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->admin()->create());
        $this->lookup();
        $this->app['env'] = 'local';
        try {
            $this->post(route('admin.group-lookup.search'), ['phone' => '0901234567'])->assertStatus(419);
            $this->post(route('admin.group-lookup.verify'), $this->payload($record))->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame(0, DB::table('submission_verification_audits')->count());
    }
}
