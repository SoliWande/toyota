<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerDirectoryFiltersTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array']);
    }

    private function fixture(): array
    {
        $dealers = [];
        foreach (['zero' => 'A Zero', 'beta' => 'Beta', 'alpha' => 'Alpha', 'alphaTwin' => 'Alpha', 'top' => 'Zulu'] as $key => $name) {
            $dealer = Dealer::factory()->create(['name' => $name, 'province' => 'Hà Nội']);
            $dealers[$key] = $dealer;
            $count = match ($key) {
                'zero' => 0, 'top' => 3, default => 1
            };
            if ($count) {
                $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
                CustomerSubmission::factory()->approved()->count($count)->create(['sales_id' => $sales->id,
                    'submitted_at' => ($key === 'beta' ? '2025-01-01' : '2026-01-01').' 00:00:00']);
            }
        }

        return $dealers;
    }

    public function test_no_filters_preserves_existing_default_order_and_labels(): void
    {
        $f = $this->fixture();
        $r = $this->get(route('dealers.index'))->assertOk()->assertSee('#Hạng')->assertDontSee('HẠNG TOÀN KỲ')->assertDontSee('Hạng tháng này');
        $this->assertSame(array_map(fn ($key) => $f[$key]->id, ['top', 'alpha', 'alphaTwin', 'beta', 'zero']), $r->viewData('dealers')->pluck('id')->all());
        $this->assertSame(['q' => null, 'sort' => 'members', 'direction' => 'desc'], array_replace(['q' => null], $r->viewData('filters')));
        $r->assertSee('aria-label="#Hạng: 1, toàn kỳ"', false);
    }

    #[DataProvider('sorts')]
    public function test_all_six_orders_are_database_orders_with_stable_ties_and_unchanged_rank(string $sort, string $direction, array $expected): void
    {
        $f = $this->fixture();
        $ranks = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->pluck('rank', 'id');
        $response = $this->get(route('dealers.index', compact('sort', 'direction')))->assertOk();
        $rows = $response->viewData('dealers');
        $this->assertSame(array_map(fn ($key) => $f[$key]->id, $expected), $rows->pluck('id')->all());
        foreach ($rows as $row) {
            $this->assertEquals($ranks[$row->id] ?? null, $row->rank);
        }
        $this->assertSame($sort, $response->viewData('filters')['sort']);
        $this->assertSame($direction, $response->viewData('filters')['direction']);
        $response->assertSee('value="'.$sort.'" selected', false)->assertSee('name="direction" type="hidden" value="'.$direction.'"', false);
    }

    public static function sorts(): array
    {
        return [
            ['members', 'asc', ['zero', 'alpha', 'alphaTwin', 'beta', 'top']],
            ['members', 'desc', ['top', 'alpha', 'alphaTwin', 'beta', 'zero']],
            ['name', 'asc', ['zero', 'alpha', 'alphaTwin', 'beta', 'top']],
            ['name', 'desc', ['top', 'beta', 'alpha', 'alphaTwin', 'zero']],
            ['rank', 'asc', ['top', 'beta', 'alpha', 'alphaTwin', 'zero']],
            ['rank', 'desc', ['alphaTwin', 'alpha', 'beta', 'top', 'zero']],
        ];
    }

    public function test_members_count_is_only_approved_profiles_not_sales_or_group_flags_and_keeps_attribution(): void
    {
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        User::factory()->active()->count(4)->create(['dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->approved()->count(2)->create(['sales_id' => $sales->id]);
        $pending = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        // A group flag alone is not moderation approval (or public membership evidence).
        DB::table('submission_verification_states')->insert(['submission_id' => $pending->id, 'group_progress' => 'member']);
        $other = Dealer::factory()->create();
        $sales->forceFill(['dealer_id' => $other->id])->save();
        $rows = $this->get(route('dealers.index', ['sort' => 'members', 'direction' => 'desc']))->assertOk()->viewData('dealers');
        $this->assertSame(2, (int) $rows->firstWhere('id', $dealer->id)->members_count);
        $this->assertSame(0, (int) $rows->firstWhere('id', $other->id)->members_count);
        $this->assertSame(2, (int) $rows->firstWhere('id', $dealer->id)->score);
        $this->assertNull($rows->firstWhere('id', $other->id)->rank);
    }

    public function test_provinces_normalize_whitespace_deduplicate_sort_and_match_original_values_without_backfill(): void
    {
        $hanoi = Dealer::factory()->create(['province' => '  Hà  Nội  ', 'name' => 'Toyota Hanoi One']);
        $otherHanoi = Dealer::factory()->create(['province' => 'Hà Nội', 'name' => 'Toyota Hanoi Two']);
        Dealer::factory()->create(['province' => 'Đà Nẵng']);
        Dealer::factory()->create(['province' => 'An Giang']);
        foreach ([null, '', " \t\n ", "\u{00a0}", '1234'] as $bad) {
            Dealer::factory()->create(['province' => $bad]);
        }
        Dealer::factory()->create(['province' => 'INACTIVE_ONLY', 'is_active' => false]);
        $before = DB::table('dealers')->orderBy('id')->get()->toJson();
        $response = $this->get(route('dealers.index'))->assertOk();
        $this->assertSame(['An Giang', 'Đà Nẵng', 'Hà Nội'], $response->viewData('provinces')->all());
        $filtered = $this->get(route('dealers.index', ['province' => 'hà nội', 'q' => 'Toyota']))->assertOk();
        $this->assertEqualsCanonicalizing([$hanoi->id, $otherHanoi->id], $filtered->viewData('dealers')->pluck('id')->all());
        $this->assertSame('Hà Nội', $filtered->viewData('filters')['province']);
        $filtered->assertSee('value="Hà Nội" selected', false);
        $this->assertSame($before, DB::table('dealers')->orderBy('id')->get()->toJson());
        $this->get(route('dealers.index', ['province' => 'NO_DEALERS', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()->assertSee('Không tìm thấy đại lý phù hợp.')->assertSee('value="NO_DEALERS" selected', false);
    }

    public function test_null_province_database_reports_missing_data_instead_of_inventing_all_34_provinces(): void
    {
        Dealer::factory()->count(2)->create(['province' => null]);
        $response = $this->get(route('dealers.index'))->assertOk()->assertSee('Tất cả tỉnh thành')
            ->assertSee('Chưa có dữ liệu tỉnh/thành của đại lý để lựa chọn.');
        $this->assertCount(0, $response->viewData('provinces'));
        $this->assertSame(2, $response->viewData('dealers')->total());
    }

    public function test_pagination_keeps_all_filters_orders_before_paginate_and_rank_is_global(): void
    {
        for ($i = 1; $i <= 46; $i++) {
            Dealer::factory()->create(['name' => sprintf('QA Dealer %02d', $i), 'province' => 'Hà Nội']);
        }
        Dealer::factory()->create(['name' => 'QA Excluded', 'province' => 'Đà Nẵng']);
        $state = ['q' => 'QA', 'province' => 'Hà Nội', 'sort' => 'name', 'direction' => 'desc'];
        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->get(route('dealers.index', [...$state, 'page' => $page]))->assertOk();
            $rows = $response->viewData('dealers');
            $this->assertSame(46, $rows->total());
            $this->assertSame(3, $rows->lastPage());
            $ids = [...$ids, ...$rows->pluck('id')->all()];
            parse_str(parse_url($rows->url(2), PHP_URL_QUERY), $query);
            $this->assertSame($state, array_intersect_key($query, $state));
            $this->assertSame(sprintf('QA Dealer %02d', 46 - ($page - 1) * 20), $rows->first()->name);
        }
        $this->assertCount(46, array_unique($ids));
    }

    #[DataProvider('badParameters')]
    public function test_invalid_sort_direction_and_arrays_are_rejected(array $query, string $field): void
    {
        Dealer::factory()->create();
        $this->getJson(route('dealers.index', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(1, Dealer::count());
    }

    public static function badParameters(): array
    {
        return [[['sort' => 'score'], 'sort'], [['sort' => 'name desc; DROP TABLE dealers'], 'sort'],
            [['sort' => ['rank']], 'sort'], [['direction' => 'sideways'], 'direction'],
            [['direction' => 'ASC'], 'direction'], [['direction' => ['desc']], 'direction'], [['province' => ['invalid']], 'province']];
    }

    public function test_query_budget_is_constant_for_sorts_and_filters_as_directory_grows(): void
    {
        foreach ([2, 24] as $additional) {
            Dealer::factory()->count($additional)->create(['province' => 'Hà Nội']);
            foreach (['members', 'name', 'rank'] as $sort) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                $this->get(route('dealers.index', ['province' => 'Hà Nội', 'sort' => $sort, 'direction' => 'asc']))->assertOk();
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
                $this->assertCount(4, $queries);
                $this->assertContains(ToyotaRealisticDemo::EMAIL, $queries[0]['bindings']);
                $this->assertStringContainsString('select distinct `province`', $queries[1]['query']);
            }
        }
    }
}
