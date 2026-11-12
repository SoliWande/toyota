<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ApplyDealerPenalty;
use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Actions\PublishAuditedAward;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Award;
use App\Models\AwardPublication;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\AwardPublicationReadiness;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ReadUnavailable;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AwardPublicationFinalTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private Award $award;

    private array $dealers = [];

    private array $sources = [];

    private CallAuditBatch $batch;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new CallAuditFreezeFixtures;
        $this->admin = $this->fixtures->admin();
        $this->actingAs($this->admin);
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false, 'realistic_demo.include_in_metrics' => false]);
        $retirement = Mockery::mock(ResetDemoRetirement::class);
        $retirement->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $retirement);
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        foreach (range(1, 4) as $n) {
            $dealer = $this->fixtures->dealer();
            $this->dealers[] = $dealer;
            $this->sources[] = $this->fixtures->submission($dealer, ['submitted_at' => '2026-10-0'.$n.' 12:00:00']);
        }
        $this->award = $this->fixtures->award();
        $result = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-initial', $this->award->id)->summary;
        $this->assertSame('FROZEN', $result['status'], json_encode($result));
        $this->batch = CallAuditBatch::findOrFail($result['batch_id']);
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    private function readiness(): array
    {
        return app(AwardPublicationReadiness::class)->read($this->admin, $this->award->id);
    }

    private function recordCall(CallAuditItem $item, string $outcome = 'correct'): void
    {
        $item->refresh();
        $n = $item->attempt_count + 1;
        $claim = app(ClaimItem::class)->execute($item->batch_id, $item->id, ['purpose' => $item->batch->purpose, 'row_version' => $item->row_version, 'idempotency_key' => 'claim-'.$item->id.'-'.$n]);
        app(RecordCallResult::class)->execute($item->batch_id, $item->id, ['purpose' => $item->batch->purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'result-'.$item->id.'-'.$n, 'outcome' => $outcome, 'answer_1' => $outcome === 'no_answer' ? null : $outcome !== 'wrong',
            'answer_2' => $outcome === 'no_answer' ? null : true, 'answer_3' => $outcome === 'no_answer' ? null : true]);
    }

    private function complete(): void
    {
        foreach ($this->batch->items as $item) {
            $this->recordCall($item);
        }
    }

    private function publish(array $input = [])
    {
        return $this->postJson('/admin/awards/'.$this->award->id.'/publish', $input + ['idempotency_key' => 'publication-test']);
    }

    private function supplement(string $key = 'supplement'): array
    {
        return app(CreateAndFreezeBatch::class)->execute('award_top3', $this->award->period_start, $this->fixtures->tag.'-'.$key, $this->award->id, true)->summary;
    }

    private function wrong(?string $decision): CallAuditItem
    {
        $item = $this->batch->items()->where('submission_id', $this->sources[0]->id)->sole();
        $this->recordCall($item, 'wrong');
        if ($decision !== null) {
            app(ResolveFinding::class)->execute($this->batch->id, $item->id, ['purpose' => 'award_top3', 'month' => '2026-10', 'attempt_id' => $item->attempts()->sole()->id,
                'row_version' => $item->fresh()->row_version, 'resolution_version' => 0, 'idempotency_key' => 'resolution', 'decision' => $decision, 'reason' => 'PRIVATE publication reason']);
        }
        foreach ($this->batch->items()->where('id', '!=', $item->id)->get() as $other) {
            $this->recordCall($other);
        }

        return $item;
    }

    public function test_complete_top3_underfill_publication_retry_and_immutable_snapshot(): void
    {
        $this->complete();
        $ready = $this->readiness();
        $this->assertTrue($ready['can_publish'], json_encode($ready));
        $input = ['preview_fingerprint' => $ready['preview_fingerprint']];
        $result = $this->publish($input)->assertOk()->assertJsonPath('data.status', 'published')->json('data');
        $this->assertSame($result, $this->publish($input)->assertOk()->json('data'));
        $this->publish(['idempotency_key' => 'another'])->assertStatus(409)->assertJsonPath('error', 'already_published');
        $this->publish(['preview_fingerprint' => str_repeat('a', 64)])->assertStatus(409)->assertJsonPath('error', 'idempotency_payload_conflict');
        $row = AwardPublication::sole();
        app(PublishAuditedAward::class)->verify($row);
        $this->assertSame(3, $this->award->winners()->count());
        $this->assertSame(1, $row->batch_generation);
        $this->assertStringNotContainsString('PRIVATE', DB::table('award_publications')->value('evidence_encrypted'));
        $before = $this->award->winners()->get()->toArray();
        $this->fixtures->change($this->sources[0], ['status' => 'rejected']);
        $this->fixtures->submission($this->dealers[3], ['submitted_at' => '2026-10-20']);
        $this->assertSame($before, $this->award->winners()->get()->toArray());
        app(PublishAuditedAward::class)->verify($row->fresh());
        $this->assertFalse($this->readiness()['can_publish']);
    }

    public function test_month_not_ended_pending_and_live_source_drift_block(): void
    {
        $this->now = CarbonImmutable::parse('2026-10-31 23:59:59', config('app.timezone'));
        $this->assertContains('period_not_ended', $this->readiness()['blockers']);
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $this->complete();
        $this->fixtures->change($this->sources[3], ['status' => 'pending', 'reviewed_at' => null, 'reviewed_by' => null]);
        $this->assertContains('pending_review_in_month', $this->readiness()['blockers']);
        $this->publish()->assertStatus(409);
        $this->fixtures->change($this->sources[3], ['status' => 'approved', 'reviewed_at' => '2026-11-02', 'reviewed_by' => $this->admin->id]);
        $this->fixtures->change($this->sources[0], ['customer_name' => 'changed']);
        $this->assertContains('audited_source_changed', $this->readiness()['blockers']);
        $this->assertSame(0, AwardPublication::count());
    }

    public function test_unresolved_and_needs_review_do_not_become_completed(): void
    {
        $this->wrong('needs_review');
        $this->assertContains('findings_unresolved', $this->readiness()['blockers']);
        $this->publish()->assertStatus(409);
    }

    public function test_confirmed_penalty_new_top3_supplemental_freeze_replay_and_publish(): void
    {
        $item = $this->wrong('confirmed');
        $this->assertContains('confirmed_penalties_outstanding', $this->readiness()['blockers']);
        app(ApplyDealerPenalty::class)->execute($this->batch->id, $item->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'penalty']);
        $ready = $this->readiness();
        $this->assertSame([$this->dealers[3]->id], $ready['supplemental_dealer_ids']);
        $original = DB::table('call_audit_batches')->where('id', $this->batch->id)->first();
        $result = $this->supplement();
        $this->assertSame('FROZEN', $result['status'], json_encode($result));
        $this->assertSame($result['batch_id'], $this->supplement()['batch_id']);
        $batch = CallAuditBatch::findOrFail($result['batch_id']);
        $this->assertSame(2, $batch->generation);
        $this->assertSame($this->batch->id, $batch->predecessor_batch_id);
        $this->assertSame([5, 1], [$batch->requested_count, $batch->actual_count]);
        $this->assertSame([$this->dealers[3]->id], $batch->items()->pluck('dealer_id_snapshot')->all());
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($batch->id)->summary['status']);
        $this->assertSame((array) $original, (array) DB::table('call_audit_batches')->where('id', $this->batch->id)->first());
        $this->assertSame('no_new_dealer_requires_sampling', $this->supplement('reroll')['code']);
        $this->recordCall($batch->items()->sole());
        $this->assertTrue($this->readiness()['can_publish']);
        $this->publish()->assertOk();
        $this->assertSame(2, AwardPublication::sole()->batch_generation);
        $this->assertSame([$this->dealers[1]->id, $this->dealers[2]->id, $this->dealers[3]->id], $this->award->winners()->orderBy('rank')->pluck('dealer_id')->all());
    }

    public function test_dismissed_wrong_can_finish_without_penalty(): void
    {
        $this->wrong('dismissed');
        $this->assertTrue($this->readiness()['can_publish']);
        $this->publish()->assertOk();
        $this->assertSame(0, DB::table('dealer_penalty_entries')->count());
    }

    public function test_no_answer_twice_finishes_without_being_correct_or_wrong(): void
    {
        $item = $this->batch->items()->first();
        $this->recordCall($item, 'no_answer');
        $this->assertContains('award_calls_outstanding', $this->readiness()['blockers']);
        $this->now = $this->now->addDay();
        $this->recordCall($item, 'no_answer');
        foreach ($this->batch->items()->where('id', '!=', $item->id)->get() as $other) {
            $this->recordCall($other);
        }
        $this->assertTrue($this->readiness()['can_publish']);
        $this->assertSame('no_answer', $item->fresh()->latest_outcome);
        $this->publish()->assertOk();
    }

    public function test_zero_eligible_stratum_is_never_completed_or_rerolled(): void
    {
        // Fourth dealer remains ranked but its phone is not eligible for a future supplement.
        $this->fixtures->change($this->sources[3], ['phone' => '0000000000', 'phone_normalized' => '0000000000']);
        $item = $this->wrong('confirmed');
        app(ApplyDealerPenalty::class)->execute($this->batch->id, $item->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'penalty']);
        $result = $this->supplement();
        $this->assertSame('FROZEN', $result['status'], json_encode($result));
        $this->assertSame(0, $result['actual_count']);
        $this->assertContains('award_audit_empty', $this->readiness()['blockers']);
        $this->publish()->assertStatus(409);
        $this->assertSame('no_new_dealer_requires_sampling', $this->supplement('retry-empty')['code']);
    }

    public function test_tampered_commit_and_publication_rollback(): void
    {
        $this->complete();
        $event = $this->batch->events()->where('event_type', 'call_result_recorded')->first();
        DB::table('call_audit_events')->where('id', $event->id)->update(['event_digest' => str_repeat('f', 64)]);
        $this->publish()->assertStatus(409);
        $this->assertSame(0, $this->award->winners()->count());
    }

    #[DataProvider('publicationFailurePoints')]
    public function test_failure_between_winners_and_evidence_rolls_back_everything(string $prefix): void
    {
        $this->complete();
        DB::connection()->beforeExecuting(function (string $sql) use ($prefix): void {
            if (str_starts_with($sql, $prefix)) {
                throw new \RuntimeException('fixture injected failure');
            }
        });
        $this->publish()->assertStatus(503);
        $this->assertNull($this->award->fresh()->published_at);
        $this->assertSame(0, $this->award->winners()->count());
        $this->assertSame(0, AwardPublication::count());
    }

    public static function publicationFailurePoints(): array
    {
        return [['insert into `award_publications`'], ['update `awards`']];
    }

    public function test_fingerprint_conflict_client_bypass_idor_privacy_and_fresh_authorization(): void
    {
        $this->complete();
        $this->publish(['preview_fingerprint' => str_repeat('f', 64)])->assertStatus(409)->assertJsonPath('error', 'publication_source_conflict');
        $this->publish(['score' => 100])->assertStatus(422);
        $this->postJson('/admin/awards/999999999/publish', ['idempotency_key' => 'idor'])->assertNotFound();
        $response = $this->publish()->assertOk();
        foreach (['evidence', 'seed', 'manifest', 'reason', $this->sources[0]->phone] as $private) {
            $response->assertDontSee($private);
        }
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->publish()->assertForbidden();
        try {
            app(PublishAuditedAward::class)->execute($this->award->id, 'publication-test');
            $this->fail('Must reauthorize');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_new_schema_constraints_encryption_and_append_only_history(): void
    {
        $this->complete();
        $this->publish()->assertOk();
        $row = AwardPublication::sole();
        $raw = (array) DB::table('award_publications')->sole();
        $this->assertNotSame(json_encode($row->evidence_encrypted), $raw['evidence_encrypted']);
        $this->assertIsArray($row->receipt_encrypted);
        foreach ([['actor_id' => 999999999], ['batch_generation' => 0], ['batch_generation' => 65], ['monthly_scope_key' => '2026-13'], ['source_cutoff_at' => '2027-01-01']] as $invalid) {
            DB::beginTransaction();
            try {
                DB::table('award_publications')->where('id', $row->id)->update($invalid);
                $this->fail('Expected FK/CHECK violation');
            } catch (QueryException $e) {
                $this->assertContains((int) $e->errorInfo[1], [1452, 3819]);
            } finally {
                DB::rollBack();
            }
        }
        DB::beginTransaction();
        try {
            unset($raw['id']);
            DB::table('award_publications')->insert($raw);
            $this->fail('Expected unique violation');
        } catch (QueryException $e) {
            $this->assertSame(1062, (int) $e->errorInfo[1]);
        } finally {
            DB::rollBack();
        }
        foreach (['month', 'actor_request'] as $key) {
            DB::beginTransaction();
            try {
                $otherAward = $this->award->fresh()->getAttributes();
                unset($otherAward['id']);
                $otherAward['period_start'] = '2026-09-01';
                $otherAward['period_end'] = '2026-10-01';
                $otherAward['published_at'] = null;
                $otherAward['published_by'] = null;
                $otherId = DB::table('awards')->insertGetId($otherAward);
                $duplicate = $raw;
                $duplicate['award_id'] = $otherId;
                if ($key === 'month') {
                    $duplicate['request_key'] = 'another-publication';
                } else {
                    $duplicate['monthly_scope_key'] = '2026-09';
                }
                DB::table('award_publications')->insert($duplicate);
                $this->fail('Expected distinct business unique violation');
            } catch (QueryException $error) {
                $this->assertSame(1062, (int) $error->errorInfo[1]);
                $this->assertStringContainsString($key === 'month' ? 'monthly_scope_key_unique' : 'publication_actor_request_unique', $error->getMessage());
            } finally {
                DB::rollBack();
            }
        }
        $foreignKeys = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', 'toyota_testing')
            ->where('TABLE_NAME', 'award_publications')->get();
        $this->assertCount(2, $foreignKeys);
        foreach ($foreignKeys as $foreignKey) {
            $this->assertSame('RESTRICT', $foreignKey->DELETE_RULE);
            $this->assertSame('RESTRICT', $foreignKey->UPDATE_RULE);
        }
        try {
            $row->forceFill(['policy_version' => 'tampered'])->save();
            $this->fail('Immutable');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
        try {
            $row->delete();
            $this->fail('Immutable');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
        DB::beginTransaction();
        try {
            DB::table('award_publications')->where('id', $row->id)->update(['evidence_digest' => str_repeat('a', 64)]);
            app(PublishAuditedAward::class)->verify($row->fresh());
            $this->fail('Tampered evidence');
        } catch (ReadUnavailable) {
            $this->assertTrue(true);
        } finally {
            DB::rollBack();
        }
    }

    #[DataProvider('raceCases')]
    public function test_real_mysql_freeze_penalty_publication_races(string $first, string $second, bool $sameActor): void
    {
        $item = $this->batch->items()->first();
        if ($first === 'penalty' || $first === 'supplement' || $second === 'penalty') {
            $item = $this->wrong('confirmed');
            if ($first !== 'penalty') {
                app(ApplyDealerPenalty::class)->execute($this->batch->id, $item->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'penalty']);
                if ($first !== 'supplement') {
                    $supplement = $this->supplement();
                    $this->recordCall(CallAuditBatch::findOrFail($supplement['batch_id'])->items()->sole());
                }
            }
        } else {
            $this->complete();
        }
        $actors = [$this->admin, $sameActor ? $this->admin : $this->fixtures->admin()];
        $directory = storage_path('app/private/p09-final-races/'.$this->fixtures->tag);
        mkdir($directory, 0700, true);
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09-final-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
            'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $workers = [];
        try {
            foreach (['holder', 'contender'] as $index => $role) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/p09_publication_worker.php'),
                    (string) $actors[$index]->id, (string) $this->batch->id, (string) $item->id, $this->fixtures->tag, $directory, $role, $index ? $second : $first], base_path(), $env, null, 25);
                $workers[] = $worker;
                $worker->start();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getOutput().$worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($directory.'/publication-results.json', json_encode($results, JSON_PRETTY_PRINT));
            $this->assertSame('OK', $results[0]['status'], json_encode($results));
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
            if ($first === 'publish') {
                $this->assertSame(1, AwardPublication::count());
                app(PublishAuditedAward::class)->verify(AwardPublication::sole());
                if ($sameActor && $second === 'publish') {
                    $this->assertSame($results[0]['response'], $results[1]['response']);
                } elseif ($second === 'publish') {
                    $this->assertSame('already_published', $results[1]['code']);
                } else {
                    $this->assertSame('OK', $results[1]['status']);
                }
            } elseif ($second === 'supplement') {
                $this->assertSame(2, CallAuditBatch::count());
                $this->assertSame(1, CallAuditBatch::where('generation', 2)->count());
                if ($sameActor) {
                    $this->assertSame($results[0]['response']['batch_id'], $results[1]['response']['batch_id']);
                } else {
                    $this->assertSame('no_new_dealer_requires_sampling', $results[1]['response']['code']);
                }
            } else {
                $this->assertSame('publication_not_ready', $results[1]['code']);
                $this->assertSame(0, AwardPublication::count());
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    public static function raceCases(): array
    {
        return [['publish', 'publish', true], ['publish', 'publish', false], ['penalty', 'publish', false], ['supplement', 'publish', false], ['publish', 'penalty', false], ['supplement', 'supplement', true], ['supplement', 'supplement', false]];
    }

    public function test_browser_desktop_existing_controls_publish_only_after_server_commit(): void
    {
        $this->complete();
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09-final-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
            'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'p09final_publication_qa', 'APP_URL' => 'http://127.0.0.1:8777',
            'P09L_ADMIN_ID' => (string) $this->admin->id, 'P09L_FIXTURE_TAG' => $this->fixtures->tag];
        $server = new Process([PHP_BINARY, '-S', '127.0.0.1:8777', '-t', 'public', base_path('tests/Fixtures/p09_publication_ui_router.php')], base_path(), $env, null, 60);
        try {
            $server->start();
            $qa = new Process(['node', base_path('tests/Browser/p09-publication.mjs')], base_path(), ['P09FINAL_AWARD_ID' => (string) $this->award->id], null, 50);
            $qa->run();
            $this->assertTrue($qa->isSuccessful(), $qa->getErrorOutput().$qa->getOutput());
            $this->assertNotNull($this->award->fresh()->published_at);
            $this->assertSame(1, AwardPublication::count());
            app(PublishAuditedAward::class)->verify(AwardPublication::sole());
        } finally {
            if ($server->isRunning()) {
                $pidFile = storage_path('app/private/p09l-ui-server/'.$this->fixtures->tag.'.pid');
                $pid = is_file($pidFile) ? trim(file_get_contents($pidFile)) : '';
                if (! ctype_digit($pid)) {
                    throw new \RuntimeException('Cannot identify owned test server');
                }
                (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', 'Stop-Process -Id '.(int) $pid.' -ErrorAction Stop'], base_path(), null, null, 10))->mustRun();
            }
        }
    }

    public function test_browser_existing_call_button_creates_supplement_then_opens_real_c08_scope(): void
    {
        $item = $this->wrong('confirmed');
        app(ApplyDealerPenalty::class)->execute($this->batch->id, $item->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'browser-penalty']);
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09-final-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
            'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'p09final_publication_qa', 'APP_URL' => 'http://127.0.0.1:8777',
            'P09L_ADMIN_ID' => (string) $this->admin->id, 'P09L_FIXTURE_TAG' => $this->fixtures->tag];
        $server = new Process([PHP_BINARY, '-S', '127.0.0.1:8777', '-t', 'public', base_path('tests/Fixtures/p09_publication_ui_router.php')], base_path(), $env, null, 60);
        try {
            $server->start();
            $qa = new Process(['node', base_path('tests/Browser/p09-publication.mjs')], base_path(), ['P09FINAL_AWARD_ID' => (string) $this->award->id, 'P09FINAL_MODE' => 'supplement'], null, 50);
            $qa->run();
            $this->assertTrue($qa->isSuccessful(), $qa->getErrorOutput().$qa->getOutput());
            $this->assertNull($this->award->fresh()->published_at);
            $this->assertSame(0, AwardPublication::count());
            $this->assertSame(2, CallAuditBatch::count());
            $supplement = CallAuditBatch::where('generation', 2)->sole();
            $this->assertSame(0, $supplement->items()->sole()->attempt_count);
            $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($supplement->id)->summary['status']);
        } finally {
            if ($server->isRunning()) {
                $pidFile = storage_path('app/private/p09l-ui-server/'.$this->fixtures->tag.'.pid');
                $pid = is_file($pidFile) ? trim(file_get_contents($pidFile)) : '';
                if (! ctype_digit($pid)) {
                    throw new \RuntimeException('Cannot identify owned test server');
                }
                (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', 'Stop-Process -Id '.(int) $pid.' -ErrorAction Stop'], base_path(), null, null, 10))->mustRun();
            }
        }
    }

    public function test_supplemental_finding_recalculates_top3_without_rerolling_returning_dealer(): void
    {
        $first = $this->wrong('confirmed');
        app(ApplyDealerPenalty::class)->execute($this->batch->id, $first->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'first-penalty']);
        $extra = $this->supplement();
        $batch = CallAuditBatch::findOrFail($extra['batch_id']);
        $item = $batch->items()->sole();
        $this->recordCall($item, 'wrong');
        app(ResolveFinding::class)->execute($batch->id, $item->id, ['purpose' => 'award_top3', 'month' => '2026-10', 'attempt_id' => $item->attempts()->sole()->id,
            'row_version' => $item->fresh()->row_version, 'resolution_version' => 0, 'idempotency_key' => 'supplement-resolution', 'decision' => 'confirmed', 'reason' => 'Verified supplemental finding']);
        $this->assertContains('confirmed_penalties_outstanding', $this->readiness()['blockers']);
        app(ApplyDealerPenalty::class)->execute($batch->id, $item->id, ['month' => '2026-10', 'purpose' => 'award_top3', 'resolution_version' => 1, 'idempotency_key' => 'second-penalty']);
        $ready = $this->readiness();
        $this->assertTrue($ready['can_publish']);
        $this->assertSame([], $ready['supplemental_dealer_ids']);
        $this->assertSame($this->dealers[0]->id, $ready['dealers'][2]['dealer_id']);
        $this->assertSame('no_new_dealer_requires_sampling', $this->supplement('no-reroll')['code']);
        $this->assertSame(2, CallAuditBatch::count());
        $this->publish()->assertOk();
    }

    public function test_csrf_and_non_admin_cannot_publish_or_supplement(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->publish()->assertStatus(419);
        $this->postJson('/admin/awards/'.$this->award->id.'/supplemental', ['idempotency_key' => 'csrf'])->assertStatus(419);
        $this->withSession(['_token' => 'publication-csrf'])->withHeader('X-CSRF-TOKEN', 'publication-csrf');
        $this->complete();
        foreach (['sales', 'dealer'] as $role) {
            $actor = $this->fixtures->admin();
            $this->fixtures->change($actor, ['role' => $role, 'dealer_id' => $this->dealers[0]->id]);
            $this->actingAs($actor->fresh());
            $this->publish()->assertForbidden();
            $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertForbidden();
            $this->postJson('/admin/awards/'.$this->award->id.'/supplemental', ['idempotency_key' => 'forbidden'])->assertForbidden();
        }
        auth()->forgetUser();
        $this->publish()->assertUnauthorized();
        $this->assertSame(0, AwardPublication::count());
    }

    public function test_microsecond_cutoff_is_preserved_without_altering_legacy_award_schema(): void
    {
        $this->complete();
        $this->now = $this->now->addMicroseconds(123456);
        $this->publish()->assertOk();
        $row = AwardPublication::sole();
        $this->assertSame('123456', $row->source_cutoff_at->format('u'));
        $this->assertSame('123456', $row->published_at->format('u'));
        $this->assertSame('000000', $this->award->fresh()->published_at->format('u'));
        app(PublishAuditedAward::class)->verify($row);
    }

    public function test_c08_findings_and_signed_receipts_are_checked_without_mixing_c07_coverage(): void
    {
        $this->complete();
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', $this->award->period_start, $this->fixtures->tag.'-general')->summary;
        $general = CallAuditBatch::findOrFail($result['batch_id']);
        $item = $general->items()->where('submission_id', $this->sources[3]->id)->sole();
        $this->recordCall($item, 'wrong');
        $this->assertContains('findings_unresolved', $this->readiness()['blockers']);
        app(ResolveFinding::class)->execute($general->id, $item->id, ['purpose' => 'general_audit', 'month' => '2026-10', 'attempt_id' => $item->attempts()->sole()->id,
            'row_version' => $item->fresh()->row_version, 'resolution_version' => 0, 'idempotency_key' => 'general-resolution', 'decision' => 'dismissed', 'reason' => 'Verified independently']);
        $this->assertTrue($this->readiness()['can_publish']);
        $other = $general->items()->where('id', '!=', $item->id)->first();
        $this->recordCall($other);
        $event = $other->events()->where('event_type', 'call_result_recorded')->sole();
        DB::table('call_audit_events')->where('id', $event->id)->update(['event_digest' => str_repeat('f', 64)]);
        $this->publish()->assertStatus(409);
        $this->assertSame(0, AwardPublication::count());
    }
}
