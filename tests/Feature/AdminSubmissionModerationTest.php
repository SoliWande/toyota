<?php

namespace Tests\Feature;

use App\Actions\RejectCustomerSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AdminSubmissionModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_queue_combines_customer_dealer_sales_status_date_filters_and_pagination(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->create([
            'sales_id' => $sales->id, 'customer_name' => 'Search customer', 'submitted_at' => '2026-10-02 12:00:00',
        ]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'Search approved', 'submitted_at' => '2026-10-02 12:00:00']);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'Search old', 'submitted_at' => '2026-10-01 23:59:59']);
        CustomerSubmission::factory()->create(['sales_id' => User::factory()->active()->create(['dealer_id' => $sales->dealer_id]), 'customer_name' => 'Search other sales', 'submitted_at' => '2026-10-02 12:00:00']);
        CustomerSubmission::factory()->create(['customer_name' => 'Search other dealer', 'submitted_at' => '2026-10-02 12:00:00']);
        $filters = ['q' => 'Search', 'dealer_id' => $sales->dealer_id, 'sales_id' => $sales->id, 'status' => 'pending', 'submitted_from' => '2026-10-02', 'submitted_to' => '2026-10-02'];
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index', $filters))->assertOk()
            ->assertViewHas('submissions', fn ($items) => $items->total() === 21 && $items->count() === 20)
            ->assertDontSee('Search old')->assertDontSee('Search approved')
            ->assertDontSee('Search other sales')->assertDontSee('Search other dealer')
            ->assertSee('submitted_to=2026-10-02')->assertSee('q=Search');
        $this->get(route('admin.submissions.index', [...$filters, 'page' => 2]))
            ->assertViewHas('submissions', fn ($items) => $items->count() === 1);
    }

    public function test_date_filter_includes_both_whole_days_and_uses_submitted_not_reviewed_time(): void
    {
        $first = CustomerSubmission::factory()->create(['submitted_at' => '2026-10-01 00:00:00']);
        $last = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-10-02 23:59:59', 'reviewed_at' => '2026-10-05 12:00:00']);
        CustomerSubmission::factory()->create(['submitted_at' => '2026-09-30 23:59:59']);
        CustomerSubmission::factory()->create(['submitted_at' => '2026-10-03 00:00:00']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index', [
            'status' => '', 'submitted_from' => '2026-10-01', 'submitted_to' => '2026-10-02',
        ]))->assertOk()->assertViewHas('submissions', fn ($items) => $items->modelKeys() === [$first->id, $last->id]);
        $this->get(route('admin.submissions.index', ['status' => '', 'submitted_to' => '2026-10-02']))
            ->assertViewHas('submissions', fn ($items) => $items->total() === 3);
    }

    public function test_search_matches_name_phone_facebook_and_treats_wildcards_literally(): void
    {
        $target = CustomerSubmission::factory()->create(['customer_name' => 'Demo %_ customer', 'phone' => '0901234567', 'facebook_url' => 'https://facebook.com/alice.example']);
        CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        foreach (['Demo', '090123', 'alice.example', '%_'] as $q) {
            $this->get(route('admin.submissions.index', ['q' => $q]))->assertOk()
                ->assertViewHas('submissions', fn ($items) => $items->modelKeys() === [$target->id]);
        }
    }

    public function test_filter_options_include_inactive_dealers_and_sales_but_not_admins(): void
    {
        $dealer = Dealer::factory()->inactive()->create();
        $sales = User::factory()->create(['dealer_id' => $dealer->id, 'status' => UserStatus::Blocked]);
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($admin)->get(route('admin.submissions.index', ['dealer_id' => $dealer->id]))->assertOk()
            ->assertViewHas('dealers', fn ($items) => $items->contains('id', $dealer->id))
            ->assertViewHas('salesOptions', fn ($items) => $items->contains('id', $sales->id) && ! $items->contains('id', $admin->id))
            ->assertViewHas('submissions', fn ($items) => $items->total() === 1);
        $this->get(route('admin.submissions.index', ['sales_id' => $admin->id]))->assertSessionHasErrors('sales_id');
    }

    /** @dataProvider invalidFilters */
    public function test_invalid_filters_are_rejected(array $filters, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index', $filters))->assertSessionHasErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            [['q' => str_repeat('a', 256)], 'q'], [['q' => ['bad']], 'q'],
            [['status' => 'invalid'], 'status'], [['dealer_id' => 999999], 'dealer_id'],
            [['sales_id' => 999999], 'sales_id'], [['page' => 0], 'page'],
            [['submitted_from' => '2026-02-30'], 'submitted_from'], [['submitted_to' => 'invalid'], 'submitted_to'],
            [['submitted_from' => '2026-10-02', 'submitted_to' => '2026-10-01'], 'submitted_to'],
        ];
    }

    public function test_detail_and_quick_review_show_required_data_safe_links_and_confirmation(): void
    {
        $target = CustomerSubmission::factory()->create(['phone' => '0901234567', 'notes' => 'Invited to group']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $target))->assertOk()
            ->assertSee($target->customer_name)->assertSee($target->phone)->assertSee($target->facebook_url)
            ->assertSee($target->sales->name)->assertSee($target->sales->dealer->name)
            ->assertSee($target->submitted_at->format('d/m/Y H:i'))->assertSee($target->notes)
            ->assertSee('Mở Facebook')->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('confirm(', false)->assertSee('data-confirm=', false)
            ->assertSee(route('admin.submissions.reject', $target));
        $this->get(route('admin.submissions.index'))->assertOk()->assertSee('Kiểm tra và duyệt nhanh')
            ->assertSee($target->phone)->assertSee($target->notes)->assertSee('return_to_queue');
    }

    public function test_reject_saves_reviewer_timestamp_reason_note_and_atomic_history_without_changing_submission(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::factory()->admin()->create();
        $target = CustomerSubmission::factory()->create(['submitted_at' => now()->subMonth()]);
        $this->actingAs($admin)->post(route('admin.submissions.reject', $target), [
            'rejection_reason' => 'Membership could not be verified', 'admin_note' => 'Internal note',
            'reviewed_by' => User::factory()->admin()->create()->id, 'reviewed_at' => '2000-01-01',
            'sales_id' => User::factory()->active()->create()->id, 'submitted_at' => '2000-01-01',
            'facebook_url' => 'https://facebook.com/fake', 'customer_name' => 'Fake customer', 'status' => 'approved',
        ])->assertRedirect(route('admin.submissions.show', $target))->assertSessionHasNoErrors();
        $fresh = $target->fresh();
        $this->assertSame(SubmissionStatus::Rejected, $fresh->status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertTrue($fresh->reviewed_at->equalTo(now()));
        $this->assertTrue($fresh->submitted_at->equalTo($target->submitted_at));
        $this->assertSame($target->sales_id, $fresh->sales_id);
        $this->assertSame($target->customer_name, $fresh->customer_name);
        $this->assertSame($target->facebook_url, $fresh->facebook_url);
        $review = $fresh->moderationReviews()->sole();
        $this->assertSame('pending', $review->from_status);
        $this->assertSame('rejected', $review->to_status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertTrue($review->reviewed_at->equalTo($fresh->reviewed_at));
        $this->assertSame($fresh->rejection_reason, $review->rejection_reason);
        $this->assertSame($fresh->admin_note, $review->admin_note);
        $this->get(route('admin.submissions.show', $target))->assertSee('Membership could not be verified')->assertSee('Internal note')
            ->assertDontSee('name="rejection_reason"', false);
        $this->actingAs($target->sales)->get(route('sales.submissions.show', $target))->assertSee('Membership could not be verified')->assertDontSee('Internal note');
    }

    public function test_reject_without_optional_reason_or_note_is_allowed(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.reject', $target))->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Rejected, $target->fresh()->status);
        $this->assertNull($target->fresh()->rejection_reason);
        $this->assertNull($target->fresh()->admin_note);
    }

    /** @dataProvider invalidReviewData */
    public function test_review_input_validation_prevents_mutation(string $action, array $data, string $field): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.'.$action, $target), $data)->assertSessionHasErrors($field);
        $this->assertSame(SubmissionStatus::Pending, $target->fresh()->status);
        $this->assertNull($target->fresh()->reviewed_by);
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public static function invalidReviewData(): array
    {
        return [
            ['reject', ['rejection_reason' => str_repeat('a', 2001)], 'rejection_reason'],
            ['reject', ['rejection_reason' => ['bad']], 'rejection_reason'],
            ['reject', ['admin_note' => str_repeat('a', 2001)], 'admin_note'],
            ['approve', ['admin_note' => ['bad']], 'admin_note'],
            ['reject', ['return_to_queue' => 'invalid'], 'return_to_queue'],
        ];
    }

    /** @dataProvider reviewedStates */
    public function test_reviewed_record_cannot_be_rejected_again_even_with_stale_model(string $state): void
    {
        $admin = User::factory()->admin()->create();
        $stale = CustomerSubmission::factory()->create();
        $reviewed = $stale->fresh();
        $reviewed->forceFill(['status' => $state, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->actingAs($admin)->post(route('admin.submissions.reject', $stale), ['admin_note' => 'Attempted override'])->assertSessionHasErrors('submission');
        $this->assertSame($state, $stale->fresh()->status->value);
        $this->assertNull($stale->fresh()->admin_note);
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RejectCustomerSubmission::class)->execute($admin, $stale);
    }

    public static function reviewedStates(): array
    {
        return [['approved'], ['rejected']];
    }

    public function test_duplicate_cannot_be_approved_in_quick_review_but_admin_can_reject_it(): void
    {
        $approved = CustomerSubmission::factory()->approved()->create();
        $target = CustomerSubmission::factory()->create(['facebook_url' => $approved->facebook_url]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()
            ->assertSee('Facebook profile đã được duyệt')->assertSee(route('admin.submissions.show', $approved))
            ->assertSee('disabled', false);
        $this->post(route('admin.submissions.approve', $target), ['return_to_queue' => 1])->assertSessionHasErrors('duplicate');
        $this->assertSame(SubmissionStatus::Pending, $target->fresh()->status);
        $this->post(route('admin.submissions.reject', $target), ['rejection_reason' => 'Duplicate Facebook profile'])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Rejected, $target->fresh()->status);
        $this->assertSame(SubmissionStatus::Approved, $approved->fresh()->status);
    }

    /** @dataProvider actions */
    public function test_quick_review_keeps_filters_page_and_returns_to_queue(string $action): void
    {
        $target = CustomerSubmission::factory()->create(['customer_name' => 'Queue Customer']);
        $filters = ['q' => 'Queue', 'dealer_id' => $target->sales->dealer_id, 'sales_id' => $target->sales_id,
            'status' => 'pending', 'submitted_from' => now()->toDateString(), 'submitted_to' => now()->toDateString(), 'page' => 2];
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.'.$action, ['submission' => $target, ...$filters]), ['return_to_queue' => 1])
            ->assertRedirect(route('admin.submissions.index', $filters))->assertSessionHasNoErrors();
        $this->assertSame($action === 'approve' ? SubmissionStatus::Approved : SubmissionStatus::Rejected, $target->fresh()->status);
    }

    public static function actions(): array
    {
        return [['approve'], ['reject']];
    }

    public function test_quick_review_preserves_explicit_all_statuses_filter(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.reject', ['submission' => $target, 'status' => '']), ['return_to_queue' => 1])
            ->assertRedirect(route('admin.submissions.index', ['status' => '']))->assertSessionHasNoErrors();
        $this->get(route('admin.submissions.index', ['status' => '']))->assertViewHas('submissions', fn ($items) => $items->total() === 1);
    }

    public function test_reject_endpoints_and_action_require_active_admin_and_public_has_no_customer_data(): void
    {
        $target = CustomerSubmission::factory()->create(['customer_name' => 'PrivateCustomerUnique', 'phone' => 'PrivatePhoneUnique', 'notes' => 'PrivateNoteUnique']);
        $this->get(route('home'))->assertDontSee($target->customer_name)->assertDontSee($target->phone)->assertDontSee($target->notes);
        $this->post(route('admin.submissions.reject', $target))->assertRedirect(route('login'));
        $actors = [User::factory()->active()->create(), User::factory()->create(), User::factory()->admin()->create(['status' => UserStatus::Blocked]), User::factory()->admin()->create(['status' => UserStatus::Rejected]), User::factory()->admin()->create(['status' => UserStatus::Pending])];
        foreach ($actors as $actor) {
            $this->actingAs($actor)->postJson(route('admin.submissions.reject', $target))->assertForbidden()->assertDontSee($target->customer_name);
            try {
                app(RejectCustomerSubmission::class)->execute($actor, $target);
                $this->fail('Action must also authorize directly.');
            } catch (AuthorizationException) {
                $this->assertSame(SubmissionStatus::Pending, $target->fresh()->status);
            }
        }
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public function test_rejection_history_failure_rolls_back_entire_review(): void
    {
        $target = CustomerSubmission::factory()->create();
        $admin = User::factory()->admin()->create();
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('History failed'));
        try {
            app(RejectCustomerSubmission::class)->execute($admin, $target, 'Reason', 'Note');
            $this->fail('History failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History failed', $exception->getMessage());
            $this->assertSame(SubmissionStatus::Pending, $target->fresh()->status);
            $this->assertNull($target->fresh()->reviewed_at);
            $this->assertNull($target->fresh()->rejection_reason);
            $this->assertNull($target->fresh()->admin_note);
            $this->assertDatabaseCount('moderation_reviews', 0);
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }
    }

    public function test_review_content_is_escaped_in_admin_and_sales_views(): void
    {
        $target = CustomerSubmission::factory()->create(['customer_name' => '<script>alert(1)</script>', 'notes' => '<img src=x onerror=alert(1)>']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()
            ->assertSee($target->customer_name)->assertDontSee($target->customer_name, false)
            ->assertSee($target->notes)->assertDontSee($target->notes, false);
        $reason = '<script>alert(2)</script>';
        $this->post(route('admin.submissions.reject', $target), ['rejection_reason' => $reason])->assertSessionHasNoErrors();
        $this->get(route('admin.submissions.show', $target))->assertSee($reason)->assertDontSee($reason, false);
        $this->actingAs($target->sales)->get(route('sales.submissions.show', $target))->assertSee($reason)->assertDontSee($reason, false);
    }
}
