<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    public function test_admin_summary_and_navigation_use_dealer_accounts_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-02-12'));
        $admin = User::factory()->admin()->create();
        User::factory()->active()->create();
        User::factory()->create();
        User::factory()->create(['status' => 'blocked']);
        CustomerSubmission::factory()->approved()->create(['submitted_at' => '2031-02-12']);
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(Dealer::count(), $stats['dealers']);
        $this->assertSame(User::where('role', 'dealer')->where('status', 'active')->count(), $stats['active_accounts']);
        $this->assertSame(User::where('role', 'dealer')->where('status', 'pending')->count(), $stats['pending_accounts']);
        $this->assertSame(CustomerSubmission::where('status', 'approved')->count(), $stats['approved_submissions']);
        foreach (['admin.dealers.index', 'admin.dealer-accounts.index', 'admin.submissions.index', 'leaderboard', 'admin.awards.index', 'profile.edit'] as $route) {
            $response->assertSee(route($route));
        }
        $response->assertDontSee('Sales Management')->assertDontSee('Top Sales')->assertDontSee('/admin/sales')->assertDontSee('/sales/dashboard');
        $this->assertCount(1, $response->viewData('topDealers'));
        $this->assertArrayNotHasKey('active_sales', $stats);
    }

    public function test_management_pages_render_dealer_labels_and_correct_login_account(): void
    {
        $account = User::factory()->active()->create(['name' => 'Dealer account contact']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.dealers.index', ['q' => $account->dealer->code]))->assertOk()
            ->assertSee('Tài khoản đại lý')->assertSee('Dealer account contact')->assertSee(route('admin.dealer-accounts.show', $account))
            ->assertDontSee('>Sales<', false);
        foreach (['admin.dealers.create', 'admin.dealer-accounts.index', 'admin.dealer-accounts.create', 'admin.awards.index', 'admin.submissions.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('/admin/sales')->assertDontSee('/sales/');
        }
        $this->get(route('admin.dealers.edit', $account->dealer))->assertOk()->assertDontSee('đăng ký Sales')->assertSee('giữ nguyên tài khoản');
        $this->get(route('admin.dealer-accounts.edit', $account))->assertOk()->assertSee('Chỉnh sửa tài khoản đại lý');
        $this->get(route('admin.dealer-accounts.show', $account))->assertOk()->assertSee('Danh sách tài khoản đại lý');
    }

    public function test_admin_dashboard_remains_protected_for_inactive_admin_and_dealer(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->active()->create())->getJson(route('admin.dashboard'))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        DB::table('users')->where('id', $admin->id)->update(['status' => 'blocked']);
        $this->getJson(route('admin.dashboard'))->assertForbidden();
    }
}
