<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerCallPhone;
use App\Services\DealerIntroductionContact;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerManagerIntroductionTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['dealer-registration.manager_contacts' => [], 'dealer-registration.approved_call_phones' => []]);
        Storage::fake('dealer_avatars');
        Storage::fake('dealer_photos');
    }

    public function test_authorized_manager_avatar_phone_changes_appear_on_dealer_without_copying_fields(): void
    {
        $dealer = Dealer::factory()->create(['photo_path' => null, 'phone' => null]);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678']);
        config(['dealer-registration.manager_contacts' => [$dealer->slug => $manager->id]]);
        $url = app(DealerProfile::class)->url($dealer);
        $previous = null;
        foreach (['0912345678', '0987654321'] as $index => $phone) {
            $this->actingAs($manager->fresh())->patch(route('profile.update'), [
                'name' => $manager->name, 'phone' => $phone,
                'avatar' => UploadedFile::fake()->image('avatar.png', 100 + $index * 50, 100),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $path = $manager->fresh()->avatar_path;
            if ($previous) {
                Storage::disk('dealer_avatars')->assertMissing($previous);
            }
            $this->get($url)->assertOk()->assertSee('class="dr-dealer-photo"', false)->assertSee(route('dealers.photo', $dealer->id))
                ->assertSee('href="tel:'.$phone.'"', false)->assertDontSee($manager->email)->assertDontSee($path);
            $photo = $this->get(route('dealers.photo', $dealer->id))->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'image/jpeg');
            $this->assertSame(Storage::disk('dealer_avatars')->get($path), $photo->streamedContent());
            $this->actingAs($manager->fresh())->get(route('profile.edit'))->assertSee(route('profile.avatar'))->assertSee('value="'.$phone.'"', false);
            $this->get(route('profile.avatar'))->assertOk();
            $this->assertNull($dealer->fresh()->phone);
            $this->assertNull($dealer->fresh()->photo_path);
            $previous = $path;
        }
    }

    public function test_no_global_fallback_or_cross_dealer_account_mapping(): void
    {
        $first = Dealer::factory()->create(['photo_path' => null, 'phone' => null]);
        $second = Dealer::factory()->create(['photo_path' => null, 'phone' => null]);
        $manager = User::factory()->dealer()->create(['dealer_id' => $first->id, 'phone' => '0912345678', 'avatar_path' => 'chosen.jpg']);
        Storage::disk('dealer_avatars')->put('chosen.jpg', 'image');
        $contacts = app(DealerIntroductionContact::class);
        $calls = app(DealerCallPhone::class);
        $this->assertNull($contacts->photoFor($first));
        $this->assertNull($calls->forDealer($first));
        config(['dealer-registration.manager_contacts' => [$first->slug => $manager->id]]);
        $this->assertNotNull($contacts->photoFor($first));
        $this->assertSame('0912345678', $calls->forDealer($first));
        $this->assertNull($contacts->photoFor($second));
        $this->assertNull($calls->forDealer($second));
        config(['dealer-registration.manager_contacts' => [$second->slug => $manager->id]]);
        $this->assertNull($contacts->photoFor($second));
        $this->assertNull($calls->forDealer($second));
        $this->get(route('dealers.photo', $second->id))->assertNotFound();
    }

    public function test_dedicated_dealer_logo_and_hotline_take_priority_and_are_never_overwritten(): void
    {
        $dealer = Dealer::factory()->create(['phone' => '02812345678', 'photo_path' => 'official.jpg']);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678', 'avatar_path' => 'personal.jpg']);
        Storage::disk('dealer_photos')->put('official.jpg', 'official-logo');
        Storage::disk('dealer_avatars')->put('personal.jpg', 'personal-photo');
        config(['dealer-registration.manager_contacts' => [$dealer->slug => $manager->id], 'dealer-registration.approved_call_phones' => [$dealer->slug => '02812345678']]);
        $this->assertSame('02812345678', app(DealerCallPhone::class)->forDealer($dealer));
        $this->assertSame('dealer_photos', app(DealerIntroductionContact::class)->photoFor($dealer)['disk']);
        $response = $this->get(route('dealers.photo', $dealer->id))->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->assertSame('official-logo', $response->streamedContent());
        $this->actingAs($manager)->patch(route('profile.update'), ['name' => $manager->name, 'phone' => '0987654321', 'avatar' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        $this->assertSame('02812345678', $dealer->fresh()->phone);
        $this->assertSame('official.jpg', $dealer->fresh()->photo_path);
        $this->assertSame('02812345678', app(DealerCallPhone::class)->forDealer($dealer));
        config(['dealer-registration.approved_call_phones' => []]);
        $this->assertNull(app(DealerCallPhone::class)->forDealer($dealer));
    }

    public function test_missing_invalid_inactive_or_wrong_role_contacts_fail_closed(): void
    {
        $dealer = Dealer::factory()->create(['phone' => null, 'photo_path' => null]);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => 'invalid', 'avatar_path' => 'missing.jpg']);
        config(['dealer-registration.manager_contacts' => [$dealer->slug => $manager->id]]);
        $contacts = app(DealerIntroductionContact::class);
        $calls = app(DealerCallPhone::class);
        $this->assertNull($contacts->photoFor($dealer));
        $this->assertNull($calls->forDealer($dealer));
        Storage::disk('dealer_avatars')->put('missing.jpg', 'photo');
        foreach (['blocked', 'pending', 'rejected'] as $status) {
            $manager->forceFill(['status' => $status, 'phone' => '0912345678'])->save();
            $this->assertNull($contacts->photoFor($dealer));
            $this->assertNull($calls->forDealer($dealer));
        }
        $manager->forceFill(['status' => 'active', 'role' => 'sales'])->save();
        $this->assertNull($contacts->photoFor($dealer));
        $this->assertNull($calls->forDealer($dealer));
        $manager->forceFill(['role' => 'dealer'])->save();
        $dealer->update(['is_active' => false]);
        $this->assertNull($contacts->photoFor($dealer));
        $this->assertNull($calls->forDealer($dealer));
    }

    public function test_revoking_mapping_removes_public_avatar_and_phone_on_reload(): void
    {
        $dealer = Dealer::factory()->create(['phone' => null, 'photo_path' => null]);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'phone' => '0912345678', 'avatar_path' => 'chosen.jpg']);
        Storage::disk('dealer_avatars')->put('chosen.jpg', 'chosen');
        config(['dealer-registration.manager_contacts' => [$dealer->slug => $manager->id]]);
        $this->get(route('dealers.photo', $dealer->id))->assertOk();
        config(['dealer-registration.manager_contacts' => []]);
        $this->get(route('dealers.photo', $dealer->id))->assertNotFound();
        $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertDontSee('class="dr-dealer-photo"', false)->assertDontSee('href="tel:', false);
    }

    public function test_export_browser_fixtures(): void
    {
        $dir = base_path('.laravel-tools/manager-introduction/qa');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $dealer = Dealer::factory()->create(['name' => 'Đại lý kiểm thử liên kết', 'address' => 'Địa chỉ đại lý trong DB kiểm thử', 'opening_hours' => '08:00 – 17:00', 'photo_path' => null, 'phone' => null]);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealer->id, 'name' => 'Nguyễn Minh Anh', 'email' => 'manager@example.test']);
        config(['dealer-registration.manager_contacts' => [$dealer->slug => $manager->id]]);
        foreach (['before' => ['0912345678', 'service'], 'after' => ['0987654321', 'gift']] as $state => [$phone, $image]) {
            $file = UploadedFile::fake()->createWithContent('chosen.png', file_get_contents(public_path('dealer-registration/benefits-five-'.$image.'-photo.png')));
            $this->actingAs($manager->fresh())->patch(route('profile.update'), ['name' => $manager->name, 'phone' => $phone, 'avatar' => $file])->assertRedirect()->assertSessionHasNoErrors();
            file_put_contents($dir.'/'.$state.'.html', $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->getContent());
            file_put_contents($dir.'/'.$state.'.jpg', Storage::disk('dealer_avatars')->get($manager->fresh()->avatar_path));
        }
        file_put_contents($dir.'/profile.html', $this->actingAs($manager->fresh())->get(route('profile.edit'))->assertOk()->getContent());
        $manager->forceFill(['avatar_path' => null, 'phone' => null])->save();
        file_put_contents($dir.'/missing.html', $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->getContent());
        $other = Dealer::factory()->create(['phone' => null, 'photo_path' => null]);
        file_put_contents($dir.'/other.html', $this->get(app(DealerProfile::class)->url($other))->assertOk()->getContent());
    }
}
