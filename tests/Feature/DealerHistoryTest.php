<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class DealerHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Dealer history tests may only refresh toyota_testing.');
        }
    }

    private function data(string $identity): array
    {
        return ['customer_name' => 'Customer', 'facebook_url' => 'https://facebook.com/'.$identity, 'vehicle_model' => 'hilux', 'first_registration_year' => 2022, 'vehicle_color' => 'White', 'license_plate' => '29D-428.12', 'evidence_image' => UploadedFile::fake()->image('evidence.jpg')];
    }

    public function test_transfer_keeps_historical_dealer_new_submissions_use_new_dealer_and_scores_split(): void
    {
        Storage::fake('submission_evidence');
        $admin = User::factory()->admin()->create();
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealerA->id]);
        $this->actingAs($sales)->post(route('sales.submissions.store'), $this->data('history.first') + ['dealer_id' => $dealerB->id, 'sales_id' => $admin->id])->assertSessionHasNoErrors();
        $first = CustomerSubmission::sole();
        $this->assertSame($dealerA->id, $first->dealer_id);
        $this->assertSame($sales->id, $first->sales_id);
        $this->actingAs($admin)->post(route('admin.submissions.approve', $first))->assertSessionHasNoErrors();
        $this->patch(route('admin.sales.update', $sales), ['name' => $sales->name, 'phone' => '0901234567', 'dealer_id' => $dealerB->id])->assertSessionHasNoErrors();
        $this->assertSame($dealerB->id, $sales->fresh()->dealer_id);
        $this->assertSame($dealerA->id, $first->fresh()->dealer_id);
        $this->get(route('admin.submissions.show', $first))->assertOk()->assertSee($dealerA->name);
        $this->get(route('admin.submissions.index', ['status' => 'approved', 'dealer_id' => $dealerA->id]))->assertOk()->assertViewHas('submissions', fn ($items) => $items->total() === 1);
        $this->get(route('admin.submissions.index', ['status' => 'approved', 'dealer_id' => $dealerB->id]))->assertViewHas('submissions', fn ($items) => $items->total() === 0);
        $this->actingAs($sales->fresh())->post(route('sales.submissions.store'), $this->data('history.second') + ['dealer_id' => $dealerA->id])->assertSessionHasNoErrors();
        $second = CustomerSubmission::whereKeyNot($first->id)->sole();
        $this->assertSame($dealerB->id, $second->dealer_id);
        $this->actingAs($admin)->post(route('admin.submissions.approve', $second))->assertSessionHasNoErrors();
        $service = app(LeaderboardService::class);
        foreach (LeaderboardPeriod::cases() as $period) {
            $scores = $service->dealers($period)->get()->keyBy('id');
            $this->assertSame(1, (int) $scores[$dealerA->id]->score);
            $this->assertSame(1, (int) $scores[$dealerB->id]->score);
            $this->assertSame(2, (int) $service->sales($period)->sole()->score);
        }
        $award = Award::factory()->create(['period_start' => now()->startOfMonth(), 'period_end' => now()->startOfMonth()->addMonth()]);
        $preview = app(AwardResults::class)->preview($award);
        $this->assertCount(2, $preview['dealers']);
    }

    public function test_pending_edit_after_transfer_does_not_change_snapshot(): void
    {
        Storage::fake('submission_evidence');
        $sales = User::factory()->active()->create();
        $old = $sales->dealer_id;
        $pending = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $sales->dealer_id = Dealer::factory()->create()->id;
        $sales->save();
        $this->actingAs($sales)->put(route('sales.submissions.update', $pending), $this->data('history.edited') + ['dealer_id' => $sales->dealer_id])->assertSessionHasNoErrors();
        $this->assertSame($old, $pending->fresh()->dealer_id);
        $this->assertSame('https://facebook.com/history.edited', $pending->fresh()->facebook_url);
    }

    public function test_unknown_legacy_dealer_is_not_inferred_but_sales_still_receives_score(): void
    {
        $submission = CustomerSubmission::factory()->approved()->create();
        // Simulate an existing row created before the snapshot migration.
        DB::table('customer_submissions')->where('id', $submission->id)->update(['dealer_id' => null]);
        $this->assertSame(1, (int) app(LeaderboardService::class)->salesScores()->sole()->score);
        $this->assertCount(0, app(LeaderboardService::class)->dealerScores()->get());
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))->assertOk()->assertSee('Chưa xác minh đại lý lịch sử');
    }

    public function test_published_snapshot_survives_name_dealer_and_account_status_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $award = Award::factory()->create();
        $winner = AwardWinner::factory()->create(['award_id' => $award->id, 'sales_id' => $sales->id]);
        $award->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        $before = $winner->refresh()->getAttributes();
        $this->actingAs($admin)->patch(route('admin.sales.update', $sales), ['name' => 'New Name', 'phone' => '0901234567', 'dealer_id' => Dealer::factory()->create()->id])->assertSessionHasNoErrors();
        $this->post(route('admin.sales.review', [$sales, 'block']))->assertSessionHasNoErrors();
        $this->assertSame($before, $winner->fresh()->getAttributes());
        $this->post(route('logout'));
        $this->get(route('awards.show', $award))->assertOk()->assertSee($winner->winner_name_snapshot)->assertSee($winner->dealer_name_snapshot)->assertDontSee('New Name');
    }

    public function test_model_rejects_modifying_submission_attribution(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $old = $submission->dealer_id;
        try {
            $submission->dealer_id = Dealer::factory()->create()->id;
            $submission->save();
            $this->fail('Historical dealer changed.');
        } catch (LogicException) {
            $this->assertSame($old, $submission->fresh()->dealer_id);
        }
    }

    public function test_dealer_with_only_submission_history_cannot_be_deleted(): void
    {
        $sales = User::factory()->active()->create();
        $dealer = $sales->dealer;
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $sales->dealer_id = Dealer::factory()->create()->id;
        $sales->save();
        $this->expectException(LogicException::class);
        $dealer->delete();
    }
}
