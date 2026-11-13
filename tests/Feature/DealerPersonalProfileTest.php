<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SalesAvatars;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerPersonalProfileTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFixtureTransaction; }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $config = DB::connection()->getConfig();
        $this->assertContains($config['host'], ['127.0.0.1', 'localhost', '::1']);
        $actual = DB::selectOne('SELECT DATABASE() db, @@hostname host');
        $this->assertSame('toyota_testing', $actual->db);
        $this->assertSame(strtolower(gethostname()), strtolower($actual->host));
        $this->beginFixtureTransaction();
    }

    public function test_manager_can_update_name_but_not_identity(): void
    {
        $user = User::factory()->dealer()->create();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertViewIs('profile.dealer')
            ->assertSee('Quản lý đại lý')->assertSee('data-dealer-profile', false)->assertDontSee('name="email"', false);
        $this->patch(route('profile.update'), ['name' => 'Nguyễn Minh Anh'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Nguyễn Minh Anh', $user->fresh()->name);
        $this->patch(route('profile.update'), ['name' => 'Attack', 'email' => 'attack@example.test', 'role' => 'admin', 'dealer_id' => 999])
            ->assertSessionHasErrors(['email', 'role', 'dealer_id']);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame($user->dealer_id, $user->fresh()->dealer_id);
    }

    #[DataProvider('formats')]
    public function test_manager_upload_is_reencoded_private_and_replaced(string $extension): void
    {
        Storage::fake('dealer_avatars');
        $user = User::factory()->dealer()->create();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('portrait.'.$extension, 800, 600)])
            ->assertSessionHasNoErrors();
        $first = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('uploaded-', $first);
        $bytes = Storage::disk('dealer_avatars')->get($first);
        $this->assertSame([512, 384, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($bytes), 0, 3));
        $this->actingAs($user->fresh())->get(route('profile.avatar'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('profile.edit'))->assertSee(route('profile.avatar'))->assertDontSee($first);
        $this->patch(route('profile.update'), ['name' => 'Changed'])->assertSessionHasNoErrors();
        $this->assertSame($first, $user->fresh()->avatar_path);
        $this->patch(route('profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('next.png')])->assertSessionHasNoErrors();
        $this->assertNotSame($first, $user->fresh()->avatar_path);
        Storage::disk('dealer_avatars')->assertMissing($first);
        Storage::disk('dealer_avatars')->assertExists($user->fresh()->avatar_path);
    }

    public static function formats(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_upload_preserves_old_avatar(string $kind): void
    {
        Storage::fake('dealer_avatars');
        Storage::disk('dealer_avatars')->put('old.jpg', 'old-image');
        $user = User::factory()->dealer()->create(['avatar_path' => 'old.jpg']);
        $file = match ($kind) {
            'svg' => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'executable' => UploadedFile::fake()->createWithContent('image.php', '<?php echo 1;'),
            'fake' => UploadedFile::fake()->createWithContent('image.jpg', 'not an image'),
            'extension' => UploadedFile::fake()->image('image.php'),
            'large' => UploadedFile::fake()->image('image.png')->size(5121),
            'dimensions' => UploadedFile::fake()->image('image.jpg', 4097, 1),
        };
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => $file])->assertSessionHasErrors('avatar');
        $this->assertSame('old.jpg', $user->fresh()->avatar_path);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame(['old.jpg'], Storage::disk('dealer_avatars')->allFiles());
    }

    public static function invalidFiles(): array
    {
        return [['svg'], ['executable'], ['fake'], ['extension'], ['large'], ['dimensions']];
    }

    public function test_storage_failure_does_not_delete_old_image_or_update_profile(): void
    {
        Storage::fake('dealer_avatars');
        $disk = Storage::disk('dealer_avatars');
        $disk->put('old.jpg', 'old-image');
        $user = User::factory()->dealer()->create(['avatar_path' => 'old.jpg']);
        $failedDisk = Mockery::mock($disk)->makePartial();
        $failedDisk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('dealer_avatars')->andReturn($failedDisk);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasErrors('avatar');
        $this->assertSame('old.jpg', $user->fresh()->avatar_path);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame('old-image', $disk->get('old.jpg'));
    }

    public function test_manager_cannot_target_other_users_and_avatar_has_no_public_access(): void
    {
        Storage::fake('dealer_avatars');
        $user = User::factory()->dealer()->create();
        $other = User::factory()->dealer()->create(['avatar_path' => 'other.jpg']);
        Storage::disk('dealer_avatars')->put('other.jpg', 'private-image');
        $this->getJson(route('profile.avatar'))->assertUnauthorized();
        $this->patchJson(route('profile.update'), ['name' => 'Guest'])->assertUnauthorized();
        $this->actingAs($user)->get(route('profile.avatar', ['user_id' => $other->id]))->assertNotFound();
        foreach (['user_id' => $other->id, 'id' => $other->id, 'avatar_path' => 'other.jpg'] as $field => $value) {
            $this->patch(route('profile.update'), ['name' => 'Attack', $field => $value, 'avatar' => UploadedFile::fake()->image('attack.jpg')])->assertSessionHasErrors($field);
        }
        $this->patch('/profile/'.$other->id, ['name' => 'Attack'])->assertNotFound();
        $this->assertSame('other.jpg', $other->fresh()->avatar_path);
        $this->assertNull($user->fresh()->avatar_path);
        foreach ([User::factory()->admin()->create(), User::factory()->active()->create()] as $nonManager) {
            $this->actingAs($nonManager)->get(route('profile.avatar'))->assertForbidden();
        }
        $blocked = User::factory()->dealer()->create(['status' => 'blocked']);
        $this->actingAs($blocked)->patchJson(route('profile.update'), ['name' => 'Attack'])->assertForbidden();
        $this->getJson(route('profile.avatar'))->assertForbidden();
    }

    public function test_new_file_is_cleaned_if_locked_account_check_fails(): void
    {
        Storage::fake('dealer_avatars');
        $disk = Storage::disk('dealer_avatars');
        $disk->put('old.jpg', 'old-image');
        $user = User::factory()->dealer()->create(['avatar_path' => 'old.jpg']);
        $this->partialMock(SalesAvatars::class, function ($mock) use ($user, $disk) {
            $mock->shouldReceive('store')->once()->andReturnUsing(function () use ($user, $disk) {
                $disk->put('uploaded-11111111-1111-4111-8111-111111111111.jpg', 'new-image');
                User::whereKey($user->id)->update(['status' => 'blocked']);

                return 'uploaded-11111111-1111-4111-8111-111111111111.jpg';
            });
        });
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => UploadedFile::fake()->image('new.jpg')])->assertForbidden();
        $this->assertSame('old.jpg', $user->fresh()->avatar_path);
        $disk->assertExists('old.jpg');
        $disk->assertMissing('uploaded-11111111-1111-4111-8111-111111111111.jpg');
    }

    public function test_password_requires_current_password_and_confirmation(): void
    {
        $user = User::factory()->dealer()->create();
        $data = ['current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
        $this->actingAs($user)->put(route('profile.password'), $data)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->put(route('profile.password'), [...$data, 'current_password' => 'password', 'password_confirmation' => 'mismatch'])->assertSessionHasErrors('password');
        $this->put(route('profile.password'), [...$data, 'current_password' => 'password'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotSame($user->remember_token, $user->fresh()->remember_token);
    }

    public function test_sales_and_admin_keep_existing_profile_views_and_permissions(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        foreach ([$sales, $admin] as $actor) {
            $this->actingAs($actor)->get(route('profile.edit'))->assertOk()->assertViewIs('profile.edit')->assertDontSee('data-dealer-profile', false);
        }
        $this->actingAs($admin)->patch(route('profile.update'), ['name' => $admin->name, 'avatar' => UploadedFile::fake()->image('no.jpg')])->assertSessionHasErrors('avatar');
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => 'Sales', 'phone' => '0901234567', 'avatar' => UploadedFile::fake()->image('yes.jpg')])->assertSessionHasNoErrors();
        Storage::disk('sales_avatars')->assertExists($sales->fresh()->avatar_path);
        $this->assertSame('0901234567', $sales->fresh()->phone);
        $this->get(route('sales.avatar', $sales))->assertOk();
    }

    public function test_export_browser_fixtures(): void
    {
        Storage::fake('dealer_avatars');
        $user = User::factory()->dealer()->create(['name' => 'Nguyễn Minh Anh', 'email' => 'manager@example.test']);
        $dir = base_path('.laravel-tools/dealer-profile/qa');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $this->actingAs($user);
        file_put_contents($dir.'/default.html', $this->get(route('profile.edit'))->assertOk()->getContent());
        $this->patch(route('profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('avatar.png', 600, 600)])->assertSessionHasNoErrors();
        file_put_contents($dir.'/saved.html', $this->actingAs($user->fresh())->get(route('profile.edit'))->assertOk()->getContent());
        file_put_contents($dir.'/avatar.jpg', Storage::disk('dealer_avatars')->get($user->fresh()->avatar_path));
        $this->patch(route('profile.update'), ['name' => ''])->assertSessionHasErrors('name');
        file_put_contents($dir.'/errors.html', $this->get(route('profile.edit'))->assertOk()->getContent());
    }
}
