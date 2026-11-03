<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneDealerRegistrationReceipts extends Command
{
    protected $signature = 'dealer-registration:prune-receipts {--dry-run} {--batch=500} {--max-batches=20}';

    protected $description = 'Remove only expired registration receipts; preserve submissions and review history';

    public function handle(): int
    {
        foreach (['batch' => 1000, 'max-batches' => 100] as $option => $maximum) {
            if (! ctype_digit((string) $this->option($option)) || (int) $this->option($option) < 1 || (int) $this->option($option) > $maximum) {
                $this->error("Invalid --{$option}; allowed range: 1–{$maximum}.");

                return self::INVALID;
            }
        }
        $cutoff = now();
        $batch = (int) $this->option('batch');
        $count = 0;
        $cursor = null;
        for ($i = 0; $i < (int) $this->option('max-batches'); $i++) {
            $query = DB::table('dealer_registration_receipts')->where('expires_at', '<=', $cutoff);
            if ($cursor !== null) {
                $query->where('draft_key', '>', $cursor);
            }
            $keys = $query->orderBy('draft_key')->limit($batch)->pluck('draft_key');
            if ($keys->isEmpty()) {
                break;
            }
            $cursor = $keys->last();
            $count += $this->option('dry-run') ? $keys->count()
                : DB::table('dealer_registration_receipts')->whereIn('draft_key', $keys)->where('expires_at', '<=', $cutoff)->delete();
        }
        $this->info(($this->option('dry-run') ? 'Eligible' : 'Deleted')." expired receipts: {$count}");
        Log::info('Registration receipt cleanup.', ['count' => $count, 'dry_run' => (bool) $this->option('dry-run')]);

        return self::SUCCESS;
    }
}
