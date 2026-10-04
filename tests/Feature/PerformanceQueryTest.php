<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PerformanceQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    private function captureQueries(callable $callback): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $callback();

            return DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_admin_queue_eager_loads_twenty_distinct_sales_and_dealers_in_constant_queries(): void
    {
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->count(21)->create();
        $this->actingAs($admin);
        foreach ([1 => 20, 2 => 1] as $page => $count) {
            $queries = $this->captureQueries(function () use ($page, $count) {
                $response = $this->get(route('admin.submissions.index', ['page' => $page]))->assertOk();
                $items = $response->viewData('submissions');
                $this->assertCount($count, $items);
                $this->assertSame(21, $items->total());
                foreach ($items as $submission) {
                    $this->assertTrue($submission->relationLoaded('sales'));
                    $this->assertTrue($submission->relationLoaded('dealer'));
                    $this->assertArrayNotHasKey('password', $submission->sales->getAttributes());
                }
            });
            // Count + page with indexed duplicate subquery + eager Sales/Dealers + two filter lists.
            $this->assertCount(6, $queries);
        }
    }

    public function test_sales_list_is_paginated_without_loading_unused_customer_details(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->create(['sales_id' => $sales->id, 'notes' => str_repeat('x', 2000)]);
        $this->actingAs($sales);
        $queries = $this->captureQueries(function () {
            $response = $this->get(route('sales.submissions.index'))->assertOk();
            $items = $response->viewData('submissions');
            $this->assertCount(20, $items);
            $this->assertSame(21, $items->total());
            foreach ($items as $submission) {
                $this->assertArrayNotHasKey('notes', $submission->getAttributes());
                $this->assertArrayNotHasKey('facebook_url', $submission->getAttributes());
            }
        });
        $this->assertCount(2, $queries);
    }

    public function test_sales_without_approved_submissions_does_not_calculate_global_ranking(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(6)->create(['sales_id' => $sales->id]);
        $this->actingAs($sales);
        $queries = $this->captureQueries(fn () => $this->get(route('sales.dashboard'))->assertOk()
            ->assertViewHas('currentRank', null)
            ->assertViewHas('recentSubmissions', fn ($items) => $items->count() === 5));
        $this->assertCount(3, $queries);
        $this->assertFalse(collect($queries)->contains(fn ($query) => str_contains($query['query'], 'ROW_NUMBER')));
    }

    public function test_sales_with_approved_submissions_keeps_global_rank_with_constant_queries(): void
    {
        $sales = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->approved()->count(6)->create(['sales_id' => $sales->id, 'reviewed_by' => $admin->id]);
        $this->actingAs($sales);
        $queries = $this->captureQueries(fn () => $this->get(route('sales.dashboard'))->assertOk()->assertViewHas('currentRank', 1));
        $this->assertCount(4, $queries);
    }

    /** @dataProvider awardPeriods */
    public function test_award_preview_uses_two_aggregate_queries_and_loads_only_top_three(string $period): void
    {
        $award = $period === 'weekly' ? Award::factory()->weekly()->create() : Award::factory()->create();
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->approved()->count(21)->create([
            'reviewed_by' => $admin->id, 'submitted_at' => $award->period_start->addHour(),
        ]);
        $results = app(AwardResults::class);
        $queries = $this->captureQueries(function () use ($award, $results) {
            $preview = $results->preview($award);
            $this->assertCount(3, $preview['sales']);
            $this->assertCount(3, $preview['dealers']);
            $this->assertSame([1, 2, 3], $preview['sales']->pluck('rank')->all());
        });
        $this->assertCount(2, $queries);
        foreach ($queries as $query) {
            $this->assertStringContainsString('COUNT(*) AS score', $query['query']);
            $this->assertStringContainsString('limit 3', $query['query']);
        }
    }

    public static function awardPeriods(): array
    {
        return [['weekly'], ['monthly']];
    }
}
