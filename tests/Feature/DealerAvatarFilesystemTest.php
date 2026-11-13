<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerAvatarFilesystemTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFixtureTransaction; }

    private string $fixtureRoot;

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
        // Assert real booted config BEFORE overriding only the fixture storage root.
        // Storage::fake would silently supply its own local driver and hide this regression.
        $disk = config('filesystems.disks.dealer_avatars');
        $this->assertIsArray($disk);
        $this->assertSame('local', $disk['driver'] ?? null);
        $this->assertSame(storage_path('app/private/dealer-avatars'), $disk['root'] ?? null);
        $this->assertSame('private', $disk['visibility'] ?? null);
        $this->assertTrue($disk['throw'] ?? false);
        $this->assertInstanceOf(LocalFilesystemAdapter::class, Storage::disk('dealer_avatars'));
        $this->fixtureRoot = storage_path('framework/testing/disks/dealer-config-'.Str::uuid());
        config(['filesystems.disks.dealer_avatars.root' => $this->fixtureRoot]);
        Storage::forgetDisk('dealer_avatars');
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot)) {
            $disk = Storage::disk('dealer_avatars');
            foreach ($disk->allFiles() as $file) {
                $disk->delete($file);
            }
            if (is_dir($this->fixtureRoot)) {
                rmdir($this->fixtureRoot);
            }
        }
        parent::tearDown();
    }

    public function test_source_and_runtime_use_the_same_private_disk_without_an_extra_env_variable(): void
    {
        $source = require config_path('filesystems.php');
        $this->assertSame('local', $source['disks']['dealer_avatars']['driver']);
        $this->assertSame(storage_path('app/private/dealer-avatars'), $source['disks']['dealer_avatars']['root']);
        $disk = Storage::disk('dealer_avatars');
        $this->assertInstanceOf(LocalFilesystemAdapter::class, $disk);
        $this->assertSame($this->fixtureRoot.DIRECTORY_SEPARATOR.'probe.txt', $disk->path('probe.txt'));
        $this->assertTrue($disk->put('probe.txt', 'private-disk-check'));
        $this->assertSame('private-disk-check', $disk->get('probe.txt'));
    }

    #[DataProvider('emptyAvatars')]
    public function test_upload_read_private_url_and_replace_twice_using_real_configured_adapter(?string $old): void
    {
        $user = User::factory()->dealer()->create(['avatar_path' => $old]);
        $disk = Storage::disk('dealer_avatars');
        $previous = null;
        foreach (['png', 'jpg', 'webp'] as $extension) {
            $this->actingAs($user->fresh())->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('portrait.'.$extension, 240, 240),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $current = $user->fresh()->avatar_path;
            $this->assertNotEmpty($current);
            $this->assertTrue($disk->exists($current));
            if ($previous !== null) {
                $this->assertNotSame($previous, $current);
                $this->assertFalse($disk->exists($previous));
            }
            $avatar = $this->actingAs($user->fresh())->get(route('profile.avatar'))->assertOk()
                ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame($disk->get($current), $avatar->streamedContent());
            $html = $this->get(route('profile.edit'))->assertOk()->getContent();
            $this->assertGreaterThanOrEqual(3, substr_count($html, 'src="'.route('profile.avatar').'"'));
            $this->assertStringNotContainsString($current, $html);
            $this->assertCount(1, $disk->allFiles());
            $previous = $current;
        }
    }

    public static function emptyAvatars(): array
    {
        return [[null], ['']];
    }
}
