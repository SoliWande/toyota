<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class StaffPagesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function payload(): array
    {
        return ['customer_name' => 'Khách đăng ký thật trong test', 'customer_phone' => '0901234567',
            'facebook_url' => 'https://www.facebook.com/customer.safe.profile',
            'vehicle_model' => 'veloz', 'first_registration_year' => 2022, 'vehicle_color' => 'Trắng',
            'license_plate' => '30A-123.45', 'evidence_image' => UploadedFile::fake()->image('evidence.png')];
    }

    public function test_create_saved_lookup_and_detail_use_real_row_and_do_not_create_new_attribution_or_consent(): void
    {
        Storage::fake('submission_evidence');
        config(['dealer.public_base_url' => 'https://canonical.example.test']);
        $sales = User::factory()->active()->create();
        $foreign = User::factory()->active()->create();
        $this->actingAs($sales)->get(route('sales.submissions.create'))->assertOk()->assertSee('evidence_image')->assertSee('facebook_url');
        $this->post(route('sales.submissions.store'), $this->payload() + ['sales_id' => $foreign->id, 'dealer_id' => $foreign->dealer_id, 'consented_at' => now()])
            ->assertSessionHasNoErrors()->assertRedirect(route('sales.submissions.saved', CustomerSubmission::sole()));
        $customer = CustomerSubmission::sole();
        $this->assertSame($sales->id, $customer->sales_id);
        $this->assertSame($sales->dealer_id, $customer->dealer_id);
        $this->assertNull($customer->consented_at);
        $this->assertSame('pending', $customer->status->value);
        Storage::disk('submission_evidence')->assertExists($customer->evidence_image_path);
        $saved = $this->get(route('sales.submissions.saved', $customer), ['Host' => 'attacker.invalid'])->assertOk()->assertSee($customer->customer_name);
        $lookup = $saved->viewData('lookupUrl');
        $this->assertSame('https://canonical.example.test/'.$customer->dealer->slug.'/registrations/lookup', $lookup);
        $this->assertStringNotContainsString($customer->phone, $lookup);
        $this->assertStringNotContainsString($customer->phone, $saved->viewData('message'));
        $this->assertStringNotContainsString($customer->customer_name, $saved->viewData('message'));
        $this->assertNull(parse_url($lookup, PHP_URL_QUERY));
        $this->get(route('sales.submissions.show', $customer))->assertOk()->assertSee($customer->license_plate)
            ->assertSee('Chưa có bằng chứng xác minh Group')->assertSee('Đã khai báo hồ sơ');
        $this->post(route('logout'));
        $this->get(route('dealers.lookup.show', $customer->dealer->slug))->assertOk()->assertDontSee($customer->customer_name)->assertDontSee($customer->phone);
        $this->post(route('dealers.lookup.store', $customer->dealer->slug), ['phone' => $customer->phone, 'license_plate' => $customer->license_plate])
            ->assertOk()->assertDontSee('supplement_key')->assertDontSee('PRIVATE_INTERNAL');
    }

    public function test_failed_submission_never_redirects_to_saved_and_missing_identity_has_no_fake_link(): void
    {
        Storage::fake('submission_evidence');
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->from(route('sales.submissions.create'))->post(route('sales.submissions.store'), ['customer_name' => 'Incomplete'])
            ->assertRedirect(route('sales.submissions.create'))->assertSessionHasErrors(['facebook_url', 'evidence_image']);
        $this->assertSame(0, CustomerSubmission::count());
        $customer = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'phone' => null]);
        $this->get(route('sales.submissions.saved', $customer))->assertOk()->assertViewHas('lookupUrl', null)
            ->assertDontSee('data-zalo-target', false)->assertSee('Chưa thể tạo link tra cứu');
    }

    public function test_saved_and_detail_are_scoped_to_owner_or_existing_manager_permissions(): void
    {
        $owner = User::factory()->active()->create();
        $customer = CustomerSubmission::factory()->create(['sales_id' => $owner->id]);
        $foreign = User::factory()->active()->create();
        $this->actingAs($foreign)->get(route('sales.submissions.saved', $customer))->assertForbidden();
        $this->get(route('sales.submissions.show', $customer))->assertForbidden();
        $manager = User::factory()->dealer()->create(['dealer_id' => $owner->dealer_id]);
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk()->assertSee($customer->customer_name)
            ->assertDontSee('action="'.route('sales.submissions.destroy', $customer).'"', false);
        $this->get(route('sales.submissions.saved', $customer))->assertForbidden();
        $this->actingAs(User::factory()->dealer()->create())->get(route('dealer.customers.show', $customer))->assertNotFound();
        foreach (['pending', 'rejected', 'blocked'] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]))->getJson(route('sales.submissions.saved', $customer))->assertForbidden();
        }
        $this->post(route('logout'));
        $this->get(route('sales.submissions.saved', $customer))->assertRedirect(route('login'));
    }

    public function test_sales_profile_qr_is_canonical_and_never_silently_relabels_dealer_registration_as_sales(): void
    {
        config(['dealer.public_base_url' => 'https://canonical.example.test']);
        $sales = User::factory()->active()->create();
        $response = $this->actingAs($sales)->get(route('sales.dashboard'), ['Host' => 'attacker.invalid'])->assertOk();
        $this->assertSame('https://canonical.example.test/sales/'.$sales->public_slug, $response->viewData('inviteUrl'));
        $this->assertStringStartsWith('data:image/png;base64,', $response->viewData('inviteQr'));
        $response->assertDontSee('bạn được ghi nhận, điểm tính cho đại lý')
            ->assertDontSee('không tự gán cho Sales'); // Unsupported Figma promise and invented replacement copy are both omitted.
        $this->assertSame(0, CustomerSubmission::count());
    }

    public function test_month_stats_and_detail_show_real_supplement_evidence_without_leaking_internal_notes(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(12));
        $sales = User::factory()->active()->create();
        $customer = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'admin_note' => 'PRIVATE_INTERNAL_NOTE']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subYear()]);
        $service = app(SubmissionVerification::class);
        $service->adminUpdate(User::factory()->admin()->create(), $customer, ['action' => 'supplement', 'supplement_reason' => 'missing_information', 'verification_version' => $service->version($customer)]);
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()
            ->assertViewHas('monthCounts', ['approved' => 1, 'pending' => 0, 'supplement' => 1])->assertDontSee('PRIVATE_INTERNAL_NOTE');
        $this->get(route('sales.submissions.show', $customer))->assertOk()->assertSee('Cần bổ sung thông tin')
            ->assertSee('Yêu cầu bổ sung thông tin')->assertDontSee('PRIVATE_INTERNAL_NOTE')->assertDontSee('Đã nhắc qua Zalo');
    }

    public function test_customer_filters_paginate_and_ignore_foreign_scope_parameters(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->create(['sales_id' => $sales->id, 'customer_name' => 'Matching name']);
        $foreign = CustomerSubmission::factory()->create(['customer_name' => 'FOREIGN_PRIVATE_NAME']);
        $this->actingAs($sales)->get(route('sales.submissions.index', ['status' => 'pending', 'q' => 'Matching', 'sales_id' => $foreign->sales_id]))
            ->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 21 && $rows->count() === 20)
            ->assertDontSee('FOREIGN_PRIVATE_NAME');
        $this->get(route('sales.submissions.index', ['status' => 'pending', 'q' => 'Matching', 'page' => 2]))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->count() === 1)->assertSee('Matching name');
    }
}
