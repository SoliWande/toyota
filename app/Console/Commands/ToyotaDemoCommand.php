<?php

namespace App\Console\Commands;

use App\Services\ToyotaRealisticDemo;
use Database\Seeders\ToyotaRealisticDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ToyotaDemoCommand extends Command
{
    protected $signature = 'toyota:demo {action=status : status, seed or cleanup}';

    protected $description = 'Inspect or explicitly create/remove the owned Toyota realistic demo dataset';

    public function handle(): int
    {
        $demo = app(ToyotaRealisticDemo::class);
        $action = $this->argument('action');
        if ($action === 'status') {
            $this->line(json_encode($demo->status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        if (! in_array($action, ['seed', 'cleanup'], true)) {
            $this->error('Expected status, seed or cleanup.');

            return self::FAILURE;
        }
        $demo->assertEnabled();
        $database = DB::connection()->getDatabaseName();
        $this->warn("Database: {$database}; environment: ".app()->environment());
        $this->warn($action === 'seed' ? 'Creates synthetic accounts/customers; affects live rankings.' : 'Deletes only manifest-owned demo records; external references cause rollback. Back up first.');
        if (! $this->input->isInteractive() || $this->ask("Type {$action}:{$database} to confirm") !== "{$action}:{$database}") {
            $this->error('Explicit interactive confirmation required.');

            return self::FAILURE;
        }
        $demo->confirmed = true;
        try {
            if ($action === 'seed') {
                app(ToyotaRealisticDemoSeeder::class)->run();
            } else {
                $demo->cleanup();
            }
        } finally {
            $demo->confirmed = false;
        }
        $this->info('Completed.');

        return self::SUCCESS;
    }
}
