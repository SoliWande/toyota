<?php

namespace Tests\Feature;

use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\FreezeResult;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class CallAuditFreezeTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new CallAuditFreezeFixtures;
        $this->actingAs($this->fixtures->admin());
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
        $receipt = Mockery::mock(ResetDemoRetirement::class);
        $receipt->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $receipt);
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturn(CarbonImmutable::parse('2026-11-10', 'UTC'));
        $this->app->instance(FreezeClock::class, $clock);
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('generalCounts')]
    public function test_general_counts_atomic_items_event_and_database_reproduction(int $n): void
    {
        $dealer = $n ? $this->fixtures->dealer() : null;
        for ($i = 0; $i < $n; $i++) {
            $this->fixtures->submission($dealer);
        }
        $result = $this->freeze();
        $this->assertSame('FROZEN', $result->summary['status'], json_encode($result));
        $this->assertSame(min(60, $n), $result->summary['actual_count']);
        $batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $this->assertSame(60, $batch->requested_count);
        $this->assertSame(min(60, $n), $batch->items()->count());
        $this->assertSame(1, $batch->events()->count());
        $this->assertSame(0, $batch->review_completion_evidence_encrypted['pending_count']);
        $this->assertFalse($batch->review_completion_evidence_encrypted['permanent_period_close']);
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($batch->id)->summary['status']);
        $this->assertSame((int) auth()->id(), (int) $batch->created_by);
        $this->assertSame((int) auth()->id(), (int) $batch->frozen_by);
        $this->assertSame((int) auth()->id(), (int) $batch->events()->first()->actor_id);
        $this->assertSame('frozen', $batch->lifecycle_state);
    }

    public static function generalCounts(): array
    {
        return array_map(fn ($n) => [$n], [0, 1, 59, 60, 61]);
    }

    #[DataProvider('dealerCounts')]
    public function test_top3_strata_use_real_eligible_counts(int $n): void
    {
        // Dealer with approved but invalid phone keeps a real Top3 stratum with N=0.
        foreach (range(1, 3) as $dealerNumber) {
            $dealer = $this->fixtures->dealer();
            for ($i = 0; $i < max(1, $n); $i++) {
                $row = $this->fixtures->submission($dealer);
                if ($n === 0) {
                    $this->fixtures->change($row, ['phone' => 'invalid', 'phone_normalized' => 'invalid']);
                }
            }
        }
        $award = $this->fixtures->award();
        $result = $this->freeze('award_top3', 'top3', $award->id);
        $this->assertSame('FROZEN', $result->summary['status'], json_encode($result));
        $batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $this->assertSame(15, $batch->requested_count);
        $this->assertSame(3 * min($n, 5), $batch->actual_count);
        $this->assertSame([min($n, 5), min($n, 5), min($n, 5)], array_column($batch->strata_snapshot_encrypted, 'actual_count'));
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($batch->id)->summary['status']);
    }

    public static function dealerCounts(): array
    {
        return array_map(fn ($n) => [$n], [0, 1, 4, 5, 6]);
    }

    public function test_month_must_end_and_pending_review_blocks_freeze(): void
    {
        $future = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-11-01'), $this->fixtures->tag.'-future');
        $this->assertSame('month_not_ended', $future->summary['code']);
        $dealer = $this->fixtures->dealer();
        $pending = $this->fixtures->submission($dealer, ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        $this->assertSame('pending_review_in_month', $this->freeze()->summary['code']);
        $this->fixtures->change($pending, ['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => '2026-11-09']);
        $this->assertSame('FROZEN', $this->freeze()->summary['status']);
    }

    public function test_boundaries_ignore_pending_outside_month_and_allow_end_instant(): void
    {
        $dealer = $this->fixtures->dealer();
        $this->fixtures->submission($dealer, ['submitted_at' => '2026-09-30 23:59:59', 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        $this->fixtures->submission($dealer, ['submitted_at' => '2026-11-01', 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturn(CarbonImmutable::parse('2026-11-01', 'UTC'));
        $this->app->instance(FreezeClock::class, $clock);
        $this->assertSame('FROZEN', $this->freeze()->summary['status']);
    }

    public function test_future_review_evidence_or_an_outer_transaction_cannot_bypass_the_gate(): void
    {
        $row = $this->fixtures->submission($this->fixtures->dealer(), ['reviewed_at' => '2026-11-11']);
        $this->assertSame('review_timestamp_outside_cutoff', $this->freeze()->summary['code']);
        $this->assertSame(0, CallAuditBatch::count());
        $this->fixtures->change($row, ['reviewed_at' => '2026-11-09']);
        $result = DB::transaction(fn () => $this->freeze());
        $this->assertSame('requires_top_level_transaction', $result->summary['code']);
        $this->assertSame(0, CallAuditBatch::count());
    }

    public function test_same_request_retries_do_not_call_planner_again_and_changed_payload_conflicts(): void
    {
        $first = $this->freeze();
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldNotReceive('now');
        $this->app->instance(FreezeClock::class, $clock);
        $retry = $this->freeze();
        $this->assertSame($first->summary['batch_id'], $retry->summary['batch_id']);
        $this->assertFalse($retry->summary['created']);
        $otherMonth = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-09-01'), $this->fixtures->tag.'-request');
        $this->assertSame('idempotency_payload_conflict', $otherMonth->summary['code']);
        $this->assertSame('generation_already_frozen', $this->freeze('general_audit', 'another')->summary['code']);
    }

    public function test_late_approval_source_drift_and_new_submissions_do_not_change_stored_selection(): void
    {
        $dealer = $this->fixtures->dealer();
        $original = $this->fixtures->submission($dealer);
        $frozen = $this->freeze();
        $this->fixtures->change($original, ['phone' => '0912345678', 'phone_normalized' => '0912345678']);
        $this->fixtures->submission($dealer, ['reviewed_at' => '2026-11-10 12:00:00']);
        $this->assertSame(1, $this->freeze()->summary['actual_count']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $verified = app(ReproductionVerifier::class)->verify($frozen->summary['batch_id']);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->assertSame('VERIFIED', $verified->summary['status']);
        $this->assertStringNotContainsString('customer_submissions', implode(' ', $queries));
        $this->assertStringNotContainsString('award_winners', implode(' ', $queries));
    }

    public function test_mid_transaction_item_or_event_failure_leaves_no_partial_batch(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        CallAuditEvent::creating(function () {
            throw new \RuntimeException('private-manifest-must-not-leak');
        });
        try {
            $result = $this->freeze();
            $this->assertSame('freeze_failed', $result->summary['code']);
            $this->assertStringNotContainsString('private-manifest', json_encode($result));
            $this->assertSame(0, CallAuditBatch::count());
            $this->assertSame(0, CallAuditItem::count());
            $this->assertSame(0, CallAuditEvent::count());
        } finally {
            CallAuditEvent::flushEventListeners();
            CallAuditEvent::clearBootedModels();
        }
    }

    public function test_changed_source_during_planning_aborts_instead_of_rerolling(): void
    {
        $row = $this->fixtures->submission($this->fixtures->dealer());
        $rangeReads = 0;
        DB::connection()->beforeExecuting(function (string $sql) use (&$rangeReads, $row): void {
            if (str_contains($sql, 'from `customer_submissions`') && str_contains($sql, 'for update')
                && str_contains($sql, '`submitted_at` >=')) {
                $rangeReads++;
                if ($rangeReads === 2) {
                    // Same-transaction simulation immediately before the final current-read recheck.
                    DB::table('customer_submissions')->where('id', $row->id)->update(['phone' => '0912345678', 'phone_normalized' => '0912345678']);
                }
            }
        });
        $result = $this->freeze();
        $this->assertSame('source_changed_during_freeze', $result->summary['code']);
        $this->assertSame(0, CallAuditBatch::count());
        $this->assertSame($row->phone, $row->fresh()->phone);
    }

    public function test_inactive_session_actor_is_rechecked_and_response_hides_private_material(): void
    {
        $actor = auth()->user();
        $this->fixtures->change($actor, ['status' => 'blocked']);
        $this->assertSame('admin_inactive_or_forbidden', $this->freeze()->summary['code']);
        $this->fixtures->change($actor, ['status' => 'active']);
        $result = $this->freeze();
        $json = json_encode($result);
        foreach (['seed', 'manifest', 'phone', 'facebook'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $raw = DB::table('call_audit_batches')->where('id', $batch->id)->first();
        $this->assertNotSame($batch->seed_encrypted, $raw->seed_encrypted);
        $this->assertStringNotContainsString('candidates', $raw->manifest_encrypted);
        $this->assertNotContains('seed_encrypted', array_keys($batch->toArray()));
        $this->fixtures->change($actor, ['status' => 'blocked']);
        $this->assertSame('verification_forbidden', app(ReproductionVerifier::class)->verify($batch->id)->summary['code']);
    }

    #[DataProvider('corruptions')]
    public function test_reproduction_rejects_corrupted_manifest_item_seed_or_event(string $kind): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $result = $this->freeze();
        $id = $result->summary['batch_id'];
        match ($kind) {
            'fingerprint' => DB::table('call_audit_items')->where('batch_id', $id)->update(['source_fingerprint' => str_repeat('0', 64)]),
            'order' => DB::table('call_audit_items')->where('batch_id', $id)->update(['selection_order' => 2]),
            'manifest' => DB::table('call_audit_batches')->where('id', $id)->update(['manifest_digest' => str_repeat('0', 64)]),
            'seed' => DB::table('call_audit_batches')->where('id', $id)->update(['seed_encrypted' => 'tampered']),
            'event' => DB::table('call_audit_events')->where('batch_id', $id)->update(['metadata' => json_encode(['purpose' => 'wrong'])]),
        };
        $this->assertSame('BLOCKED', app(ReproductionVerifier::class)->verify($id)->summary['status']);
    }

    public static function corruptions(): array
    {
        return [['fingerprint'], ['order'], ['manifest'], ['seed'], ['event']];
    }

    private function freeze(string $purpose = 'general_audit', string $key = 'request', ?int $awardId = null): FreezeResult
    {
        return app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse('2026-10-15', 'UTC'), $this->fixtures->tag.'-'.$key, $awardId);
    }
}
