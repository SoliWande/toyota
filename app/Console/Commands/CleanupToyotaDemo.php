<?php

namespace App\Console\Commands;

use App\Services\DemoCleanup;
use App\Services\DemoCleanupBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

class CleanupToyotaDemo extends Command
{
    protected $signature = 'toyota:cleanup-demo
        {--dry-run : Read-only audit (also the default)}
        {--backup : Create a full private dump and authenticated receipt; never delete}
        {--execute : Delete only verified manifest records in one transaction}
        {--plan= : Exact plan_hash from dry-run}
        {--receipt= : Authenticated backup receipt path}
        {--restore-verified= : Backup SHA256 explicitly acknowledged after isolated restore testing}
        {--confirm= : Exact cleanup:<database> confirmation}
        {--allow-production : Additional production-only execution acknowledgement}';

    protected $description = 'Audit or safely remove verified Toyota demo records; defaults to dry-run, never deletes Dealer or storage files';

    public function handle(DemoCleanup $cleanup, DemoCleanupBackup $backup): int
    {
        try {
            if (count(array_filter([$this->option('dry-run'), $this->option('backup'), $this->option('execute')])) > 1) {
                throw new LogicException('Choose only dry-run, backup or execute.');
            }
            if ($this->option('execute')) {
                $database = DB::connection()->getDatabaseName();
                if ($this->option('confirm') !== 'cleanup:'.$database || ! $this->option('plan')
                    || ! $this->option('receipt') || ! $this->option('restore-verified')) {
                    throw new LogicException('Execution requires exact database confirmation, plan, backup receipt and restore acknowledgement.');
                }
                if (app()->environment('production') && (! $this->option('allow-production') || ! app()->isDownForMaintenance())) {
                    throw new LogicException('Production execution requires separate approval, --allow-production and maintenance mode; stop queue/other writers first.');
                }
                $this->line(json_encode($cleanup->execute($this->option('plan'), $this->option('receipt'), $this->option('restore-verified')), JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $plan = $cleanup->audit();
            if ($this->option('backup')) {
                $this->line(json_encode($backup->create($plan), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }
            unset($plan['ids']); // Never print the manifest, secrets, encrypted payloads or customer data.
            $plan['mode'] = 'dry-run';
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $plan['eligible'] ? self::SUCCESS : self::FAILURE;
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Cleanup aborted. No automatic repair or retry; inspect private operator logs and transaction outcome.');

            return self::FAILURE;
        }
    }
}
