<?php

namespace Tests\Feature;

use App\Actions\UpdateCustomerSubmission;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\LeaderboardService;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminSubmissionActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function url(string $action, CustomerSubmission $row): string
    {
        return route('admin.submissions.'.$action, ['submission' => $row, 'workflow' => 'pending', 'status' => 'pending',
            'dealer_id' => $row->dealer_id, 'selected' => $row->id, 'page' => 2, 'c02' => 1]);
    }

    private function edit(CustomerSubmission $row): array
    {
        return ['customer_name' => $row->customer_name, 'phone' => $row->phone, 'vehicle_model' => $row->vehicle_model,
            'license_plate' => $row->license_plate, 'dealer_id' => $row->dealer_id,
            'edit_submission_id' => (string) $row->id, 'edit_version' => UpdateCustomerSubmission::version($row)];
    }

    public function test_queue_approval_uses_real_review_and_score_once_without_implying_group_membership(): void
    {
        $row = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson($this->url('approve', $row), ['review_submission_id' => (string) ($row->id + 1)])->assertUnprocessable();
        $this->assertDatabaseCount('moderation_reviews', 0);
        $result = $this->postJson($this->url('approve', $row), ['review_submission_id' => (string) $row->id])->assertOk();
        $result->assertJsonPath('message', 'Đã xác minh '.$row->customer_name.' · đại lý +1 điểm');
        $this->postJson($this->url('approve', $row), ['review_submission_id' => (string) $row->id])->assertUnprocessable();
        $this->assertDatabaseCount('moderation_reviews', 1);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->sole()->score);
        $this->assertDatabaseCount('submission_verification_states', 0);
    }

    public function test_inline_supplement_reuses_version_reason_audit_and_never_claims_delivery_or_points(): void
    {
        $row = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $service = app(SubmissionVerification::class);
        $payload = ['action' => 'supplement', 'supplement_reason' => 'missing_information', 'verification_version' => $service->version($row)];
        $this->postJson($this->url('verification', $row), [...$payload, 'supplement_reason' => 'plate_mismatch'])->assertUnprocessable();
        $this->postJson($this->url('verification', $row), $payload)->assertOk()->assertExactJson(['message' => null]);
        $this->postJson($this->url('verification', $row), $payload)->assertUnprocessable();
        $this->assertSame('open', $service->state($row->id)->supplement_state);
        $this->assertSame('pending', $row->fresh()->status->value);
        $this->assertDatabaseCount('submission_verification_audits', 1);
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->assertSame(0, app(LeaderboardService::class)->dealerScores()->count());
        $this->actingAs(User::factory()->active()->create())->postJson($this->url('verification', $row), $payload)->assertForbidden();
    }

    public function test_modal_edit_rejects_wrong_target_stale_versions_and_prohibited_facebook_and_only_toasts_changes(): void
    {
        $row = CustomerSubmission::factory()->create(['vehicle_model' => 'hilux']);
        $payload = $this->edit($row);
        $this->actingAs(User::factory()->admin()->create());
        $this->patchJson($this->url('update', $row), [...$payload, 'edit_submission_id' => '999999'])->assertUnprocessable();
        $this->patchJson($this->url('update', $row), [...$payload, 'facebook_url' => 'https://facebook.com/forged'])->assertUnprocessable();
        $this->patchJson($this->url('update', $row), $payload)->assertOk()->assertExactJson(['message' => null]);
        $this->assertDatabaseCount('submission_edits', 0);
        $this->patchJson($this->url('update', $row), [...$payload, 'customer_name' => 'Đã sửa có audit'])->assertOk()
            ->assertJsonPath('message', 'Đã lưu thông tin khách · ghi vào lịch sử');
        $this->patchJson($this->url('update', $row), [...$payload, 'customer_name' => 'Gửi lại phiên cũ'])->assertUnprocessable();
        $this->assertSame('Đã sửa có audit', $row->fresh()->customer_name);
        $this->assertDatabaseCount('submission_edits', 1);
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public function test_qr_approval_requires_explicit_verified_profile_and_edit_keeps_required_address_validation(): void
    {
        $row = CustomerSubmission::factory()->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => Dealer::factory()->create()->id, 'facebook_url' => null,
            'phone' => '0909123456', 'phone_normalized' => '0909123456', 'address_line' => 'Fixture', 'province_code' => '01', 'ward_code' => '00001',
            'vehicle_description' => 'Hilux', 'consented_at' => now(), 'consent_version' => 'v1']);
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson($this->url('approve', $row), ['review_submission_id' => (string) $row->id])->assertUnprocessable();
        $payload = $this->edit($row);
        unset($payload['vehicle_model']);
        $this->patchJson($this->url('update', $row), [...$payload, 'vehicle_description' => 'Hilux'])->assertUnprocessable()
            ->assertJsonValidationErrors(['address_line', 'province_code', 'ward_code']);
        $this->assertSame('pending', $row->fresh()->status->value);
        $this->assertDatabaseCount('submission_edits', 0);
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public function test_legacy_rejection_retains_safe_confirmation_public_reason_private_note_and_queue_context(): void
    {
        $row = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $url = $this->url('show', $row).'&review=reject';
        $this->get($url)->assertOk()->assertSee('Xác nhận từ chối')->assertSee('name="return_to_queue"', false)
            ->assertSee('workflow=pending', false)->assertSee('c02=1', false);
        $response = $this->post($this->url('reject', $row), ['review_submission_id' => (string) $row->id,
            'return_to_queue' => 1, 'rejection_reason' => 'Lý do cho Sales', 'admin_note' => 'Chỉ Admin']);
        $response->assertRedirect()->assertSessionHas('cq_toast', 'Đã từ chối khai báo · sales sẽ thấy lý do');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $context);
        $this->assertEquals(['workflow' => 'pending', 'status' => 'pending', 'dealer_id' => $row->dealer_id, 'selected' => $row->id, 'page' => 2], $context);
        $this->assertSame('rejected', $row->fresh()->status->value);
        $this->assertSame('Lý do cho Sales', $row->fresh()->rejection_reason);
        $this->assertDatabaseHas('moderation_reviews', ['customer_submission_id' => $row->id, 'admin_note' => 'Chỉ Admin']);
    }

    public function test_empty_last_page_clamps_without_losing_filters_and_modal_does_not_submit_unsupported_fields(): void
    {
        $row = CustomerSubmission::factory()->create(['vehicle_model' => 'hilux']);
        $this->actingAs(User::factory()->admin()->create());
        $redirect = $this->get(route('admin.submissions.index', ['workflow' => 'pending', 'selected' => $row->id, 'page' => 2, 'q' => $row->customer_name]))->assertRedirect();
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $context);
        $this->assertEquals(['workflow' => 'pending', 'selected' => $row->id, 'page' => 1, 'q' => $row->customer_name, 'status' => 'pending'], $context);
        $this->get(route('admin.submissions.index', ['selected' => $row->id]))->assertOk()
            ->assertSee('Sửa thông tin khách')->assertSee('name="edit_version"', false)
            ->assertDontSee('name="facebook_url"', false)->assertDontSee('name="first_registration_year"', false)
            ->assertDontSee('name="province_code"', false)->assertDontSee('Hoàn tác')->assertDontSee('Đã gửi yêu cầu');
        $this->assertSame(0, DB::table('submission_edits')->count());
    }

    public function test_qr_modal_payload_keeps_required_existing_address_and_edits_without_assigning_sales(): void
    {
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];
        $ward = $directory->wards($province['code'])[0];
        $row = CustomerSubmission::factory()->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => Dealer::factory()->create()->id,
            'facebook_url' => null, 'phone' => '0909876543', 'phone_normalized' => '0909876543', 'address_line' => 'Địa chỉ giữ nguyên',
            'province_code' => $province['code'], 'ward_code' => $ward['code'], 'vehicle_description' => 'Toyota Hilux', 'consented_at' => now(), 'consent_version' => 'v1']);
        $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index', ['selected' => $row->id]))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $payload = [];
        foreach ($xpath->query('//form[@id="cq-edit-form"]//input[@name and not(@disabled)]') as $input) {
            $payload[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $payload['dealer_id'] = $row->dealer_id;
        $payload['customer_name'] = 'Tên khách QR đã sửa';
        $this->patchJson($this->url('update', $row), $payload)->assertOk()->assertJsonPath('message', 'Đã lưu thông tin khách · ghi vào lịch sử');
        $fresh = $row->fresh();
        $this->assertNull($fresh->sales_id);
        $this->assertSame('Địa chỉ giữ nguyên', $fresh->address_line);
        $this->assertSame($ward['code'], $fresh->ward_code);
        $this->assertDatabaseCount('submission_edits', 1);
    }

    public function test_c02_mutations_require_real_csrf_and_admin_authority(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $row = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson($this->url('approve', $row), ['review_submission_id' => (string) $row->id])->assertStatus(419);
        $this->postJson($this->url('reject', $row), ['review_submission_id' => (string) $row->id])->assertStatus(419);
        $this->patchJson($this->url('update', $row), $this->edit($row))->assertStatus(419);
        $this->postJson($this->url('verification', $row), ['action' => 'supplement'])->assertStatus(419);
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->assertDatabaseCount('submission_edits', 0);
        $this->assertDatabaseCount('submission_verification_audits', 0);
    }
}
