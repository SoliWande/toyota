<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class PublicLeaderboardTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    public function test_public_dealer_ranking_paginates_stable_ranks_and_preserves_top_three(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-02-12 12:00:00'));
        $accounts = User::factory()->count(23)->active()->create();
        foreach ($accounts as $account) {
            CustomerSubmission::factory()->approved()->for($account, 'creator')->create([
                'submitted_at' => '2031-02-12', 'customer_name' => 'Private customer', 'phone' => 'Private phone',
                'notes' => 'Private note', 'facebook_url' => 'https://facebook.com/private.customer.'.$account->id,
            ]);
        }
        foreach (['week', 'month', 'all'] as $period) {
            $response = $this->get(route('leaderboard', ['period' => $period, 'dealers_page' => 2]))->assertOk()
                ->assertDontSee('Top Sales')->assertDontSee('Private customer')->assertDontSee('Private phone')
                ->assertDontSee('Private note')->assertDontSee('facebook.com/private.customer')->assertDontSee($accounts[0]->email);
            $rows = $response->viewData('dealers');
            $this->assertGreaterThanOrEqual(23, $rows->total());
            $this->assertSame(21, (int) $rows->first()->rank);
            $this->assertCount(3, $response->viewData('topDealers'));
            $this->assertSame(1, (int) $response->viewData('topDealers')->first()->rank);
        }
        $this->get(route('home'))->assertOk()->assertDontSee('Top Sales')->assertSee($accounts[0]->dealer->name);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()->assertDontSee('Top 3 Sales');
    }

    public function test_empty_period_and_invalid_filters(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-02-12'));
        $this->get(route('leaderboard'))->assertOk()->assertViewHas('dealers', fn ($rows) => $rows->total() === 0);
        foreach ([['period' => 'sales'], ['dealers_page' => -1], ['dealers_page' => 'invalid']] as $invalid) {
            $this->getJson(route('leaderboard', $invalid))->assertUnprocessable();
        }
    }
}
