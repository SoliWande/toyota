<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Models\CallAuditBatch;
use App\Models\DealerPenaltyEntry;
use App\Models\DealerPenaltyEvent;
use App\Models\DealerPenaltyEvidence;
use App\Services\CallAudit\FreezeClock;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** NOT_RUN P09S. Requires separately authorized pre-existing DDL and scoped fixtures; never migrates. */
class DealerPenaltySchemaTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private bool $transactionStarted = false;

    private bool $restrictWrites = false;

    private array $items = [];

    private array $results = [];

    private int $actorId;

    private int $otherBatchId;

    protected function setUp(): void
    {
        if (getenv('TOYOTA_RUN_DEALER_PENALTY_SCHEMA_TESTS') !== '1') {
            $this->markTestSkipped('NOT_RUN: requires separately authorized test schema and fixture task.');
        }
        if (getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_DATABASE') !== 'toyota_testing'
            || ! getenv('APP_CONFIG_CACHE') || is_file(getenv('APP_CONFIG_CACHE'))) {
            throw new \RuntimeException('Explicit loopback test database and unused test config-cache path required.');
        }
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getConfig('host') !== '127.0.0.1' || $db->getConfig('database') !== 'toyota_testing'
            || (int) $db->getConfig('port') !== 3306
            || $db->getConfig('url') || $db->getConfig('unix_socket') || $db->getConfig('read') || $db->getConfig('write')) {
            throw new \RuntimeException('Rejected database configuration before PDO.');
        }
        TestDatabase::assertIsolated();
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@foreign_key_checks AS fk_checks, VERSION() AS version');
        $this->assertSame('toyota_testing', $identity->db);
        $this->assertSame(3306, (int) $identity->port);
        $this->assertSame(1, (int) $identity->fk_checks);
        $this->assertStringNotContainsString('MariaDB', $identity->version);
        $this->assertTrue(version_compare($identity->version, '8.0.16', '>='), 'Enforced MySQL CHECK constraints required.');
        $this->assertStringContainsString('127.0.0.1', $db->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS));
        foreach (['dealer_penalty_entries', 'dealer_penalty_evidence', 'dealer_penalty_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Prepare schema only in a separate approved DDL task.');
        }
        foreach (['call_audit_batches', 'call_audit_items', 'call_audit_attempts', 'call_audit_events'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), 'Existing audit data must be preserved; do not clear it for fixtures.');
        }
        $this->fixtures = new CallAuditFreezeFixtures;
        $actor = $this->fixtures->admin();
        $this->actorId = $actor->id;
        $this->actingAs($actor);
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false, 'realistic_demo.include_in_metrics' => false]);
        $retirement = Mockery::mock(ResetDemoRetirement::class);
        $retirement->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $retirement);
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturn(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $this->app->instance(FreezeClock::class, $clock);
        $dealer = $this->fixtures->dealer();
        $this->fixtures->submission($dealer);
        $this->fixtures->submission($dealer);
        $freeze = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-general');
        $this->assertSame('FROZEN', $freeze->summary['status']);
        $batch = CallAuditBatch::findOrFail($freeze->summary['batch_id']);
        $this->items = $batch->items()->orderBy('selection_order')->get()->all();
        foreach ($this->items as $item) {
            $claim = app(ClaimItem::class)->execute($batch->id, $item->id,
                ['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'schema-claim-'.$item->id]);
            $call = app(RecordCallResult::class)->execute($batch->id, $item->id, [
                'purpose' => 'general_audit', 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
                'idempotency_key' => 'schema-call-'.$item->id, 'outcome' => 'wrong', 'answer_1' => false, 'answer_2' => true, 'answer_3' => true,
            ]);
            app(ResolveFinding::class)->execute($batch->id, $item->id, ['purpose' => 'general_audit', 'row_version' => $call['row_version'],
                'attempt_id' => $call['attempt_id'], 'resolution_version' => 0, 'idempotency_key' => 'schema-resolution-'.$item->id,
                'decision' => 'confirmed', 'reason' => 'Isolated schema fixture']);
            $this->results[] = ['attempt_id' => $call['attempt_id'],
                'call_event_id' => $item->events()->where('event_type', 'call_result_recorded')->sole()->id,
                'resolution_event_id' => $item->events()->where('event_type', 'finding_resolution_recorded')->sole()->id];
        }
        $other = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-award', $this->fixtures->award()->id);
        $this->assertSame('FROZEN', $other->summary['status']);
        $this->otherBatchId = $other->summary['batch_id'];
        $db->beforeExecuting(function (string $sql): void {
            if (! $this->restrictWrites || preg_match('/^\s*(select|show|checksum)\b/i', $sql)
                || preg_match('/^\s*(insert into|update|delete from) `dealer_penalty_(entries|evidence|events)`(?:\s|$)/i', $sql)) {
                return;
            }
            throw new \LogicException('Schema cases forbid DDL and business/audit parent writes.');
        });
        DB::beginTransaction();
        $this->transactionStarted = true;
        $this->restrictWrites = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                DB::rollBack();
            }
            $this->restrictWrites = false;
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function test_engines_checks_and_foreign_keys_are_enforced_without_cascades(): void
    {
        foreach (['dealer_penalty_entries' => 3, 'dealer_penalty_evidence' => 7, 'dealer_penalty_events' => 3] as $table => $expectedForeignKeys) {
            $this->assertSame('InnoDB', DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->engine);
            $foreignKeys = DB::select('SELECT DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            $this->assertCount($expectedForeignKeys, $foreignKeys);
            foreach ($foreignKeys as $fk) {
                $this->assertContains($fk->DELETE_RULE, ['RESTRICT', 'NO ACTION']);
                $this->assertContains($fk->UPDATE_RULE, ['RESTRICT', 'NO ACTION']);
            }
        }
        $checks = DB::select("SELECT ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('dealer_penalty_entries','dealer_penalty_evidence','dealer_penalty_events') AND CONSTRAINT_TYPE='CHECK'");
        $this->assertCount(9, $checks);
        foreach ($checks as $check) {
            $this->assertSame('YES', $check->ENFORCED);
        }
    }

    public function test_submission_month_is_unique_and_points_month_and_period_checks_are_real(): void
    {
        $this->entry();
        $this->violates(fn () => $this->entry(), 1062);
        $this->violates(fn () => $this->entry(1, ['points' => 0]), 3819);
        $this->violates(fn () => $this->entry(1, ['monthly_scope_key' => '2026-13']), 3819);
        $this->violates(fn () => $this->entry(1, ['source_submitted_at' => '2026-11-01']), 3819);
    }

    public function test_evidence_uniques_and_existing_parent_composite_keys_are_real(): void
    {
        $a = $this->entry();
        $b = $this->entry(1);
        $this->evidence($a);
        $this->violates(fn () => $this->evidence($b, 1, ['resolution_event_id' => $this->results[0]['resolution_event_id']]), 1062);
        $this->violates(fn () => $this->evidence($a, 0, ['resolution_event_id' => $this->results[1]['resolution_event_id']]), 1062);
        $this->violates(fn () => $this->evidence($b, 1, ['batch_id' => $this->otherBatchId]), 1452);
        $this->violates(fn () => $this->evidence($b, 1, ['attempt_id' => $this->results[0]['attempt_id']]), 1452);
        $this->violates(fn () => $this->evidence($b, 1, ['resolution_version' => 0]), 3819);
    }

    public function test_events_protect_sequence_idempotency_version_counts_and_evidence_entry_scope(): void
    {
        $a = $this->entry();
        $b = $this->entry(1);
        $evidence = $this->evidence($a);
        $this->event($a, ['evidence_id' => $evidence->id]);
        $this->violates(fn () => $this->event($a, ['command_key' => 'different']), 1062);
        $this->violates(fn () => $this->event($b), 1062);
        $this->violates(fn () => $this->event($b, ['command_key' => 'different', 'evidence_id' => $evidence->id]), 1452);
        $this->violates(fn () => $this->event($b, ['command_key' => 'different', 'after_version' => 0]), 3819);
        $this->violates(fn () => $this->event($b, ['command_key' => 'different', 'evidence_count_after' => 0]), 3819);
        $this->violates(fn () => DB::table('dealer_penalty_entries')->where('id', $a->id)->delete(), 1451);
        $this->violates(fn () => DB::table('dealer_penalty_evidence')->where('id', $evidence->id)->delete(), 1451);
    }

    public function test_actors_are_restrictive_foreign_keys_and_ciphertexts_round_trip_through_mysql(): void
    {
        $entry = $this->entry();
        $evidence = $this->evidence($entry);
        $event = $this->event($entry);
        $this->violates(fn () => $this->entry(1, ['applied_by' => PHP_INT_MAX]), 1452);
        $this->violates(fn () => $this->evidence($entry, 1, ['verified_by' => PHP_INT_MAX]), 1452);
        $this->violates(fn () => $this->event($entry, ['command_key' => 'other', 'event_sequence' => 2, 'before_version' => 1,
            'after_version' => 2, 'evidence_count_before' => 1, 'previous_event_digest' => str_repeat('a', 64), 'actor_id' => PHP_INT_MAX]), 1452);
        foreach ([[$entry, 'policy_snapshot_encrypted'], [$entry, 'dealer_attribution_encrypted'],
            [$evidence, 'evidence_snapshot_encrypted'], [$event, 'intent_encrypted'], [$event, 'receipt_encrypted']] as [$model, $field]) {
            $this->assertSame(['fixture' => 'PRIVATE-SCHEMA-DATA'], $model->fresh()->$field);
            $cipher = DB::table($model->getTable())->where('id', $model->id)->value($field);
            $this->assertStringNotContainsString('PRIVATE-SCHEMA-DATA', $cipher);
            $this->assertArrayNotHasKey($field, $model->fresh()->toArray());
        }
    }

    private function entry(int $index = 0, array $overrides = []): DealerPenaltyEntry
    {
        $item = $this->items[$index];
        $row = (new DealerPenaltyEntry)->forceFill(array_replace([
            'submission_id' => $item->submission_id, 'dealer_id' => $item->dealer_id_snapshot, 'monthly_scope_key' => '2026-10',
            'period_start' => '2026-10-01', 'period_end' => '2026-11-01', 'calendar_zone' => config('app.timezone'), 'calendar_version' => 'test_v1',
            'points' => 1, 'schema_version' => 'dealer_penalty_entry_v1', 'policy_version' => DealerPenaltyEntry::POLICY,
            'projection_policy_version' => DealerPenaltyEntry::PROJECTION_POLICY, 'ranking_policy_version' => DealerPenaltyEntry::RANKING_POLICY,
            'policy_snapshot_encrypted' => ['fixture' => 'PRIVATE-SCHEMA-DATA'], 'dealer_attribution_encrypted' => ['fixture' => 'PRIVATE-SCHEMA-DATA'],
            'attribution_version' => 'test_v1', 'attribution_observed_at' => '2026-11-10', 'source_submitted_at' => '2026-10-02',
            'source_fingerprint' => $item->source_fingerprint, 'fingerprint_version' => $item->fingerprint_version,
            'applied_by' => $this->actorId, 'applied_at' => '2026-11-12', 'effective_at' => '2026-11-12', 'created_at' => '2026-11-12',
            'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => 'test-key',
        ], $overrides));
        $row->save();

        return $row;
    }

    private function evidence(DealerPenaltyEntry $entry, int $index = 0, array $overrides = []): DealerPenaltyEvidence
    {
        $item = $this->items[$index];
        $row = (new DealerPenaltyEvidence)->forceFill(array_replace($this->results[$index] + [
            'entry_id' => $entry->id, 'batch_id' => $item->batch_id, 'item_id' => $item->id, 'resolution_version' => 1, 'item_version_used' => 3,
            'schema_version' => 'dealer_penalty_evidence_v1', 'verification_version' => 'test_v1', 'verified_by' => $this->actorId,
            'call_recorded_at' => '2026-11-10 12:00:00', 'resolution_recorded_at' => '2026-11-10 12:00:00', 'verified_at' => '2026-11-12',
            'evidence_snapshot_encrypted' => ['fixture' => 'PRIVATE-SCHEMA-DATA'], 'evidence_digest' => str_repeat('a', 64),
            'digest_key_id' => 'test-key', 'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => 'test-key',
        ], $overrides));
        $row->save();

        return $row;
    }

    private function event(DealerPenaltyEntry $entry, array $overrides = []): DealerPenaltyEvent
    {
        $row = (new DealerPenaltyEvent)->forceFill(array_replace([
            'entry_id' => $entry->id, 'actor_id' => $this->actorId, 'event_sequence' => 1, 'event_type' => 'entry_created',
            'event_schema_version' => 'dealer_penalty_event_v1', 'command_name' => 'apply_dealer_penalty', 'command_key' => 'schema-request',
            'event_ordinal' => 0, 'before_version' => 0, 'after_version' => 1, 'evidence_count_before' => 0, 'evidence_count_after' => 1,
            'reason_code' => 'confirmed_finding', 'policy_version' => DealerPenaltyEntry::POLICY, 'occurred_at' => '2026-11-12',
            'correlation_id' => 'test-correlation', 'metadata' => ['fixture' => true],
            'intent_encrypted' => ['fixture' => 'PRIVATE-SCHEMA-DATA'], 'receipt_encrypted' => ['fixture' => 'PRIVATE-SCHEMA-DATA'],
            'payload_digest' => str_repeat('a', 64), 'event_digest' => str_repeat('b', 64), 'digest_key_id' => 'test-key',
            'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => 'test-key',
        ], $overrides));
        $row->save();

        return $row;
    }

    private function violates(callable $operation, int $code): void
    {
        try {
            $operation();
            $this->fail('Expected database constraint rejection.');
        } catch (QueryException $error) {
            $this->assertSame($code, (int) $error->errorInfo[1]);
        }
    }
}
