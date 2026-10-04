<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class DevelopmentDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['development.demo_password' => 'testing-only-demo-password']);
        Storage::fake('submission_evidence');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00', config('app.timezone')));
    }

    public function test_dataset_supports_dashboards_rankings_moderation_and_immutable_award_history(): void
    {
        $admin = User::factory()->admin()->create();
        $oldPassword = $admin->password;
        $existingDealer = Dealer::factory()->inactive()->create();
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame($oldPassword, $admin->fresh()->password);
        $this->assertFalse($existingDealer->fresh()->is_active);
        $this->assertDatabaseCount('users', 51);
        $this->assertDatabaseCount('customer_submissions', 500);
        $this->assertSame(['active' => 37, 'pending' => 8, 'rejected' => 3, 'blocked' => 3], User::select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(['pending' => 150, 'approved' => 300, 'rejected' => 50], CustomerSubmission::select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(5, DB::table('customer_submissions')->selectRaw("COUNT(DISTINCT DATE_FORMAT(submitted_at, '%Y-%m')) AS total")->value('total'));
        $this->assertDatabaseCount('moderation_reviews', 395);
        $this->assertCount(500, Storage::disk('submission_evidence')->allFiles());
        $this->assertSame(0, CustomerSubmission::where('submitted_at', '>', now())->count());
        $this->assertSame(0, CustomerSubmission::whereNotNull('reviewed_at')->whereColumn('reviewed_at', '<', 'submitted_at')->count());
        $sales = User::where('email', 'demo.sales.01@example.test')->sole();
        $this->assertTrue(Hash::check('testing-only-demo-password', $sales->password));
        $service = app(LeaderboardService::class);
        foreach (LeaderboardPeriod::cases() as $period) {
            $rows = $service->sales($period)->get();
            $this->assertGreaterThan(3, $rows->count());
            $this->assertGreaterThan($rows->last()->score, $rows->first()->score);
            $this->get(route('leaderboard', ['period' => $period->value]))->assertOk();
        }
        $this->get(route('leaderboard', ['period' => 'all', 'sales_page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($items) => $items->first()->rank === 21);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['approved_submissions'] === 300 && $stats['pending_submissions'] === 150);
        $queue = $this->get(route('admin.submissions.index'))->assertOk();
        $this->assertSame(150, $queue->viewData('submissions')->total());
        $this->assertCount(20, $queue->viewData('submissions'));
        $this->get(route('admin.submissions.index', ['page' => 2]))->assertOk();
        $duplicate = CustomerSubmission::where('status', 'pending')->where('facebook_url_normalized', 'https://www.facebook.com/demo.toyota.customer.0001')->sole();
        $this->get(route('admin.submissions.show', $duplicate))->assertOk()->assertViewHas('duplicate', fn ($row) => $row !== null);
        $this->get(route('admin.sales.index'))->assertOk();
        $this->get(route('admin.awards.index'))->assertOk();
        $this->assertSame(2, Award::where('period_type', 'weekly')->whereNotNull('published_at')->count());
        $this->assertSame(2, Award::where('period_type', 'monthly')->whereNotNull('published_at')->count());
        $this->assertDatabaseCount('award_winners', 24);
        $this->get(route('awards.index'))->assertOk()->assertViewHas('awards', fn ($items) => $items->total() === 4);
        foreach (Award::with('winners')->get() as $award) {
            $this->get(route('awards.show', $award))->assertOk()->assertSee('[DEMO]');
            $this->get(route('admin.awards.show', $award))->assertOk();
            $preview = app(AwardResults::class)->preview(Award::factory()->make([
                'period_type' => $award->period_type, 'period_start' => $award->period_start, 'period_end' => $award->period_end,
            ]));
            $this->assertSame($preview['sales']->pluck('score')->all(), $award->winners->where('winner_type', \App\Enums\AwardWinnerType::Sales)->sortBy('rank')->pluck('score')->all());
        }
        $this->post(route('logout'));
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk();
        $this->get(route('sales.submissions.index'))->assertOk()->assertViewHas('submissions', fn ($items) => $items->total() === 40);
        $submission = $sales->submissions()->first();
        $this->get(route('sales.submissions.show', $submission))->assertOk()->assertSee('DEMO-');
        $this->get(route('submissions.evidence', $submission))->assertOk()->assertHeader('Content-Type', 'image/png');
        $before = [User::count(), CustomerSubmission::count(), Award::count(), AwardWinner::count(), ModerationReview::count()];
        $snapshots = AwardWinner::orderBy('id')->get()->map->getRawOriginal()->all();
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame($before, [User::count(), CustomerSubmission::count(), Award::count(), AwardWinner::count(), ModerationReview::count()]);
        $sales->update(['name' => '[DEMO] Renamed after publication']);
        $this->assertSame($snapshots, AwardWinner::orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_demo_seeder_refuses_production_before_writing_anything(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->expectException(LogicException::class);
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_missing_demo_password_does_not_create_data(): void
    {
        config(['development.demo_password' => null]);
        try {
            $this->seed(DevelopmentDemoSeeder::class);
            $this->fail('Missing password must reject seeding.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('DEV_DEMO_PASSWORD', $error->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_partial_or_colliding_reserved_accounts_are_never_overwritten(): void
    {
        $user = User::factory()->admin()->create(['email' => 'demo.sales.01@example.test']);
        try {
            $this->seed(DevelopmentDemoSeeder::class);
            $this->fail('Conflicting demo accounts must reject seeding.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('Partial or conflicting', $error->getMessage());
        }
        $this->assertSame('admin', $user->fresh()->role->value);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_submissions', 0);
    }

    public function test_failed_seed_rolls_back_records_and_removes_only_new_images(): void
    {
        Storage::disk('submission_evidence')->put('existing.png', 'existing file');
        CustomerSubmission::creating(fn () => throw new RuntimeException('Simulated seed failure.'));
        try {
            $this->seed(DevelopmentDemoSeeder::class);
            $this->fail('Simulated failure must abort seeding.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated seed failure.', $error->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('dealers', 0);
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->assertSame(['existing.png'], Storage::disk('submission_evidence')->allFiles());
    }
}
