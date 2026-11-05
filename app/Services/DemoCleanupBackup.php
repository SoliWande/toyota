<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Process\Process;

class DemoCleanupBackup
{
    private function root(): string
    {
        $private = realpath(storage_path('app/private'));
        $public = realpath(public_path());
        if ($private && $public && ($private === $public || str_starts_with($private, $public.DIRECTORY_SEPARATOR))) {
            throw new LogicException('Backup storage resolves inside the public web root.');
        }

        return storage_path('app/private/demo-cleanup');
    }

    /** Full database export; no password is placed in command arguments or reports. */
    protected function dump(string $directory, string $target): void
    {
        $db = DB::connection();
        $quote = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
        $credentials = $directory.'/client.cnf';
        $text = "[client]\n";
        foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $key) {
            if (($value = $db->getConfig($key)) !== null && $value !== '') {
                $option = ['username' => 'user', 'unix_socket' => 'socket'][$key] ?? $key;
                $text .= $option.'='.$quote($value)."\n";
            }
        }
        $handle = fopen($credentials, 'x');
        if ($handle === false) {
            throw new LogicException('Cannot create private backup credentials.');
        }
        chmod($credentials, 0600);
        try {
            $written = fwrite($handle, $text);
            fclose($handle);
            if ($written !== strlen($text) || str_starts_with($db->getDatabaseName(), '-')) {
                throw new LogicException('Cannot safely configure the database backup.');
            }
            $process = new Process(['mysqldump', '--defaults-extra-file='.$credentials, '--single-transaction', '--quick',
                '--skip-lock-tables', '--routines', '--triggers', '--events', '--hex-blob', '--no-tablespaces',
                '--result-file='.$target, $db->getDatabaseName()], null, null, null, 300);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new LogicException('mysqldump failed; no deletion permitted. Check private operator credentials/tool privileges.');
            }
        } finally {
            // Only this newly created credential file is removed, never uploads/storage data.
            if (is_file($credentials)) {
                unlink($credentials);
            }
        }
    }

    public function create(array $plan): array
    {
        if (! isset($plan['plan_hash']) || ! $plan['eligible']) {
            throw new LogicException('Audit blocked; use an operator-managed database backup for review first.');
        }
        $directory = $this->root().'/'.gmdate('Ymd-His').'-'.Str::uuid();
        if (! mkdir($directory, 0700, true)) {
            throw new LogicException('Cannot create private backup directory.');
        }
        $target = $directory.'/database.sql';
        $this->dump($directory, $target);
        if (! is_file($target) || filesize($target) === 0) {
            throw new LogicException('Backup missing/empty; no deletion permitted.');
        }
        chmod($target, 0600);
        $current = app(DemoCleanup::class)->audit();
        if (! hash_equals($plan['plan_hash'], $current['plan_hash'] ?? '')) {
            throw new LogicException('Database changed during backup. Keep the dump for review; create a new dry-run/backup.');
        }
        $payload = ['binding' => $plan['binding'], 'plan_hash' => $plan['plan_hash'], 'eligible' => $plan['eligible'],
            'path' => $target, 'sha256' => hash_file('sha256', $target), 'bytes' => filesize($target)];
        $receipt = $directory.'/receipt.enc';
        file_put_contents($receipt, Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), LOCK_EX);
        chmod($receipt, 0600);

        return ['receipt' => $receipt, 'sha256' => $payload['sha256'], 'bytes' => $payload['bytes'],
            'restore_test_required' => true, 'database_deleted' => false];
    }

    public function validate(string $receipt, array $plan, string $restoreVerified): void
    {
        $this->assertPrivateFile($receipt);
        try {
            $payload = json_decode(Crypt::decryptString(file_get_contents($receipt)), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new LogicException('Backup receipt cannot be authenticated.');
        }
        if (($payload['eligible'] ?? false) !== true || ($payload['binding'] ?? '') !== $plan['binding']
            || ($payload['plan_hash'] ?? '') !== $plan['plan_hash']) {
            throw new LogicException('Backup belongs to another database/plan or a blocked audit.');
        }
        $target = $payload['path'] ?? '';
        $this->assertPrivateFile($target);
        if (! is_string($payload['sha256'] ?? null) || ! hash_equals($payload['sha256'], hash_file('sha256', $target))
            || filesize($target) !== $payload['bytes'] || ! hash_equals($payload['sha256'], $restoreVerified)) {
            throw new LogicException('Backup hash/size or explicit restore verification mismatch.');
        }
    }

    private function assertPrivateFile(string $file): void
    {
        $root = realpath($this->root());
        $resolved = realpath($file);
        if (! $root || ! $resolved || ! is_file($resolved) || is_link($file)
            || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            throw new LogicException('Backup/receipt must be an existing file in private demo-cleanup storage.');
        }
    }
}
