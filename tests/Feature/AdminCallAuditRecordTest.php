<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditRecordTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private CallAuditBatch $batch;

    private CallAuditItem $item;

    private CarbonImmutable $now;

    private array $claim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new CallAuditFreezeFixtures;
        $this->admin = $this->fixtures->admin();
        $this->actingAs($this->admin);
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
        $receipt = Mockery::mock(ResetDemoRetirement::class);
        $receipt->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $receipt);
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        $this->fixtures->submission($this->fixtures->dealer());
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-general');
        $this->assertSame('FROZEN', $result->summary['status']);
        $this->batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $this->item = $this->batch->items()->sole();
        $this->claim = app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'initial-claim']);
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('deniedAccounts')]
    public function test_record_denies_unauthorized_accounts(?string $role, ?string $status): void
    {
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $user = $this->fixtures->admin();
            $values = ['role' => $role, 'status' => $status];
            if ($role !== 'admin') {
                $values['dealer_id'] = $this->fixtures->dealer()->id;
            }
            $this->fixtures->change($user, $values);
            $this->actingAs($user->fresh());
        }
        $this->save()->assertStatus($role === null ? 401 : 403);
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(2, $this->batch->events()->count());
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'],
            ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    #[DataProvider('answers')]
    public function test_complete_answer_combinations_record_correct_or_pending_finding(bool $a, bool $b, bool $c): void
    {
        $outcome = $a && $b && $c ? 'correct' : 'wrong';
        $result = $this->save(['outcome' => $outcome, 'answer_1' => $a, 'answer_2' => $b, 'answer_3' => $c,
            'note' => 'Private customer note'])->assertOk()->json('data');
        $this->assertSame(3, $result['row_version']);
        $this->assertSame($outcome === 'wrong' ? 'finding_pending' : 'completed', $result['queue_state']);
        $this->assertSame($outcome === 'wrong' ? 'pending' : null, $result['finding_status']);
        $attempt = $this->item->attempts()->sole();
        $this->assertSame([$a, $b, $c], [$attempt->answer_1, $attempt->answer_2, $attempt->answer_3]);
        $this->assertSame($this->admin->id, $attempt->actor_id);
        $this->assertSame('Private customer note', $attempt->note_encrypted);
        $this->assertStringNotContainsString('Private customer note', $attempt->getRawOriginal('note_encrypted'));
        $this->assertSame($this->item->source_fingerprint, $attempt->source_fingerprint_used);
        $this->assertSame(2, $attempt->item_version_used);
        $this->assertSame(1, $attempt->attempt_no);
        $this->assertNull($attempt->retry_due_at);
        $this->assertNull($attempt->retry_policy_version);
        $event = $this->batch->events()->where('event_type', 'call_result_recorded')->sole();
        $this->assertSame($attempt->id, $event->attempt_id);
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame($result['finding_status'], $event->metadata['finding_status']);
        $this->assertSame([2, 3], [$event->before_version, $event->after_version]);
        $this->assertNull($this->item->fresh()->claim_owner_id);
        $this->assertNull($this->item->fresh()->claim_expires_at);
        $this->assertNull($this->item->fresh()->claim_token_hash);
        $this->assertSame(1, $this->item->fresh()->attempt_count);
        $this->assertSame($result['queue_state'], $this->item->fresh()->queue_state);
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($this->batch->id)->summary['status']);
        try {
            app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
                ['purpose' => 'general_audit', 'row_version' => 3, 'idempotency_key' => 'cannot-reopen']);
            $this->fail('Completed/finding items cannot reopen.');
        } catch (CallCommandRejected $rejected) {
            $this->assertSame('item_not_claimable', $rejected->reason);
        }
    }

    public static function answers(): array
    {
        $cases = [];
        foreach ([false, true] as $a) {
            foreach ([false, true] as $b) {
                foreach ([false, true] as $c) {
                    $cases[] = [$a, $b, $c];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('invalidResults')]
    public function test_invalid_outcome_answers_notes_or_input_do_not_write(array $changes, ?string $missing = null): void
    {
        $input = $this->input($changes);
        if ($missing !== null) {
            unset($input[$missing]);
        }
        $this->postJson($this->url(), $input)->assertStatus(422);
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(2, $this->item->fresh()->row_version);
        $this->assertSame(2, $this->batch->events()->count());
    }

    public static function invalidResults(): array
    {
        return [
            [['outcome' => 'correct', 'answer_1' => false]],
            [['outcome' => 'wrong']], [['answer_3' => null]],
            [['outcome' => 'wrong', 'answer_1' => false, 'answer_3' => null]],
            [['outcome' => 'no_answer']], [['outcome' => 'unknown']], [['answer_1' => 'yes']],
            [['note' => str_repeat('x', 2001)]], [['actor_id' => 999]], [['seed' => 'client-seed']],
            [[], 'answer_2'], [[], 'claim_token'], [['row_version' => 0]], [['note' => ['private']]],
        ];
    }

    public function test_no_answer_retries_after_exact_24_hours_then_stops_without_wrong_finding(): void
    {
        $first = $this->save($this->noAnswer())->assertOk()->json('data');
        $this->assertSame('recall', $first['queue_state']);
        $due = $this->now->addSeconds(86400);
        $this->assertSame($due->toIso8601String(), $first['retry_due_at']);
        $firstAttempt = $this->item->attempts()->sole();
        $this->assertSame([null, null, null], [$firstAttempt->answer_1, $firstAttempt->answer_2, $firstAttempt->answer_3]);
        $this->now = $due->subMicrosecond();
        $claimInput = ['purpose' => 'general_audit', 'row_version' => 3, 'idempotency_key' => 'retry-claim'];
        try {
            app(ClaimItem::class)->execute($this->batch->id, $this->item->id, $claimInput);
            $this->fail('Must not call early.');
        } catch (CallCommandRejected $rejected) {
            $this->assertSame('retry_not_due', $rejected->reason);
        }
        $this->now = $due;
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $this->claim = app(ClaimItem::class)->execute($this->batch->id, $this->item->id, $claimInput);
        $second = $this->save($this->noAnswer() + ['idempotency_key' => 'second-result'])->assertOk()->json('data');
        $this->assertSame('completed', $second['queue_state']);
        $this->assertNull($second['retry_due_at']);
        $this->assertNull($second['finding_status']);
        $this->assertSame(2, $this->item->fresh()->attempt_count);
        $this->assertSame('no_answer', $this->item->fresh()->latest_outcome);
        $attempts = $this->item->attempts()->orderBy('attempt_no')->get();
        $this->assertSame([1, 2], $attempts->pluck('attempt_no')->all());
        $this->assertSame([$this->admin->id, $other->id], $attempts->pluck('actor_id')->all());
        $this->assertSame($firstAttempt->getAttributes(), $attempts[0]->getAttributes());
        $this->assertNull($attempts[1]->retry_due_at);
        try {
            app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
                ['purpose' => 'general_audit', 'row_version' => 5, 'idempotency_key' => 'third-call']);
            $this->fail('Must not allow third attempt.');
        } catch (CallCommandRejected $rejected) {
            $this->assertSame('item_not_claimable', $rejected->reason);
        }
    }

    #[DataProvider('answeredRetries')]
    public function test_second_call_may_answer_after_first_no_answer(string $outcome, bool $answer): void
    {
        $this->save($this->noAnswer())->assertOk();
        $this->now = $this->now->addDay();
        $this->claim = app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 3, 'idempotency_key' => 'retry-claim']);
        $this->save(['idempotency_key' => 'answered-second', 'outcome' => $outcome, 'answer_1' => $answer])->assertOk()
            ->assertJsonPath('data.queue_state', $outcome === 'wrong' ? 'finding_pending' : 'completed');
        $this->assertSame(['no_answer', $outcome], $this->item->attempts()->orderBy('attempt_no')->pluck('outcome')->all());
    }

    public static function answeredRetries(): array
    {
        return [['correct', true], ['wrong', false]];
    }

    public function test_same_request_replays_after_commit_expiry_and_source_drift_without_extra_attempt(): void
    {
        $input = $this->input(['note' => 'private note']);
        $first = $this->postJson($this->url(), $input)->assertOk()->json();
        $this->now = $this->now->addDays(2);
        $this->fixtures->change($this->item->submission, ['customer_name' => 'source changed']);
        $this->postJson($this->url(), $input)->assertExactJson($first);
        $this->postJson($this->url(), array_replace($input, ['note' => 'different private note']))->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_payload_conflict');
        $this->postJson($this->url(), array_replace($input, ['idempotency_key' => 'different-key', 'row_version' => 3]))->assertStatus(409)
            ->assertJsonPath('error', 'claim_ownership_conflict');
        $this->assertSame(1, $this->item->attempts()->count());
        $this->assertSame(3, $this->batch->events()->count());
    }

    public function test_owner_token_version_expiry_and_renewed_claim_protect_recording(): void
    {
        $this->save(['claim_token' => str_repeat('f', 64)])->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->save(['row_version' => 1])->assertStatus(409)->assertJsonPath('error', 'item_version_conflict');
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $this->save()->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->actingAs($this->admin);
        $this->now = $this->now->addSeconds(600);
        $this->save()->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->actingAs($other);
        $newClaim = app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 2, 'idempotency_key' => 'new-owner']);
        $this->actingAs($this->admin);
        $this->save(['row_version' => 3])->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->actingAs($other);
        $this->claim = $newClaim;
        $this->save()->assertOk();
        $this->assertSame($other->id, $this->item->attempts()->sole()->actor_id);
    }

    public function test_source_drift_and_persisted_inactive_actor_are_rechecked_before_record(): void
    {
        $source = $this->item->submission;
        $original = $source->getRawOriginal('customer_name');
        $this->fixtures->change($source, ['customer_name' => 'changed']);
        $this->save()->assertStatus(409)->assertJsonPath('error', 'source_drift_conflict');
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(2, $this->item->fresh()->row_version);
        $this->fixtures->change($source, ['customer_name' => $original]);
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->save()->assertForbidden();
        try {
            app(RecordCallResult::class)->execute($this->batch->id, $this->item->id, $this->input());
            $this->fail('Direct action must reject inactive actor.');
        } catch (CallCommandRejected $rejected) {
            $this->assertSame(403, $rejected->status);
        }
    }

    public function test_actor_may_claim_and_record_batch_created_by_another_admin(): void
    {
        $creator = $this->admin->id;
        $this->now = $this->now->addSeconds(600);
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $this->claim = app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 2, 'idempotency_key' => 'other-claim']);
        $this->save()->assertOk();
        $this->assertSame($creator, $this->batch->fresh()->created_by);
        $this->assertSame($other->id, $this->item->attempts()->sole()->actor_id);
        $this->assertSame($other->id, $this->batch->events()->where('event_type', 'call_result_recorded')->sole()->actor_id);
    }

    #[DataProvider('failurePoints')]
    public function test_failure_rolls_back_attempt_event_and_projection(string $sqlPrefix): void
    {
        Log::spy();
        $before = $this->item->fresh()->getAttributes();
        $fail = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fail, $sqlPrefix): void {
            if ($fail && str_starts_with($sql, $sqlPrefix)) {
                $fail = false;
                throw new \RuntimeException('private note and SQL must not be logged');
            }
        });
        $this->save()->assertStatus(503)->assertExactJson(['error' => 'audit_command_unavailable']);
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(2, $this->batch->events()->count());
        $this->assertSame($before, $this->item->fresh()->getAttributes());
        Log::shouldNotHaveReceived('error');
        $this->save()->assertOk();
    }

    public static function failurePoints(): array
    {
        return [['insert into `call_audit_attempts`'], ['update `call_audit_items`'], ['insert into `call_audit_events`']];
    }

    public function test_csrf_scope_and_sensitive_response_boundaries(): void
    {
        $this->save(['purpose' => 'award_top3'])->assertNotFound();
        $this->postJson('/admin/call-audits/'.$this->batch->id.'/items/999999999/record', $this->input())->assertNotFound();
        $this->getJson($this->url())->assertStatus(405);
        $this->postJson($this->url().'?phone=0901234567', $this->input())->assertStatus(422)->assertExactJson(['error' => 'invalid_call_command']);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->save()->assertStatus(419);
        $response = $this->withSession(['_token' => 'record-csrf'])->withHeader('X-CSRF-TOKEN', 'record-csrf')
            ->save(['note' => 'Private PII note'])->assertOk();
        foreach (['Private PII note', $this->claim['claim_token'], 'seed', 'manifest', 'fingerprint', 'answer_1', 'source_snapshot'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $route = app('router')->getRoutes()->getByName('admin.call-audits.record');
        $this->assertSame(['POST'], $route->methods());
        foreach (['web', 'auth', 'auth.session', 'role:admin', 'account.active', 'throttle:admin-writes'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_wrong_finding_has_no_business_side_effects_and_history_is_immutable(): void
    {
        $before = [];
        foreach (['customer_submissions', 'submission_verifications', 'audit_logs', 'awards', 'award_winners', 'users', 'dealers'] as $table) {
            $before[$table] = (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED');
        }
        $this->save(['outcome' => 'wrong', 'answer_2' => false])->assertOk()->assertJsonPath('data.finding_status', 'pending');
        foreach ($before as $table => $checksum) {
            $this->assertSame($checksum, (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'));
        }
        $attempt = $this->item->attempts()->sole();
        foreach (['update', 'delete'] as $mutation) {
            try {
                $mutation === 'update' ? $attempt->forceFill(['outcome' => 'correct'])->save() : $attempt->delete();
                $this->fail('Attempt must be append-only.');
            } catch (\LogicException) {
                $this->assertSame('wrong', $attempt->fresh()->outcome);
            }
        }
    }

    public function test_c07_and_c08_remain_independent_even_for_the_same_submission(): void
    {
        $award = $this->fixtures->award();
        $result = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'),
            $this->fixtures->tag.'-award', $award->id);
        $this->assertSame('FROZEN', $result->summary['status']);
        $awardBatch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $awardItem = $awardBatch->items()->sole();
        $this->assertSame($this->item->submission_id, $awardItem->submission_id);
        $this->postJson('/admin/call-audits/'.$awardBatch->id.'/items/'.$this->item->id.'/record',
            $this->input(['purpose' => 'award_top3']))->assertNotFound();
        $claim = app(ClaimItem::class)->execute($awardBatch->id, $awardItem->id,
            ['purpose' => 'award_top3', 'row_version' => 1, 'idempotency_key' => 'award-claim']);
        $this->postJson('/admin/call-audits/'.$awardBatch->id.'/items/'.$awardItem->id.'/record',
            $this->input(['purpose' => 'award_top3', 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token']]))->assertOk();
        $this->assertSame(1, $awardItem->attempts()->count());
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame('uncalled', $this->item->fresh()->queue_state);
        $this->assertSame(2, $this->batch->events()->count());
        $this->save()->assertOk();
        $this->assertSame(1, $this->item->attempts()->count());
        $this->assertSame(1, $awardItem->attempts()->count());
    }

    #[DataProvider('races')]
    public function test_real_mysql_concurrent_saves_are_idempotent_and_preserve_ownership(bool $sameActor): void
    {
        $admins = [$this->admin, $sameActor ? $this->admin : $this->fixtures->admin()];
        $directory = storage_path('app/private/p09jb-record-races/'.$this->fixtures->tag);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $config = DB::connection()->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09jb-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing',
            'TOYOTA_TEST_DATABASE' => 'toyota_testing', 'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CALL_AUDIT_RACE_TOKEN' => $this->claim['claim_token']];
        $workers = [];
        try {
            foreach (['holder', 'contender'] as $index => $role) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/call_audit_record_worker.php'),
                    (string) $admins[$index]->id, (string) $this->batch->id, (string) $this->item->id,
                    $this->fixtures->tag, $directory, $role], base_path(), $environment, null, 20);
                $workers[] = $worker;
                $worker->start();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($directory.'/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
            $this->assertSame('RECORDED', $results[0]['status']);
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            if ($sameActor) {
                $this->assertSame('RECORDED', $results[1]['status']);
                $this->assertSame($results[0]['attempt_id'], $results[1]['attempt_id']);
            } else {
                $this->assertSame('CONFLICT', $results[1]['status']);
                $this->assertSame('item_version_conflict', $results[1]['code']);
            }
            $this->assertSame(1, $this->item->attempts()->count());
            $this->assertSame($this->admin->id, $this->item->attempts()->sole()->actor_id);
            $this->assertSame(3, $this->batch->events()->count());
            $this->assertSame(3, $this->item->fresh()->row_version);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    public static function races(): array
    {
        return ['double-submit' => [true], 'two-admins' => [false]];
    }

    private function noAnswer(): array
    {
        return ['outcome' => 'no_answer', 'answer_1' => null, 'answer_2' => null, 'answer_3' => null];
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['purpose' => 'general_audit', 'row_version' => $this->claim['row_version'],
            'idempotency_key' => 'save-call', 'claim_token' => $this->claim['claim_token'],
            'outcome' => 'correct', 'answer_1' => true, 'answer_2' => true, 'answer_3' => true], $overrides);
    }

    private function url(): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/items/'.$this->item->id.'/record';
    }

    private function save(array $overrides = [])
    {
        return $this->postJson($this->url(), $this->input($overrides));
    }
}
