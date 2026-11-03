<?php

namespace App\Console\Commands;

use App\Services\SubmissionCollisionAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditSubmissionCollisions extends Command
{
    protected $signature = 'submissions:audit-collisions {--confirm-database= : Exact configured database name} {--json}';

    protected $description = 'Read-only phone/plate collision counts; never output customer identities';

    public function handle(SubmissionCollisionAudit $audit): int
    {
        $connection = DB::connection();
        if (! is_string($this->option('confirm-database')) || $this->option('confirm-database') === ''
            || $this->option('confirm-database') !== $connection->getDatabaseName() || $connection->getDriverName() !== 'mysql') {
            $this->error('Confirm the configured MySQL database before running this read-only audit.');

            return self::INVALID;
        }
        if ($connection->selectOne('SELECT DATABASE() AS name')->name !== $this->option('confirm-database')) {
            $this->error('Actual database does not match confirmation.');

            return self::FAILURE;
        }
        if ($connection->transactionLevel() === 0) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        }
        $counts = $connection->transaction(fn () => $audit->scan($connection));
        if ($this->option('json')) {
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Metric', 'Count'], array_map(fn ($key, $count) => [$key, $count], array_keys($counts), array_values($counts)));
        }

        return self::SUCCESS;
    }
}
