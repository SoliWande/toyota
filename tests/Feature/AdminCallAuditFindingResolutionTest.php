<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditFindingResolutionTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private CallAuditBatch $batch;

    private CallAuditItem $item;

    private CarbonImmutable $now;

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
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        $this->fixtures->submission($this->fixtures->dealer());
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-general');
        $this->assertSame('FROZEN', $result->summary['status']);
        $this->batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $this->item = $this->batch->items()->sole();
        $this->record($this->item);
        $this->item->refresh();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && (! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)
                || preg_match('/for update|for share|lock in share mode/i', $sql))) {
                throw new \RuntimeException('Resolution GET cannot write or lock.');
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('transitions')]
    public function test_approved_transition_matrix(?string $from, string $next, bool $allowed): void
    {
        if ($from !== null) {
            $this->save(['decision' => $from])->assertOk();
        }
        $response = $this->save(['decision' => $next, 'resolution_version' => $from === null ? 0 : 1,
            'idempotency_key' => 'next']);
        $response->assertStatus($allowed ? 200 : 409);
        $this->assertSame(($from === null ? 0 : 1) + ($allowed ? 1 : 0), $this->events()->count());
        $current = $this->read()->assertOk();
        $current->assertJsonPath('data.decision', $allowed ? $next : $from);
        $this->assertSame(3, $this->item->fresh()->row_version);
        $this->assertSame('wrong', $this->item->fresh()->latest_outcome);
        $this->assertSame('finding_pending', $this->item->fresh()->queue_state);
    }

    public static function transitions(): array
    {
        $cases = [];
        foreach ([null, 'needs_review', 'confirmed', 'dismissed'] as $from) {
            foreach (['needs_review', 'confirmed', 'dismissed'] as $next) {
                $cases[($from ?? 'initial').'->'.$next] = [$from, $next, $from === null || ($from === 'needs_review' && $next !== 'needs_review')];
            }
        }

        return $cases;
    }

    public function test_current_empty_then_history_is_single_current_state_and_paginated_without_reason(): void
    {
        $this->read()->assertOk()->assertJsonPath('data.resolution_version', 0)->assertJsonPath('data.decision', null);
        $this->read('/history')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
        $first = $this->save(['decision' => 'needs_review'])->assertOk()->json('data');
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $this->now = $this->now->addSecond();
        $last = $this->save(['decision' => 'dismissed', 'resolution_version' => 1, 'idempotency_key' => 'finish'])->assertOk()->json('data');
        $this->assertSame($other->id, $last['actor_id']);
        $this->assertSame($last, $this->read()->assertOk()->json('data'));
        $this->assertSame($last, $this->read('/history', ['per_page' => 1])->assertOk()->assertJsonPath('meta.total', 2)->json('data.0'));
        $this->assertSame($first, $this->read('/history', ['per_page' => 1, 'page' => 2])->assertOk()->json('data.0'));
        $this->read('/history', ['page' => 3])->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 2);
    }

    public function test_retry_is_same_receipt_even_after_later_decision_and_payload_conflicts_fail(): void
    {
        $original = $this->input(['decision' => 'needs_review']);
        $first = $this->postJson($this->url(), $original)->assertOk()->json('data');
        $this->save(['decision' => 'confirmed', 'resolution_version' => 1, 'idempotency_key' => 'finish'])->assertOk();
        $this->assertSame($first, $this->postJson($this->url(), $original)->assertOk()->json('data'));
        $this->postJson($this->url(), array_replace($original, ['reason' => 'different']))->assertStatus(409)->assertJsonPath('error', 'idempotency_payload_conflict');
        $this->save(['resolution_version' => 0, 'idempotency_key' => 'stale'])->assertStatus(409)->assertJsonPath('error', 'resolution_version_conflict');
        $this->assertSame(2, $this->events()->count());
    }

    #[DataProvider('invalidInputs')]
    public function test_validation_is_safe_and_reason_required(array $overrides): void
    {
        $this->save($overrides)->assertStatus(422)->assertDontSee('PRIVATE-REASON', false);
        $this->assertSame(0, $this->events()->count());
    }

    public static function invalidInputs(): array
    {
        return [[['reason' => null]], [['reason' => '  ']], [['reason' => str_repeat('é', 2001)]], [['reason' => ['PRIVATE-REASON']]],
            [['decision' => 'reopen']], [['actor_id' => 9]], [['resolution_version' => -1]], [['attempt_id' => 0]],
            [['row_version' => 0]], [['month' => '2026-13']], [['idempotency_key' => 'bad key']]];
    }

    #[DataProvider('deniedAccounts')]
    public function test_authorization_on_each_route(?string $role, ?string $status): void
    {
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $actor = $this->fixtures->admin();
            $values = ['role' => $role, 'status' => $status];
            if ($role !== 'admin') {
                $values['dealer_id'] = $this->fixtures->dealer()->id;
            }
            $this->fixtures->change($actor, $values);
            $this->actingAs($actor->fresh());
        }
        $expected = $role === null ? 401 : 403;
        $this->save()->assertStatus($expected);
        $this->read()->assertStatus($expected);
        $this->read('/history')->assertStatus($expected);
        $this->assertSame(0, $this->events()->count());
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'],
            ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_fresh_blocked_actor_and_policy_are_enforced_in_direct_action(): void
    {
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->save()->assertForbidden();
        try {
            app(ResolveFinding::class)->execute($this->batch->id, $this->item->id, $this->input());
            $this->fail('Stale Auth must not authorize blocked actor.');
        } catch (CallCommandRejected $error) {
            $this->assertSame(403, $error->status);
        }
        $this->assertSame(0, $this->events()->count());
    }

    public function test_separate_resolution_policy_cannot_be_bypassed_by_active_admin(): void
    {
        Gate::before(fn ($actor, $ability) => $ability === 'resolveFinding' ? false : null);
        $this->save()->assertForbidden()->assertJsonPath('error', 'finding_resolution_forbidden');
        $this->read()->assertOk()->assertJsonPath('data.resolution_version', 0);
        $this->assertSame(0, $this->events()->count());
    }

    #[DataProvider('nonFindings')]
    public function test_real_non_wrong_evidence_cannot_be_resolved(?string $outcome): void
    {
        $frozen = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'),
            $this->fixtures->tag.'-award', $this->fixtures->award()->id);
        $batch = CallAuditBatch::findOrFail($frozen->summary['batch_id']);
        $item = $batch->items()->sole();
        if ($outcome !== null) {
            $this->record($item, $outcome);
        }
        $url = '/admin/call-audits/'.$batch->id.'/findings/'.$item->id.'/resolution';
        $this->getJson($url.'?purpose=award_top3')->assertNotFound();
        $this->postJson($url, $this->input(['purpose' => 'award_top3']))->assertNotFound();
        $this->assertSame(0, $batch->events()->where('event_type', 'finding_resolution_recorded')->count());
    }

    public static function nonFindings(): array
    {
        return ['uncalled' => [null], 'correct' => ['correct'], 'no-answer' => ['no_answer']];
    }

    public function test_fake_pending_without_attempt_is_rejected_without_repair(): void
    {
        $input = $this->input();
        DB::table('call_audit_events')->where('item_id', $this->item->id)->where('event_type', 'call_result_recorded')->delete();
        DB::table('call_audit_attempts')->where('item_id', $this->item->id)->delete();
        $before = $this->snapshot();
        $this->postJson($this->url(), $input)->assertStatus(409);
        $this->read()->assertStatus(409);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_source_changes_do_not_rewrite_historical_finding_and_actor_is_persisted(): void
    {
        $this->fixtures->change($this->item->submission, ['customer_name' => 'Changed live customer', 'phone' => '0912345678', 'phone_normalized' => '0912345678']);
        $this->save(['decision' => 'needs_review'])->assertOk();
        $this->fixtures->change($this->admin, ['name' => 'Changed live actor', 'status' => 'blocked']);
        $this->actingAs($this->fixtures->admin());
        $this->read()->assertOk()->assertJsonPath('data.actor_id', $this->admin->id)->assertDontSee('Changed live actor');
        $this->save(['decision' => 'dismissed', 'resolution_version' => 1, 'idempotency_key' => 'finish'])->assertOk();
        $this->assertSame('Changed live customer', $this->item->submission()->sole()->customer_name);
    }

    public function test_scope_csrf_unknown_fields_and_evidence_version(): void
    {
        $this->save(['purpose' => 'award_top3'])->assertNotFound();
        $this->save(['month' => '2026-09'])->assertNotFound();
        $this->read('', ['month' => '2026-09'])->assertNotFound();
        $this->postJson('/admin/call-audits/'.$this->batch->id.'/findings/999999999/resolution', $this->input())->assertNotFound();
        foreach (['attempt_id', 'row_version'] as $field) {
            $this->save([$field => 999999])->assertStatus(409)->assertJsonPath('error', 'finding_evidence_version_conflict');
        }
        $this->postJson($this->url().'?reason=PRIVATE-REASON', $this->input())->assertStatus(422)->assertDontSee('PRIVATE-REASON');
        $this->read('', ['reason' => 'PRIVATE-REASON'])->assertStatus(422)->assertDontSee('PRIVATE-REASON');
        $this->read('', ['page' => 1])->assertStatus(422);
        $this->read('/history', ['per_page' => 51])->assertStatus(422);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->save()->assertStatus(419);
        $this->withSession(['_token' => 'resolution-csrf'])->withHeader('X-CSRF-TOKEN', 'resolution-csrf')->save()->assertOk();
        foreach (['show', 'history', 'store'] as $name) {
            $route = app('router')->getRoutes()->getByName('admin.call-audits.resolutions.'.$name);
            $this->assertSame($name === 'store' ? ['POST'] : ['GET', 'HEAD'], $route->methods());
            foreach (['web', 'auth', 'auth.session', 'role:admin', 'account.active', 'throttle:admin-writes'] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
        }
    }

    #[DataProvider('tampering')]
    public function test_tampered_evidence_or_resolution_is_never_accepted(string $kind): void
    {
        $this->save(['decision' => 'needs_review'])->assertOk();
        $event = $this->events()->sole();
        if ($kind === 'call_digest') {
            DB::table('call_audit_events')->where('item_id', $this->item->id)->where('event_type', 'call_result_recorded')->update(['event_digest' => str_repeat('a', 64)]);
        } elseif ($kind === 'source') {
            DB::table('call_audit_items')->where('id', $this->item->id)->update(['source_fingerprint' => str_repeat('a', 64)]);
        } elseif ($kind === 'receipt') {
            $private = $event->private_payload_encrypted;
            $private['input']['reason'] = 'altered reason';
            $cipher = (new CallAuditEvent)->forceFill(['private_payload_encrypted' => $private])->getAttributes()['private_payload_encrypted'];
            DB::table('call_audit_events')->where('id', $event->id)->update(['private_payload_encrypted' => $cipher]);
        } else {
            $fields = ['digest' => ['event_digest' => str_repeat('b', 64)], 'version' => ['after_version' => 9],
                'actor' => ['actor_id' => $this->fixtures->admin()->id], 'type' => ['event_type' => 'incorrect'],
                'metadata' => ['metadata' => json_encode(['decision' => 'confirmed'])], 'anchor' => ['attempt_id' => null],
                'predecessor' => ['previous_event_digest' => str_repeat('c', 64)], 'key' => ['command_key' => 'altered-key']];
            DB::table('call_audit_events')->where('id', $event->id)->update($fields[$kind]);
        }
        Log::spy();
        $before = $this->snapshot();
        $this->read()->assertStatus(409)->assertDontSee('PRIVATE-REASON');
        $this->read('/history')->assertStatus(409);
        $this->save(['decision' => 'confirmed', 'resolution_version' => 1, 'idempotency_key' => 'next'])->assertStatus(409);
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('error');
    }

    public static function tampering(): array
    {
        return array_map(fn ($kind) => [$kind], ['call_digest', 'source', 'receipt', 'digest', 'version', 'actor', 'type', 'metadata', 'anchor', 'predecessor', 'key']);
    }

    public function test_rollback_after_insert_has_no_partial_event_or_business_effect(): void
    {
        $before = $this->snapshot();
        $fail = true;
        DB::listen(function ($query) use (&$fail): void {
            if ($fail && str_starts_with($query->sql, 'insert into `call_audit_events`')) {
                $fail = false;
                throw new \RuntimeException('PRIVATE-REASON must not be logged');
            }
        });
        Log::spy();
        $this->save()->assertStatus(503)->assertExactJson(['error' => 'finding_resolution_unavailable']);
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('error');
        $this->save()->assertOk();
    }

    public function test_encryption_append_only_privacy_and_unchanged_c1_c2_and_business(): void
    {
        $before = $this->snapshot(['call_audit_events']);
        $oldEvents = $this->batch->events()->orderBy('id')->get()->map->getAttributes()->all();
        $this->save(['reason' => '  PRIVATE-REASON  '])->assertOk()->assertJsonPath('data.resolution_version', 1)->assertDontSee('PRIVATE-REASON');
        $event = $this->events()->sole();
        $this->assertSame('PRIVATE-REASON', $event->private_payload_encrypted['input']['reason']);
        $this->assertStringNotContainsString('PRIVATE-REASON', $event->getRawOriginal('private_payload_encrypted'));
        $this->assertStringNotContainsString('PRIVATE-REASON', json_encode($event->metadata));
        $this->assertSame($before, $this->snapshot(['call_audit_events']));
        $this->assertSame($oldEvents, $this->batch->events()->where('event_type', '!=', 'finding_resolution_recorded')->orderBy('id')->get()->map->getAttributes()->all());
        $after = $this->snapshot();
        $this->app->bind(ResolveFinding::class, fn () => throw new \RuntimeException('GET must not resolve writer'));
        foreach (['', '/history'] as $suffix) {
            $response = $this->read($suffix)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach (['PRIVATE-REASON', 'seed', 'manifest', 'payload_digest', 'source_snapshot', 'claim_token', 'phone', 'facebook', 'reason'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $this->assertSame($after, $this->snapshot());
        $this->getJson('/admin/call-audits/'.$this->batch->id.'/findings/'.$this->item->id.'?purpose=general_audit')->assertOk()->assertJsonPath('data.status', 'finding_pending');
        $this->getJson('/admin/call-audits/'.$this->batch->id.'?purpose=general_audit')->assertOk();
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($this->batch->id)->summary['status']);
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $event->forceFill(['event_type' => 'changed'])->save() : $event->delete();
                $this->fail('Resolution events must be append-only.');
            } catch (\LogicException) {
                $this->assertSame(1, $this->events()->count());
            }
        }
    }

    public function test_c07_c08_same_source_resolutions_are_independent_and_cross_item_idor_fails(): void
    {
        $frozen = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'),
            $this->fixtures->tag.'-award', $this->fixtures->award()->id);
        $award = CallAuditBatch::findOrFail($frozen->summary['batch_id']);
        $otherItem = $award->items()->sole();
        $this->record($otherItem);
        $this->save()->assertOk();
        $otherUrl = '/admin/call-audits/'.$award->id.'/findings/'.$otherItem->id.'/resolution';
        $this->getJson($otherUrl.'?purpose=award_top3')->assertOk()->assertJsonPath('data.resolution_version', 0);
        $input = $this->input(['purpose' => 'award_top3', 'attempt_id' => $otherItem->attempts()->sole()->id, 'decision' => 'dismissed']);
        $this->postJson($otherUrl, $input)->assertOk()->assertJsonPath('data.decision', 'dismissed');
        $this->read()->assertOk()->assertJsonPath('data.decision', 'confirmed');
        $this->postJson('/admin/call-audits/'.$this->batch->id.'/findings/'.$otherItem->id.'/resolution', $this->input())->assertNotFound();
        $this->getJson('/admin/call-audits/'.$this->batch->id.'/findings/'.$otherItem->id.'/resolution?purpose=general_audit')->assertNotFound();
        $this->getJson($otherUrl.'?purpose=general_audit')->assertNotFound();
    }

    #[DataProvider('races')]
    public function test_real_mysql_concurrent_resolution(bool $sameActor): void
    {
        $actors = [$this->admin, $sameActor ? $this->admin : $this->fixtures->admin()];
        $directory = storage_path('app/private/p09o-resolution-races/'.$this->fixtures->tag);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $config = DB::connection()->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09o-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing',
            'TOYOTA_TEST_DATABASE' => 'toyota_testing', 'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $workers = [];
        try {
            foreach (['holder', 'contender'] as $index => $role) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/call_audit_resolution_worker.php'),
                    (string) $actors[$index]->id, (string) $this->batch->id, (string) $this->item->id,
                    $this->fixtures->tag, $directory, $role], base_path(), $environment, null, 20);
                $workers[] = $worker;
                $worker->start();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getOutput().$worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($directory.'/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
            $this->assertSame('RESOLVED', $results[0]['status']);
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
            $this->assertSame($sameActor ? 'RESOLVED' : 'CONFLICT', $results[1]['status']);
            if ($sameActor) {
                $this->assertSame($results[0]['response'], $results[1]['response']);
            } else {
                $this->assertSame('resolution_version_conflict', $results[1]['code']);
            }
            $this->assertSame(1, $this->events()->count());
            $this->assertSame($this->admin->id, $this->events()->sole()->actor_id);
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
        return ['same-request' => [true], 'two-admins' => [false]];
    }

    private function record(CallAuditItem $item, string $outcome = 'wrong'): void
    {
        $claim = app(ClaimItem::class)->execute($item->batch_id, $item->id,
            ['purpose' => $item->batch->purpose, 'row_version' => $item->row_version, 'idempotency_key' => 'claim-'.$item->id]);
        app(RecordCallResult::class)->execute($item->batch_id, $item->id, [
            'purpose' => $item->batch->purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'record-'.$item->id, 'outcome' => $outcome,
            'answer_1' => $outcome === 'no_answer' ? null : $outcome === 'correct',
            'answer_2' => $outcome === 'no_answer' ? null : true, 'answer_3' => $outcome === 'no_answer' ? null : true,
        ]);
    }

    private function events()
    {
        return $this->batch->events()->where('event_type', 'finding_resolution_recorded');
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['purpose' => 'general_audit', 'month' => '2026-10', 'attempt_id' => $this->item->attempts()->sole()->id,
            'row_version' => 3, 'resolution_version' => 0, 'decision' => 'confirmed', 'reason' => 'PRIVATE-REASON', 'idempotency_key' => 'resolve'], $overrides);
    }

    private function url(): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/findings/'.$this->item->id.'/resolution';
    }

    private function save(array $overrides = [])
    {
        return $this->postJson($this->url(), $this->input($overrides));
    }

    private function read(string $suffix = '', array $filters = [])
    {
        $this->reading = true;
        try {
            return $this->getJson($this->url().$suffix.'?'.http_build_query(array_replace(['purpose' => 'general_audit'], $filters)));
        } finally {
            $this->reading = false;
        }
    }

    private function snapshot(array $except = []): array
    {
        $result = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
            $table = array_values((array) $row)[0];
            if (! in_array($table, $except, true)) {
                $result[$table] = [DB::table($table)->count(), (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED')];
            }
        }

        return $result;
    }
}
