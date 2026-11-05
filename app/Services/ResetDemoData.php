<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Explicit pre-go-live reset, never demo ownership inference. */
class ResetDemoData
{
    // Children precede parents. A changed schema must pass the FK order audit.
    public const BUSINESS = ['submission_verification_audits', 'submission_verification_states',
        'dealer_registration_receipts', 'submission_edits', 'moderation_reviews', 'award_winners',
        'awards', 'customer_submissions'];

    private function tables(): array
    {
        // Reuse read-only primary/driver validation, never the ownership audit.
        app(DemoCleanup::class)->snapshots();

        return collect(DB::select("SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"))
            ->mapWithKeys(fn ($row) => [$row->name => $row->engine])->all();
    }

    public function foreignKeys(): array
    {
        return DB::select('SELECT TABLE_SCHEMA AS source_schema, TABLE_NAME AS source_table, COLUMN_NAME AS source_column,
            REFERENCED_TABLE_SCHEMA AS target_schema, REFERENCED_TABLE_NAME AS target_table, REFERENCED_COLUMN_NAME AS target_column,
            CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME IS NOT NULL AND (TABLE_SCHEMA = DATABASE() OR REFERENCED_TABLE_SCHEMA = DATABASE())
            ORDER BY TABLE_SCHEMA, TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
    }

    private function orphans(array $foreign): array
    {
        $invalid = [];
        foreach ($foreign as $fk) {
            if ($fk->source_schema !== DB::connection()->getDatabaseName() || $fk->target_schema !== $fk->source_schema) {
                continue;
            }
            if (DB::table($fk->source_table.' as child')->leftJoin($fk->target_table.' as parent',
                'child.'.$fk->source_column, '=', 'parent.'.$fk->target_column)
                ->whereNotNull('child.'.$fk->source_column)->whereNull('parent.'.$fk->target_column)->exists()) {
                $invalid[] = $fk->source_table.'.'.$fk->name;
            }
        }

        return array_values(array_unique($invalid));
    }

    private function userOrder(array $removed, array &$blockers): array
    {
        $remaining = DB::table('users')->whereIn('id', $removed)->pluck('reviewed_by', 'id')->all();
        $order = [];
        while ($remaining) {
            $referenced = array_filter(array_values($remaining), fn ($id) => $id !== null);
            $leaves = array_values(array_diff(array_keys($remaining), $referenced));
            if ($leaves === []) {
                $blockers[] = 'Cyclic account review relationships require operator review.';
                break;
            }
            $order[] = array_map('intval', $leaves);
            $remaining = array_diff_key($remaining, array_flip($leaves));
        }

        return $order;
    }

    public function audit(string $database, array $admins): array
    {
        if ($database === '' || $database !== DB::connection()->getDatabaseName()
            || DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
            throw new LogicException('Explicit --database must match the configured and actual primary database.');
        }
        $engines = $this->tables();
        $blockers = [];
        if (app(ResetDemoRetirement::class)->isPresent()) {
            $blockers[] = 'A pre-go-live reset receipt already exists; future business data must not be reset under this approval.';
        }
        foreach ([...self::BUSINESS, 'users', 'dealers', 'migrations', 'submission_intake_mutex'] as $table) {
            if (! isset($engines[$table])) {
                $blockers[] = 'Missing required table: '.$table;
            }
        }
        if (array_filter($engines, fn ($engine) => $engine !== 'InnoDB')) {
            $blockers[] = 'Every table must be transactional InnoDB.';
        }
        if (! isset($engines['users'], $engines['dealers'])) {
            return ['eligible' => false, 'blockers' => $blockers];
        }
        $admins = array_values(array_unique($admins));
        sort($admins, SORT_NUMERIC);
        $users = DB::table('users')->orderBy('id')->get();
        $adminRows = $users->where('role', UserRole::Admin->value);
        $dealers = DB::table('dealers')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $dealerAccounts = $users->where('role', UserRole::Dealer->value);
        if (count($dealers) !== 87) {
            $blockers[] = 'Exactly 87 Dealer records are required; no count override is available.';
        }
        if ($admins === [] || array_diff($admins, $adminRows->pluck('id')->all())) {
            $blockers[] = 'An explicit operator-approved Admin ID allowlist is required; every ID must be an existing Admin.';
        }
        if (! $adminRows->whereIn('id', $admins)->contains('status', UserStatus::Active->value)) {
            $blockers[] = 'At least one approved Admin must remain active.';
        }
        foreach ($dealerAccounts as $account) {
            if (! in_array((int) $account->dealer_id, $dealers, true) || (int) $account->dealer_manager_key !== (int) $account->dealer_id
                || ! in_array($account->status, array_column(UserStatus::cases(), 'value'), true)) {
                $blockers[] = 'Invalid Dealer account relation/status: '.$account->id.'; preserve and review, never auto-delete.';
            }
        }
        if ($users->whereNotIn('role', array_column(UserRole::cases(), 'value'))->isNotEmpty()) {
            $blockers[] = 'Unknown account role requires review.';
        }
        $keep = array_values(array_unique([...$admins, ...$dealerAccounts->pluck('id')->map(fn ($id) => (int) $id)->all()]));
        sort($keep, SORT_NUMERIC);
        $removed = $users->whereNotIn('id', $keep)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $delete = [];
        foreach (self::BUSINESS as $table) {
            if (isset($engines[$table])) {
                $delete[$table] = ['all' => true];
            }
        }
        // Soft references: remove only credentials belonging to removed accounts.
        foreach (['personal_access_tokens', 'sessions', 'password_reset_tokens', 'password_resets'] as $table) {
            if (! isset($engines[$table])) {
                continue;
            }
            if ($table === 'personal_access_tokens') {
                $type = (new User)->getMorphClass();
                if (DB::table($table)->whereIn('tokenable_id', $removed)->where('tokenable_type', '!=', $type)->exists()) {
                    $blockers[] = 'Unknown token morph owner collides with removed user IDs.';
                }
                $query = DB::table($table)->where('tokenable_type', $type)->whereIn('tokenable_id', $removed);
                $key = 'id';
            } elseif ($table === 'sessions') {
                $query = DB::table($table)->whereIn('user_id', $removed);
                $key = 'id';
            } else {
                $key = 'email';
                $query = DB::table($table)->whereIn('email', $users->whereIn('id', $removed)->pluck('email')->all());
            }
            $delete[$table] = ['key' => $key, 'values' => $query->orderBy($key)->pluck($key)->all()];
        }
        $delete['users'] = ['key' => 'id', 'values' => $removed];
        foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
            if (isset($engines[$table]) && DB::table($table)->exists()) {
                $blockers[] = 'Pending/failed queue or batch records require operator review: '.$table;
            }
        }
        if (config('realistic_demo.enabled') || config('realistic_demo.allow_production') || config('realistic_demo.include_in_metrics')) {
            $blockers[] = 'Disable all demo install/production/metrics flags before planning reset.';
        }
        $foreign = $this->foreignKeys();
        $positions = array_flip(array_keys($delete));
        foreach ($foreign as $fk) {
            if ($fk->source_schema !== $database || $fk->target_schema !== $database) {
                $blockers[] = 'Cross-schema FK requires operator review: '.$fk->name;

                continue;
            }
            if (! isset($delete[$fk->target_table])) {
                continue;
            }
            if ($fk->target_column !== 'id') {
                $blockers[] = 'Unexpected incoming FK target requires review: '.$fk->name;

                continue;
            }
            $target = $delete[$fk->target_table];
            $query = DB::table($fk->source_table);
            if (! ($target['all'] ?? false)) {
                $query->whereIn($fk->source_column, $target['values']);
            }
            $source = $delete[$fk->source_table] ?? null;
            if ($source === null || ! ($source['all'] ?? false)) {
                if ($source !== null) {
                    $query->whereNotIn($source['key'], $source['values']);
                }
                if ($query->exists()) {
                    $blockers[] = 'Retained rows reference reset data: '.$fk->source_table.'.'.$fk->source_column;
                }
            }
            if ($fk->source_table !== 'users' || $fk->target_table !== 'users') {
                if ($source !== null && $positions[$fk->source_table] >= $positions[$fk->target_table]) {
                    $blockers[] = 'Unexpected FK deletion order: '.$fk->name;
                }
            } elseif ($fk->source_column !== 'reviewed_by') {
                $blockers[] = 'Unexpected self-reference requires review: '.$fk->name;
            }
        }
        $orphans = $this->orphans($foreign);
        if ($orphans) {
            $blockers[] = 'Existing orphan foreign keys require review.';
        }
        $userOrder = $this->userOrder($removed, $blockers);
        $schemas = [];
        foreach ($engines as $table => $engine) {
            $row = (array) DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`');
            $schemas[$table] = hash('sha256', array_values($row)[1]);
        }
        $tables = app(DemoCleanup::class)->snapshots();
        foreach ($tables as $table => &$row) {
            $scope = $delete[$table] ?? [];
            $row['delete'] = ($scope['all'] ?? false) ? $row['total'] : count($scope['values'] ?? []);
            $row['keep'] = $row['total'] - $row['delete'];
        }
        unset($row);
        $plan = ['purpose' => 'pre-go-live-business-reset', 'version' => 1, 'database' => $database,
            'binding' => app(DemoCleanup::class)->binding(), 'approved_admin_ids' => $admins, 'kept_dealer_ids' => $dealers,
            'admin_candidates' => $adminRows->map(fn ($u) => ['id' => (int) $u->id, 'status' => $u->status, 'kept' => in_array((int) $u->id, $admins, true)])->values()->all(),
            'kept_dealer_accounts' => $dealerAccounts->map(fn ($u) => ['id' => (int) $u->id, 'dealer_id' => (int) $u->dealer_id, 'status' => $u->status])->values()->all(),
            'kept_user_ids' => $keep, 'deleted_user_ids' => $removed, 'user_delete_order' => $userOrder,
            'delete' => $delete, 'tables' => $tables, 'schema_hashes' => $schemas, 'foreign_keys' => $foreign, 'orphans' => $orphans,
            'blockers' => array_values(array_unique($blockers)), 'files_deleted' => false];
        $plan['eligible'] = $plan['blockers'] === [];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));

        return $plan;
    }

    private function retained(array $delete): array
    {
        $result = [];
        foreach (array_keys($this->tables()) as $table) {
            $query = DB::table($table);
            if (isset($delete[$table])) {
                $scope = $delete[$table];
                ($scope['all'] ?? false) ? $query->whereRaw('1 = 0') : $query->whereNotIn($scope['key'], $scope['values']);
            }
            $hashes = [];
            foreach ($query->cursor() as $row) {
                $hashes[] = hash('sha256', serialize((array) $row));
            }
            sort($hashes, SORT_STRING);
            $result[$table] = ['total' => count($hashes), 'sha256' => hash('sha256', implode('', $hashes))];
        }

        return $result;
    }

    public function execute(string $database, array $admins, string $expected, string $receipt, string $restored): array
    {
        $result = app(SubmissionIntake::class)->locked(function () use ($database, $admins, $expected, $receipt, $restored) {
            if (! in_array(DB::selectOne('SELECT @@transaction_isolation AS level')->level, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                throw new LogicException('Reset requires repeatable-read/serializable; isolation will not be weakened.');
            }
            foreach ($this->tables() as $table => $engine) {
                DB::table($table)->lockForUpdate()->get();
            }
            $plan = $this->audit($database, $admins);
            if (! $plan['eligible'] || ! hash_equals($plan['plan_hash'], $expected)) {
                throw new LogicException('Reset blocked or database/allowlist/schema changed; create a new plan and backup.');
            }
            app(ResetDemoBackup::class)->validate($receipt, $plan, $restored);
            $preserved = $this->retained($plan['delete']);
            $deleted = [];
            foreach ($plan['delete'] as $table => $scope) {
                $actual = 0;
                if ($table === 'users') {
                    foreach ($plan['user_delete_order'] as $batch) {
                        $actual += DB::table('users')->whereIn('id', $batch)->delete();
                    }
                } else {
                    $query = DB::table($table);
                    if (! ($scope['all'] ?? false)) {
                        $query->whereIn($scope['key'], $scope['values']);
                    }
                    $actual = $query->delete();
                }
                if ($actual !== $plan['tables'][$table]['delete']) {
                    throw new LogicException('Reset count mismatch; rolling back.');
                }
                $deleted[$table] = $actual;
            }
            if ($preserved !== app(DemoCleanup::class)->snapshots() || $this->orphans($plan['foreign_keys'])) {
                throw new LogicException('Retained Dealer/accounts/configuration changed or orphan detected; rolling back.');
            }
            // Framework runs this only after the outermost real commit. Tests use
            // Laravel's transaction manager which excludes the fixture wrapper.
            DB::afterCommit(fn () => app(ResetDemoRetirement::class)->seal($plan, $preserved));

            return ['deleted' => $deleted, 'kept_admin_ids' => $plan['approved_admin_ids'],
                'kept_dealer_accounts' => $plan['kept_dealer_accounts'], 'dealers' => 87,
                'retained_data_unchanged' => true, 'files_deleted' => false,
                'retirement_receipt' => app(ResetDemoRetirement::class)->path()];
        });

        return $result;
    }
}
