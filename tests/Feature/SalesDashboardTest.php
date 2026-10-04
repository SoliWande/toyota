<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class SalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_empty_dashboard_shows_sales_dealer_zero_counts_and_rank_placeholder(): void
    {
        $sales = User::factory()->active()->create();

        $this->actingAs($sales)->get(route('sales.dashboard'))
            ->assertOk()->assertSee($sales->name)->assertSee($sales->dealer->name)
            ->assertSee($sales->dealer->code)
            ->assertViewHas('stats', ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0])
            ->assertViewHas('currentRank', null)
            ->assertViewHas('recentSubmissions', fn ($items) => $items->isEmpty())
            ->assertSee('Bạn chưa có khai báo nào')->assertSee('Chưa có dữ liệu xếp hạng.')
            ->assertSee('Thêm khách hàng')->assertSee(route('sales.submissions.create'));
    }

    public function test_counts_include_all_time_and_all_statuses_for_only_the_authenticated_sales(): void
    {
        $sales = User::factory()->active()->create();
        $otherSales = User::factory()->active()->create(['dealer_id' => $sales->dealer_id]);
        CustomerSubmission::factory()->count(3)->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->count(2)->approved()->create([
            'sales_id' => $sales->id, 'submitted_at' => now()->subYear(),
        ]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => $otherSales->id, 'customer_name' => 'Other private customer',
        ]);

        $this->actingAs($sales)->get(route('sales.dashboard', [
            'sales_id' => $otherSales->id, 'user_id' => $otherSales->id, 'dealer_id' => $otherSales->dealer_id,
        ]))->assertOk()
            ->assertViewHas('stats', ['total' => 6, 'approved' => 2, 'pending' => 3, 'rejected' => 1])
            ->assertDontSee('Other private customer');
    }

    public function test_recent_submissions_are_limited_and_sorted_by_submitted_time_then_id(): void
    {
        $sales = User::factory()->active()->create();
        $old = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(10)]);
        $newest = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDay()]);
        $middle = CustomerSubmission::factory()->count(4)->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(2)]);
        $lateCreated = CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(20)]);
        $expected = [$newest->id, ...$middle->sortByDesc('id')->modelKeys()];

        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()
            ->assertViewHas('recentSubmissions', fn ($items) => $items->modelKeys() === $expected)
            ->assertSeeInOrder([$newest->customer_name, ...$middle->sortByDesc('id')->pluck('customer_name')->all()])
            ->assertDontSee($old->customer_name)->assertDontSee($lateCreated->customer_name)
            ->assertSee($newest->submitted_at->format('d/m/Y H:i'))->assertSee('Đã duyệt')->assertSee('Chờ duyệt');
    }

    public function test_customer_names_are_escaped_and_internal_data_is_not_rendered(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->rejected()->create([
            'sales_id' => $sales->id, 'customer_name' => '<script>alert("customer")</script>',
            'admin_note' => 'Private admin note', 'notes' => 'Private sales note',
            'phone' => '0901234567', 'rejection_reason' => 'Private rejection reason',
        ]);

        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()
            ->assertSee('<script>alert("customer")</script>')
            ->assertDontSee('<script>alert("customer")</script>', false)
            ->assertDontSee('Private admin note')->assertDontSee('Private sales note')
            ->assertDontSee('0901234567')->assertDontSee('Private rejection reason')->assertSee('Từ chối');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sales.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_sales_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('sales.dashboard'))->assertForbidden();
    }

    /** @dataProvider inactiveStatuses */
    public function test_inactive_sales_cannot_access_dashboard(UserStatus $status): void
    {
        $sales = User::factory()->create(['status' => $status]);
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertRedirect(route('account.status'));
        $this->getJson(route('sales.dashboard'))->assertForbidden();
    }

    public static function inactiveStatuses(): array
    {
        return [
            'pending' => [UserStatus::Pending],
            'rejected' => [UserStatus::Rejected],
            'blocked' => [UserStatus::Blocked],
        ];
    }

    public function test_active_sales_can_access_even_when_dealer_becomes_inactive(): void
    {
        $sales = User::factory()->active()->create(['dealer_id' => Dealer::factory()->inactive()]);
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk();
    }
}
