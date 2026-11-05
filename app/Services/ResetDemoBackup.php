<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use LogicException;

/** Reuses only the SQL export transport, not cleanup's ownership policy. */
class ResetDemoBackup extends DemoCleanupBackup
{
    private function root(): string
    {
        $path = storage_path('app/private/demo-reset');
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new LogicException('Cannot create private reset backup directory.');
        }
        $resolved = strtolower(str_replace('\\', '/', realpath($path))).'/';
        $public = strtolower(str_replace('\\', '/', realpath(public_path()))).'/';
        if (str_starts_with($resolved, $public)) {
            throw new LogicException('Reset backup must be outside the public web root.');
        }

        return $path;
    }

    public function create(array $plan): array
    {
        if (! ($plan['eligible'] ?? false) || ($plan['purpose'] ?? null) !== 'pre-go-live-business-reset') {
            throw new LogicException('Reset plan must be eligible before backup.');
        }
        $directory = $this->root().'/'.gmdate('Ymd-His').'-'.Str::uuid();
        if (! mkdir($directory, 0700)) {
            throw new LogicException('Cannot create reset backup directory.');
        }
        $target = $directory.'/database.sql';
        $this->dump($directory, $target);
        if (! is_file($target) || filesize($target) === 0) {
            throw new LogicException('Full backup is missing/empty.');
        }
        chmod($target, 0600);
        $current = app(ResetDemoData::class)->audit($plan['database'], $plan['approved_admin_ids']);
        if (! hash_equals($plan['plan_hash'], $current['plan_hash'] ?? '')) {
            throw new LogicException('Database/schema changed during backup; create a new plan and backup.');
        }
        $payload = ['purpose' => $plan['purpose'], 'binding' => $plan['binding'], 'plan_hash' => $plan['plan_hash'],
            'path' => $target, 'sha256' => hash_file('sha256', $target), 'bytes' => filesize($target)];
        $receipt = $directory.'/receipt.enc';
        $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        if (file_put_contents($receipt, $encrypted, LOCK_EX) !== strlen($encrypted)) {
            throw new LogicException('Cannot write reset backup receipt.');
        }
        chmod($receipt, 0600);

        return ['receipt' => $receipt, 'sha256' => $payload['sha256'], 'bytes' => $payload['bytes'],
            'plan_hash' => $plan['plan_hash'], 'restore_test_required' => true, 'database_deleted' => false];
    }

    private function privateFile(string $file): void
    {
        $root = realpath($this->root());
        $resolved = realpath($file);
        if (! $resolved || ! is_file($resolved) || is_link($file) || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            throw new LogicException('Reset backup/receipt must be an existing private reset file.');
        }
    }

    public function validate(string $receipt, array $plan, string $restoreVerified): void
    {
        $this->privateFile($receipt);
        try {
            $payload = json_decode(Crypt::decryptString(file_get_contents($receipt)), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new LogicException('Reset backup receipt cannot be authenticated.');
        }
        if (($payload['purpose'] ?? null) !== 'pre-go-live-business-reset' || ($payload['binding'] ?? null) !== $plan['binding']
            || ($payload['plan_hash'] ?? null) !== $plan['plan_hash']) {
            throw new LogicException('Reset backup belongs to another plan/database/purpose.');
        }
        $target = $payload['path'] ?? '';
        $this->privateFile($target);
        if (! is_string($payload['sha256'] ?? null) || ! hash_equals($payload['sha256'], hash_file('sha256', $target))
            || ($payload['bytes'] ?? null) !== filesize($target) || ! hash_equals($payload['sha256'], $restoreVerified)) {
            throw new LogicException('Reset backup hash/size or isolated restore acknowledgement mismatch.');
        }
    }
}
