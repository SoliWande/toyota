<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\User;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    public function test_guest_public_ui_has_dealer_content_without_current_sales_sections(): void
    {
        foreach (['home', 'leaderboard', 'awards.index', 'login', 'register', 'password.request'] as $route) {
            $response = $this->get(route($route))->assertOk();
            foreach (['Sales Management', 'Sales Dashboard', 'Top Sales', 'Top 3 Sales', 'đăng ký Sales', 'Sales đang hoạt động', '/sales/', '/admin/sales'] as $obsolete) {
                $response->assertDontSee($obsolete);
            }
        }
        $home = $this->get(route('home'))->assertSee('Đăng ký đại lý')->assertSee('Xếp hạng đại lý')->assertSee('Vinh danh đại lý');
        $this->assertArrayNotHasKey('sales', $home->viewData('stats'));
        $this->assertArrayHasKey('accounts', $home->viewData('stats'));
        $award = Award::where('competition_scope', 'legacy_mixed')->whereNotNull('published_at')->firstOrFail();
        $this->get(route('awards.show', $award))->assertOk()->assertSee('Sales (lịch sử)');
    }

    public function test_public_desktop_and_mobile_account_links_follow_authenticated_role(): void
    {
        foreach ([User::factory()->active()->create(), User::factory()->admin()->create()] as $account) {
            $this->actingAs($account)->get(route('home'))->assertOk()->assertSee(route($account->homeRoute()))
                ->assertSee(route('logout'))->assertDontSee('href="'.route('register').'"', false);
        }
        $account = User::factory()->create();
        $this->actingAs($account)->get(route('account.status'))->assertOk()->assertDontSee('Chức năng đại lý')
            ->assertDontSee(route('dealer.submissions.create'));
        $this->get(route('home'))->assertOk()->assertSee('Trạng thái tài khoản');
    }

    public function test_dealer_profile_and_submission_pages_have_only_dealer_navigation(): void
    {
        $account = User::factory()->active()->create();
        foreach (['profile.edit', 'dealer.submissions.index', 'dealer.submissions.create'] as $route) {
            $this->actingAs($account)->get(route($route))->assertOk()->assertSee('Dashboard đại lý')->assertSee('Khai báo của đại lý')
                ->assertDontSee('Chức năng quản trị')->assertDontSee('/sales/');
        }
        $this->get(route('profile.edit'))->assertSee('gắn cố định với đại lý này')->assertDontSee('name="dealer_id"', false);
    }
}
