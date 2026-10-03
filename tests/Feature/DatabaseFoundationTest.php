<?php

namespace Tests\Feature;

use App\Enums\AwardPeriod;
use App\Enums\AwardWinnerType;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentAdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh the dedicated toyota_testing MySQL database.');
        }
    }

    public function test_sales_defaults_and_dealer_relationship_and_admin_without_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->for($dealer)->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame(UserRole::Sales, $sales->role);
        $this->assertSame(UserStatus::Pending, $sales->status);
        $this->assertTrue($sales->dealer->is($dealer));
        $this->assertTrue($dealer->sales->first()->is($sales));
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNull($admin->dealer_id);
    }

    public function test_database_requires_sales_to_have_a_dealer(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['dealer_id' => null]);
    }

    public function test_database_rejects_unknown_role(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['role' => 'dealer']);
    }

    public function test_submission_normalizes_url_and_preserves_submitted_date_after_review(): void
    {
        $sales = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        $submission = CustomerSubmission::factory()->for($sales, 'sales')->create([
            'facebook_url' => 'http://m.facebook.com/Alice.Example/?ref=share',
            'submitted_at' => '2026-10-31 12:00:00',
        ]);

        $submission->status = SubmissionStatus::Approved;
        $submission->reviewer()->associate($admin);
        $submission->reviewed_at = '2026-11-02 12:00:00';
        $submission->save();
        $submission->refresh();

        $this->assertSame('https://www.facebook.com/alice.example', $submission->facebook_url_normalized);
        $this->assertSame('2026-10-31', $submission->submitted_at->toDateString());
        $this->assertSame('2026-11-02', $submission->reviewed_at->toDateString());
        $this->assertTrue($submission->sales->is($sales));
        $this->assertTrue($submission->reviewer->is($admin));
        $this->assertTrue($sales->submissions->first()->is($submission));
        $this->assertTrue($admin->reviewedSubmissions->first()->is($submission));
    }

    public function test_database_allows_duplicate_pending_and_rejected_profiles(): void
    {
        $url = 'https://www.facebook.com/profile.php?id=123456';
        CustomerSubmission::factory()->count(2)->create(['facebook_url' => $url]);
        CustomerSubmission::factory()->rejected()->create(['facebook_url' => $url]);
        CustomerSubmission::factory()->approved()->create(['facebook_url' => $url]);

        $this->assertSame(4, CustomerSubmission::where('facebook_url_normalized', $url)->count());
    }

    /** @dataProvider duplicateOwners */
    public function test_database_prevents_second_approval_even_when_bypassing_model_events(bool $sameSales): void
    {
        $first = CustomerSubmission::factory()->approved()->create([
            'facebook_url' => 'https://facebook.com/Alice.Example',
        ]);
        $second = CustomerSubmission::factory()->create([
            'sales_id' => $sameSales ? $first->sales_id : User::factory()->active(),
            'facebook_url' => 'http://m.facebook.com/alice.example/?ref=share',
        ]);

        try {
            DB::table('customer_submissions')->where('id', $second->id)->update([
                'status' => 'approved',
                'reviewed_by' => $first->reviewed_by,
                'reviewed_at' => now(),
            ]);
            $this->fail('Duplicate approval was allowed.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
            $this->assertStringContainsString('submissions_approved_profile_unique', $exception->getMessage());
        }

        $this->assertSame(SubmissionStatus::Pending, $second->fresh()->status);
        $this->assertSame(1, CustomerSubmission::where('status', 'approved')->count());
    }

    public static function duplicateOwners(): array
    {
        return [[true], [false]];
    }

    public function test_database_requires_reviewer_for_approved_submission(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('customer_submissions')->where('id', $submission->id)->update(['status' => 'approved']);
    }

    public function test_admin_cannot_own_a_customer_submission(): void
    {
        $admin = User::factory()->admin()->create();
        $this->expectException(InvalidArgumentException::class);
        CustomerSubmission::factory()->create(['sales_id' => $admin->id]);
    }

    public function test_changing_pending_url_recomputes_identity_and_preserves_submitted_at(): void
    {
        $submission = CustomerSubmission::factory()->create(['submitted_at' => '2026-10-31 12:00:00']);
        $submission->update(['facebook_url' => 'https://m.facebook.com/Updated.Profile?ref=share']);

        $this->assertSame('https://www.facebook.com/updated.profile', $submission->fresh()->facebook_url_normalized);
        $this->assertSame('2026-10-31 12:00:00', $submission->fresh()->submitted_at->format('Y-m-d H:i:s'));
    }

    public function test_privileged_fields_are_not_mass_assignable(): void
    {
        $user = new User(['name' => 'Example', 'role' => 'admin', 'status' => 'active', 'dealer_id' => 999]);
        $submission = new CustomerSubmission(['customer_name' => 'Example', 'status' => 'approved', 'reviewed_by' => 999, 'facebook_url_normalized' => 'forged']);

        $this->assertSame(UserRole::Sales, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNull($user->dealer_id);
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertNull($submission->reviewed_by);
        $this->assertNull($submission->facebook_url_normalized);
    }

    public function test_account_reviewer_must_be_admin(): void
    {
        $sales = User::factory()->create();
        $reviewer = User::factory()->active()->create();
        $this->expectException(InvalidArgumentException::class);
        $sales->reviewed_by = $reviewer->id;
        $sales->reviewed_at = now();
        $sales->save();
    }

    public function test_sales_cannot_be_submission_reviewer(): void
    {
        $sales = User::factory()->active()->create();
        $this->expectException(InvalidArgumentException::class);
        CustomerSubmission::factory()->approved()->create(['reviewed_by' => $sales->id]);
    }

    public function test_referenced_dealer_cannot_be_deleted(): void
    {
        $sales = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('dealers')->where('id', $sales->dealer_id)->delete();
    }

    public function test_awards_support_both_periods_and_both_winner_types(): void
    {
        $monthly = Award::factory()->create();
        $weekly = Award::factory()->weekly()->create();
        $salesWinner = AwardWinner::factory()->for($monthly)->create();
        $dealerWinner = AwardWinner::factory()->dealer()->for($monthly)->create();

        $this->assertSame(AwardPeriod::Monthly, $monthly->period_type);
        $this->assertSame(AwardPeriod::Weekly, $weekly->period_type);
        $this->assertSame(AwardWinnerType::Sales, $salesWinner->winner_type);
        $this->assertSame(AwardWinnerType::Dealer, $dealerWinner->winner_type);
        $this->assertSame(2, $monthly->winners()->count());
        $this->assertTrue($salesWinner->sales->awardWins->first()->is($salesWinner));
        $this->assertTrue($dealerWinner->dealer->awardWins->first()->is($dealerWinner));
    }

    public function test_published_snapshot_survives_live_name_and_score_changes(): void
    {
        $winner = AwardWinner::factory()->create(['score' => 12]);
        $award = $winner->award;
        $name = $winner->winner_name_snapshot;
        $dealerName = $winner->dealer_name_snapshot;
        $dealerCode = $winner->dealer_code_snapshot;
        $award->published_by = User::factory()->admin()->create()->id;
        $award->published_at = now();
        $award->save();

        $winner->sales->update(['name' => 'Renamed sales']);
        $winner->sales->dealer->update(['name' => 'Renamed dealer', 'code' => 'RENAMED']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $winner->sales_id]);
        $winner->refresh();

        $this->assertSame($name, $winner->winner_name_snapshot);
        $this->assertSame($dealerName, $winner->dealer_name_snapshot);
        $this->assertSame($dealerCode, $winner->dealer_code_snapshot);
        $this->assertSame(12, $winner->score);
        $this->assertTrue($award->publisher->publishedAwards->first()->is($award));
    }

    public function test_published_winner_cannot_be_updated(): void
    {
        $winner = AwardWinner::factory()->create();
        $winner->award->forceFill(['published_by' => User::factory()->admin()->create()->id, 'published_at' => now()])->save();

        $this->expectException(LogicException::class);
        $winner->score = 999;
        $winner->save();
    }

    public function test_published_award_cannot_be_changed(): void
    {
        $award = Award::factory()->create();
        $award->forceFill(['published_by' => User::factory()->admin()->create()->id, 'published_at' => now()])->save();

        $this->expectException(LogicException::class);
        $award->update(['title' => 'Changed result']);
    }

    public function test_award_publisher_must_be_admin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Award::factory()->create(['published_by' => User::factory()->active(), 'published_at' => now()]);
    }

    public function test_admin_cannot_be_sales_award_winner(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        AwardWinner::factory()->create([
            'sales_id' => $admin->id,
            'winner_name_snapshot' => $admin->name,
            'sales_dealer_id_snapshot' => $dealer->id,
            'dealer_name_snapshot' => $dealer->name,
            'dealer_code_snapshot' => $dealer->code,
        ]);
    }

    public function test_database_prevents_duplicate_rank_per_winner_type(): void
    {
        $award = Award::factory()->create();
        AwardWinner::factory()->for($award)->create();
        $this->expectException(QueryException::class);
        AwardWinner::factory()->for($award)->create();
    }

    public function test_database_rejects_rank_outside_top_three(): void
    {
        $this->expectException(QueryException::class);
        AwardWinner::factory()->create(['rank' => 4]);
    }

    public function test_database_rejects_invalid_period(): void
    {
        $this->expectException(QueryException::class);
        Award::factory()->create(['period_end' => '2000-01-01 00:00:00']);
    }

    public function test_database_requires_exactly_one_award_winner_subject(): void
    {
        $this->expectException(QueryException::class);
        AwardWinner::factory()->create(['dealer_id' => Dealer::factory()]);
    }

    public function test_review_history_relationships_and_append_only_protection(): void
    {
        $submissionReview = ModerationReview::factory()->create();
        $salesReview = ModerationReview::factory()->sales()->create();

        $this->assertTrue($submissionReview->submission->moderationReviews->first()->is($submissionReview));
        $this->assertTrue($salesReview->sales->moderationReviews->first()->is($salesReview));
        $this->assertTrue($submissionReview->reviewer->performedReviews->first()->is($submissionReview));

        $this->expectException(LogicException::class);
        $submissionReview->delete();
    }

    public function test_database_rejects_review_with_two_subjects(): void
    {
        $this->expectException(QueryException::class);
        ModerationReview::factory()->create(['sales_id' => User::factory()]);
    }

    public function test_history_reviewer_must_be_admin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ModerationReview::factory()->create(['reviewed_by' => User::factory()->active()]);
    }

    public function test_development_seed_is_idempotent_and_does_not_overwrite_password(): void
    {
        config()->set('development.admin.email', 'seed-admin@example.test');
        config()->set('development.admin.password', 'testing-only-credential');
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'seed-admin@example.test')->sole();
        $originalPassword = $admin->password;
        config()->set('development.admin.password', 'different-testing-credential');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertTrue(Hash::check('testing-only-credential', $originalPassword));
        $this->assertSame($originalPassword, $admin->fresh()->password);
        $this->assertSame(1, User::count());
        $this->assertSame(1, Dealer::where('code', 'DEV-TOYOTA')->count());
    }

    public function test_development_admin_seeder_refuses_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->expectException(LogicException::class);
        $this->seed(DevelopmentAdminSeeder::class);
    }

    public function test_seeder_cannot_promote_existing_sales_account(): void
    {
        $sales = User::factory()->create();
        config()->set('development.admin.email', $sales->email);
        config()->set('development.admin.password', 'testing-only-credential');
        $this->expectException(LogicException::class);
        $this->seed(DevelopmentAdminSeeder::class);
    }

    public function test_development_seeder_requires_explicit_credentials(): void
    {
        config()->set('development.admin.email', null);
        config()->set('development.admin.password', null);
        $this->expectException(InvalidArgumentException::class);
        $this->seed(DevelopmentAdminSeeder::class);
    }
}
