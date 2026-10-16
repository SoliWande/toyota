<?php

namespace Tests\Feature;

use App\Actions\CreateAwardDraft;
use App\Actions\PublishDealerAward;
use App\Enums\AwardPeriod;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class AwardWorkflowTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    private function draft(string $type = 'monthly', string $date = '2031-02-12'): Award
    {
        $this->travelTo(CarbonImmutable::parse('2031-03-01 00:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return app(CreateAwardDraft::class)->execute($admin, AwardPeriod::from($type), CarbonImmutable::parse($date, config('app.timezone')));
    }

    private function score(int $count = 1, string $time = '2031-02-12 12:00:00'): User
    {
        $account = User::factory()->active()->create();
        CustomerSubmission::factory()->count($count)->approved()->for($account, 'creator')->create([
            'submitted_at' => $time, 'reviewed_at' => '2031-03-01 00:00:00',
            'customer_name' => 'Private customer name', 'notes' => 'Private customer note',
        ]);

        return $account;
    }

    private function preview(Award $award): string
    {
        return $this->get(route('admin.awards.show', $award))->assertOk()->viewData('previewHash');
    }

    private function publish(Award $award, ?string $hash = null)
    {
        return $this->post(route('admin.awards.publish', $award), ['preview_hash' => $hash ?? $this->preview($award)]);
    }

    /** @dataProvider draftPeriods */
    public function test_canonical_draft_ignores_forged_publication_scope_and_winners(string $type, string $date, string $start, string $end): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.awards.store'), [
            'period_type' => $type, 'period_date' => $date, 'title' => 'Dealer competition',
            'competition_scope' => 'legacy_mixed', 'published_at' => now(), 'published_by' => 99999,
            'period_start' => '2000-01-01', 'period_end' => '2099-01-01', 'winners' => [['winner_type' => 'sales', 'score' => 999]],
        ])->assertSessionHasNoErrors();
        $award = Award::where('title', 'Dealer competition')->sole();
        $this->assertSame($start, $award->period_start->format('Y-m-d H:i:s'));
        $this->assertSame($end, $award->period_end->format('Y-m-d H:i:s'));
        $this->assertSame('dealer', $award->competition_scope);
        $this->assertNull($award->published_at);
        $this->assertNull($award->published_by);
        $this->assertSame(0, $award->winners()->count());
        $this->preview($award);
        $this->get(route('awards.show', $award))->assertNotFound();
    }

    public static function draftPeriods(): array
    {
        return [
            ['weekly', '2030-12-31', '2030-12-30 00:00:00', '2031-01-06 00:00:00'],
            ['monthly', '2032-02-29', '2032-02-01 00:00:00', '2032-03-01 00:00:00'],
        ];
    }

    public function test_same_period_opens_existing_draft_or_published_award_without_changing_title(): void
    {
        $award = $this->draft();
        $before = $award->refresh()->getRawOriginal();
        $this->post(route('admin.awards.store'), ['period_type' => 'monthly', 'period_date' => '2031-02-28', 'title' => 'Replacement'])
            ->assertRedirect(route('admin.awards.show', $award));
        $this->assertSame($before, $award->fresh()->getRawOriginal());
        $this->publish($award)->assertSessionHasNoErrors();
        $before = $award->refresh()->getRawOriginal();
        $this->post(route('admin.awards.store'), ['period_type' => 'monthly', 'period_date' => '2031-02-01'])
            ->assertRedirect(route('admin.awards.show', $award));
        $this->assertSame($before, $award->fresh()->getRawOriginal());
        $this->assertSame(1, Award::where('period_start', '2031-02-01')->where('period_type', 'monthly')->count());
    }

    public function test_preview_and_publication_contain_only_top_three_dealers_and_server_snapshots(): void
    {
        $award = $this->draft();
        $accounts = collect([$this->score(4), $this->score(3), $this->score(2), $this->score()]);
        $response = $this->get(route('admin.awards.show', $award))->assertOk()->assertDontSee('Top 3 Sales');
        $preview = $response->viewData('preview');
        $this->assertSame(['dealers'], array_keys($preview));
        $this->assertSame([4, 3, 2], $preview['dealers']->pluck('score')->all());
        $this->assertSame($accounts->take(3)->pluck('dealer_id')->all(), $preview['dealers']->pluck('dealer_id')->all());
        $this->assertSame([1, 2, 3], $preview['dealers']->pluck('rank')->all());
        $this->assertSame(0, $award->winners()->count());
        $this->post(route('admin.awards.publish', $award), ['preview_hash' => $response->viewData('previewHash'),
            'winners' => [['winner_type' => 'sales', 'score' => 999]], 'published_by' => $accounts[0]->id])->assertSessionHasNoErrors();
        $award->refresh();
        $this->assertNotNull($award->published_at);
        $this->assertSame(auth()->id(), $award->published_by);
        $this->assertSame(3, $award->winners()->count());
        foreach ($award->winners as $index => $winner) {
            foreach ($preview['dealers'][$index] as $key => $value) {
                $this->assertSame($value, $key === 'winner_type' ? $winner->$key->value : $winner->$key);
            }
        }
        $this->get(route('awards.show', $award))->assertOk()->assertDontSee('Top 3 Sales')
            ->assertDontSee('Private customer name')->assertDontSee('Private customer note')->assertDontSee($accounts[0]->email);
    }

    public function test_weekly_awards_use_monday_half_open_bounds_and_same_tie_breaking_as_leaderboard(): void
    {
        $award = $this->draft('weekly', '2031-02-12'); // Monday 10th through Monday 17th.
        $first = $this->score(1, '2031-02-10 00:00:00');
        $second = $this->score(1, '2031-02-10 00:00:00');
        $late = $this->score(1, '2031-02-16 23:59:59');
        $this->score(5, '2031-02-09 23:59:59');
        $this->score(5, '2031-02-17 00:00:00');
        $preview = app(AwardResults::class)->preview($award)['dealers'];
        $this->assertSame([$first->dealer_id, $second->dealer_id, $late->dealer_id], $preview->pluck('dealer_id')->all());
        $this->assertSame([1, 1, 1], $preview->pluck('score')->all());
        $this->publish($award)->assertSessionHasNoErrors();
        $this->assertSame($preview->pluck('dealer_id')->all(), $award->winners()->orderBy('rank')->pluck('dealer_id')->all());
    }

    public function test_publication_requires_admin_preview_in_the_same_session(): void
    {
        $award = $this->draft();
        $results = app(AwardResults::class);
        $forged = $results->fingerprint($award, $results->preview($award));
        $this->post(route('admin.awards.publish', $award), ['preview_hash' => $forged])->assertSessionHasErrors('preview_hash');
        $this->post(route('admin.awards.publish', $award), [])->assertSessionHasErrors('preview_hash');
        $this->assertNull($award->fresh()->published_at);
        $this->publish($award)->assertSessionHasNoErrors();
    }

    public function test_publish_is_blocked_until_exact_end_boundary(): void
    {
        $award = $this->draft();
        $hash = $this->preview($award);
        $this->travelTo($award->period_end->subSecond());
        $this->publish($award, $hash)->assertSessionHasErrors('award');
        $this->assertNull($award->fresh()->published_at);
        $this->travelTo($award->period_end);
        $this->publish($award, $hash)->assertSessionHasNoErrors();
    }

    public function test_pending_anywhere_in_period_blocks_publish_but_pending_at_end_does_not(): void
    {
        $award = $this->draft();
        $this->score();
        $pending = CustomerSubmission::factory()->create(['submitted_at' => '2031-02-01 00:00:00']);
        CustomerSubmission::factory()->create(['submitted_at' => '2031-03-01 00:00:00']);
        $hash = $this->preview($award);
        $this->publish($award, $hash)->assertSessionHasErrors('award');
        $this->assertNull($award->fresh()->published_at);
        $this->assertSame(0, $award->winners()->count());
        $this->post(route('admin.submissions.reject', $pending), ['rejection_reason' => 'Not a valid member'])->assertSessionHasNoErrors();
        $this->publish($award)->assertSessionHasNoErrors();
    }

    public function test_changed_preview_requires_a_fresh_preview_for_scores_or_dealer_names(): void
    {
        $award = $this->draft();
        $account = $this->score();
        $hash = $this->preview($award);
        $account->dealer->update(['name' => 'Renamed dealer']);
        $this->publish($award, $hash)->assertSessionHasErrors('preview_hash');
        $hash = $this->preview($award);
        $this->score(2);
        $this->publish($award, $hash)->assertSessionHasErrors('preview_hash');
        $this->assertNull($award->fresh()->published_at);
        $this->assertSame(0, $award->winners()->count());
        $this->publish($award)->assertSessionHasNoErrors();
    }

    public function test_double_publish_keeps_original_snapshot_and_publisher(): void
    {
        $award = $this->draft();
        $this->score();
        $hash = $this->preview($award);
        $this->publish($award, $hash)->assertSessionHasNoErrors();
        $before = $award->refresh()->getRawOriginal();
        $winners = $award->winners()->get()->toArray();
        $this->publish($award, $hash)->assertSessionHasErrors('preview_hash');
        try {
            app(PublishDealerAward::class)->execute(User::factory()->admin()->create(), $award, $hash);
            $this->fail('Published award must not be published again.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('award', $error->errors());
        }
        $this->assertSame($before, $award->fresh()->getRawOriginal());
        $this->assertSame($winners, $award->winners()->get()->toArray());
    }

    public function test_snapshot_and_publication_are_atomic_when_a_winner_write_fails(): void
    {
        $award = $this->draft();
        $this->score(2);
        $this->score();
        $hash = $this->preview($award);
        AwardWinner::creating(function (AwardWinner $winner) {
            if ($winner->rank === 2) {
                throw new RuntimeException('Simulated snapshot write failure.');
            }
        });
        try {
            app(PublishDealerAward::class)->execute(auth()->user(), $award, $hash);
            $this->fail('Expected write failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated snapshot write failure.', $error->getMessage());
        }
        $this->assertNull($award->fresh()->published_at);
        $this->assertSame(0, $award->winners()->count());
    }

    public function test_published_snapshots_are_immutable_in_models_and_raw_database_writes(): void
    {
        $award = $this->draft();
        $account = $this->score();
        $this->publish($award)->assertSessionHasNoErrors();
        $winner = $award->winners()->sole();
        $name = $winner->winner_name_snapshot;
        $account->dealer->update(['name' => 'Future dealer name']);
        $this->score(5);
        $this->get(route('awards.show', $award))->assertOk()->assertSee($name)->assertDontSee('Future dealer name');
        foreach ([fn () => $award->fresh()->update(['title' => 'Rewrite']), fn () => $award->fresh()->delete(),
            fn () => $winner->fresh()->forceFill(['score' => 99])->save(), fn () => $winner->fresh()->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Published snapshots must be immutable.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
        foreach ([fn () => DB::table('awards')->where('id', $award->id)->update(['title' => 'Rewrite']),
            fn () => DB::table('award_winners')->where('id', $winner->id)->update(['score' => 99]),
            fn () => DB::table('award_winners')->where('id', $winner->id)->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Database must preserve snapshots.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, $winner->fresh()->score);
    }

    public function test_legacy_published_sales_awards_remain_visible_and_byte_for_byte_unchanged(): void
    {
        $awards = DB::table('awards')->where('competition_scope', 'legacy_mixed')->orderBy('id')->get()->toArray();
        $winners = DB::table('award_winners')->whereIn('award_id', array_column($awards, 'id'))->orderBy('id')->get()->toArray();
        $award = Award::where('competition_scope', 'legacy_mixed')->whereNotNull('published_at')->firstOrFail();
        $sales = $award->winners()->where('winner_type', 'sales')->firstOrFail();
        $this->get(route('awards.show', $award))->assertOk()->assertSee($sales->winner_name_snapshot)->assertSee('Top 3 Sales');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.awards.show', $award))->assertOk()->assertViewHas('preview', null);
        $this->withSession(['awards' => ['preview' => [$award->id => str_repeat('a', 64)]]])
            ->post(route('admin.awards.publish', $award), ['preview_hash' => str_repeat('a', 64)])->assertSessionHasErrors('award');
        $new = $this->draft();
        $this->score();
        $this->publish($new)->assertSessionHasNoErrors();
        $this->assertSame(serialize($awards), serialize(DB::table('awards')->whereIn('id', array_column($awards, 'id'))->orderBy('id')->get()->toArray()));
        $this->assertSame(serialize($winners), serialize(DB::table('award_winners')->whereIn('id', array_column($winners, 'id'))->orderBy('id')->get()->toArray()));
    }

    public function test_new_sales_winners_are_rejected_by_model_and_database(): void
    {
        $award = $this->draft();
        $legacy = DB::table('legacy_sales_accounts')->first();
        $data = ['award_id' => $award->id, 'winner_type' => 'sales', 'sales_id' => $legacy->id,
            'dealer_id' => null, 'sales_dealer_id_snapshot' => $legacy->dealer_id, 'rank' => 1, 'score' => 1,
            'winner_name_snapshot' => 'Forbidden new Sales', 'dealer_name_snapshot' => 'Dealer', 'dealer_code_snapshot' => 'CODE'];
        try {
            (new AwardWinner)->forceFill($data)->save();
            $this->fail('New Sales awards must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $award->winners()->count());
        }
        $this->expectException(QueryException::class);
        DB::table('award_winners')->insert($data);
    }

    public function test_publish_rechecks_fresh_admin_status_and_rejects_malformed_periods(): void
    {
        $award = $this->draft();
        $admin = auth()->user();
        $hash = $this->preview($award);
        DB::table('users')->where('id', $admin->id)->update(['status' => 'blocked']);
        try {
            app(PublishDealerAward::class)->execute($admin, $award, $hash);
            $this->fail('Stale admin must be denied.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertNull($award->fresh()->published_at);
        }
        $award->update(['period_end' => '2031-03-02']);
        $this->expectException(ValidationException::class);
        app(AwardResults::class)->preview($award);
    }

    public function test_award_endpoints_require_active_admin_and_validate_inputs(): void
    {
        $award = Award::factory()->create(['period_start' => '2031-02-01', 'period_end' => '2031-03-01']);
        $this->post(route('admin.awards.publish', $award))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->active()->create())->getJson(route('admin.awards.show', $award))->assertForbidden();
        $this->postJson(route('admin.awards.publish', $award))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        foreach ([['period_type' => 'all'], ['period_date' => '2031-02-30'], ['title' => str_repeat('a', 256)]] as $invalid) {
            $this->post(route('admin.awards.store'), array_replace(['period_type' => 'monthly', 'period_date' => '2031-02-12'], $invalid))
                ->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->get(route('awards.index'))->assertOk()->assertDontSee($award->title);
    }
}
