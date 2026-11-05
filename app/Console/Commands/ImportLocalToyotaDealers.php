<?php

namespace App\Console\Commands;

use App\Enums\LeaderboardPeriod;
use App\Services\LeaderboardService;
use App\Services\LocalToyotaDealerImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ImportLocalToyotaDealers extends Command
{
    protected $signature = 'toyota:import-local-dealers {--execute} {--evidence=} {--confirm=}';

    protected $description = 'Plan or import the approved Toyota snapshot into local toyota only, after verified backup/restore';

    public function handle(LocalToyotaDealerImport $import): int
    {
        $lock = false;
        try {
            $binding = $import->guard();
            $source = $import->source();
            $plan = $import->plan($source);
            $this->line(json_encode(['database' => $binding, 'source_hash' => LocalToyotaDealerImport::SOURCE_HASH, 'counts' => $plan['counts']], JSON_UNESCAPED_SLASHES));
            if (! $this->option('execute')) {
                return self::SUCCESS;
            }
            if ($this->option('confirm') !== 'toyota:87:'.LocalToyotaDealerImport::SOURCE_HASH) {
                throw new RuntimeException('Explicit toyota:87:approved-source-hash confirmation required.');
            }
            $privateRoot = realpath(base_path('.laravel-tools/backups/toyota-local'));
            $path = realpath((string) $this->option('evidence'));
            if (! $privateRoot || ! $path || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $privateRoot).'/')) {
                throw new RuntimeException('Evidence must be in the private local backup directory.');
            }
            $evidence = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $backup = realpath(dirname($path).'/'.$evidence['backup_file']);
            if (! $backup || dirname($backup) !== dirname($path) || hash_file('sha256', $backup) !== $evidence['backup_sha256']
                || $evidence['binding'] !== $binding || $evidence['source_hash'] !== LocalToyotaDealerImport::SOURCE_HASH
                || $evidence['restore']['status'] !== 'PASS' || $evidence['plan_sha256'] !== hash('sha256', serialize($plan))) {
                throw new RuntimeException('Backup/restore/binding/plan evidence mismatch.');
            }
            if ($import->capture($evidence['restore']['database']) !== $evidence['baseline']) {
                throw new RuntimeException('Restore clone no longer matches verified baseline.');
            }
            if ((int) DB::selectOne("SELECT GET_LOCK('toyota.local-dealer-import.v1', 0) AS acquired")->acquired !== 1) {
                throw new RuntimeException('Another importer holds the lock.');
            }
            $lock = true;
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::beginTransaction();
            // Full scans lock rows and insert gaps, including empty business tables.
            foreach (array_keys($evidence['baseline']['tables']) as $table) {
                if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) {
                    throw new RuntimeException('Invalid table identifier.');
                }
                DB::select("SELECT * FROM `$table` FOR UPDATE");
            }
            $import->guard();
            if ($import->capture() !== $evidence['baseline'] || $import->plan($source) !== $plan) {
                throw new RuntimeException('Database/schema changed since backup or plan.');
            }
            if ($plan['counts'] !== ['INSERT' => 87, 'UPDATE' => 0, 'SKIP' => 0, 'CONFLICT' => 0]) {
                throw new RuntimeException('Execution requires exactly 87 new inserts, no updates/skips/conflicts.');
            }
            $old = DB::table('dealers')->orderBy('id')->get();
            $standing = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->get()->all();
            $started = microtime(true);
            $created = $import->insertBatch($plan);
            $after = $import->capture();
            foreach ($evidence['baseline']['tables'] as $table => $baseline) {
                if ($table === 'dealers') {
                    if ($after['tables'][$table]['count'] !== $baseline['count'] + 87 || $after['tables'][$table]['schema_sha256'] !== $baseline['schema_sha256']) {
                        throw new RuntimeException('Unexpected Dealer count/schema after insert.');
                    }
                } elseif ($after['tables'][$table] !== $baseline) {
                    throw new RuntimeException('Business table changed: '.$table);
                }
            }
            if ($after['objects'] !== $evidence['baseline']['objects']
                || serialize(DB::table('dealers')->whereIn('id', $old->pluck('id'))->orderBy('id')->get()->all()) !== serialize($old->all())
                || serialize(app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->get()->all()) !== serialize($standing)) {
                throw new RuntimeException('Existing Dealer/ranking/objects changed.');
            }
            $receipt = ['status' => 'READY_TO_COMMIT', 'source_hash' => LocalToyotaDealerImport::SOURCE_HASH, 'backup_path' => $backup,
                'backup_sha256' => $evidence['backup_sha256'], 'restore' => $evidence['restore'], 'binding' => $binding,
                'imported_at' => date(DATE_ATOM), 'counts' => $plan['counts'], 'created' => $created, 'before' => $evidence['baseline'],
                'after' => $after, 'duration_seconds' => microtime(true) - $started];
            $pending = dirname($path).'/import-receipt-pending-'.bin2hex(random_bytes(6)).'.json';
            if (file_put_contents($pending, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new RuntimeException('Cannot write precommit receipt.');
            }
            DB::commit();
            $receipt['status'] = 'COMMITTED';
            $final = dirname($pending).'/import-receipt-'.bin2hex(random_bytes(6)).'.json';
            if (file_put_contents($final, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new RuntimeException('Commit succeeded; final receipt write failed. Preserve pending evidence and review; do not rerun execute.');
            }
            $this->info('PASS committed 87 INSERT; 0 UPDATE/DELETE. Receipt: '.$final);

            return self::SUCCESS;
        } catch (Throwable $error) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error($error->getMessage());

            return self::FAILURE;
        } finally {
            if ($lock) {
                DB::selectOne("SELECT RELEASE_LOCK('toyota.local-dealer-import.v1')");
            }
        }
    }
}
