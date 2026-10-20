<?php

namespace Tests\Feature;

use App\Actions\UpdateCustomerSubmission;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\SubmissionEdit;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSubmissionInlineTest extends TestCase
{
    use RefreshDatabase;

    private function payload(CustomerSubmission $submission, array $changes = []): array
    {
        return array_replace(['customer_name' => $submission->customer_name, 'phone' => $submission->phone,
            'vehicle_model' => $submission->vehicle_model, 'edit_version' => UpdateCustomerSubmission::version($submission->fresh()),
            'edit_submission_id' => (string) $submission->id], $changes);
    }

    private function qr(): CustomerSubmission
    {
        $province = app(AdministrativeDivisions::class)->provinces()[0];
        $ward = app(AdministrativeDivisions::class)->wards($province['code'])[0];

        return CustomerSubmission::factory()->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => Dealer::factory()->create()->id,
            'facebook_url' => null, 'phone' => '0901234567', 'phone_normalized' => '0901234567', 'vehicle_description' => 'Toyota Vios',
            'address_line' => 'Địa chỉ riêng tư', 'province_code' => $province['code'], 'ward_code' => $ward['code'],
            'consented_at' => now(), 'consent_version' => config('dealer.consent_version')]);
    }

    public function test_list_renders_phone_historical_dealer_sales_source_and_inline_controls(): void
    {
        $sales = User::factory()->active()->create();
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'phone' => '0901234567']);
        $old = $submission->dealer;
        $new = Dealer::factory()->create();
        $sales->forceFill(['dealer_id' => $new->id])->save();
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()
            ->assertSee($submission->phone)->assertSee($old->name)->assertSee($sales->name)->assertSee('Sales khai báo')
            ->assertSee('Sales hiện thuộc đại lý khác')->assertSee('Chỉnh sửa')->assertSee('Lưu thay đổi')
            ->assertSee('Xác nhận duyệt')->assertDontSee('confirm(', false)->assertDontSee('onsubmit=', false);
        $this->assertSame($old->id, $response->viewData('submissions')->sole()->dealer_id);
    }

    public function test_qr_rows_remain_without_sales_and_edit_without_assigning_one(): void
    {
        $qr = $this->qr();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.submissions.index'))->assertOk()->assertSee('Khách tự đăng ký')->assertSee('Khách tự đăng ký qua QR Dealer');
        $data = $this->payload($qr, ['customer_name' => 'Tên QR đã sửa', 'province_code' => $qr->province_code,
            'ward_code' => $qr->ward_code, 'address_line' => $qr->address_line, 'vehicle_description' => 'Toyota Hilux']);
        unset($data['vehicle_model']);
        $this->patch(route('admin.submissions.update', $qr), $data)->assertSessionHasNoErrors();
        $this->assertSame('pending', $qr->fresh()->status->value);
        $this->assertNull($qr->fresh()->sales_id);
        $this->assertSame('Toyota Hilux', $qr->fresh()->vehicle_description);
    }

    public function test_missing_dealer_is_visible_and_cannot_be_approved(): void
    {
        $submission = CustomerSubmission::factory()->create();
        DB::table('customer_submissions')->where('id', $submission->id)->update(['dealer_id' => null]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()->assertSee('Thiếu đại lý lịch sử hợp lệ');
        $this->post(route('admin.submissions.approve', $submission))->assertSessionHasErrors('submission');
        $this->assertSame('pending', $submission->fresh()->status->value);
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_invalid_sales_role_is_warned_without_generating_a_broken_profile_link(): void
    {
        $submission = CustomerSubmission::factory()->create();
        DB::table('users')->where('id', $submission->sales_id)->update(['role' => 'admin', 'dealer_id' => null]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()
            ->assertSee('Quan hệ Sales không hợp lệ')->assertDontSee(route('admin.sales.show', $submission->sales_id));
        $this->post(route('admin.submissions.approve', $submission))->assertSessionHasErrors('submission');
        $this->assertSame('pending', $submission->fresh()->status->value);
    }

    public function test_admin_edits_personal_fields_with_masked_atomic_audit_and_preserves_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = CustomerSubmission::factory()->create(['customer_name' => 'PRIVATE_OLD_NAME', 'phone' => '0901234567']);
        $filters = ['q' => 'PRIVATE', 'dealer_id' => $submission->dealer_id, 'status' => 'pending', 'page' => 2];
        $this->actingAs($admin)->patch(route('admin.submissions.update', ['submission' => $submission, ...$filters]),
            $this->payload($submission, ['customer_name' => 'PRIVATE_NEW_NAME', 'phone' => '+84 912 345 678']))
            ->assertRedirect(route('admin.submissions.index', $filters))->assertSessionHasNoErrors();
        $fresh = $submission->fresh();
        $this->assertSame('PRIVATE_NEW_NAME', $fresh->customer_name);
        $this->assertSame('0912345678', $fresh->phone_normalized);
        $this->assertSame('pending', $fresh->status->value);
        $this->assertNull($fresh->reviewed_by);
        $this->assertSame($submission->submitted_at->toDateTimeString(), $fresh->submitted_at->toDateTimeString());
        $edit = SubmissionEdit::sole();
        $this->assertSame($admin->id, $edit->edited_by);
        $this->assertSame('*******567', $edit->changes['phone']['before']);
        $raw = DB::table('submission_edits')->value('changes');
        foreach (['PRIVATE_OLD_NAME', 'PRIVATE_NEW_NAME', '0901234567', '0912345678'] as $private) {
            $this->assertStringNotContainsString($private, $raw);
        }
        $this->assertSame(0, ModerationReview::count());
    }

    public function test_pending_attribution_requires_matching_sales_and_dealer_and_is_audited(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $next = User::factory()->active()->create();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', $submission),
            $this->payload($submission, ['dealer_id' => $next->dealer_id, 'sales_id' => $submission->sales_id]))->assertSessionHasErrors('sales_id');
        $this->assertSame(0, SubmissionEdit::count());
        $this->patch(route('admin.submissions.update', $submission), $this->payload($submission, ['dealer_id' => $next->dealer_id, 'sales_id' => $next->id]))->assertSessionHasNoErrors();
        $this->assertSame($next->dealer_id, $submission->fresh()->dealer_id);
        $this->assertSame($next->id, $submission->fresh()->sales_id);
        $this->assertArrayHasKey('dealer_id', SubmissionEdit::sole()->changes);
    }

    public function test_approved_and_rejected_attribution_cannot_change_but_personal_corrections_keep_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['approved', 'rejected'] as $status) {
            $submission = CustomerSubmission::factory()->$status()->create();
            $next = User::factory()->active()->create();
            $this->patch(route('admin.submissions.update', $submission), $this->payload($submission, ['dealer_id' => $next->dealer_id, 'sales_id' => $next->id]))->assertSessionHasErrors('dealer_id');
            $this->assertSame($submission->dealer_id, $submission->fresh()->dealer_id);
            $this->patch(route('admin.submissions.update', $submission), $this->payload($submission, ['customer_name' => 'Corrected Customer']))->assertSessionHasNoErrors();
            $this->assertSame($status, $submission->fresh()->status->value);
            $this->assertSame($submission->reviewed_by, $submission->fresh()->reviewed_by);
        }
    }

    public function test_phone_duplicate_validation_ignores_self_and_normalizes_existing_legacy_phone(): void
    {
        CustomerSubmission::factory()->approved()->create(['phone' => '+84 (901) 234 567']);
        $target = CustomerSubmission::factory()->create(['phone' => '0912345678']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', $target), $this->payload($target, ['phone' => '0901234567']))->assertSessionHasErrors('phone');
        $this->assertSame('0912345678', $target->fresh()->phone);
        $this->patch(route('admin.submissions.update', $target), $this->payload($target, ['customer_name' => 'Only name changed']))->assertSessionHasNoErrors();
    }

    public function test_invalid_fields_restore_only_the_matching_inline_form(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', ['submission' => $target, 'status' => 'pending']),
            $this->payload($target, ['customer_name' => ['bad'], 'phone' => 'not-a-phone', 'province_code' => '79', 'ward_code' => '00000']))
            ->assertSessionHasErrors(['customer_name', 'phone', 'ward_code']);
        $this->get(route('admin.submissions.index', ['status' => 'pending']))->assertOk()->assertSee('Chưa lưu được thay đổi.');
        $this->assertSame(0, SubmissionEdit::count());
    }

    public function test_system_field_tampering_and_stale_versions_do_not_mutate(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', $target), $this->payload($target, ['status' => 'approved', 'facebook_url' => 'https://facebook.com/forged']))->assertSessionHasErrors(['status', 'facebook_url']);
        $data = $this->payload($target, ['customer_name' => 'Stale admin write']);
        $target->forceFill(['customer_name' => 'Another admin write'])->save();
        $this->patch(route('admin.submissions.update', $target), $data)->assertSessionHasErrors('submission');
        $this->assertSame('Another admin write', $target->fresh()->customer_name);
        $this->assertSame(0, SubmissionEdit::count());
    }

    public function test_other_roles_and_guest_cannot_edit_or_load_admin_address_api(): void
    {
        $target = CustomerSubmission::factory()->create();
        $url = route('admin.submissions.update', $target);
        $this->patchJson($url, $this->payload($target))->assertUnauthorized();
        foreach ([User::factory()->active()->create(), User::factory()->dealer()->create()] as $user) {
            $this->actingAs($user)->patchJson($url, $this->payload($target))->assertForbidden();
            $this->getJson(route('admin.submissions.address.wards', ['province' => '79']))->assertForbidden();
        }
        $this->assertSame(0, SubmissionEdit::count());
    }

    public function test_no_schema_disables_editing_and_backend_fails_closed_before_writing(): void
    {
        $target = CustomerSubmission::factory()->create();
        Schema::shouldReceive('hasTable')->with('submission_edits')->andReturn(false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()->assertSee('chờ áp dụng migration nhật ký')->assertDontSee('name="edit_version"', false);
        $this->patch(route('admin.submissions.update', $target), $this->payload($target, ['customer_name' => 'Must not save']))->assertSessionHasErrors('submission');
        $this->assertSame($target->customer_name, $target->fresh()->customer_name);
    }

    public function test_inline_confirmation_only_mutates_after_post_and_does_not_count_twice(): void
    {
        $target = CustomerSubmission::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.index'))->assertOk()->assertSee('data-inline-confirmation', false)->assertSee('Bạn có chắc muốn duyệt khai báo');
        $this->assertSame('pending', $target->fresh()->status->value);
        $filters = ['q' => 'customer', 'status' => 'pending', 'page' => 2];
        $url = route('admin.submissions.approve', ['submission' => $target, ...$filters]);
        $this->post($url, ['return_to_queue' => 1])->assertRedirect(route('admin.submissions.index', $filters));
        $this->post($url, ['return_to_queue' => 1])->assertSessionHasErrors('submission');
        $this->assertSame(1, ModerationReview::count());
        $this->assertSame('approved', $target->fresh()->status->value);
    }

    public function test_audit_insert_failure_rolls_back_the_customer_edit(): void
    {
        $target = CustomerSubmission::factory()->create();
        $admin = User::factory()->admin()->create();
        $fail = true;
        SubmissionEdit::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('Simulated audit storage failure');
            }
        });
        try {
            app(UpdateCustomerSubmission::class)->execute($admin, $target, $this->payload($target, ['customer_name' => 'Must roll back']));
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated audit storage failure', $error->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertSame($target->customer_name, $target->fresh()->customer_name);
        $this->assertSame(0, SubmissionEdit::count());
    }

    public function test_audit_survives_existing_sales_pending_delete_without_blocking_its_permission(): void
    {
        Storage::fake('submission_evidence');
        $target = CustomerSubmission::factory()->create();
        $admin = User::factory()->admin()->create();
        app(UpdateCustomerSubmission::class)->execute($admin, $target, $this->payload($target, ['customer_name' => 'Corrected before delete']));
        $this->actingAs($target->sales)->delete(route('sales.submissions.destroy', $target))->assertSessionHasNoErrors();
        $this->assertNull($target->fresh());
        $this->assertNull(SubmissionEdit::sole()->customer_submission_id);
        $this->assertSame($target->id, SubmissionEdit::sole()->submission_id_snapshot);
        $this->assertSame($admin->id, SubmissionEdit::sole()->edited_by);
    }

    public function test_browser_fixtures_for_inline_edit_and_confirmation(): void
    {
        $target = CustomerSubmission::factory()->create(['customer_name' => 'Khách kiểm thử inline', 'phone' => '0901234567']);
        $qr = $this->qr();
        $admin = User::factory()->admin()->create();
        $folder = base_path('.laravel-tools/submission-inline/qa');
        File::ensureDirectoryExists($folder);
        $response = $this->actingAs($admin)->get(route('admin.submissions.index', ['status' => 'pending']))->assertOk();
        File::put($folder.'/list.html', $response->getContent());
        File::put($folder.'/manifest.json', json_encode(['url' => route('admin.submissions.index', ['status' => 'pending']),
            'targetId' => $target->id, 'qrId' => $qr->id,
            'updateUrl' => route('admin.submissions.update', ['submission' => $target, 'status' => 'pending']),
            'approveUrl' => route('admin.submissions.approve', ['submission' => $target, 'status' => 'pending'])], JSON_THROW_ON_ERROR));
    }
}
