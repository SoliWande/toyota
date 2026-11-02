<?php

namespace Database\Seeders;

use App\Actions\ApproveCustomerSubmission;
use App\Actions\RejectCustomerSubmission;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

final class DealerRegistrationPhase2aDemoSeeder extends Seeder
{
    public const TAG = '[DEMO-PHASE2A-V1]';

    public function run(): void
    {
        // Deliberately limited to the explicitly authorized Laragon database.
        $connection = DB::connection();
        if (! app()->environment('local') || $connection->getDriverName() !== 'mysql'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            || $connection->getDatabaseName() !== 'toyota') {
            throw new LogicException('Phase 2A demo requires local loopback MySQL database toyota.');
        }
        if ($connection->selectOne('SELECT DATABASE() AS name')->name !== 'toyota') {
            throw new LogicException('Unexpected database; no demo data was written.');
        }
        $directory = base_path('.laravel-tools/phase-2a-demo');
        $credentials = $directory.'/credentials.json';
        $createdFile = false;
        try {
            DB::transaction(function () use ($directory, $credentials, &$createdFile) {
                $codes = ['DEMO-P2A-ACTIVE', 'DEMO-P2A-INACTIVE'];
                $emails = ['phase2a.admin@example.test', 'phase2a.dealer@example.test', 'phase2a.sales@example.test'];
                $existing = Dealer::whereIn('code', $codes)->lockForUpdate()->get();
                if ($existing->isNotEmpty() || User::whereIn('email', $emails)->exists()
                    || CustomerSubmission::where('notes', self::TAG)->exists() || File::exists($credentials)) {
                    if ($existing->count() === 2 && User::whereIn('email', $emails)->count() === 3
                        && CustomerSubmission::where('notes', self::TAG)->count() === 6 && File::exists($credentials)) {
                        $this->command?->info('Phase 2A demo already exists; preserving all data, reviews and passwords.');

                        return;
                    }
                    throw new LogicException('Partial/conflicting demo dataset or credential file; refusing to overwrite.');
                }
                // These are synthetic test values, never numbers to call or message.
                $phones = array_map(fn ($i) => '099000000'.$i, range(1, 6));
                if (CustomerSubmission::whereIn('phone', $phones)->orWhereIn('phone_normalized', $phones)->exists()) {
                    throw new LogicException('Demo phone fixtures conflict with existing data.');
                }
                $active = Dealer::create(['code' => $codes[0], 'name' => 'Demo Phase 2A Active', 'is_active' => true,
                    'address' => 'Địa chỉ giả lập — không phải đại lý thật', 'opening_hours' => 'Chỉ dùng kiểm thử local']);
                Dealer::create(['code' => $codes[1], 'name' => 'Demo Phase 2A Inactive', 'is_active' => false]);
                $password = Str::random(32);
                $accounts = [];
                foreach (['admin', 'dealer', 'sales'] as $i => $role) {
                    $user = new User;
                    $user->forceFill(['name' => self::TAG.' '.ucfirst($role), 'email' => $emails[$i],
                        'password' => $password, 'role' => $role, 'status' => 'active',
                        'dealer_id' => $role === 'admin' ? null : $active->id, 'email_verified_at' => now()])->save();
                    $accounts[$role] = $user;
                }
                $divisions = app(AdministrativeDivisions::class);
                $province = $divisions->provinces()[0];
                $ward = $divisions->wards($province['code'])[0];
                foreach (range(1, 6) as $i) {
                    $qr = $i <= 3;
                    $facebook = 'https://www.facebook.com/toyota.phase2a.synthetic.'.sprintf('%02d', $i);
                    $record = new CustomerSubmission;
                    $record->forceFill([
                        'source' => $qr ? 'dealer_qr' : 'sales', 'sales_id' => $qr ? null : $accounts['sales']->id,
                        'dealer_id' => $active->id, 'customer_name' => self::TAG.' Khách giả lập '.$i,
                        'phone' => $qr ? $phones[$i - 1] : '+84 990 000 00'.$i,
                        'phone_normalized' => $qr ? $phones[$i - 1] : null,
                        'facebook_url' => $qr ? null : $facebook, 'notes' => self::TAG,
                        'address_line' => 'Địa chỉ giả lập '.$i, 'province_code' => $province['code'],
                        'province_name' => $province['name'], 'ward_code' => $ward['code'], 'ward_name' => $ward['name'],
                        'vehicle_model' => 'veloz', 'vehicle_description' => 'Veloz', 'first_registration_year' => 2024,
                        'vehicle_color' => 'Trắng', 'license_plate' => 'DEMO-P2A-00'.$i,
                        'consented_at' => $qr ? now() : null, 'consent_version' => $qr ? config('dealer.consent_version') : null,
                    ])->save();
                    if ($i % 3 === 2) {
                        app(ApproveCustomerSubmission::class)->execute($accounts['admin'], $record, self::TAG, $qr ? $facebook : null);
                    } elseif ($i % 3 === 0) {
                        app(RejectCustomerSubmission::class)->execute($accounts['admin'], $record, 'Từ chối giả lập để kiểm thử.', self::TAG);
                    }
                }
                // Keep the generated secret out of console, logs, tracked files and public storage.
                File::ensureDirectoryExists($directory, 0700);
                $handle = @fopen($credentials, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Cannot create private demo credential file.');
                }
                $createdFile = true;
                try {
                    $contents = json_encode(['login_path' => '/login', 'password' => $password, 'accounts' => $emails,
                        'notice' => 'Local synthetic fixtures only; never call demo numbers.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                    if (fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle)) {
                        throw new RuntimeException('Cannot persist demo credentials.');
                    }
                } finally {
                    fclose($handle);
                }
                @chmod($credentials, 0600);
                $this->command?->info('Created 2 demo Dealers, 3 accounts, 6 submissions and 4 moderation reviews.');
            });
        } catch (Throwable $error) {
            if ($createdFile) {
                File::delete($credentials);
            }
            throw $error;
        }
        $this->command?->info('Private credentials: '.$credentials);
    }
}
