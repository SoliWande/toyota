<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Services\CallAudit\FreezeClock;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditReadIntegrationTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private CarbonImmutable $now;

    private CallAuditBatch $batch;

    private bool $reading = false;

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
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        $dealer = $this->fixtures->dealer();
        for ($i = 0; $i < 6; $i++) {
            $this->fixtures->submission($dealer);
        }
        $this->batch = $this->freeze();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && (! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)
                || preg_match('/for update|lock in share mode|for share/i', $sql))) {
                throw new \RuntimeException('GET must read without mutations or locking claims.');
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

    public function test_counts_tabs_outcomes_pagination_and_dealer_denominator_use_distinct_customers(): void
    {
        $items = $this->batch->items()->orderBy('selection_order')->get();
        $this->record($items[1], 'correct');
        $this->record($items[2], 'wrong');
        $this->record($items[3], 'no_answer');
        $this->record($items[4], 'no_answer');
        $this->record($items[5], 'no_answer');
        $this->now = $this->now->addDay();
        $this->record($items[4], 'no_answer');
        $this->record($items[5], 'correct');
        $detail = $this->read()->assertOk()->json('data');
        $this->assertSame(60, $detail['requested_count']);
        $this->assertSame(6, $detail['actual_count']);
        $this->assertSame(['called_count' => 5, 'needs_call_count' => 1, 'recall_count' => 1,
            'completed_count' => 3, 'finding_pending_count' => 1, 'is_complete' => false], $detail['progress']);
        $this->assertSame($detail['progress'], $detail['dealers'][0]['progress']);
        $this->assertSame(3, $detail['dealers'][0]['wrong_ratio_denominator']);
        $this->assertEquals(33.33, $detail['dealers'][0]['wrong_ratio']);
        $this->assertSame(7, $this->batch->items()->withCount('attempts')->get()->sum('attempts_count'));
        $all = $this->read('/items')->assertOk();
        $this->assertSame([1, 1, 4], array_column($all->json('tabs'), 'count'));
        $this->assertSame(['Cần gọi', 'Gọi lại', 'Đã xong'], array_column($all->json('tabs'), 'label'));
        $this->read('/items', ['tab' => 'needs_call'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $items[0]->id);
        $this->read('/items', ['tab' => 'recall'])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $items[3]->id)->assertJsonPath('data.0.is_due', true);
        $done = $this->read('/items', ['tab' => 'completed', 'per_page' => 2, 'page' => 1])->assertOk();
        $this->assertSame([$items[1]->id, $items[2]->id], array_column($done->json('data'), 'id'));
        $this->assertSame(['completed', 'finding_pending'], array_column($done->json('data'), 'state'));
        $this->assertSame(['correct', 'wrong'], array_column($done->json('data'), 'outcome'));
        $this->assertSame([null, 'pending'], array_column($done->json('data'), 'finding_status'));
        $done->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);
        $last = $this->read('/items', ['tab' => 'completed', 'per_page' => 2, 'page' => 2])->assertOk();
        $this->assertSame([$items[4]->id, $items[5]->id], array_column($last->json('data'), 'id'));
        $this->assertSame(['no_answer', 'correct'], array_column($last->json('data'), 'outcome'));
        $this->assertSame([2, 2], array_column($last->json('data'), 'attempt_count'));
        $this->assertSame([null, null], array_column($last->json('data'), 'is_due'));
        $this->assertArrayNotHasKey('links', $last->json());
        $this->read('/items', ['tab' => 'completed', 'page' => 9])->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 4);
    }

    public function test_retry_before_at_and_after_due_remains_recall_without_mutation(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'no_answer');
        $due = $this->now->addSeconds(86400);
        $before = $this->databaseState();
        foreach ([-1, 0, 1] as $offset) {
            $this->now = $due->addMicroseconds($offset);
            $this->read('/items', ['tab' => 'recall'])->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.retry_due_at', $due->toIso8601String())->assertJsonPath('data.0.is_due', $offset >= 0)
                ->assertJsonPath('data.0.state', 'recall')->assertJsonPath('batch.progress.recall_count', 1);
        }
        $this->assertSame($before, $this->databaseState());
        $this->assertSame(1, $item->attempts()->count());
        $this->assertSame('recall', $item->fresh()->queue_state);
    }

    public function test_zero_denominator_no_answer_and_pending_are_not_verified_correct(): void
    {
        $this->read()->assertOk()->assertJsonPath('data.dealers.0.wrong_ratio', null)->assertJsonPath('data.dealers.0.wrong_ratio_denominator', 0);
        $items = $this->batch->items()->get();
        foreach ($items as $item) {
            $this->record($item, 'no_answer');
        }
        $this->read()->assertOk()->assertJsonPath('data.progress.called_count', 6)
            ->assertJsonPath('data.progress.recall_count', 6)->assertJsonPath('data.dealers.0.wrong_ratio', null);
        $this->now = $this->now->addDay();
        foreach ($items as $item) {
            $this->record($item, 'no_answer');
        }
        $this->read()->assertOk()->assertJsonPath('data.progress.completed_count', 6)
            ->assertJsonPath('data.progress.finding_pending_count', 0)->assertJsonPath('data.progress.called_count', 6)
            ->assertJsonPath('data.progress.is_complete', true)->assertJsonPath('data.dealers.0.wrong_ratio', null);
        $done = $this->read('/items', ['tab' => 'completed'])->assertOk()->json('data');
        $this->assertSame(['no_answer'], array_values(array_unique(array_column($done, 'outcome'))));
    }

    public function test_finding_pending_is_done_for_call_tab_but_not_complete_discrepancy_processing(): void
    {
        foreach ($this->batch->items()->get() as $item) {
            $this->record($item, 'wrong');
        }
        $this->read()->assertOk()->assertJsonPath('data.progress.completed_count', 0)
            ->assertJsonPath('data.progress.finding_pending_count', 6)->assertJsonPath('data.progress.is_complete', false)
            ->assertJsonPath('data.dealers.0.wrong_ratio', 100);
        $this->read('/items', ['tab' => 'completed'])->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('tabs.2.count', 6);
    }

    public function test_each_dealer_uses_its_own_selected_called_finding_and_answered_counts(): void
    {
        $dealers = [$this->fixtures->dealer(), $this->fixtures->dealer()];
        foreach ($dealers as $dealer) {
            for ($i = 0; $i < 2; $i++) {
                $this->fixtures->submission($dealer, ['submitted_at' => '2026-09-02']);
            }
        }
        $this->batch = $this->freeze('general_audit', '2026-09');
        $groups = $this->batch->items()->orderBy('selection_order')->get()->groupBy('dealer_id_snapshot');
        $this->record($groups[$dealers[0]->id][0], 'correct');
        $this->record($groups[$dealers[0]->id][1], 'wrong');
        $this->record($groups[$dealers[1]->id][0], 'no_answer');
        $result = $this->read()->assertOk()->json('data');
        $byId = array_column($result['dealers'], null, 'id');
        $this->assertSame(4, $result['actual_count']);
        $this->assertSame(3, $result['progress']['called_count']);
        $this->assertSame(2, $byId[$dealers[0]->id]['actual_count']);
        $this->assertSame(2, $byId[$dealers[0]->id]['progress']['called_count']);
        $this->assertSame(1, $byId[$dealers[0]->id]['progress']['finding_pending_count']);
        $this->assertSame(2, $byId[$dealers[0]->id]['wrong_ratio_denominator']);
        $this->assertEquals(50, $byId[$dealers[0]->id]['wrong_ratio']);
        $this->assertSame(2, $byId[$dealers[1]->id]['actual_count']);
        $this->assertSame(1, $byId[$dealers[1]->id]['progress']['called_count']);
        $this->assertSame(1, $byId[$dealers[1]->id]['progress']['recall_count']);
        $this->assertSame(0, $byId[$dealers[1]->id]['progress']['finding_pending_count']);
        $this->assertSame(0, $byId[$dealers[1]->id]['wrong_ratio_denominator']);
        $this->assertNull($byId[$dealers[1]->id]['wrong_ratio']);
    }

    public function test_real_results_still_require_fresh_active_admin(): void
    {
        $this->record($this->batch->items()->first(), 'wrong');
        $this->read()->assertOk();
        $this->fixtures->change(auth()->user(), ['status' => 'blocked']);
        $this->read()->assertForbidden();
        $this->read('/items')->assertForbidden();
    }

    public function test_frozen_batch_and_other_purpose_dealers_are_not_affected_by_live_source_changes(): void
    {
        $other = $this->freeze('award_top3');
        $item = $this->batch->items()->first();
        $source = $item->submission;
        $this->record($item, 'wrong');
        $before = $this->read()->assertOk()->json();
        $this->fixtures->change($source, ['customer_name' => 'changed customer', 'phone' => '0999999999', 'phone_normalized' => '0999999999']);
        $this->fixtures->change($source->dealer, ['name' => 'changed dealer']);
        $this->read()->assertExactJson($before);
        $this->read('/items/'.$item->id)->assertOk()->assertJsonPath('data.phone_masked', '09****001')
            ->assertJsonPath('data.outcome', 'wrong')->assertDontSee('changed customer')->assertDontSee('0999999999');
        $this->getJson('/admin/call-audits/'.$other->id.'?purpose=award_top3')->assertOk()
            ->assertJsonPath('data.progress.called_count', 0)->assertJsonPath('data.progress.needs_call_count', 5);
        $this->getJson('/admin/call-audits/'.$other->id.'/items/'.$item->id.'?purpose=award_top3')->assertNotFound();
    }

    #[DataProvider('mismatches')]
    public function test_inconsistent_history_or_projection_fails_on_every_read_without_repair(string $target, array $values): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'no_answer');
        if ($target === 'item') {
            DB::table('call_audit_items')->where('id', $item->id)->update($values);
        } elseif ($target === 'attempt') {
            DB::table('call_audit_attempts')->where('item_id', $item->id)->update($values);
        } elseif ($target === 'event') {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->update($values);
        } else {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->delete();
        }
        $before = $this->databaseState();
        Log::spy();
        foreach (['', '/'.$this->batch->id, '/'.$this->batch->id.'/items?tab=completed',
            '/'.$this->batch->id.'/items/'.$item->id] as $path) {
            $url = '/admin/call-audits'.$path.(str_contains($path, '?') ? '&' : '?').'purpose=general_audit';
            $this->reading = true;
            try {
                $this->getJson($url)->assertStatus(409)->assertExactJson(['error' => 'audit_read_unavailable']);
            } finally {
                $this->reading = false;
            }
        }
        $this->assertSame($before, $this->databaseState());
        Log::shouldNotHaveReceived('error');
    }

    public static function mismatches(): array
    {
        return [
            ['item', ['attempt_count' => 0]], ['item', ['queue_state' => 'completed']],
            ['item', ['latest_outcome' => 'correct']], ['item', ['retry_due_at' => null]],
            ['attempt', ['questionnaire_version' => 'unknown']], ['attempt', ['outcome' => 'correct']],
            ['attempt', ['retry_due_at' => '2026-11-11 12:01:00']],
            ['attempt', ['answer_1' => true]], ['attempt', ['source_fingerprint_used' => str_repeat('f', 64)]],
            ['event', ['metadata' => json_encode(['policy' => 'call_result_v1', 'finding_status' => 'pending'])]],
            ['event', ['metadata' => json_encode('private-invalid-metadata')]],
            ['event', ['before_version' => 999]], ['event', ['command_name' => 'unknown']], ['missing', []],
        ];
    }

    public function test_get_of_real_results_never_resolves_writers_or_fetches_sensitive_columns(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $before = $this->databaseState();
        foreach ([ClaimItem::class, RecordCallResult::class, CreateAndFreezeBatch::class] as $writer) {
            $this->app->bind($writer, fn () => throw new \RuntimeException('GET must not resolve a writer.'));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['', '/items', '/items/'.$item->id] as $path) {
            $response = $this->read($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach (['private note', '0900000001', 'seed', 'manifest', 'fingerprint', 'claim_token', 'answer_1', 'address_line'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $queries = implode(' ', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['note_encrypted', 'private_payload_encrypted', 'seed_encrypted', 'manifest_encrypted',
            'from `customer_submissions`', 'from `dealers`', 'from `awards`'] as $private) {
            $this->assertStringNotContainsString($private, $queries);
        }
        $this->assertSame($before, $this->databaseState());
    }

    private function freeze(string $purpose = 'general_audit', string $month = '2026-10'): CallAuditBatch
    {
        $awardId = $purpose === 'award_top3' ? $this->fixtures->award()->id : null;
        $result = app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse($month.'-15'),
            $this->fixtures->tag.'-'.$purpose.'-'.$month, $awardId);
        $this->assertSame('FROZEN', $result->summary['status']);

        return CallAuditBatch::findOrFail($result->summary['batch_id']);
    }

    private function record(CallAuditItem $item, string $outcome): void
    {
        $item->refresh();
        $key = 'call-'.$item->id.'-'.$item->row_version;
        $claim = app(ClaimItem::class)->execute($this->batch->id, $item->id,
            ['purpose' => 'general_audit', 'row_version' => $item->row_version, 'idempotency_key' => $key]);
        app(RecordCallResult::class)->execute($this->batch->id, $item->id, [
            'purpose' => 'general_audit', 'row_version' => $claim['row_version'], 'idempotency_key' => $key,
            'claim_token' => $claim['claim_token'], 'outcome' => $outcome, 'note' => 'private note',
            'answer_1' => $outcome === 'no_answer' ? null : $outcome === 'correct',
            'answer_2' => $outcome === 'no_answer' ? null : true, 'answer_3' => $outcome === 'no_answer' ? null : true,
        ]);
    }

    private function read(string $suffix = '', array $filters = [])
    {
        $this->reading = true;
        try {
            return $this->getJson('/admin/call-audits/'.$this->batch->id.$suffix.'?'.http_build_query(['purpose' => 'general_audit'] + $filters));
        } finally {
            $this->reading = false;
        }
    }

    private function databaseState(): array
    {
        $state = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
            $table = array_values((array) $row)[0];
            $state[$table] = [DB::table($table)->count(), (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED')];
        }

        return $state;
    }
}
