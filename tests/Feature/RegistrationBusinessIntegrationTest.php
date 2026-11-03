<?php

namespace Tests\Feature;

use App\Actions\UpdateCustomerSubmission;
use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\DealerProfile;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class RegistrationBusinessIntegrationTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    private string $cachePath;

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('i', 32)), 'app.debug' => false,
            'cache.default' => 'array']);
        $this->cachePath = storage_path('framework/testing/business-'.bin2hex(random_bytes(8)));
        config(['cache.stores.file.path' => $this->cachePath]);
        Cache::forgetDriver('file');
        $this->withoutVite();
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
        Storage::fake('submission_evidence');
    }

    protected function tearDown(): void
    {
        if (isset($this->cachePath)) {
            File::deleteDirectory($this->cachePath);
        }
        parent::tearDown();
    }

    private function address(): array
    {
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0]['code'];

        return ['province_code' => $province, 'ward_code' => $directory->wards($province)[0]['code'],
            'address_line' => 'SYNTHETIC_INTEGRATION_ADDRESS'];
    }

    private function register(Dealer $dealer, ?int $odo = null): CustomerSubmission
    {
        $count = CustomerSubmission::count();
        $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $this->post(route('dealers.registrations.step-one', $dealer->slug), [
            'customer_name' => 'SYNTHETIC_INTEGRATION_PERSON', 'phone' => '0990000011', 'vehicle_description' => 'FJ',
        ])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $this->assertSame($count, CustomerSubmission::count());
        $id = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->viewData('draftId');
        $payload = $this->address() + ['_flow' => 'two-step', '_draft' => $id, 'license_plate' => '51K-900.11',
            'first_registration_year' => 2024, 'odo' => $odo ?? '', 'facebook_url' => 'https://facebook.com/synthetic.integration.customer', 'consent' => '1'];
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('dealers.registrations.store', $dealer->slug), $payload)
                ->assertRedirect(route('dealers.registrations.success', $dealer->slug));
            $page = $this->get(route('dealers.registrations.success', $dealer->slug))->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue(str_contains($page->getContent(), 'Đăng ký thành công!'));
            $this->assertTrue(str_contains($page->getContent(), 'href="'.config('dealer-registration.group_url').'"'));
        }
        $this->assertSame($count + 1, CustomerSubmission::count());

        return CustomerSubmission::latest('id')->firstOrFail();
    }

    private function editPayload(CustomerSubmission $record): array
    {
        return $this->address() + ['customer_name' => 'SYNTHETIC_EDITED_PERSON', 'phone' => $record->phone,
            'vehicle_description' => $record->vehicle_description, 'edit_version' => UpdateCustomerSubmission::version($record),
            'edit_submission_id' => (string) $record->id];
    }

    private function score(Dealer $dealer, ?CarbonImmutable $at = null): int
    {
        return (int) (app(LeaderboardService::class)->dealers($at ? LeaderboardPeriod::Month : LeaderboardPeriod::AllTime, $at)
            ->get()->firstWhere('id', $dealer->id)->score ?? 0);
    }

    public function test_new_registration_queue_admin_edit_verified_approval_and_dealer_visibility(): void
    {
        $dealer = Dealer::factory()->create();
        $record = $this->register($dealer);
        $this->assertSame('pending', $record->status->value);
        $this->assertSame('dealer_qr', $record->source);
        $this->assertSame($dealer->id, $record->dealer_id);
        $this->assertNull($record->sales_id);
        $this->assertNull($record->odo);
        $this->assertTrue($record->address_line === $this->address()['address_line']);
        $this->assertNull($record->reviewed_by);
        $this->assertNull($record->approved_facebook_identity);
        $this->assertSame(0, $this->score($dealer));
        $admin = User::factory()->admin()->create();
        $queue = $this->actingAs($admin)->get(route('admin.submissions.index'))->assertOk()->viewData('submissions');
        $this->assertTrue($queue->contains('id', $record->id));
        $this->get(route('admin.submissions.show', $record))->assertOk();
        $this->patch(route('admin.submissions.update', $record), $this->editPayload($record))->assertSessionHasNoErrors()->assertRedirect();
        $record->refresh();
        $this->assertTrue($record->customer_name === 'SYNTHETIC_EDITED_PERSON');
        $this->assertSame('pending', $record->status->value);
        $this->assertSame(1, DB::table('submission_edits')->where('customer_submission_id', $record->id)->count());
        $this->postJson(route('admin.submissions.approve', $record))->assertUnprocessable()->assertJsonValidationErrors('verified_facebook_url');
        $this->assertSame('pending', $record->fresh()->status->value);
        $this->post(route('admin.submissions.approve', $record), ['verified_facebook_url' => $record->facebook_url])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status->value);
        $this->assertSame(1, $record->moderationReviews()->count());
        $this->assertSame(1, $this->score($dealer));
        $this->assertSame(0, app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->count());
        $this->postJson(route('admin.submissions.approve', $record), ['verified_facebook_url' => $record->facebook_url])->assertUnprocessable();
        $this->assertSame(1, $record->moderationReviews()->count());
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id]);
        $this->actingAs($manager)->get(route('dealer.customers.index'))->assertOk()
            ->assertViewHas('customers', fn ($items) => $items->contains('id', $record->id));
        $this->get(route('dealer.customers.show', $record))->assertOk();
        $this->post(route('admin.submissions.approve', $record))->assertForbidden();
        $other = User::factory()->dealer()->create();
        $this->actingAs($other)->get(route('dealer.customers.show', $record))->assertNotFound();
        $this->actingAs(User::factory()->active()->create())->get(route('sales.submissions.show', $record))->assertForbidden();
    }

    public function test_rejection_has_no_points_and_does_not_modify_existing_history(): void
    {
        $history = CustomerSubmission::factory()->approved()->create(['customer_name' => 'SYNTHETIC_HISTORY']);
        $before = $history->refresh()->getAttributes();
        $dealer = Dealer::factory()->create();
        $record = $this->register($dealer);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.reject', $record),
            ['rejection_reason' => 'SYNTHETIC_REJECTION'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('rejected', $record->fresh()->status->value);
        $this->assertNull($record->fresh()->approved_facebook_identity);
        $this->assertSame(0, $this->score($dealer));
        $this->assertSame(1, $record->moderationReviews()->count());
        $this->assertTrue($before === $history->fresh()->getAttributes(), 'Historical fixture changed.');
    }

    private function salesPayload(int $number): array
    {
        return ['customer_name' => 'SYNTHETIC_SALES_PERSON', 'customer_phone' => '09900000'.$number,
            'facebook_url' => 'https://facebook.com/synthetic.integration.sales'.$number, 'vehicle_model' => 'hilux',
            'first_registration_year' => 2024, 'vehicle_color' => 'Synthetic', 'license_plate' => '51K900'.$number];
    }

    public function test_sales_edit_and_transfer_preserve_historical_dealer_and_monthly_scores(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:59:59', config('app.timezone')));
        $a = Dealer::factory()->create();
        $b = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $a->id]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($sales)->post(route('sales.submissions.store'), $this->salesPayload(21) + [
            'evidence_image' => UploadedFile::fake()->image('synthetic.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $first = CustomerSubmission::latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.sales.update', $sales), [
            'name' => $sales->name, 'dealer_id' => $b->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($sales->fresh())->put(route('sales.submissions.update', $first), $this->salesPayload(22))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($a->id, $first->fresh()->dealer_id);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', config('app.timezone')));
        $this->actingAs($admin)->post(route('admin.submissions.approve', $first))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($sales->fresh())->post(route('sales.submissions.store'), $this->salesPayload(23) + [
            'evidence_image' => UploadedFile::fake()->image('synthetic-new.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $second = CustomerSubmission::latest('id')->firstOrFail();
        $this->assertSame($b->id, $second->dealer_id);
        $this->actingAs($admin)->post(route('admin.submissions.approve', $second))->assertSessionHasNoErrors()->assertRedirect();
        $october = CarbonImmutable::parse('2026-10-15', config('app.timezone'));
        $november = $october->addMonth();
        $this->assertSame(1, $this->score($a, $october));
        $this->assertSame(0, $this->score($b, $october));
        $this->assertSame(0, $this->score($a, $november));
        $this->assertSame(1, $this->score($b, $november));
        $this->assertSame(2, (int) app(LeaderboardService::class)->sales(LeaderboardPeriod::AllTime)->get()->firstWhere('id', $sales->id)->score);
        $this->assertSame(1, $this->score($a));
        $this->assertSame(1, $this->score($b));
        $this->travelBack();
    }

    public static function duplicateCases(): array
    {
        $cases = [];
        foreach (['pending', 'approved', 'rejected'] as $status) {
            foreach (['phone', 'license_plate'] as $field) {
                $cases[$status.'-'.$field] = [$status, $field];
            }
        }

        return $cases;
    }

    #[DataProvider('duplicateCases')]
    public function test_sales_creation_checks_global_phone_and_plate(string $status, string $field): void
    {
        $factory = CustomerSubmission::factory();
        if ($status !== 'pending') {
            $factory = $factory->$status();
        }
        $existing = $factory->create(['customer_name' => 'SYNTHETIC_DUPLICATE_PERSON', 'phone' => '+84 990 000 031', 'license_plate' => '51k - 900.31']);
        $before = $existing->refresh()->getAttributes();
        $sales = User::factory()->active()->create();
        $payload = $this->salesPayload(32);
        $payload[$field === 'phone' ? 'customer_phone' : 'license_plate'] = $field === 'phone' ? '0990000031' : '51K90031';
        $count = CustomerSubmission::count();
        $response = $this->actingAs($sales)->postJson(route('sales.submissions.store'), $payload + [
            'evidence_image' => UploadedFile::fake()->image('synthetic.jpg'),
        ]);
        if ($status === 'rejected') {
            $response->assertRedirect();
            $this->assertSame($count + 1, CustomerSubmission::count());
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors($field === 'phone' ? 'customer_phone' : $field);
            $this->assertSame($count, CustomerSubmission::count());
            $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
        }
        $this->assertTrue($before === $existing->fresh()->getAttributes(), 'Historical fixture changed.');
    }

    public function test_legacy_registration_public_profile_fixed_qr_and_inactive_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $slug = $dealer->slug;
        $payload = $this->address() + ['customer_name' => 'SYNTHETIC_LEGACY_PERSON', 'phone' => '0990000041',
            'vehicle_description' => 'Synthetic vehicle', 'consent' => '1'];
        $this->postJson(route('dealers.registrations.store', $slug), $payload)->assertCreated();
        $record = CustomerSubmission::latest('id')->firstOrFail();
        $this->assertSame('dealer_qr', $record->source);
        $this->assertNull($record->license_plate);
        $this->assertNull($record->sales_id);
        $this->assertSame('pending', $record->status->value);
        $other = Dealer::factory()->create();
        $this->postJson(route('dealers.registrations.store', $other->slug), $payload)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->get(route('dealers.show', $dealer))->assertRedirect(app(DealerProfile::class)->url($dealer))->assertStatus(301);
        $qr = $this->get(route('dealers.qr', $dealer))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        $dealer->update(['name' => 'SYNTHETIC_RENAMED_DEALER', 'is_active' => false]);
        $this->assertSame($slug, $dealer->fresh()->slug);
        $this->assertSame($qr, $this->get(route('dealers.qr', $dealer))->assertOk()->getContent());
        $this->get(route('dealers.profile', $slug))->assertOk()->assertViewHas('dealer', fn ($d) => ! $d->is_active);
        $this->postJson(route('dealers.registrations.store', $slug), array_replace($payload, ['phone' => '0990000042']))->assertNotFound();
        $this->assertSame(0, $this->score($dealer));
    }

    public function test_arbitrary_demo_note_does_not_exclude_unowned_approved_rows(): void
    {
        // A mutable note is not proof of demo ownership; never exclude real data by this label.
        $record = CustomerSubmission::factory()->approved()->create(['notes' => ToyotaRealisticDemo::TAG, 'customer_name' => 'SYNTHETIC_DEMO']);
        $this->assertSame(1, $this->score($record->dealer));
    }

    public function test_sales_edit_rejects_new_phone_and_plate_conflicts(): void
    {
        // Phase 2C.2 protects intentional identity changes without rewriting historical rows.
        $sales = User::factory()->active()->create();
        $first = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'SYNTHETIC_FIRST',
            'phone' => '0990000061', 'license_plate' => '51K90061']);
        $second = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'SYNTHETIC_SECOND',
            'phone' => '0990000062', 'license_plate' => '51K90062']);
        $before = $first->refresh()->getAttributes();
        $payload = array_replace($this->salesPayload(63), ['customer_phone' => $first->phone, 'license_plate' => $first->license_plate,
            'evidence_image' => UploadedFile::fake()->image('synthetic-edit.jpg')]);
        $this->actingAs($sales)->putJson(route('sales.submissions.update', $second), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_phone', 'license_plate']);
        $this->assertTrue($second->fresh()->phone !== $first->phone);
        $this->assertTrue($second->fresh()->license_plate !== $first->license_plate);
        $this->assertTrue($before === $first->fresh()->getAttributes(), 'Existing record changed.');
    }

    public static function odoCases(): array
    {
        return ['omitted' => [null], 'zero' => [0], 'positive' => [25000]];
    }

    #[DataProvider('odoCases')]
    public function test_registered_odo_is_available_to_authorized_admin_and_dealer(?int $odo): void
    {
        $dealer = Dealer::factory()->create();
        $record = $this->register($dealer, $odo);
        $this->assertSame($odo, $record->odo);
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id]);
        foreach ([[$admin, 'admin.submissions.show'], [$manager, 'dealer.customers.show']] as [$viewer, $route]) {
            $page = $this->actingAs($viewer)->get(route($route, $record))->assertOk();
            $this->assertSame($odo, $page->viewData('submission')->odo);
            $this->assertSame($odo !== null, str_contains($page->getContent(), 'Số km (ODO)'));
        }
    }

    public static function legacyDuplicateCases(): array
    {
        return ['pending' => ['pending'], 'approved' => ['approved'], 'rejected' => ['rejected']];
    }

    #[DataProvider('legacyDuplicateCases')]
    public function test_legacy_phone_policy_is_global_across_sales_and_statuses(string $status): void
    {
        $factory = CustomerSubmission::factory();
        if ($status !== 'pending') {
            $factory = $factory->$status();
        }
        $existing = $factory->create(['customer_name' => 'SYNTHETIC_LEGACY_DUPLICATE', 'phone' => '+84 990 000 051']);
        $dealer = Dealer::factory()->create();
        $count = CustomerSubmission::count();
        $response = $this->postJson(route('dealers.registrations.store', $dealer->slug), $this->address() + [
            'customer_name' => 'SYNTHETIC_LEGACY_PERSON', 'phone' => '0990000051', 'vehicle_description' => 'FJ', 'consent' => '1',
        ]);
        if ($status === 'rejected') {
            $response->assertCreated();
            $this->assertSame($count + 1, CustomerSubmission::count());
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('phone');
            $this->assertSame($count, CustomerSubmission::count());
        }
        $this->assertSame($status, $existing->fresh()->status->value);
    }

    public function test_admin_edit_of_approved_registration_cannot_reassign_historical_credit(): void
    {
        $dealer = Dealer::factory()->create();
        $record = $this->register($dealer);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.submissions.approve', $record), [
            'verified_facebook_url' => $record->facebook_url,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $record->refresh();
        $approvedIdentity = $record->approved_facebook_identity;
        $payload = $this->editPayload($record);
        $this->patch(route('admin.submissions.update', $record), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $record->refresh();
        $this->assertSame('approved', $record->status->value);
        $this->assertTrue($approvedIdentity === $record->approved_facebook_identity);
        $other = Dealer::factory()->create();
        $this->patchJson(route('admin.submissions.update', $record), $this->editPayload($record) + ['dealer_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('dealer_id');
        $this->assertSame($dealer->id, $record->fresh()->dealer_id);
        $this->assertSame(1, $record->moderationReviews()->count());
        $this->assertSame(1, $this->score($dealer));
        $this->assertSame(0, $this->score($other));
    }
}
