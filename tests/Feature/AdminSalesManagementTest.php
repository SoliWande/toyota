<?php

namespace Tests\Feature;

use App\Actions\ReviewSalesAccount;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminSalesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_admin_list_excludes_admin_accounts_and_paginates(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->active()->count(23)->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.sales.index'))->assertOk()
            ->assertDontSee($otherAdmin->email)->assertViewHas('sales', fn ($sales) => $sales->total() === 23 && $sales->count() === 20);
        $this->get(route('admin.sales.index', ['page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->count() === 3);
    }

    #[DataProvider('salesStatuses')]
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

    public function test_c05_counts_and_filters_keep_manager_out_of_sales_scope(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->active()->create();
        User::factory()->active()->count(2)->create(['status' => 'blocked']);
        User::factory()->create();
        $manager = User::factory()->dealer()->create();
        $this->actingAs($admin)->get(route('admin.sales.index', ['role' => 'sales', 'dealer_id' => $active->dealer_id]))
            ->assertOk()->assertDontSee($manager->email)->assertSee('Tỉ lệ bị từ chối cao')
            ->assertViewHas('counts', ['active' => 1, 'blocked' => 2])
            ->assertViewHas('sales', fn ($sales) => $sales->total() === 1 && $sales->first()->is($active));
        $this->get(route('admin.sales.index', ['role' => 'dealer']))->assertSessionHasErrors('role');
        $this->post(route('admin.sales.review', [$manager, 'block']))->assertForbidden();
        $this->assertSame(UserStatus::Active, $manager->fresh()->status);
    }

    public function test_c05_month_uses_approved_submitted_at_and_inline_uses_dealer_snapshot(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(12, 0));
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create(['last_login_at' => now()->subHours(2)]);
        $oldDealer = $sales->dealer;
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-11-01 00:00:00']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-10-31 23:59:59']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-12-01 00:00:00']);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        $sales->forceFill(['dealer_id' => Dealer::factory()->create()->id])->save();
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($admin)->get(route('admin.sales.index', ['lock' => $sales->id]))->assertOk()
            ->assertSee('Khoá '.$sales->name.'?')->assertSee('1 khách đã mời vẫn tính cho '.$sales->fresh()->dealer->name)
            ->assertSee('2 giờ trước')->assertDontSee('38%')
            ->assertViewHas('sales', fn ($rows) => (int) $rows->first()->month_score === 1 && (int) $rows->first()->invited_count === 1);
        $this->assertSame(4, CustomerSubmission::where('dealer_id', $oldDealer->id)->count());
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_inline_lock_succeeds_once_preserves_customers_scores_and_revokes_access(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $customer = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $before = $customer->fresh()->getAttributes();
        $url = route('admin.sales.index', ['lock' => $sales->id]);
        $this->actingAs($admin)->from($url)->post(route('admin.sales.review', [$sales, 'block']))
            ->assertRedirect($url)->assertSessionHas('sales_blocked', true)
            ->assertSessionHas('success', 'Đã khoá '.$sales->name.' · link mời hết hiệu lực');
        $this->get($url)->assertOk()->assertSee('data-sales-success', false)->assertDontSee('data-sales-lock', false);
        $this->post(route('admin.sales.review', [$sales, 'block']))->assertSessionHasErrors('action');
        $this->get($url)->assertOk()->assertDontSee('data-sales-success', false);
        $this->assertSame(1, ModerationReview::count());
        $this->assertSame($before, $customer->fresh()->getAttributes());
        $this->get(route('sales.public.show', ['slug' => $sales->public_slug]))->assertNotFound();
        $this->actingAs($sales->fresh())->get(route('sales.dashboard'))->assertRedirect(route('account.status'));
        $this->getJson(route('sales.dashboard'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'reactivate']))->assertSessionHasNoErrors();
        $this->get(route('admin.sales.edit', $sales))->assertOk();
        $this->assertSame($before, $customer->fresh()->getAttributes());
    }

    public function test_lock_confirmation_cannot_select_foreign_roles_or_rows_outside_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->active()->create();
        $manager = User::factory()->dealer()->create();
        foreach ([['lock' => $manager->id], ['lock' => $active->id, 'status' => 'blocked'], ['lock' => $active->id, 'q' => 'No matching account']] as $query) {
            $this->actingAs($admin)->get(route('admin.sales.index', $query))->assertOk()->assertDontSee('data-sales-lock', false);
        }
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_c05_ellipsis_selects_only_its_row_and_cancel_preserves_filters_and_page(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->active()->create(['name' => 'Matching target']);
        $others = User::factory()->active()->count(20)->create(['dealer_id' => $target->dealer_id, 'name' => 'Matching other']);
        $blocked = User::factory()->create(['dealer_id' => $target->dealer_id, 'status' => 'blocked', 'name' => 'Matching blocked']);
        $filters = ['status' => 'active', 'dealer_id' => $target->dealer_id, 'q' => 'Matching', 'role' => 'sales', 'page' => 2];
        $xpath = function ($response) {
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

            return new \DOMXPath($document);
        };
        $queryOf = function ($url) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

            return $query;
        };

        $response = $this->actingAs($admin)->get(route('admin.sales.index', $filters))->assertOk();
        $links = $xpath($response)->query('//*[@data-sales-open-confirm]');
        $this->assertSame(1, $links->length);
        $openUrl = $links->item(0)->getAttribute('href');
        $this->assertEquals([...$filters, 'lock' => $target->id], $queryOf($openUrl));
        $opened = $this->get($openUrl)->assertOk();
        $forms = $xpath($opened)->query('//form[@data-sales-lock]');
        $this->assertSame(1, $forms->length);
        $this->assertSame(route('admin.sales.review', [$target, 'block']), $forms->item(0)->getAttribute('action'));
        $this->assertSame('POST', $forms->item(0)->getAttribute('method'));
        $cancel = $xpath($opened)->query('//form[@data-sales-lock]//a')->item(0)->getAttribute('href');
        $this->assertEquals($filters, $queryOf($cancel));
        $this->get($cancel)->assertOk()->assertDontSee('data-sales-lock', false);
        foreach ([$others->first()->id, $blocked->id] as $invalidId) {
            $this->get(route('admin.sales.index', [...$filters, 'lock' => $invalidId]))->assertOk()->assertDontSee('data-sales-lock', false);
        }
        $this->get(route('admin.sales.index', [...$filters, 'page' => 1, 'lock' => $target->id]))->assertOk()->assertDontSee('data-sales-lock', false);
        $this->get(route('admin.sales.index', ['status' => 'blocked', 'lock' => $blocked->id]))->assertOk()
            ->assertDontSee('data-sales-open-confirm', false)->assertDontSee('data-sales-lock', false);
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_inline_lock_requires_csrf(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $this->app['env'] = 'local'; // Enable CSRF middleware; connection remains guarded toyota_testing.
        try {
            $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'block']))->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
    }

    #[DataProvider('searchableFields')]
    public function test_admin_can_search_name_email_and_phone(string $field, string $value, string $search): void
    {
        $admin = User::factory()->admin()->create();
        $match = User::factory()->active()->create([$field => $value]);
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
        $sales = User::factory()->active()->create(['phone' => '0901234567']);
        $this->actingAs($admin)->from(route('admin.sales.show', $sales))
            ->post(route('admin.sales.review', [$sales, 'block']), ['admin_note' => 'Verified employment'])->assertRedirect();
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

    #[DataProvider('salesStatuses')]
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

    #[DataProvider('allTransitions')]
    public function test_all_state_transitions_are_enforced(string $from, string $action, ?string $to): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create(['status' => $from, 'rejection_reason' => $from === 'rejected' ? 'Old reason' : null]);
        $data = ['rejection_reason' => 'Not eligible', 'admin_note' => 'Review note', 'reviewed_by' => 999999];
        $response = $this->actingAs($admin)->from(route('admin.sales.show', $sales))
            ->post(route('admin.sales.review', [$sales, $action]), $data);
        // New contract: Sales registration approval belongs exclusively to its Dealer Manager.
        if (in_array($action, ['approve', 'reject'], true)) {
            $response->assertForbidden();
            $this->assertSame($from, $sales->fresh()->status->value);
            $this->assertNull($sales->fresh()->reviewed_by);
            $this->assertSame(0, ModerationReview::count());

            return;
        }
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
        $valid = ['active:block' => 'blocked', 'blocked:reactivate' => 'active'];
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
        $this->post(route('admin.sales.review', [$sales, 'reject']), ['rejection_reason' => '   '])->assertForbidden();
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertNull($sales->fresh()->rejection_reason);
    }

    public function test_account_change_is_rolled_back_when_history_insert_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        new ModerationReview;
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('Simulated history write failure.'));
        try {
            app(ReviewSalesAccount::class)->execute($admin, $sales, 'block', []);
            $this->fail('Expected the history insert to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated history write failure.', $exception->getMessage());
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
        $this->assertNull($sales->fresh()->reviewed_by);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_repeat_block_does_not_duplicate_history(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'block']))->assertSessionHasNoErrors();
        $this->post(route('admin.sales.review', [$sales, 'block']))->assertSessionHasErrors('action');
        $this->assertSame(1, ModerationReview::count());
    }

    public function test_reactivation_keeps_old_admin_notes_in_history(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $this->actingAs($admin)->post(route('admin.sales.review', [$sales, 'block']), ['admin_note' => 'First note'])->assertSessionHasNoErrors();
        $this->post(route('admin.sales.review', [$sales, 'reactivate']), ['admin_note' => 'Reviewed again'])->assertSessionHasNoErrors();
        $this->assertSame(2, $sales->moderationReviews()->count());
        $this->assertSame('First note', $sales->moderationReviews()->orderBy('id')->first()->admin_note);
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
