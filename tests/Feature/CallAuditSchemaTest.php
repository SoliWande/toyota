<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\CallAuditAttempt;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\TestDatabase;
use Tests\TestCase;

// P09F: pre-existing schema, lazy parent fixtures and audit writes inside rollback transactions.
class CallAuditSchemaTest extends TestCase
{
    private bool $transactionStarted = false;

    private ?User $admin = null;

    private ?Dealer $dealer = null;

    private bool $creatingParentFixture = false;

    private array $fixtureAdminIds = [];

    private int $submissionSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::assertIsolated();
        $connection = DB::connection();
        $this->assertSame('127.0.0.1', $connection->getConfig('host'));
        $this->assertEmpty($connection->getConfig('url'));
        $this->assertEmpty($connection->getConfig('unix_socket'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));
        $this->assertSame('toyota_testing', $connection->getConfig('database'));
        $connection->beforeExecuting(function (string $sql, array $bindings) use ($connection): void {
            if (preg_match('/^\s*(select|show|explain)\b/i', $sql)) {
                return;
            }
            if ($this->transactionStarted && $connection->transactionLevel() > 0) {
                if (preg_match('/^\s*(insert into|update|delete from)\s+`?(call_audit_batches|call_audit_items|call_audit_attempts|call_audit_events)`?\s/i', $sql)) {
                    return;
                }
                if ($this->creatingParentFixture
                    && (preg_match('/^\s*insert into\s+`?(users|dealers|customer_submissions|awards)`?\s/i', $sql)
                        || preg_match('/^\s*(SAVEPOINT trans[0-9]+|ROLLBACK TO SAVEPOINT trans[0-9]+)$/i', $sql))) {
                    return;
                }
                if (preg_match('/^delete from `users` where `id` = \?$/i', $sql)
                    && count($bindings) === 1 && in_array($bindings[0], $this->fixtureAdminIds, true)) {
                    return;
                }
            }

            throw new \LogicException('P09F forbids DDL, nontransactional writes and changes to existing parent records.');
        });
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertStringContainsString('127.0.0.1', $connection->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS));
        foreach (['call_audit_batches', 'call_audit_items', 'call_audit_attempts', 'call_audit_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Prepare the schema in a separately authorized step: '.$table);
        }
        DB::beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_schema_has_expected_engines_enforced_checks_and_actor_ciphertext_columns(): void
    {
        foreach (['call_audit_batches', 'call_audit_items', 'call_audit_attempts', 'call_audit_events'] as $table) {
            $meta = DB::selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
            $this->assertSame('InnoDB', $meta->ENGINE);
        }
        $checks = DB::select("SELECT CONSTRAINT_NAME, ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('call_audit_batches','call_audit_items','call_audit_attempts','call_audit_events') AND CONSTRAINT_TYPE = 'CHECK'");
        $this->assertCount(10, $checks);
        foreach ($checks as $check) {
            $this->assertSame('YES', $check->ENFORCED);
        }
        foreach ([
            'call_audit_batches' => ['created_by', 'frozen_by'],
            'call_audit_attempts' => ['actor_id'], 'call_audit_events' => ['actor_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $meta = DB::selectOne('SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
                $this->assertSame('bigint', $meta->DATA_TYPE);
                $this->assertSame('NO', $meta->IS_NULLABLE);
            }
        }
        foreach ([
            'call_audit_batches' => ['seed_encrypted', 'manifest_encrypted', 'review_completion_evidence_encrypted'],
            'call_audit_items' => ['source_snapshot_encrypted', 'dealer_snapshot_encrypted'],
            'call_audit_attempts' => ['note_encrypted'], 'call_audit_events' => ['private_payload_encrypted'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $meta = DB::selectOne('SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
                $this->assertSame('longtext', $meta->DATA_TYPE);
            }
        }
    }

    public function test_unique_and_queue_indexes_preserve_column_order(): void
    {
        foreach ([
            ['call_audit_batches', 'cab_scope_generation_uq', ['purpose', 'monthly_scope_key', 'generation'], 0],
            ['call_audit_batches', 'cab_create_request_uq', ['created_by', 'create_request_key'], 0],
            ['call_audit_items', 'cai_batch_submission_uq', ['batch_id', 'submission_id'], 0],
            ['call_audit_items', 'cai_batch_order_uq', ['batch_id', 'selection_order'], 0],
            ['call_audit_items', 'cai_queue_idx', ['batch_id', 'queue_state', 'retry_due_at', 'selection_order'], 1],
            ['call_audit_items', 'cai_claim_expiry_idx', ['claim_expires_at'], 1],
            ['call_audit_attempts', 'caa_item_number_uq', ['item_id', 'attempt_no'], 0],
            ['call_audit_attempts', 'caa_request_uq', ['item_id', 'actor_id', 'idempotency_key'], 0],
            ['call_audit_events', 'cae_batch_sequence_uq', ['batch_id', 'event_sequence'], 0],
            ['call_audit_events', 'cae_command_event_uq', ['batch_id', 'actor_id', 'command_name', 'command_key', 'event_ordinal'], 0],
        ] as [$table, $index, $columns, $nonUnique]) {
            $rows = DB::select('SELECT COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [$table, $index]);
            $this->assertSame($columns, array_column($rows, 'COLUMN_NAME'));
            $this->assertSame([$nonUnique], array_values(array_unique(array_column($rows, 'NON_UNIQUE'))));
        }
    }

    public function test_composite_fk_targets_and_restrictive_parent_rules_are_installed(): void
    {
        foreach ([
            'cae_item_batch_fk' => ['call_audit_items', ['item_id', 'batch_id'], ['id', 'batch_id']],
            'cae_attempt_item_fk' => ['call_audit_attempts', ['attempt_id', 'item_id'], ['id', 'item_id']],
        ] as $constraint => [$parent, $childColumns, $parentColumns]) {
            $rows = DB::select('SELECT COLUMN_NAME, REFERENCED_COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ? ORDER BY ORDINAL_POSITION', [$constraint]);
            $this->assertSame($childColumns, array_column($rows, 'COLUMN_NAME'));
            $this->assertSame($parentColumns, array_column($rows, 'REFERENCED_COLUMN_NAME'));
            $this->assertSame([$parent], array_values(array_unique(array_column($rows, 'REFERENCED_TABLE_NAME'))));
        }
        $rules = DB::select("SELECT DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('call_audit_batches','call_audit_items','call_audit_attempts','call_audit_events')");
        $this->assertCount(15, $rules);
        foreach ($rules as $rule) {
            $this->assertContains($rule->DELETE_RULE, ['RESTRICT', 'NO ACTION']);
            $this->assertContains($rule->UPDATE_RULE, ['RESTRICT', 'NO ACTION']);
        }
    }

    public function test_invalid_batch_check_values_are_rejected_without_creating_legacy_fixtures(): void
    {
        foreach ([['actual_count' => 61], ['purpose' => 'unknown'], ['monthly_scope_key' => '2026-13'],
            ['generation' => 0], ['frozen_at' => '2026-10-31']] as $invalid) {
            $batch = new CallAuditBatch;
            $batch->forceFill($this->batchAttributes($invalid));
            $row = $batch->getAttributes() + ['created_at' => '2026-11-03 00:00:00'];
            $this->assertConstraint(fn () => DB::table('call_audit_batches')->insert($row), [3819]);
        }
    }

    public function test_unknown_actor_is_rejected_by_fk_without_changing_users(): void
    {
        $missing = ((int) User::max('id')) + 1;
        $batch = new CallAuditBatch;
        $batch->forceFill($this->batchAttributes(['created_by' => $missing, 'frozen_by' => $missing]));
        $row = $batch->getAttributes() + ['created_at' => '2026-11-03 00:00:00'];
        $this->assertConstraint(fn () => DB::table('call_audit_batches')->insert($row), [1452]);
    }

    public function test_item_attempt_and_event_checks_reject_invalid_rows_without_parent_fixtures(): void
    {
        $item = new CallAuditItem;
        $item->forceFill([
            'batch_id' => 0, 'submission_id' => 0, 'dealer_id_snapshot' => 0,
            'selection_order' => 1, 'stratum_key' => 'all', 'dealer_snapshot_encrypted' => [],
            'source_snapshot_encrypted' => [], 'source_fingerprint' => str_repeat('a', 64),
            'fingerprint_version' => 'test', 'source_observed_at' => '2026-11-03',
        ]);
        $itemRow = $item->getAttributes() + ['created_at' => '2026-11-03 00:00:00'];
        $this->assertConstraint(fn () => DB::table('call_audit_items')->insert(array_replace($itemRow, ['selection_order' => 0])), [3819]);
        $this->assertConstraint(fn () => DB::table('call_audit_items')->insert(array_replace($itemRow, ['claim_owner_id' => 0])), [3819]);

        $attemptRow = [
            'item_id' => 0, 'actor_id' => 0, 'attempt_no' => 1, 'item_version_used' => 1,
            'started_at' => '2026-11-03 08:00:00', 'recorded_at' => '2026-11-03 08:01:00',
            'questionnaire_version' => 'test', 'outcome' => 'fixture',
            'source_fingerprint_used' => str_repeat('a', 64),
            'idempotency_key' => (string) Str::uuid(), 'payload_digest' => str_repeat('b', 64),
        ];
        foreach ([['attempt_no' => 0], ['retry_policy_version' => 'test'], ['answer_1' => 2]] as $invalid) {
            $this->assertConstraint(fn () => DB::table('call_audit_attempts')->insert(array_replace($attemptRow, $invalid)), [3819]);
        }

        $eventRow = [
            'batch_id' => 0, 'actor_id' => 0, 'event_sequence' => 1, 'event_ordinal' => 0,
            'occurred_at' => '2026-11-03 08:01:00', 'event_type' => 'fixture',
            'event_schema_version' => 'test', 'command_name' => 'fixture',
            'command_key' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid(),
            'metadata' => '{}', 'event_digest' => str_repeat('a', 64), 'digest_key_id' => 'test-only',
        ];
        foreach ([['event_sequence' => 0], ['attempt_id' => 0]] as $invalid) {
            $this->assertConstraint(fn () => DB::table('call_audit_events')->insert(array_replace($eventRow, $invalid)), [3819]);
        }
    }

    public function test_encrypted_snapshots_are_ciphertext_after_database_round_trip(): void
    {
        $batch = $this->batch();
        $raw = DB::table('call_audit_batches')->where('id', $batch->id)->first();
        $this->assertNotSame(str_repeat('s', 32), $raw->seed_encrypted);
        $this->assertStringNotContainsString('candidates', $raw->manifest_encrypted);
        $this->assertSame(str_repeat('s', 32), $batch->fresh()->seed_encrypted);
        $this->assertSame(['candidates' => []], $batch->fresh()->manifest_encrypted);
        $this->assertArrayNotHasKey('seed_encrypted', $batch->fresh()->toArray());
    }

    public function test_business_key_cannot_be_bypassed_by_another_creator_or_request_key(): void
    {
        $batch = $this->batch();
        $copy = $this->row($batch);
        $other = $this->createAdmin();
        $copy['created_by'] = $other->id;
        $copy['create_request_key'] = (string) Str::uuid();
        $this->assertConstraint(fn () => DB::table('call_audit_batches')->insert($copy), [1062]);
        $copy = $this->row($batch);
        $copy['generation'] = 2;
        $this->assertConstraint(fn () => DB::table('call_audit_batches')->insert($copy), [1062]);
    }

    public function test_submission_can_overlap_purposes_but_not_repeat_inside_one_batch(): void
    {
        $item = $this->item($this->batch());
        $award = $this->parentFixture(fn () => Award::factory()->create([
            'title' => 'P09F transaction fixture', 'period_type' => 'monthly',
            'period_start' => '2026-10-01', 'period_end' => '2026-11-01',
        ]));
        $second = $this->item($this->batch([
            'purpose' => CallAuditBatch::PURPOSE_AWARD, 'award_id' => $award->id,
            'requested_count' => 15, 'target_cap' => 15,
            'award_baseline_encrypted' => ['dealers' => []],
            'award_baseline_fingerprint' => str_repeat('a', 64),
        ]), $item->submission);
        $this->assertSame($item->submission_id, $second->submission_id);
        $copy = $this->row($item);
        $copy['selection_order'] = 2;
        $this->assertConstraint(fn () => DB::table('call_audit_items')->insert($copy), [1062]);
    }

    public function test_count_purpose_and_partial_claim_constraints_are_enforced(): void
    {
        $batch = $this->batch();
        $this->assertConstraint(fn () => DB::table('call_audit_batches')->where('id', $batch->id)->update(['actual_count' => 61]), [3819, 4025]);
        $this->assertConstraint(fn () => DB::table('call_audit_batches')->where('id', $batch->id)->update(['purpose' => 'unknown']), [3819, 4025]);
        $item = $this->item($batch);
        $this->assertConstraint(fn () => DB::table('call_audit_items')->where('id', $item->id)->update(['claim_owner_id' => $this->admin->id]), [3819, 4025]);
    }

    public function test_underfilled_and_empty_batches_keep_counts_without_fake_completion(): void
    {
        $partial = $this->batch(['eligible_count' => 2, 'actual_count' => 2]);
        $empty = $this->batch(['generation' => 2, 'eligible_count' => 0, 'actual_count' => 0]);
        $this->assertSame(60, $partial->requested_count);
        $this->assertSame(2, $partial->actual_count);
        $this->assertSame(0, $empty->actual_count);
        $this->assertSame('frozen', $empty->fresh()->lifecycle_state);
        $this->assertSame(0, $empty->items()->count());
    }

    public function test_attempt_number_and_request_key_cannot_be_replayed_into_extra_rows(): void
    {
        $attempt = $this->attempt($this->item($this->batch()));
        $copy = $this->row($attempt);
        $copy['idempotency_key'] = (string) Str::uuid();
        $this->assertConstraint(fn () => DB::table('call_audit_attempts')->insert($copy), [1062]);
        $copy = $this->row($attempt);
        $copy['attempt_no'] = 2;
        $this->assertConstraint(fn () => DB::table('call_audit_attempts')->insert($copy), [1062]);
        $this->assertNull($attempt->answer_1);
    }

    public function test_events_cannot_reference_an_item_from_another_batch_or_attempt_from_another_item(): void
    {
        $first = $this->item($this->batch());
        $second = $this->item($this->batch(['generation' => 2]));
        $attempt = $this->attempt($first);
        $event = $this->event($first, $attempt);
        $copy = $this->row($event);
        $copy['batch_id'] = $second->batch_id;
        $copy['command_key'] = (string) Str::uuid();
        $this->assertConstraint(fn () => DB::table('call_audit_events')->insert($copy), [1452]);
        $copy['item_id'] = $second->id;
        $this->assertConstraint(fn () => DB::table('call_audit_events')->insert($copy), [1452]);
    }

    public function test_event_command_and_sequence_uniqueness_and_actor_restriction(): void
    {
        $item = $this->item($this->batch());
        // This actor is referenced only by the audit event, not by submission.reviewed_by.
        $eventActor = $this->createAdmin();
        $event = $this->event($item, $this->attempt($item), $eventActor);
        $copy = $this->row($event);
        $copy['event_sequence'] = 2;
        $this->assertConstraint(fn () => DB::table('call_audit_events')->insert($copy), [1062]);
        $copy = $this->row($event);
        $copy['command_key'] = (string) Str::uuid();
        $this->assertConstraint(fn () => DB::table('call_audit_events')->insert($copy), [1062]);
        $rules = DB::select("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('call_audit_batches','call_audit_attempts','call_audit_events') AND REFERENCED_TABLE_NAME = 'users'");
        $this->assertCount(4, $rules);
        foreach ($rules as $rule) {
            $this->assertContains($rule->DELETE_RULE, ['RESTRICT', 'NO ACTION']);
        }
        $this->assertConstraint(fn () => DB::table('users')->where('id', $eventActor->id)->delete(), [1451], 'call_audit_events_actor_id_foreign');
        $this->assertTrue(User::whereKey($eventActor->id)->exists());
    }

    private function batch(array $overrides = []): CallAuditBatch
    {
        $this->admin ??= $this->createAdmin();
        $batch = new CallAuditBatch;
        $batch->forceFill($this->batchAttributes($overrides))->save();

        return $batch;
    }

    private function batchAttributes(array $overrides = []): array
    {
        return array_replace([
            'purpose' => CallAuditBatch::PURPOSE_GENERAL, 'period_type' => 'monthly',
            'monthly_scope_key' => '2026-10', 'period_start' => '2026-10-01', 'period_end' => '2026-11-01',
            'calendar_zone' => 'UTC', 'calendar_version' => 'test-v1', 'generation' => 1,
            'approval_cutoff_at' => '2026-11-02', 'cutoff_policy_version' => 'fixture-only',
            'eligibility_as_of' => '2026-11-03', 'frozen_at' => '2026-11-03',
            'review_completion_evidence_encrypted' => ['reference' => 'isolated-fixture'],
            'created_by' => $this->admin?->id ?? 0, 'frozen_by' => $this->admin?->id ?? 0,
            'policy_version' => 'v1', 'manifest_version' => 'v1', 'algorithm_version' => 'v1',
            'fingerprint_version' => 'v1', 'eligible_count' => 1, 'requested_count' => 60,
            'actual_count' => 1, 'target_cap' => 60, 'strata_snapshot_encrypted' => [],
            'manifest_encrypted' => ['candidates' => []], 'manifest_digest' => str_repeat('a', 64),
            'selection_digest' => str_repeat('b', 64), 'seed_encrypted' => str_repeat('s', 32),
            'encryption_key_id' => 'test-only', 'encryption_version' => 'v1', 'digest_key_id' => 'test-only',
            'demo_policy_snapshot' => [], 'lifecycle_state' => 'frozen', 'row_version' => 1,
            'create_request_key' => (string) Str::uuid(), 'create_payload_digest' => str_repeat('c', 64),
        ], $overrides);
    }

    private function item(CallAuditBatch $batch, ?CustomerSubmission $submission = null): CallAuditItem
    {
        $submission ??= $this->createSubmission();
        $item = new CallAuditItem;
        $item->forceFill([
            'batch_id' => $batch->id, 'submission_id' => $submission->id, 'selection_order' => 1,
            'stratum_key' => 'all', 'dealer_id_snapshot' => $submission->dealer_id,
            'sales_id_snapshot' => $submission->sales_id, 'dealer_snapshot_encrypted' => [],
            'source_snapshot_encrypted' => ['phone' => '0901234567'],
            'source_fingerprint' => str_repeat('a', 64), 'fingerprint_version' => 'v1',
            'source_observed_at' => '2026-11-03',
        ])->save();

        return $item;
    }

    private function attempt(CallAuditItem $item): CallAuditAttempt
    {
        $attempt = new CallAuditAttempt;
        $attempt->forceFill([
            'item_id' => $item->id, 'attempt_no' => 1, 'actor_id' => $this->admin->id,
            'started_at' => '2026-11-03 08:00:00', 'recorded_at' => '2026-11-03 08:01:00',
            'questionnaire_version' => 'fixture-only', 'outcome' => 'no_answer',
            'source_fingerprint_used' => str_repeat('a', 64), 'item_version_used' => 1,
            'idempotency_key' => (string) Str::uuid(), 'payload_digest' => str_repeat('b', 64),
        ])->save();

        return $attempt;
    }

    private function event(CallAuditItem $item, CallAuditAttempt $attempt, ?User $actor = null): CallAuditEvent
    {
        $event = new CallAuditEvent;
        $event->forceFill([
            'batch_id' => $item->batch_id, 'item_id' => $item->id, 'attempt_id' => $attempt->id,
            'actor_id' => $actor?->id ?? $this->admin->id, 'event_sequence' => 1, 'occurred_at' => '2026-11-03 08:01:00',
            'event_type' => 'recorded', 'event_schema_version' => 'v1', 'command_name' => 'record',
            'command_key' => (string) Str::uuid(), 'event_ordinal' => 0, 'correlation_id' => (string) Str::uuid(),
            'metadata' => [], 'event_digest' => str_repeat('c', 64), 'digest_key_id' => 'test-only',
        ])->save();

        return $event;
    }

    private function row(Model $model): array
    {
        return Arr::except($model->getAttributes(), ['id']);
    }

    private function createAdmin(): User
    {
        $admin = $this->parentFixture(fn () => User::factory()->admin()->create([
            'name' => 'P09F transaction Admin', 'email' => 'p09f-'.Str::uuid().'@example.test',
        ]));
        $this->fixtureAdminIds[] = $admin->id;

        return $admin;
    }

    private function createSubmission(): CustomerSubmission
    {
        $this->admin ??= $this->createAdmin();
        $this->dealer ??= $this->parentFixture(fn () => Dealer::factory()->create([
            'code' => 'P09F-'.Str::uuid(), 'name' => 'P09F transaction Dealer',
        ]));
        $sequence = ++$this->submissionSequence;

        return $this->parentFixture(fn () => CustomerSubmission::factory()->approved()->create([
            'customer_name' => 'P09F transaction customer '.$sequence,
            'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $this->dealer->id,
            'reviewed_by' => $this->admin->id, 'reviewed_at' => '2026-10-03 00:00:00',
            'submitted_at' => '2026-10-02 00:00:00',
            'phone' => sprintf('090%07d', $sequence), 'phone_normalized' => sprintf('090%07d', $sequence),
            'license_plate' => sprintf('51K-100.%02d', $sequence),
            'vehicle_description' => 'Toyota transaction fixture', 'address_line' => 'P09F fixture only',
            'province_code' => '79', 'ward_code' => '26734',
            'consented_at' => '2026-10-02 00:00:00', 'consent_version' => 'p09f-test-only',
        ]));
    }

    private function parentFixture(callable $create): mixed
    {
        $this->assertTrue($this->transactionStarted && DB::connection()->transactionLevel() > 0);
        $this->creatingParentFixture = true;
        try {
            return $create();
        } finally {
            $this->creatingParentFixture = false;
        }
    }

    private function assertConstraint(callable $write, array $codes, ?string $constraint = null): void
    {
        try {
            $write();
            $this->fail('The database accepted an invalid call audit row.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[1], $codes);
            if ($constraint !== null) {
                $this->assertStringContainsString($constraint, (string) $exception->errorInfo[2]);
            }
        }
    }
}
