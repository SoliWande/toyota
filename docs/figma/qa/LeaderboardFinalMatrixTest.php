<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

// Final QA only; existing isolated schema, no migrate/seed/reset. Run with
// competition-test-bootstrap.php only after competition-db-check.php passes.
class LeaderboardFinalMatrixTest extends TestCase
{
    use RefreshDatabase;

    private static array $evidence = [];

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public static function countsAndPeriods(): iterable
    {
        foreach ([0, 1, 2, 3, 4, 20, 21, 40, 46] as $count) {
            foreach (['month', 'all'] as $period) {
                yield $count.'-'.$period => [$count, $period];
            }
        }
    }

    #[DataProvider('countsAndPeriods')]
    public function test_all_pages_preserve_service_order_unique_coverage_and_period(int $count, string $periodValue): void
    {
        $this->assertSame(0, CustomerSubmission::count(), 'Matrix requires an empty isolated test transaction baseline.');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $expectedIds = [];
        foreach (range(1, max(1, $count)) as $index) {
            if ($index > $count) {
                break;
            }
            $sales = User::factory()->active()->create(['name' => 'PRIVATE_MATRIX_SALES_'.$index]);
            $sales->dealer->update(['name' => 'Đại lý kiểm thử Mỗi kết nối – Một hành trình mới '.$index]);
            CustomerSubmission::factory()->approved()->create([
                'sales_id' => $sales->id, 'reviewed_by' => $admin->id,
                'license_plate' => '78A-'.(50000 + $index),
                'submitted_at' => CarbonImmutable::parse('2026-11-01 10:00:00')->addMinutes($index),
                'customer_name' => 'PRIVATE_MATRIX_CUSTOMER', 'admin_note' => 'PRIVATE_MATRIX_NOTE',
            ]);
            $expectedIds[] = $sales->dealer_id;
        }
        $period = LeaderboardPeriod::from($periodValue);
        $service = app(LeaderboardService::class);
        $before = $service->dealers($period)->get()->map(fn ($row) => (array) $row)->all();
        $this->assertSame($expectedIds, array_column($before, 'id'));
        $this->assertSame($count === 0 ? [] : range(1, $count), array_map('intval', array_column($before, 'rank')));
        $this->assertSame(array_fill(0, $count, 1), array_map('intval', array_column($before, 'score')));
        $sourceCount = CustomerSubmission::count();
        $expectedTop = array_slice($expectedIds, 0, 3);
        $lastPage = max(1, (int) ceil($count / 20));
        $directory = base_path('.laravel-tools/competition/leaderboard-final-qa');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $seen = [];
        $pages = [];
        $url = route('leaderboard', ['period' => $periodValue]);
        for ($page = 1; $page <= $lastPage; $page++) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->get($url)->assertOk()->assertDontSee('PRIVATE_MATRIX_');
            $queries = collect(DB::getQueryLog());
            DB::disableQueryLog();
            $ownership = $queries->filter(fn ($query) => str_contains($query['query'], '`users`') && in_array(ToyotaRealisticDemo::EMAIL, $query['bindings'], true));
            $this->assertCount($page === 1 ? 1 : 2, $ownership);
            $this->assertCount($count === 0 ? 1 : ($page === 1 ? 2 : 3), $queries->diffKeys($ownership));
            $rows = $response->viewData('dealers');
            $this->assertSame($count, $rows->total());
            $this->assertSame($page, $rows->currentPage());
            $this->assertSame($lastPage, $rows->lastPage());
            $this->assertSame($period, $response->viewData('period'));
            $window = array_slice($before, ($page - 1) * 20, 20);
            $this->assertSame(array_column($window, 'id'), $rows->pluck('id')->all());
            $this->assertSame(array_map('intval', array_column($window, 'rank')), $rows->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
            $this->assertSame($expectedTop, $response->viewData('topDealers')->pluck('id')->all());
            foreach ($rows as $row) {
                $this->assertSame(['id', 'name', 'code', 'score', 'rank'], array_keys((array) $row));
            }
            $html = $response->getContent();
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            $ids = function (string $class) use ($xpath): array {
                $items = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');
                $ids = [];
                foreach ($items as $item) {
                    $this->assertMatchesRegularExpression('~/dealers/[0-9]+$~', $item->getAttribute('href'));
                    $ids[] = (int) basename(parse_url($item->getAttribute('href'), PHP_URL_PATH));
                }

                return $ids;
            };
            $cardIds = $ids('wc-lb-card');
            $listIds = $ids('wc-lb-row');
            $expectedList = array_column(array_values(array_filter($window, fn ($row) => $row['rank'] > 3)), 'id');
            $this->assertSame($expectedTop, $cardIds);
            $this->assertSame($expectedList, $listIds);
            $displayed = [...$cardIds, ...$listIds];
            $this->assertCount(count($displayed), array_unique($displayed), 'No duplicated Dealer within a page.');
            if ($page === 1) {
                $seen = $cardIds;
            }
            $seen = [...$seen, ...$listIds];
            $active = $xpath->query('//nav[contains(@class,"wc-lb-periods")]/a[@aria-current="page"]');
            $this->assertSame(1, $active->length);
            $this->assertSame($period->label(), trim($active->item(0)->textContent));
            $this->assertStringContainsString('period='.$periodValue, $active->item(0)->getAttribute('href'));
            if ($count > 0) {
                $this->assertStringContainsString('Trang '.$page.' / '.$lastPage, $html);
            }
            $next = $rows->nextPageUrl();
            $prev = $rows->previousPageUrl();
            foreach (array_filter([$next, $prev]) as $target) {
                parse_str(parse_url($target, PHP_URL_QUERY), $query);
                $this->assertSame($periodValue, $query['period']);
                $this->assertSame(['period', 'dealers_page'], array_keys($query));
            }
            $file = 'count-'.$count.'-'.$periodValue.'-page-'.$page.'.html';
            file_put_contents($directory.'/'.$file, $html);
            $pages[] = ['page' => $page, 'total' => $rows->total(), 'lastPage' => $lastPage, 'cardIds' => $cardIds, 'listIds' => $listIds, 'ranks' => array_map('intval', array_column($window, 'rank')), 'businessQueries' => $queries->diffKeys($ownership)->count(), 'ownershipQueries' => $ownership->count(), 'html' => $file];
            if ($page < $lastPage) {
                $this->assertNotNull($next);
                $url = $next;
            } else {
                $this->assertNull($next);
            }
        }
        $this->assertSame($expectedIds, $seen, 'Top3 once plus paginated lists cover every Dealer exactly once.');
        $this->assertCount($count, array_unique($seen));
        $after = $service->dealers($period)->get()->map(fn ($row) => (array) $row)->all();
        $this->assertSame($before, $after, 'Rendering and pagination cannot change LeaderboardService results.');
        $this->assertSame($sourceCount, CustomerSubmission::count());
        self::$evidence[] = ['count' => $count, 'period' => $periodValue, 'totalPages' => $lastPage, 'uniqueCovered' => count($seen), 'serviceUnchanged' => true, 'pages' => $pages];
    }

    public static function tearDownAfterClass(): void
    {
        require_once __DIR__.'/artifact-paths.php';
        $output = qa_artifact_output('competition-leaderboard/final-qa');
        if (! is_dir($output)) {
            mkdir($output, 0777, true);
        }
        file_put_contents($output.'/matrix.json', json_encode(self::$evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        parent::tearDownAfterClass();
    }
}
