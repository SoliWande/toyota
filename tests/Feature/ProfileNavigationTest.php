<?php

namespace Tests\Feature;

use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfileNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function links(TestResponse $response): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $links = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $links[] = ['name' => trim(preg_replace('/\s+/u', ' ', $anchor->textContent)), 'url' => $anchor->getAttribute('href')];
        }

        return $links;
    }

    private function assertNamedLink(TestResponse $response, string $name, string $url): void
    {
        $this->assertContains(['name' => $name, 'url' => $url], $this->links($response));
        $this->assertNull(parse_url($url, PHP_URL_QUERY), 'Profile links must not copy private search/filter input.');
    }

    public function test_admin_dashboard_and_customer_rows_link_to_authorized_details(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $pending = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $this->actingAs($admin);
        foreach ([route('admin.dashboard'), route('admin.submissions.index'), route('admin.submissions.show', $pending)] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertNamedLink($response, $sales->name, route('admin.sales.show', $sales));
            $this->assertNamedLink($response, $sales->dealer->name, route('admin.dealers.show', $sales->dealer_id));
        }
        $this->assertNamedLink($this->get(route('admin.dashboard')), $pending->customer_name, route('admin.submissions.show', $pending));
        foreach ([route('admin.sales.index'), route('admin.sales.show', $sales)] as $url) {
            $this->assertNamedLink($this->get($url)->assertOk(), $sales->dealer->name, route('admin.dealers.show', $sales->dealer_id));
        }
        $this->get(route('admin.sales.show', $sales))->assertOk()->assertSee(route('sales.public.show', ['slug' => $sales->public_slug]));
        $this->get(route('admin.dealers.show', $sales->dealer_id))->assertOk();
    }

    public function test_dealer_links_current_sales_internally_and_transferred_sales_only_to_public_profile(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($manager);
        foreach ([route('dealer.dashboard'), route('dealer.customers.index'), route('dealer.customers.show', $submission)] as $url) {
            $this->assertNamedLink($this->get($url)->assertOk(), $sales->name, route('dealer.sales.show', $sales));
        }
        $this->get(route('dealer.sales.show', $sales))->assertOk()->assertSee(route('sales.public.show', ['slug' => $sales->public_slug]));
        $sales->forceFill(['dealer_id' => Dealer::factory()->create()->id])->save();
        $public = route('sales.public.show', ['slug' => $sales->public_slug]);
        $response = $this->get(route('dealer.customers.show', $submission))->assertOk()->assertDontSee(route('dealer.sales.show', $sales));
        $this->assertNamedLink($response, $sales->name, $public);
        $this->get($public)->assertOk()->assertDontSee($sales->email)->assertDontSee($submission->customer_name);
        $this->get(route('dealer.sales.show', $sales))->assertNotFound();
        $this->assertSame($manager->dealer_id, $submission->fresh()->dealer_id);
        $sales->forceFill(['status' => 'blocked'])->save();
        $this->get(route('dealer.customers.show', $submission))->assertOk()->assertSee($sales->name)
            ->assertDontSee($public)->assertDontSee(route('dealer.sales.show', $sales));
        $this->get($public)->assertNotFound();
    }

    public function test_sales_dashboard_links_own_public_profile_and_dealer_without_internal_admin_links(): void
    {
        $sales = User::factory()->active()->create(['phone' => '0901234567', 'email' => 'PRIVATE_NAV_EMAIL@example.test']);
        $response = $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk();
        $public = route('sales.public.show', ['slug' => $sales->public_slug]);
        $this->assertNamedLink($response, $sales->name, $public);
        $this->assertNamedLink($response, $sales->dealer->name, route('dealers.show', $sales->dealer_id));
        $response->assertDontSee(route('admin.sales.show', $sales))->assertDontSee(route('dealer.sales.show', $sales));
        foreach ([false, true] as $json) {
            ($json ? $this->getJson($public) : $this->get($public))->assertOk()
                ->assertSee('••• ••• 567')->assertDontSee($sales->phone)->assertDontSee($sales->email)
                ->assertDontSee('/submissions/')->assertDontSee('/evidence');
        }
        $this->get(route('dealers.show', $sales->dealer_id))->assertRedirect(app(DealerProfile::class)->url($sales->dealer));
        $this->get(app(DealerProfile::class)->url($sales->dealer))->assertOk();
    }

    public function test_award_names_link_to_dealer_public_profile_or_admin_details_without_rewriting_snapshots(): void
    {
        $salesWinner = AwardWinner::factory()->create(['winner_name_snapshot' => 'Historic Sales Navigation']);
        $dealerWinner = AwardWinner::factory()->dealer()->create(['award_id' => $salesWinner->award_id, 'dealer_name_snapshot' => 'Historic Dealer Navigation']);
        $award = $salesWinner->award;
        $admin = User::factory()->admin()->create();
        $award->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        $response = $this->get(route('awards.show', $award))->assertOk()->assertDontSee('Historic Sales Navigation');
        $this->assertNamedLink($response, 'Historic Dealer Navigation', route('dealers.show', $dealerWinner->dealer_id));
        $this->get(route('dealers.show', $dealerWinner->dealer_id))->assertRedirect(app(DealerProfile::class)->url($dealerWinner->dealer_id));
        $response = $this->actingAs($admin)->get(route('admin.awards.show', $award))->assertOk();
        $this->assertNamedLink($response, 'Historic Sales Navigation', route('admin.sales.show', $salesWinner->sales_id));
        $this->assertNamedLink($response, $dealerWinner->winner_name_snapshot, route('admin.dealers.show', $dealerWinner->dealer_id));
        $this->assertNamedLink($this->get(route('awards.show', $award))->assertOk(), 'Historic Dealer Navigation', route('dealers.show', $dealerWinner->dealer_id));
        $this->assertSame('Historic Sales Navigation', $salesWinner->fresh()->winner_name_snapshot);
    }

    public function test_search_filters_and_pagination_keep_profile_and_customer_links_working(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->count(21)->create(['sales_id' => $sales->id, 'customer_name' => 'NAVGROUP Customer']);
        $this->actingAs($admin);
        $url = route('admin.submissions.index', ['q' => 'NAVGROUP', 'status' => 'pending', 'dealer_id' => $manager->dealer_id]);
        $page = $this->get($url)->assertOk()->viewData('submissions');
        $response = $this->get($page->nextPageUrl())->assertOk()->assertViewHas('submissions', fn ($items) => $items->total() === 21 && $items->count() === 1);
        $this->assertNamedLink($response, $sales->name, route('admin.sales.show', $sales));
        $this->assertNamedLink($response, 'NAVGROUP Customer', route('admin.submissions.show', $response->viewData('submissions')->first()));
        $this->get(route('admin.submissions.show', $response->viewData('submissions')->first()))->assertOk();
        $this->actingAs($manager);
        $page = $this->get(route('dealer.customers.index', ['q' => 'NAVGROUP', 'status' => 'pending']))->assertOk()->viewData('customers');
        $response = $this->get($page->nextPageUrl())->assertOk()->assertViewHas('customers', fn ($items) => $items->total() === 21 && $items->count() === 1);
        $this->assertNamedLink($response, $sales->name, route('dealer.sales.show', $sales));
        $this->get(route('dealer.customers.show', $response->viewData('customers')->first()))->assertOk();
    }

    public function test_customer_detail_links_remain_private_for_html_json_and_evidence(): void
    {
        $submission = CustomerSubmission::factory()->create(['customer_name' => 'PRIVATE_NAV_CUSTOMER', 'phone' => 'PRIVATE_NAV_PHONE']);
        $urls = [route('admin.submissions.show', $submission), route('dealer.customers.show', $submission),
            route('sales.submissions.show', $submission), route('submissions.evidence', $submission)];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'))->assertDontSee($submission->customer_name);
            $this->getJson($url)->assertUnauthorized()->assertDontSee($submission->phone)->assertDontSee($submission->customer_name);
        }
        $this->actingAs(User::factory()->dealer()->create())->getJson(route('dealer.customers.show', $submission))->assertNotFound()->assertDontSee($submission->customer_name);
        $this->actingAs(User::factory()->active()->create())->getJson(route('sales.submissions.show', $submission))->assertForbidden()->assertDontSee($submission->customer_name);
        $this->actingAs($submission->sales)->get(route('sales.submissions.show', $submission))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))->assertOk();
    }

    public function test_dealer_and_sales_slugs_do_not_capture_login_logout_or_system_routes(): void
    {
        $dealer = Dealer::factory()->create(['name' => 'Login']);
        $sales = User::factory()->active()->create();
        $this->assertNotSame('login', $dealer->slug);
        $this->get('/login')->assertOk();
        $this->get('/logout')->assertStatus(405);
        $this->get('/sales/dashboard')->assertRedirect(route('login'));
        $this->get('/sales/submissions')->assertRedirect(route('login'));
        $this->get('/dealer/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get(route('sales.public.show', ['slug' => $sales->public_slug]))->assertOk();
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk();
        $this->get('/sales/not-a-profile-slug')->assertNotFound();
        $this->get('/customers/123')->assertNotFound();
    }

    public function test_missing_legacy_dealer_and_qr_sales_do_not_generate_broken_links(): void
    {
        $submission = CustomerSubmission::factory()->create();
        DB::table('customer_submissions')->where('id', $submission->id)->update(['dealer_id' => null]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))->assertOk();
        $response->assertSee('Chưa xác minh đại lý lịch sử');
        $this->assertNotContains(route('admin.dealers.index'), array_column(array_filter($this->links($response), fn ($link) => $link['name'] === 'Chưa xác minh đại lý lịch sử'), 'url'));
        $qr = CustomerSubmission::factory()->create([
            'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $submission->sales->dealer_id,
            'facebook_url' => null, 'phone_normalized' => '0901234567', 'vehicle_description' => 'Toyota Vios',
            'address_line' => 'Địa chỉ kiểm thử', 'province_code' => '79', 'ward_code' => '26734',
            'consented_at' => now(), 'consent_version' => config('dealer.consent_version'),
        ]);
        $this->get(route('admin.submissions.show', $qr))->assertOk()->assertSee('Khách tự đăng ký qua QR')->assertDontSee('/admin/sales/');
    }

    public function test_navigation_browser_fixtures(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id, 'name' => 'Sales Navigation QA']);
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $directory = base_path('.laravel-tools/profile-navigation/qa');
        File::ensureDirectoryExists($directory);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();
        File::put($directory.'/admin.html', $response->getContent());
        $response = $this->actingAs($manager)->get(route('dealer.customers.index'))->assertOk();
        File::put($directory.'/dealer.html', $response->getContent());
        File::put($directory.'/manifest.json', json_encode(['origin' => config('app.url'), 'salesName' => $sales->name, 'customerName' => $submission->customer_name,
            'dealerName' => $manager->dealer->name, 'adminSalesUrl' => route('admin.sales.show', $sales), 'adminDealerUrl' => route('admin.dealers.show', $manager->dealer_id),
            'adminCustomerUrl' => route('admin.submissions.show', $submission), 'dealerSalesUrl' => route('dealer.sales.show', $sales),
            'dealerCustomerUrl' => route('dealer.customers.show', $submission)], JSON_THROW_ON_ERROR));
    }
}
