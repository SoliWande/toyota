<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Sales\SubmissionController;
use App\Http\Requests\Sales\SubmissionRequest;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mockery;
use Tests\TestCase;

class CustomerSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Khách hàng Demo',
            'facebook_url' => 'http://m.facebook.com/Alice.Example/?ref=share#about',
            'customer_phone' => '0901234567', 'note' => 'Đã tham gia cộng đồng',
            'vehicle_model' => 'veloz', 'first_registration_year' => 2022,
            'vehicle_color' => 'Trắng', 'license_plate' => '29D-428.12',
            'evidence_image' => UploadedFile::fake()->image('bang-chung.jpg'),
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('submission_evidence');
    }

    public function test_creation_uses_authenticated_owner_and_server_fields_only(): void
    {
        $sales = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $this->travelTo(now()->startOfSecond());
        $this->actingAs($sales)->get(route('sales.submissions.create'))->assertOk();
        $this->post(route('sales.submissions.store'), $this->data([
            'sales_id' => $other->id, 'status' => 'approved', 'submitted_at' => '2000-01-01',
            'facebook_url_normalized' => 'https://www.facebook.com/fake',
            'reviewed_by' => $other->id, 'reviewed_at' => now(),
            'rejection_reason' => 'Fake reason', 'admin_note' => 'Fake note',
        ]))->assertSessionHasNoErrors();
        $submission = CustomerSubmission::sole();
        $this->assertSame($sales->id, $submission->sales_id);
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertTrue($submission->submitted_at->equalTo(now()));
        $this->assertSame($this->data()['facebook_url'], $submission->facebook_url);
        $this->assertSame('https://www.facebook.com/alice.example', $submission->facebook_url_normalized);
        $this->assertSame('0901234567', $submission->phone);
        $this->assertSame('Đã tham gia cộng đồng', $submission->notes);
        foreach (['reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_note'] as $field) {
            $this->assertNull($submission->$field);
        }
    }

    public function test_optional_fields_can_be_omitted_and_pending_profiles_can_duplicate(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create(['facebook_url' => 'https://www.facebook.com/alice.example']);
        $data = $this->data(['customer_name' => 'Demo']);
        unset($data['customer_phone'], $data['note']);
        $this->actingAs($sales)->post(route('sales.submissions.store'), $data)->assertSessionHasNoErrors();
        $data['evidence_image'] = UploadedFile::fake()->image('bang-chung-2.jpg');
        $this->post(route('sales.submissions.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $sales->submissions()->count());
        $this->assertNull($sales->submissions()->first()->phone);
        $this->assertNull($sales->submissions()->first()->notes);
    }

    /** @dataProvider invalidInputs */
    public function test_validation_rejects_invalid_input_without_creating_records(array $overrides, string $field): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->post(route('sales.submissions.store'), $this->data($overrides))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('customer_submissions', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            [['customer_name' => '  '], 'customer_name'],
            [['customer_name' => str_repeat('a', 256)], 'customer_name'],
            [['customer_name' => ['bad']], 'customer_name'],
            [['facebook_url' => ''], 'facebook_url'],
            [['facebook_url' => ['bad']], 'facebook_url'],
            [['facebook_url' => 'https://facebook.com.evil.test/alice'], 'facebook_url'],
            [['facebook_url' => 'javascript:alert(1)'], 'facebook_url'],
            [['facebook_url' => 'https://facebook.com/groups/toyota'], 'facebook_url'],
            [['facebook_url' => 'https://facebook.com/profile.php?id=123&id=456'], 'facebook_url'],
            [['facebook_url' => 'https://facebook.com/alice?q='.str_repeat('a', 2048)], 'facebook_url'],
            [['customer_phone' => str_repeat('1', 31)], 'customer_phone'],
            [['customer_phone' => ['bad']], 'customer_phone'],
            [['note' => str_repeat('a', 2001)], 'note'],
            [['note' => ['bad']], 'note'],
        ];
    }

    public function test_owner_can_update_pending_without_changing_attribution_or_submission_time(): void
    {
        $submission = CustomerSubmission::factory()->create(['submitted_at' => now()->subMonth()]);
        $originalTime = $submission->submitted_at;
        $this->actingAs($submission->sales)->get(route('sales.submissions.edit', $submission))->assertOk();
        $this->put(route('sales.submissions.update', $submission), $this->data([
            'facebook_url' => 'https://web.facebook.com/profile.php?id=00123456&ref=share',
            'sales_id' => User::factory()->active()->create()->id, 'status' => 'approved',
            'submitted_at' => now(), 'reviewed_by' => User::factory()->admin()->create()->id,
        ]))->assertRedirect(route('sales.submissions.show', $submission))->assertSessionHasNoErrors();
        $fresh = $submission->fresh();
        $this->assertSame($submission->sales_id, $fresh->sales_id);
        $this->assertTrue($originalTime->equalTo($fresh->submitted_at));
        $this->assertSame(SubmissionStatus::Pending, $fresh->status);
        $this->assertSame('https://www.facebook.com/profile.php?id=123456', $fresh->facebook_url_normalized);
        $this->assertNull($fresh->reviewed_by);
        $this->assertSame('0901234567', $fresh->phone);
    }

    public function test_invalid_update_preserves_original_submission_and_optional_fields_can_be_cleared(): void
    {
        $submission = CustomerSubmission::factory()->create(['phone' => '0901234567', 'notes' => 'Old note']);
        $this->actingAs($submission->sales)->put(route('sales.submissions.update', $submission), $this->data(['facebook_url' => 'bad']))
            ->assertSessionHasErrors('facebook_url');
        $this->assertSame($submission->facebook_url, $submission->fresh()->facebook_url);
        $this->put(route('sales.submissions.update', $submission), $this->data(['customer_phone' => '', 'note' => '']))->assertSessionHasNoErrors();
        $this->assertNull($submission->fresh()->phone);
        $this->assertNull($submission->fresh()->notes);
    }

    public function test_owner_can_delete_pending_submission(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $this->actingAs($submission->sales)->delete(route('sales.submissions.destroy', $submission))
            ->assertRedirect(route('sales.submissions.index'));
        $this->assertModelMissing($submission);
    }

    /** @dataProvider reviewedStatuses */
    public function test_reviewed_submissions_are_read_only(string $status): void
    {
        $submission = CustomerSubmission::factory()->$status()->create(['admin_note' => 'Hidden internal note']);
        $this->actingAs($submission->sales)->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertDontSee('Hidden internal note')->assertDontSee('Sửa khai báo')->assertDontSee('Xóa khai báo');
        $this->get(route('sales.submissions.edit', $submission))->assertForbidden();
        $this->put(route('sales.submissions.update', $submission), $this->data())->assertForbidden();
        $this->delete(route('sales.submissions.destroy', $submission))->assertForbidden();
        $this->assertSame($submission->customer_name, $submission->fresh()->customer_name);
    }

    public static function reviewedStatuses(): array
    {
        return [['approved'], ['rejected']];
    }

    public function test_other_sales_cannot_view_edit_update_or_delete_by_changing_url_id(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $other = User::factory()->active()->create(['dealer_id' => $submission->sales->dealer_id]);
        $this->actingAs($other)->get(route('sales.submissions.show', $submission))->assertForbidden();
        $this->get(route('sales.submissions.edit', $submission))->assertForbidden();
        $this->put(route('sales.submissions.update', $submission), $this->data())->assertForbidden();
        $this->delete(route('sales.submissions.destroy', $submission))->assertForbidden();
        $this->get(route('sales.submissions.index', ['sales_id' => $submission->sales_id]))->assertOk()
            ->assertViewHas('submissions', fn ($items) => $items->total() === 0);
        $this->assertModelExists($submission);
    }

    public function test_list_combines_search_status_pagination_and_owner_scope(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->create(['sales_id' => $sales->id, 'customer_name' => 'Search Customer']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'Search Approved']);
        CustomerSubmission::factory()->create(['customer_name' => 'Search Outsider']);
        $response = $this->actingAs($sales)->get(route('sales.submissions.index', ['q' => 'Search', 'status' => 'pending']));
        $response->assertOk()->assertDontSee('Search Approved')->assertDontSee('Search Outsider')
            ->assertViewHas('submissions', fn ($items) => $items->total() === 21 && $items->count() === 20)
            ->assertSee('status=pending')->assertSee('q=Search');
        $this->get(route('sales.submissions.index', ['q' => 'Search', 'status' => 'pending', 'page' => 2]))
            ->assertViewHas('submissions', fn ($items) => $items->count() === 1);
    }

    public function test_search_supports_phone_facebook_and_literal_wildcards(): void
    {
        $submission = CustomerSubmission::factory()->create(['customer_name' => 'Literal %_ customer', 'phone' => '0901234567', 'facebook_url' => 'https://facebook.com/alice.example']);
        $this->actingAs($submission->sales);
        foreach (['090123', 'alice.example', '%_'] as $query) {
            $this->get(route('sales.submissions.index', ['q' => $query]))->assertOk()
                ->assertViewHas('submissions', fn ($items) => $items->total() === 1);
        }
        $this->get(route('sales.submissions.index', ['q' => 'missing']))->assertSee('Không có khai báo phù hợp.');
        $this->get(route('sales.submissions.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_all_endpoints_require_active_sales(): void
    {
        $submission = CustomerSubmission::factory()->create();
        $endpoints = [
            ['get', 'index', []], ['get', 'create', []], ['post', 'store', []],
            ['get', 'show', [$submission]], ['get', 'edit', [$submission]],
            ['put', 'update', [$submission]], ['delete', 'destroy', [$submission]],
        ];
        foreach ($endpoints as [$method, $route, $params]) {
            $this->$method(route('sales.submissions.'.$route, $params), $this->data())->assertRedirect(route('login'));
        }
        foreach ([UserStatus::Pending, UserStatus::Rejected, UserStatus::Blocked] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]));
            foreach ($endpoints as [$method, $route, $params]) {
                $this->$method(route('sales.submissions.'.$route, $params), $this->data())->assertRedirect(route('account.status'));
            }
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach ($endpoints as [$method, $route, $params]) {
            $this->$method(route('sales.submissions.'.$route, $params), $this->data())->assertForbidden();
        }
    }

    /** @dataProvider mutations */
    public function test_mutation_rechecks_status_when_bound_model_is_stale(string $method): void
    {
        $stale = CustomerSubmission::factory()->create();
        $this->actingAs($stale->sales);
        $reviewed = $stale->fresh();
        $reviewed->status = SubmissionStatus::Approved;
        $reviewed->reviewed_by = User::factory()->admin()->create()->id;
        $reviewed->reviewed_at = now();
        $reviewed->save();
        $request = Mockery::mock(SubmissionRequest::class);
        try {
            $controller = app(SubmissionController::class);
            $method === 'update' ? $controller->update($request, $stale) : $controller->destroy($stale);
            $this->fail('Stale pending model must not allow mutation.');
        } catch (AuthorizationException) {
            $this->assertSame(SubmissionStatus::Approved, $stale->fresh()->status);
            $this->assertSame($stale->customer_name, $stale->fresh()->customer_name);
        }
    }

    public static function mutations(): array
    {
        return [['update'], ['destroy']];
    }

    public function test_write_endpoints_are_rate_limited(): void
    {
        Cache::flush();
        $sales = User::factory()->active()->create();
        $this->actingAs($sales);
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('sales.submissions.store'), $this->data())->assertRedirect();
        }
        $this->post(route('sales.submissions.store'), $this->data())->assertStatus(429);
        $this->get(route('sales.submissions.index'))->assertOk();
        $this->assertSame(20, $sales->submissions()->count());
        Cache::flush();
    }

    public function test_detail_escapes_user_content_and_uses_safe_profile_link(): void
    {
        $submission = CustomerSubmission::factory()->rejected()->create([
            'customer_name' => '<script>alert(1)</script>', 'notes' => '<img src=x onerror=alert(1)>',
            'rejection_reason' => '<script>alert(2)</script>', 'facebook_url' => 'http://m.facebook.com/Alice.Example?ref=share',
        ]);
        $this->actingAs($submission->sales)->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertSee($submission->customer_name)->assertDontSee($submission->customer_name, false)
            ->assertSee($submission->notes)->assertDontSee($submission->notes, false)
            ->assertSee($submission->rejection_reason)->assertDontSee($submission->rejection_reason, false)
            ->assertSee('href="https://www.facebook.com/alice.example"', false)
            ->assertSee('rel="noopener noreferrer"', false)->assertSee('Từ chối');
    }
}
