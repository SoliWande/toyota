<?php

namespace Tests\Feature;

use App\Models\CallAuditBatch;
use App\Services\CallAudit\ReproductionVerifier;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CallAuditFreezeFixtures;
use Tests\TestCase;

class CallAuditFreezeConcurrencyTest extends TestCase
{
    #[DataProvider('races')]
    public function test_real_connections_serialize_creators_and_late_submissions(string $mode): void
    {
        $fixtures = new CallAuditFreezeFixtures;
        $workers = [];
        $directory = storage_path('app/private/p09h-races/'.$fixtures->tag);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        try {
            $admins = [$fixtures->admin(), $fixtures->admin()];
            $this->actingAs($admins[0]);
            $dealer = $fixtures->dealer();
            $fixtures->submission($dealer);
            $config = DB::connection()->getConfig();
            $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('tests/Fixtures/.p09h-disabled-config-cache.php'),
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'toyota_testing',
                'TOYOTA_TEST_DATABASE' => 'toyota_testing', 'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '',
                'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
            foreach (['holder', 'contender'] as $index => $role) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/call_audit_freeze_worker.php'),
                    (string) $admins[$index]->id, $fixtures->tag.'-race-'.($index ? 'b' : 'a'), $directory, $role, $mode, (string) $dealer->id], base_path(), $env, null, 20);
                $workers[] = $worker;
                $worker->start();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($directory.'/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
            $this->assertSame('FROZEN', $results[0]['status']);
            $this->assertGreaterThan(250, $results[1]['wait_ms']);
            if ($mode === 'freeze') {
                $this->assertSame('BLOCKED', $results[1]['status']);
                $this->assertSame('generation_already_frozen', $results[1]['code']);
            } else {
                $this->assertSame('LATE_PENDING', $results[1]['status']);
                $this->assertSame('pending', DB::table('customer_submissions')->where('id', $results[1]['submission_id'])->value('status'));
            }
            $this->assertSame(1, CallAuditBatch::count());
            $batch = CallAuditBatch::sole();
            $this->assertSame(1, $batch->actual_count);
            $this->assertSame(1, $batch->items()->count());
            $this->assertSame(1, $batch->events()->count());
            $this->assertSame('VERIFIED', app(ReproductionVerifier::class)->verify($batch->id)->summary['status']);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            $fixtures->cleanup();
            // Keep private synchronization files as evidence, never recursively remove directories.
        }
    }

    public static function races(): array
    {
        return [['freeze'], ['late']];
    }
}
