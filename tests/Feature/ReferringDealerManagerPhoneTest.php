<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class ReferringDealerManagerPhoneTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFixtureTransaction; }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertContains(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1']);
        $identity = DB::selectOne('SELECT DATABASE() db, @@hostname host');
        $this->assertSame('toyota_testing', $identity->db);
        $this->assertSame(strtolower(gethostname()), strtolower($identity->host));
        $this->beginFixtureTransaction();
    }

    public function test_manager_phone_updates_normalizes_and_stays_separate_from_dealer_hotline(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'phone'));
        $dealer = Dealer::factory()->create(['phone' => '02812345678']);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678']);
        $this->actingAs($manager)->get(route('profile.edit'))->assertOk()->assertSee('Số điện thoại')->assertSee('value="0912345678"', false);
        $this->patch(route('profile.update'), ['name' => 'Quản lý mới', 'phone' => '+84 (98) 765-4321'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0987654321', $manager->fresh()->phone);
        $this->assertSame('02812345678', $dealer->fresh()->phone);
        $this->actingAs($manager->fresh())->get(route('profile.edit'))->assertSee('value="0987654321"', false);
        $this->patch(route('profile.update'), ['name' => 'Giữ số'])->assertSessionHasNoErrors();
        $this->assertSame('0987654321', $manager->fresh()->phone);
        $this->patch(route('profile.update'), ['name' => 'Xóa số', 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($manager->fresh()->phone);
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_manager_phone_does_not_change_profile(mixed $phone): void
    {
        $manager = User::factory()->dealer()->create(['phone' => '0912345678']);
        $this->actingAs($manager)->patch(route('profile.update'), ['name' => 'Do not save', 'phone' => $phone])->assertSessionHasErrors('phone');
        $this->assertSame('0912345678', $manager->fresh()->phone);
        $this->assertSame($manager->name, $manager->fresh()->name);
    }

    public static function invalidPhones(): array
    {
        return [['abc'], ['123'], [str_repeat('1', 16)], ['0901234567;ext=1'], [['0912345678']]];
    }

    public function test_manager_can_only_update_own_phone_and_protected_fields_stay_fixed(): void
    {
        $manager = User::factory()->dealer()->create(['phone' => '0912345678']);
        $other = User::factory()->dealer()->create(['phone' => '0987654321']);
        $this->actingAs($manager)->patch(route('profile.update'), ['name' => 'Attack', 'phone' => '0901234567', 'user_id' => $other->id])->assertSessionHasErrors('user_id');
        $this->patch(route('profile.update'), ['name' => 'Attack', 'phone' => '0901234567', 'email' => 'changed@example.test'])->assertSessionHasErrors('email');
        $this->patch('/profile/'.$other->id, ['name' => 'Attack', 'phone' => '0901234567'])->assertNotFound();
        $this->assertSame('0912345678', $manager->fresh()->phone);
        $this->assertSame('0987654321', $other->fresh()->phone);
        $this->assertSame($manager->email, $manager->fresh()->email);
        $this->assertSame($manager->role, $manager->fresh()->role);
    }

    public function test_public_card_uses_dealer_photo_address_hours_and_hotline_not_manager_fields(): void
    {
        Storage::fake('dealer_photos');
        Storage::fake('dealer_avatars');
        $dealer = Dealer::factory()->create(['phone' => '02812345678', 'address' => 'Địa chỉ từ dữ liệu đại lý', 'opening_hours' => '08:00 – 17:00', 'photo_path' => 'dealer-photo.jpg']);
        $photo = UploadedFile::fake()->image('dealer.jpg');
        Storage::disk('dealer_photos')->put('dealer-photo.jpg', file_get_contents($photo->getPathname()));
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678']);
        $this->actingAs($manager)->patch(route('profile.update'), ['name' => $manager->name, 'avatar' => UploadedFile::fake()->image('account.jpg')])->assertSessionHasNoErrors();
        $first = $manager->fresh()->avatar_path;
        $this->patch(route('profile.update'), ['name' => $manager->name, 'phone' => '0987654321', 'avatar' => UploadedFile::fake()->image('next.jpg')])->assertSessionHasNoErrors();
        Storage::disk('dealer_avatars')->assertMissing($first);
        Storage::disk('dealer_avatars')->assertExists($manager->fresh()->avatar_path);
        $this->assertSame('dealer-photo.jpg', $dealer->fresh()->photo_path);
        $this->assertSame('02812345678', $dealer->fresh()->phone);
        $this->actingAs($manager->fresh())->get(route('profile.edit'))->assertSee(route('profile.avatar'))->assertSee('value="0987654321"', false);
        $this->get(route('profile.avatar'))->assertOk();
        config(['dealer-registration.approved_call_phones' => [$dealer->slug => '02812345678']]);
        $this->post(route('logout'));
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertSee(route('dealers.photo', $dealer->id))
            ->assertSee('Địa chỉ từ dữ liệu đại lý')->assertSee('08:00 – 17:00')->assertSee('href="tel:02812345678"', false)
            ->assertDontSee('0987654321')->assertDontSee(route('profile.avatar'))->assertDontSee($manager->email);
        $this->get(route('dealers.photo', $dealer->id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_missing_photo_and_phone_render_fallback_without_manager_mapping_or_fake_call(): void
    {
        Storage::fake('dealer_photos');
        $dealer = Dealer::factory()->create(['phone' => null, 'photo_path' => null, 'address' => null, 'opening_hours' => null]);
        User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678', 'avatar_path' => 'account-only.jpg']);
        config(['dealer-registration.approved_call_phones' => []]);
        foreach ([null, 'missing.jpg'] as $photo) {
            $dealer->forceFill(['photo_path' => $photo])->save();
            $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertSee('referring-dealer-store.png')
                ->assertDontSee('class="dr-dealer-photo"', false)->assertDontSee('href="tel:', false)
                ->assertDontSee('account-only.jpg')->assertDontSee('0912345678');
        }
        $dealer->update(['phone' => '02812345678']);
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertDontSee('href="tel:', false);
        config(['dealer-registration.approved_call_phones' => [$dealer->slug => '02812345678']]);
        $this->get(app(DealerProfile::class)->url($dealer))->assertSee('href="tel:02812345678"', false);
    }

    public function test_export_browser_fixtures(): void
    {
        Storage::fake('dealer_photos');
        Storage::fake('dealer_avatars');
        $dir = base_path('.laravel-tools/referring-dealer-phone/qa');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $dealer = Dealer::factory()->create(['name' => 'Toyota An Sương', 'address' => 'Số 1 Quốc lộ 22, Hóc Môn, TP.HCM', 'opening_hours' => '7:30 – 17:30, tất cả các ngày', 'phone' => '02812345678', 'photo_path' => 'dealer.jpg']);
        $photo = file_get_contents(public_path('dealer-registration/benefits-five-service-photo.png'));
        // Test-only source image, separate from the manager's deliberately different avatar.
        Storage::disk('dealer_photos')->put('dealer.jpg', $photo);
        file_put_contents($dir.'/dealer.png', $photo);
        config(['dealer-registration.approved_call_phones' => [$dealer->slug => '02812345678']]);
        file_put_contents($dir.'/landing.html', $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->getContent());
        $dealer->forceFill(['photo_path' => null, 'phone' => null])->save();
        file_put_contents($dir.'/missing.html', $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->getContent());
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'name' => 'Nguyễn Minh Anh', 'phone' => '0912345678', 'email' => 'manager@example.test']);
        $accountPhoto = UploadedFile::fake()->createWithContent('account.png', file_get_contents(public_path('dealer-registration/benefits-five-gift-photo.png')));
        $this->actingAs($manager)->patch(route('profile.update'), ['name' => $manager->name, 'avatar' => $accountPhoto])->assertSessionHasNoErrors();
        file_put_contents($dir.'/profile.html', $this->actingAs($manager->fresh())->get(route('profile.edit'))->assertOk()->getContent());
        file_put_contents($dir.'/avatar.jpg', Storage::disk('dealer_avatars')->get($manager->fresh()->avatar_path));
    }
}
