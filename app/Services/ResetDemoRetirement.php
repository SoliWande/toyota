<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use LogicException;

class ResetDemoRetirement
{
    public function path(): string
    {
        return storage_path('app/private/demo-reset-retirement/'.app(DemoCleanup::class)->binding().'.enc');
    }

    public function isPresent(): bool
    {
        return is_file($this->path());
    }

    public function assertValid(): void
    {
        try {
            $payload = json_decode(Crypt::decryptString(file_get_contents($this->path())), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new LogicException('Pre-go-live reset retirement receipt cannot be authenticated; operator review required.');
        }
        if (($payload['purpose'] ?? null) !== 'pre-go-live-business-reset' || ($payload['version'] ?? null) !== 1
            || ($payload['retired'] ?? null) !== true || ($payload['binding'] ?? null) !== app(DemoCleanup::class)->binding()) {
            throw new LogicException('Pre-go-live reset receipt belongs to another database/purpose.');
        }
    }

    /** Registered with DB::afterCommit; never called on transaction rollback. */
    public function seal(array $plan, array $preserved): void
    {
        if (($plan['purpose'] ?? null) !== 'pre-go-live-business-reset'
            || $preserved !== app(DemoCleanup::class)->snapshots() || ($preserved['dealers']['total'] ?? null) !== 87) {
            throw new LogicException('Reset SQL committed but post-commit verification failed; keep maintenance and inspect outcome.');
        }
        foreach (ResetDemoData::BUSINESS as $table) {
            if (($preserved[$table]['total'] ?? null) !== 0) {
                throw new LogicException('Reset SQL committed but business data is not empty; keep maintenance.');
            }
        }
        $path = $this->path();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new LogicException('Reset SQL committed but retirement directory cannot be created.');
        }
        $resolved = strtolower(str_replace('\\', '/', realpath($directory))).'/';
        $public = strtolower(str_replace('\\', '/', realpath(public_path()))).'/';
        if (str_starts_with($resolved, $public)) {
            throw new LogicException('Reset retirement receipt must be private.');
        }
        $payload = ['purpose' => $plan['purpose'], 'version' => 1, 'retired' => true, 'binding' => $plan['binding'],
            'completed_plan' => $plan['plan_hash'], 'kept_admin_ids' => $plan['approved_admin_ids'],
            'kept_dealer_accounts' => $plan['kept_dealer_accounts'], 'dealer_hash' => $preserved['dealers']['sha256'],
            'created_at' => now()->toIso8601String()];
        $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $temporary = $path.'.'.bin2hex(random_bytes(16)).'.tmp';
        $handle = fopen($temporary, 'x');
        if ($handle === false) {
            throw new LogicException('Reset SQL committed but receipt cannot be created.');
        }
        try {
            chmod($temporary, 0600);
            if (fwrite($handle, $encrypted) !== strlen($encrypted)) {
                throw new LogicException('Reset SQL committed but receipt write incomplete.');
            }
        } finally {
            fclose($handle);
        }
        if (! rename($temporary, $path)) {
            throw new LogicException('Reset SQL committed but receipt cannot be installed.');
        }
    }
}
