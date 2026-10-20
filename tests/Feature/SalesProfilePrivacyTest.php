<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_sales_name_opens_active_public_profile_and_keeps_admin_details_separate(): void
    {
        $sales = User::factory()->active()->create(['name' => 'Sales Dương Lễ']);
        CustomerSubmission::factory()->approved()->count(6)->create(['sales_id' => $sales->id]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();
        $url = route('sales.public.show', ['slug' => $sales->public_slug]);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $targets = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (trim($anchor->textContent) === $sales->name) {
                $targets[] = $anchor->getAttribute('href');
            }
        }
        $this->assertSame([$url], $targets);
        $response->assertSee(route('admin.sales.show', $sales))->assertSee('Chi tiết nội bộ');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get($url)->assertOk()->assertSee($sales->name)->assertSee('Đại lý hiện tại:')
            ->assertSee('Trang này chỉ hiển thị thống kê tổng hợp.')
            ->assertViewHas('profile', fn ($profile) => $profile['approvedTotal'] === 6 && count($profile['months']) === 3);
    }

    public function test_processing_consent_does_not_publish_even_masked_customer_rows_and_pagination_cannot_bypass_it(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'PRIVATE_CUSTOMER_DISCLOSURE', 'phone' => '0987654321',
            'address_line' => 'PRIVATE_STREET_ADDRESS', 'province_name' => 'PRIVATE_PROVINCE', 'ward_name' => 'PRIVATE_WARD',
            'notes' => 'PRIVATE_INTERNAL_NOTE', 'evidence_image_path' => 'PRIVATE_EVIDENCE.jpg',
            'consented_at' => now(), 'consent_version' => config('dealer.consent_version'),
        ]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'PRIVATE_PENDING']);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'customer_name' => 'PRIVATE_REJECTED']);
        $this->assertStringContainsString('Thông tin này không được hiển thị công khai.', config('dealer.consent_notice'));
        foreach ([false, true] as $json) {
            foreach ([[], ['page' => 2, 'customers_page' => 2, 'include' => 'customers,evidence', 'public_consent' => 1, 'fields' => 'customer_name,phone,address_line']] as $query) {
                $url = route('sales.public.show', ['slug' => $sales->public_slug, ...$query]);
                $response = ($json ? $this->getJson($url) : $this->get($url))->assertOk()
                    ->assertSee('Thông tin từng khách hàng được bảo vệ theo quyền riêng tư.')
                    ->assertDontSee('PRIVATE_')->assertDontSee('0987654321')->assertDontSee('98***321')
                    ->assertDontSee('/evidence')->assertDontSee('customers_page=')->assertDontSee('public_consent=')
                    ->assertViewMissing('customers')->assertViewMissing('submissions');
                $profile = $response->viewData('profile');
                $this->assertSame(1, $profile['approvedTotal']);
                foreach (['customers', 'submissions', 'customer_name', 'consented_at', 'consent_version', 'evidence_image_path'] as $key) {
                    $this->assertArrayNotHasKey($key, $profile);
                }
            }
        }
    }

    public function test_public_profile_never_selects_individual_customer_personal_fields(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('sales.public.show', ['slug' => $sales->public_slug]))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], '`customer_submissions`'));
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
        foreach ($queries as $query) {
            foreach (['customer_name', 'phone', 'address_line', 'facebook_url', 'evidence_image_path', 'notes', 'consented_at'] as $field) {
                $this->assertStringNotContainsString($field, $query['query']);
            }
        }
    }

    public function test_blocked_ranked_sales_keeps_internal_detail_without_broken_public_link(): void
    {
        $sales = User::factory()->active()->create(['status' => 'blocked']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $url = route('sales.public.show', ['slug' => $sales->public_slug]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.sales.show', $sales))->assertDontSee($url);
        $this->get(route('admin.sales.show', $sales))->assertOk();
    }
}
