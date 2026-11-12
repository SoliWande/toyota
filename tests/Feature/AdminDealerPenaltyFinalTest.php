<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ApplyDealerPenalty;
use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Actions\CallAudit\ResolveFinding;
use App\Enums\LeaderboardPeriod;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FreezeClock;
use App\Services\LeaderboardService;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminDealerPenaltyFinalTest extends TestCase
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

    public function test_apply_retry_effective_rank_sales_and_draft_fingerprint_without_business_mutation(): void
    {
        $this->award = $this->fixtures->award();
        $results = app(AwardResults::class);
        $preview = $results->preview($this->award);
        $hash = $results->fingerprint($this->award, $preview);
        $item = $this->confirm($this->sources[0][0]);
        $before = $this->businessState();
        $first = $this->apply($item)->assertOk()->json('data');
        $this->assertTrue($first['created']);
        $this->assertSame($first, $this->apply($item)->assertOk()->json('data'));
        $this->assertSame(1, DB::table('dealer_penalty_entries')->count());
        $this->assertSame(1, DB::table('dealer_penalty_events')->count());
        $this->assertSame($before, $this->businessState());
        $lb = app(LeaderboardService::class);
        $rows = $lb->dealers(LeaderboardPeriod::Month, CarbonImmutable::parse('2026-10-15'))->get();
        $row = $rows->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([3, 1, 2, 2, 2], array_map('intval', [$row->raw_score, $row->penalty_points, $row->display_score, $row->score, $row->rank]));
        $this->assertSame(3, (int) $lb->salesForRange(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'))->first()->score);
        $this->assertSame(3, (int) $lb->dealers(LeaderboardPeriod::AllTime)->get()->firstWhere('id', $this->dealers[0]->id)->score);
        $this->assertNotSame($hash, $results->fingerprint($this->award, $results->preview($this->award)));
        $this->apply($item, ['resolution_version' => 2])->assertStatus(409)->assertJsonPath('error', 'idempotency_payload_conflict');
    }

    public function test_two_purposes_share_one_debit_and_preserve_both_evidence_links(): void
    {
        $other = $this->freeze('award_top3');
        $first = $this->confirm($this->sources[0][0]);
        $second = $this->confirm($this->sources[0][0], 'confirmed', $other);
        $this->apply($first)->assertOk()->assertJsonPath('data.evidence_count', 2);
        $this->apply($second, ['purpose' => 'award_top3', 'idempotency_key' => 'other-purpose'])->assertOk()->assertJsonPath('data.created', false);
        $this->assertSame(1, DB::table('dealer_penalty_entries')->count());
        $this->assertSame(2, DB::table('dealer_penalty_evidence')->count());
        $this->assertSame(2, DB::table('dealer_penalty_events')->count());
    }

    public function test_display_floor_and_top3_change_do_not_penalize_sales_or_modify_snapshots(): void
    {
        $this->award = $this->fixtures->award();
        $preview = app(AwardResults::class)->preview($this->award);
        DB::transaction(function () use ($preview) {
            foreach (collect($preview['dealers'])->merge($preview['sales']) as $row) {
                (new AwardWinner)->forceFill(['award_id' => $this->award->id] + $row)->save();
            }
        });
        $this->fixtures->change($this->award, ['published_at' => '2026-11-03', 'published_by' => $this->admin->id]);
        $before = (array) DB::selectOne('CHECKSUM TABLE award_winners EXTENDED');
        foreach ($this->sources[0] as $i => $source) {
            $item = $this->confirm($source);
            $this->apply($item, ['idempotency_key' => 'apply-'.$i])->assertOk();
        }
        $rows = app(LeaderboardService::class)->dealersForRange(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'))->get();
        $a = $rows->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([3, 3, 0, 4], array_map('intval', [$a->raw_score, $a->penalty_points, $a->display_score, $a->rank]));
        $this->assertSame($before, (array) DB::selectOne('CHECKSUM TABLE award_winners EXTENDED'));
        $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertOk()->assertJsonPath('status', 'published')->assertJsonPath('can_publish', false);
    }

    public function test_publication_without_top3_coverage_and_applied_penalties_fails_closed(): void
    {
        $this->award = $this->fixtures->award();
        $this->confirm($this->sources[0][0]);
        $before = $this->snapshot();
        $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertOk()->assertJsonPath('can_publish', false)
            ->assertJsonPath('blockers.0', 'confirmed_penalties_outstanding');
        $this->postJson('/admin/awards/'.$this->award->id.'/publish', ['idempotency_key' => 'not-ready'])->assertStatus(409)->assertJsonPath('error', 'publication_not_ready');
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('failurePoints')]
    public function test_failure_rolls_back_all_penalty_rows_and_a_retry_can_succeed(string $prefix): void
    {
        $item = $this->confirm($this->sources[0][0]);
        $before = $this->snapshot();
        $fail = true;
        DB::connection()->beforeExecuting(function ($sql) use (&$fail, $prefix) {
            if ($fail && str_starts_with($sql, $prefix)) {
                $fail = false;
                throw new \RuntimeException('PRIVATE SQL must not be logged');
            }
        });
        Log::spy();
        $this->apply($item)->assertStatus(503)->assertExactJson(['error' => 'penalty_unavailable']);
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('error');
        $this->apply($item)->assertOk();
    }

    public static function failurePoints(): array
    {
        return [['insert into `dealer_penalty_evidence`'], ['insert into `dealer_penalty_events`']];
    }

    public function test_permission_csrf_scope_version_and_invalid_or_unconfirmed_evidence(): void
    {
        $item = $this->confirm($this->sources[0][0], 'needs_review');
        $this->apply($item)->assertStatus(409)->assertJsonPath('error', 'confirmed_finding_required');
        $item = $this->confirm($this->sources[0][1]);
        $this->apply($item, ['resolution_version' => 2])->assertStatus(409);
        $this->apply($item, ['purpose' => 'award_top3'])->assertNotFound();
        $this->apply($item, ['month' => '2026-09'])->assertNotFound();
        $this->apply($item, ['points' => 50])->assertStatus(422);
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->apply($item)->assertForbidden();
        $this->fixtures->change($this->admin, ['status' => 'active']);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->apply($item)->assertStatus(419);
        $this->withSession(['_token' => 'penalty-csrf'])->withHeader('X-CSRF-TOKEN', 'penalty-csrf')->apply($item)->assertOk();
        $this->getJson('/admin/call-audits/penalties?month=2026-10')->assertOk()->assertDontSee('intent_encrypted');
    }

    public function test_tampered_call_or_ledger_is_rejected_without_repair(): void
    {
        $item = $this->confirm($this->sources[0][0]);
        $this->apply($item)->assertOk();
        DB::table('dealer_penalty_entries')->update(['points' => 2]);
        $before = $this->snapshot();
        $this->getJson('/admin/call-audits/penalties?month=2026-10')->assertStatus(409);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('races')]
    public function test_real_mysql_penalty_concurrency(bool $sameActor, bool $crossPurpose): void
    {
        $otherBatch = $crossPurpose ? $this->freeze('award_top3') : null;
        $a = $this->confirm($this->sources[0][0]);
        $b = $crossPurpose ? $this->confirm($this->sources[0][0], 'confirmed', $otherBatch) : $a;
        $actors = [$this->admin, $sameActor ? $this->admin : $this->fixtures->admin()];
        $directory = storage_path('app/private/p09-final-races/'.$this->fixtures->tag);
        mkdir($directory, 0700, true);
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09-final-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
            'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $workers = [];
        try {
            foreach (['holder', 'contender'] as $i => $role) {
                $item = $i === 0 ? $a : $b;
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/p09_final_penalty_worker.php'),
                    (string) $actors[$i]->id, (string) $item->batch_id, (string) $item->id, $this->fixtures->tag, $directory, $role], base_path(), $env, null, 25);
                $workers[] = $worker;
                $worker->start();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getOutput().$worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($directory.'/results.json', json_encode($results, JSON_PRETTY_PRINT), LOCK_EX);
            $this->assertSame('APPLIED', $results[0]['status']);
            $this->assertSame('APPLIED', $results[1]['status']);
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
            $this->assertSame($results[0]['response']['entry_id'], $results[1]['response']['entry_id']);
            if ($sameActor) {
                $this->assertSame($results[0]['response'], $results[1]['response']);
            }
            $this->assertSame(1, DB::table('dealer_penalty_entries')->count());
            $this->assertSame($crossPurpose ? 2 : 1, DB::table('dealer_penalty_evidence')->count());
            $this->assertSame($sameActor ? 1 : 2, DB::table('dealer_penalty_events')->count());
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
        return [[true, false], [false, false], [false, true]];
    }

    private function apply(CallAuditItem $item, array $overrides = [])
    {
        return $this->postJson('/admin/call-audits/'.$item->batch_id.'/findings/'.$item->id.'/penalty', array_replace([
            'month' => '2026-10', 'purpose' => $item->batch->purpose, 'resolution_version' => 1, 'idempotency_key' => 'apply-penalty'], $overrides));
    }

    public function test_c07_existing_call_button_opens_scoped_workspace_and_real_counts(): void
    {
        $batch = $this->freeze('award_top3');
        $item = $this->confirm($this->sources[0][0], 'confirmed', $batch);
        $url = route('admin.call-audits.workspace', ['month' => '2026-10', 'purpose' => 'award_top3', 'batch' => $batch->id]);
        $response = $this->get(route('admin.awards.index', ['selected' => $this->award->id]))->assertOk()->assertSee($url)
            ->assertSee('3 khách')->assertSee('1/3 đã gọi, 0 đúng');
        $directory = base_path('.laravel-tools/p09-final-browser');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/c07-render.html', $response->getContent(), LOCK_EX);
        $this->get($url)->assertOk()->assertSee('data-purpose="award_top3"', false)->assertSee('data-batch="'.$batch->id.'"', false);
        $this->getJson('/admin/call-audits/workspace?purpose=general_audit&batch='.$batch->id)->assertNotFound();
        $this->apply($item, ['purpose' => 'award_top3'])->assertOk();
        $this->getJson('/admin/call-audits/dealer-scores?month=2026-10')->assertOk()->assertJsonPath('data.0.dealer_id', $this->dealers[1]->id);
    }

    public function test_explicit_post_freeze_uses_a3_idempotency_and_get_does_not_create_batch(): void
    {
        $this->award = $this->fixtures->award();
        $input = ['purpose' => 'award_top3', 'month' => '2026-10', 'award_id' => $this->award->id, 'idempotency_key' => $this->fixtures->tag.'-api-freeze'];
        $response = $this->postJson('/admin/call-audits/freeze', $input)->assertOk()->assertJsonPath('data.status', 'FROZEN')->json('data');
        $this->postJson('/admin/call-audits/freeze', $input)->assertOk()->assertJsonPath('data.batch_id', $response['batch_id']);
        $this->postJson('/admin/call-audits/freeze', array_replace($input, ['idempotency_key' => 'reroll']))->assertStatus(409);
        $this->postJson('/admin/call-audits/freeze', $input + ['seed' => 'PRIVATE-SEED'])->assertStatus(422)->assertDontSee('PRIVATE-SEED');
        $this->assertSame(2, CallAuditBatch::count());
    }

    #[DataProvider('deniedAccounts')]
    public function test_final_api_authorization(?string $role, ?string $status): void
    {
        $item = $this->confirm($this->sources[0][0]);
        $this->award = $this->fixtures->award();
        if ($role === null) {
            auth()->forgetUser();
        } else {
            $actor = $this->fixtures->admin();
            $this->fixtures->change($actor, ['role' => $role, 'status' => $status] + ($role !== 'admin' ? ['dealer_id' => $this->dealers[0]->id] : []));
            $this->actingAs($actor->fresh());
        }
        $code = $role === null ? 401 : 403;
        $this->apply($item)->assertStatus($code);
        $this->getJson('/admin/call-audits/penalties?month=2026-10')->assertStatus($code);
        $this->getJson('/admin/call-audits/dealer-scores?month=2026-10')->assertStatus($code);
        $this->getJson('/admin/awards/'.$this->award->id.'/publication')->assertStatus($code);
        $this->assertSame(0, DB::table('dealer_penalty_entries')->count());
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'], ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_origin_invalid_signature_and_conflicting_frozen_dealer_are_rejected(): void
    {
        $item = $this->confirm($this->sources[0][0]);
        DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->update(['event_digest' => str_repeat('f', 64)]);
        $this->apply($item)->assertStatus(409);
        $this->assertSame(0, DB::table('dealer_penalty_entries')->count());
    }

    public function test_historic_dealer_debit_with_zero_raw_does_not_transfer_or_underflow(): void
    {
        $item = $this->confirm($this->sources[0][0]);
        foreach ($this->sources[0] as $source) {
            $this->fixtures->change($source, ['status' => 'rejected']);
        }
        $this->apply($item)->assertOk();
        $rows = app(LeaderboardService::class)->dealers(LeaderboardPeriod::Month, CarbonImmutable::parse('2026-10-15'))->get();
        $row = $rows->firstWhere('id', $this->dealers[0]->id);
        $this->assertSame([0, 1, -1, 0, 4], array_map('intval', [$row->raw_score, $row->penalty_points, $row->net_score, $row->display_score, $row->rank]));
    }

    public function test_conflicting_original_dealer_across_batches_does_not_pick_representative(): void
    {
        $source = $this->sources[0][0];
        $a = $this->confirm($source);
        $this->fixtures->change($source, ['dealer_id' => $this->dealers[1]->id]);
        $other = $this->freeze('award_top3');
        $this->confirm($source, 'confirmed', $other);
        $this->apply($a)->assertStatus(409)->assertJsonPath('error', 'dealer_attribution_conflict');
        $this->assertSame(0, DB::table('dealer_penalty_entries')->count());
    }

    public function test_same_dealer_receipt_replay_cannot_bypass_fresh_actor_policy(): void
    {
        $item = $this->confirm($this->sources[0][0]);
        $this->apply($item)->assertOk();
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->apply($item)->assertForbidden();
        try {
            app(ApplyDealerPenalty::class)->execute($item->batch_id, $item->id,
                ['month' => '2026-10', 'purpose' => 'general_audit', 'resolution_version' => 1, 'idempotency_key' => 'apply-penalty']);
            $this->fail('Fresh actor permission required on replay.');
        } catch (CallCommandRejected $e) {
            $this->assertSame(403, $e->status);
        }
        $this->assertSame(1, DB::table('dealer_penalty_events')->count());
    }

    public function test_final_gets_are_private_and_never_write(): void
    {
        $this->award = $this->fixtures->award();
        $item = $this->confirm($this->sources[0][0]);
        $this->apply($item)->assertOk();
        $before = $this->snapshot();
        $this->reading = true;
        try {
            foreach (['/admin/call-audits/penalties?month=2026-10', '/admin/call-audits/dealer-scores?month=2026-10', '/admin/awards/'.$this->award->id.'/publication'] as $url) {
                $response = $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
                foreach (['intent_encrypted', 'receipt_encrypted', 'source_fingerprint', 'manifest', 'seed', 'PRIVATE-SIMULATION-REASON', $this->sources[0][0]->phone] as $private) {
                    $response->assertDontSee($private, false);
                }
            }
        } finally {
            $this->reading = false;
        }
        $this->assertSame($before, $this->snapshot());
        $this->postJson('/admin/awards/'.$this->award->id.'/publish', ['approved' => true])->assertStatus(422);
    }

    private function businessState(): array
    {
        return array_filter($this->snapshot(), fn ($table) => ! str_starts_with($table, 'dealer_penalty_'), ARRAY_FILTER_USE_KEY);
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
