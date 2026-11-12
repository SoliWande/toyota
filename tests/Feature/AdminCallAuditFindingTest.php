<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\FindingReadModel;
use App\Services\CallAudit\FreezeClock;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditFindingTest extends TestCase
{
    private ?CallAuditFreezeFixtures $fixtures = null;

    private User $admin;

    private CallAuditBatch $batch;

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
        $receipt = Mockery::mock(ResetDemoRetirement::class);
        $receipt->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $receipt);
        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $clock = Mockery::mock(FreezeClock::class);
        $clock->shouldReceive('now')->andReturnUsing(fn () => $this->now);
        $this->app->instance(FreezeClock::class, $clock);
        $dealer = $this->fixtures->dealer();
        $this->fixtures->change($dealer, ['name' => 'Frozen Dealer']);
        for ($i = 0; $i < 3; $i++) {
            $this->fixtures->submission($dealer, ['customer_name' => 'Frozen Customer '.$i]);
        }
        $this->batch = $this->freeze();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->reading && (! preg_match('/^\s*(select|show|SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)
                || preg_match('/for update|for share|lock in share mode/i', $sql))) {
                throw new \RuntimeException('Finding GET must be non-mutating and must not claim.');
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

    public function test_real_wrong_provides_one_stable_item_finding_with_frozen_dealer_and_actor(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $attempt = $item->attempts()->sole();
        $event = $item->events()->where('event_type', 'call_result_recorded')->sole();
        $response = $this->read()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $finding = $response->json('data.0');
        $this->assertSame($item->id, $finding['id']);
        $this->assertSame($item->id, $finding['item_id']);
        $this->assertSame($this->batch->id, $finding['batch_id']);
        $this->assertSame($attempt->id, $finding['attempt_id']);
        $this->assertSame($event->id, $finding['event_id']);
        $this->assertSame($this->admin->id, $finding['recorded_by']['id']);
        $this->assertSame('finding_pending', $finding['status']);
        $this->assertSame('Frozen Dealer', $finding['dealer']['name']);
        $this->assertMatchesRegularExpression('/^0[1-9]\*{4}\d{3}$/D', $finding['customer']['phone_masked']);
        $this->read('/'.$item->id)->assertOk()->assertJsonPath('data', $finding);
        $this->read()->assertExactJson($response->json());
        $this->assertSame(1, $item->attempts()->count());
        $this->assertSame(3, $this->batch->events()->count());
    }

    public function test_no_answer_then_wrong_is_one_finding_for_latest_attempt_only(): void
    {
        $items = $this->batch->items()->orderBy('selection_order')->get();
        $this->record($items[0], 'no_answer');
        $this->record($items[1], 'correct');
        $this->record($items[2], 'no_answer');
        $this->read()->assertOk()->assertJsonCount(0, 'data');
        $this->now = $this->now->addDay();
        $this->record($items[0], 'wrong');
        $this->record($items[2], 'no_answer');
        $response = $this->read()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->assertSame($items[0]->attempts()->where('attempt_no', 2)->sole()->id, $response->json('data.0.attempt_id'));
        $this->read('/'.$items[1]->id)->assertNotFound();
        $this->read('/'.$items[2]->id)->assertNotFound();
        $this->assertSame(5, DB::table('call_audit_attempts')->count());
    }

    #[DataProvider('deniedAccounts')]
    public function test_list_and_detail_reject_unauthorized_accounts(?string $role, ?string $status): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
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
        $this->read()->assertStatus($role === null ? 401 : 403);
        $this->read('/'.$item->id)->assertStatus($role === null ? 401 : 403);
    }

    public static function deniedAccounts(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'],
            ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_any_active_admin_can_review_other_creator_and_persisted_block_is_rechecked(): void
    {
        $this->record($this->batch->items()->first(), 'wrong');
        $reader = $this->fixtures->admin();
        $this->actingAs($reader);
        $this->read()->assertOk()->assertJsonPath('data.0.recorded_by.id', $this->admin->id);
        $this->fixtures->change($reader, ['status' => 'blocked']);
        $this->read()->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(FindingReadModel::class)->index($reader, $this->batch->id, ['purpose' => 'general_audit']);
    }

    public function test_purpose_month_and_child_idor_never_mix_c07_and_c08(): void
    {
        $general = $this->batch;
        $generalItem = $general->items()->first();
        $this->record($generalItem, 'wrong');
        $award = $this->freeze('award_top3');
        $this->read('', ['purpose' => 'award_top3'])->assertNotFound();
        $this->read('', ['month' => '2026-09'])->assertNotFound();
        $this->batch = $award;
        $this->read('', ['purpose' => 'award_top3'])->assertOk()->assertJsonCount(0, 'data');
        $this->read('/'.$generalItem->id, ['purpose' => 'award_top3'])->assertNotFound();
        $awardItem = $award->items()->where('submission_id', $generalItem->submission_id)->sole();
        $this->record($awardItem, 'wrong');
        $result = $this->read('', ['purpose' => 'award_top3'])->assertOk()->json('data.0');
        $this->assertSame($award->id, $result['batch_id']);
        $this->assertSame($awardItem->id, $result['id']);
        $this->assertNotSame($generalItem->id, $result['id']);
        $this->assertNotSame($generalItem->attempts()->sole()->id, $result['attempt_id']);
        $this->batch = $general;
        $this->read()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $generalItem->id);
        $this->read('/'.$awardItem->id)->assertNotFound();
        $this->read('/999999999')->assertNotFound();
    }

    public function test_frozen_history_survives_live_customer_dealer_and_recorded_actor_changes(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $reader = $this->fixtures->admin();
        $this->actingAs($reader);
        $before = $this->read()->assertOk()->json();
        $this->fixtures->change($item->submission, ['customer_name' => 'changed', 'phone' => '0999999999', 'phone_normalized' => '0999999999']);
        $this->fixtures->change($item->dealer, ['name' => 'changed dealer']);
        $this->fixtures->change($this->admin, ['status' => 'blocked', 'name' => 'changed caller']);
        $this->read()->assertExactJson($before)->assertDontSee('0999999999')->assertDontSee('changed caller');
    }

    public function test_pagination_is_stable_and_zero_batches_or_no_wrong_have_no_fake_findings(): void
    {
        $this->read()->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $empty = $this->freeze('general_audit', '2026-09');
        $original = $this->batch;
        $this->batch = $empty;
        $this->read()->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->batch = $original;
        $items = $original->items()->orderBy('selection_order')->get();
        foreach ($items as $item) {
            $this->record($item, 'wrong');
        }
        $first = $this->read('', ['per_page' => 2])->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
        $this->assertSame($items->take(2)->pluck('id')->all(), array_column($first->json('data'), 'id'));
        $this->read('', ['per_page' => 2, 'page' => 2])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $items[2]->id);
        $this->read('', ['per_page' => 2, 'page' => 99])->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 3);
        $this->assertArrayNotHasKey('links', $first->json());
    }

    #[DataProvider('corruptions')]
    public function test_fake_or_stale_evidence_fails_closed_without_repair(string $target, array $values): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        if ($target === 'event') {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->update($values);
        } elseif ($target === 'attempt') {
            DB::table('call_audit_attempts')->where('item_id', $item->id)->update($values);
        } elseif ($target === 'item') {
            DB::table('call_audit_items')->where('id', $item->id)->update($values);
        } elseif ($target === 'batch') {
            DB::table('call_audit_batches')->where('id', $this->batch->id)->update($values);
        } elseif ($target === 'previous') {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'claim_item')->update($values);
        } else {
            DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->delete();
        }
        $before = $this->databaseState();
        Log::spy();
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'audit_findings_unavailable']);
        $this->read('/'.$item->id)->assertStatus(409)->assertExactJson(['error' => 'audit_findings_unavailable']);
        $this->assertSame($before, $this->databaseState());
        Log::shouldNotHaveReceived('error');
    }

    public static function corruptions(): array
    {
        return [
            ['item', ['queue_state' => 'completed']], ['item', ['attempt_count' => 99]],
            ['item', ['row_version' => 4]], ['item', ['source_snapshot_encrypted' => 'bad-private-snapshot']],
            ['item', ['source_fingerprint' => str_repeat('f', 64)]],
            ['event', ['event_digest' => str_repeat('f', 64)]],
            ['event', ['private_payload_encrypted' => 'bad-private-receipt']],
            ['event', ['before_version' => 999]], ['event', ['command_key' => 'stale-request-key']],
            ['event', ['digest_key_id' => 'unavailable-key']],
            ['event', ['metadata' => '{"policy":"call_result_v1","finding_status":null}']],
            ['attempt', ['payload_digest' => str_repeat('f', 64)]], ['attempt', ['idempotency_key' => 'stale-request-key']],
            ['attempt', ['answer_1' => true, 'answer_2' => true, 'answer_3' => true]],
            ['batch', ['digest_key_id' => 'unavailable-key']], ['previous', ['event_digest' => str_repeat('f', 64)]], ['missing', []],
        ];
    }

    public function test_fake_projection_and_duplicate_wrong_history_do_not_create_findings(): void
    {
        $item = $this->batch->items()->first();
        DB::table('call_audit_items')->where('id', $item->id)->update(['queue_state' => 'finding_pending', 'latest_outcome' => 'wrong']);
        $this->read()->assertStatus(409);
        DB::table('call_audit_items')->where('id', $item->id)->update(['queue_state' => 'uncalled', 'latest_outcome' => null]);
        $this->record($item, 'wrong');
        $row = (array) DB::table('call_audit_attempts')->where('item_id', $item->id)->sole();
        unset($row['id']);
        $row['attempt_no'] = 2;
        $row['idempotency_key'] = 'duplicate-wrong';
        DB::table('call_audit_attempts')->insert($row);
        DB::table('call_audit_items')->where('id', $item->id)->update(['attempt_count' => 2]);
        $this->read()->assertStatus(409);
        $this->assertSame(2, $item->attempts()->count());
    }

    public function test_stale_signed_correct_receipt_cannot_be_relabelled_wrong(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'correct');
        $event = $item->events()->where('event_type', 'call_result_recorded')->sole();
        $receipt = $event->private_payload_encrypted;
        $receipt['response']['outcome'] = 'wrong';
        $receipt['response']['queue_state'] = 'finding_pending';
        $receipt['response']['finding_status'] = 'pending';
        $ciphertext = (new CallAuditEvent)->forceFill(['private_payload_encrypted' => $receipt])->getAttributes()['private_payload_encrypted'];
        DB::table('call_audit_items')->where('id', $item->id)->update(['queue_state' => 'finding_pending', 'latest_outcome' => 'wrong']);
        DB::table('call_audit_attempts')->where('item_id', $item->id)->update(['outcome' => 'wrong', 'answer_1' => false]);
        DB::table('call_audit_events')->where('id', $event->id)->update(['private_payload_encrypted' => $ciphertext,
            'metadata' => '{"policy":"call_result_v1","finding_status":"pending"}']);
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'audit_findings_unavailable']);
    }

    public function test_duplicate_result_event_is_not_another_finding(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $row = (array) DB::table('call_audit_events')->where('item_id', $item->id)->where('event_type', 'call_result_recorded')->sole();
        unset($row['id']);
        $row['event_sequence'] = 4;
        $row['event_ordinal'] = 1;
        DB::table('call_audit_events')->insert($row);
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'audit_findings_unavailable']);
        $this->assertSame(1, $item->attempts()->count());
        $this->assertSame(4, $this->batch->events()->count());
    }

    public function test_readable_but_altered_frozen_snapshot_is_not_trusted(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $snapshot = $item->source_snapshot_encrypted;
        $snapshot['customer_name'] = 'PRIVATE-TAMPERED-SNAPSHOT';
        $ciphertext = (new CallAuditItem)->forceFill(['source_snapshot_encrypted' => $snapshot])->getAttributes()['source_snapshot_encrypted'];
        DB::table('call_audit_items')->where('id', $item->id)->update(['source_snapshot_encrypted' => $ciphertext]);
        $this->read()->assertStatus(409)->assertExactJson(['error' => 'audit_findings_unavailable'])->assertDontSee('PRIVATE-TAMPERED-SNAPSHOT');
    }

    public function test_invalid_off_page_finding_is_not_counted_as_valid(): void
    {
        $items = $this->batch->items()->orderBy('selection_order')->get();
        foreach ($items as $item) {
            $this->record($item, 'wrong');
        }
        DB::table('call_audit_events')->where('item_id', $items[2]->id)->where('event_type', 'call_result_recorded')
            ->update(['event_digest' => str_repeat('f', 64)]);
        $this->read('', ['per_page' => 1, 'page' => 1])->assertStatus(409);
    }

    public function test_get_is_read_only_private_and_never_exposes_or_fetches_unneeded_pii(): void
    {
        $item = $this->batch->items()->first();
        $this->record($item, 'wrong');
        $source = $item->submission;
        $before = $this->databaseState();
        foreach ([ClaimItem::class, RecordCallResult::class, CreateAndFreezeBatch::class] as $writer) {
            $this->app->bind($writer, fn () => throw new \RuntimeException('GET must never resolve a writer.'));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['', '/'.$item->id] as $path) {
            $response = $this->read($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach ([$source->phone, $source->license_plate, $source->facebook_url, $this->admin->email,
                $this->admin->name, 'private finding note', 'seed', 'manifest', 'snapshot', 'payload_digest', 'claim_token',
                'fingerprint', 'private_payload', 'encryption_key', 'answers', 'rate', 'penalty'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $queries = implode(' ', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['note_encrypted', 'seed_encrypted', 'manifest_encrypted', 'from `customer_submissions`', 'from `dealers`', 'from `awards`'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $queries);
        }
        $this->assertSame($before, $this->databaseState());
        $this->assertSame(2, count(array_filter(iterator_to_array(app('router')->getRoutes()),
            fn ($route) => str_starts_with($route->getName() ?? '', 'admin.call-audits.findings.'))));
        foreach (['index', 'show'] as $name) {
            $route = app('router')->getRoutes()->getByName('admin.call-audits.findings.'.$name);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
        $this->postJson($this->url(), [])->assertStatus(405);
        $this->patchJson($this->url('/'.$item->id), [])->assertStatus(405);
    }

    public function test_filters_are_allowlisted_without_private_echo_or_links(): void
    {
        foreach ([['phone' => 'PRIVATE-PHONE'], ['tab' => 'completed'], ['purpose' => 'manager'],
            ['month' => '2026-13'], ['per_page' => 51], ['page' => 0], ['page' => 100001]] as $filters) {
            $this->read('', $filters)->assertStatus(422)->assertExactJson(['error' => 'invalid_finding_filters'])->assertDontSee('PRIVATE-PHONE');
        }
        $this->read('/'.$this->batch->items()->first()->id, ['page' => 1])->assertStatus(422);
    }

    private function record(CallAuditItem $item, string $outcome): void
    {
        $item->refresh();
        $purpose = $item->batch->purpose;
        $key = 'finding-'.$item->id.'-'.$item->row_version;
        $claim = app(ClaimItem::class)->execute($item->batch_id, $item->id,
            ['purpose' => $purpose, 'row_version' => $item->row_version, 'idempotency_key' => $key]);
        app(RecordCallResult::class)->execute($item->batch_id, $item->id, [
            'purpose' => $purpose, 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => $key, 'outcome' => $outcome, 'note' => 'private finding note',
            'answer_1' => $outcome === 'no_answer' ? null : $outcome === 'correct',
            'answer_2' => $outcome === 'no_answer' ? null : true, 'answer_3' => $outcome === 'no_answer' ? null : true,
        ]);
    }

    private function freeze(string $purpose = 'general_audit', string $month = '2026-10'): CallAuditBatch
    {
        $result = app(CreateAndFreezeBatch::class)->execute($purpose, CarbonImmutable::parse($month.'-15'),
            $this->fixtures->tag.'-'.$purpose.'-'.$month, $purpose === 'award_top3' ? $this->fixtures->award()->id : null);
        $this->assertSame('FROZEN', $result->summary['status']);

        return CallAuditBatch::findOrFail($result->summary['batch_id']);
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/findings'.$suffix;
    }

    private function read(string $suffix = '', array $filters = [])
    {
        $this->reading = true;
        try {
            return $this->getJson($this->url($suffix).'?'.http_build_query(array_replace(['purpose' => 'general_audit'], $filters)));
        } finally {
            $this->reading = false;
        }
    }

    private function databaseState(): array
    {
        $snapshot = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
            $table = array_values((array) $row)[0];
            $snapshot[$table] = [DB::table($table)->count(), (array) DB::selectOne('CHECKSUM TABLE `'.$table.'` EXTENDED')];
        }

        return $snapshot;
    }
}
