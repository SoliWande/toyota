<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\RestoresVerifiedDealerDatabase;
use Tests\TestCase;

class SubmissionApprovalRaceTest extends TestCase
{
    use RestoresVerifiedDealerDatabase;

    public function test_two_real_mysql_transactions_approve_only_one_normalized_profile(): void
    {
        (require database_path('migrations/2026_10_16_000015_prepare_dealer_only_database.php'))->up();
        // No outer test transaction: both subprocess connections must see committed fixtures.
        $admins = User::factory()->count(2)->admin()->create();
        $first = CustomerSubmission::factory()->create(['facebook_url' => 'https://facebook.com/alice.example']);
        $second = CustomerSubmission::factory()->create(['facebook_url' => 'http://m.facebook.com/Alice.Example/?ref=share#about']);
        $ids = [$first->id, $second->id];
        $barrier = base_path('.laravel-tools/submission-approval-race-'.bin2hex(random_bytes(8)));
        mkdir($barrier);
        $connection = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
            'DEALER_SUBMISSION_TEST_DATABASE' => DB::connection()->getDatabaseName(),
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
            $this->assertSame(1, CustomerSubmission::whereIn('id', $ids)->where('status', 'approved')->count());
            $this->assertSame(1, CustomerSubmission::whereIn('id', $ids)->where('status', 'pending')->count());
            $this->assertSame(1, ModerationReview::whereIn('customer_submission_id', $ids)->count());
            $loser = CustomerSubmission::whereIn('id', $ids)->where('status', 'pending')->sole();
            $this->assertNull($loser->reviewed_by);
            $this->assertNull($loser->reviewed_at);
            $this->assertNull($loser->admin_note);
            $this->assertSame(0, $loser->moderationReviews()->count());
            $winner = CustomerSubmission::whereIn('id', $ids)->where('status', 'approved')->sole();
            $this->assertSame($winner->id, ModerationReview::whereIn('customer_submission_id', $ids)->sole()->customer_submission_id);
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
