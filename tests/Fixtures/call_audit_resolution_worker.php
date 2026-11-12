<?php

require __DIR__.'/../../vendor/autoload.php';

use App\Actions\CallAudit\ResolveFinding;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FreezeClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\CallAuditFreezeFixtures;

[$script, $actorId, $batchId, $itemId, $tag, $directory, $role] = $argv;
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
CallAuditFreezeFixtures::assertLocal();
$root = str_replace('\\', '/', storage_path('app/private/p09o-resolution-races/'));
if (! in_array($role, ['holder', 'contender'], true) || ! preg_match('/^p09h-[a-f0-9]{20}$/D', $tag)
    || ! is_dir($directory) || ! str_starts_with(str_replace('\\', '/', realpath($directory)), $root)) {
    throw new RuntimeException('Unsafe resolution race arguments.');
}
$actor = User::whereKey($actorId)->where('name', $tag)->firstOrFail();
$batch = CallAuditBatch::whereKey($batchId)->where('create_request_key', $tag.'-general')->firstOrFail();
if (! $batch->items()->whereKey($itemId)->exists()) {
    throw new RuntimeException('Race item is not owned by the fixture.');
}
Auth::setUser($actor);
// Same isolated test clock as the parent, never alter the database/system timezone or time.
$app->instance(FreezeClock::class, new class extends FreezeClock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-10 12:01:00', 'UTC');
    }
});
$wait = static function (callable $ready): void {
    $deadline = microtime(true) + 10;
    while (! $ready()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Resolution race synchronization timed out.');
        }
        usleep(10000);
    }
};
$waitMs = 0;
$held = false;
touch($directory.'/'.$role.'.ready');
$wait(fn () => is_file($directory.'/holder.ready') && is_file($directory.'/contender.ready'));
if ($role === 'holder') {
    DB::listen(function ($event) use ($directory, $wait, &$held): void {
        if (! $held && str_contains($event->sql, '`call_audit_batches`') && str_contains($event->sql, 'for update')) {
            $held = true;
            touch($directory.'/holder.locked');
            $wait(fn () => is_file($directory.'/contender.attempting'));
            usleep(400000);
        }
    });
} else {
    $wait(fn () => is_file($directory.'/holder.locked'));
    DB::connection()->beforeExecuting(function (string $sql) use ($directory): void {
        if (str_contains($sql, '`call_audit_batches`') && str_contains($sql, 'for update')) {
            touch($directory.'/contender.attempting');
        }
    });
    DB::listen(function ($event) use (&$waitMs): void {
        if (str_contains($event->sql, '`call_audit_batches`') && str_contains($event->sql, 'for update')) {
            $waitMs = max($waitMs, $event->time);
        }
    });
}
$connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
try {
    $item = $batch->items()->whereKey($itemId)->firstOrFail();
    $result = app(ResolveFinding::class)->execute((int) $batchId, (int) $itemId, [
        'purpose' => 'general_audit', 'month' => '2026-10', 'attempt_id' => $item->attempts()->sole()->id,
        'row_version' => 3, 'resolution_version' => 0, 'idempotency_key' => 'race-resolution',
        'decision' => 'confirmed', 'reason' => 'Isolated concurrency resolution reason',
    ]);
    echo json_encode(['status' => 'RESOLVED', 'response' => $result, 'wait_ms' => $waitMs, 'connection_id' => $connectionId]);
} catch (CallCommandRejected $rejected) {
    echo json_encode(['status' => 'CONFLICT', 'code' => $rejected->reason, 'wait_ms' => $waitMs, 'connection_id' => $connectionId]);
} catch (Throwable) {
    echo json_encode(['status' => 'UNAVAILABLE']);
    exit(1);
}
