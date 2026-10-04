<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SubmissionApprovalRaceTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private migrateTestDatabase;
    }

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Race tests may only migrate toyota_testing.');
        }
        $this->migrateTestDatabase();
    }

    public function test_two_real_mysql_transactions_approve_only_one_normalized_profile(): void
    {
        // No outer test transaction: both subprocess connections must see committed fixtures.
        $admins = User::factory()->count(2)->admin()->create();
        $first = CustomerSubmission::factory()->create(['facebook_url' => 'https://facebook.com/alice.example']);
        $second = CustomerSubmission::factory()->create(['facebook_url' => 'http://m.facebook.com/Alice.Example/?ref=share#about']);
        $barrier = storage_path('framework/toyota-approval-race-'.bin2hex(random_bytes(8)));
        mkdir($barrier);
        $connection = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'toyota_testing',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'DB_URL' => false,
        ];
        $workers = [];
        try {
            foreach ([$first, $second] as $index => $submission) {
                $worker = new Process([
                    PHP_BINARY, '-d', 'sys_temp_dir='.$barrier, base_path('tests/Fixtures/approve_submission.php'),
                    (string) $admins[$index]->id, (string) $submission->id, $barrier,
                ], base_path(), $env, null, 20);
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
            $this->assertSame(['approved', 'duplicate'], $results);
            $this->assertSame(1, CustomerSubmission::where('status', 'approved')->count());
            $this->assertSame(1, CustomerSubmission::where('status', 'pending')->count());
            $this->assertSame(1, ModerationReview::count());
            $loser = CustomerSubmission::where('status', 'pending')->sole();
            $this->assertNull($loser->reviewed_by);
            $this->assertNull($loser->reviewed_at);
            $this->assertNull($loser->admin_note);
            $this->assertSame(0, $loser->moderationReviews()->count());
            $winner = CustomerSubmission::where('status', 'approved')->sole();
            $this->assertSame($winner->id, ModerationReview::sole()->customer_submission_id);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($barrier.'/*.ready') as $file) {
                unlink($file);
            }
            rmdir($barrier);
        }
    }
}
