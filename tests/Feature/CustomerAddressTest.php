<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('submission_evidence');
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Address test customer', 'facebook_url' => 'https://www.facebook.com/address.customer',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2024, 'vehicle_color' => 'White', 'license_plate' => '29D-428.12',
            'evidence_image' => UploadedFile::fake()->image('evidence.jpg'),
            'address_line' => '12 Đường riêng tư', 'province_code' => '01', 'ward_code' => '00004',
        ], $overrides);
    }

    public function test_submission_stores_canonical_codes_and_name_snapshots_and_shows_them_only_internally(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->post(route('sales.submissions.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $submission = CustomerSubmission::sole();
        $this->assertSame('01', $submission->province_code);
        $this->assertSame('00004', $submission->ward_code);
        $this->assertSame('Thành phố Hà Nội', $submission->province_name);
        $this->assertSame('Phường Ba Đình', $submission->ward_name);
        $this->assertSame('12 Đường riêng tư', $submission->address_line);
        $this->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertSee('Thành phố Hà Nội')->assertSee('Phường Ba Đình')->assertSee('12 Đường riêng tư');
        $this->get(route('sales.submissions.edit', $submission))->assertOk()
            ->assertSee('value="01" selected', false)->assertSee('value="00004" selected', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))
            ->assertOk()->assertSee('Phường Ba Đình')->assertSee('12 Đường riêng tư');
    }

    #[DataProvider('invalidAddresses')]
    public function test_partial_unknown_or_mismatched_addresses_are_rejected(array $address, string $field): void
    {
        $this->actingAs(User::factory()->active()->create())->post(route('sales.submissions.store'), $this->data($address))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertCount(0, Storage::disk('submission_evidence')->allFiles());
        $this->get(route('sales.submissions.create'))->assertOk();
    }

    public static function invalidAddresses(): array
    {
        return [
            'unknown province' => [['province_code' => '99'], 'province_code'],
            'unknown ward' => [['ward_code' => '99999'], 'ward_code'],
            'different province' => [['province_code' => '79'], 'ward_code'],
            'missing province' => [['province_code' => null], 'province_code'],
            'missing ward' => [['ward_code' => null], 'ward_code'],
            'detail only' => [['province_code' => null, 'ward_code' => null], 'province_code'],
            'numeric province loses zero' => [['province_code' => 1], 'province_code'],
            'numeric ward loses zeros' => [['ward_code' => 4], 'ward_code'],
            'array province' => [['province_code' => ['01']], 'province_code'],
            'array ward' => [['ward_code' => ['00004']], 'ward_code'],
            'long detail' => [['address_line' => str_repeat('a', 501)], 'address_line'],
            'array detail' => [['address_line' => ['private']], 'address_line'],
            'spoofed province name' => [['province_name' => 'Forged province'], 'province_name'],
            'spoofed ward name' => [['ward_name' => 'Forged ward'], 'ward_name'],
        ];
    }

    public function test_address_is_optional_and_omitting_it_does_not_erase_an_existing_one(): void
    {
        $sales = User::factory()->active()->create();
        $data = $this->data();
        unset($data['address_line'], $data['province_code'], $data['ward_code']);
        $this->actingAs($sales)->post(route('sales.submissions.store'), $data)->assertSessionHasNoErrors();
        $submission = CustomerSubmission::sole();
        $this->assertNull($submission->province_code);
        $this->get(route('sales.submissions.show', $submission))->assertOk()->assertSee('Chưa cung cấp');
        $submission->fill(['address_line' => 'Original street', 'province_code' => '01', 'province_name' => 'Historic province',
            'ward_code' => '00004', 'ward_name' => 'Historic ward'])->save();
        unset($data['evidence_image']);
        $this->patch(route('sales.submissions.update', $submission), $data)->assertSessionHasNoErrors();
        $this->assertSame('Original street', $submission->fresh()->address_line);
        $this->assertSame('Historic ward', $submission->fresh()->ward_name);
        $this->get(route('sales.submissions.show', $submission))->assertOk()->assertSee('Historic province')->assertSee('Historic ward');
    }

    public function test_validation_error_preserves_selected_province_ward_and_address_detail(): void
    {
        $this->actingAs(User::factory()->active()->create())->post(route('sales.submissions.store'),
            $this->data(['vehicle_model' => 'invalid']))->assertSessionHasErrors('vehicle_model');
        $this->get(route('sales.submissions.create'))->assertOk()->assertSee('value="01" selected', false)
            ->assertSee('value="00004" selected', false)->assertSee('12 Đường riêng tư');
        $this->assertDatabaseCount('customer_submissions', 0);
    }

    public function test_owner_can_change_or_clear_pending_address_without_changing_evidence_or_attribution(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->post(route('sales.submissions.store'), $this->data())->assertSessionHasNoErrors();
        $submission = CustomerSubmission::sole();
        $evidence = $submission->evidence_image_path;
        $dealer = $submission->dealer_id;
        $data = $this->data(['ward_code' => '00008', 'address_line' => 'New private street']);
        unset($data['evidence_image']);
        $this->patch(route('sales.submissions.update', $submission), $data)->assertSessionHasNoErrors();
        $this->assertSame('Phường Ngọc Hà', $submission->fresh()->ward_name);
        $this->assertSame('New private street', $submission->fresh()->address_line);
        $this->assertSame($evidence, $submission->fresh()->evidence_image_path);
        $this->assertSame($dealer, $submission->fresh()->dealer_id);
        Storage::disk('submission_evidence')->assertExists($evidence);
        $this->patch(route('sales.submissions.update', $submission), array_merge($data, [
            'address_line' => null, 'province_code' => null, 'ward_code' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($submission->fresh()->address_line);
        $this->assertNull($submission->fresh()->province_name);
        $this->assertNull($submission->fresh()->ward_name);
    }

    public function test_address_cannot_be_changed_by_another_sales_or_after_review(): void
    {
        $owner = User::factory()->active()->create();
        $submission = CustomerSubmission::factory()->create(['sales_id' => $owner->id, 'address_line' => 'Private existing street']);
        $this->actingAs(User::factory()->active()->create())->patch(route('sales.submissions.update', $submission), $this->data())
            ->assertForbidden();
        $this->get(route('sales.submissions.show', $submission))->assertForbidden()->assertDontSee('Private existing street');
        $reviewed = CustomerSubmission::factory()->approved()->create(['sales_id' => $owner->id, 'address_line' => 'Approved private street']);
        $this->actingAs($owner)->patch(route('sales.submissions.update', $reviewed), $this->data())->assertForbidden();
        $this->assertSame('Approved private street', $reviewed->fresh()->address_line);
        $this->assertSame('Private existing street', $submission->fresh()->address_line);
    }

    public function test_ward_lookup_requires_an_active_sales_and_returns_only_administrative_names(): void
    {
        $url = route('sales.address.wards', '01');
        $this->getJson($url)->assertUnauthorized();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->active()->create())->getJson($url)->assertOk()
            ->assertExactJson(['wards' => app(AdministrativeDivisions::class)->wards('01')])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('sales.address.wards', '99'))->assertNotFound();
        $this->getJson(route('sales.address.wards', '1'))->assertNotFound();
    }

    public function test_private_address_never_reaches_public_or_serialized_submission_data(): void
    {
        $submission = CustomerSubmission::factory()->approved()->create([
            'address_line' => 'PRIVATE_ADDRESS_7FE19', 'province_code' => '01', 'province_name' => 'PRIVATE_PROVINCE_7FE19',
            'ward_code' => '00004', 'ward_name' => 'PRIVATE_WARD_7FE19',
        ]);
        foreach (['address_line', 'province_code', 'province_name', 'ward_code', 'ward_name'] as $field) {
            $this->assertArrayNotHasKey($field, $submission->toArray());
        }
        foreach ([route('home'), route('leaderboard', ['period' => 'all']), route('awards.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('PRIVATE_ADDRESS_7FE19')->assertDontSee('PRIVATE_PROVINCE_7FE19')->assertDontSee('PRIVATE_WARD_7FE19');
            $this->getJson($url)->assertOk()->assertDontSee('PRIVATE_ADDRESS_7FE19');
        }
        $this->getJson(route('sales.submissions.show', $submission))->assertUnauthorized()->assertDontSee('PRIVATE_ADDRESS_7FE19');
    }

    public function test_directory_has_unique_zero_padded_codes_and_valid_province_hierarchy(): void
    {
        $directory = app(AdministrativeDivisions::class);
        $this->assertCount(34, $directory->provinces());
        $wards = [];
        foreach ($directory->provinces() as $province) {
            $this->assertMatchesRegularExpression('/^[0-9]{2}$/D', $province['code']);
            $this->assertNotEmpty($directory->wards($province['code']));
            foreach ($directory->wards($province['code']) as $ward) {
                $this->assertMatchesRegularExpression('/^[0-9]{5}$/D', $ward['code']);
                $this->assertNotEmpty($ward['name']);
                $wards[] = $ward['code'];
            }
        }
        $this->assertCount(3321, $wards);
        $this->assertCount(3321, array_unique($wards));
        $this->assertNull($directory->ward('79', '00004'));
        $this->assertSame('Phường Ba Đình', $directory->ward('01', '00004')['name']);
    }
}
