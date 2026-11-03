<?php

namespace Tests\Feature;

use App\Actions\UpdateCustomerSubmission;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\LeaderboardService;
use App\Services\SubmissionCollisionAudit;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class ControlledDuplicateApprovalTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32)), 'cache.default' => 'array']);
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function record(int $suffix, string $status = 'pending'): CustomerSubmission
    {
        $factory = CustomerSubmission::factory();
        if ($status !== 'pending') {
            $factory = $factory->$status();
        }

        return $factory->create(['customer_name' => 'SYNTHETIC_CONTROLLED_'.$suffix,
            'facebook_url' => 'https://facebook.com/synthetic.controlled.'.$suffix,
            'phone' => '09900001'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT),
            'license_plate' => '51C901'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT)])->refresh();
    }

    public static function collisions(): array
    {
        $cases = [];
        foreach (['pending', 'approved', 'rejected'] as $status) {
            foreach (['phone', 'license_plate'] as $field) {
                $cases[$status.'-'.$field] = [$status, $field];
            }
        }

        return $cases;
    }

    #[DataProvider('collisions')]
    public function test_approval_checks_all_dealers_and_preserves_other_records(string $status, string $field): void
    {
        $target = $this->record(11);
        $other = $this->record(12, $status);
        // Simulate only these testing rows having historical, unnormalized collisions.
        $value = $field === 'phone' ? '+84 (990) 000-111' : '51c - 901.11';
        DB::table('customer_submissions')->where('id', $other->id)->update([$field => $value]);
        $before = $other->refresh()->getAttributes();
        $scores = app(LeaderboardService::class)->dealerScores()->get()->toJson();
        $response = $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.submissions.approve', $target));
        if ($status === 'rejected') {
            $response->assertRedirect();
            $this->assertSame('approved', $target->fresh()->status->value);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertSame('pending', $target->fresh()->status->value);
            $this->assertNull($target->fresh()->reviewed_at);
            $this->assertNull($target->fresh()->reviewed_by);
            $this->assertNull($target->fresh()->approved_facebook_identity);
            $this->assertSame(0, $target->moderationReviews()->count());
            $this->assertTrue($scores === app(LeaderboardService::class)->dealerScores()->get()->toJson());
        }
        $this->assertTrue($before === $other->fresh()->getAttributes(), 'Conflicting historical fixture changed.');
    }

    private function edit(CustomerSubmission $record, array $changes = []): array
    {
        return array_replace(['customer_name' => 'SYNTHETIC_EDITED', 'phone' => $record->phone,
            'license_plate' => $record->license_plate, 'vehicle_model' => $record->vehicle_model,
            'edit_submission_id' => (string) $record->id, 'edit_version' => UpdateCustomerSubmission::version($record)], $changes);
    }

    public function test_admin_can_fix_plate_then_approve_without_mutating_approved_history(): void
    {
        $target = $this->record(21);
        $other = $this->record(22, 'approved');
        DB::table('customer_submissions')->where('id', $target->id)->update(['license_plate' => $other->license_plate]);
        $target->refresh();
        $history = $other->getAttributes();
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson(route('admin.submissions.approve', $target))->assertUnprocessable()->assertJsonValidationErrors('license_plate');
        $this->patch(route('admin.submissions.update', $target), $this->edit($target, ['license_plate' => '51c - 999.99']))->assertSessionHasNoErrors()->assertRedirect();
        $target->refresh();
        $this->assertTrue($target->license_plate === '51C99999');
        $audit = DB::table('submission_edits')->where('customer_submission_id', $target->id)->sole();
        $this->assertTrue(! str_contains($audit->changes, $target->license_plate));
        $this->post(route('admin.submissions.approve', $target))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $target->fresh()->status->value);
        $this->assertSame(1, $target->moderationReviews()->count());
        $this->assertTrue($history === $other->fresh()->getAttributes());
    }

    public function test_unrelated_edit_and_invalid_new_identity_do_not_rewrite_history(): void
    {
        $target = $this->record(31);
        $other = $this->record(32);
        DB::table('customer_submissions')->where('id', $other->id)->update(['phone' => $target->phone, 'license_plate' => $target->license_plate]);
        $identity = $target->only(['phone', 'phone_normalized', 'license_plate']);
        $this->actingAs(User::factory()->admin()->create());
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0]['code'];
        $address = ['province_code' => $province, 'ward_code' => $directory->wards($province)[0]['code'], 'address_line' => 'SYNTHETIC_EDITED_ADDRESS'];
        $this->patch(route('admin.submissions.update', $target), $this->edit($target, $address))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($identity === $target->fresh()->only(['phone', 'phone_normalized', 'license_plate']));
        $this->assertTrue($target->fresh()->address_line === $address['address_line']);
        $third = $this->record(33);
        $this->patchJson(route('admin.submissions.update', $target), $this->edit($target->refresh(), ['license_plate' => $third->license_plate]))
            ->assertUnprocessable()->assertJsonValidationErrors('license_plate');
        $this->assertTrue($identity === $target->fresh()->only(['phone', 'phone_normalized', 'license_plate']));
    }

    public function test_pending_pair_has_no_automatic_winner_and_reject_releases_identity(): void
    {
        $first = $this->record(41);
        $second = $this->record(42);
        DB::table('customer_submissions')->where('id', $second->id)->update(['phone' => $first->phone]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([$first, $second] as $record) {
            $this->postJson(route('admin.submissions.approve', $record))->assertUnprocessable()->assertJsonValidationErrors('phone');
            $this->assertSame('pending', $record->fresh()->status->value);
        }
        $this->post(route('admin.submissions.reject', $second), ['rejection_reason' => 'SYNTHETIC_DUPLICATE'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.submissions.approve', $first))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $first->fresh()->status->value);
        $this->assertSame('rejected', $second->fresh()->status->value);
    }

    public static function reactivations(): array
    {
        return ['pending' => ['pending'], 'approved' => ['approved']];
    }

    #[DataProvider('reactivations')]
    public function test_reactivation_checks_identity_but_does_not_add_a_ui_flow(string $status): void
    {
        $first = $this->record(51);
        $rejected = $this->record(52, 'rejected');
        $rejected->update(['phone' => $first->phone]);
        try {
            $values = ['status' => $status, 'rejection_reason' => null];
            if ($status === 'pending') {
                $values += ['reviewed_at' => null, 'reviewed_by' => null];
            }
            $rejected->forceFill($values)->save();
            $this->fail('Conflicting reactivation succeeded.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('phone', $error->errors());
        }
        $this->assertSame('rejected', $rejected->fresh()->status->value);
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.submissions.approve', $rejected))->assertUnprocessable();
    }

    public function test_target_normalized_phone_alias_is_checked_and_admin_can_correct_phone(): void
    {
        $target = $this->record(91);
        $other = $this->record(92, 'approved');
        DB::table('customer_submissions')->where('id', $target->id)->update(['phone_normalized' => $other->phone]);
        $target->refresh();
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.submissions.approve', $target))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->patch(route('admin.submissions.update', $target), $this->edit($target, ['phone' => '0990000193']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.submissions.approve', $target))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $target->fresh()->status->value);
    }

    #[DataProvider('reactivations')]
    public function test_existing_model_reactivation_without_collision_remains_possible(string $status): void
    {
        $record = $this->record(53, 'rejected');
        $values = ['status' => $status, 'rejection_reason' => null];
        if ($status === 'pending') {
            $values += ['reviewed_at' => null, 'reviewed_by' => null];
        }
        $record->forceFill($values)->save();
        $this->assertSame($status, $record->fresh()->status->value);
    }

    public function test_inline_warnings_are_generic_once_and_edit_values_are_retained(): void
    {
        $first = $this->record(61);
        $other = $this->record(62);
        DB::table('customer_submissions')->where('id', $other->id)->update(['phone' => $first->phone]);
        $this->actingAs(User::factory()->admin()->create());
        $page = $this->get(route('admin.submissions.show', $first))->assertOk();
        $this->assertSame(1, substr_count($page->getContent(), SubmissionCollisionAudit::MESSAGES['phone']));
        $this->assertFalse(str_contains($page->getContent(), $other->customer_name));
        $this->get(route('admin.submissions.index'))->assertOk()->assertViewHas('identityConflicts', fn ($map) => isset($map[$first->id]['phone']));
        $this->from(route('admin.submissions.index'))->patch(route('admin.submissions.update', $first), $this->edit($first, ['license_plate' => '??invalid']))->assertRedirect();
        $html = $this->get(route('admin.submissions.index'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $input = $xpath->query('//input[@id="edit-plate-'.$first->id.'"]')->item(0);
        $this->assertTrue($input->getAttribute('value') === '??invalid');
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(1, $xpath->query('//*[@id="edit-plate-error-'.$first->id.'"]')->length);
    }

    public function test_csrf_authorization_and_edit_version_still_apply(): void
    {
        $record = $this->record(71);
        $sales = $record->sales;
        $this->actingAs($sales)->postJson(route('admin.submissions.approve', $record))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->edit($record);
        DB::table('customer_submissions')->where('id', $record->id)->update(['license_plate' => '51C99971']);
        $this->patchJson(route('admin.submissions.update', $record), $payload)->assertUnprocessable()->assertJsonValidationErrors('submission');
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->get(route('admin.submissions.show', $record))->assertOk();
        $token = session()->token();
        $this->post(route('admin.submissions.approve', $record))->assertStatus(419);
        $this->post(route('admin.submissions.approve', $record), ['_token' => 'wrong'])->assertStatus(419);
        $this->post(route('admin.submissions.approve', $record), ['_token' => $token])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status->value);
    }

    public function test_read_only_audit_counts_aliases_once_and_outputs_no_pii(): void
    {
        $first = $this->record(81, 'approved');
        $second = $this->record(82);
        $third = $this->record(83);
        $fourth = $this->record(84);
        $ignored = $this->record(85, 'rejected');
        DB::table('customer_submissions')->where('id', $second->id)->update(['phone' => '+84 990 000 181', 'phone_normalized' => $first->phone]);
        DB::table('customer_submissions')->where('id', $fourth->id)->update(['license_plate' => $third->license_plate]);
        DB::table('customer_submissions')->where('id', $ignored->id)->update(['phone' => $first->phone, 'license_plate' => $third->license_plate]);
        $before = DB::table('customer_submissions')->orderBy('id')->get()->toJson();
        $this->artisan('submissions:audit-collisions')->assertExitCode(2);
        $this->artisan('submissions:audit-collisions', ['--confirm-database' => 'not-the-configured-db'])->assertExitCode(2);
        $this->assertSame(0, Artisan::call('submissions:audit-collisions', ['--confirm-database' => 'toyota_testing', '--json' => true]));
        $output = Artisan::output();
        $this->assertSame(['active_submissions' => 4, 'phone_groups' => 1, 'plate_groups' => 1,
            'affected_submissions' => 4, 'groups_with_approved' => 1, 'pending_only_groups' => 1, 'cross_dealer_groups' => 2], json_decode($output, true));
        foreach ([$first->phone, $first->license_plate, $first->customer_name, $first->facebook_url] as $private) {
            $this->assertFalse(str_contains($output, $private), 'Audit leaked an identity.');
        }
        $this->assertTrue($before === DB::table('customer_submissions')->orderBy('id')->get()->toJson());
    }

    public function test_audit_counts_affected_records_once_across_fields_and_same_dealer(): void
    {
        $first = $this->record(86);
        $second = $this->record(87);
        DB::table('customer_submissions')->where('id', $second->id)->update([
            'dealer_id' => $first->dealer_id, 'phone' => $first->phone,
            'phone_normalized' => $first->phone, 'license_plate' => $first->license_plate,
        ]);
        $counts = app(SubmissionCollisionAudit::class)->scan();
        $this->assertSame(2, $counts['affected_submissions']);
        $this->assertSame(1, $counts['phone_groups']);
        $this->assertSame(1, $counts['plate_groups']);
        $this->assertSame(2, $counts['pending_only_groups']);
        $this->assertSame(0, $counts['cross_dealer_groups']);
    }

    public function test_name_edit_preserves_missing_raw_phone_and_historical_normalized_alias(): void
    {
        $record = $this->record(94);
        DB::table('customer_submissions')->where('id', $record->id)->update(['phone' => null, 'phone_normalized' => '0990000194']);
        $record->refresh();
        $identity = $record->only(['phone', 'phone_normalized', 'license_plate']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', $record), $this->edit($record))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($identity === $record->fresh()->only(['phone', 'phone_normalized', 'license_plate']));
        $record = $this->record(95);
        $identity = $record->only(['phone', 'phone_normalized', 'license_plate']);
        $payload = $this->edit($record);
        unset($payload['phone']);
        $this->patch(route('admin.submissions.update', $record), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($identity === $record->fresh()->only(['phone', 'phone_normalized', 'license_plate']));
    }
}
