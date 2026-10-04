<?php

namespace Tests\Feature;

use App\Actions\ReviewSalesAccount;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AdminSalesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_admin_list_excludes_admin_accounts_and_paginates(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(23)->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.sales.index'))->assertOk()
            ->assertDontSee($otherAdmin->email)->assertViewHas('sales', fn ($sales) => $sales->total() === 23 && $sales->count() === 20);
        $this->get(route('admin.sales.index', ['page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->count() === 3);
    }

    /** @dataProvider salesStatuses */
    public function test_admin_can_filter_every_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        foreach (UserStatus::cases() as $state) {
            User::factory()->create(['status' => $state]);
        }
        $this->actingAs($admin)->get(route('admin.sales.index', ['status' => $status]))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->total() === 1 && $sales->first()->status->value === $status);
    }

    public static function salesStatuses(): array
    {
        return [['pending'], ['active'], ['rejected'], ['blocked']];
    }

    /** @dataProvider searchableFields */
    public function test_admin_can_search_name_email_and_phone(string $field, string $value, string $search): void
    {
        $admin = User::factory()->admin()->create();
        $match = User::factory()->create([$field => $value]);
        User::factory()->create();
        $this->actingAs($admin)->get(route('admin.sales.index', ['q' => $search]))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->total() === 1 && $sales->first()->is($match));
    }

    public static function searchableFields(): array
    {
        return [
            ['name', 'Nguyễn Minh Anh', 'Minh Anh'],
            ['email', 'search-target@example.test', 'SEARCH-TARGET'],
            ['phone', '0901234567', '012345'],
            ['name', 'Literal % sign', '%'],
            ['name', 'Literal _ sign', '_'],
        ];
    }

    public function test_combined_filters_preserve_query_on_pagination_and_include_inactive_dealers(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->inactive()->create();
        User::factory()->count(21)->for($dealer)->create(['status' => 'pending', 'name' => 'Matching Sales']);
        User::factory()->for($dealer)->active()->create(['name' => 'Matching Active']);
        User::factory()->create(['name' => 'Matching Other Dealer']);
        $filters = ['dealer_id' => $dealer->id, 'status' => 'pending', 'q' => 'Matching'];
        $this->actingAs($admin)->get(route('admin.sales.index', $filters))->assertOk()
            ->assertSee($dealer->name)
            ->assertViewHas('sales', function ($sales) use ($dealer) {
                return $sales->total() === 21 && str_contains($sales->nextPageUrl(), 'dealer_id='.$dealer->id)
                    && str_contains($sales->nextPageUrl(), 'status=pending') && str_contains($sales->nextPageUrl(), 'q=Matching');
            });
    }

    public function test_details_show_phone_dealer_and_review_history(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create(['phone' => '0901234567']);
        $this->actingAs($admin)->from(route('admin.sales.show', $sales))
            ->post(route('admin.sales.review', [$sales, 'approve']), ['admin_note' => 'Verified employment'])->assertRedirect();
        $this->get(route('admin.sales.show', $sales))->assertOk()->assertSee($sales->email)->assertSee($sales->phone)
            ->assertSee($sales->dealer->name)->assertSee('Verified employment')->assertSee($admin->name);
    }

    public function test_guests_cannot_access_any_sales_management_endpoint(): void
    {
        $sales = User::factory()->create();
        $this->get(route('admin.sales.index'))->assertRedirect(route('login'));
        $this->get(route('admin.sales.show', $sales))->assertRedirect(route('login'));
        foreach (['approve', 'reject', 'block', 'reactivate'] as $action) {
            $this->post(route('admin.sales.review', [$sales, $action]), ['rejection_reason' => 'Example'])->assertRedirect(route('login'));
        }
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
    }

    /** @dataProvider salesStatuses */
    public function test_sales_of_any_status_cannot_access_admin_endpoints(string $status): void
    {
        $actor = User::factory()->create(['status' => $status]);
        $target = User::factory()->create();
        $this->actingAs($actor)->get(route('admin.sales.index'))->assertForbidden();
        $this->get(route('admin.sales.show', $target))->assertForbidden();
        foreach (['approve', 'reject', 'block', 'reactivate'] as $action) {
            $this->post(route('admin.sales.review', [$target, $action]), ['rejection_reason' => 'Example'])->assertForbidden();
        }
        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_blocked_admin_cannot_manage_sales(): void
    {
        $admin = User::factory()->admin()->create(['status' => 'blocked']);
        $sales = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.sales.index'))->assertRedirect(route('account.status'));
        $this->get(route('admin.sales.show', $sales))->assertRedirect(route('account.status'));
        foreach (['approve', 'reject', 'block', 'reactivate'] as $action) {
            $this->post(route('admin.sales.review', [$sales, $action]))->assertRedirect(route('account.status'));
        }
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_admin_accounts_cannot_be_targets_of_sales_management(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.sales.show', $target))->assertForbidden();
        foreach (['approve', 'reject', 'block', 'reactivate'] as $action) {
            $this->post(route('admin.sales.review', [$target, $action]))->assertForbidden();
        }
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_action_authorizes_even_when_called_outside_controller(): void
    {
        $sales = User::factory()->active()->create();
        $target = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(ReviewSalesAccount::class)->execute($sales, $target, 'approve', []);
    }

    /** @dataProvider allTransitions */
    public function test_all_state_transitions_are_enforced(string $from, string $action, ?string $to): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create(['status' => $from, 'rejection_reason' => $from === 'rejected' ? 'Old reason' : null]);
        $data = ['rejection_reason' => 'Not eligible', 'admin_note' => 'Review note', 'reviewed_by' => 999999];
        $response = $this->actingAs($admin)->from(route('admin.sales.show', $sales))
            ->post(route('admin.sales.review', [$sales, $action]), $data);
        if ($to === null) {
            $response->assertSessionHasErrors('action');
            $this->assertSame($from, $sales->fresh()->status->value);
            $this->assertNull($sales->fresh()->reviewed_by);
            $this->assertSame(0, ModerationReview::count());

            return;
        }
        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.sales.show', $sales));
        $sales->refresh();
        $this->assertSame($to, $sales->status->value);
        $this->assertSame($admin->id, $sales->reviewed_by);
        $this->assertNotNull($sales->reviewed_at);
        $this->assertSame('Review note', $sales->admin_note);
        $this->assertSame($action === 'reject' ? 'Not eligible' : null, $sales->rejection_reason);
        $review = ModerationReview::sole();
        $this->assertSame($from, $review->from_status);
        $this->assertSame($to, $review->to_status);
        $this->assertSame($sales->id, $review->sales_id);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertEquals($sales->reviewed_at, $review->reviewed_at);
    }

    public static function allTransitions(): array
    {
        $valid = ['pending:approve' => 'active', 'pending:reject' => 'rejected', 'active:block' => 'blocked', 'blocked:reactivate' => 'active', 'rejected:reactivate' => 'active'];
        $cases = [];
        foreach (['pending', 'active', 'rejected', 'blocked'] as $from) {
            foreach (['approve', 'reject', 'block', 'reactivate'] as $action) {
                $cases[$from.':'.$action] = [$from, $action, $valid[$from.':'.$action] ?? null];
            }
        }

        return $cases;
    }

    public function test_reject_reason_is_optional_and_notes_have_length_limits(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'reject']), ['rejection_reason' => str_repeat('a', 2001), 'admin_note' => str_repeat('b', 2001)])
            ->assertSessionHasErrors(['rejection_reason', 'admin_note']);
        $this->assertSame(0, ModerationReview::count());
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->post(route('admin.sales.review', [$sales, 'reject']), ['rejection_reason' => '   '])->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Rejected, $sales->fresh()->status);
        $this->assertNull($sales->fresh()->rejection_reason);
    }

    public function test_account_change_is_rolled_back_when_history_insert_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        new ModerationReview; // Boot the normal model listeners before cloning the dispatcher.
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('Simulated history write failure.'));

        try {
            app(ReviewSalesAccount::class)->execute($admin, $sales, 'approve', []);
            $this->fail('Expected the history insert to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated history write failure.', $exception->getMessage());
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }

        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertNull($sales->fresh()->reviewed_by);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_repeat_approval_does_not_duplicate_history(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'approve']))->assertSessionHasNoErrors();
        $this->post(route('admin.sales.review', [$sales, 'approve']))->assertSessionHasErrors('action');
        $this->assertSame(1, ModerationReview::count());
    }

    public function test_reactivation_keeps_old_rejection_in_history(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'reject']), ['rejection_reason' => 'First reason'])->assertSessionHasNoErrors();
        $this->post(route('admin.sales.review', [$sales, 'reactivate']), ['admin_note' => 'Reviewed again'])->assertSessionHasNoErrors();
        $this->assertSame(2, $sales->moderationReviews()->count());
        $this->assertSame('First reason', $sales->moderationReviews()->orderBy('id')->first()->rejection_reason);
        $this->assertNull($sales->fresh()->rejection_reason);
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
    }

    public function test_forged_status_and_role_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'approve']), ['status' => 'active', 'role' => 'admin'])
            ->assertSessionHasErrors(['status', 'role']);
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
    }

    public function test_unknown_targets_actions_and_invalid_filters_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $this->actingAs($admin)->get('/admin/sales/999999')->assertNotFound();
        $this->post(route('admin.sales.review', [$sales, 'delete']))->assertNotFound();
        $this->get('/admin/sales?status=unknown&dealer_id=999999&page=0')->assertSessionHasErrors(['status', 'dealer_id', 'page']);
        $this->get('/admin/sales?q[]=invalid')->assertSessionHasErrors('q');
    }
}
