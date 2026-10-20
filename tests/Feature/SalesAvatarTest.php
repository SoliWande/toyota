<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\User;
use Database\Seeders\DevelopmentSalesAvatarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_upload_replace_and_keep_an_avatar_while_protected_account_fields_stay_unchanged(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $email = $sales->email;
        $dealerId = $sales->dealer_id;
        $this->actingAs($sales)->get(route('profile.edit'))->assertOk()->assertSee('multipart/form-data')->assertSee('Ảnh đại diện mặc định');
        $this->patch(route('profile.update'), ['name' => $sales->name, 'avatar' => UploadedFile::fake()->image('photo.png', 800, 600)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $path = $sales->fresh()->avatar_path;
        Storage::disk('sales_avatars')->assertExists($path);
        $image = getimagesizefromstring(Storage::disk('sales_avatars')->get($path));
        $this->assertSame([512, 384, IMAGETYPE_JPEG], array_slice($image, 0, 3));
        $this->get(route('sales.avatar', $sales))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->patch(route('profile.update'), ['name' => 'Updated name'])->assertSessionHasNoErrors();
        $this->assertSame($path, $sales->fresh()->avatar_path);
        $this->patch(route('profile.update'), ['name' => 'Updated name', 'avatar' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $this->assertNotSame($path, $sales->fresh()->avatar_path);
        Storage::disk('sales_avatars')->assertMissing($path);
        $this->assertSame($email, $sales->fresh()->email);
        $this->assertSame($dealerId, $sales->fresh()->dealer_id);
        $this->assertArrayNotHasKey('avatar_path', $sales->fresh()->toArray());
    }

    public function test_avatar_endpoint_is_private_for_admin_and_owner_only(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create(['avatar_path' => 'private.jpg']);
        Storage::disk('sales_avatars')->put('private.jpg', 'private-image');
        $url = route('sales.avatar', $sales);
        $this->get($url)->assertRedirect(route('login'));
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->active()->create(['dealer_id' => $sales->dealer_id]))->get($url)->assertForbidden();
        $this->actingAs($sales)->get($url)->assertOk();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->get(route('sales.avatar', $admin))->assertForbidden();
        foreach ([UserStatus::Pending, UserStatus::Blocked, UserStatus::Rejected] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]))->getJson($url)->assertForbidden();
        }
    }

    public function test_dashboard_shows_avatar_and_missing_file_falls_back_to_placeholder(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create(['avatar_path' => 'missing.jpg']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('sales.avatar', $sales))->assertSee('data-sales-avatar')->assertSee('Ảnh đại diện mặc định');
        $this->get(route('sales.avatar', $sales))->assertNotFound();
        $sales->forceFill(['avatar_path' => null])->save();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('sales.avatar', $sales))->assertSee('Ảnh đại diện mặc định');
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_uploads_are_rejected_without_files_or_account_changes(string $kind): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $file = match ($kind) {
            'svg' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg" />'),
            'text' => UploadedFile::fake()->createWithContent('avatar.jpg', 'not an image'),
            'large' => UploadedFile::fake()->image('avatar.png')->size(5121),
            'dimensions' => UploadedFile::fake()->image('avatar.jpg', 4097, 10),
        };
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => 'Changed', 'avatar' => $file])->assertSessionHasErrors('avatar');
        $this->assertNull($sales->fresh()->avatar_path);
        $this->assertSame($sales->name, $sales->fresh()->name);
        $this->assertSame([], Storage::disk('sales_avatars')->allFiles());
    }

    public static function invalidUploads(): array
    {
        return array_map(fn ($kind) => [$kind], ['svg', 'text', 'large', 'dimensions']);
    }

    public function test_avatar_path_cannot_be_injected_and_admin_profile_does_not_accept_sales_uploads(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => $sales->name, 'avatar_path' => '../secret'])
            ->assertSessionHasErrors('avatar_path');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('profile.update'), ['name' => $admin->name, 'avatar' => UploadedFile::fake()->image('avatar.jpg')])
            ->assertSessionHasErrors('avatar');
        $this->assertSame([], Storage::disk('sales_avatars')->allFiles());
    }

    public function test_demo_seeder_fills_only_reserved_demo_accounts_and_preserves_existing_uploads_on_rerun(): void
    {
        Storage::fake('sales_avatars');
        $demo = User::factory()->active()->create(['name' => '[DEMO] Example', 'email' => 'demo.sales.01@example.test']);
        $uploaded = User::factory()->active()->create(['name' => '[DEMO] Uploaded', 'email' => 'demo.sales.02@example.test', 'avatar_path' => 'uploaded.jpg']);
        $real = User::factory()->active()->create();
        $this->seed(DevelopmentSalesAvatarSeeder::class);
        $path = $demo->fresh()->avatar_path;
        Storage::disk('sales_avatars')->assertExists($path);
        $this->assertSame('uploaded.jpg', $uploaded->fresh()->avatar_path);
        $this->assertNull($real->fresh()->avatar_path);
        $files = Storage::disk('sales_avatars')->allFiles();
        $this->seed(DevelopmentSalesAvatarSeeder::class);
        $this->assertSame($path, $demo->fresh()->avatar_path);
        $this->assertSame($files, Storage::disk('sales_avatars')->allFiles());
    }

    public function test_demo_seeder_refuses_conflicting_accounts_and_production(): void
    {
        Storage::fake('sales_avatars');
        User::factory()->active()->create(['name' => 'Real person', 'email' => 'demo.sales.01@example.test']);
        try {
            $this->seed(DevelopmentSalesAvatarSeeder::class);
            $this->fail('Conflicting account accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Conflicting', $exception->getMessage());
        }
        $this->app->instance('env', 'production');
        try {
            $this->app->call([new DevelopmentSalesAvatarSeeder, 'run']);
            $this->fail('Production seeding accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertSame([], Storage::disk('sales_avatars')->allFiles());
    }
}
