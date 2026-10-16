<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\RestoresVerifiedDealerDatabase;
use Tests\TestCase;

class DealerAccountCreationRaceTest extends TestCase
{
    use RestoresVerifiedDealerDatabase;

    public function test_two_admin_transactions_create_exactly_one_login_for_a_dealer(): void
    {
        (require database_path('migrations/2026_10_16_000015_prepare_dealer_only_database.php'))->up();
        // No parent transaction: both PHP workers must see committed fixtures.
        $admins = User::factory()->count(2)->admin()->create();
        $dealer = Dealer::factory()->create();
        $connection = config('database.connections.mysql');
        $barrier = base_path('.laravel-tools/dealer-account-race-'.bin2hex(random_bytes(8)));
        mkdir($barrier, 0700);
        $workers = [];
        try {
            foreach ($admins as $index => $admin) {
                $worker = new Process([
                    PHP_BINARY, '-d', 'sys_temp_dir='.$barrier, base_path('tests/Fixtures/create_dealer_account.php'),
                    (string) $admin->id, (string) $dealer->id, 'race-'.$index.'@example.test', $barrier,
                ], base_path(), [
                    'APP_ENV' => 'testing', 'BCRYPT_ROUNDS' => '4',
                    'DEALER_ACCOUNT_TEST_DATABASE' => DB::connection()->getDatabaseName(),
                    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
                    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
                ], null, 30);
                $worker->start();
                $workers[] = $worker;
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = trim($worker->getOutput());
            }
            sort($results);
            $this->assertSame(['created', 'duplicate'], $results);
            $account = User::where('role', 'dealer')->where('dealer_id', $dealer->id)->sole();
            $this->assertSame('pending', $account->status->value);
            $this->assertContains($account->email, ['race-0@example.test', 'race-1@example.test']);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ($admins as $admin) {
                $ready = $barrier.'/'.$admin->id.'.ready';
                if (is_file($ready)) {
                    unlink($ready);
                }
            }
            rmdir($barrier);
        }
    }
}
