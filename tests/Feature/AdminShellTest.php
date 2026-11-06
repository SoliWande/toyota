<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginIsolatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->beginIsolatedTransaction();
    }

    public function test_existing_admin_pages_keep_their_routes_and_contents_inside_the_new_shell(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $award = Award::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $pages = [
            ['admin.dashboard', null, 'Quản trị / Tổng quan'],
            ['admin.submissions.index', null, 'Quản trị / Duyệt khách hàng'],
            ['admin.submissions.show', $submission, $submission->customer_name],
            ['admin.sales.index', null, 'Quản trị / Sales'],
            ['admin.sales.show', $sales, $sales->name], ['admin.sales.edit', $sales, $sales->name],
            ['admin.dealers.index', null, 'Quản trị / Đại lý'],
            ['admin.dealers.show', $manager->dealer, $manager->dealer->name],
            ['admin.dealers.create', null, 'Tạo đại lý'], ['admin.dealers.edit', $manager->dealer, $manager->dealer->name],
            ['admin.dealers.account.edit', $manager->dealer, $manager->name],
            ['admin.dealer-registrations.index', null, 'Quản trị / Duyệt tài khoản'],
            ['admin.awards.index', null, 'Tạo bản nháp / Xem kỳ'], ['admin.awards.show', $award, 'Bản nháp — Preview'],
        ];
        foreach ($pages as [$route, $parameter, $content]) {
            $this->get(route($route, $parameter))->assertOk()->assertSee($content)
                ->assertSee('class="ad-admin"', false)->assertSee('Giờ Việt Nam (UTC+7)')
                ->assertSee('Đăng xuất')->assertSee('action="'.route('logout').'"', false)
                ->assertSee('Tra SĐT vào group')->assertSee('Kiểm tra ngẫu nhiên')
                ->assertDontSee('href="#"', false)->assertDontSee('data-admin-toast', false)
                ->assertDontSee('wc-staff-header');
        }
    }

    public function test_shell_preserves_admin_access_boundaries_and_logout(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        foreach ([User::factory()->active()->create(), User::factory()->dealer()->create(), User::factory()->admin()->create(['status' => 'blocked'])] as $user) {
            $this->actingAs($user)->getJson(route('admin.dashboard'))->assertForbidden();
        }
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_assets_and_sidebar_do_not_enter_sales_dealer_or_public_pages(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()->assertDontSee('class="ad-admin"', false)->assertDontSee('ad-sidebar');
        $manager = User::factory()->dealer()->create();
        $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()->assertDontSee('class="ad-admin"', false)->assertDontSee('ad-sidebar');
        $this->post(route('logout'));
        $this->get(route('login'))->assertOk()->assertDontSee('class="ad-admin"', false)->assertDontSee('ad-sidebar');
    }

    public function test_unintegrated_primitives_are_inert_and_disabled_links_cannot_submit_or_navigate(): void
    {
        $html = Blade::render('<x-admin.modal id="dealer-modal" title="Thêm đại lý"><x-admin.button>Tạo đại lý</x-admin.button></x-admin.modal><x-admin.toast id="saved-toast">Đã lưu thông tin đại lý</x-admin.toast><x-admin.button href="/admin/dealers" :disabled="true">Lưu thay đổi</x-admin.button>');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//dialog[@id="dealer-modal" and not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="saved-toast" and @hidden]')->length);
        $this->assertSame(0, $xpath->query('//button[@type="submit"]|//a[@href="/admin/dealers"]')->length);
        $this->assertSame(1, $xpath->query('//span[@aria-disabled="true"]')->length);
    }
}
