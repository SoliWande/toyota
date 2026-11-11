<?php

require __DIR__.'/../../vendor/autoload.php';

use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\ResetDemoRetirement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\CallAuditFreezeFixtures;

[$script, $adminId, $key, $directory, $role, $mode, $dealerId] = $argv;
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
CallAuditFreezeFixtures::assertLocal();
if (! in_array($role, ['holder', 'contender'], true) || ! in_array($mode, ['freeze', 'late'], true)
    || ! preg_match('/^p09h-[a-f0-9]{20}-race-[ab]$/D', $key)
    || ! is_dir($directory) || ! str_starts_with(str_replace('\\', '/', realpath($directory)), str_replace('\\', '/', storage_path('app/private/p09h-races/')))) {
    throw new RuntimeException('Unsafe race worker arguments.');
}
config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
    'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
$app->instance(ResetDemoRetirement::class, new class extends ResetDemoRetirement
{
    public function isPresent(): bool
    {
        return false;
    }
});
Auth::setUser(User::findOrFail($adminId));
$wait = static function (callable $ready): void {
    $deadline = microtime(true) + 12;
    while (! $ready()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race synchronization timed out.');
        }
        usleep(10000);
    }
};
touch($directory.'/'.$role.'.ready');
$wait(fn () => is_file($directory.'/holder.ready') && is_file($directory.'/contender.ready'));
$holdOnce = false;
$waitMs = 0;
if ($role === 'holder') {
    DB::listen(function ($event) use ($directory, $wait, &$holdOnce): void {
        if (! $holdOnce && str_contains($event->sql, 'submission_intake_mutex') && str_contains($event->sql, 'for update')) {
            $holdOnce = true;
            touch($directory.'/holder.locked');
            $wait(fn () => is_file($directory.'/contender.attempting'));
            usleep(400000);
        }
    });
} else {
    $wait(fn () => is_file($directory.'/holder.locked'));
    DB::connection()->beforeExecuting(function (string $sql) use ($directory): void {
        if (str_contains($sql, 'submission_intake_mutex') && str_contains($sql, 'for update')) {
            touch($directory.'/contender.attempting');
        }
    });
    DB::listen(function ($event) use (&$waitMs): void {
        if (str_contains($event->sql, 'submission_intake_mutex') && str_contains($event->sql, 'for update')) {
            $waitMs = max($waitMs, $event->time);
        }
    });
}
if ($role === 'contender' && $mode === 'late') {
    $tag = substr($key, 0, -7);
    $source = CustomerSubmission::factory()->create([
        'customer_name' => $tag, 'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => (int) $dealerId,
        'submitted_at' => '2026-10-31 23:59:59', 'phone' => '0923456789', 'phone_normalized' => '0923456789',
        'license_plate' => '51K-99999', 'vehicle_description' => 'Toyota fixture', 'address_line' => 'Isolated test',
        'province_code' => '79', 'ward_code' => '26734', 'consented_at' => '2026-10-31', 'consent_version' => 'p09h-test-only',
    ]);
    echo json_encode(['status' => 'LATE_PENDING', 'submission_id' => $source->id, 'wait_ms' => $waitMs]);
} else {
    $result = $app->make(CreateAndFreezeBatch::class)->execute('general_audit', CarbonImmutable::parse('2026-10-15'), $key);
    echo json_encode($result->summary + ['wait_ms' => $waitMs]);
}
