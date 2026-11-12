<?php

namespace Tests\Feature;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\CallAudit\RecordCallResult;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\FreezeClock;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class AdminCallAuditUiTest extends TestCase
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
        $dealers = [];
        for ($i = 1; $i <= 3; $i++) {
            $dealer = $this->fixtures->dealer();
            $this->fixtures->change($dealer, ['name' => 'Toyota Thử nghiệm '.$i]);
            $dealers[] = $dealer;
        }
        for ($i = 1; $i <= 5; $i++) {
            $this->fixtures->submission($dealers[($i - 1) % 3], ['customer_name' => 'Khách xác minh '.$i,
                'vehicle_model' => 'hilux', 'first_registration_year' => 2024]);
        }
        $result = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-10-15'), $this->fixtures->tag.'-general');
        $this->assertSame('FROZEN', $result->summary['status']);
        $this->batch = CallAuditBatch::findOrFail($result->summary['batch_id']);
        $this->item = $this->batch->items()->orderBy('selection_order')->first();
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures?->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function test_page_links_navigation_and_only_renders_designed_controls_without_private_bootstrap(): void
    {
        $response = $this->get('/admin/call-audits/workspace')->assertOk()->assertSee('Kiểm tra ngẫu nhiên')
            ->assertSee('data-call-audit', false)->assertSee('Lưu kết quả')->assertSee('Ghi chú (tuỳ chọn)')
            ->assertSee('KỊCH BẢN GỌI, KHOẢNG 30 GIÂY')->assertDontSee('trừ 1 điểm')->assertDontSee('huỷ xác minh')
            ->assertSee('Thông tin khách hàng chưa khớp. Kết quả sẽ được ghi nhận để kiểm tra và xử lý.');
        $html = $response->getContent();
        foreach (['seed_encrypted', 'manifest_encrypted', 'source_snapshot', 'claim_token', '0900000001', '0901 234 567'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//a[@aria-current="page" and contains(@href,"/call-audits/workspace")]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-tab]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-workspace and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-call-toast and @hidden]')->length);
        $this->assertSame(0, $xpath->query('//dialog')->length);
        $this->assertNull($this->item->fresh()->claim_owner_id);
        $this->assertSame(0, $this->item->attempts()->count());
    }

    #[DataProvider('denied')]
    public function test_page_and_workspace_deny_guest_nonadmin_and_inactive(?string $role, ?string $status): void
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
        $this->getJson('/admin/call-audits/workspace')->assertStatus($role === null ? 401 : 403);
        $this->workspace()->assertStatus($role === null ? 401 : 403);
    }

    public static function denied(): array
    {
        return [[null, null], ['sales', 'active'], ['dealer', 'active'], ['dealer', 'active'],
            ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_preview_does_not_claim_and_only_owner_with_current_token_may_read_contact(): void
    {
        $source = $this->item->submission;
        $this->workspace()->assertOk()->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.name', $source->customer_name)->assertJsonPath('data.vehicle', 'Hilux 2024')
            ->assertJsonPath('data.questions.1', 'Anh đang đi Toyota Hilux, biển số '.$source->license_plate.' phải không ạ?');
        $this->assertNull($this->item->fresh()->claim_owner_id);
        $claim = $this->claim();
        $body = ['row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token']];
        $response = $this->workspace($body)->assertOk()->assertJsonPath('data.phone', $source->phone)->assertJsonPath('data.verified_at', null);
        foreach ([$source->facebook_url, 'seed', 'manifest', 'note_encrypted', 'source_snapshot', 'claim_token', 'fingerprint'] as $private) {
            $response->assertDontSee($private, false);
        }
        $this->assertFalse($this->admin->can('viewSensitiveData', $this->batch));
        $sibling = $this->batch->items()->where('id', '!=', $this->item->id)->first();
        $siblingClaim = app(ClaimItem::class)->execute($this->batch->id, $sibling->id,
            ['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'sibling-claim']);
        $this->postJson('/admin/call-audits/'.$this->batch->id.'/items/'.$sibling->id.'/workspace',
            ['purpose' => 'general_audit', 'row_version' => $siblingClaim['row_version'], 'claim_token' => $claim['claim_token']])
            ->assertStatus(409)->assertDontSee($sibling->submission->phone, false);
        $this->actingAs($this->fixtures->admin());
        $this->workspace($body)->assertStatus(409)->assertJsonPath('error', 'contact_claim_conflict');
        $this->actingAs($this->admin);
        $this->workspace(['row_version' => $claim['row_version'], 'claim_token' => str_repeat('f', 64)])->assertStatus(409);
        $this->workspace(['row_version' => 1, 'claim_token' => $claim['claim_token']])->assertStatus(409);
    }

    public function test_contact_rechecks_source_lease_active_status_and_does_not_write_or_log(): void
    {
        $claim = $this->claim();
        $body = ['row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token']];
        $before = $this->databaseState();
        $this->workspace($body)->assertOk();
        $this->assertSame($before, $this->databaseState());
        $this->now = $this->now->addSeconds(600);
        $this->workspace($body)->assertStatus(409);
        $this->now = $this->now->subSeconds(1);
        $source = $this->item->submission;
        $this->fixtures->change($source, ['customer_name' => 'changed']);
        Log::spy();
        $this->workspace($body)->assertStatus(409)->assertExactJson(['error' => 'source_drift_conflict']);
        $this->assertSame(0, $this->item->attempts()->count());
        $this->fixtures->change($this->admin, ['status' => 'blocked']);
        $this->workspace($body)->assertForbidden();
        $this->getJson('/admin/call-audits/workspace')->assertForbidden();
        Log::shouldNotHaveReceived('error');
    }

    public function test_contact_is_scoped_validated_and_csrf_protected_without_private_query_or_response(): void
    {
        $claim = $this->claim();
        $this->workspace(['purpose' => 'award_top3', 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token']])->assertNotFound();
        $this->postJson('/admin/call-audits/'.$this->batch->id.'/items/999999999/workspace', ['purpose' => 'general_audit', 'row_version' => 1])->assertNotFound();
        $this->workspace(['phone' => 'private-phone'])->assertStatus(422)->assertDontSee('private-phone');
        $this->postJson($this->url().'?phone=private-phone', ['purpose' => 'general_audit', 'row_version' => 1])->assertStatus(422)->assertDontSee('private-phone');
        $this->getJson($this->url())->assertStatus(405);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->workspace()->assertStatus(419);
        $this->withSession(['_token' => 'workspace-csrf'])->withHeader('X-CSRF-TOKEN', 'workspace-csrf')
            ->workspace(['row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token']])->assertOk();
    }

    public function test_recorded_no_answer_is_readonly_until_due_then_new_owner_can_disclose(): void
    {
        $claim = $this->claim();
        app(RecordCallResult::class)->execute($this->batch->id, $this->item->id, [
            'purpose' => 'general_audit', 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
            'idempotency_key' => 'ui-no-answer', 'outcome' => 'no_answer', 'answer_1' => null, 'answer_2' => null, 'answer_3' => null,
        ]);
        $this->workspace(['row_version' => 3])->assertOk()->assertJsonPath('data.outcome', 'no_answer')
            ->assertJsonPath('data.phone', null)->assertJsonPath('data.is_due', false)->assertJsonPath('data.answers', [null, null, null]);
        $this->workspace(['row_version' => 3, 'claim_token' => $claim['claim_token']])->assertStatus(409);
        $this->now = $this->now->addSeconds(86400);
        $this->actingAs($this->fixtures->admin());
        $new = app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 3, 'idempotency_key' => 'ui-retry-claim']);
        $this->workspace(['row_version' => $new['row_version'], 'claim_token' => $new['claim_token']])->assertOk()
            ->assertJsonPath('data.phone', $this->item->submission->phone)->assertJsonPath('data.is_due', true);
    }

    public function test_browser_desktop_live_claim_release_save_tabs_and_toast_after_response(): void
    {
        $this->runBrowser('tests/Browser/admin-call-audits.mjs');
        $this->assertSame(5, DB::table('call_audit_attempts')->count());
        $this->assertSame(3, DB::table('call_audit_attempts')->where('outcome', 'correct')->count());
        $this->assertSame(1, DB::table('call_audit_attempts')->where('outcome', 'wrong')->count());
        $this->assertSame(1, DB::table('call_audit_attempts')->where('outcome', 'no_answer')->count());
    }

    public function test_refinement_browser_no_batch_zero_underfill_due_expiry_and_failure(): void
    {
        $empty = app(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-09-15'), $this->fixtures->tag.'-empty-september');
        $this->assertSame('FROZEN', $empty->summary['status']);
        $actual = (new FreezeClock)->now();
        $this->assertGreaterThan($this->batch->frozen_at->addHours(25), $actual);
        $items = $this->batch->items()->orderBy('selection_order')->take(2)->get();
        foreach ($items as $index => $item) {
            // Fixture-only clock: persist one historical and one current no-answer through C1.
            $this->now = $index === 0 ? $actual->subHours(25) : $actual;
            $claim = app(ClaimItem::class)->execute($this->batch->id, $item->id,
                ['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'edge-claim-'.$index]);
            app(RecordCallResult::class)->execute($this->batch->id, $item->id, [
                'purpose' => 'general_audit', 'row_version' => $claim['row_version'], 'claim_token' => $claim['claim_token'],
                'idempotency_key' => 'edge-result-'.$index, 'outcome' => 'no_answer',
                'answer_1' => null, 'answer_2' => null, 'answer_3' => null,
            ]);
        }
        $this->runBrowser('tests/Browser/admin-call-audits-refinement.mjs', [
            'P09M_DUE_ITEM' => (string) $items[0]->id, 'P09M_FUTURE_ITEM' => (string) $items[1]->id,
        ]);
        $this->assertSame(2, CallAuditBatch::count());
        $this->assertSame(3, DB::table('call_audit_attempts')->count());
        $this->assertSame(3, DB::table('call_audit_attempts')->where('outcome', 'no_answer')->count());
        $this->assertSame('completed', $items[0]->fresh()->queue_state);
        $this->assertSame('recall', $items[1]->fresh()->queue_state);
    }

    private function runBrowser(string $script, array $extraEnv = []): void
    {
        if (! is_file(base_path('.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs'))
            || ! is_file('C:/Program Files/Google/Chrome/Application/chrome.exe')) {
            $this->markTestSkipped('NOT_RUN: local Playwright/Chrome Desktop harness is unavailable.');
        }
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09l-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing',
            'TOYOTA_TEST_DATABASE' => 'toyota_testing', 'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'p09l_qa_session',
            'APP_URL' => 'http://127.0.0.1:8776',
            'P09L_ADMIN_ID' => (string) $this->admin->id, 'P09L_FIXTURE_TAG' => $this->fixtures->tag];
        $server = new Process([PHP_BINARY, '-S', '127.0.0.1:8776', '-t', 'public', base_path('tests/Fixtures/call_audit_ui_router.php')], base_path(), $env, null, 60);
        try {
            $server->start();
            $qa = new Process(['node', base_path($script)], base_path(), $extraEnv, null, 55);
            $qa->run();
            $this->assertTrue($qa->isSuccessful(), $qa->getErrorOutput().$qa->getOutput());
        } finally {
            if ($server->isRunning()) {
                if (PHP_OS_FAMILY === 'Windows') {
                    // Native termination of this exact child; taskkill/signal handling can hang in the sandbox.
                    $pidFile = storage_path('app/private/p09l-ui-server/'.$this->fixtures->tag.'.pid');
                    $actualPid = is_file($pidFile) ? trim(file_get_contents($pidFile)) : null;
                    if (! is_string($actualPid) || ! ctype_digit($actualPid)) {
                        throw new \RuntimeException('Cannot identify owned QA server PID safely.');
                    }
                    (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command',
                        'Stop-Process -Id '.(int) $actualPid.' -ErrorAction Stop'], base_path(), null, null, 10))->mustRun();
                } else {
                    $server->stop(1);
                }
            }
        }
    }

    private function claim(): array
    {
        return app(ClaimItem::class)->execute($this->batch->id, $this->item->id,
            ['purpose' => 'general_audit', 'row_version' => 1, 'idempotency_key' => 'ui-claim']);
    }

    private function workspace(array $overrides = [])
    {
        return $this->postJson($this->url(), array_replace(['purpose' => 'general_audit', 'row_version' => 1], $overrides));
    }

    private function url(): string
    {
        return '/admin/call-audits/'.$this->batch->id.'/items/'.$this->item->id.'/workspace';
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
