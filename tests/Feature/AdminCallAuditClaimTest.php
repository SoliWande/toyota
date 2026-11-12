<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
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

class AdminCallAuditClaimTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private CallAuditBatch $batch;

    private CallAuditItem $item;

    private CarbonImmutable $now;

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
    public function test_both_post_routes_reject_unauthorized_accounts(?string $role, ?string $status): void
    {
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $account = $this->fixtures->admin();
            $values = ['role' => $role, 'status' => $status];
            if ($role !== 'admin') {
                $values['dealer_id'] = $this->fixtures->dealer()->id;
            }
            $this->fixtures->change($account, $values);
            $this->actingAs($account->fresh());
        }
        foreach (['claim', 'release'] as $command) {
            $this->postCommand($command, ['claim_token' => str_repeat('a', 64)])->assertStatus($role === null ? 401 : 403);
        }
        $this->assertSame(1, $this->batch->events()->count());
        $this->assertNull($this->item->fresh()->claim_owner_id);
    }

    public static function deniedAccounts(): array
    {
        return [['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'],
            ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked'], [null, null]];
    }

    public function test_other_admin_can_claim_creator_batch_and_stale_auth_status_is_rechecked(): void
    {
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $data = $this->postCommand('claim')->assertOk()->json('data');
        $this->assertSame($other->id, $this->item->fresh()->claim_owner_id);
        $this->assertSame($other->id, $this->batch->events()->where('event_type', 'claim_item')->sole()->actor_id);
        $this->fixtures->change($other, ['status' => 'blocked']);
        $this->postCommand('release', ['row_version' => 2, 'claim_token' => $data['claim_token']])->assertForbidden();
        try {
            app(ClaimItem::class)->execute($this->batch->id, $this->item->id, $this->input());
            $this->fail('Direct action must recheck authorization.');
        } catch (CallCommandRejected $rejected) {
            $this->assertSame(403, $rejected->status);
        }
        $this->assertSame(2, $this->batch->events()->count());
    }

    public function test_claim_release_receipts_are_idempotent_and_keep_actor_version_history(): void
    {
        $data = $this->postCommand('claim')->assertOk()->json('data');
        $this->assertSame(2, $data['row_version']);
        $this->assertSame($this->now->addSeconds(600)->toIso8601String(), $data['claim_expires_at']);
        $this->postCommand('claim')->assertExactJson(['data' => $data]);
        $item = $this->item->fresh();
        $this->assertSame(hash('sha256', $data['claim_token']), $item->claim_token_hash);
        $this->assertNotSame($data['claim_token'], $item->claim_token_hash);
        $claimEvent = $this->batch->events()->where('event_type', 'claim_item')->sole();
        $this->assertStringNotContainsString($data['claim_token'], $claimEvent->getRawOriginal('private_payload_encrypted'));
        $this->assertSame($data, $claimEvent->private_payload_encrypted['response']);
        $releaseInput = ['row_version' => 2, 'idempotency_key' => 'release-1', 'claim_token' => $data['claim_token']];
        $released = $this->postCommand('release', $releaseInput)->assertOk()->json();
        $this->postCommand('release', $releaseInput)->assertExactJson($released);
        $this->assertSame(['item_id' => $item->id, 'row_version' => 3, 'released' => true], $released['data']);
        $this->assertNull($item->fresh()->claim_owner_id);
        $this->assertNull($item->fresh()->claim_token_hash);
        $this->assertNull($item->fresh()->claim_expires_at);
        $events = $this->batch->events()->orderBy('event_sequence')->get();
        $this->assertSame([1, 2, 3], $events->pluck('event_sequence')->all());
        $this->assertSame([null, 1, 2], $events->pluck('before_version')->all());
        $this->assertSame([1, 2, 3], $events->pluck('after_version')->all());
        $this->assertSame($events[0]->event_digest, $events[1]->previous_event_digest);
        $this->assertSame($events[1]->event_digest, $events[2]->previous_event_digest);
        $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($this->batch->id)->summary['status']);
    }

    public function test_same_key_changed_payload_conflicts_instead_of_renewing_or_switching_items(): void
    {
        $this->postCommand('claim')->assertOk();
        $this->postCommand('claim', ['row_version' => 2])->assertStatus(409)->assertJsonPath('error', 'idempotency_payload_conflict');
        $this->postCommand('claim', ['row_version' => 2, 'idempotency_key' => 'another'])->assertStatus(409)
            ->assertJsonPath('error', 'item_already_claimed');
        $this->assertSame(2, $this->batch->events()->count());
    }

    public function test_other_admin_wrong_token_and_wrong_version_cannot_release_or_overwrite_claim(): void
    {
        $claim = $this->postCommand('claim')->assertOk()->json('data');
        $this->postCommand('release', ['row_version' => 1, 'claim_token' => $claim['claim_token']])->assertStatus(409)->assertJsonPath('error', 'item_version_conflict');
        $this->postCommand('release', ['row_version' => 2, 'claim_token' => str_repeat('f', 64)])->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->actingAs($this->fixtures->admin());
        $this->postCommand('claim', ['row_version' => 2])->assertStatus(409)->assertJsonPath('error', 'item_already_claimed');
        $this->postCommand('release', ['row_version' => 2, 'claim_token' => $claim['claim_token']])->assertStatus(409)->assertJsonPath('error', 'claim_ownership_conflict');
        $this->assertSame($this->admin->id, $this->item->fresh()->claim_owner_id);
        $this->assertSame(2, $this->item->fresh()->row_version);
    }

    public function test_expiry_boundary_allows_new_claim_but_no_old_token_or_receipt_can_override(): void
    {
        $claim = $this->postCommand('claim')->assertOk()->json('data');
        $this->now = $this->now->addSeconds(600);
        $this->postCommand('claim')->assertStatus(409)->assertJsonPath('error', 'claim_receipt_obsolete');
        $this->postCommand('release', ['row_version' => 2, 'claim_token' => $claim['claim_token']])->assertStatus(409);
        $newOwner = $this->fixtures->admin();
        $this->actingAs($newOwner);
        $new = $this->postCommand('claim', ['row_version' => 2])->assertOk()->json('data');
        $this->assertNotSame($claim['claim_token'], $new['claim_token']);
        $this->assertSame(3, $new['row_version']);
        $this->actingAs($this->admin);
        $this->postCommand('release', ['row_version' => 3, 'claim_token' => $claim['claim_token']])->assertStatus(409);
        $this->assertSame($newOwner->id, $this->item->fresh()->claim_owner_id);
        $this->assertSame(3, $this->batch->events()->count());
    }

    public function test_release_retry_after_another_claim_does_not_clear_the_new_lease(): void
    {
        $first = $this->postCommand('claim')->assertOk()->json('data');
        $input = ['row_version' => 2, 'claim_token' => $first['claim_token']];
        $receipt = $this->postCommand('release', $input)->assertOk()->json();
        $other = $this->fixtures->admin();
        $this->actingAs($other);
        $this->postCommand('claim', ['row_version' => 3])->assertOk();
        $this->actingAs($this->admin);
        $this->postCommand('release', $input)->assertExactJson($receipt);
        $this->assertSame($other->id, $this->item->fresh()->claim_owner_id);
        $this->assertSame(4, $this->batch->events()->count());
    }

    public function test_idor_purpose_nonfrozen_and_missing_items_fail_without_writes(): void
    {
        foreach (['claim', 'release'] as $command) {
            $this->postCommand($command, ['purpose' => 'award_top3', 'claim_token' => str_repeat('a', 64)])->assertStatus($command === 'claim' ? 422 : 404);
            $this->postJson('/admin/call-audits/'.$this->batch->id.'/items/999999999/'.$command,
                $this->input($command === 'release' ? ['claim_token' => str_repeat('a', 64)] : []))->assertNotFound();
        }
        // A second real batch is frozen with the same submission, but child IDs remain scoped.
        $award = $this->fixtures->award();
        $result = app(CreateAndFreezeBatch::class)->execute('award_top3', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-award', $award->id);
        $this->assertSame('FROZEN', $result->summary['status']);
        $this->postJson('/admin/call-audits/'.$result->summary['batch_id'].'/items/'.$this->item->id.'/claim',
            $this->input(['purpose' => 'award_top3']))->assertNotFound();
        DB::table('call_audit_batches')->where('id', $this->batch->id)->update(['lifecycle_state' => 'void']);
        $this->postCommand('claim')->assertNotFound();
        $this->assertNull($this->item->fresh()->claim_owner_id);
        $this->assertSame(1, $this->batch->events()->count());
    }

    public function test_invalid_input_is_not_echoed_logged_or_flashed_and_get_cannot_claim(): void
    {
        Log::spy();
        $input = $this->input(['actor_id' => 999, 'phone' => '0901234567']);
        $this->postJson($this->url('claim'), $input)->assertStatus(422)->assertExactJson(['error' => 'invalid_call_command']);
        $this->postJson($this->url('claim').'?phone=0901234567', $this->input())->assertStatus(422);
        $this->postCommand('claim', ['idempotency_key' => str_repeat('a', 65)])->assertStatus(422);
        $this->postCommand('claim', ['row_version' => []])->assertStatus(422);
        $this->getJson($this->url('claim'))->assertStatus(405);
        $this->assertNull(session()->getOldInput('phone'));
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('info');
        $this->assertNull($this->item->fresh()->claim_owner_id);
    }

    public function test_source_drift_conflicts_without_replacing_frozen_snapshot_or_claim(): void
    {
        $snapshot = $this->item->getRawOriginal('source_snapshot_encrypted');
        $source = $this->item->submission;
        $this->fixtures->change($source, ['customer_name' => 'changed source']);
        $this->postCommand('claim')->assertStatus(409)->assertExactJson(['error' => 'source_drift_conflict']);
        $this->assertNull($this->item->fresh()->claim_owner_id);
        $this->assertSame(1, $this->item->fresh()->row_version);
        $this->assertSame($snapshot, $this->item->fresh()->getRawOriginal('source_snapshot_encrypted'));
        $this->assertSame(1, $this->batch->events()->count());
        $this->assertSame('changed source', $source->fresh()->customer_name);
    }

    public function test_unapproved_call_projections_cannot_be_claimed_or_turned_into_attempts(): void
    {
        foreach ([['queue_state' => 'completed'], ['attempt_count' => 1], ['latest_outcome' => 'wrong'],
            ['retry_due_at' => '2026-11-11 12:00:00'], ['source_stale_at' => '2026-11-10 12:00:00']] as $projection) {
            DB::table('call_audit_items')->where('id', $this->item->id)->update(array_replace([
                'queue_state' => 'uncalled', 'attempt_count' => 0, 'latest_outcome' => null,
                'retry_due_at' => null, 'source_stale_at' => null,
            ], $projection));
            $this->postCommand('claim')->assertStatus(409)->assertJsonPath('error', 'item_not_claimable');
        }
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(1, $this->batch->events()->count());
    }

    public function test_unapproved_correction_actions_remain_unavailable(): void
    {
        foreach (['result', 'retry', 'correct', 'void'] as $action) {
            $this->postJson($this->url($action), ['outcome' => 'wrong', 'answer_1' => false])->assertNotFound();
        }
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(1, $this->batch->events()->count());
        $this->assertNull($this->item->fresh()->latest_outcome);
    }

    public function test_event_insert_failure_rolls_back_item_and_does_not_log_private_exception(): void
    {
        Log::spy();
        $before = $this->item->fresh()->getAttributes();
        $fail = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fail): void {
            if ($fail && str_starts_with($sql, 'insert into `call_audit_events`')) {
                $fail = false;
                throw new \RuntimeException('private-token-and-customer');
            }
        });
        $this->postCommand('claim')->assertStatus(503)->assertExactJson(['error' => 'audit_command_unavailable']);
        $this->assertSame($before, $this->item->fresh()->getAttributes());
        $this->assertSame(1, $this->batch->events()->count());
        $this->assertSame(0, $this->item->attempts()->count());
        Log::shouldNotHaveReceived('error');
        $this->postCommand('claim')->assertOk();
    }

    public function test_claim_does_not_create_attempts_or_change_business_data_or_read_payload(): void
    {
        $tables = ['customer_submissions', 'submission_verifications', 'awards', 'award_winners', 'audit_logs', 'users', 'dealers'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED');
        }
        $data = $this->postCommand('claim')->assertOk()->json('data');
        $read = $this->getJson('/admin/call-audits/'.$this->batch->id.'/items/'.$this->item->id.'?purpose=general_audit')->assertOk()
            ->assertJsonPath('data.row_version', 2);
        foreach (['claim_token', 'claim_token_hash', 'seed', 'manifest', '0900000001', $data['claim_token']] as $private) {
            $this->assertStringNotContainsString($private, $read->getContent());
        }
        $this->assertSame(0, $this->item->attempts()->count());
        $this->assertSame(0, $this->item->fresh()->attempt_count);
        $this->assertSame('uncalled', $this->item->fresh()->queue_state);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'));
        }
    }

    public function test_csrf_is_enforced_and_post_routes_have_existing_admin_throttle(): void
    {
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->postCommand('claim')->assertStatus(419);
        $this->withSession(['_token' => 'test-only-csrf'])->withHeader('X-CSRF-TOKEN', 'test-only-csrf')
            ->postCommand('claim')->assertOk();
        foreach (['claim', 'release'] as $command) {
            $route = app('router')->getRoutes()->getByName('admin.call-audits.'.$command);
            $this->assertSame(['POST'], $route->methods());
            foreach (['web', 'auth', 'auth.session', 'role:admin', 'account.active', 'throttle:admin-writes'] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
        }
    }

    public function test_two_real_mysql_connections_cannot_claim_the_same_item(): void
    {
        $admins = [$this->admin, $this->fixtures->admin()];
        $directory = storage_path('app/private/p09j-races/'.$this->fixtures->tag);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $config = DB::connection()->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09j-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing',
            'TOYOTA_TEST_DATABASE' => 'toyota_testing', 'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $workers = [];
        try {
            foreach (['holder', 'contender'] as $index => $role) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/call_audit_claim_worker.php'),
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
            $this->assertSame('CLAIMED', $results[0]['status']);
            $this->assertSame('CONFLICT', $results[1]['status']);
            $this->assertSame('item_version_conflict', $results[1]['code']);
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            $this->assertSame($this->admin->id, $this->item->fresh()->claim_owner_id);
            $this->assertSame(2, $this->item->fresh()->row_version);
            $this->assertSame(2, $this->batch->events()->count());
            $this->assertSame(0, $this->item->attempts()->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'test-request'], $overrides);
    }

    private function url(string $command): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/items/'.$this->item->id.'/'.$command;
    }

    private function postCommand(string $command, array $overrides = [])
    {
        return $this->postJson($this->url($command), $this->input($overrides));
    }
}
