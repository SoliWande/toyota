<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CompetitionDealerTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array', 'dealer-registration.approved_call_phones' => []]);
    }

    public function test_public_profile_is_separate_from_qr_landing_and_does_not_expose_private_data(): void
    {
        $dealer = Dealer::factory()->create(['phone' => '0901234567']);
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id, 'name' => 'PRIVATE_STAFF', 'phone' => 'PRIVATE_PHONE', 'admin_note' => 'PRIVATE_NOTE']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'PRIVATE_CUSTOMER', 'notes' => 'PRIVATE_SUBMISSION']);
        $url = route('dealers.competition', $dealer);
        $response = $this->get($url)->assertOk()->assertSee($dealer->name)->assertSee('Thành viên mới 3 tháng gần nhất')
            ->assertDontSee($dealer->phone)->assertDontSee('href="tel:', false)
            ->assertDontSee('PRIVATE_STAFF')->assertDontSee('PRIVATE_PHONE')->assertDontSee('PRIVATE_NOTE')
            ->assertDontSee('PRIVATE_CUSTOMER')->assertDontSee('PRIVATE_SUBMISSION')->assertDontSee($sales->email);
        $this->assertSame(1, (int) $response->viewData('standing')->score);
        $response->assertViewMissing('customers')->assertViewMissing('sales');
        $this->assertArrayNotHasKey('photo_path', $response->viewData('dealer')->toArray());
        $this->assertArrayNotHasKey('phone', $response->viewData('dealer')->toArray());
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->sole()->score);
        $this->get(route('dealers.index'))->assertOk()->assertDontSee($dealer->phone)->assertSee($url, false);
        $this->get(route('dealers.show', $dealer))->assertRedirect(app(DealerProfile::class)->url($dealer))->assertStatus(301);
        $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertSee('data-landing-screen', false);
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk()->assertSee($url, false);
        $this->get(route('dealers.competition', 999999999))->assertNotFound();
    }

    public function test_phone_requires_exact_approval_and_inactive_dealer_has_no_intake_or_call_cta(): void
    {
        $dealer = Dealer::factory()->create(['phone' => '0901234567']);
        config(['dealer-registration.approved_call_phones' => [$dealer->slug => '0901234567']]);
        $this->get(route('dealers.competition', $dealer))->assertOk()->assertSee('href="tel:0901234567"', false)
            ->assertSee(route('dealers.profile', $dealer->slug).'#registration', false);
        $dealer->forceFill(['phone' => '0901234568'])->save();
        $this->get(route('dealers.competition', $dealer))->assertOk()->assertDontSee('href="tel:', false)->assertDontSee($dealer->phone);
        $dealer->forceFill(['is_active' => false, 'phone' => '0901234567'])->save();
        $this->get(route('dealers.competition', $dealer))->assertOk()->assertSee('Đại lý hiện ngừng nhận đăng ký.')
            ->assertDontSee('href="tel:', false)->assertDontSee('#registration', false);
        $this->get(route('dealers.index'))->assertOk()->assertDontSee($dealer->name);
    }

    public function test_three_month_chart_uses_submitted_at_approved_snapshot_dealer_and_exact_month_bounds(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        foreach (['2026-09-01', '2026-10-31', '2026-11-01'] as $index => $date) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $date.' 00:00:00',
                'license_plate' => '51K-'.(100 + $index).'.01']);
        }
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-11-02 00:00:00', 'license_plate' => '51K-103.01']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-08-31 23:59:59', 'license_plate' => '51K-104.01']);
        $destination = Dealer::factory()->create();
        $sales->forceFill(['dealer_id' => $destination->id])->save();
        $response = $this->get(route('dealers.competition', $dealer))->assertOk();
        $this->assertSame([1, 1, 1], array_column($response->viewData('months'), 'score'));
        $this->assertSame(['2026-09', '2026-10', '2026-11'], array_column($response->viewData('months'), 'period'));
        $this->assertSame(4, (int) $response->viewData('standing')->score);
        $empty = $this->get(route('dealers.competition', $destination))->assertOk();
        $this->assertSame([0, 0, 0], array_column($empty->viewData('months'), 'score'));
        $empty->assertViewHas('standing', null);
    }

    public function test_directory_province_search_and_pagination_preserve_all_time_rank_and_do_not_lose_rows(): void
    {
        $rows = Dealer::factory()->count(21)->create(['province' => 'QA PROVINCE']);
        Dealer::factory()->create(['province' => 'OTHER_PROVINCE']);
        $params = ['q' => 'QA', 'province' => 'QA PROVINCE'];
        $a = $this->get(route('dealers.index', $params))->assertOk();
        $b = $this->get(route('dealers.index', [...$params, 'page' => 2]))->assertOk();
        $first = $a->viewData('dealers');
        $second = $b->viewData('dealers');
        $this->assertSame(21, $first->total());
        $this->assertCount(20, $first);
        $this->assertCount(1, $second);
        $this->assertEqualsCanonicalizing($rows->pluck('id')->all(), [...$first->pluck('id')->all(), ...$second->pluck('id')->all()]);
        $this->assertStringContainsString('province=QA%20PROVINCE', $first->url(2));
        $this->get(route('dealers.index', ['province' => 'UNMATCHED']))->assertOk()->assertSee('Không tìm thấy đại lý phù hợp.');
        $this->getJson(route('dealers.index', ['province' => ['invalid']]))->assertUnprocessable();
    }

    public function test_photos_use_existing_private_media_endpoint_and_absent_files_use_fallback(): void
    {
        Storage::fake('dealer_photos');
        $dealer = Dealer::factory()->create(['photo_path' => 'PRIVATE_PHOTO.jpg']);
        $this->get(route('dealers.competition', $dealer))->assertOk()->assertViewHas('photoUrl', null)->assertDontSee('PRIVATE_PHOTO.jpg');
        Storage::disk('dealer_photos')->put('PRIVATE_PHOTO.jpg', 'isolated-image');
        $this->get(route('dealers.competition', $dealer))->assertOk()->assertViewHas('photoUrl', route('dealers.photo', $dealer))
            ->assertDontSee('PRIVATE_PHOTO.jpg');
    }

    public function test_profile_query_budget_is_constant_when_dealer_has_more_staff(): void
    {
        $dealer = Dealer::factory()->create();
        foreach ([1, 20] as $additionalStaff) {
            User::factory()->active()->count($additionalStaff)->create(['dealer_id' => $dealer->id]);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('dealers.competition', $dealer))->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            // Dealer projection + four independent aggregates, each with its existing ownership lookup.
            $this->assertCount(9, $queries);
            $ownership = array_filter($queries, fn ($query) => in_array(ToyotaRealisticDemo::EMAIL, $query['bindings'], true));
            $this->assertCount(4, $ownership);
        }
    }

    public function test_province_filter_does_not_recalculate_global_rank(): void
    {
        $top = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->count(2)->create(['sales_id' => $top->id]);
        $inside = Dealer::factory()->create(['province' => 'ONLY_THIS_PROVINCE']);
        $sales = User::factory()->active()->create(['dealer_id' => $inside->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $rows = $this->get(route('dealers.index', ['province' => $inside->province]))->assertOk()->viewData('dealers');
        $this->assertSame($inside->id, $rows->sole()->id);
        $this->assertSame(2, (int) $rows->sole()->rank);
        $this->assertSame(1, (int) $rows->sole()->score);
    }
}
