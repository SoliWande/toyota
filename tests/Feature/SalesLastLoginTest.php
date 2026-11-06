<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class SalesLastLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_only_successful_sales_login_records_time_and_ignores_client_timestamp(): void
    {
        $this->travelTo(now()->startOfHour());
        $sales = User::factory()->active()->create();
        $this->post('/login', ['email' => $sales->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertNull($sales->fresh()->last_login_at);
        $this->post('/login', ['email' => $sales->email, 'password' => 'password', 'last_login_at' => '2000-01-01'])
            ->assertRedirect(route('sales.dashboard'));
        $this->assertEquals(now(), $sales->fresh()->last_login_at);
        $this->travel(1)->hours();
        $this->get(route('sales.dashboard'))->assertOk();
        $this->assertEquals(now()->subHour(), $sales->fresh()->last_login_at);
        $this->post('/logout');
        $this->post('/login', ['email' => $sales->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertEquals(now()->subHour(), $sales->fresh()->last_login_at);
        $this->post('/login', ['email' => $sales->email, 'password' => 'password'])->assertRedirect();
        $this->assertEquals(now(), $sales->fresh()->last_login_at);
        $this->assertArrayNotHasKey('last_login_at', $sales->fresh()->toArray());
    }

    public function test_manager_and_admin_login_do_not_record_sales_login_time(): void
    {
        foreach ([User::factory()->dealer()->create(), User::factory()->admin()->create()] as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
            $this->assertNull($user->fresh()->last_login_at);
            $this->post('/logout');
        }
    }

    public function test_time_visible_only_in_own_manager_list_with_unknown_for_existing_accounts(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id, 'last_login_at' => '2026-11-06 09:17:23']);
        User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $foreign = User::factory()->active()->create(['last_login_at' => '2026-10-11 07:51:19']);
        $this->actingAs($manager)->get(route('dealer.sales.index'))->assertOk()
            ->assertSee('ĐĂNG NHẬP GẦN NHẤT')->assertSee('06/11/2026 09:17')->assertSee('Chưa ghi nhận đăng nhập')
            ->assertDontSee($foreign->email)->assertDontSee('11/10/2026 07:51');
        $this->actingAs(User::factory()->dealer()->create())->get(route('dealer.sales.show', $sales))->assertNotFound();
        $this->actingAs($sales)->get(route('dealer.sales.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('dealer.sales.index'))->assertForbidden();
    }
}
