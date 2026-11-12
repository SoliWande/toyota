<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ImpactPreviewReadModel;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditImpactPreviewTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private User $sales;

    private Dealer $dealer;

    private Dealer $otherDealer;

    private CustomerSubmission $source;

    private CustomerSubmission $otherSource;

    private CallAuditBatch $batch;

    private CallAuditItem $item;

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
        $this->dealer = $this->fixtures->dealer();
        $this->otherDealer = $this->fixtures->dealer();
        $this->sales = $this->fixtures->admin();
        $this->fixtures->change($this->sales, ['role' => 'sales', 'dealer_id' => $this->dealer->id]);
        $this->source = $this->fixtures->submission($this->dealer, ['source' => 'sales', 'sales_id' => $this->sales->id]);
        $this->otherSource = $this->fixtures->submission($this->otherDealer);
        $this->fixtures->submission($this->otherDealer);
        $this->batch = $this->freeze();
        $this->item = $this->batch->items()->where('submission_id', $this->source->id)->sole();
        $this->record($this->item);
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && (! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)
                || preg_match('/for update|for share|lock in share mode/i', $sql))) {
                throw new \RuntimeException('Impact GET cannot mutate or lock.');
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            if ($this->award !== null) {
                // Only the award returned by this run's tagged fixture; never clear existing snapshots.
                DB::table('award_winners')->where('award_id', $this->award->id)->delete();
            }
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('decisions')]
    public function test_only_valid_current_confirmed_is_an_impact_candidate(?string $decision): void
    {
        if ($decision !== null) {
            $this->resolve($this->item, $decision);
        }
        $response = $this->read()->assertOk();
        $response->assertJsonPath('counts.candidate_count', $decision === 'confirmed' ? 1 : 0);
        if ($decision === 'confirmed') {
            $response->assertJsonPath('data.0.submission_id', $this->source->id)
                ->assertJsonPath('data.0.evidence.0.purpose', 'general_audit')
                ->assertJsonPath('data.0.evidence.0.frozen_attribution.dealer_id', $this->dealer->id)
                ->assertJsonPath('data.0.evidence.0.resolution_version', 1);
            $this->read([], true)->assertOk()->assertJsonPath('counts.candidate_count', 1);
        } else {
            $this->read([], true)->assertNotFound();
        }
    }

    public static function decisions(): array
    {
        return [[null], ['confirmed'], ['dismissed'], ['needs_review']];
    }

    public function test_current_resolution_after_needs_review_is_used_not_prior_event(): void
    {
        $this->resolve($this->item, 'needs_review');
        $this->resolve($this->item, 'confirmed', 1);
        $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 1)->assertJsonPath('data.0.evidence.0.resolution_version', 2);
    }

    public function test_distinct_submissions_are_counted_once_each(): void
    {
        $other = $this->batch->items()->where('submission_id', $this->otherSource->id)->sole();
        $this->record($other);
        $this->resolve($other);
        $this->resolve($this->item);
        $response = $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 2)->assertJsonPath('counts.confirmed_evidence_count', 2);
        $this->assertSame([$this->source->id, $this->otherSource->id], array_column($response->json('data'), 'submission_id'));
    }

    public function test_resolution_pointing_at_uncalled_item_is_not_a_trusted_candidate(): void
    {
        $this->resolve($this->item);
        $row = (array) DB::table('call_audit_events')->where('item_id', $this->item->id)->where('event_type', 'finding_resolution_recorded')->sole();
        unset($row['id']);
        $row['item_id'] = $this->batch->items()->where('submission_id', $this->otherSource->id)->sole()->id;
        $row['attempt_id'] = null;
        $row['event_sequence']++;
        $row['command_key'] = 'fake-confirmed';
        DB::table('call_audit_events')->insert($row);
        $before = $this->snapshot();
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'impact_preview_unavailable']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_empty_frozen_batch_has_zero_candidates_and_no_assumed_points(): void
    {
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-09-15'), $this->fixtures->tag.'-empty');
        $this->assertSame('FROZEN', $result->summary['status']);
        $this->read(['month' => '2026-09'])->assertOk()->assertJsonPath('counts.candidate_count', 0)
            ->assertJsonPath('data', [])->assertJsonPath('current_rankings', ['dealers' => [], 'sales' => []]);
    }

    public function test_confirmed_item_after_first_fifty_findings_is_not_lost_to_reader_pagination(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->fixtures->submission($this->dealer, ['submitted_at' => '2026-09-02 12:00:00']);
        }
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-09-15'), $this->fixtures->tag.'-large');
        $this->assertSame('FROZEN', $result->summary['status']);
        $batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $items = $batch->items()->orderBy('selection_order')->get();
        foreach ($items as $item) {
            $this->record($item);
        }
        $last = $items->last();
        $this->resolve($last);
        $this->read(['month' => '2026-09'])->assertOk()->assertJsonPath('counts.candidate_count', 1)
            ->assertJsonPath('data.0.submission_id', (int) $last->submission_id);
    }

    public function test_overlap_deduplicates_submission_but_preserves_independent_purposes(): void
    {
        $awardBatch = $this->freeze('award_top3');
        $awardItem = $awardBatch->items()->where('submission_id', $this->source->id)->sole();
        $this->record($awardItem);
        $this->resolve($this->item);
        $this->resolve($awardItem);
        $all = $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 1)
            ->assertJsonPath('counts.confirmed_evidence_count', 2)->assertJsonPath('counts.general_audit_evidence_count', 1)
            ->assertJsonPath('counts.award_top3_evidence_count', 1)->json('data.0');
        $this->assertSame([$this->item->id, $awardItem->id], array_column($all['evidence'], 'item_id'));
        foreach (['general_audit', 'award_top3'] as $purpose) {
            $this->read(['purpose' => $purpose])->assertOk()->assertJsonPath('counts.candidate_count', 1)
                ->assertJsonPath('counts.confirmed_evidence_count', 1)->assertJsonPath('data.0.evidence.0.purpose', $purpose);
        }
        $this->read([], true)->assertOk()->assertJsonPath('counts.confirmed_evidence_count', 1);
    }

    public function test_dismissed_c07_does_not_cancel_confirmed_c08_or_duplicate_impact(): void
    {
        $batch = $this->freeze('award_top3');
        $item = $batch->items()->where('submission_id', $this->source->id)->sole();
        $this->record($item);
        $this->resolve($item, 'dismissed');
        $this->resolve($this->item);
        $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 1)->assertJsonPath('counts.confirmed_evidence_count', 1);
        $this->read(['purpose' => 'award_top3'])->assertOk()->assertJsonPath('data', []);
    }

    public function test_live_rank_uses_existing_window_and_historic_dealer_is_not_sales_current_dealer(): void
    {
        $this->resolve($this->item);
        $this->fixtures->change($this->sales, ['dealer_id' => $this->otherDealer->id]);
        $response = $this->read()->assertOk();
        $response->assertJsonPath('current_rankings.dealers.0.id', $this->dealer->id)
            ->assertJsonPath('current_rankings.dealers.0.score', 1)->assertJsonPath('current_rankings.dealers.0.rank', 2)
            ->assertJsonPath('current_rankings.sales.0.id', $this->sales->id)->assertJsonPath('current_rankings.sales.0.score', 1)
            ->assertJsonPath('current_rankings.sales.0.dealer_id', $this->otherDealer->id)
            ->assertJsonPath('data.0.current_submission.dealer_id', $this->dealer->id)
            ->assertJsonPath('data.0.evidence.0.frozen_attribution.dealer_id', $this->dealer->id)
            ->assertJsonPath('data.0.live_contribution', ['dealer' => true, 'sales' => true]);
    }

    #[DataProvider('boundaries')]
    public function test_current_month_membership_uses_existing_inclusive_start_exclusive_end(string $at, bool $inMonth): void
    {
        $this->resolve($this->item);
        $this->fixtures->change($this->source, ['submitted_at' => $at]);
        $this->read()->assertOk()->assertJsonPath('data.0.current_submission.in_month', $inMonth)
            ->assertJsonPath('data.0.evidence.0.comparison.submitted_at_changed', true)
            ->assertJsonPath('data.0.live_contribution.dealer', $inMonth)
            ->assertJsonPath('data.0.live_contribution.sales', $inMonth);
    }

    public static function boundaries(): array
    {
        return [['2026-10-01 00:00:00', true], ['2026-10-31 23:59:59', true], ['2026-11-01 00:00:00', false], ['2026-09-30 23:59:59', false]];
    }

    public function test_changed_source_dealer_status_and_sales_role_are_context_not_penalty(): void
    {
        $this->resolve($this->item);
        $this->fixtures->change($this->source, ['dealer_id' => $this->otherDealer->id, 'status' => 'rejected']);
        $response = $this->read()->assertOk()->assertJsonPath('data.0.current_submission.status', 'rejected')
            ->assertJsonPath('data.0.current_submission.dealer_id', $this->otherDealer->id)
            ->assertJsonPath('data.0.evidence.0.frozen_attribution.dealer_id', $this->dealer->id)
            ->assertJsonPath('data.0.evidence.0.comparison.dealer_changed', true)
            ->assertJsonPath('data.0.live_contribution', ['dealer' => false, 'sales' => false]);
        $this->assertNull(collect($response->json('current_rankings.dealers'))->firstWhere('id', $this->dealer->id)['rank']);
        $this->fixtures->change($this->source, ['status' => 'approved']);
        $this->fixtures->change($this->sales, ['role' => 'dealer']);
        $this->read()->assertOk()->assertJsonPath('data.0.live_contribution', ['dealer' => false, 'sales' => false]);
    }

    #[DataProvider('awardStates')]
    public function test_award_draft_uses_real_results_and_published_uses_immutable_snapshots(bool $published): void
    {
        $this->resolve($this->item);
        $award = $this->award();
        $baseline = app(AwardResults::class)->preview($award);
        if ($published) {
            DB::transaction(function () use ($award, $baseline) {
                foreach (collect($baseline['dealers'])->merge($baseline['sales']) as $row) {
                    (new AwardWinner)->forceFill(['award_id' => $award->id] + $row)->save();
                }
            });
            $this->fixtures->change($award, ['published_at' => '2026-11-03 12:00:00', 'published_by' => $this->admin->id]);
            $publishedReader = Mockery::mock(AwardResults::class);
            $publishedReader->shouldNotReceive('preview');
            $this->app->instance(AwardResults::class, $publishedReader);
        }
        $this->fixtures->change($this->otherSource, ['status' => 'rejected']);
        $before = $this->snapshot();
        $response = $this->read()->assertOk()->assertJsonPath('awards.0.status', $published ? 'published' : 'draft')
            ->assertJsonPath('awards.0.result_source', $published ? 'published_snapshot' : 'live_preview');
        $record = collect($response->json('awards.0.records'))->first(fn ($row) => $row['winner_type'] === 'dealer' && $row['dealer_id'] === $this->dealer->id);
        $this->assertSame($published ? 2 : 1, $record['rank']);
        $this->assertSame([$this->source->id], $record['related_submission_ids']);
        $this->assertSame($before, $this->snapshot());
    }

    public static function awardStates(): array
    {
        return ['draft' => [false], 'published' => [true]];
    }

    #[DataProvider('deniedAccounts')]
    public function test_authorization_on_both_gets(?string $role, ?string $status): void
    {
        $this->resolve($this->item);
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $actor = $this->fixtures->admin();
            $values = ['role' => $role, 'status' => $status];
            if ($role !== 'admin') {
                $values['dealer_id'] = $this->dealer->id;
            }
            $this->fixtures->change($actor, $values);
            $this->actingAs($actor->fresh());
        }
        $this->read()->assertStatus($role === null ? 401 : 403);
        $this->read([], true)->assertStatus($role === null ? 401 : 403);
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'], ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_fresh_authorization_is_required_even_from_direct_reader_and_other_admin_is_allowed(): void
    {
        $this->resolve($this->item);
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->read()->assertForbidden();
        try {
            app(ImpactPreviewReadModel::class)->read($this->admin, ['month' => '2026-10']);
            $this->fail('Stale Auth must not authorize blocked Admin.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->actingAs($this->fixtures->admin());
        $this->read()->assertOk()->assertJsonPath('counts.candidate_count', 1);
    }

    #[DataProvider('tampering')]
    public function test_invalid_evidence_never_becomes_candidate_or_leaks_private_data(string $kind): void
    {
        $this->resolve($this->item);
        $event = $this->item->events()->where('event_type', 'finding_resolution_recorded')->sole();
        if ($kind === 'call') {
            DB::table('call_audit_events')->where('item_id', $this->item->id)->where('event_type', 'call_result_recorded')->update(['event_digest' => str_repeat('a', 64)]);
        } elseif ($kind === 'source') {
            DB::table('call_audit_items')->where('id', $this->item->id)->update(['source_fingerprint' => str_repeat('a', 64)]);
        } elseif ($kind === 'snapshot') {
            $source = $this->item->source_snapshot_encrypted;
            $source['dealer_id'] = $this->otherDealer->id;
            $cipher = (new CallAuditItem)->forceFill(['source_snapshot_encrypted' => $source])->getAttributes()['source_snapshot_encrypted'];
            DB::table('call_audit_items')->where('id', $this->item->id)->update(['source_snapshot_encrypted' => $cipher]);
        } elseif ($kind === 'receipt') {
            $private = $event->private_payload_encrypted;
            $private['input']['decision'] = 'dismissed';
            $cipher = (new CallAuditEvent)->forceFill(['private_payload_encrypted' => $private])->getAttributes()['private_payload_encrypted'];
            DB::table('call_audit_events')->where('id', $event->id)->update(['private_payload_encrypted' => $cipher]);
        } else {
            $fields = ['digest' => ['event_digest' => str_repeat('c', 64)], 'version' => ['after_version' => 7],
                'type' => ['event_type' => 'changed'], 'anchor' => ['attempt_id' => null], 'cipher' => ['private_payload_encrypted' => 'invalid']];
            DB::table('call_audit_events')->where('id', $event->id)->update($fields[$kind]);
        }
        $before = $this->snapshot();
        Log::spy();
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'impact_preview_unavailable']);
        $this->read([], true)->assertStatus(409)->assertDontSee('PRIVATE-IMPACT-REASON');
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('error');
    }

    public static function tampering(): array
    {
        return array_map(fn ($kind) => [$kind], ['call', 'source', 'snapshot', 'receipt', 'digest', 'version', 'type', 'anchor', 'cipher']);
    }

    public function test_scope_idor_empty_filters_and_mutation_methods_are_rejected(): void
    {
        $this->resolve($this->item);
        $this->read(['purpose' => 'award_top3'], true)->assertNotFound();
        $this->read(['month' => '2026-09'], true)->assertNotFound();
        $this->read(['month' => '2026-09'])->assertOk()->assertJsonPath('data', []);
        $this->getJson('/admin/call-audits/'.$this->batch->id.'/findings/999999999/impact-preview?month=2026-10&purpose=general_audit')->assertNotFound();
        $other = $this->batch->items()->where('submission_id', $this->otherSource->id)->sole();
        $this->getJson('/admin/call-audits/'.$this->batch->id.'/findings/'.$other->id.'/impact-preview?month=2026-10&purpose=general_audit')->assertNotFound();
        $awardBatch = $this->freeze('award_top3');
        $awardItem = $awardBatch->items()->where('submission_id', $this->source->id)->sole();
        $this->getJson('/admin/call-audits/'.$this->batch->id.'/findings/'.$awardItem->id.'/impact-preview?month=2026-10&purpose=general_audit')->assertNotFound();
        foreach ([['phone' => 'PRIVATE-PHONE'], ['month' => '2026-13'], ['month' => null], ['purpose' => 'manager'], ['page' => 1]] as $filters) {
            $this->read($filters)->assertStatus(422)->assertExactJson(['error' => 'invalid_impact_filters'])->assertDontSee('PRIVATE-PHONE');
        }
        $this->postJson('/admin/call-audits/impact-preview', [])->assertStatus(405);
        $this->postJson($this->detailUrl(), [])->assertStatus(405);
    }

    public function test_get_is_read_only_masked_allowlist_and_has_no_sanction_or_fake_point_delta(): void
    {
        $this->resolve($this->item);
        $this->award();
        $before = $this->snapshot();
        foreach ([ResolveFinding::class, ClaimItem::class, RecordCallResult::class, CreateAndFreezeBatch::class] as $writer) {
            $this->app->bind($writer, fn () => throw new \RuntimeException('Impact GET must never resolve writers.'));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ([false, true, false] as $detail) {
            $response = $this->read([], $detail)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach ([$this->source->phone, $this->source->license_plate, $this->source->customer_name, $this->admin->email,
                'PRIVATE-IMPACT-REASON', 'seed', 'manifest', 'snapshot_encrypted', 'private_payload', 'payload_digest',
                'reason', 'note', 'facebook', 'phone', 'new_score', 'score_delta', 'penalty', 'threshold'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $queries = implode(' ', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['seed_encrypted', 'manifest_encrypted', 'note_encrypted'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $queries);
        }
        $this->assertSame($before, $this->snapshot());
        foreach (['index', 'show'] as $name) {
            $route = app('router')->getRoutes()->getByName('admin.call-audits.impact.'.$name);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            foreach (['web', 'auth', 'auth.session', 'role:admin', 'account.active', 'throttle:admin-writes'] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
        }
    }

    private function freeze(string $purpose = 'general_audit'): CallAuditBatch
    {
        $result = app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse('2026-10-15'),
            $this->fixtures->tag.'-'.$purpose, $purpose === 'award_top3' ? $this->award()->id : null);
        $this->assertSame('FROZEN', $result->summary['status']);

        return CallAuditBatch::findOrFail($result->summary['batch_id']);
    }

    private function award(): Award
    {
        return $this->award ??= $this->fixtures->award();
    }

    private function record(CallAuditItem $item): void
    {
        $claim = app(ClaimItem::class)->execute($item->batch_id, $item->id,
            ['purpose' => $item->batch->purpose, 'row_version' => $item->row_version, 'idempotency_key' => 'claim-'.$item->id]);
        app(RecordCallResult::class)->execute($item->batch_id, $item->id, [
            'purpose' => $item->batch->purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'call-'.$item->id, 'outcome' => 'wrong', 'answer_1' => false, 'answer_2' => true, 'answer_3' => true,
        ]);
    }

    private function resolve(CallAuditItem $item, string $decision = 'confirmed', int $version = 0): void
    {
        app(ResolveFinding::class)->execute($item->batch_id, $item->id, [
            'purpose' => $item->batch->purpose, 'month' => $item->batch->monthly_scope_key, 'attempt_id' => $item->attempts()->sole()->id,
            'row_version' => $item->fresh()->row_version, 'resolution_version' => $version, 'idempotency_key' => 'resolve-'.$item->id.'-'.$version,
            'decision' => $decision, 'reason' => 'PRIVATE-IMPACT-REASON',
        ]);
    }

    private function detailUrl(): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/findings/'.$this->item->id.'/impact-preview';
    }

    private function read(array $filters = [], bool $detail = false)
    {
        $this->reading = true;
        try {
            $defaults = ['month' => '2026-10'] + ($detail ? ['purpose' => 'general_audit'] : []);

            return $this->getJson(($detail ? $this->detailUrl() : '/admin/call-audits/impact-preview').'?'.http_build_query(array_replace($defaults, $filters)));
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
