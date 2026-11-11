<?php

namespace Tests\Feature;

use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Models\CallAuditAttempt;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\SamplingPlanner;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditReadTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private bool $reading = false;

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
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturn(CarbonImmutable::parse('2026-11-10', 'UTC'));
        $this->app->instance(FreezeClock::class, $clock);
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && ! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)) {
                throw new \RuntimeException('Admin audit GET must not write to the database.');
            }
        });
    }

    protected function tearDown(): void
    {
        $this->reading = false;
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('deniedAccounts')]
    public function test_all_get_routes_reject_unauthorized_accounts(?string $role, ?string $status): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $batch = $this->freeze();
        $item = $batch->items()->first();
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
        foreach (['', '/'.$batch->id, '/'.$batch->id.'/items', '/'.$batch->id.'/items/'.$item->id] as $path) {
            $this->read($path)->assertStatus($role === null ? 401 : 403);
        }
    }

    public static function deniedAccounts(): array
    {
        return [
            'guest' => [null, null], 'sales' => ['sales', 'active'], 'dealer' => ['dealer', 'active'],
            'dealer_manager_account' => ['dealer', 'active'], 'pending_admin' => ['admin', 'pending'],
            'rejected_admin' => ['admin', 'rejected'], 'blocked_admin' => ['admin', 'blocked'],
        ];
    }

    public function test_other_active_admin_can_read_and_persisted_inactive_status_is_rechecked(): void
    {
        $batch = $this->freeze();
        $viewer = $this->fixtures->admin();
        $this->actingAs($viewer);
        $this->read('/'.$batch->id)->assertOk()->assertJsonPath('data.id', $batch->id);
        $this->fixtures->change($viewer, ['status' => 'blocked']);
        // Auth guard still holds the old active object; the read model must check the database.
        $this->read('/'.$batch->id)->assertForbidden();
    }

    public function test_month_and_purpose_filters_do_not_mix_batch_counts(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $general = $this->freeze();
        $september = $this->freeze('general_audit', '2026-09');
        $award = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $this->read('', ['month' => '2026-10'])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $general->id)->assertJsonPath('data.0.actual_count', 1);
        $this->read('', ['purpose' => 'award_top3'])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $award->id)->assertJsonPath('data.0.requested_count', 5);
        $this->read('', ['month' => '2026-09'])->assertOk()->assertJsonPath('data.0.id', $september->id)->assertJsonPath('data.0.actual_count', 0);
        $this->read('/'.$award->id)->assertNotFound();
        $this->read('/'.$general->id, ['purpose' => 'award_top3'])->assertNotFound();
        $this->read('/'.$general->id, ['month' => '2026-09'])->assertNotFound();
    }

    public function test_child_item_id_cannot_escape_its_parent_batch_scope(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $general = $this->freeze();
        $award = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $otherItem = $award->items()->first();
        $this->read('/'.$general->id.'/items/'.$otherItem->id)->assertNotFound();
        $this->read('/'.$award->id.'/items/'.$otherItem->id, ['purpose' => 'award_top3'])->assertOk();
        $this->read('/999999999/items/'.$otherItem->id)->assertNotFound();
    }

    public function test_batch_and_item_pagination_have_stable_order_and_no_url_links(): void
    {
        $dealer = $this->fixtures->dealer();
        for ($i = 0; $i < 3; $i++) {
            $this->fixtures->submission($dealer);
        }
        $october = $this->freeze();
        $september = $this->freeze('general_audit', '2026-09');
        $this->read('', ['per_page' => 1])->assertOk()->assertJsonPath('data.0.id', $october->id)->assertJsonPath('meta.total', 2);
        $this->read('', ['per_page' => 1, 'page' => 2])->assertOk()->assertJsonPath('data.0.id', $september->id);
        $ids = $october->items()->orderBy('selection_order')->pluck('id')->all();
        foreach ($ids as $index => $id) {
            $response = $this->read('/'.$october->id.'/items', ['per_page' => 1, 'page' => $index + 1]);
            $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)
                ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3);
            $this->assertArrayNotHasKey('links', $response->json());
            $this->assertArrayNotHasKey('path', $response->json('meta'));
        }
    }

    public function test_frozen_names_dealer_and_masked_phone_do_not_follow_live_customer_edits(): void
    {
        $dealer = $this->fixtures->dealer();
        $this->fixtures->change($dealer, ['name' => 'Frozen Dealer']);
        $source = $this->fixtures->submission($dealer, ['customer_name' => 'Frozen Customer']);
        $batch = $this->freeze();
        $item = $batch->items()->first();
        $this->fixtures->change($source, ['customer_name' => 'CHANGED_LIVE_NAME', 'phone' => '0912345678', 'phone_normalized' => '0912345678']);
        $this->fixtures->change($dealer, ['name' => 'CHANGED_LIVE_DEALER']);
        $response = $this->read('/'.$batch->id.'/items/'.$item->id);
        $response->assertOk()->assertJsonPath('data.name', 'Frozen Customer')->assertJsonPath('data.dealer.name', 'Frozen Dealer')
            ->assertJsonPath('data.phone_masked', '09****001')->assertJsonPath('data.state', 'needs_call');
        foreach ([$source->phone, '0912345678', 'CHANGED_LIVE_NAME', 'CHANGED_LIVE_DEALER', $source->facebook_url, $source->license_plate] as $private) {
            $response->assertDontSee($private, false);
        }
    }

    public function test_payload_allowlist_never_serializes_private_baseline_seed_manifest_or_snapshots(): void
    {
        $dealer = $this->fixtures->dealer();
        $sales = $this->fixtures->admin();
        $this->fixtures->change($sales, ['role' => 'sales', 'dealer_id' => $dealer->id, 'name' => 'PRIVATE_SALES_NAME']);
        $source = $this->fixtures->submission($dealer, ['source' => 'sales', 'sales_id' => $sales->id, 'customer_name' => 'Visible Customer']);
        $batch = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $item = $batch->items()->first();
        foreach (['', '/'.$batch->id, '/'.$batch->id.'/items', '/'.$batch->id.'/items/'.$item->id] as $path) {
            $response = $this->read($path, ['purpose' => 'award_top3'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach (['PRIVATE_SALES_NAME', $source->phone, $source->facebook_url, $source->license_plate,
                'seed_encrypted', 'manifest_encrypted', 'source_snapshot', 'fingerprint', 'digest_key',
                'encryption_key', 'create_request_key', 'address_line', 'notes', 'sales_id'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
    }

    public function test_empty_index_and_zero_or_underfilled_batches_are_not_complete(): void
    {
        $this->read('')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $empty = $this->freeze();
        $this->read('/'.$empty->id)->assertOk()->assertJsonPath('data.requested_count', 60)
            ->assertJsonPath('data.actual_count', 0)->assertJsonPath('data.progress.is_complete', false);
        $this->read('/'.$empty->id.'/items')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('tabs.0.count', 0);
        $this->fixtures->submission($this->fixtures->dealer());
        $small = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $this->read('/'.$small->id, ['purpose' => 'award_top3'])->assertOk()->assertJsonPath('data.requested_count', 5)
            ->assertJsonPath('data.actual_count', 1)->assertJsonPath('data.progress.called_count', 0)
            ->assertJsonPath('data.dealers.0.actual_count', 1)->assertJsonPath('data.dealers.0.wrong_ratio', null);
    }

    public function test_award_dealer_with_zero_eligible_items_still_has_real_stratum_count(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $bad = $this->fixtures->submission($this->fixtures->dealer());
        $this->fixtures->change($bad, ['phone' => 'invalid', 'phone_normalized' => 'invalid']);
        $batch = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $response = $this->read('/'.$batch->id, ['purpose' => 'award_top3'])->assertOk()->assertJsonCount(2, 'data.dealers');
        $this->assertSame([1, 0], array_column($response->json('data.dealers'), 'actual_count'));
        $this->assertSame([5, 5], array_column($response->json('data.dealers'), 'requested_count'));
    }

    public function test_tabs_ignore_fake_queue_projection_and_only_attempt_rows_count_as_called(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $batch = $this->freeze();
        $item = $batch->items()->first();
        DB::table('call_audit_items')->where('id', $item->id)->update(['queue_state' => 'completed', 'attempt_count' => 99, 'latest_outcome' => 'wrong']);
        $independent = $this->freeze('award_top3', '2026-10', $this->fixtures->award()->id);
        $this->read('/'.$batch->id.'/items')->assertOk()->assertJsonPath('data.0.state', 'needs_call')
            ->assertJsonPath('batch.progress.called_count', 0)->assertJsonPath('tabs.0.count', 1)
            ->assertJsonPath('tabs.1.count', 0)->assertJsonPath('tabs.2.count', 0);
        $this->read('/'.$batch->id.'/items', ['tab' => 'recall'])->assertOk()->assertJsonCount(0, 'data');
        $this->read('/'.$batch->id.'/items', ['tab' => 'completed'])->assertOk()->assertJsonCount(0, 'data');
        (new CallAuditAttempt)->forceFill([
            'item_id' => $item->id, 'attempt_no' => 1, 'actor_id' => $this->admin->id,
            'started_at' => '2026-11-10 08:00:00', 'recorded_at' => '2026-11-10 08:01:00',
            'questionnaire_version' => 'fixture-only', 'outcome' => 'unclassified_fixture',
            'source_fingerprint_used' => $item->source_fingerprint, 'item_version_used' => 1,
            'idempotency_key' => (string) Str::uuid(), 'payload_digest' => str_repeat('a', 64),
        ])->save();
        $this->read('/'.$batch->id.'/items')->assertOk()->assertJsonPath('batch.progress.called_count', 1)
            ->assertJsonPath('tabs.0.count', 0)->assertJsonPath('tabs.1.count', null)->assertJsonPath('tabs.2.count', null)
            ->assertJsonPath('data.0.state', null)->assertJsonPath('batch.progress.is_complete', false);
        $this->read('/'.$batch->id.'/items', ['tab' => 'needs_call'])->assertOk()->assertJsonCount(0, 'data');
        $this->read('/'.$batch->id.'/items', ['tab' => 'completed'])->assertStatus(409);
        $this->read('/'.$independent->id.'/items', ['purpose' => 'award_top3'])->assertOk()
            ->assertJsonPath('batch.progress.called_count', 0)->assertJsonPath('tabs.0.count', 1)
            ->assertJsonPath('tabs.1.count', 0)->assertJsonPath('tabs.2.count', 0);
    }

    public function test_query_validation_does_not_echo_private_values_or_generate_pagination_links(): void
    {
        Log::spy();
        $private = 'PRIVATE-PHONE-0901234567';
        $response = $this->read('', ['phone' => $private]);
        $response->assertUnprocessable()->assertDontSee($private, false);
        $this->read('', ['purpose' => 'manager'])->assertUnprocessable();
        $this->read('', ['month' => '2026-13'])->assertUnprocessable();
        $this->read('', ['per_page' => 1000])->assertUnprocessable();
        Log::shouldNotHaveReceived('error');
    }

    public function test_get_does_not_generate_claim_sample_or_change_any_database_rows(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $batch = $this->freeze();
        $item = $batch->items()->first();
        $this->app->bind(CreateAndFreezeBatch::class, fn () => throw new \RuntimeException('GET must not resolve freeze action'));
        $this->app->bind(SamplingPlanner::class, fn () => throw new \RuntimeException('GET must not resolve sampler'));
        $before = $this->databaseState();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['', '/'.$batch->id, '/'.$batch->id.'/items', '/'.$batch->id.'/items/'.$item->id] as $path) {
            $this->read($path)->assertOk();
        }
        $queries = implode(' ', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['from `customer_submissions`', 'from `dealers`', 'from `awards`', 'seed_encrypted', 'manifest_encrypted'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $queries);
        }
        $this->assertSame($before, $this->databaseState());
        $this->assertNull($item->fresh()->claim_owner_id);
    }

    public function test_unreadable_snapshot_fails_closed_without_disclosing_or_logging_private_data(): void
    {
        $this->fixtures->submission($this->fixtures->dealer());
        $batch = $this->freeze();
        DB::table('call_audit_items')->where('batch_id', $batch->id)->update(['source_snapshot_encrypted' => 'PRIVATE_TAMPERED_PAYLOAD']);
        Log::spy();
        $this->read('/'.$batch->id.'/items')->assertStatus(409)->assertExactJson(['error' => 'audit_read_unavailable'])
            ->assertDontSee('PRIVATE_TAMPERED_PAYLOAD', false);
        Log::shouldNotHaveReceived('error');
    }

    public function test_snapshot_from_another_item_cannot_be_disclosed_as_this_customer(): void
    {
        $dealer = $this->fixtures->dealer();
        $this->fixtures->submission($dealer);
        $this->fixtures->submission($dealer);
        $batch = $this->freeze();
        $items = $batch->items()->orderBy('selection_order')->get();
        DB::table('call_audit_items')->where('id', $items[1]->id)
            ->update(['source_snapshot_encrypted' => $items[0]->getRawOriginal('source_snapshot_encrypted')]);
        $this->read('/'.$batch->id.'/items/'.$items[1]->id)->assertStatus(409)->assertExactJson(['error' => 'audit_read_unavailable']);
    }

    public function test_only_get_routes_are_registered_for_read_api(): void
    {
        $count = 0;
        foreach (app('router')->getRoutes() as $route) {
            if (in_array($route->getName(), ['admin.call-audits.index', 'admin.call-audits.show', 'admin.call-audits.items', 'admin.call-audits.item'], true)) {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
                $count++;
            }
        }
        $this->assertSame(4, $count);
    }

    private function freeze(string $purpose = 'general_audit', string $month = '2026-10', ?int $awardId = null): CallAuditBatch
    {
        $result = app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse($month.'-15'),
            $this->fixtures->tag.'-'.$purpose.'-'.$month, $awardId);
        $this->assertSame('FROZEN', $result->summary['status'], json_encode($result));

        return CallAuditBatch::findOrFail($result->summary['batch_id']);
    }

    private function read(string $path, array $filters = [])
    {
        $this->reading = true;
        try {
            return $this->getJson('/admin/call-audits'.$path.'?'.http_build_query(array_replace(['purpose' => 'general_audit'], $filters)));
        } finally {
            $this->reading = false;
        }
    }

    private function databaseState(): array
    {
        $snapshot = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
            $table = array_values((array) $row)[0];
            $snapshot[$table] = [DB::table($table)->count(), ((array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED'))['Checksum']];
        }

        return $snapshot;
    }
}
