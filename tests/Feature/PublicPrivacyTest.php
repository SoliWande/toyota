<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function publishedAward(): array
    {
        $sales = User::factory()->active()->create([
            'name' => 'PRIVATE_SALES_NAME_8CF19', 'email' => 'private-sales-8cf19@example.test', 'phone' => 'PRIVATE_SALES_PHONE_8CF19',
        ]);
        $submission = CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'PRIVATE_CUSTOMER_8CF19', 'notes' => 'PRIVATE_NOTES_8CF19',
        ]);
        $award = Award::factory()->create(['title' => 'PRIVATE_AWARD_TITLE_8CF19']);
        AwardWinner::factory()->create([
            'award_id' => $award->id, 'sales_id' => $sales->id, 'winner_name_snapshot' => 'PRIVATE_SALES_SNAPSHOT_8CF19',
        ]);
        AwardWinner::factory()->dealer()->create([
            'award_id' => $award->id, 'dealer_id' => $sales->dealer_id,
            'dealer_name_snapshot' => 'Public historic Dealer', 'dealer_code_snapshot' => 'PUBLIC-DEALER',
            // Public output uses the Dealer snapshot, never this free-form internal field.
            'winner_name_snapshot' => 'PRIVATE_INTERNAL_WINNER_FIELD_8CF19',
        ]);
        $award->forceFill(['published_at' => now(), 'published_by' => $submission->reviewed_by])->save();

        return [$sales, $submission, $award];
    }

    public function test_public_html_and_json_requests_never_expose_sales_or_internal_award_data(): void
    {
        [$sales, $submission, $award] = $this->publishedAward();
        $urls = [route('home'), route('home', ['period' => 'month']),
            route('leaderboard'), route('leaderboard', ['period' => 'month']), route('leaderboard', ['period' => 'all']),
            route('awards.index'), route('awards.index', ['period_type' => 'monthly']), route('awards.show', $award),
            route('dealers.index'), app(DealerProfile::class)->url($sales->dealer_id),
            route('login'), route('register'), route('password.request'), route('password.reset', ['token' => 'test-token'])];

        foreach ([false, true] as $json) {
            foreach ($urls as $url) {
                $response = ($json ? $this->getJson($url) : $this->get($url))->assertOk();
                $privateFields = [$sales->name, $sales->email, $sales->phone, $submission->customer_name, $submission->notes,
                    'PRIVATE_SALES_SNAPSHOT_8CF19', 'PRIVATE_AWARD_TITLE_8CF19', 'PRIVATE_INTERNAL_WINNER_FIELD_8CF19',
                    'Top Sales', 'Top 3 Sales'];
                foreach ($privateFields as $private) {
                    $response->assertDontSee($private);
                }
                $response->assertViewMissing('sales')->assertViewMissing('topSales');
                foreach (['stats', 'topDealers', 'latestAward', 'dealers', 'dealer', 'standing', 'awards', 'award'] as $key) {
                    $data = $response->viewData()[$key] ?? null;
                    $serialized = json_encode($data, JSON_THROW_ON_ERROR);
                    foreach (['sales_id', 'sales_dealer_id_snapshot', 'published_by', 'PRIVATE_', $sales->email] as $private) {
                        $this->assertStringNotContainsString($private, $serialized, $url.' / '.$key);
                    }
                }
            }
        }
    }

    public function test_public_awards_select_only_dealer_fields_while_admin_retains_sales_snapshots(): void
    {
        [$sales, $submission, $award] = $this->publishedAward();
        $public = $this->get(route('awards.show', $award))->assertOk()->assertSee('Public historic Dealer')->viewData('award');
        $this->assertSame(['id', 'period_type', 'period_start', 'period_end', 'published_at'], array_keys($public->getAttributes()));
        $this->assertCount(1, $public->winners);
        $winner = $public->winners->sole();
        $this->assertSame(['award_id', 'dealer_id', 'rank', 'score', 'dealer_name_snapshot', 'dealer_code_snapshot', 'winner_name_snapshot'], array_keys($winner->getAttributes()));
        $this->assertSame('Public historic Dealer', $winner->winner_name_snapshot);
        $this->assertFalse($winner->relationLoaded('sales'));

        $admin = User::findOrFail($submission->reviewed_by);
        $this->actingAs($admin)->get(route('admin.awards.show', $award))->assertOk()->assertSee('PRIVATE_SALES_SNAPSHOT_8CF19')
            ->assertViewHas('award', fn ($item) => $item->winners->count() === 2);
        $this->get(route('admin.sales.show', $sales))->assertOk()->assertSee($sales->name);
    }

    public function test_sales_only_awards_and_drafts_are_not_public_even_with_guessed_ids(): void
    {
        $winner = AwardWinner::factory()->create(['winner_name_snapshot' => 'PRIVATE_SALES_ONLY']);
        $winner->award->forceFill(['published_at' => now(), 'published_by' => User::factory()->admin()->create()->id])->save();
        $draftWinner = AwardWinner::factory()->dealer()->create(['award_id' => Award::factory()->weekly()]);

        $this->get(route('home'))->assertOk()->assertViewHas('latestAward', null)->assertDontSee('PRIVATE_SALES_ONLY');
        $this->get(route('awards.index'))->assertOk()->assertViewHas('awards', fn ($items) => $items->total() === 0);
        foreach ([$winner->award_id, $draftWinner->award_id] as $id) {
            $this->get(route('awards.show', $id))->assertNotFound();
            $this->getJson(route('awards.show', $id))->assertNotFound()->assertDontSee('PRIVATE_SALES_ONLY');
        }
    }

    public function test_logging_in_does_not_turn_public_pages_into_sales_rankings(): void
    {
        [$sales, $submission, $award] = $this->publishedAward();
        foreach ([$sales, User::findOrFail($submission->reviewed_by)] as $user) {
            $this->actingAs($user);
            foreach ([route('home'), route('leaderboard'), route('awards.index'), route('awards.show', $award)] as $url) {
                $this->get($url)->assertOk()->assertDontSee($sales->name)->assertDontSee($sales->email)
                    ->assertDontSee('PRIVATE_SALES_SNAPSHOT_8CF19')->assertDontSee('Top Sales')
                    ->assertViewMissing('sales')->assertViewMissing('topSales');
            }
        }
    }

    public function test_guest_cannot_retrieve_sales_submissions_evidence_or_exports(): void
    {
        [$sales, $submission] = $this->publishedAward();
        foreach ([route('admin.sales.index'), route('admin.sales.show', $sales), route('admin.sales.edit', $sales),
            route('sales.dashboard'), route('sales.submissions.index'), route('sales.submissions.show', $submission),
            route('submissions.evidence', $submission), route('sales.avatar', $sales), route('profile.edit'), '/api/user'] as $url) {
            $this->get($url)->assertRedirect(route('login'))->assertDontSee($sales->email);
            $this->getJson($url)->assertUnauthorized()->assertDontSee($sales->name)->assertDontSee($sales->email);
        }
        foreach (['/api/sales', '/api/leaderboard', '/leaderboard/export', '/sales/export', '/awards/'.$submission->id.'/export'] as $url) {
            $response = $this->getJson($url);
            $this->assertContains($response->status(), [401, 404]);
            $response->assertDontSee($sales->name)->assertDontSee($sales->email);
        }
        $this->patchJson(route('admin.sales.update', $sales), ['name' => 'Unauthorized change'])->assertUnauthorized();
        $this->postJson(route('admin.sales.review', [$sales, 'block']))->assertUnauthorized();
        $this->postJson(route('sales.submissions.store'), [])->assertUnauthorized();
        $this->assertSame($sales->name, $sales->fresh()->name);
    }

    public function test_public_pagination_ignores_sales_selectors_and_does_not_echo_private_query_parameters(): void
    {
        CustomerSubmission::factory()->count(23)->approved()->create();
        $response = $this->get(route('leaderboard', [
            'period' => 'all', 'sales_page' => 2, 'type' => 'sales', 'include' => 'sales', 'format' => 'json', 'email' => 'PRIVATE_QUERY_EMAIL',
        ]))->assertOk()->assertViewMissing('sales')->assertViewMissing('topSales');
        $this->assertSame(23, $response->viewData('dealers')->total());
        foreach (['sales_page', 'PRIVATE_QUERY_EMAIL', 'include=sales', 'type=sales'] as $query) {
            $response->assertDontSee($query);
        }

        [$sales, $submission, $award] = $this->publishedAward();
        $this->get(route('awards.index', ['email' => 'PRIVATE_QUERY_EMAIL', 'sales_id' => $sales->id]))
            ->assertOk()->assertDontSee('PRIVATE_QUERY_EMAIL')->assertDontSee('sales_id');
    }

    public function test_dealer_points_stay_with_submission_dealer_and_sales_workflow_remains_private(): void
    {
        Storage::fake('submission_evidence');
        $sales = User::factory()->active()->create();
        $oldDealer = $sales->dealer;
        $old = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        $newDealer = Dealer::factory()->create();
        $admin = User::findOrFail($old->reviewed_by);
        $this->actingAs($admin)->patch(route('admin.sales.update', $sales), [
            'name' => $sales->name, 'dealer_id' => $newDealer->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($sales->fresh())->post(route('sales.submissions.store'), [
            'customer_name' => 'PRIVATE_NEW_CUSTOMER', 'facebook_url' => 'https://www.facebook.com/new.private.customer',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2024, 'vehicle_color' => 'White',
            'license_plate' => '29D-428.12', 'evidence_image' => UploadedFile::fake()->image('evidence.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $new = CustomerSubmission::where('customer_name', 'PRIVATE_NEW_CUSTOMER')->sole();
        $this->assertSame($newDealer->id, $new->dealer_id);
        $this->actingAs($admin)->post(route('admin.submissions.approve', $new))->assertSessionHasNoErrors();
        $this->actingAs($sales->fresh())->get(route('sales.dashboard'))->assertOk()
            ->assertViewHas('currentRank', 1);
        $this->post(route('logout'));

        $rows = $this->get(route('leaderboard', ['period' => 'all']))->assertOk()->assertDontSee($sales->name)->viewData('dealers');
        $this->assertSame(1, (int) $rows->firstWhere('id', $oldDealer->id)->score);
        $this->assertSame(1, (int) $rows->firstWhere('id', $newDealer->id)->score);
        $this->get(route('home'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['points'] === 2);
        $this->assertSame($oldDealer->id, $old->fresh()->dealer_id);
    }

    public function test_all_public_get_routes_are_dealer_pages_account_forms_or_framework_helpers(): void
    {
        $public = collect(Route::getRoutes())->filter(fn ($route) => in_array('GET', $route->methods(), true)
            && ! collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'auth')))
            ->map(fn ($route) => $route->uri())->sort()->values()->all();
        $expected = ['/', 'leaderboard', 'dealers', 'dealers/{dealer}', 'awards', 'awards/{award}', 'login', 'register', 'forgot-password', 'reset-password/{token}',
            'dealers/{dealer}/qr', 'dealers/{dealer}/photo', '{slug}', '{slug}/address/wards/{province}', 'sales/{slug}', 'sales/{slug}/avatar',
            'sanctum/csrf-cookie', '_ignition/health-check'];
        sort($expected);
        $this->assertSame($expected, $public);
    }
}
