<?php

namespace Tests\Feature;

use App\Actions\ReviewAccountRegistration;
use App\Enums\LeaderboardPeriod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AccountRegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public static function roles(): array
    {
        return [['sales'], ['dealer']];
    }

    #[DataProvider('roles')]
    public function test_both_roles_register_pending_with_hashed_password_and_cannot_self_activate(string $role): void
    {
        $dealer = Dealer::factory()->create();
        $this->post(route('register'), ['name' => 'Nguyễn Minh Anh', 'email' => 'NEW@EXAMPLE.TEST', 'phone' => '090 123 4567',
            'password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123', 'dealer_id' => $dealer->id, 'role' => $role])
            ->assertRedirect(route('account.status'));
        $account = User::sole();
        $this->assertSame($role, $account->role->value);
        $this->assertSame(UserStatus::Pending, $account->status);
        $this->assertSame($dealer->id, $account->dealer_id);
        $this->assertSame('0901234567', $account->phone);
        $this->assertTrue(Hash::check('safe-password-123', $account->password));
        $this->assertNull($account->reviewed_by);
        $this->get(route('account.status'))->assertOk()->assertSee($dealer->name)->assertSee($role === 'dealer' ? 'Admin Ô tô Sài Gòn' : 'Quản lý đại lý');
        $this->getJson(route($account->role === UserRole::Dealer ? 'dealer.dashboard' : 'sales.dashboard'))->assertForbidden();
        $this->postJson(route('account.resubmit'))->assertUnprocessable();
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_malformed_registration_fields_render_validation_errors_without_crashing_or_flashing_passwords(): void
    {
        Dealer::factory()->create();
        $this->from(route('register'))->post(route('register'), ['name' => ['invalid'], 'dealer_id' => ['invalid'],
            'role' => ['admin'], 'email' => ['invalid'], 'phone' => ['invalid'], 'password' => 'private-password'])
            ->assertSessionHasErrors(['name', 'dealer_id', 'role', 'email', 'phone', 'password']);
        $this->get(route('register'))->assertOk()->assertDontSee('private-password');
        $this->assertSame(0, User::count());
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_inactive_admin_cannot_review_manager_and_non_admin_cannot_access_admin_queue(): void
    {
        $target = User::factory()->dealer()->create(['status' => 'pending']);
        $admin = User::factory()->admin()->create(['status' => 'blocked']);
        $this->actingAs($admin)->postJson(route('admin.dealer-registrations.review', [$target, 'approve']))->assertForbidden();
        $this->actingAs(User::factory()->dealer()->create())->getJson(route('admin.dealer-registrations.index'))->assertForbidden();
        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_manager_uniqueness_includes_pending_rejected_and_blocked_accounts(): void
    {
        foreach (UserStatus::cases() as $status) {
            $manager = User::factory()->dealer()->create(['status' => $status]);
            $this->post(route('register'), ['name' => 'Another manager', 'email' => $status->value.'@example.test', 'phone' => '0901234567',
                'password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123', 'dealer_id' => $manager->dealer_id, 'role' => 'dealer'])
                ->assertSessionHasErrors('dealer_id');
            $this->assertSame(1, $manager->dealer->manager()->count());
        }
    }

    public function test_only_same_dealer_manager_can_review_sales_and_admin_cannot_bypass_legacy_route(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->create(['dealer_id' => $manager->dealer_id]);
        $other = User::factory()->dealer()->create();
        $admin = User::factory()->admin()->create();
        foreach ([$sales, $other] as $actor) {
            $this->actingAs($actor)->postJson(route('dealer.staff.review', [$sales, 'approve']))->assertForbidden();
        }
        $this->actingAs($manager)->postJson(route('dealer.staff.review', [$manager, 'approve']))->assertForbidden();
        $this->actingAs($admin)->postJson(route('admin.sales.review', [$sales, 'approve']))->assertForbidden();
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertSame(0, ModerationReview::count());
        $this->actingAs($manager)->post(route('dealer.staff.review', [$sales, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
        $this->assertSame($manager->id, $sales->fresh()->reviewed_by);
        $this->assertSame($sales->id, ModerationReview::sole()->sales_id);
        $this->postJson(route('dealer.staff.review', [$sales, 'approve']))->assertUnprocessable();
        $this->assertSame(1, ModerationReview::count());
        $this->actingAs($sales->fresh())->get(route('sales.dashboard'))->assertOk();
    }

    public function test_admin_requires_existing_permission_to_review_manager_and_legacy_editor_cannot_bypass(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->dealer()->create(['status' => 'pending']);
        $this->actingAs($admin)->get(route('admin.dealer-registrations.index'))->assertOk()->assertSee($manager->email);
        $this->patch(route('admin.dealers.account.update', $manager->dealer_id), ['name' => $manager->name, 'status' => 'active'])->assertSessionHasErrors('status');
        $this->assertSame(UserStatus::Pending, $manager->fresh()->status);
        $deny = true;
        Gate::before(function ($actor, $ability) use (&$deny) {
            return $deny && $ability === 'update' ? false : null;
        });
        $this->postJson(route('admin.dealer-registrations.review', [$manager, 'approve']))->assertForbidden();
        $deny = false;
        $this->post(route('admin.dealer-registrations.review', [$manager, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Active, $manager->fresh()->status);
        $this->assertSame($admin->id, ModerationReview::sole()->reviewed_by);
        $this->postJson(route('admin.dealer-registrations.review', [$manager, 'approve']))->assertUnprocessable();
        $this->actingAs($manager->fresh())->get(route('dealer.dashboard'))->assertOk();
    }

    #[DataProvider('roles')]
    public function test_reject_and_resubmit_preserve_identity_unique_manager_and_append_only_history(string $role): void
    {
        $actor = $role === 'sales' ? User::factory()->dealer()->create() : User::factory()->admin()->create();
        $target = User::factory()->create(['role' => $role, 'dealer_id' => $role === 'sales' ? $actor->dealer_id : Dealer::factory()->create()->id]);
        $identity = $target->only(['id', 'name', 'email', 'password', 'phone', 'role', 'dealer_id']);
        $reviewRoute = $role === 'sales' ? 'dealer.staff.review' : 'admin.dealer-registrations.review';
        $this->actingAs($actor)->post(route($reviewRoute, [$target, 'reject']), ['rejection_reason' => 'Vui lòng xác nhận công việc.'])->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Rejected, $target->fresh()->status);
        $this->actingAs($target->fresh())->get(route('account.status'))->assertOk()->assertSee('Vui lòng xác nhận công việc.');
        $this->post(route('account.resubmit'), ['role' => 'admin', 'dealer_id' => 9999])->assertRedirect(route('account.status'));
        $this->assertSame($identity, $target->fresh()->only(array_keys($identity)));
        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
        $this->assertNull($target->fresh()->reviewed_by);
        $this->assertNull($target->fresh()->reviewed_at);
        $this->assertSame(2, ModerationReview::count());
        $this->assertSame('Vui lòng xác nhận công việc.', ModerationReview::orderBy('id')->first()->rejection_reason);
        $this->postJson(route('account.resubmit'))->assertUnprocessable();
        $this->actingAs($actor)->post(route($reviewRoute, [$target, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame(3, ModerationReview::count());
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertSame(1, User::whereKey($target->id)->count());
    }

    public function test_manager_review_validates_reason_and_rejects_forged_fields(): void
    {
        $manager = User::factory()->dealer()->create();
        $target = User::factory()->create(['dealer_id' => $manager->dealer_id]);
        $this->actingAs($manager)->postJson(route('dealer.staff.review', [$target, 'reject']), ['rejection_reason' => str_repeat('a', 2001)])->assertUnprocessable();
        $this->postJson(route('dealer.staff.review', [$target, 'approve']), ['role' => 'admin', 'status' => 'active', 'admin_note' => 'private', 'dealer_id' => 999])->assertUnprocessable();
        $this->assertSame(0, ModerationReview::count());
        $this->post(route('dealer.staff.review', [$target, 'reject']), ['rejection_reason' => '  '])->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Rejected, $target->fresh()->status);
        $this->assertNull($target->fresh()->rejection_reason);
        $this->postJson(route('dealer.staff.review', [$target, 'reactivate']))->assertUnprocessable();
    }

    public function test_action_reloads_revoked_actor_and_reassigned_target_before_authorization(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->create(['dealer_id' => $manager->dealer_id]);
        User::whereKey($manager->id)->update(['status' => 'blocked']);
        try {
            app(ReviewAccountRegistration::class)->execute($manager, $sales, 'approve');
            $this->fail('Stale active actor accepted.');
        } catch (AuthorizationException) {
            $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        }
        User::whereKey($manager->id)->update(['status' => 'active']);
        User::whereKey($sales->id)->update(['dealer_id' => Dealer::factory()->create()->id]);
        try {
            app(ReviewAccountRegistration::class)->execute($manager, $sales, 'approve');
            $this->fail('Stale Dealer assignment accepted.');
        } catch (AuthorizationException) {
            $this->assertSame(0, ModerationReview::count());
        }
    }

    public function test_history_failure_rolls_back_status_and_reviewer(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->create(['dealer_id' => $manager->dealer_id]);
        new ModerationReview;
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('audit failed'));
        try {
            app(ReviewAccountRegistration::class)->execute($manager, $sales, 'approve');
            $this->fail('Expected rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertNull($sales->fresh()->reviewed_by);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_customer_moderation_remains_admin_only_and_internal_notes_are_never_disclosed(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->create(['dealer_id' => $manager->dealer_id, 'admin_note' => 'SECRET_INTERNAL']);
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($manager)->postJson(route('admin.submissions.approve', $submission))->assertForbidden();
        $this->get(route('dealer.sales.index'))->assertOk()->assertDontSee('SECRET_INTERNAL');
        $review = new ModerationReview;
        $this->expectException(\InvalidArgumentException::class);
        $review->forceFill(['customer_submission_id' => $submission->id, 'reviewed_by' => $manager->id, 'from_status' => 'pending', 'to_status' => 'approved', 'reviewed_at' => now()])->save();
    }

    public function test_pending_rejected_and_blocked_roles_cannot_access_private_pages_or_review(): void
    {
        $target = User::factory()->create();
        foreach (['sales', 'dealer'] as $role) {
            foreach (['pending', 'rejected', 'blocked'] as $status) {
                $actor = User::factory()->create(['role' => $role, 'status' => $status]);
                foreach ($role === 'dealer' ? ['dealer.dashboard', 'dealer.sales.index', 'dealer.customers.index', 'dealer.awards.index'] : ['sales.dashboard', 'sales.submissions.index', 'sales.submissions.create'] as $route) {
                    $this->actingAs($actor)->getJson(route($route))->assertForbidden();
                }
                $this->postJson(route('dealer.staff.review', [$target, 'approve']))->assertForbidden();
            }
        }
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_staff_tabs_paginate_pending_requests_and_keep_search_state(): void
    {
        $manager = User::factory()->dealer()->create();
        User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        User::factory()->count(21)->create(['dealer_id' => $manager->dealer_id, 'name' => 'Matching pending']);
        $this->actingAs($manager)->get(route('dealer.sales.index'))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['status'] === 'active')
            ->assertViewHas('sales', fn ($rows) => $rows->total() === 1 && $rows->first()->status === UserStatus::Active);
        $this->get(route('dealer.sales.index', ['status' => 'pending', 'q' => 'Matching']))->assertOk()
            ->assertViewHas('sales', fn ($rows) => $rows->total() === 21 && $rows->count() === 20
                && str_contains($rows->nextPageUrl(), 'status=pending') && str_contains($rows->nextPageUrl(), 'q=Matching'));
        $this->get(route('dealer.sales.index', ['status' => 'pending', 'q' => 'Matching', 'page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($rows) => $rows->count() === 1)->assertSee('Duyệt');
        $this->get(route('dealer.sales.index').'?status=')->assertOk()->assertViewHas('sales', fn ($rows) => $rows->total() === 22);
    }

    public function test_staff_list_queries_do_not_grow_per_person_and_only_show_own_dealer(): void
    {
        $manager = User::factory()->dealer()->create();
        User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $other = User::factory()->create();
        $this->actingAs($manager);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('dealer.sales.index'))->assertOk()->assertDontSee($other->email);
        $small = count(DB::getQueryLog());
        User::factory()->active()->count(19)->create(['dealer_id' => $manager->dealer_id]);
        DB::flushQueryLog();
        $this->get(route('dealer.sales.index'))->assertOk()->assertViewHas('sales', fn ($items) => $items->count() === 20);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 1, $large);
    }

    public function test_legacy_admin_sales_rejection_notes_are_not_exposed_on_status_page(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create(['status' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
            'rejection_reason' => 'LEGACY_INTERNAL_REASON', 'admin_note' => 'SECRET_INTERNAL']);
        $this->actingAs($sales)->get(route('account.status'))->assertOk()->assertDontSee('LEGACY_INTERNAL_REASON')->assertDontSee('SECRET_INTERNAL');
    }

    public function test_c06_queue_is_real_masked_and_does_not_claim_unverified_checks_or_blocked_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->dealer()->create(['status' => 'pending', 'phone' => '0939123214', 'name' => '<script>Pending</script>', 'admin_note' => 'PRIVATE_NOTE']);
        $active = User::factory()->dealer()->create();
        $rejected = User::factory()->dealer()->create(['status' => 'rejected']);
        $sales = User::factory()->create(['created_at' => now()->subDays(5)]);
        $response = $this->actingAs($admin)->get(route('admin.dealer-registrations.index'))->assertOk()
            ->assertViewHas('pendingCount', 1)->assertViewHas('accounts', fn ($rows) => $rows->modelKeys() === [$pending->id])
            ->assertSee($pending->email)->assertSee('0939 xxx 214')->assertSee($pending->name)
            ->assertDontSee($pending->name, false)->assertDontSee('0939123214')->assertDontSee('PRIVATE_NOTE')
            ->assertDontSee($active->email)->assertDontSee($rejected->email)->assertDontSee('Email đúng tên miền')
            ->assertDontSee('Số điện thoại chưa dùng')->assertDontSee('admin có thể nhắc hoặc duyệt thay')
            ->assertDontSee('Đã gửi')->assertDontSee('Đã duyệt thay');
        $response->assertSee('Đã xử lý')->assertSee('aria-disabled="true"', false);
        $this->get(route('admin.dealer-registrations.index', ['tab' => 'overdue']))->assertOk()
            ->assertViewHas('accounts', fn ($rows) => $rows->isEmpty())->assertSee($sales->name)->assertDontSee($pending->email);
        $this->getJson(route('admin.dealer-registrations.index', ['tab' => 'processed']))->assertUnprocessable();
        $this->actingAs($sales)->getJson(route('admin.dealer-registrations.index'))->assertForbidden();
    }

    public function test_c06_overdue_uses_strict_72_hours_and_latest_resubmission_instead_of_updated_at(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->dealer()->create();
        $old = User::factory()->create(['dealer_id' => $manager->dealer_id, 'created_at' => now()->subDays(5), 'updated_at' => now()]);
        User::factory()->create(['created_at' => now()->subHours(72)]);
        $over = User::factory()->create(['created_at' => now()->subHours(72)->subSecond()]);
        User::factory()->active()->create(['created_at' => now()->subMonth()]);
        $resubmitted = User::factory()->create(['created_at' => now()->subMonth(), 'status' => 'rejected']);
        app(ReviewAccountRegistration::class)->resubmit($resubmitted);
        $this->actingAs($admin)->get(route('admin.dealer-registrations.index'))->assertOk()
            ->assertViewHas('overdueSales', fn ($rows) => $rows->modelKeys() === [$old->id, $over->id]
                && $rows->first()->waiting_days === 5 && $rows->first()->dealer->manager->is($manager));
        $this->travel(4)->days();
        $this->get(route('admin.dealer-registrations.index'))->assertOk()
            ->assertViewHas('overdueSales', fn ($rows) => $rows->contains('id', $resubmitted->id));
    }

    public function test_c06_public_rejection_combines_reason_and_note_with_validation_and_never_sends_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $account = User::factory()->dealer()->create(['status' => 'pending']);
        $identity = $account->only(['id', 'email', 'password', 'dealer_id', 'role']);
        $url = route('admin.dealer-registrations.review', [$account, 'reject']);
        $this->actingAs($admin)->postJson($url, ['admin_note' => 'PRIVATE', 'dealer_id' => 999, 'role' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors(['admin_note', 'dealer_id', 'role']);
        $this->actingAs($admin)->postJson($url, ['rejection_reason' => 'Không phải nhân viên của đại lý', 'rejection_note' => str_repeat('a', 2000)])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->postJson($url, ['rejection_reason' => 'Không phải nhân viên của đại lý', 'rejection_note' => ['forged']])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_note');
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->postJson($url, ['rejection_reason' => 'Không phải nhân viên của đại lý', 'rejection_note' => ' Xác nhận lại với tổng đài. '])
            ->assertOk()->assertJsonPath('message', null)->assertDontSee('email');
        $reason = "Không phải nhân viên của đại lý\nXác nhận lại với tổng đài.";
        $this->assertSame($reason, $account->fresh()->rejection_reason);
        $this->assertSame($reason, ModerationReview::sole()->rejection_reason);
        $this->assertSame($identity, $account->fresh()->only(array_keys($identity)));
        $this->postJson($url)->assertUnprocessable();
        $this->assertDatabaseCount('moderation_reviews', 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_c06_approval_toast_requires_real_success_and_admin_cannot_override_sales(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->dealer()->create(['status' => 'pending']);
        $sales = User::factory()->create(['dealer_id' => $manager->dealer_id]);
        $this->actingAs($admin)->postJson(route('admin.dealer-registrations.review', [$sales, 'approve']))->assertForbidden();
        $this->postJson(route('admin.dealer-registrations.review', [$manager, 'approve']))->assertOk()
            ->assertJsonPath('message', 'Đã duyệt '.$manager->name.' làm quản lý '.$manager->dealer->name);
        $this->assertSame(UserStatus::Active, $manager->fresh()->status);
        $this->assertSame(UserStatus::Pending, $sales->fresh()->status);
        $this->assertDatabaseCount('moderation_reviews', 1);
        $this->postJson(route('admin.dealer-registrations.review', [$manager, 'approve']))->assertUnprocessable();
        $this->assertDatabaseCount('moderation_reviews', 1);
        $this->actingAs($manager->fresh())->postJson(route('dealer.staff.review', [$sales, 'approve']))->assertRedirect();
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
    }

    public function test_c06_rejects_missing_csrf_and_inactive_dealer_without_writing(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->dealer()->create(['status' => 'pending']);
        $url = route('admin.dealer-registrations.review', [$account, 'approve']);
        $this->actingAs($admin);
        $previous = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->post($url)->assertStatus(419);
        } finally {
            $this->app['env'] = $previous;
        }
        $account->dealer->update(['is_active' => false]);
        $this->get(route('admin.dealer-registrations.index'))->assertOk()->assertDontSee('chưa có quản lý. Duyệt xong');
        $this->postJson($url)->assertUnprocessable();
        $this->assertSame(UserStatus::Pending, $account->fresh()->status);
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public function test_staff_block_and_unblock_are_scoped_and_do_not_change_scores_or_customer_data(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $customer = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $before = $customer->fresh()->getRawOriginal();
        $score = app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->get()->toArray();
        $this->actingAs($manager)->post(route('dealer.staff.review', [$sales, 'block']))->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Blocked, $sales->fresh()->status);
        $this->post(route('dealer.staff.review', [$sales, 'reactivate']))->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
        $this->assertSame($before, $customer->fresh()->getRawOriginal());
        $this->assertEquals($score, app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->get()->toArray());
    }
}
