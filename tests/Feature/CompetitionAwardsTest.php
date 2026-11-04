<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CompetitionAwardsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function publish(string $start, User $admin, string $type = 'monthly'): Award
    {
        $date = CarbonImmutable::parse($start);
        $award = Award::factory()->create(['title' => 'PRIVATE_INTERNAL_TITLE', 'period_type' => $type,
            'period_start' => $date, 'period_end' => $type === 'monthly' ? $date->addMonth() : $date->addWeek()]);
        foreach ([1 => 67, 2 => 9, 3 => 8] as $rank => $score) {
            AwardWinner::factory()->dealer()->create(['award_id' => $award->id, 'rank' => $rank, 'score' => $score,
                'winner_name_snapshot' => 'Snapshot Đại lý '.$rank, 'dealer_name_snapshot' => 'Snapshot Đại lý '.$rank,
                'dealer_code_snapshot' => 'SNAPSHOT-'.$rank]);
        }
        AwardWinner::factory()->create(['award_id' => $award->id, 'winner_name_snapshot' => 'PRIVATE_SALES_SNAPSHOT']);
        $award->forceFill(['published_at' => '2026-11-01 12:00:00', 'published_by' => $admin->id])->save();

        return $award;
    }

    private function snapshot(): array
    {
        return [DB::table('awards')->orderBy('id')->get()->toJson(), DB::table('award_winners')->orderBy('id')->get()->toJson()];
    }

    public function test_public_ui_uses_stored_snapshots_and_matches_home_widget(): void
    {
        $admin = User::factory()->admin()->create();
        $award = $this->publish('2026-10-01', $admin);
        foreach ($award->winners()->whereNotNull('dealer_id')->get() as $winner) {
            $winner->dealer->update(['name' => 'PRIVATE_LIVE_RENAMED_DEALER']);
        }
        $before = $this->snapshot();
        $index = $this->get(route('awards.index'))->assertOk()->assertSee('DẤU ẤN CỘNG ĐỒNG')
            ->assertSee('Snapshot Đại lý 1')->assertSee('67 điểm')->assertSee('Tháng 10/2026')
            ->assertDontSee('PRIVATE_INTERNAL_TITLE')->assertDontSee('PRIVATE_SALES_SNAPSHOT')->assertDontSee('PRIVATE_LIVE_RENAMED_DEALER');
        $this->assertSame($award->id, $index->viewData('selectedAward')->id);
        $detail = $this->get(route('awards.show', $award))->assertOk()->assertSee('Kết quả đã lưu tại thời điểm công bố.')
            ->assertDontSee('PRIVATE_');
        $public = $detail->viewData('award');
        $this->assertSame(['id', 'period_type', 'period_start', 'period_end', 'published_at'], array_keys($public->getAttributes()));
        $this->assertSame([1, 2, 3], $public->winners->pluck('rank')->all());
        $this->assertSame([67, 9, 8], $public->winners->pluck('score')->all());
        $home = $this->get(route('home'))->assertOk()->assertSee(route('awards.show', $award));
        $this->assertSame($public->id, $home->viewData('latestAward')->id);
        $this->assertSame($public->winners->pluck('dealer_name_snapshot')->all(), $home->viewData('latestAward')->winners->pluck('dealer_name_snapshot')->all());
        $this->assertSame($before, $this->snapshot());
    }

    public function test_empty_public_state_excludes_drafts_and_sales_only_publications_for_every_role(): void
    {
        $draft = Award::factory()->create(['title' => 'PRIVATE_DRAFT']);
        AwardWinner::factory()->dealer()->create(['award_id' => $draft->id, 'dealer_name_snapshot' => 'PRIVATE_DRAFT_WINNER']);
        $salesOnly = AwardWinner::factory()->create(['award_id' => Award::factory()->weekly(), 'winner_name_snapshot' => 'PRIVATE_SALES_ONLY'])->award;
        $salesOnly->forceFill(['published_at' => now(), 'published_by' => User::factory()->admin()->create()->id])->save();
        foreach ([null, User::factory()->admin()->create(), User::factory()->dealer()->create(), User::factory()->active()->create()] as $viewer) {
            if ($viewer) {
                $this->actingAs($viewer);
            }
            $response = $this->get(route('awards.index'))->assertOk()->assertSee('Chưa có kết quả vinh danh được công bố.')
                ->assertDontSee('PRIVATE_')->assertDontSee('data-award-rank=', false);
            $this->assertSame(0, $response->viewData('awards')->total());
            $this->assertNull($response->viewData('selectedAward'));
            $this->get(route('awards.show', $draft))->assertNotFound();
            $this->get(route('awards.show', $salesOnly))->assertNotFound();
        }
    }

    public function test_history_filters_pagination_and_queries_remain_bounded(): void
    {
        $admin = User::factory()->admin()->create();
        $awards = [];
        $measure = function (string $url, int $expectedQueries): TestResponse {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->get($url)->assertOk()->assertDontSee('PRIVATE_');
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertCount($expectedQueries, $queries);
            foreach ($queries as $query) {
                $this->assertStringNotContainsString('customer_submissions', $query['query'], 'Published snapshots must not be recalculated.');
            }

            return $response;
        };
        for ($index = 0; $index < 14; $index++) {
            $awards[] = $this->publish(CarbonImmutable::parse('2026-10-01')->subMonths($index)->format('Y-m-d'), $admin);
            if ($index === 2) {
                $measure(route('awards.index'), 3);
            }
        }
        $weekly = $this->publish('2026-10-26', $admin, 'weekly');
        $before = $this->snapshot();
        $first = $measure(route('awards.index', ['period_type' => 'monthly', 'email' => 'PRIVATE_QUERY_EMAIL']), 3);
        $this->assertSame(14, $first->viewData('awards')->total());
        $this->assertSame(array_map(fn ($award) => $award->id, array_slice($awards, 0, 12)), $first->viewData('awards')->pluck('id')->all());
        $next = $first->viewData('awards')->nextPageUrl();
        $this->assertStringContainsString('period_type=monthly', $next);
        $second = $measure($next, 3);
        $this->assertSame([$awards[12]->id, $awards[13]->id], $second->viewData('awards')->pluck('id')->all());
        $detail = $measure(route('awards.show', ['award' => $awards[12]->id, 'page' => 2, 'period_type' => 'monthly']), 5);
        $this->assertSame($awards[12]->id, $detail->viewData('award')->id);
        $this->assertSame(2, $detail->viewData('awards')->currentPage());
        $week = $measure(route('awards.index', ['period_type' => 'weekly']), 3);
        $this->assertSame([$weekly->id], $week->viewData('awards')->pluck('id')->all());
        $this->assertSame($weekly->id, $week->viewData('selectedAward')->id);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_missing_snapshot_name_and_dealer_id_do_not_create_a_fake_identity_or_link(): void
    {
        // Schema prohibits blank snapshots; test defensive presentation with an
        // unsaved DTO, never bypass the database constraint to create bad data.
        $award = new Award;
        $award->setDateFormat('Y-m-d H:i:s');
        $award->forceFill(['id' => 1, 'period_type' => 'monthly', 'period_start' => '2026-10-01',
            'period_end' => '2026-11-01', 'published_at' => '2026-11-01']);
        $winner = new AwardWinner;
        $winner->forceFill(['dealer_id' => null, 'rank' => 1, 'score' => 1,
            'dealer_name_snapshot' => '', 'dealer_code_snapshot' => '']);
        $award->setRelation('winners', collect([$winner]));
        $html = Blade::render('<x-competition.award-panel :award="$award" />', ['award' => $award]);
        $this->assertStringContainsString('Chưa có thông tin tên đại lý', $html);
        $this->assertStringContainsString('1 điểm', $html);
        $this->assertStringNotContainsString('/dealers/0', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }
}
