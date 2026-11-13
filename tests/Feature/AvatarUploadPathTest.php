<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SalesAvatars;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AvatarUploadPathTest extends TestCase
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

    #[DataProvider('emptyAvatars')]
    public function test_first_upload_and_replacement_with_unresolvable_realpath(?string $oldPath): void
    {
        Storage::fake('dealer_avatars');
        $user = User::factory()->dealer()->create(['avatar_path' => $oldPath]);
        // Real readable temporary file, but canonical path resolution fails as in the log.
        $file = UploadedFile::fake()->image('portrait.png', 200, 200);
        $upload = new class($file->getPathname(), 'portrait.png', 'image/png', UPLOAD_ERR_OK, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };
        $this->assertFalse($upload->getRealPath());
        $this->assertTrue(is_readable($upload->getPathname()));
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'avatar' => $upload])->assertRedirect()->assertSessionHasNoErrors();
        $first = $user->fresh()->avatar_path;
        Storage::disk('dealer_avatars')->assertExists($first);
        $this->actingAs($user->fresh())->get(route('profile.avatar'))->assertOk();
        $this->get(route('profile.edit'))->assertOk()->assertSee(route('profile.avatar'));
        $this->patch(route('profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('next.webp')])->assertSessionHasNoErrors();
        Storage::disk('dealer_avatars')->assertMissing($first);
        Storage::disk('dealer_avatars')->assertExists($user->fresh()->avatar_path);
    }

    public static function emptyAvatars(): array
    {
        return [[null], ['']];
    }

    public function test_empty_or_disappeared_temporary_file_is_validation_error(): void
    {
        Storage::fake('dealer_avatars');
        $user = User::factory()->dealer()->create(['avatar_path' => 'old.jpg']);
        Storage::disk('dealer_avatars')->put('old.jpg', 'old');
        $file = UploadedFile::fake()->image('portrait.png');
        $upload = new class($file->getPathname(), 'portrait.png', 'image/png', UPLOAD_ERR_OK, true) extends UploadedFile
        {
            public function getPathname(): string
            {
                return '';
            }
        };
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => $upload])->assertSessionHasErrors('avatar');
        $missing = new UploadedFile(base_path('.laravel-tools/avatar-path-fix/not-present.png'), 'missing.png', 'image/png', UPLOAD_ERR_NO_TMP_DIR, true);
        $this->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => $missing])->assertSessionHasErrors('avatar');
        $this->assertSame('old.jpg', $user->fresh()->avatar_path);
        $this->assertSame(['old.jpg'], Storage::disk('dealer_avatars')->allFiles());
    }

    public function test_service_rejects_empty_path_before_file_operations(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read avatar upload.');
        app(SalesAvatars::class)->store('');
    }

    public function test_only_managed_avatar_names_can_be_deleted(): void
    {
        $disk = Mockery::mock();
        $name = 'uploaded-11111111-1111-4111-8111-111111111111.jpg';
        $disk->shouldReceive('delete')->once()->with($name)->andReturn(true);
        Storage::shouldReceive('disk')->once()->with('dealer_avatars')->andReturn($disk);
        $service = app(SalesAvatars::class);
        foreach ([null, '', ' ', "\0", '../outside.jpg', '/outside.jpg', 'C:\\outside.jpg', 'default.jpg', 'default.svg', 'old.jpg', 'uploaded-not-a-uuid.jpg'] as $unsafe) {
            $service->deleteManaged($unsafe, 'dealer_avatars');
        }
        $service->deleteManaged($name, 'public');
        $service->deleteManaged($name, 'dealer_avatars');
        $this->assertTrue(true);
    }

    public function test_default_or_unmanaged_old_file_survives_replacement(): void
    {
        Storage::fake('dealer_avatars');
        $disk = Storage::disk('dealer_avatars');
        $disk->put('default.jpg', 'default-image');
        $user = User::factory()->dealer()->create(['avatar_path' => 'default.jpg']);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $disk->assertExists('default.jpg');
        $disk->assertExists($user->fresh()->avatar_path);
    }

    public function test_partial_storage_write_failure_is_cleaned_and_old_avatar_survives(): void
    {
        Storage::fake('dealer_avatars');
        $disk = Storage::disk('dealer_avatars');
        $disk->put('old.jpg', 'old-image');
        $user = User::factory()->dealer()->create(['avatar_path' => 'old.jpg']);
        $adapter = Mockery::mock($disk)->makePartial();
        $adapter->shouldReceive('put')->once()->andReturnUsing(function ($path, $bytes) use ($disk) {
            $disk->put($path, $bytes);

            return false;
        });
        Storage::shouldReceive('disk')->with('dealer_avatars')->andReturn($adapter);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => UploadedFile::fake()->image('new.png')])->assertSessionHasErrors('avatar');
        $this->assertSame('old.jpg', $user->fresh()->avatar_path);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame(['old.jpg'], $disk->allFiles());
    }

    public function test_database_save_failure_rolls_back_and_deletes_only_new_file(): void
    {
        Storage::fake('dealer_avatars');
        $disk = Storage::disk('dealer_avatars');
        $old = 'uploaded-11111111-1111-4111-8111-111111111111.jpg';
        $disk->put($old, 'old-image');
        $user = User::factory()->dealer()->create(['avatar_path' => $old]);
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(clone $dispatcher);
        try {
            User::updating(function (User $record) use ($user) {
                if ($record->id === $user->id) {
                    throw new QueryException('mysql', 'update users', [], new PDOException('Simulated database save failure'));
                }
            });
            $this->withoutExceptionHandling()->actingAs($user);
            try {
                $this->patch(route('profile.update'), ['name' => 'Do not save', 'avatar' => UploadedFile::fake()->image('new.jpg')]);
                $this->fail('Expected database save failure');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Simulated database save failure', $exception->getMessage());
            }
        } finally {
            User::setEventDispatcher($dispatcher);
        }
        $this->assertSame($old, $user->fresh()->avatar_path);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame([$old], $disk->allFiles());
        $this->actingAs($user->fresh())->get(route('profile.avatar'))->assertOk();
    }
}
