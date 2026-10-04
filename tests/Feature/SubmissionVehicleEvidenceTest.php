<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SubmissionVehicleEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('submission_evidence');
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Khách hàng thật', 'facebook_url' => 'https://facebook.com/customer.example',
            'vehicle_model' => 'veloz', 'first_registration_year' => 2022,
            'vehicle_color' => 'Trắng', 'license_plate' => '29d-428.12',
            'evidence_image' => UploadedFile::fake()->image('bang-chung.jpg'),
        ], $overrides);
    }

    private function submissionWithImage(): CustomerSubmission
    {
        $path = UploadedFile::fake()->image('old.jpg')->store('', 'submission_evidence');

        return CustomerSubmission::factory()->create(['evidence_image_path' => $path]);
    }

    public function test_form_and_creation_store_all_vehicle_fields_and_one_private_image(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->get(route('sales.submissions.create'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('Năm đăng ký lần đầu')
            ->assertSee('Biển số xe')->assertSee('Ảnh bằng chứng (1 ảnh)')
            ->assertSee('Camry')->assertSee('Vios')->assertSee('Hilux');
        $this->post(route('sales.submissions.store'), $this->data([
            'sales_id' => User::factory()->active()->create()->id, 'status' => 'approved',
            'evidence_image_path' => '../../private-password.txt',
        ]))->assertSessionHasNoErrors();
        $submission = CustomerSubmission::sole();
        $this->assertSame($sales->id, $submission->sales_id);
        $this->assertSame('veloz', $submission->vehicle_model);
        $this->assertSame(2022, $submission->first_registration_year);
        $this->assertSame('Trắng', $submission->vehicle_color);
        $this->assertSame('29D-428.12', $submission->license_plate);
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{40}\.jpg$/', $submission->evidence_image_path);
        Storage::disk('submission_evidence')->assertExists($submission->evidence_image_path);
        $this->assertCount(1, Storage::disk('submission_evidence')->allFiles());
        $this->assertArrayNotHasKey('evidence_image_path', $submission->toArray());
        $this->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertSee('29D-428.12')->assertSee('Trắng')->assertSee('2022')
            ->assertSee(route('submissions.evidence', $submission))->assertDontSee($submission->evidence_image_path);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))
            ->assertOk()->assertSee('29D-428.12')->assertSee(route('submissions.evidence', $submission));
        $this->get(route('admin.submissions.index'))->assertOk()->assertSee('29D-428.12');
    }

    /** @dataProvider requiredFields */
    public function test_all_five_new_fields_are_required(string $field): void
    {
        $data = $this->data();
        unset($data[$field]);
        $this->actingAs(User::factory()->active()->create())->post(route('sales.submissions.store'), $data)
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
    }

    public static function requiredFields(): array
    {
        return array_map(fn ($field) => [$field], ['vehicle_model', 'first_registration_year', 'vehicle_color', 'license_plate', 'evidence_image']);
    }

    /** @dataProvider invalidVehicleData */
    public function test_invalid_vehicle_data_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->active()->create())->post(route('sales.submissions.store'), $this->data($overrides))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('customer_submissions', 0);
    }

    public static function invalidVehicleData(): array
    {
        return [
            [['vehicle_model' => 'ford'], 'vehicle_model'],
            [['vehicle_model' => ['veloz']], 'vehicle_model'],
            [['first_registration_year' => 1899], 'first_registration_year'],
            [['first_registration_year' => 9999], 'first_registration_year'],
            [['first_registration_year' => '2022.5'], 'first_registration_year'],
            [['first_registration_year' => '22'], 'first_registration_year'],
            [['vehicle_color' => '   '], 'vehicle_color'],
            [['vehicle_color' => str_repeat('a', 51)], 'vehicle_color'],
            [['license_plate' => ''], 'license_plate'],
            [['license_plate' => '<script>alert(1)</script>'], 'license_plate'],
            [['license_plate' => str_repeat('1', 21)], 'license_plate'],
            'malformed UTF-8 plate' => [['license_plate' => "29D-\xFF"], 'license_plate'],
        ];
    }

    public function test_image_validation_rejects_fake_images_svg_multiple_files_and_oversized_images(): void
    {
        $this->actingAs(User::factory()->active()->create());
        foreach ([
            UploadedFile::fake()->create('fake.jpg', 10, 'image/jpeg'),
            UploadedFile::fake()->createWithContent('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
            UploadedFile::fake()->image('large.jpg')->size(5121),
            UploadedFile::fake()->image('wide.jpg', 8001, 10),
        ] as $image) {
            $this->post(route('sales.submissions.store'), $this->data(['evidence_image' => $image]))
                ->assertSessionHasErrors('evidence_image');
        }
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_executable_filename_is_rejected_even_with_valid_image_content(): void
    {
        $image = UploadedFile::fake()->image('original.jpg');
        $this->actingAs(User::factory()->active()->create())->post(route('sales.submissions.store'),
            $this->data(['evidence_image' => UploadedFile::fake()->createWithContent('payload.php', file_get_contents($image->getPathname()))]))
            ->assertSessionHasErrors('evidence_image');
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_pending_update_can_keep_image_and_preserves_attribution_and_submitted_time(): void
    {
        $submission = $this->submissionWithImage();
        $oldPath = $submission->evidence_image_path;
        $data = $this->data(['vehicle_model' => 'hilux', 'license_plate' => ' 29d - 428.12 ']);
        unset($data['evidence_image']);
        $this->actingAs($submission->sales)->put(route('sales.submissions.update', $submission), $data)
            ->assertSessionHasNoErrors();
        $fresh = $submission->fresh();
        $this->assertSame('hilux', $fresh->vehicle_model);
        $this->assertSame('29D-428.12', $fresh->license_plate);
        $this->assertSame($oldPath, $fresh->evidence_image_path);
        $this->assertSame($submission->sales_id, $fresh->sales_id);
        $this->assertTrue($submission->submitted_at->equalTo($fresh->submitted_at));
        Storage::disk('submission_evidence')->assertExists($oldPath);
    }

    public function test_replacing_pending_image_removes_old_image_after_success(): void
    {
        $submission = $this->submissionWithImage();
        $oldPath = $submission->evidence_image_path;
        $this->actingAs($submission->sales)->put(route('sales.submissions.update', $submission), $this->data())
            ->assertSessionHasNoErrors();
        $this->assertNotSame($oldPath, $submission->fresh()->evidence_image_path);
        Storage::disk('submission_evidence')->assertMissing($oldPath);
        Storage::disk('submission_evidence')->assertExists($submission->fresh()->evidence_image_path);
        $this->assertCount(1, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_legacy_record_stays_readable_and_requires_image_when_edited(): void
    {
        $submission = CustomerSubmission::factory()->create([
            'vehicle_model' => null, 'first_registration_year' => null, 'vehicle_color' => null, 'license_plate' => null,
        ]);
        $this->actingAs($submission->sales)->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertSee('Chưa cung cấp')->assertSee('Khai báo cũ chưa có ảnh bằng chứng.');
        $this->get(route('submissions.evidence', $submission))->assertNotFound();
        $data = $this->data();
        unset($data['evidence_image']);
        $this->put(route('sales.submissions.update', $submission), $data)->assertSessionHasErrors('evidence_image');
        $this->assertNull($submission->fresh()->vehicle_model);
    }

    public function test_only_active_owner_or_active_admin_can_read_private_image(): void
    {
        $submission = $this->submissionWithImage();
        $url = route('submissions.evidence', $submission);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->active()->create())->get($url)->assertForbidden();
        $this->actingAs($submission->sales)->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        foreach ([UserStatus::Pending, UserStatus::Rejected, UserStatus::Blocked] as $status) {
            $owner = $submission->sales->fresh();
            $owner->forceFill(['status' => $status])->save();
            $this->actingAs($owner)->getJson($url)->assertForbidden();
            $this->actingAs(User::factory()->admin()->create(['status' => $status]))->getJson($url)->assertForbidden();
        }
    }

    public function test_approved_vehicle_and_evidence_cannot_be_changed_or_deleted(): void
    {
        $submission = $this->submissionWithImage();
        $submission->forceFill(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => User::factory()->admin()->create()->id])->save();
        $original = $submission->refresh()->getRawOriginal();
        $this->actingAs($submission->sales)->put(route('sales.submissions.update', $submission), $this->data())
            ->assertForbidden();
        $this->delete(route('sales.submissions.destroy', $submission))->assertForbidden();
        $this->assertSame($original, $submission->fresh()->getRawOriginal());
        Storage::disk('submission_evidence')->assertExists($submission->evidence_image_path);
        $this->assertCount(1, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_failed_creation_rolls_back_record_and_cleans_new_upload(): void
    {
        CustomerSubmission::creating(function ($submission) {
            if ($submission->notes === 'simulate-create-failure') {
                throw new RuntimeException('Simulated database save failure.');
            }
        });
        $this->withoutExceptionHandling()->actingAs(User::factory()->active()->create());
        try {
            $this->post(route('sales.submissions.store'), $this->data(['note' => 'simulate-create-failure']));
            $this->fail('Creation must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated database save failure.', $error->getMessage());
        }
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_failed_replacement_preserves_old_file_and_database_and_cleans_new_upload(): void
    {
        $submission = $this->submissionWithImage();
        CustomerSubmission::updating(function ($submission) {
            if ($submission->notes === 'simulate-update-failure') {
                throw new RuntimeException('Simulated update failure.');
            }
        });
        $original = $submission->refresh()->getRawOriginal();
        $this->withoutExceptionHandling()->actingAs($submission->sales);
        try {
            $this->put(route('sales.submissions.update', $submission), $this->data(['note' => 'simulate-update-failure']));
            $this->fail('Update must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated update failure.', $error->getMessage());
        }
        $this->assertSame($original, $submission->fresh()->getRawOriginal());
        Storage::disk('submission_evidence')->assertExists($submission->evidence_image_path);
        $this->assertCount(1, Storage::disk('submission_evidence')->allFiles());
    }

    public function test_deleting_pending_record_also_deletes_its_image(): void
    {
        $submission = $this->submissionWithImage();
        $this->actingAs($submission->sales)->delete(route('sales.submissions.destroy', $submission))->assertRedirect();
        $this->assertModelMissing($submission);
        Storage::disk('submission_evidence')->assertMissing($submission->evidence_image_path);
    }

    public function test_vehicle_and_image_data_are_never_exposed_on_public_pages(): void
    {
        $submission = $this->submissionWithImage();
        $submission->forceFill([
            'vehicle_color' => 'Private vehicle color', 'license_plate' => '29D-428.12',
            'status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => User::factory()->admin()->create()->id,
        ])->save();
        foreach (['home', 'leaderboard', 'awards.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Private vehicle color')->assertDontSee('29D-428.12')
                ->assertDontSee($submission->evidence_image_path)->assertDontSee(route('submissions.evidence', $submission));
        }
    }
}
