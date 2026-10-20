<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerBrowserFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_pages_render_using_only_isolated_test_fixtures(): void
    {
        TestDatabase::assertIsolated();
        Storage::fake('submission_evidence');
        Storage::fake('sales_avatars');
        $directory = base_path('.laravel-tools/dealer-dashboard/qa');
        File::ensureDirectoryExists($directory);
        $owner = User::factory()->dealer()->create(['name' => 'Quản lý đại lý']);
        $dealer = $owner->dealer;
        $dealer->update(['name' => 'Toyota An Sương · Kiểm thử', 'address' => '382 Lê Quang Đạo, Thành phố Hồ Chí Minh', 'phone' => '19007339']);
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id, 'name' => 'Nhân viên kinh doanh kiểm thử']);
        User::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Nhân viên đang chờ duyệt']);
        $evidence = UploadedFile::fake()->image('evidence.jpg', 600, 300);
        Storage::disk('submission_evidence')->put('test.jpg', file_get_contents($evidence->getRealPath()));
        $customer = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'Khách hàng kiểm thử',
            'submitted_at' => now()->subHour(), 'evidence_image_path' => 'test.jpg', 'address_line' => '12 Đường kiểm thử',
            'province_name' => 'Thành phố Hồ Chí Minh', 'ward_name' => 'Phường An Hội Tây']);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'Khách hàng đang chờ duyệt']);
        $admin = User::factory()->admin()->create();
        $award = Award::factory()->weekly()->create();
        AwardWinner::factory()->dealer()->create(['award_id' => $award->id, 'dealer_id' => $dealer->id, 'dealer_name_snapshot' => 'Toyota An Sương']);
        $award->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        $pages = [];
        $save = function (string $url) use ($directory, &$pages) {
            $response = $this->get($url)->assertOk();
            $path = parse_url($url, PHP_URL_PATH);
            $file = 'page-'.count($pages).'.html';
            File::put($directory.'/'.$file, $response->getContent());
            $pages[$path] = $file;
        };
        $save(app(DealerProfile::class)->url($dealer));
        $this->actingAs($owner);
        foreach ([route('dealer.dashboard'), route('dealer.sales.index'), route('dealer.sales.show', $sales),
            route('dealer.customers.index'), route('dealer.customers.show', $customer), route('dealer.awards.index')] as $url) {
            $save($url);
        }
        $this->actingAs($admin);
        $save(route('admin.dealers.account.edit', $dealer));
        $this->get(route('dealers.qr', $dealer))->assertOk();
        File::put($directory.'/qr.png', app(DealerProfile::class)->qr($dealer));
        File::put($directory.'/evidence.jpg', Storage::disk('submission_evidence')->get('test.jpg'));
        File::put($directory.'/manifest.json', json_encode(['pages' => $pages, 'dealerId' => $dealer->id,
            'publicUrl' => app(DealerProfile::class)->url($dealer), 'evidenceUrl' => parse_url(route('submissions.evidence', $customer), PHP_URL_PATH)], JSON_PRETTY_PRINT));
    }
}
