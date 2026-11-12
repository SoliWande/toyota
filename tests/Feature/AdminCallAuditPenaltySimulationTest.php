<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\PenaltySimulationReadModel;
use App\Services\LeaderboardService;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditPenaltySimulationTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private User $sales;

    private array $dealers = [];

    private array $sources = [];

    private CallAuditBatch $batch;

    private ?Award $award = null;

    private bool $reading = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new CallAuditFreezeFixtures;
        $this->admin = $this->fixtures->admin();
        $this->actingAs($this->admin);
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
        $retirement = Mockery::mock(ResetDemoRetirement::class);
        $retirement->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $retirement);
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturn(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $this->app->instance(FreezeClock::class, $clock);
        $this->sales = $this->fixtures->admin();
        foreach ([3, 3, 2, 2] as $index => $count) {
            $dealer = $this->fixtures->dealer();
            $this->dealers[] = $dealer;
            if ($index === 0) {
                $this->fixtures->change($this->sales, ['role' => 'sales', 'dealer_id' => $dealer->id]);
            }
            for ($i = 0; $i < $count; $i++) {
                $this->sources[$index][] = $this->fixtures->submission($dealer, [
                    'submitted_at' => '2026-10-0'.($index + 2).' 12:00:00',
                    'source' => $index === 0 ? 'sales' : 'dealer_qr', 'sales_id' => $index === 0 ? $this->sales->id : null,
                ]);
            }
        }
        $this->batch = $this->freeze();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && (! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)
                || preg_match('/for update|for share|lock in share mode/i', $sql))) {
                throw new \RuntimeException('Simulation GET cannot write or lock.');
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            if ($this->award !== null) {
                DB::table('award_winners')->where('award_id', $this->award->id)->delete();
            }
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function test_multiple_confirmed_submissions_change_dealer_top3_and_supported_sales_independently(): void
    {
        $this->confirm($this->sources[0][0]);
        $this->confirm($this->sources[0][1]);
        $before = $this->snapshot();
        $result = $this->read()->assertOk()->assertJsonPath('mode', 'SIMULATION_ONLY')
            ->assertJsonPath('counts.candidate_count', 2)->assertJsonPath('assumptions.points_per_submission', 1)->json();
        $dealer = collect($result['dealers']['rows'])->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([3, 1, 2, 1, 4], [$dealer['before_score'], $dealer['before_rank'], $dealer['deduction'], $dealer['after_score'], $dealer['after_rank']]);
        $this->assertSame([$this->dealers[1]->id, $this->dealers[2]->id, $this->dealers[3]->id], array_column($result['dealers']['top3_after'], 'id'));
        $this->assertSame([$this->dealers[3]->id], $result['dealers']['top3_changes']['entered_ids']);
        $this->assertSame([$this->dealers[0]->id], $result['dealers']['top3_changes']['exited_ids']);
        $this->assertSame(1, $result['sales']['rows'][0]['after_score']);
        $this->assertTrue($result['sales']['complete']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_overlap_between_c07_and_c08_deducts_only_one_per_submission(): void
    {
        $source = $this->sources[0][0];
        $this->confirm($source);
        $awardBatch = $this->freeze('award_top3');
        $this->confirm($source, 'confirmed', $awardBatch);
        $result = $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 1)
            ->assertJsonPath('counts.confirmed_evidence_count', 2)->assertJsonPath('candidates.0.dealer_deduction', 1)->json();
        $this->assertSame(1, collect($result['dealers']['rows'])->firstWhere('id', $this->dealers[0]->id)['deduction']);
        $this->assertSame(1, $result['sales']['rows'][0]['deduction']);
        $this->assertCount(2, $result['candidates'][0]['evidence']);
    }

    #[DataProvider('nonConfirmed')]
    public function test_dismissed_needs_review_and_wrong_without_resolution_do_not_deduct(?string $decision): void
    {
        $this->confirm($this->sources[0][0], $decision);
        $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 0)
            ->assertJsonPath('dealers.top3_changes', ['entered_ids' => [], 'exited_ids' => [], 'reordered_ids' => []])
            ->assertJsonPath('dealers.rows.0.deduction', 0)->assertJsonPath('sales.rows.0.deduction', 0);
    }

    public static function nonConfirmed(): array
    {
        return [[null], ['dismissed'], ['needs_review']];
    }

    public function test_signed_negative_baseline_and_no_unsupported_sales_deduction(): void
    {
        $this->confirm($this->sources[0][0]);
        $this->confirm($this->sources[0][1]);
        foreach ($this->sources[0] as $source) {
            $this->fixtures->change($source, ['status' => 'rejected']);
        }
        $response = $this->read()->assertOk()->assertJsonPath('sales.complete', false);
        $row = collect($response->json('dealers.rows'))->firstWhere('id', $this->dealers[0]->id);
        $this->assertFalse($row['baseline_present']);
        $this->assertNull($row['before_rank']);
        $this->assertSame([0, 2, -2], [$row['before_score'], $row['deduction'], $row['after_score']]);
        $this->assertSame([], $response->json('sales.rows'));
        $response->assertJsonPath('candidates.0.sales_effect_status', 'not_simulated_no_live_contribution')
            ->assertJsonPath('candidates.0.sales_deduction', null);
    }

    public function test_qr_submission_has_dealer_effect_and_does_not_invent_sales_effect(): void
    {
        $this->confirm($this->sources[2][0]);
        $response = $this->read()->assertOk()->assertJsonPath('candidates.0.sales_effect_status', 'no_sales_attribution')
            ->assertJsonPath('candidates.0.sales_deduction', null)->assertJsonPath('sales.complete', true);
        $this->assertSame(0, $response->json('sales.rows.0.deduction'));
        $this->assertSame(1, collect($response->json('dealers.rows'))->firstWhere('id', $this->dealers[2]->id)['deduction']);
    }

    public function test_sales_transfer_does_not_move_dealer_penalty_or_sales_ownership(): void
    {
        $this->confirm($this->sources[0][0]);
        $this->fixtures->change($this->sales, ['dealer_id' => $this->dealers[1]->id]);
        $this->fixtures->change($this->sources[0][0], ['dealer_id' => $this->dealers[1]->id]);
        $result = $this->read()->assertOk()->assertJsonPath('candidates.0.dealer_id', $this->dealers[0]->id)
            ->assertJsonPath('sales.rows.0.id', $this->sales->id)->assertJsonPath('sales.rows.0.dealer_id', $this->dealers[1]->id)->json();
        $a = collect($result['dealers']['rows'])->firstWhere('id', $this->dealers[0]->id);
        $b = collect($result['dealers']['rows'])->firstWhere('id', $this->dealers[1]->id);
        $this->assertSame([2, 1, 1], [$a['before_score'], $a['deduction'], $a['after_score']]);
        $this->assertSame([4, 0, 4], [$b['before_score'], $b['deduction'], $b['after_score']]);
        $this->assertSame(1, $result['sales']['rows'][0]['deduction']);
    }

    public function test_changed_sales_attribution_is_marked_incomplete_not_guessed(): void
    {
        $this->confirm($this->sources[0][0]);
        $other = $this->fixtures->admin();
        $this->fixtures->change($other, ['role' => 'sales', 'dealer_id' => $this->dealers[1]->id]);
        $this->fixtures->change($this->sources[0][0], ['sales_id' => $other->id]);
        $response = $this->read()->assertOk()->assertJsonPath('sales.complete', false)
            ->assertJsonPath('candidates.0.sales_effect_status', 'not_simulated_attribution_changed');
        $this->assertSame([$this->sources[0][0]->id], $response->json('sales.not_simulated_submission_ids'));
        $this->assertSame([0, 0], array_column($response->json('sales.rows'), 'deduction'));
    }

    public function test_conflicting_frozen_dealer_attribution_blocks_simulation_without_picking_one(): void
    {
        $source = $this->sources[0][0];
        $this->confirm($source);
        $this->fixtures->change($source, ['dealer_id' => $this->dealers[1]->id]);
        $awardBatch = $this->freeze('award_top3');
        $this->confirm($source, 'confirmed', $awardBatch);
        $before = $this->snapshot();
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'simulation_unavailable']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_before_ranks_match_mysql_and_penalty_keeps_live_tie_timestamp(): void
    {
        // A/B score3 and different achieved_at; penalize B and C to tie C/D using their original MAX dates.
        $this->confirm($this->sources[1][0]);
        $this->confirm($this->sources[2][0]);
        $result = $this->read()->assertOk()->json();
        $live = app(LeaderboardService::class)->dealersForRange(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'))->get();
        foreach ($live as $row) {
            $sim = collect($result['dealers']['rows'])->firstWhere('id', (int) $row->id);
            $this->assertSame((int) $row->rank, $sim['before_rank']);
            $this->assertSame((int) $row->score, $sim['before_score']);
        }
        $this->assertSame([$this->dealers[0]->id, $this->dealers[1]->id, $this->dealers[3]->id], array_column($result['dealers']['top3_after'], 'id'));
    }

    public function test_equal_live_score_and_timestamp_keep_mysql_id_tiebreak(): void
    {
        foreach ($this->sources[1] as $source) {
            $this->fixtures->change($source, ['submitted_at' => '2026-10-02 12:00:00']);
        }
        $this->confirm($this->sources[0][0]);
        $result = $this->read()->assertOk()->json();
        $this->assertSame([$this->dealers[0]->id, $this->dealers[1]->id, $this->dealers[2]->id], array_column($result['dealers']['top3_before'], 'id'));
        $this->assertSame([$this->dealers[1]->id, $this->dealers[0]->id, $this->dealers[2]->id], array_column($result['dealers']['top3_after'], 'id'));
        $live = app(LeaderboardService::class)->dealersForRange(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'))->limit(3)->get();
        $this->assertSame($live->pluck('id')->map(fn ($id) => (int) $id)->all(), array_column($result['dealers']['top3_before'], 'id'));
    }

    public function test_zero_score_does_not_implicitly_exclude_dealer_or_sales(): void
    {
        foreach ($this->sources[0] as $source) {
            $this->confirm($source);
        }
        $response = $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 3)
            ->assertJsonPath('sales.rows.0.after_score', 0)->assertJsonPath('sales.rows.0.after_rank', 1);
        $dealer = collect($response->json('dealers.rows'))->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([3, 0, 4], [$dealer['deduction'], $dealer['after_score'], $dealer['after_rank']]);
        $this->assertCount(4, $response->json('dealers.rows'));
    }

    #[DataProvider('awardStates')]
    public function test_awards_compare_live_simulation_without_recalculating_published_snapshot(bool $published): void
    {
        $this->confirm($this->sources[0][0]);
        $this->award = $this->fixtures->award();
        if ($published) {
            $baseline = app(AwardResults::class)->preview($this->award);
            DB::transaction(function () use ($baseline) {
                foreach (collect($baseline['dealers'])->merge($baseline['sales']) as $row) {
                    (new AwardWinner)->forceFill(['award_id' => $this->award->id] + $row)->save();
                }
            });
            $this->fixtures->change($this->award, ['published_at' => '2026-11-03 12:00:00', 'published_by' => $this->admin->id]);
            $reader = Mockery::mock(AwardResults::class);
            $reader->shouldNotReceive('preview');
            $this->app->instance(AwardResults::class, $reader);
            $this->fixtures->change($this->sources[0][2], ['status' => 'rejected']);
        }
        $before = $this->snapshot();
        $response = $this->read()->assertOk()->assertJsonPath('awards.0.status', $published ? 'published' : 'draft')
            ->assertJsonPath('awards.0.comparison_basis', $published ? 'published_snapshot_vs_simulated_live' : 'draft_live_vs_simulated_live');
        $ref = collect($response->json('awards.0.reference_records'))->first(fn ($row) => $row['winner_type'] === 'dealer' && $row['dealer_id'] === $this->dealers[0]->id);
        $this->assertSame(3, $ref['score']);
        $this->assertSame(1, $ref['rank']);
        $sim = collect($response->json('dealers.rows'))->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame($published ? 1 : 2, $sim['after_score']);
        $this->assertSame($before, $this->snapshot());
    }

    public static function awardStates(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('deniedAccounts')]
    public function test_only_active_admin_can_read_simulation(?string $role, ?string $status): void
    {
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $actor = $this->fixtures->admin();
            $this->fixtures->change($actor, ['role' => $role, 'status' => $status] + ($role !== 'admin' ? ['dealer_id' => $this->dealers[0]->id] : []));
            $this->actingAs($actor->fresh());
        }
        $this->read()->assertStatus($role === null ? 401 : 403);
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'], ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_stale_blocked_actor_cannot_call_service_and_other_active_admin_can(): void
    {
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->read()->assertForbidden();
        try {
            app(PenaltySimulationReadModel::class)->read($this->admin, '2026-10');
            $this->fail('Fresh active Admin must be required.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->actingAs($this->fixtures->admin());
        $this->read()->assertOk()->assertJsonPath('mode', 'SIMULATION_ONLY');
    }

    #[DataProvider('tampering')]
    public function test_tampered_evidence_is_not_simulated(string $type): void
    {
        $item = $this->confirm($this->sources[0][0]);
        if ($type === 'source') {
            DB::table('call_audit_items')->where('id', $item->id)->update(['source_fingerprint' => str_repeat('a', 64)]);
        } else {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', $type)->update(['event_digest' => str_repeat('b', 64)]);
        }
        Log::spy();
        $before = $this->snapshot();
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'simulation_unavailable']);
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('error');
    }

    public static function tampering(): array
    {
        return [['source'], ['call_result_recorded'], ['finding_resolution_recorded']];
    }

    public function test_month_scope_allowlist_privacy_and_repeated_get_do_not_write_or_resolve_actions(): void
    {
        $this->confirm($this->sources[0][0]);
        $this->award = $this->fixtures->award();
        $before = $this->snapshot();
        foreach ([CreateAndFreezeBatch::class, ClaimItem::class, RecordCallResult::class, ResolveFinding::class] as $writer) {
            $this->app->bind($writer, fn () => throw new \RuntimeException('Simulation must not resolve writers'));
        }
        foreach ([[], [], ['month' => '2026-09']] as $filters) {
            $response = $this->read($filters)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach ([$this->sources[0][0]->phone, $this->sources[0][0]->customer_name, $this->sources[0][0]->license_plate,
                'PRIVATE-SIMULATION-REASON', 'seed', 'manifest', 'phone', 'facebook', 'reason', 'note', 'payload_digest', 'source_snapshot', 'threshold'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $this->assertSame($before, $this->snapshot());
        $this->read(['month' => '2026-09'])->assertOk()->assertJsonPath('counts.candidate_count', 0)->assertJsonPath('dealers.rows', [])->assertJsonPath('sales.rows', []);
        foreach ([['month' => null], ['month' => '2026-13'], ['purpose' => 'general_audit'], ['points_per_submission' => 5], ['phone' => 'PRIVATE-PHONE'], ['page' => 1]] as $filters) {
            $this->read($filters)->assertStatus(422)->assertExactJson(['error' => 'invalid_simulation_filters'])->assertDontSee('PRIVATE-PHONE');
        }
        $this->postJson('/admin/call-audits/penalty-simulation', [])->assertStatus(405);
        $route = app('router')->getRoutes()->getByName('admin.call-audits.simulation');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['web', 'auth', 'auth.session', 'role:admin', 'account.active', 'throttle:admin-writes'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    private function freeze(string $purpose = 'general_audit'): CallAuditBatch
    {
        if ($purpose === 'award_top3') {
            $this->award ??= $this->fixtures->award();
        }
        $result = app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse('2026-10-15'),
            $this->fixtures->tag.'-'.$purpose, $purpose === 'award_top3' ? $this->award->id : null);
        $this->assertSame('FROZEN', $result->summary['status']);

        return CallAuditBatch::findOrFail($result->summary['batch_id']);
    }

    private function confirm(CustomerSubmission $source, ?string $decision = 'confirmed', ?CallAuditBatch $batch = null): CallAuditItem
    {
        $batch ??= $this->batch;
        $item = $batch->items()->where('submission_id', $source->id)->sole();
        $claim = app(ClaimItem::class)->execute($batch->id, $item->id,
            ['purpose' => $batch->purpose, 'row_version' => $item->row_version, 'idempotency_key' => 'claim-'.$item->id]);
        app(RecordCallResult::class)->execute($batch->id, $item->id, [
            'purpose' => $batch->purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'call-'.$item->id, 'outcome' => 'wrong', 'answer_1' => false, 'answer_2' => true, 'answer_3' => true,
        ]);
        if ($decision !== null) {
            app(ResolveFinding::class)->execute($batch->id, $item->id, [
                'purpose' => $batch->purpose, 'month' => '2026-10', 'attempt_id' => $item->attempts()->sole()->id,
                'row_version' => $item->fresh()->row_version, 'resolution_version' => 0, 'idempotency_key' => 'resolve-'.$item->id,
                'decision' => $decision, 'reason' => 'PRIVATE-SIMULATION-REASON',
            ]);
        }

        return $item->fresh();
    }

    private function read(array $filters = [])
    {
        $this->reading = true;
        try {
            return $this->getJson('/admin/call-audits/penalty-simulation?'.http_build_query(array_replace(['month' => '2026-10'], $filters)));
        } finally {
            $this->reading = false;
        }
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
            $table = array_values((array) $row)[0];
            $result[$table] = [DB::table($table)->count(), (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED')];
        }

        return $result;
    }
}
