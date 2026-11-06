<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerCustomerPresentationTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginIsolatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->beginIsolatedTransaction();
    }

    public function test_list_uses_staff_shell_masks_phone_and_preserves_private_dealer_scope(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $customer = CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => '<script>customer()</script>',
            'phone' => '0901234567', 'notes' => 'PRIVATE-SALES-NOTE', 'admin_note' => 'PRIVATE-ADMIN-NOTE',
        ]);
        CustomerSubmission::factory()->create(['customer_name' => 'FOREIGN-CUSTOMER']);
        $this->actingAs($manager)->get(route('dealer.customers.index'))->assertOk()
            ->assertSee('wc-page-manager-customers')->assertSee('wc-staff-header')->assertSee('Khách của tôi')
            ->assertSee($customer->customer_name)->assertDontSee($customer->customer_name, false)
            ->assertSee('••• ••• 567')->assertSee($customer->status->label())->assertSee($sales->name)
            ->assertSee(route('dealer.customers.show', $customer))
            ->assertDontSee('0901234567')->assertDontSee('PRIVATE-SALES-NOTE')->assertDontSee('PRIVATE-ADMIN-NOTE')
            ->assertDontSee('FOREIGN-CUSTOMER')->assertDontSee('Lọc khách hàng')
            ->assertDontSee('name="q"', false)->assertDontSee('name="status"', false)
            ->assertDontSee('Sales hiện tại')->assertDontSee('Khách hàng được giữ tại đại lý ghi nhận khi khai báo.');
    }

    public function test_hidden_search_and_status_still_filter_and_paginate_real_records(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->count(21)->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'Matching customer']);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'Matching pending customer']);
        CustomerSubmission::factory()->approved()->create(['customer_name' => 'Matching foreign customer']);
        $response = $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Matching', 'status' => 'approved']))
            ->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 21 && $rows->count() === 20)
            ->assertDontSee('Matching pending customer')->assertDontSee('Matching foreign customer');
        $next = $response->viewData('customers')->nextPageUrl();
        $this->assertStringContainsString('q=Matching', $next);
        $this->assertStringContainsString('status=approved', $next);
        $this->get($next)->assertOk()->assertViewHas('customers', fn ($rows) => $rows->count() === 1);
        $this->get(route('dealer.customers.index', ['q' => 'No matching customer']))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->isEmpty())
            ->assertDontSee('wc-manager-customer-list')->assertDontSee('Không có khách hàng phù hợp.');
    }
}
