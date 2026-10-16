<?php

namespace Tests\Feature;

use App\Actions\CreateAwardDraft;
use App\Enums\AwardPeriod;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\RestoresVerifiedDealerDatabase;
use Tests\TestCase;

class AwardPublicationRaceTest extends TestCase
{
    use RestoresVerifiedDealerDatabase;

    public function test_two_real_admin_transactions_publish_only_one_immutable_snapshot(): void
    {
        (require database_path('migrations/2026_10_16_000015_prepare_dealer_only_database.php'))->up();
        $admins = User::factory()->count(2)->admin()->create();
        $award = app(CreateAwardDraft::class)->execute($admins[0], AwardPeriod::Monthly, CarbonImmutable::parse('2001-02-12'));
        CustomerSubmission::factory()->count(3)->approved()->create(['submitted_at' => '2001-02-12']);
        $results = app(AwardResults::class);
        $preview = $results->preview($award);
        $hash = $results->fingerprint($award, $preview);
        $barrier = base_path('.laravel-tools/award-publication-race-'.bin2hex(random_bytes(8)));
        mkdir($barrier);
        $connection = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => false,
            'DEALER_AWARD_TEST_DATABASE' => DB::connection()->getDatabaseName(),
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        ];
        $workers = [];
        try {
            foreach ($admins as $admin) {
                $worker = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.$barrier,
                    base_path('tests/Fixtures/publish_dealer_award.php'), (string) $admin->id, (string) $award->id, $hash, $barrier,
                ], base_path(), $env, null, 25);
                $worker->start();
                $workers[] = $worker;
            }
            $outcomes = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $outcomes[] = trim($worker->getOutput());
            }
            sort($outcomes);
            $this->assertSame(['already-published', 'published'], $outcomes);
            $award->refresh();
            $this->assertNotNull($award->published_at);
            $this->assertContains($award->published_by, $admins->pluck('id')->all());
            $this->assertSame(3, $award->winners()->count());
            $this->assertSame(0, $award->winners()->where('winner_type', 'sales')->count());
            $this->assertSame($preview['dealers']->pluck('dealer_id')->all(), $award->winners()->orderBy('rank')->pluck('dealer_id')->all());
            $this->assertSame(1, DB::table('awards')->where('period_type', 'monthly')->where('period_start', '2001-02-01')->count());
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
