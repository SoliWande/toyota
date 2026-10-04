<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class V1WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_registration_through_review_and_period_rankings_using_real_http_endpoints(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        date_default_timezone_set(config('app.timezone'));
        Storage::fake('submission_evidence');
        $october = CarbonImmutable::parse('2026-10-31 15:00:00', config('app.timezone'));
        $this->travelTo($october);
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $registration = [
            'name' => 'Sales QA', 'email' => 'sales-qa@example.test', 'dealer_id' => $dealer->id,
            'password' => 'testing-qa-password', 'password_confirmation' => 'testing-qa-password',
        ];
        $this->post(route('register'), $registration + ['role' => 'admin', 'status' => 'active'])->assertSessionHasErrors(['role', 'status']);
        $this->assertDatabaseMissing('users', ['email' => $registration['email']]);
        $this->post(route('register'), $registration)->assertSessionHasNoErrors()->assertRedirect(route('account.status'));
        $sales = User::where('email', 'sales-qa@example.test')->sole();
        $this->assertSame('sales', $sales->role->value);
        $this->assertSame('pending', $sales->status->value);
        $this->get(route('sales.dashboard'))->assertRedirect(route('account.status'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($admin)->post(route('admin.sales.review', ['sales' => $sales, 'action' => 'approve']))->assertSessionHasNoErrors();
        $this->assertSame('active', $sales->fresh()->status->value);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $sales->email, 'password' => 'testing-qa-password'])
            ->assertSessionHasNoErrors()->assertRedirect(route('sales.dashboard'));
        $this->get(route('sales.dashboard'))->assertOk();
        $data = [
            'customer_name' => 'Private QA customer', 'facebook_url' => 'https://www.facebook.com/qa.customer.final',
            'customer_phone' => '0000000000', 'note' => 'Private QA note',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2022, 'vehicle_color' => 'Trắng', 'license_plate' => '29D-428.12',
        ];
        $this->post(route('sales.submissions.store'), $data + ['evidence_image' => UploadedFile::fake()->image('evidence.png')])
            ->assertSessionHasNoErrors();
        $submission = $sales->submissions()->sole();
        $submittedAt = $submission->submitted_at;
        $image = $submission->evidence_image_path;
        $this->put(route('sales.submissions.update', $submission), array_replace($data, ['vehicle_color' => 'Bạc']))->assertSessionHasNoErrors();
        $this->assertSame('Bạc', $submission->fresh()->vehicle_color);
        $this->assertTrue($submittedAt->equalTo($submission->fresh()->submitted_at));
        $this->assertSame($image, $submission->fresh()->evidence_image_path);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', config('app.timezone')));
        $this->post(route('logout'));
        $this->actingAs($admin)->get(route('admin.submissions.show', $submission))->assertOk()->assertSee('Mở Facebook');
        $this->post(route('admin.submissions.approve', $submission), ['admin_note' => 'Private admin QA note'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $submission->fresh()->status->value);
        $this->assertSame($admin->id, $submission->fresh()->reviewed_by);
        $this->assertTrue($submittedAt->equalTo($submission->fresh()->submitted_at));
        $this->assertSame(1, $submission->moderationReviews()->count());
        $this->post(route('logout'));
        $this->actingAs($sales->fresh())->put(route('sales.submissions.update', $submission), $data)->assertForbidden();
        $this->delete(route('sales.submissions.destroy', $submission))->assertForbidden();
        $duplicateData = array_replace($data, ['facebook_url' => 'https://m.facebook.com/QA.Customer.Final/?ref=qa']);
        $this->post(route('sales.submissions.store'), $duplicateData + ['evidence_image' => UploadedFile::fake()->image('other.png')])
            ->assertSessionHasNoErrors();
        $duplicate = $sales->submissions()->where('status', 'pending')->sole();
        $this->post(route('logout'));
        $this->actingAs($admin)->get(route('admin.submissions.show', $duplicate))->assertOk()->assertViewHas('duplicate', fn ($row) => $row->id === $submission->id);
        $this->post(route('admin.submissions.approve', $duplicate))->assertSessionHasErrors('duplicate');
        $this->assertSame('pending', $duplicate->fresh()->status->value);
        $this->assertSame(0, $duplicate->moderationReviews()->count());
        $this->post(route('admin.submissions.reject', $duplicate), ['rejection_reason' => 'Profile đã ghi nhận trước đó.', 'admin_note' => 'Private reject QA note'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $duplicate->fresh()->status->value);
        $this->assertSame(1, $duplicate->moderationReviews()->count());
        $service = app(LeaderboardService::class);
        foreach ([LeaderboardPeriod::Week, LeaderboardPeriod::Month] as $period) {
            $this->assertSame(1, (int) $service->sales($period, $october)->first()->score);
            $this->assertSame(1, (int) $service->dealers($period, $october)->first()->score);
            $this->assertTrue($service->sales($period)->get()->isEmpty());
        }
        $this->assertSame(1, (int) $service->sales(LeaderboardPeriod::AllTime)->first()->score);
        $this->assertSame($dealer->id, $service->dealers(LeaderboardPeriod::AllTime)->first()->id);
        foreach (['weekly', 'monthly'] as $type) {
            $this->post(route('admin.awards.store'), ['period_type' => $type, 'period_date' => '2026-10-31'])->assertSessionHasNoErrors();
            $award = Award::where('period_type', $type)->sole();
            $this->get(route('admin.awards.show', $award))->assertSessionHasNoErrors()->assertOk()->assertViewHas('preview', fn ($preview) => $preview['sales']->first()['score'] === 1);
            $this->get(route('awards.show', $award))->assertNotFound();
        }
        $this->post(route('logout'));
        $this->actingAs($sales->fresh())->get(route('sales.dashboard'))->assertOk()->assertViewHas('currentRank', 1);
        $this->get(route('sales.submissions.show', $duplicate))->assertOk()->assertSee('Profile đã ghi nhận trước đó.')->assertDontSee('Private reject QA note');
        $this->get(route('admin.dashboard'))->assertForbidden();
        $otherSales = User::factory()->active()->create();
        $this->post(route('logout'));
        $this->actingAs($otherSales)->get(route('sales.submissions.show', $submission))->assertForbidden();
        $this->get(route('submissions.evidence', $submission))->assertForbidden();
        $this->post(route('logout'));
        foreach (['home', 'leaderboard', 'awards.index', 'login', 'register'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Private QA customer')->assertDontSee('Private QA note')->assertDontSee('Private admin QA note');
        }
    }
}
