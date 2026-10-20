<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class DevelopmentDealerAccountSeeder extends Seeder
{
    public ?string $credentialsFile = null;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Dealer accounts are restricted to local/testing.');
        }

        $this->credentialsFile = null;
        $file = null;
        $createdFile = false;
        $created = 0;
        $skipped = 0;
        try {
            DB::transaction(function () use (&$file, &$createdFile, &$created, &$skipped) {
                $credentials = [];
                // Match Admin's parent-row lock and retain the database unique constraint as the final guard.
                foreach (Dealer::orderBy('id')->lockForUpdate()->get() as $dealer) {
                    if ($dealer->manager()->exists()) {
                        $skipped++;

                        continue;
                    }
                    $email = 'demo.dealer.'.$dealer->id.'@example.test';
                    if (User::where('email', $email)->exists()) {
                        throw new LogicException('Reserved Dealer demo email conflicts with an existing account; no accounts were changed.');
                    }
                    $password = Str::random(32);
                    $manager = new User([
                        'name' => 'Quản lý '.Str::limit($dealer->name, 240, ''),
                        'email' => $email,
                        'password' => $password,
                    ]);
                    $manager->role = UserRole::Dealer;
                    $manager->status = $dealer->is_active ? UserStatus::Active : UserStatus::Blocked;
                    $manager->dealer_id = $dealer->id;
                    $manager->email_verified_at = now();
                    $manager->save();
                    $credentials[] = ['dealer_id' => $dealer->id, 'dealer_name' => $dealer->name, 'dealer_slug' => $dealer->slug,
                        'email' => $email, 'password' => $password, 'status' => $manager->status->value];
                    $created++;
                }

                if ($credentials === []) {
                    return;
                }
                // Never put plaintext passwords in console output, logs, public storage or tracked source.
                $directory = base_path('.laravel-tools/dealer-accounts');
                File::ensureDirectoryExists($directory, 0700);
                $file = $directory.'/credentials-'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
                $handle = @fopen($file, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Cannot create private Dealer credential export.');
                }
                $createdFile = true;
                try {
                    $contents = json_encode(['login_url' => route('login'), 'accounts' => $credentials], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    if (fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle)) {
                        throw new RuntimeException('Cannot complete private Dealer credential export.');
                    }
                } finally {
                    fclose($handle);
                }
                @chmod($file, 0600);
            });
        } catch (Throwable $error) {
            if ($createdFile && $file !== null) {
                File::delete($file);
            }
            throw $error;
        }
        $this->credentialsFile = $file;
        $this->command?->info("Created {$created} Dealer accounts; preserved {$skipped} existing managers.");
        if ($file !== null) {
            $this->command?->info('Private credential file: '.$file);
        }
    }
}
