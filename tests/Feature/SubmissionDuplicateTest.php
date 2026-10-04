<?php

namespace Tests\Feature;

use App\Actions\ApproveCustomerSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SubmissionDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_review_warns_with_previous_sales_dealer_and_record_link(): void
    {
        $approved = CustomerSubmission::factory()->approved()->create(['facebook_url' => 'https://facebook.com/alice.example']);
        $pending = CustomerSubmission::factory()->create(['facebook_url' => 'http://m.facebook.com/Alice.Example/?ref=share#about']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $pending))->assertOk()
            ->assertSee('Facebook profile đã được duyệt trước đó')
            ->assertSee($approved->sales->name)->assertSee($approved->sales->dealer->name)
            ->assertSee($approved->sales->dealer->code)->assertSee(route('admin.submissions.show', $approved))
            ->assertSee('disabled', false)->assertViewHas('duplicate', fn ($record) => $record->id === $approved->id);
        $this->get(route('admin.submissions.show', $approved))->assertOk()->assertViewHas('duplicate', null);
    }

    /** @dataProvider duplicateProfiles */
    public function test_normalized_duplicates_cannot_be_approved(string $firstUrl, string $newUrl, bool $sameSales): void
    {
        $approved = CustomerSubmission::factory()->approved()->create(['facebook_url' => $firstUrl]);
        $pending = CustomerSubmission::factory()->create([
            'facebook_url' => $newUrl,
            'sales_id' => $sameSales ? $approved->sales_id : User::factory()->active(),
        ]);
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.submissions.show', $pending))
            ->post(route('admin.submissions.approve', $pending), ['admin_note' => 'Should not be saved'])
            ->assertRedirect(route('admin.submissions.show', $pending))->assertSessionHasErrors('duplicate');
        $this->assertPendingWithoutReview($pending);
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->assertSame(SubmissionStatus::Approved, $approved->fresh()->status);
    }

    public static function duplicateProfiles(): array
    {
        return [
            ['https://facebook.com/alice.example', 'https://facebook.com/alice.example', false],
            ['https://facebook.com/alice.example', 'http://m.facebook.com/Alice.Example/?ref=share#about', false],
            ['https://www.facebook.com/profile.php?id=123456', 'https://mbasic.facebook.com/profile.php?ref=share&id=00123456#about', false],
            ['https://facebook.com/alice.example', 'https://web.facebook.com/ALICE.Example', true],
        ];
    }

    public function test_approve_rechecks_after_review_page_was_opened_and_normalizes_current_raw_url(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = CustomerSubmission::factory()->create(['facebook_url' => 'https://facebook.com/alice.example']);
        $this->actingAs($admin)->get(route('admin.submissions.show', $pending))->assertOk()->assertViewHas('duplicate', null);
        $approved = CustomerSubmission::factory()->approved()->create(['facebook_url' => 'https://facebook.com/alice.example']);
        // Even a stale/corrupt stored normalized value must not bypass the raw URL recheck.
        DB::table('customer_submissions')->where('id', $pending->id)->update(['facebook_url_normalized' => 'https://www.facebook.com/different']);
        $this->from(route('admin.submissions.show', $pending))->post(route('admin.submissions.approve', $pending), [
            'facebook_url' => 'https://facebook.com/fake', 'facebook_url_normalized' => 'https://facebook.com/fake',
        ])->assertSessionHasErrors('duplicate');
        $this->assertPendingWithoutReview($pending);
        $this->get(route('admin.submissions.show', $pending))->assertSee(route('admin.submissions.show', $approved));
    }

    public function test_same_name_and_phone_with_different_facebook_can_both_be_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $first = CustomerSubmission::factory()->create(['customer_name' => 'Same Customer', 'phone' => '0901234567', 'submitted_at' => now()->subMonth()]);
        $second = CustomerSubmission::factory()->create(['customer_name' => $first->customer_name, 'phone' => $first->phone]);
        $this->actingAs($admin)->post(route('admin.submissions.approve', $first), ['admin_note' => 'Verified manually'])
            ->assertRedirect(route('admin.submissions.show', $first))->assertSessionHasNoErrors();
        $this->get(route('admin.submissions.show', $second))->assertViewHas('duplicate', null);
        $this->post(route('admin.submissions.approve', $second))->assertSessionHasNoErrors();
        $this->assertSame(2, CustomerSubmission::where('status', 'approved')->count());
        $fresh = $first->fresh();
        $this->assertSame($first->sales_id, $fresh->sales_id);
        $this->assertTrue($first->submitted_at->equalTo($fresh->submitted_at));
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertSame('Verified manually', $fresh->admin_note);
        $review = $fresh->moderationReviews()->sole();
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertSame('pending', $review->from_status);
        $this->assertSame('approved', $review->to_status);
        $this->assertTrue($review->reviewed_at->equalTo($fresh->reviewed_at));
    }

    public function test_pending_and_rejected_duplicates_do_not_block_approval(): void
    {
        $url = 'https://facebook.com/alice.example';
        CustomerSubmission::factory()->create(['facebook_url' => $url]);
        CustomerSubmission::factory()->rejected()->create(['facebook_url' => $url]);
        $target = CustomerSubmission::factory()->create(['facebook_url' => $url]);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.approve', $target))->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Approved, $target->fresh()->status);
    }

    public function test_repeated_approval_or_rejected_record_does_not_create_more_history(): void
    {
        $admin = User::factory()->admin()->create();
        $target = CustomerSubmission::factory()->create();
        $this->actingAs($admin)->post(route('admin.submissions.approve', $target))->assertSessionHasNoErrors();
        $this->post(route('admin.submissions.approve', $target))->assertSessionHasErrors('submission');
        $rejected = CustomerSubmission::factory()->rejected()->create();
        $this->post(route('admin.submissions.approve', $rejected))->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('moderation_reviews', 1);
        $this->assertSame(SubmissionStatus::Rejected, $rejected->fresh()->status);
    }

    public function test_authorization_protects_every_admin_endpoint_and_action(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $this->get(route('admin.submissions.index'))->assertRedirect(route('login'));
        $this->get(route('admin.submissions.show', $submission))->assertRedirect(route('login'));
        $this->post(route('admin.submissions.approve', $submission))->assertRedirect(route('login'));
        $actors = [User::factory()->active()->create(), User::factory()->create()];
        foreach ([UserStatus::Pending, UserStatus::Rejected, UserStatus::Blocked] as $status) {
            $actors[] = User::factory()->admin()->create(['status' => $status]);
        }
        foreach ($actors as $actor) {
            $this->actingAs($actor)->getJson(route('admin.submissions.index'))->assertForbidden();
            $this->getJson(route('admin.submissions.show', $submission))->assertForbidden();
            $this->postJson(route('admin.submissions.approve', $submission))->assertForbidden();
            try {
                app(ApproveCustomerSubmission::class)->execute($actor, $submission);
                $this->fail('Action must authorize even outside HTTP.');
            } catch (AuthorizationException) {
                $this->assertPendingWithoutReview($submission);
            }
        }
    }

    public function test_admin_queue_defaults_to_pending_and_paginates(): void
    {
        CustomerSubmission::factory()->count(21)->create();
        $approved = CustomerSubmission::factory()->approved()->create();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()
            ->assertDontSee($approved->customer_name)
            ->assertViewHas('submissions', fn ($items) => $items->total() === 21 && $items->count() === 20);
        $this->get(route('admin.submissions.index', ['status' => 'approved']))->assertOk()->assertSee($approved->customer_name);
        $this->get(route('admin.submissions.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_invalid_note_prevents_approval_and_request_cannot_change_owner_time_or_url(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.approve', $target), ['admin_note' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('admin_note');
        $this->assertPendingWithoutReview($target);
        $this->post(route('admin.submissions.approve', $target), [
            'sales_id' => User::factory()->active()->create()->id, 'submitted_at' => '2000-01-01',
            'facebook_url' => 'https://facebook.com/fake', 'status' => 'rejected',
        ])->assertSessionHasNoErrors();
        $this->assertSame($target->sales_id, $target->fresh()->sales_id);
        $this->assertSame($target->facebook_url, $target->fresh()->facebook_url);
        $this->assertTrue($target->submitted_at->equalTo($target->fresh()->submitted_at));
    }

    public function test_database_unique_constraint_rejects_duplicate_even_when_application_check_is_bypassed(): void
    {
        $approved = CustomerSubmission::factory()->approved()->create();
        $pending = CustomerSubmission::factory()->create(['facebook_url' => $approved->facebook_url]);
        try {
            DB::transaction(fn () => DB::table('customer_submissions')->where('id', $pending->id)->update([
                'status' => 'approved', 'reviewed_by' => $approved->reviewed_by, 'reviewed_at' => now(),
            ]));
            $this->fail('Database must enforce approved profile uniqueness.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
            $this->assertStringContainsString('submissions_approved_profile_unique', $exception->errorInfo[2]);
            $this->assertPendingWithoutReview($pending);
        }
    }

    public function test_history_failure_rolls_back_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = CustomerSubmission::factory()->create();
        $originalDispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $originalDispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('Simulated history failure'));
        try {
            app(ApproveCustomerSubmission::class)->execute($admin, $pending);
            $this->fail('Failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated history failure', $exception->getMessage());
            $this->assertPendingWithoutReview($pending);
            $this->assertDatabaseCount('moderation_reviews', 0);
        } finally {
            ModerationReview::setEventDispatcher($originalDispatcher);
        }
    }

    private function assertPendingWithoutReview(CustomerSubmission $submission): void
    {
        $fresh = $submission->fresh();
        $this->assertSame(SubmissionStatus::Pending, $fresh->status);
        foreach (['reviewed_by', 'reviewed_at', 'admin_note', 'rejection_reason'] as $field) {
            $this->assertNull($fresh->$field);
        }
    }
}
