<?php

namespace Tests\Feature;

use App\Actions\PublishAuditedAward;
use App\Enums\LeaderboardPeriod;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Award;
use App\Models\AwardPublication;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\LeaderboardService;
use App\Services\ResetDemoRetirement;
use App\Services\SubmissionVerification;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class FinalCallAuditUatTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private User $sales;

    private Award $award;

    private array $dealers = [];

    private array $sources = [];

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new CallAuditFreezeFixtures;
        $this->admin = $this->fixtures->admin();
        $this->actingAs($this->admin);
        $this->withSession(['_token' => 'final-uat-csrf'])->withHeader('X-CSRF-TOKEN', 'final-uat-csrf');
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false, 'realistic_demo.include_in_metrics' => false]);
        $retirement = Mockery::mock(ResetDemoRetirement::class);
        $retirement->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $retirement);
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', config('app.timezone'));
        Carbon::setTestNow($this->now);
        CarbonImmutable::setTestNow($this->now);
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        $this->sales = $this->fixtures->admin();
        foreach ([3, 2, 2, 2] as $n => $count) {
            $dealer = $this->fixtures->dealer();
            $this->dealers[] = $dealer;
            if ($n === 0) {
                $this->fixtures->change($this->sales, ['role' => 'sales', 'dealer_id' => $dealer->id]);
            }
            foreach (range(1, $count) as $i) {
                $this->sources[$n][] = $this->fixtures->submission($dealer, ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null,
                    'customer_name' => 'PRIVATE-UAT-CUSTOMER-'.$n.'-'.$i, 'admin_note' => null, 'rejection_reason' => null, 'submitted_at' => '2026-10-0'.($n + 2).' 12:00:00',
                    'source' => $n === 0 ? 'sales' : 'dealer_qr', 'sales_id' => $n === 0 ? $this->sales->id : null]);
            }
        }
        $this->award = $this->fixtures->award();
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            parent::tearDown();
        }
    }

    private function approve(): void
    {
        foreach (array_merge(...$this->sources) as $source) {
            $url = 'https://www.facebook.com/uat.'.str_replace('-', '', $this->fixtures->tag).'.'.$source->id;
            $this->postJson(route('admin.submissions.approve', $source), ['verified_facebook_url' => $url])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('approved', $source->fresh()->status->value);
            $this->assertSame($this->admin->id, $source->fresh()->reviewed_by);
        }
        $this->assertSame(9, DB::table('moderation_reviews')->whereIn('customer_submission_id', array_map(fn ($s) => $s->id, array_merge(...$this->sources)))->count());
    }

    private function freeze(string $purpose): CallAuditBatch
    {
        $input = ['purpose' => $purpose, 'month' => '2026-10', 'idempotency_key' => $this->fixtures->tag.'-'.$purpose];
        if ($purpose === 'award_top3') {
            $input['award_id'] = $this->award->id;
        }
        $result = $this->postJson('/admin/call-audits/freeze', $input)->assertOk()->json('data');
        $this->assertSame($result['batch_id'], $this->postJson('/admin/call-audits/freeze', $input)->assertOk()->json('data.batch_id'));

        return CallAuditBatch::findOrFail($result['batch_id']);
    }

    private function record(CallAuditItem $item, string $outcome = 'correct'): array
    {
        // Model operator time; retain the real write throttle rather than disabling middleware.
        $this->now = $this->now->addSeconds(10);
        Carbon::setTestNow($this->now);
        CarbonImmutable::setTestNow($this->now);
        $item->refresh();
        $prefix = '/admin/call-audits/'.$item->batch_id.'/items/'.$item->id;
        $input = ['purpose' => $item->batch->purpose, 'row_version' => $item->row_version, 'idempotency_key' => 'uat-claim-'.$item->id.'-'.$item->attempt_count];
        $claim = $this->postJson($prefix.'/claim', $input)->assertOk()->json('data');
        $save = ['purpose' => $item->batch->purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'uat-save-'.$item->id.'-'.$item->attempt_count, 'outcome' => $outcome,
            'answer_1' => $outcome === 'no_answer' ? null : $outcome !== 'wrong',
            'answer_2' => $outcome === 'no_answer' ? null : true, 'answer_3' => $outcome === 'no_answer' ? null : true,
            'note' => 'PRIVATE UAT call note'];
        $result = $this->postJson($prefix.'/record', $save)->assertOk()->json('data');
        $this->assertSame($result, $this->postJson($prefix.'/record', $save)->assertOk()->json('data'));

        return $result;
    }

    private function resolve(CallAuditItem $item, string $decision = 'confirmed', int $version = 0): void
    {
        $this->postJson('/admin/call-audits/'.$item->batch_id.'/findings/'.$item->id.'/resolution', [
            'purpose' => $item->batch->purpose, 'month' => '2026-10', 'attempt_id' => $item->attempts()->latest('attempt_no')->first()->id,
            'row_version' => $item->fresh()->row_version, 'resolution_version' => $version, 'decision' => $decision,
            'idempotency_key' => 'uat-resolution-'.$item->id.'-'.$version, 'reason' => 'PRIVATE UAT resolution reason',
        ])->assertOk()->assertJsonPath('data.decision', $decision);
    }

    private function penalty(CallAuditItem $item, int $version = 1)
    {
        return $this->postJson('/admin/call-audits/'.$item->batch_id.'/findings/'.$item->id.'/penalty', [
            'purpose' => $item->batch->purpose, 'month' => '2026-10', 'resolution_version' => $version, 'idempotency_key' => 'uat-penalty-'.$item->id]);
    }

    public function test_fixture_pending_to_approved_audits_penalty_supplement_publish_and_late_penalty_snapshot(): void
    {
        $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertOk()->assertJsonPath('can_publish', false)->assertJsonPath('pending_submission_count', 9);
        $this->approve();
        $leaderboard = app(LeaderboardService::class);
        $month = $this->award->period_start;
        $this->assertSame([3, 2, 2, 2], $leaderboard->dealers(LeaderboardPeriod::Month, $month)->get()->pluck('score')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(3, (int) $leaderboard->sales(LeaderboardPeriod::Month, $month)->sole()->score);
        $this->assertSame(0, $leaderboard->dealers(LeaderboardPeriod::Month, $month->addMonth())->count());
        $verification = app(SubmissionVerification::class);
        $source = $this->sources[0][0]->fresh();
        $verification->adminUpdate($this->admin, $source, ['action' => 'group', 'group_progress' => 'member', 'verification_version' => $verification->version($source)]);
        $sourceState = $this->businessState();
        $general = $this->freeze('general_audit');
        $award = $this->freeze('award_top3');
        $this->assertSame([60, 9, 15, 7], [$general->requested_count, $general->actual_count, $award->requested_count, $award->actual_count]);
        $wrong = [];
        $lateItem = null;
        foreach ($general->items as $item) {
            if ($item->submission_id === $this->sources[3][1]->id) {
                $lateItem = $item;

                continue;
            }
            $outcome = in_array($item->submission_id, [$this->sources[0][0]->id, $this->sources[0][1]->id], true) ? 'wrong' : 'correct';
            $this->record($item, $outcome);
            if ($outcome === 'wrong') {
                $wrong[] = $item;
            }
        }
        $this->resolve($wrong[0], 'needs_review');
        $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertOk()->assertJsonPath('can_publish', false);
        $this->resolve($wrong[0], 'confirmed', 1);
        $this->resolve($wrong[1]);
        $recall = null;
        $overlap = null;
        foreach ($award->items as $item) {
            if ($item->submission_id === $this->sources[0][0]->id) {
                $this->record($item, 'wrong');
                $this->resolve($item);
                $overlap = $item;
            } elseif ($item->submission_id === $this->sources[1][0]->id) {
                $this->record($item, 'no_answer');
                $recall = $item;
            } elseif ($item->submission_id === $this->sources[2][0]->id) {
                $this->record($item, 'wrong');
                $this->resolve($item, 'dismissed');
            } else {
                $this->record($item);
            }
        }
        $this->now = $this->now->addDay();
        $this->record($recall, 'no_answer');
        $this->assertSame('completed', $recall->fresh()->queue_state);
        $this->assertSame('no_answer', $recall->fresh()->latest_outcome);
        $this->penalty($wrong[0], 2)->assertOk()->assertJsonPath('data.evidence_count', 2);
        $this->penalty($overlap)->assertOk()->assertJsonPath('data.created', false);
        $this->penalty($wrong[1])->assertOk();
        $this->assertSame(2, DB::table('dealer_penalty_entries')->count());
        $this->assertSame(3, DB::table('dealer_penalty_evidence')->count());
        $scores = $leaderboard->dealers(LeaderboardPeriod::Month, $month)->get();
        $this->assertSame([$this->dealers[1]->id, $this->dealers[2]->id, $this->dealers[3]->id], $scores->take(3)->pluck('id')->all());
        $original = $scores->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([3, 2, 1], [(int) $original->raw_score, (int) $original->penalty_points, (int) $original->display_score]);
        $this->assertSame(3, (int) $leaderboard->sales(LeaderboardPeriod::Month, $month)->sole()->score);
        $readyUrl = '/admin/awards/'.$this->award->id.'/publication';
        $this->getJson($readyUrl)->assertOk()->assertJsonPath('supplemental_dealer_ids.0', $this->dealers[3]->id);
        $input = ['idempotency_key' => $this->fixtures->tag.'-supplement'];
        $result = $this->postJson('/admin/awards/'.$this->award->id.'/supplemental', $input)->assertOk()->json('data');
        $extra = CallAuditBatch::findOrFail($result['batch_id']);
        $this->assertSame([2, 5, 2], [$extra->generation, $extra->requested_count, $extra->actual_count]);
        foreach ($extra->items as $item) {
            $this->record($item);
        }
        foreach ([$general, $award, $extra] as $batch) {
            $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($batch->id)->summary['status']);
        }
        $this->assertSame($sourceState, $this->businessState());
        $ready = $this->getJson($readyUrl)->assertOk()->assertJsonPath('can_publish', true)->json();
        $before = DB::table('call_audit_events')->count();
        $this->getJson($readyUrl)->assertOk();
        $this->assertSame($before, DB::table('call_audit_events')->count());
        $publish = ['idempotency_key' => 'uat-publish', 'preview_fingerprint' => $ready['preview_fingerprint']];
        $response = $this->postJson('/admin/awards/'.$this->award->id.'/publish', $publish)->assertOk()->json('data');
        $this->assertSame($response, $this->postJson('/admin/awards/'.$this->award->id.'/publish', $publish)->assertOk()->json('data'));
        $publication = AwardPublication::sole();
        app(PublishAuditedAward::class)->verify($publication);
        $winners = $this->award->winners()->orderBy('id')->get()->toArray();
        $this->assertCount(4, $winners); // Three Dealer snapshots and the unchanged internal Sales winner.
        $this->assertSame(2, $publication->batch_generation);
        $this->assertSame($sourceState, $this->businessState());
        foreach (['PRIVATE', 'manifest', 'seed', $source->phone] as $private) {
            $this->assertStringNotContainsString($private, json_encode($response));
        }
        $this->get(route('awards.show', $this->award))->assertOk()->assertDontSee($source->phone)->assertDontSee($source->customer_name);
        // Later C08 audit is independent: a newly confirmed debit changes live ranks only.
        $this->record($lateItem, 'wrong');
        $this->resolve($lateItem);
        $this->penalty($lateItem)->assertOk()->assertJsonPath('data.created', true);
        $this->assertSame(3, DB::table('dealer_penalty_entries')->count());
        $this->assertSame($sourceState, $this->businessState());
        $late = $this->fixtures->submission($this->dealers[3], ['submitted_at' => '2026-10-20']);
        $this->assertSame($winners, $this->award->winners()->orderBy('id')->get()->toArray());
        app(PublishAuditedAward::class)->verify($publication->fresh());
    }

    private function businessState(): array
    {
        $state = [];
        foreach (['customer_submissions', 'moderation_reviews', 'submission_verification_states', 'submission_verification_audits'] as $table) {
            $state[$table] = DB::table($table)->orderBy($table === 'submission_verification_states' ? 'submission_id' : 'id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    #[DataProvider('accounts')]
    public function test_c07_c08_role_and_account_state_matrix(?string $role, ?string $status): void
    {
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $actor = $this->fixtures->admin();
            $this->fixtures->change($actor, ['role' => $role, 'status' => $status] + ($role === 'admin' ? [] : ['dealer_id' => $this->dealers[0]->id]));
            $this->actingAs($actor->fresh());
        }
        $code = $role === null ? 401 : 403;
        foreach (['/admin/awards/'.$this->award->id.'/publication', '/admin/call-audits?purpose=general_audit&month=2026-10', '/admin/call-audits/penalties?month=2026-10'] as $url) {
            $this->getJson($url)->assertStatus($code);
        }
        foreach (['/admin/awards/'.$this->award->id.'/publish', '/admin/awards/'.$this->award->id.'/supplemental', '/admin/call-audits/freeze'] as $url) {
            $this->postJson($url, ['idempotency_key' => 'denied'])->assertStatus($code);
        }
        $this->assertSame(0, CallAuditBatch::count());
        $this->assertSame(0, AwardPublication::count());
    }

    public static function accounts(): array
    {
        $cases = [[null, null]];
        foreach (['admin', 'dealer', 'sales'] as $role) {
            foreach (['active', 'pending', 'rejected', 'blocked'] as $status) {
                if ($role !== 'admin' || $status !== 'active') {
                    $cases[$role.'-'.$status] = [$role, $status];
                }
            }
        }

        return $cases;
    }
}
