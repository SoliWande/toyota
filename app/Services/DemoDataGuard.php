<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** Detection is a review alarm, never ownership proof or a deletion predicate. */
class DemoDataGuard
{
    public function queries(): array
    {
        $users = DB::table('users')->where(fn ($q) => $q
            ->where('email', 'like', 'toyota.realistic.v1.%@example.com')
            ->orWhere('email', 'like', 'demo.sales.%@example.test'));
        $submissions = DB::table('customer_submissions')->where(fn ($q) => $q
            ->where('notes', ToyotaRealisticDemo::TAG)->orWhere('license_plate', 'like', 'DEMO-V1-%')
            ->orWhere('facebook_url_normalized', 'like', 'https://www.facebook.com/toyota.demo.synthetic.v1.%'));
        $awards = DB::table('awards')->where('title', 'like', '[DEMO]%');

        return ['users' => $users, 'customer_submissions' => $submissions, 'awards' => $awards];
    }

    public function candidates(): array
    {
        ['users' => $users, 'customer_submissions' => $submissions, 'awards' => $awards] = $this->queries();
        $row = DB::query()->selectSub($users->selectRaw('COUNT(*)'), 'users')
            ->selectSub($submissions->selectRaw('COUNT(*)'), 'customer_submissions')
            ->selectSub($awards->selectRaw('COUNT(*)'), 'awards')->first();

        return array_map('intval', (array) $row);
    }

    public function assertRetired(): void
    {
        if (config('realistic_demo.enabled') || config('realistic_demo.allow_production') || config('realistic_demo.include_in_metrics')) {
            throw new LogicException('Retired production cannot enable demo installation or demo metrics.');
        }
        if (app(ResetDemoRetirement::class)->isPresent()) {
            app(ResetDemoRetirement::class)->assertValid();

            return;
        }
        try {
            $path = $this->receiptPath();
            $receipt = is_file($path) ? json_decode(Crypt::decryptString(file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR) : null;
            $valid = is_array($receipt) && ($receipt['version'] ?? null) === 1
                && ($receipt['retired'] ?? null) === true
                && hash_equals(app(DemoCleanup::class)->binding(), $receipt['binding'] ?? '');
        } catch (Throwable $exception) {
            throw new LogicException('Retirement receipt cannot be authenticated; operator review required.', 0, $exception);
        }
        // Publicly entered strings are not provenance. A marker on a NEW
        // submission must not let an anonymous visitor disable the whole site.
        if (! $valid || DB::table('users')->where('email', ToyotaRealisticDemo::EMAIL)->where('role', 'admin')->exists()) {
            throw new LogicException('Verified cleanup retirement receipt required; operator review required.');
        }
    }

    public function receiptPath(): string
    {
        return storage_path('app/private/demo-retirement/'.app(DemoCleanup::class)->binding().'.enc');
    }

    /** Called only after a successful cleanup commit, including a verified no-op. */
    public function seal(string $completedPlan): string
    {
        $plan = app(DemoCleanup::class)->audit();
        if (! $plan['eligible'] || array_sum(array_map('count', $plan['ids'])) !== 0 || array_sum($plan['candidates']) !== 0) {
            throw new LogicException('Cleanup committed but retirement verification failed; keep maintenance and review.');
        }
        $path = $this->receiptPath();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new LogicException('Cleanup committed but private retirement directory cannot be created.');
        }
        $resolved = str_replace('\\', '/', realpath($directory));
        $public = str_replace('\\', '/', realpath(public_path()));
        if ($resolved === $public || str_starts_with(strtolower($resolved).'/', strtolower($public).'/')) {
            throw new LogicException('Retirement receipt must be outside the public web root.');
        }
        $encrypted = Crypt::encryptString(json_encode(['version' => 1, 'retired' => true,
            'binding' => $plan['binding'], 'completed_plan' => $completedPlan, 'created_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));
        $temporary = $path.'.'.bin2hex(random_bytes(16)).'.tmp';
        $handle = fopen($temporary, 'x');
        if ($handle === false) {
            throw new LogicException('Cleanup committed but retirement receipt cannot be written.');
        }
        try {
            chmod($temporary, 0600);
            if (fwrite($handle, $encrypted) !== strlen($encrypted)) {
                throw new LogicException('Cleanup committed but retirement receipt write was incomplete.');
            }
        } finally {
            fclose($handle);
        }
        if (! rename($temporary, $path)) {
            throw new LogicException('Cleanup committed but retirement receipt cannot be installed.');
        }

        return $path;
    }
}
