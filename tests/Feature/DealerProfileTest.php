<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_never_exposes_sales_or_customers_with_approved_customer_count(): void
    {
        $dealer = Dealer::factory()->create(['phone' => 'DEALER_PHONE', 'address' => 'DEALER_ADDRESS']);
        $active = User::factory()->active()->create(['dealer_id' => $dealer->id, 'name' => 'Public Sales Name',
            'email' => 'private.profile@example.test', 'phone' => 'PRIVATE_SALES_PHONE', 'admin_note' => 'PRIVATE_ADMIN_NOTE',
            'avatar_path' => 'PRIVATE_AVATAR_PATH.jpg']);
        foreach ([UserStatus::Pending, UserStatus::Blocked, UserStatus::Rejected] as $status) {
            User::factory()->create(['dealer_id' => $dealer->id, 'status' => $status, 'name' => 'PRIVATE_'.$status->value]);
        }
        $other = User::factory()->active()->create(['name' => 'PRIVATE_OTHER_DEALER']);
        $approved = CustomerSubmission::factory()->approved()->create(['sales_id' => $active->id, 'customer_name' => 'PRIVATE_CUSTOMER', 'notes' => 'PRIVATE_CUSTOMER_NOTES']);
        CustomerSubmission::factory()->create(['sales_id' => $active->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $active->id]);
        foreach ([false, true] as $json) {
            $response = ($json ? $this->getJson(app(DealerProfile::class)->url($dealer)) : $this->get(app(DealerProfile::class)->url($dealer)))->assertOk()
                ->assertSee($dealer->name)->assertSee($dealer->address)->assertSee($dealer->phone)->assertDontSee($active->name)
                ->assertSee('Khách hàng đã được duyệt')->assertDontSee($active->email)->assertDontSee($active->phone)
                ->assertDontSee($active->avatar_path)->assertDontSee($active->admin_note)->assertDontSee($other->name)
                ->assertDontSee($approved->customer_name)->assertDontSee($approved->notes)
                ->assertDontSee('PRIVATE_pending')->assertDontSee('PRIVATE_blocked')->assertDontSee('PRIVATE_rejected')
                ->assertDontSee(route('admin.sales.show', $active))->assertDontSee(route('sales.avatar', $active));
            $this->assertSame(1, (int) $response->viewData('standing')->score);
            $response->assertViewMissing('publicSales')->assertViewMissing('sales')->assertViewMissing('customers');
            $this->assertArrayNotHasKey('photo_path', $response->viewData('dealer')->toArray());
        }
        // Other public pages retain their Dealer-only scope.
        foreach ([route('home'), route('leaderboard'), route('dealers.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee($active->name)->assertDontSee($active->email);
        }
    }

    public function test_public_profile_ignores_sales_selectors_and_private_query_parameters(): void
    {
        $dealer = Dealer::factory()->create();
        User::factory()->active()->count(21)->create(['dealer_id' => $dealer->id]);
        $this->get(app(DealerProfile::class)->url($dealer).'?email=PRIVATE_QUERY_EMAIL&include=accounts&sales_page=2')
            ->assertOk()->assertDontSee('PRIVATE_QUERY_EMAIL')->assertDontSee('include=accounts')->assertViewMissing('publicSales');
    }

    public function test_admin_customer_list_is_scoped_to_original_dealer_even_after_sales_transfer(): void
    {
        $sales = User::factory()->active()->create();
        $original = $sales->dealer;
        $old = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->count(20)->create(['sales_id' => $sales->id]);
        $destination = Dealer::factory()->create();
        $sales->forceFill(['dealer_id' => $destination->id])->save();
        $new = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.dealers.show', $original))->assertOk()
            ->assertViewHas('approvedCustomers', 1)->assertViewHas('sales', fn ($items) => $items->isEmpty())
            ->assertViewHas('customers', fn ($items) => $items->total() === 21)
            ->assertDontSee($new->customer_name);
        foreach ($response->viewData('customers') as $customer) {
            $this->assertSame($original->id, $customer->dealer_id);
            $this->assertTrue($customer->relationLoaded('sales'));
        }
        $this->get(route('admin.dealers.show', ['dealer' => $original, 'customers_page' => 2]))->assertOk()
            ->assertViewHas('customers', fn ($items) => $items->count() === 1);
        $this->get(route('admin.dealers.show', $destination))->assertOk()->assertSee($new->customer_name)->assertDontSee($old->customer_name)
            ->assertViewHas('sales', fn ($items) => $items->sole()->id === $sales->id)->assertViewHas('approvedCustomers', 0);
        $this->post(route('logout'));
        $this->get(app(DealerProfile::class)->url($original))->assertOk()->assertDontSee($sales->name)->assertDontSee($old->customer_name)
            ->assertViewHas('standing', fn ($standing) => (int) $standing->score === 1);
        $this->get(app(DealerProfile::class)->url($destination))->assertOk()->assertDontSee($sales->name)->assertDontSee($new->customer_name)->assertViewHas('standing', null);
        $this->getJson(route('admin.dealers.show', $original))->assertUnauthorized();
        $this->actingAs($sales)->get(route('admin.dealers.show', $original))->assertForbidden();
    }

    public function test_former_public_avatar_endpoint_is_removed_for_every_sales_status(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create(['avatar_path' => 'avatar.jpg']);
        Storage::disk('sales_avatars')->put('avatar.jpg', 'private-image');
        $original = $sales->dealer;
        $url = '/dealers/'.$original->id.'/sales/'.$sales->id.'/avatar';
        $this->get($url)->assertNotFound();
        foreach (UserStatus::cases() as $status) {
            $sales->forceFill(['status' => $status])->save();
            $this->getJson($url)->assertNotFound()->assertDontSee($sales->email);
        }
        $destination = Dealer::factory()->create();
        $sales->forceFill(['status' => UserStatus::Active, 'dealer_id' => $destination->id])->save();
        $this->getJson($url)->assertNotFound();
        $this->get('/dealers/'.$destination->id.'/sales/'.$sales->id.'/avatar')->assertNotFound();
        $this->getJson(route('sales.avatar', $sales))->assertUnauthorized();
        $sales->forceFill(['avatar_path' => null])->save();
        $this->get(app(DealerProfile::class)->url($destination))->assertOk()->assertDontSee('Ảnh đại diện Sales');
    }

    public function test_every_dealer_has_a_downloadable_default_png_qr_from_canonical_configuration(): void
    {
        config(['dealer.public_base_url' => 'https://community.example.test/toyota']);
        $dealer = Dealer::factory()->create();
        $profile = app(DealerProfile::class);
        $this->assertSame('https://community.example.test/toyota/'.$dealer->slug.'/', $profile->url($dealer->id));
        $response = $this->get(route('dealers.qr', $dealer))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
        $this->assertSame([600, 600], array_slice(getimagesizefromstring($response->getContent()), 0, 2));
        $this->assertSame($profile->qr($dealer->id), $response->getContent());
        $this->get(route('dealers.qr', ['dealer' => $dealer, 'download' => 1]))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="dealer-'.$dealer->id.'-qr.png"');
        $this->get('https://untrusted.example.test/dealers/'.$dealer->id.'/qr?url=https://evil.example.test')
            ->assertOk()->assertContent($response->getContent());
        $this->assertNotSame($response->getContent(), $profile->qr(Dealer::factory()->create()->id));
        $this->getJson(route('dealers.qr', ['dealer' => $dealer, 'download' => 'bad']))->assertUnprocessable();
        $this->get('/dealers/999999999/qr')->assertNotFound();
    }

    public function test_admin_can_upload_and_replace_dealer_photo_without_touching_other_fields_or_public_file_paths(): void
    {
        Storage::fake('dealer_photos');
        $admin = User::factory()->admin()->create();
        $data = ['name' => 'Photo Dealer', 'code' => 'PHOTO-DEALER', 'is_active' => 1];
        $this->actingAs($admin)->post(route('admin.dealers.store'), [...$data, 'photo' => UploadedFile::fake()->image('photo.png', 1600, 800)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $dealer = Dealer::where('code', $data['code'])->sole();
        $path = $dealer->photo_path;
        Storage::disk('dealer_photos')->assertExists($path);
        $this->assertSame([1200, 600], array_slice(getimagesizefromstring(Storage::disk('dealer_photos')->get($path)), 0, 2));
        $this->put(route('admin.dealers.update', $dealer), $data)->assertSessionHasNoErrors();
        $this->assertSame($path, $dealer->fresh()->photo_path);
        $this->put(route('admin.dealers.update', $dealer), [...$data, 'photo' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $this->assertNotSame($path, $dealer->fresh()->photo_path);
        Storage::disk('dealer_photos')->assertMissing($path);
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertSee(route('dealers.photo', $dealer))->assertDontSee($dealer->fresh()->photo_path);
        $this->post(route('logout'));
        $this->get(route('dealers.photo', $dealer))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->putJson(route('admin.dealers.update', $dealer), $data)->assertUnauthorized();
    }

    public function test_invalid_or_unauthorized_dealer_photo_uploads_are_rejected_without_creating_files(): void
    {
        Storage::fake('dealer_photos');
        $dealer = Dealer::factory()->create();
        $data = ['name' => $dealer->name, 'code' => $dealer->code, 'is_active' => 1];
        $this->actingAs(User::factory()->admin()->create());
        foreach ([UploadedFile::fake()->createWithContent('bad.jpg', 'not an image'), UploadedFile::fake()->image('large.jpg')->size(5121),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg" />')] as $photo) {
            $this->put(route('admin.dealers.update', $dealer), [...$data, 'photo' => $photo])->assertSessionHasErrors('photo');
        }
        $this->put(route('admin.dealers.update', $dealer), [...$data, 'photo_path' => '../private'])->assertSessionHasErrors('photo_path');
        $this->actingAs(User::factory()->active()->create())->put(route('admin.dealers.update', $dealer), [...$data, 'photo' => UploadedFile::fake()->image('photo.jpg')])->assertForbidden();
        $this->assertSame([], Storage::disk('dealer_photos')->allFiles());
        $this->assertNull($dealer->fresh()->photo_path);
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertSee('Ảnh đại lý đang được cập nhật');
        $this->get(route('dealers.photo', $dealer))->assertNotFound();
    }
}
