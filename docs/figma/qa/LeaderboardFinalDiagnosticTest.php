<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class LeaderboardFinalDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_service_query_failure_is_two_aggregation_queries_plus_two_ownership_lookups(): void
    {
        $this->assertSame(0, CustomerSubmission::count());
        $admin = User::factory()->admin()->create();
        $results = [];
        foreach (range(1, 16) as $index) {
            CustomerSubmission::factory()->approved()->create(['reviewed_by' => $admin->id, 'license_plate' => '78A-'.(60000 + $index)]);
            if (! in_array($index, [8, 16], true)) {
                continue;
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $service = app(LeaderboardService::class);
            $sales = $service->salesScores()->get();
            $dealers = $service->dealerScores()->get();
            $queries = collect(DB::getQueryLog());
            DB::disableQueryLog();
            $ownership = $queries->filter(fn ($query) => str_contains($query['query'], '`users`') && in_array(ToyotaRealisticDemo::EMAIL, $query['bindings'], true));
            $aggregates = $queries->diffKeys($ownership);
            $this->assertCount($index, $sales);
            $this->assertCount($index, $dealers);
            $this->assertCount(4, $queries);
            $this->assertCount(2, $ownership);
            $this->assertCount(2, $aggregates);
            foreach ($ownership as $query) {
                $this->assertSame('select * from `users` where `email` = ? limit 1', $query['query']);
                $this->assertSame([ToyotaRealisticDemo::EMAIL], $query['bindings']);
            }
            foreach ($aggregates as $query) {
                $this->assertStringContainsString('COUNT(*) AS score', $query['query']);
                $this->assertStringContainsString('group by', $query['query']);
            }
            $results[] = ['participants' => $index, 'aggregateQueries' => 2, 'ownershipQueries' => 2, 'totalQueries' => 4, 'sql' => $queries->pluck('query')->all()];
        }
        require_once __DIR__.'/artifact-paths.php';
        file_put_contents(qa_artifact_output('competition-leaderboard/final-qa').'/service-query-diagnostic.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
