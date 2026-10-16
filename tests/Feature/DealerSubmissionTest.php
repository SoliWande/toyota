<?php

namespace Tests\Feature;

use App\Actions\ApproveCustomerSubmission;
use App\Http\Controllers\Dealer\SubmissionController;
use App\Http\Requests\Dealer\SubmissionRequest;
use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class DealerSubmissionTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    private function asAccount(User $user): static
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'Dealer customer', 'facebook_url' => 'https://facebook.com/dealer.customer.'.bin2hex(random_bytes(4)),
            'customer_phone' => '0901234567', 'note' => 'Dealer note',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2022, 'vehicle_color' => 'Trắng', 'license_plate' => '29d-428.12',
            'evidence_image' => UploadedFile::fake()->image('evidence.png'),
        ], $overrides);
    }

    private function pending(User $account): CustomerSubmission
    {
        $path = 'fixture-'.bin2hex(random_bytes(8)).'.png';
        Storage::disk('submission_evidence')->put($path, 'existing-evidence-bytes');

        return CustomerSubmission::factory()->for($account, 'creator')->create([
            'submitted_at' => '2026-01-31 12:00:00', 'evidence_image_path' => $path,
        ]);
    }

    public function test_create_takes_all_system_fields_from_backend_and_preserves_uploaded_bytes(): void
    {
        $account = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $data = $this->data(['facebook_url' => 'http://m.facebook.com/Dealer.Identity/?ref=share#about',
            'dealer_id' => $other->dealer_id, 'created_by_user_id' => $other->id, 'sales_id' => 1, 'status' => 'approved',
            'submitted_at' => '2000-01-01', 'reviewed_by' => $other->id, 'reviewed_at' => now(),
            'admin_note' => 'Forged private note', 'facebook_url_normalized' => 'forged', 'evidence_image_path' => '../../forged.png']);
        $hash = hash_file('sha256', $data['evidence_image']->getPathname());
        $this->asAccount($account)->get(route('dealer.submissions.create'))->assertOk()->assertSee($account->dealer->name)
            ->assertDontSee('name="dealer_id"', false);
        $this->travelTo(now()->startOfSecond());
        $this->post(route('dealer.submissions.store'), $data)->assertSessionHasNoErrors();
        $record = $account->submissions()->sole();
        $this->assertSame($account->dealer_id, $record->dealer_id);
        $this->assertSame($account->id, $record->created_by_user_id);
        $this->assertNull($record->sales_id);
        $this->assertSame('pending', $record->status->value);
        $this->assertTrue($record->submitted_at->equalTo(now()));
        $this->assertSame('https://www.facebook.com/dealer.identity', $record->facebook_url_normalized);
        $this->assertSame('29D-428.12', $record->license_plate);
        foreach (['reviewed_by', 'reviewed_at', 'admin_note', 'rejection_reason'] as $field) {
            $this->assertNull($record->$field);
        }
        $this->assertSame($hash, hash('sha256', Storage::disk('submission_evidence')->get($record->evidence_image_path)));
        $this->assertArrayNotHasKey('evidence_image_path', $record->toArray());
        $this->get(route('dealer.submissions.show', $record))->assertOk()->assertSee('Chờ duyệt')->assertDontSee($record->evidence_image_path);
    }

    public function test_owner_list_search_filters_pagination_and_idor_use_only_dealer_snapshot(): void
    {
        $account = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->for($account, 'creator')->create(['customer_name' => 'Matching customer']);
        CustomerSubmission::factory()->approved()->for($account, 'creator')->create(['customer_name' => 'Matching approved']);
        $foreign = $this->pending($other);
        $foreign->update(['customer_name' => 'Private other dealer']);
        $this->asAccount($account)->get(route('dealer.submissions.index', ['q' => 'Matching', 'status' => 'pending',
            'dealer_id' => $other->dealer_id, 'user_id' => $other->id, 'sales_id' => $other->id]))->assertOk()
            ->assertViewHas('submissions', fn ($page) => $page->total() === 21 && $page->count() === 20)
            ->assertDontSee($foreign->customer_name)->assertDontSee('Matching approved')->assertSee('status=pending');
        $this->get(route('dealer.submissions.index', ['q' => 'Matching', 'status' => 'pending', 'page' => 2]))
            ->assertViewHas('submissions', fn ($page) => $page->count() === 1);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('dealer.submissions.'.$action, $foreign))->assertForbidden()->assertDontSee($foreign->customer_name);
        }
        $this->put(route('dealer.submissions.update', $foreign), $this->data())->assertForbidden();
        $this->delete(route('dealer.submissions.destroy', $foreign))->assertForbidden();
        $this->get(route('submissions.evidence', $foreign))->assertForbidden();
        $this->get(route('dealer.submissions.index', ['q' => "' OR 1=1 --", 'dealer_id' => $other->dealer_id]))
            ->assertViewHas('submissions', fn ($page) => $page->total() === 0);
        $this->assertModelExists($foreign);
    }

    public function test_pending_edit_keeps_or_replaces_image_without_changing_owner_or_submitted_at(): void
    {
        $account = User::factory()->active()->create();
        $record = $this->pending($account);
        $originalTime = $record->submitted_at;
        $oldPath = $record->evidence_image_path;
        $data = $this->data(['dealer_id' => 999999, 'created_by_user_id' => 999999, 'sales_id' => 1,
            'submitted_at' => '2099-01-01', 'status' => 'approved', 'evidence_image_path' => 'forged.png']);
        unset($data['evidence_image']);
        $this->asAccount($account)->put(route('dealer.submissions.update', $record), $data)->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame($oldPath, $record->evidence_image_path);
        $this->assertSame('existing-evidence-bytes', Storage::disk('submission_evidence')->get($oldPath));
        $this->assertSame($account->dealer_id, $record->dealer_id);
        $this->assertSame($account->id, $record->created_by_user_id);
        $this->assertTrue($originalTime->equalTo($record->submitted_at));
        $this->assertSame('pending', $record->status->value);
        $image = UploadedFile::fake()->image('replacement.jpg');
        $hash = hash_file('sha256', $image->getPathname());
        $this->put(route('dealer.submissions.update', $record), $this->data(['evidence_image' => $image]))->assertSessionHasNoErrors();
        $record->refresh();
        Storage::disk('submission_evidence')->assertMissing($oldPath);
        $this->assertSame($hash, hash('sha256', Storage::disk('submission_evidence')->get($record->evidence_image_path)));
        $this->assertTrue($originalTime->equalTo($record->submitted_at));
    }

    public function test_dealer_deletes_only_pending_record_and_its_own_image(): void
    {
        $account = User::factory()->active()->create();
        $record = $this->pending($account);
        $other = $this->pending(User::factory()->active()->create());
        $this->asAccount($account)->delete(route('dealer.submissions.destroy', $record))->assertRedirect(route('dealer.submissions.index'));
        $this->assertModelMissing($record);
        Storage::disk('submission_evidence')->assertMissing($record->evidence_image_path);
        Storage::disk('submission_evidence')->assertExists($other->evidence_image_path);
        $this->assertModelExists($other);
    }

    public function test_approved_and_rejected_records_are_read_only_and_hide_internal_notes(): void
    {
        $account = User::factory()->active()->create();
        foreach (['approved', 'rejected'] as $status) {
            $record = CustomerSubmission::factory()->$status()->for($account, 'creator')->create(['admin_note' => 'Private moderation note']);
            $before = $record->refresh()->getRawOriginal();
            $this->asAccount($account)->get(route('dealer.submissions.show', $record))->assertOk()->assertDontSee('Private moderation note');
            $this->get(route('dealer.submissions.edit', $record))->assertForbidden();
            $this->put(route('dealer.submissions.update', $record), $this->data())->assertForbidden();
            $this->delete(route('dealer.submissions.destroy', $record))->assertForbidden();
            $this->assertSame($before, $record->fresh()->getRawOriginal());
        }
    }

    public function test_admin_approve_and_reject_preserve_evidence_time_and_history(): void
    {
        $account = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        $approve = $this->pending($account);
        $reject = $this->pending($account);
        $before = $approve->refresh()->getRawOriginal();
        $this->asAccount($admin)->get(route('admin.submissions.index'))->assertOk()
            ->assertSee($account->dealer->name)->assertDontSee('name="sales_id"', false);
        $this->get(route('admin.submissions.show', $approve))->assertOk()->assertSee($account->name);
        $this->post(route('admin.submissions.approve', $approve), ['admin_note' => 'Private admin approval', 'dealer_id' => 999999,
            'submitted_at' => '2099-01-01', 'sales_id' => 1])->assertSessionHasErrors('dealer_id');
        $this->post(route('admin.submissions.approve', $approve), ['admin_note' => 'Private admin approval', 'submitted_at' => '2099-01-01', 'created_by_user_id' => $admin->id])->assertSessionHasNoErrors();
        $approve->refresh();
        foreach (['dealer_id', 'created_by_user_id', 'sales_id', 'evidence_image_path', 'submitted_at', 'facebook_url', 'customer_name'] as $field) {
            $this->assertSame($before[$field], $approve->getRawOriginal($field));
        }
        $this->assertSame('approved', $approve->status->value);
        $this->assertSame($admin->id, $approve->reviewed_by);
        $history = $approve->moderationReviews()->sole();
        $this->assertSame('pending', $history->from_status);
        $this->assertSame('approved', $history->to_status);
        $this->assertTrue($approve->reviewed_at->equalTo($history->reviewed_at));
        $this->post(route('admin.submissions.approve', $approve))->assertSessionHasErrors('submission');
        $this->assertSame(1, $approve->moderationReviews()->count());
        $this->post(route('admin.submissions.reject', $reject), ['rejection_reason' => 'Chưa xác minh thành viên', 'admin_note' => 'Private reject note'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $reject->fresh()->status->value);
        $this->assertSame('rejected', $reject->moderationReviews()->sole()->to_status);
        Storage::disk('submission_evidence')->assertExists($approve->evidence_image_path);
        Storage::disk('submission_evidence')->assertExists($reject->evidence_image_path);
        $this->asAccount($account)->get(route('dealer.submissions.show', $reject))->assertOk()->assertSee('Chưa xác minh thành viên')->assertDontSee('Private reject note');
    }

    public function test_duplicate_detection_is_global_and_pending_duplicates_are_still_allowed(): void
    {
        $a = User::factory()->active()->create();
        $b = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        $first = CustomerSubmission::factory()->for($a, 'creator')->create(['facebook_url' => 'https://facebook.com/dealer.duplicate.identity']);
        $sameDealer = CustomerSubmission::factory()->for($a, 'creator')->create(['facebook_url' => 'http://m.facebook.com/Dealer.Duplicate.Identity/?ref=share']);
        $otherDealer = CustomerSubmission::factory()->for($b, 'creator')->create(['facebook_url' => 'https://web.facebook.com/dealer.duplicate.identity#about']);
        $this->asAccount($admin)->post(route('admin.submissions.approve', $first))->assertSessionHasNoErrors();
        foreach ([$sameDealer, $otherDealer] as $duplicate) {
            $this->get(route('admin.submissions.show', $duplicate))->assertOk()->assertSee($a->dealer->name)->assertSee(route('admin.submissions.show', $first));
            $this->post(route('admin.submissions.approve', $duplicate))->assertSessionHasErrors('duplicate');
            $this->assertSame('pending', $duplicate->fresh()->status->value);
            $this->assertNull($duplicate->fresh()->reviewed_at);
            $this->assertSame(0, $duplicate->moderationReviews()->count());
        }
        $this->asAccount($b)->post(route('dealer.submissions.store'), $this->data(['facebook_url' => $first->facebook_url]))->assertSessionHasNoErrors();
        $this->assertSame(2, $b->submissions()->where('status', 'pending')->count());
    }

    public function test_failed_history_insert_rolls_back_approval_without_changing_attribution(): void
    {
        $record = $this->pending(User::factory()->active()->create());
        $admin = User::factory()->admin()->create();
        $before = $record->refresh()->getRawOriginal();
        new ModerationReview;
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('Fixture history failed'));
        try {
            app(ApproveCustomerSubmission::class)->execute($admin, $record);
            $this->fail('Approval must fail atomically.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture history failed', $error->getMessage());
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }
        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame(0, $record->moderationReviews()->count());
        Storage::disk('submission_evidence')->assertExists($record->evidence_image_path);
    }

    public function test_failed_create_or_image_replacement_cleans_only_the_new_upload(): void
    {
        $account = User::factory()->active()->create();
        $record = $this->pending($account);
        $before = $record->refresh()->getRawOriginal();
        $dispatcher = CustomerSubmission::getEventDispatcher();
        CustomerSubmission::setEventDispatcher(clone $dispatcher);
        CustomerSubmission::saving(function ($record) {
            if ($record->notes === 'fixture-fail-save') {
                throw new RuntimeException('Fixture save failed');
            }
        });
        $this->asAccount($account)->withoutExceptionHandling();
        try {
            foreach (['post', 'put'] as $method) {
                try {
                    $this->$method(route('dealer.submissions.'.($method === 'post' ? 'store' : 'update'), $method === 'post' ? [] : [$record]), $this->data(['note' => 'fixture-fail-save']));
                    $this->fail('Save must fail.');
                } catch (RuntimeException $error) {
                    $this->assertSame('Fixture save failed', $error->getMessage());
                }
                $this->assertSame([$record->evidence_image_path], Storage::disk('submission_evidence')->allFiles());
                $this->assertSame($before, $record->fresh()->getRawOriginal());
            }
        } finally {
            CustomerSubmission::setEventDispatcher($dispatcher);
        }
    }

    public function test_write_rechecks_account_and_pending_status_under_locks(): void
    {
        $account = User::factory()->active()->create();
        $stale = $this->pending($account);
        app(ApproveCustomerSubmission::class)->execute(User::factory()->admin()->create(), $stale);
        $request = Mockery::mock(SubmissionRequest::class);
        $request->shouldReceive('user')->andReturn($account);
        try {
            app(SubmissionController::class)->update($request, $stale);
            $this->fail('A stale pending record cannot be edited.');
        } catch (AuthorizationException) {
            $this->assertSame('approved', $stale->fresh()->status->value);
        }
        $pending = $this->pending($account);
        DB::table('users')->where('id', $account->id)->update(['status' => 'blocked']);
        $delete = Request::create('/dealer/submissions/'.$pending->id, 'DELETE');
        $delete->setUserResolver(fn () => $account);
        try {
            app(SubmissionController::class)->destroy($delete, $pending);
            $this->fail('A stale active account cannot delete submissions.');
        } catch (AuthorizationException) {
            $this->assertModelExists($pending);
            Storage::disk('submission_evidence')->assertExists($pending->evidence_image_path);
        }
    }

    public function test_unknown_legacy_ownership_is_not_inferred_and_reads_do_not_change_legacy_rows(): void
    {
        $legacyRows = DB::table('customer_submissions')->whereNotNull('sales_id')->orderBy('id')->get()->toArray();
        $known = CustomerSubmission::whereNotNull('sales_id')->whereNotNull('dealer_id')->firstOrFail();
        $owner = User::factory()->active()->create(['dealer_id' => $known->dealer_id]);
        $this->asAccount($owner)->get(route('dealer.submissions.show', $known))->assertOk();
        $unknown = CustomerSubmission::whereNull('dealer_id')->whereNotNull('sales_id')->firstOrFail();
        $currentDealer = $unknown->legacySales->dealer_id;
        $potential = User::where('role', 'dealer')->where('dealer_id', $currentDealer)->first()
            ?? User::factory()->active()->create(['dealer_id' => $currentDealer]);
        $this->asAccount($potential)->get(route('dealer.submissions.show', $unknown))->assertForbidden();
        $this->get(route('submissions.evidence', $unknown))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->asAccount($admin)->get(route('admin.submissions.show', $unknown))->assertOk()
            ->assertSee('Chưa xác minh đại lý lịch sử')->assertSee('Tham chiếu Sales lịch sử (audit)');
        $this->assertEquals($legacyRows, DB::table('customer_submissions')->whereNotNull('sales_id')->orderBy('id')->get()->toArray());
    }

    public function test_new_workflow_leaves_all_legacy_submissions_reviews_and_awards_unchanged(): void
    {
        $tables = ['customer_submissions', 'moderation_reviews', 'awards', 'award_winners', 'legacy_sales_accounts'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }
        $account = User::factory()->active()->create();
        $this->asAccount($account)->post(route('dealer.submissions.store'), $this->data())->assertSessionHasNoErrors();
        $record = $account->submissions()->sole();
        $this->asAccount(User::factory()->admin()->create())->post(route('admin.submissions.approve', $record))->assertSessionHasNoErrors();
        foreach ($tables as $table) {
            $ids = array_map(fn ($row) => $row->id, $before[$table]);
            $this->assertEquals($before[$table], DB::table($table)->whereIn('id', $ids)->orderBy('id')->get()->toArray(), $table);
        }
    }

    public function test_invalid_vehicle_url_and_image_inputs_do_not_create_records_or_files(): void
    {
        $account = User::factory()->active()->create();
        $this->asAccount($account);
        $data = $this->data(['customer_name' => ' ', 'facebook_url' => 'https://facebook.com.evil.test/user',
            'vehicle_model' => 'not-toyota', 'first_registration_year' => 9999, 'vehicle_color' => '', 'license_plate' => '<script>']);
        unset($data['evidence_image']);
        $this->post(route('dealer.submissions.store'), $data)->assertSessionHasErrors([
            'customer_name', 'facebook_url', 'vehicle_model', 'first_registration_year', 'vehicle_color', 'license_plate', 'evidence_image',
        ]);
        foreach ([UploadedFile::fake()->create('fake.jpg', 10, 'image/jpeg'),
            UploadedFile::fake()->image('big.jpg')->size(5121),
            [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')]] as $image) {
            $this->post(route('dealer.submissions.store'), $this->data(['evidence_image' => $image]))->assertSessionHasErrors('evidence_image');
        }
        $this->assertSame(0, $account->submissions()->count());
        $this->assertSame([], Storage::disk('submission_evidence')->allFiles());
    }

    public function test_evidence_access_mutations_and_moderation_require_the_correct_active_role(): void
    {
        $account = User::factory()->active()->create();
        $record = $this->pending($account);
        $this->get(route('dealer.submissions.index'))->assertRedirect(route('login'));
        $this->get(route('submissions.evidence', $record))->assertRedirect(route('login'));
        $this->asAccount($account)->get(route('submissions.evidence', $record))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('admin.submissions.approve', $record))->assertForbidden();
        foreach (['pending', 'rejected', 'blocked'] as $status) {
            DB::table('users')->where('id', $account->id)->update(['status' => $status]);
            $this->getJson(route('dealer.submissions.index'))->assertForbidden();
            $this->putJson(route('dealer.submissions.update', $record), [])->assertForbidden();
            $this->deleteJson(route('dealer.submissions.destroy', $record))->assertForbidden();
            $this->getJson(route('submissions.evidence', $record))->assertForbidden();
        }
        $this->asAccount(User::factory()->admin()->create())->get(route('submissions.evidence', $record))->assertOk();
        $this->get(route('dealer.submissions.index'))->assertForbidden();
    }

    public function test_model_rejects_reassignment_or_moving_submitted_time(): void
    {
        $record = $this->pending(User::factory()->active()->create());
        $before = $record->refresh()->getRawOriginal();
        foreach (['dealer_id' => 999999, 'created_by_user_id' => 999999, 'sales_id' => 1, 'submitted_at' => '2099-01-01'] as $field => $value) {
            $attempt = $record->fresh();
            try {
                $attempt->$field = $value;
                $attempt->save();
                $this->fail('Immutable attribution must not change.');
            } catch (LogicException) {
                $this->assertSame($before, $record->fresh()->getRawOriginal());
            }
        }
    }
}
