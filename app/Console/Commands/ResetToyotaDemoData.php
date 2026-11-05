<?php

namespace App\Console\Commands;

use App\Services\ResetDemoBackup;
use App\Services\ResetDemoData;
use Illuminate\Console\Command;
use LogicException;

class ResetToyotaDemoData extends Command
{
    protected $signature = 'toyota:reset-demo-data
        {--database= : Exact configured primary database; never switches the connection}
        {--keep-admin=* : Operator-approved real Admin ID, repeat for every Admin to keep}
        {--dry-run : Read-only plan, also the default}
        {--backup : Full private SQL backup and authenticated reset receipt, no deletion}
        {--execute : Explicit pre-go-live business reset}
        {--plan= : Exact hash of the approved dry-run plan}
        {--receipt= : Private reset backup receipt}
        {--restore-verified= : Backup SHA256 acknowledged after an isolated restore test}
        {--confirm= : Exact reset-demo-data:<database> confirmation}
        {--allow-production : Separate production execution acknowledgement}';

    protected $description = 'Plan a pre-go-live business reset; preserve 87 Dealers and approved Admin/Dealer accounts. Defaults to dry-run.';

    public function handle(ResetDemoData $reset, ResetDemoBackup $backup): int
    {
        try {
            if (count(array_filter([$this->option('dry-run'), $this->option('backup'), $this->option('execute')])) > 1) {
                throw new LogicException('Choose only dry-run, backup or execute.');
            }
            $database = (string) $this->option('database');
            $admins = $this->option('keep-admin');
            foreach ($admins as $id) {
                if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1 || (string) (int) $id !== $id) {
                    throw new LogicException('Every --keep-admin must be a positive integer ID.');
                }
            }
            $admins = array_map('intval', $admins);
            if ($this->option('execute')) {
                if ($database === '' || $this->option('confirm') !== 'reset-demo-data:'.$database
                    || ! $this->option('plan') || ! $this->option('receipt') || ! $this->option('restore-verified')) {
                    throw new LogicException('Execution requires database, exact confirmation, plan, reset backup receipt and restore acknowledgement.');
                }
                if (app()->environment('production') && (! $this->option('allow-production') || ! app()->isDownForMaintenance())) {
                    throw new LogicException('Production reset requires --allow-production and maintenance mode; stop all other writers.');
                }
                $result = $reset->execute($database, $admins, $this->option('plan'), $this->option('receipt'), $this->option('restore-verified'));
            } else {
                $plan = $reset->audit($database, $admins);
                if ($this->option('backup')) {
                    $result = $backup->create($plan);
                } else {
                    // Internal token/reset selectors can contain email addresses.
                    // Public audit output needs only counts and approved account IDs.
                    unset($plan['delete'], $plan['user_delete_order']);
                    $result = ['mode' => 'dry-run', ...$plan];
                }
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return ($result['eligible'] ?? true) ? self::SUCCESS : self::FAILURE;
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Reset aborted; inspect private logs and actual transaction outcome before retrying. No automatic repair.');

            return self::FAILURE;
        }
    }
}
