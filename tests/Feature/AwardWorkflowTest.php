<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AwardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    /** @dataProvider draftPeriods */
    public function test_admin_creates_canonical_draft_period_without_trusting_publication_or_winners(string $type, string $date, string $start, string $end): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.awards.store'), [
            'period_type' => $type, 'period_date' => $date,
            'published_at' => now(), 'published_by' => 999999, 'status' => 'published',
            'period_start' => '2000-01-01', 'period_end' => '2099-01-01', 'winners' => [['score' => 999]],
        ])->assertSessionHasNoErrors();
        $award = Award::sole();
        $this->assertSame($start, $award->period_start->format('Y-m-d H:i:s'));
        $this->assertSame($end, $award->period_end->format('Y-m-d H:i:s'));
        $this->assertSame('draft', $award->status);
        $this->assertNull($award->published_at);
        $this->assertNull($award->published_by);
        $this->assertDatabaseCount('award_winners', 0);
        $this->get(route('admin.awards.show', $award))->assertOk()->assertSee('Bản nháp — Preview');
    }

    public static function draftPeriods(): array
    {
        return [
            ['weekly', '2026-10-04', '2026-09-28 00:00:00', '2026-10-05 00:00:00'],
            ['monthly', '2026-10-31', '2026-10-01 00:00:00', '2026-11-01 00:00:00'],
        ];
    }

    public function test_creating_same_type_period_opens_existing_award_instead_of_duplicating_or_modifying_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.awards.store'), ['period_type' => 'monthly', 'period_date' => '2026-10-01', 'title' => 'First title']);
        $award = Award::sole();
        $this->post(route('admin.awards.store'), ['period_type' => 'monthly', 'period_date' => '2026-10-31', 'title' => 'Different title'])
            ->assertRedirect(route('admin.awards.show', $award));
        $this->assertDatabaseCount('awards', 1);
        $this->assertSame('First title', $award->fresh()->title);
    }

    public function test_preview_selects_top_three_sales_and_dealers_by_submitted_time_and_leaderboard_ties(): void
    {
        $award = Award::factory()->create(['period_start' => '2026-10-01', 'period_end' => '2026-11-01']);
        $participants = User::factory()->count(4)->active()->create();
        foreach ($participants as $index => $sales) {
            CustomerSubmission::factory()->count(4 - $index)->approved()->create([
                'sales_id' => $sales->id, 'submitted_at' => '2026-10-31 12:00:00', 'reviewed_at' => '2026-11-02 12:00:00',
            ]);
        }
        CustomerSubmission::factory()->count(10)->create(['sales_id' => $participants[3]->id, 'submitted_at' => '2026-10-15 12:00:00']);
        CustomerSubmission::factory()->count(10)->approved()->create(['sales_id' => $participants[3]->id, 'submitted_at' => '2026-11-01 00:00:00']);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.awards.show', $award))->assertOk();
        $preview = $response->viewData('preview');
        $this->assertSame($participants->take(3)->pluck('id')->all(), $preview['sales']->pluck('sales_id')->all());
        $this->assertSame($participants->take(3)->pluck('dealer_id')->all(), $preview['dealers']->pluck('dealer_id')->all());
        $this->assertSame([4, 3, 2], $preview['sales']->pluck('score')->all());
        $this->assertSame([1, 2, 3], $preview['dealers']->pluck('rank')->all());
        $this->assertDatabaseCount('award_winners', 0);
    }

    public function test_preview_fingerprint_changes_when_names_scores_or_winners_change(): void
    {
        $award = Award::factory()->create();
        $submission = CustomerSubmission::factory()->approved()->create();
        $results = app(AwardResults::class);
        $first = $results->fingerprint($award, $results->preview($award));
        $submission->sales->update(['name' => 'Changed name']);
        $this->assertNotSame($first, $results->fingerprint($award, $results->preview($award)));
    }

    public function test_weekly_preview_uses_monday_boundaries_even_when_approved_later(): void
    {
        $award = Award::factory()->weekly()->create(['period_start' => '2026-09-28', 'period_end' => '2026-10-05']);
        $sales = User::factory()->active()->create();
        foreach (['2026-09-28 00:00:00', '2026-10-04 23:59:59', '2026-09-27 23:59:59', '2026-10-05 00:00:00'] as $time) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $time, 'reviewed_at' => '2026-10-10 12:00:00']);
        }
        $preview = app(AwardResults::class)->preview($award);
        $this->assertSame(2, $preview['sales']->first()['score']);
        $this->assertSame(2, $preview['dealers']->first()['score']);
    }

    public function test_award_preview_uses_earliest_achieved_score_for_ties(): void
    {
        $award = Award::factory()->create(['period_start' => '2026-10-01', 'period_end' => '2026-11-01']);
        $late = User::factory()->active()->create();
        $early = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create(['sales_id' => $late->id, 'submitted_at' => '2026-10-02 10:00:00']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $early->id, 'submitted_at' => '2026-10-01 10:00:00']);
        $preview = app(AwardResults::class)->preview($award);
        $this->assertSame([$early->id, $late->id], $preview['sales']->pluck('sales_id')->all());
        $this->assertSame([$early->dealer_id, $late->dealer_id], $preview['dealers']->pluck('dealer_id')->all());
    }

    public function test_public_history_shows_published_snapshots_only_and_hides_drafts_and_customer_data(): void
    {
        $draft = Award::factory()->weekly()->create(['title' => 'Secret draft']);
        $winner = AwardWinner::factory()->create(['winner_name_snapshot' => 'Historic Sales', 'dealer_name_snapshot' => 'Historic Dealer']);
        $award = $winner->award;
        $award->forceFill(['published_at' => now(), 'published_by' => User::factory()->admin()->create()->id])->save();
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => $winner->sales_id, 'customer_name' => 'PrivateCustomerName', 'phone' => 'PrivateCustomerPhone',
            'notes' => 'PrivateCustomerNote', 'facebook_url' => 'https://facebook.com/private.customer',
        ]);
        $winner->sales->update(['name' => 'Live Sales renamed']);
        $winner->sales->dealer->update(['name' => 'Live Dealer renamed']);
        $this->get(route('awards.index'))->assertOk()->assertSee($award->title)->assertDontSee($draft->title);
        $this->get(route('awards.show', $draft))->assertNotFound();
        $this->get(route('awards.show', $award))->assertOk()->assertSee('Historic Sales')->assertSee('Historic Dealer')
            ->assertDontSee('Live Sales renamed')->assertDontSee('Live Dealer renamed')
            ->assertDontSee('PrivateCustomerName')->assertDontSee('PrivateCustomerPhone')->assertDontSee('PrivateCustomerNote')
            ->assertDontSee('https://facebook.com/private.customer');
    }

    public function test_draft_creation_and_preview_require_active_admin(): void
    {
        $award = Award::factory()->create();
        $this->get(route('admin.awards.index'))->assertRedirect(route('login'));
        $this->get(route('admin.awards.show', $award))->assertRedirect(route('login'));
        $this->post(route('admin.awards.store'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->active()->create())->getJson(route('admin.awards.index'))->assertForbidden();
        $this->getJson(route('admin.awards.show', $award))->assertForbidden();
        $this->postJson(route('admin.awards.store'), ['period_type' => 'weekly', 'period_date' => '2026-10-01'])->assertForbidden();
    }

    public function test_invalid_draft_type_date_or_title_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach ([['period_type' => 'daily'], ['period_date' => '2026-02-30'], ['title' => str_repeat('a', 256)]] as $invalid) {
            $this->post(route('admin.awards.store'), array_merge(['period_type' => 'weekly', 'period_date' => '2026-10-01'], $invalid))
                ->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount('awards', 0);
    }

    public function test_public_filter_and_pagination_use_only_published_awards(): void
    {
        $admin = User::factory()->admin()->create();
        for ($index = 0; $index < 13; $index++) {
            $start = now()->startOfMonth()->subMonths($index);
            Award::factory()->create([
                'period_start' => $start, 'period_end' => $start->copy()->addMonth(),
                'published_at' => now(), 'published_by' => $admin->id,
            ]);
        }
        $this->get(route('awards.index', ['period_type' => 'monthly']))->assertOk()
            ->assertViewHas('awards', fn ($items) => $items->total() === 13 && $items->count() === 12);
        $this->get(route('awards.index', ['period_type' => 'monthly', 'page' => 2]))->assertOk()
            ->assertViewHas('awards', fn ($items) => $items->count() === 1);
        $this->get(route('awards.index', ['period_type' => 'weekly']))->assertOk()
            ->assertViewHas('awards', fn ($items) => $items->total() === 0);
    }
}
