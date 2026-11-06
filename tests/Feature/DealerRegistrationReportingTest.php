<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\DealerRegistrationReceipt;
use App\Services\DealerRegistrationReport;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerRegistrationReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function updateWorkflow(CustomerSubmission $customer, array $values): void
    {
        $service = app(SubmissionVerification::class);
        $service->adminUpdate(User::factory()->admin()->create(), $customer->fresh(),
            $values + ['verification_version' => $service->version($customer->fresh(), $service->state($customer->id))]);
    }

    public function test_monthly_funnel_is_exclusive_scoped_and_does_not_infer_group_from_moderation(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(12));
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $new = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $group = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $requested = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $member = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $supplement = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $blocker = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subMonth()]);
        $foreign = CustomerSubmission::factory()->create(['customer_name' => 'FOREIGN_CUSTOMER_SECRET']);
        $this->updateWorkflow($group, ['action' => 'group', 'group_progress' => 'not_requested']);
        $this->updateWorkflow($requested, ['action' => 'group', 'group_progress' => 'requested']);
        $this->updateWorkflow($member, ['action' => 'group', 'group_progress' => 'member']);
        $this->updateWorkflow($supplement, ['action' => 'group', 'group_progress' => 'member']);
        $this->updateWorkflow($supplement, ['action' => 'supplement', 'supplement_reason' => 'missing_information']);
        $this->updateWorkflow($blocker, ['action' => 'blocker', 'blocker_code' => 'identity_conflict']);
        $before = $new->fresh()->getRawOriginal();
        $response = $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()->assertDontSee($foreign->customer_name);
        $report = $response->viewData('registrationReport');
        $this->assertSame(['new' => 1, 'group' => 2, 'verification' => 2, 'supplement' => 1, 'verified' => 1], $report['counts']);
        $this->assertSame(1, $report['rejected']);
        $this->assertSame(1, $report['stalled_count']); // Previous month's pending customer remains actionable.
        $this->assertSame($before, $new->fresh()->getRawOriginal());
    }

    public function test_overdue_uses_strict_24_hour_boundary_and_same_stage_reviews_do_not_reset_clock(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(12)->startOfHour());
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHours(24)]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHours(24)->subSecond()]);
        $group = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(3)]);
        $this->travelBack();
        $this->travelTo(now()->startOfMonth()->addDays(12)->startOfHour()->subHours(25));
        $this->updateWorkflow($group, ['action' => 'group', 'group_progress' => 'requested']);
        $this->travel(25)->hours();
        $this->updateWorkflow($group, ['action' => 'group', 'group_progress' => 'requested']);
        $group->forceFill(['notes' => 'Unrelated edit'])->save();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(2, $report['stalled_count']);
        $this->assertSame($group->id, $report['stalled'][0]['id']);
        $this->assertEquals(now()->subHours(25), $report['stalled'][0]['since']);
    }

    public function test_supplement_close_starts_new_step_and_stale_profile_has_no_guessed_clock(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10)->startOfHour());
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $customer = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $stale = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->updateWorkflow($customer, ['action' => 'group', 'group_progress' => 'requested']);
        $this->updateWorkflow($customer, ['action' => 'supplement', 'supplement_reason' => 'missing_information']);
        $this->updateWorkflow($stale, ['action' => 'group', 'group_progress' => 'requested']);
        $this->travel(26)->hours();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(2, $report['stalled_count']);
        $this->assertSame('Cần bổ sung', collect($report['stalled'])->firstWhere('id', $customer->id)['stage']);
        $this->updateWorkflow($customer, ['action' => 'close_supplement']);
        $stale->forceFill(['facebook_url' => 'https://www.facebook.com/changed.profile'])->save();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(0, $report['stalled_count']);
        $this->assertSame(1, $report['missing_clock']);
        $this->assertSame(1, $report['counts']['group']);
        $this->assertSame(1, $report['counts']['new']);
    }

    public function test_missing_timestamps_are_not_overdue_and_alert_list_is_bounded_oldest_first(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $unknown = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(3)]);
        DB::table('submission_verification_states')->insert(['submission_id' => $unknown->id, 'supplement_state' => 'open']);
        CustomerSubmission::factory()->count(7)->create(['sales_id' => $sales->id, 'submitted_at' => now()->subDays(3)]);
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(7, $report['stalled_count']);
        $this->assertCount(5, $report['stalled']);
        $this->assertSame(1, $report['missing_clock']);
        $this->assertNotContains($unknown->id, array_column($report['stalled'], 'id'));
    }

    public function test_report_queries_do_not_grow_per_customer(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $small = count(DB::getQueryLog());
        CustomerSubmission::factory()->count(20)->create(['sales_id' => $sales->id]);
        DB::flushQueryLog();
        app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 1, $large);
    }

    public function test_membership_confirmation_starts_verification_step_after_group_wait(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $customer = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->updateWorkflow($customer, ['action' => 'group', 'group_progress' => 'requested']);
        $this->travel(26)->hours();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(1, $report['stalled_count']);
        $this->assertSame('Chờ vào group', $report['stalled'][0]['stage']);
        $this->updateWorkflow($customer, ['action' => 'group', 'group_progress' => 'member']);
        $this->assertSame(0, app(DealerRegistrationReport::class)->forDealer($manager->dealer)['stalled_count']);
        $this->travel(25)->hours();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(1, $report['stalled_count']);
        $this->assertSame('Chờ xác minh', $report['stalled'][0]['stage']);
    }

    public function test_customer_supplement_response_starts_verification_clock_without_changing_moderation(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $customer = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->updateWorkflow($customer, ['action' => 'supplement', 'supplement_reason' => 'facebook_unavailable']);
        $this->travel(26)->hours();
        $this->assertSame(1, app(DealerRegistrationReport::class)->forDealer($manager->dealer)['stalled_count']);
        $this->startSession();
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        DB::table('dealer_registration_receipts')->insert([
            'draft_key' => hash('sha256', 'isolated-report-response'),
            'owner_key' => app(DealerRegistrationReceipt::class)->owner($request, $manager->dealer),
            'submission_id' => $customer->id,
            'confirmation' => Crypt::encryptString('{}'),
            'expires_at' => now()->addDay(), 'created_at' => now(),
        ]);
        $service = app(SubmissionVerification::class);
        $state = $service->state($customer->id);
        $service->respond($request, $manager->dealer, $state->supplement_key,
            $service->version($customer->fresh(), $state), 'https://www.facebook.com/customer.corrected.profile');
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(0, $report['stalled_count']);
        $this->assertSame('pending', $customer->fresh()->status->value);
        $this->travel(25)->hours();
        $report = app(DealerRegistrationReport::class)->forDealer($manager->dealer);
        $this->assertSame(1, $report['stalled_count']);
        $this->assertSame('Chờ xác minh', $report['stalled'][0]['stage']);
    }
}
