<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicDealerDirectoryTest extends TestCase
{
    use DatabaseTransactions;

    private int $phoneSequence = 100;

    private function identity(): array
    {
        $phone = '099'.sprintf('%07d', ++$this->phoneSequence);

        return ['phone' => $phone, 'phone_normalized' => $phone];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function approved(Dealer $dealer, int $count, string $date = '2020-01-01 12:00:00'): void
    {
        $identity = $this->identity(...);
        CustomerSubmission::factory()->approved()->count($count)->state(fn () => $identity())->create([
            'source' => 'dealer_qr', 'sales_id' => null,
            'vehicle_description' => 'Toyota',
            'address_line' => 'Test address', 'province_code' => '01', 'ward_code' => '00001',
            'consented_at' => now(), 'consent_version' => 'v1', 'dealer_id' => $dealer->id,
            'submitted_at' => $date,
        ]);
    }

    public function test_directory_orders_by_all_time_approved_score_then_name_and_id_without_changing_rank(): void
    {
        $identity = $this->identity(...);
        $zero = Dealer::factory()->create(['name' => 'A Zero']);
        $beta = Dealer::factory()->create(['name' => 'Beta']);
        $alpha = Dealer::factory()->create(['name' => 'Alpha']);
        $alphaNext = Dealer::factory()->create(['name' => 'Alpha']);
        $top = Dealer::factory()->create(['name' => 'Zulu']);
        $this->approved($beta, 1);
        $this->approved($alpha, 1, '2021-01-01 12:00:00');
        $this->approved($alphaNext, 1);
        $this->approved($top, 3);
        CustomerSubmission::factory()->count(5)->state(fn () => $identity())->create([
            'source' => 'dealer_qr', 'sales_id' => null,
            'vehicle_description' => 'Toyota',
            'address_line' => 'Test address', 'province_code' => '01', 'ward_code' => '00001',
            'consented_at' => now(), 'consent_version' => 'v1', 'dealer_id' => $zero->id,
        ]);
        CustomerSubmission::factory()->rejected()->create([
            'source' => 'dealer_qr', 'sales_id' => null,
            'phone_normalized' => '0901234567', 'vehicle_description' => 'Toyota',
            'address_line' => 'Test address', 'province_code' => '01', 'ward_code' => '00001',
            'consented_at' => now(), 'consent_version' => 'v1', 'dealer_id' => $zero->id,
        ]);
        $ranks = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->pluck('rank', 'id');

        $response = $this->get(route('dealers.index'))->assertOk();
        $items = $response->viewData('dealers');
        $this->assertSame([$top->id, $alpha->id, $alphaNext->id, $beta->id, $zero->id], $items->pluck('id')->all());
        $this->assertSame([3, 1, 1, 1, 0], $items->pluck('score')->map(fn ($score) => (int) $score)->all());
        foreach ($items as $dealer) {
            $this->assertEquals($ranks[$dealer->id] ?? null, $dealer->rank);
        }
        $response->assertSeeInOrder(['Zulu', 'Alpha', 'Beta', 'A Zero'])->assertSee('3 thành viên (hồ sơ đã duyệt)')->assertSee('0 thành viên (hồ sơ đã duyệt)');
        $this->assertSame([3, 1, 1, 1, 0], $items->pluck('members_count')->map(fn ($count) => (int) $count)->all());
    }

    public function test_score_order_is_applied_before_pagination_and_preserved_for_search_without_n_plus_one(): void
    {
        $zeros = collect(range(1, 21))->map(fn ($i) => Dealer::factory()->create([
            'name' => sprintf('A Directory %02d', $i), 'province' => 'Directory Search',
        ]));
        $top = Dealer::factory()->create(['name' => 'Zulu Directory', 'province' => 'Directory Search']);
        $this->approved($top, 2);
        Dealer::factory()->create(['name' => 'Excluded', 'is_active' => false]);
        foreach ([[], ['q' => 'Directory'], ['q' => 'Directory Search']] as $filters) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $first = $this->get(route('dealers.index', $filters))->assertOk()->viewData('dealers');
            // One ownership lookup, paginator count/data, one public province list.
            $this->assertCount(4, DB::getQueryLog());
            $this->assertContains(ToyotaRealisticDemo::EMAIL, DB::getQueryLog()[0]['bindings']);
            $this->assertStringContainsString('select distinct `province`', DB::getQueryLog()[3]['query']);
            DB::disableQueryLog();
            $this->assertSame(22, $first->total());
            $this->assertSame([$top->id, ...$zeros->take(19)->pluck('id')->all()], $first->pluck('id')->all());
            $second = $this->get(route('dealers.index', [...$filters, 'page' => 2]))->assertOk()->viewData('dealers');
            $this->assertSame($zeros->slice(19)->pluck('id')->all(), $second->pluck('id')->all());
            if ($filters !== []) {
                $this->assertStringContainsString(http_build_query($filters, '', '&', PHP_QUERY_RFC3986), $first->url(2));
            }
        }
    }
}
