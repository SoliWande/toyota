<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\CallAudit\CohortBuilder;
use App\Services\CallAudit\ManifestCodec;
use App\Services\CallAudit\SamplingPlanner;
use App\Services\CallAudit\SourceFingerprint;
use App\Services\ResetDemoRetirement;
use App\Services\ToyotaRealisticDemo;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CallAuditSamplingTest extends TestCase
{
    private bool $transactionStarted = false;

    private bool $fixtures = false;

    private array $fixtureIds = [];

    private ?User $admin = null;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::assertIsolated();
        $db = DB::connection();
        $this->assertSame('127.0.0.1', $db->getConfig('host'));
        $this->assertSame('toyota_testing', $db->getConfig('database'));
        foreach (['url', 'unix_socket', 'read', 'write'] as $override) {
            $this->assertEmpty($db->getConfig($override));
        }
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $db->beforeExecuting(function (string $sql, array $bindings) use ($db): void {
            if (preg_match('/^\s*(select|show)\b/i', $sql)) {
                return;
            }
            if ($this->transactionStarted && $db->transactionLevel() > 0) {
                if (preg_match('/^(SAVEPOINT trans[0-9]+|ROLLBACK TO SAVEPOINT trans[0-9]+)$/i', $sql)) {
                    return;
                }
                if ($this->fixtures && preg_match('/^insert into `(users|dealers|customer_submissions|awards)` /i', $sql)) {
                    return;
                }
                if ($this->fixtures && preg_match('/^update `(users|dealers|customer_submissions|awards)` .* where `id` = \?$/i', $sql, $match)
                    && in_array(end($bindings), $this->fixtureIds[$match[1]] ?? [], true)) {
                    return;
                }
            }

            throw new \LogicException('A2 forbids DDL, audit-table writes and writes outside transactional fixtures.');
        });
        DB::beginTransaction();
        $this->transactionStarted = true;
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
        $receipt = Mockery::mock(ResetDemoRetirement::class);
        $receipt->shouldReceive('isPresent')->andReturn(false);
        $this->app->instance(ResetDemoRetirement::class, $receipt);
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_month_boundaries_current_approval_and_invalid_phone_exclusions_are_real(): void
    {
        $dealer = $this->dealer();
        $start = $this->submission($dealer, ['submitted_at' => '2026-10-01 00:00:00', 'phone' => '+84 (900) 000-001']);
        $last = $this->submission($dealer, ['submitted_at' => '2026-10-31 23:59:59']);
        $this->submission($dealer, ['submitted_at' => '2026-09-30 23:59:59']);
        $this->submission($dealer, ['submitted_at' => '2026-11-01 00:00:00']);
        $this->submission($dealer, ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        $this->submission($dealer, ['status' => 'rejected']);
        $invalid = $this->submission($dealer, ['phone' => 'bad', 'phone_normalized' => 'bad']);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('READY', $result['status']);
        $this->assertSame([$start->id, $last->id], array_column($result['candidates'], 'submission_id'));
        $this->assertSame([['submission_id' => $invalid->id, 'code' => 'invalid_phone']], $result['excluded']);
        $this->assertSame('2026-10-01 00:00:00', $result['context']['period_start']);
        $this->assertSame('2026-11-01 00:00:00', $result['context']['period_end']);
        $this->assertSame('UTC', $result['context']['calendar_zone']);
    }

    #[DataProvider('conflicts')]
    public function test_conflicting_phone_plate_and_facebook_are_blocked_without_selecting_representatives(string $kind, string $code): void
    {
        $dealer = $this->dealer();
        $first = $this->submission($dealer);
        $second = $this->submission($dealer);
        $change = match ($kind) {
            'phone' => ['phone' => '+84 900 000 001', 'phone_normalized' => $first->phone],
            'alias' => ['phone_normalized' => $first->phone],
            'plate' => ['license_plate' => strtolower(str_replace(['-', '.'], ' ', $first->license_plate))],
            'facebook' => ['facebook_url' => $first->facebook_url],
        };
        $this->change($second, $change);
        $plan = app(SamplingPlanner::class)->general($this->actor(), $this->month(), str_repeat('s', 32));
        $this->assertSame('BLOCKED', $plan->summary['status']);
        $this->assertContains($code, array_column($plan->summary['issues'], 'code'));
        $this->assertSame([], $plan->selected());
        $this->assertNull($plan->manifest());
        $this->assertNull($plan->seed());
        $json = json_encode($plan, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($first->phone, $json);
        $this->assertStringNotContainsString($first->facebook_url, $json);
        $field = match ($kind) {
            'alias' => 'phone_normalized', 'phone' => 'phone', 'plate' => 'license_plate', 'facebook' => 'facebook_url',
        };
        $this->assertSame($change[$field], $second->fresh()->getAttribute($field));
    }

    public static function conflicts(): array
    {
        return [['phone', 'duplicate_phone'], ['alias', 'phone_alias_conflict'],
            ['plate', 'duplicate_plate'], ['facebook', 'duplicate_facebook']];
    }

    public function test_duplicate_identity_is_not_hidden_by_invalid_phone_exclusion(): void
    {
        $dealer = $this->dealer();
        $first = $this->submission($dealer);
        $invalid = $this->submission($dealer, ['phone' => 'invalid', 'phone_normalized' => 'invalid']);
        $this->change($invalid, ['license_plate' => $first->license_plate]);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('BLOCKED', $result['status']);
        $this->assertContains('duplicate_plate', array_column($result['issues'], 'code'));
    }

    public function test_demo_scope_excludes_owned_ids_without_changing_the_existing_service(): void
    {
        $dealer = $this->dealer();
        $owned = $this->submission($dealer);
        $real = $this->submission($dealer);
        $ownership = Mockery::mock(ToyotaRealisticDemo::class);
        $ownership->shouldReceive('ownedSubmissionIds')->andReturn([$owned->id]);
        $this->app->instance(ToyotaRealisticDemo::class, $ownership);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('READY', $result['status']);
        $this->assertSame([$real->id], array_column($result['candidates'], 'submission_id'));
        $this->assertSame((new ManifestCodec)->digest([$owned->id]), $result['context']['demo_policy']['excluded_ids_digest']);
    }

    public function test_invalid_demo_metrics_configuration_returns_blocked_not_an_empty_ready_batch(): void
    {
        config(['realistic_demo.include_in_metrics' => true, 'realistic_demo.enabled' => false]);
        $plan = app(SamplingPlanner::class)->general($this->actor(), $this->month());
        $this->assertSame('BLOCKED', $plan->summary['status']);
        $this->assertSame('demo_scope_blocked', $plan->summary['issues'][0]['code']);
        $this->assertNull($plan->seed());
    }

    public function test_retired_demo_guard_failure_is_not_suppressed(): void
    {
        config(['realistic_demo.retired' => true, 'realistic_demo.enabled' => true]);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('BLOCKED', $result['status']);
        $this->assertSame('demo_scope_blocked', $result['issues'][0]['code']);
    }

    public function test_explicitly_enabled_demo_metrics_keep_the_current_scope_semantics(): void
    {
        $row = $this->submission($this->dealer());
        config(['realistic_demo.include_in_metrics' => true, 'realistic_demo.enabled' => true]);
        $ownership = Mockery::mock(ToyotaRealisticDemo::class);
        $ownership->shouldNotReceive('ownedSubmissionIds');
        $this->app->instance(ToyotaRealisticDemo::class, $ownership);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('READY', $result['status']);
        $this->assertSame([$row->id], array_column($result['candidates'], 'submission_id'));
        $this->assertTrue($result['context']['demo_policy']['include_in_metrics']);
    }

    public function test_source_integrity_conflict_blocks_instead_of_reattributing_a_submission(): void
    {
        $dealer = $this->dealer();
        $sales = $this->fixture(fn () => User::factory()->active()->create(['dealer_id' => $dealer->id]));
        $row = $this->submission($dealer, ['source' => 'sales', 'sales_id' => $sales->id]);
        $this->change($sales, ['role' => 'dealer']);
        $result = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('BLOCKED', $result['status']);
        $this->assertContains('source_integrity_conflict', array_column($result['issues'], 'code'));
        $this->assertSame($dealer->id, $row->fresh()->dealer_id);
    }

    public function test_general_sampling_caps_at_60_without_dealer_quota_or_out_of_month_fill(): void
    {
        $large = $this->dealer();
        $small = $this->dealer();
        for ($i = 0; $i < 64; $i++) {
            $this->submission($large);
        }
        $this->submission($small);
        $outside = $this->submission($small, ['submitted_at' => '2026-09-30 23:59:59']);
        $plan = app(SamplingPlanner::class)->general($this->actor(), $this->month(), str_repeat('c', 32));
        $this->assertSame('READY', $plan->summary['status']);
        $this->assertSame(60, $plan->summary['requested_count']);
        $this->assertSame(60, $plan->summary['actual_count']);
        $this->assertSame(65, $plan->summary['eligible_count']);
        $ids = array_column($plan->selected(), 'submission_id');
        $this->assertCount(60, array_unique($ids));
        $this->assertNotContains($outside->id, $ids);
        $this->assertGreaterThanOrEqual(59, count(array_filter($plan->selected(), fn ($row) => $row['dealer_id'] === $large->id)));
        $this->assertSame($plan->selected(), app(SamplingPlanner::class)->reproduce($plan->manifest(), $plan->seed()));
        $this->assertArrayNotHasKey('manifest', json_decode(json_encode($plan, JSON_THROW_ON_ERROR), true));
        $this->assertStringNotContainsString('source_fingerprint', json_encode($plan, JSON_THROW_ON_ERROR));
    }

    public function test_empty_and_underfilled_plans_do_not_fake_completion_or_add_previous_month(): void
    {
        $planner = app(SamplingPlanner::class);
        $empty = $planner->general($this->actor(), $this->month());
        $this->assertSame(60, $empty->summary['requested_count']);
        $this->assertSame(0, $empty->summary['actual_count']);
        $this->assertArrayNotHasKey('completed', $empty->summary);
        $this->submission($this->dealer());
        $small = $planner->general($this->actor(), $this->month());
        $this->assertSame(1, $small->summary['actual_count']);
        $this->assertSame(1, count($small->manifest()['candidates']));
    }

    public function test_award_top3_uses_real_baseline_and_separate_five_per_dealer_strata(): void
    {
        $dealers = [];
        foreach ([8, 7, 3, 2] as $size) {
            $dealer = $dealers[] = $this->dealer();
            for ($i = 0; $i < $size; $i++) {
                $this->submission($dealer);
            }
        }
        $award = $this->award();
        $planner = app(SamplingPlanner::class);
        $plan = $planner->awardTop3($this->actor(), $award, str_repeat('a', 32));
        $this->assertSame('READY', $plan->summary['status']);
        $this->assertSame(15, $plan->summary['requested_count']);
        $this->assertSame(13, $plan->summary['actual_count']);
        $this->assertSame([5, 5, 3], array_column($plan->summary['strata'], 'actual_count'));
        $this->assertSame(array_column(array_slice($dealers, 0, 3), 'id'), array_column($plan->summary['strata'], 'dealer_id'));
        $this->assertCount(18, $plan->manifest()['candidates']);
        $this->assertNotContains($dealers[3]->id, array_column($plan->selected(), 'dealer_id'));
        $results = app(AwardResults::class)->preview($award);
        $this->assertSame(app(AwardResults::class)->fingerprint($award, $results), $plan->manifest()['award_baseline']['fingerprint']);
        $general = $planner->general($this->actor(), $this->month(), str_repeat('a', 32));
        $this->assertSame(13, count(array_intersect(array_column($general->selected(), 'submission_id'), array_column($plan->selected(), 'submission_id'))));
    }

    public function test_award_with_less_than_three_dealers_keeps_real_counts_and_does_not_replace_them(): void
    {
        $award = $this->award();
        $planner = app(SamplingPlanner::class);
        $zero = $planner->awardTop3($this->actor(), $award);
        $this->assertSame(0, $zero->summary['requested_count']);
        $this->assertSame(0, $zero->summary['actual_count']);
        $this->submission($this->dealer());
        $one = $planner->awardTop3($this->actor(), $award);
        $this->assertSame(5, $one->summary['requested_count']);
        $this->assertSame(1, $one->summary['actual_count']);
        $this->assertCount(1, $one->summary['strata']);
        $this->assertSame(15, $one->summary['target_cap']);
    }

    public function test_historic_dealer_and_missing_normalized_phone_survive_a_sales_transfer(): void
    {
        $historic = $this->dealer();
        $new = $this->dealer();
        $sales = $this->fixture(fn () => User::factory()->active()->create(['dealer_id' => $historic->id]));
        $submission = $this->submission($historic, ['source' => 'sales', 'sales_id' => $sales->id, 'phone_normalized' => null]);
        $fingerprint = app(SourceFingerprint::class)->of($submission->fresh()->load('dealer'));
        $this->change($sales, ['dealer_id' => $new->id]);
        $cohort = app(CohortBuilder::class)->build($this->month());
        $this->assertSame('READY', $cohort['status']);
        $this->assertSame($historic->id, $cohort['candidates'][0]['dealer_id']);
        $this->assertSame($fingerprint, $cohort['candidates'][0]['source_fingerprint']);
    }

    public function test_source_drift_is_detected_even_without_a_new_updated_at_and_old_manifest_reproduces_without_queries(): void
    {
        $row = $this->submission($this->dealer());
        $planner = app(SamplingPlanner::class);
        $plan = $planner->general($this->actor(), $this->month(), str_repeat('s', 32));
        $old = $plan->selected()[0]['source_fingerprint'];
        $this->change($row, ['phone' => '0912345678', 'phone_normalized' => '0912345678']);
        $this->assertFalse(app(SourceFingerprint::class)->matches($old, $row->fresh()->load('dealer')));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($plan->selected(), $planner->reproduce($plan->manifest(), $plan->seed()));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($old, $plan->manifest()['candidates'][0]['source_fingerprint']);
    }

    public function test_published_weekly_or_stale_award_cannot_be_previewed_as_a_monthly_draft(): void
    {
        $award = $this->award();
        $this->change($award, ['published_at' => '2026-11-03', 'published_by' => $this->actor()->id]);
        $plan = app(SamplingPlanner::class)->awardTop3($this->actor(), $award);
        $this->assertSame('BLOCKED', $plan->summary['status']);
        $weekly = $this->fixture(fn () => Award::factory()->weekly()->create());
        $this->assertSame('BLOCKED', app(SamplingPlanner::class)->awardTop3($this->actor(), $weekly)->summary['status']);
    }

    #[DataProvider('corruptions')]
    public function test_corrupted_reproduction_material_is_rejected(string $kind): void
    {
        $planner = app(SamplingPlanner::class);
        $plan = $planner->general($this->actor(), $this->month(), str_repeat('s', 32));
        $manifest = $plan->manifest();
        $seed = $plan->seed();
        if ($kind === 'selection_digest') {
            $manifest['selection_digest'] = str_repeat('0', 64);
        } elseif ($kind === 'seed_commitment') {
            $manifest['seed_commitment'] = str_repeat('0', 64);
        } elseif ($kind === 'wrong_seed') {
            $seed = str_repeat('x', 32);
        } else {
            $manifest['strata'][] = $manifest['strata'][0];
        }
        $this->expectException(InvalidArgumentException::class);
        $planner->reproduce($manifest, $seed);
    }

    public static function corruptions(): array
    {
        return [['selection_digest'], ['seed_commitment'], ['wrong_seed'], ['duplicate_strata']];
    }

    public function test_blocked_admin_cannot_access_private_sampling_material(): void
    {
        $actor = $this->actor();
        $actor->status = UserStatus::Blocked;
        $this->expectException(AuthorizationException::class);
        app(SamplingPlanner::class)->general($actor, $this->month());
    }

    private function actor(): User
    {
        return $this->admin ??= $this->fixture(fn () => User::factory()->admin()->create([
            'name' => 'P09G test Admin', 'email' => 'p09g-'.Str::uuid().'@example.test',
        ]));
    }

    private function dealer(): Dealer
    {
        return $this->fixture(fn () => Dealer::factory()->create(['code' => 'P09G-'.Str::uuid(), 'name' => 'P09G test Dealer']));
    }

    private function submission(Dealer $dealer, array $overrides = []): CustomerSubmission
    {
        $actor = $this->actor();
        $n = ++$this->sequence;

        return $this->fixture(fn () => CustomerSubmission::factory()->approved()->create(array_replace([
            'customer_name' => 'P09G test customer '.$n, 'source' => 'dealer_qr', 'sales_id' => null,
            'dealer_id' => $dealer->id, 'reviewed_by' => $actor->id, 'reviewed_at' => '2026-11-02 00:00:00',
            'submitted_at' => '2026-10-02 00:00:00', 'phone' => sprintf('090%07d', $n),
            'phone_normalized' => sprintf('090%07d', $n), 'license_plate' => sprintf('51K-%05d', $n),
            'vehicle_description' => 'Toyota test vehicle', 'address_line' => 'Isolated test only',
            'province_code' => '79', 'ward_code' => '26734', 'consented_at' => '2026-10-02', 'consent_version' => 'p09g-only',
        ], $overrides)));
    }

    private function award(): Award
    {
        return $this->fixture(fn () => Award::factory()->create([
            'title' => 'P09G test draft', 'period_start' => '2026-10-01', 'period_end' => '2026-11-01',
        ]));
    }

    private function change(Model $row, array $values): void
    {
        $this->fixtures = true;
        try {
            DB::table($row->getTable())->where('id', $row->id)->update($values);
        } finally {
            $this->fixtures = false;
        }
    }

    private function fixture(callable $create): mixed
    {
        $this->assertTrue($this->transactionStarted && DB::connection()->transactionLevel() > 0);
        $previous = $this->fixtures;
        $this->fixtures = true;
        try {
            $model = $create();
            $this->fixtureIds[$model->getTable()][] = $model->id;

            return $model;
        } finally {
            $this->fixtures = $previous;
        }
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-15', 'UTC');
    }
}
