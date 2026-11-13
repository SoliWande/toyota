<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class RegistrationGroupFirstBrowserTest extends TestCase
{
    public function test_group_first_registration_validation_duplicate_lookup_and_inactive_browser(): void
    {
        $fixtures = new CallAuditFreezeFixtures;
        $dealer = $fixtures->dealer();
        $other = $fixtures->dealer();
        $inactive = $fixtures->dealer();
        $fixtures->change($dealer, ['name' => 'Toyota Kiểm tra luồng đăng ký']);
        $fixtures->change($inactive, ['is_active' => false]);
        $fixtures->submission($other, ['phone' => '0990900099', 'phone_normalized' => '0990900099', 'license_plate' => '51K87654']);
        $directory = storage_path('app/private/d02-d06-browser/'.$fixtures->tag);
        File::ensureDirectoryExists($directory.'/sessions');
        File::ensureDirectoryExists($directory.'/cache');
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09-final-disabled-config-cache.php'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing', 'TOYOTA_TEST_DATABASE' => 'toyota_testing',
            'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'APP_URL' => 'http://127.0.0.1:8781', 'D02_FIXTURE_DIRECTORY' => $directory,
            'D02_FIXTURE_SLUGS' => implode(',', [$dealer->slug, $other->slug, $inactive->slug])];
        $server = new Process([PHP_BINARY, '-S', '127.0.0.1:8781', '-t', 'public', base_path('tests/Fixtures/d02_d06_ui_router.php')], base_path(), $env, null, 150);
        try {
            $server->start();
            $qa = new Process(['node', base_path('tests/Browser/d02-d06-registration.mjs')], base_path(), [
                'D02_DEALER' => $dealer->slug, 'D02_OTHER' => $other->slug, 'D02_INACTIVE' => $inactive->slug,
                'D02_NAME' => $fixtures->tag,
            ], null, 130);
            $qa->run();
            $this->assertTrue($qa->isSuccessful(), $qa->getErrorOutput().$qa->getOutput());
            $records = CustomerSubmission::where('customer_name', $fixtures->tag)->where('dealer_id', $dealer->id)->get();
            $this->assertCount(2, $records);
            foreach ($records as $record) {
                $this->assertSame('pending', $record->status->value);
                $this->assertNull($record->sales_id);
                $this->assertNull($record->reviewed_by);
                $this->assertNull($record->approved_facebook_identity);
                $this->assertSame(0, DB::table('submission_verification_states')->where('submission_id', $record->id)->count());
            }
            $this->assertSame(1, CustomerSubmission::where('dealer_id', $other->id)->count());
        } finally {
            if ($server->isRunning()) {
                $pidFile = $directory.'/server.pid';
                if (is_file($pidFile) && ctype_digit(trim(file_get_contents($pidFile)))) {
                    (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command',
                        'Stop-Process -Id '.(int) trim(file_get_contents($pidFile)).' -ErrorAction Stop'], base_path(), null, null, 10))->run();
                }
                $server->stop();
            }
            $ids = CustomerSubmission::where('customer_name', $fixtures->tag)->whereIn('dealer_id', [$dealer->id, $other->id, $inactive->id])->pluck('id');
            DB::table('dealer_registration_receipts')->whereIn('submission_id', $ids)->delete();
            $fixtures->cleanup();
        }
    }
}
