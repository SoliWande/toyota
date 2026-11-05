<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

class DemoCleanup
{
    public const REQUIRED = ['users', 'dealers', 'customer_submissions', 'moderation_reviews', 'awards', 'award_winners',
        'submission_edits', 'dealer_registration_receipts', 'submission_verification_states', 'submission_verification_audits', 'submission_intake_mutex'];

    public function binding(): string
    {
        $db = DB::connection();

        return hash('sha256', json_encode([$db->getDriverName(), $db->getConfig('host'), $db->getConfig('port'), $db->getDatabaseName()], JSON_THROW_ON_ERROR));
    }

    private function tables(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new LogicException('Cleanup requires the audited MySQL/InnoDB schema.');
        }
        if (DB::connection()->getConfig('read') || DB::connection()->getConfig('write')
            || DB::selectOne('SELECT DATABASE() AS name')->name !== DB::connection()->getDatabaseName()) {
            throw new LogicException('Use one explicit primary database connection; replicas/database mismatch require operator review.');
        }

        return collect(DB::select("SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"))
            ->mapWithKeys(fn ($row) => [$row->name => $row->engine])->all();
    }

    /** No values or PII leave this method; stable even for tables without a primary key. */
    public function snapshots(array $exclude = []): array
    {
        $result = [];
        foreach ($this->tables() as $table => $engine) {
            $query = DB::table($table);
            if (isset($exclude[$table])) {
                $query->whereNotIn('id', $exclude[$table]);
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

    public function audit(): array
    {
        $engines = $this->tables();
        $blockers = [];
        foreach (self::REQUIRED as $table) {
            if (! isset($engines[$table])) {
                $blockers[] = 'Missing required table: '.$table;
            }
        }
        if (array_filter($engines, fn ($engine) => $engine !== 'InnoDB')) {
            $blockers[] = 'All tables must be transactional InnoDB.';
        }
        if (array_diff(['users', 'dealers', 'customer_submissions', 'moderation_reviews', 'awards'], array_keys($engines))) {
            return ['database' => DB::connection()->getDatabaseName(), 'eligible' => false, 'blockers' => $blockers, 'tables' => []];
        }

        $ids = ['users' => [], 'customer_submissions' => [], 'moderation_reviews' => []];
        $verified = false;
        try {
            $manifest = app(ToyotaRealisticDemo::class)->verifiedCleanupManifest();
            if ($manifest !== null) {
                $ids = ['users' => $manifest['users'], 'customer_submissions' => $manifest['submissions'], 'moderation_reviews' => $manifest['reviews']];
                $verified = true;
            }
        } catch (LogicException $exception) {
            $blockers[] = $exception->getMessage();
        }

        $candidates = app(DemoDataGuard::class)->candidates();
        foreach (app(DemoDataGuard::class)->queries() as $table => $query) {
            if ($query->whereNotIn('id', $ids[$table] ?? [])->exists()) {
                $blockers[] = 'Unverified demo candidate(s) in '.$table.'; labels are not deletion proof.';
            }
        }
        $dependencies = [];
        $protectedAdmins = DB::table('users')->whereNotIn('id', $ids['users'])->where('role', 'admin')->where('status', 'active')->count();
        if ($verified) {
            if ($protectedAdmins === 0) {
                $blockers[] = 'No active Admin outside the verified demo manifest; refusing administrative lockout.';
            }
            // Every incoming FK is inspected, including future tables and SET NULL relationships.
            $foreign = DB::select("SELECT TABLE_SCHEMA AS source_schema, TABLE_NAME AS source_table, COLUMN_NAME AS source_column, REFERENCED_TABLE_NAME AS target_table FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN ('users','customer_submissions','moderation_reviews')");
            foreach ($foreign as $fk) {
                if ($fk->source_schema !== DB::connection()->getDatabaseName()) {
                    $blockers[] = 'Cross-schema foreign key requires operator review.';

                    continue;
                }
                $query = DB::table($fk->source_table)->whereIn($fk->source_column, $ids[$fk->target_table]);
                if (isset($ids[$fk->source_table])) {
                    $query->whereNotIn('id', $ids[$fk->source_table]);
                }
                $count = $query->count();
                if ($count) {
                    $dependencies[$fk->source_table.'.'.$fk->source_column] = $count;
                }
            }
            $checks = [
                'submission_edits.snapshot' => DB::table('submission_edits')->whereIn('submission_id_snapshot', $ids['customer_submissions']),
                'users.uploads' => DB::table('users')->whereIn('id', $ids['users'])->whereNotNull('avatar_path'),
                // The legacy fingerprint intentionally omits these fields. Treat
                // later account use/operator notes as review, never deletion proof.
                'users.operational_metadata' => DB::table('users')->whereIn('id', $ids['users'])
                    ->where(fn ($q) => $q->whereNotNull('remember_token')->orWhere(fn ($q) => $q
                        ->where('email', '!=', ToyotaRealisticDemo::EMAIL)->whereNotNull('admin_note'))),
                'customer_submissions.uploads' => DB::table('customer_submissions')->whereIn('id', $ids['customer_submissions'])->whereNotNull('evidence_image_path'),
                'moderation_reviews.outside_manifest_subject' => DB::table('moderation_reviews')->whereIn('id', $ids['moderation_reviews'])
                    ->where(fn ($q) => $q->whereNull('customer_submission_id')->orWhereNotIn('customer_submission_id', $ids['customer_submissions'])->orWhereNotIn('reviewed_by', $ids['users'])),
            ];
            if (isset($engines['personal_access_tokens'])) {
                $checks['personal_access_tokens'] = DB::table('personal_access_tokens')->whereIn('tokenable_id', $ids['users']);
            }
            if (isset($engines['sessions'])) {
                $checks['sessions'] = DB::table('sessions')->whereIn('user_id', $ids['users']);
            }
            foreach (['jobs', 'failed_jobs'] as $table) {
                if (isset($engines[$table])) {
                    // Serialized payload ownership cannot be inferred from IDs/text.
                    $checks[$table.'.operator_review'] = DB::table($table);
                }
            }
            foreach (['password_reset_tokens', 'password_resets'] as $table) {
                if (isset($engines[$table])) {
                    $checks[$table] = DB::table($table)->whereIn('email', DB::table('users')->whereIn('id', $ids['users'])->select('email'));
                }
            }
            foreach ($checks as $name => $query) {
                if ($count = $query->count()) {
                    $dependencies[$name] = $count;
                }
            }
            if ($dependencies) {
                $blockers[] = 'Dependent records, history or uploads are not manifest-owned; operator review required.';
            }
        }

        $tables = $this->snapshots();
        foreach ($tables as $table => &$row) {
            $row['verified_demo'] = $verified ? count($ids[$table] ?? []) : 0;
            $row['retained_unclassified'] = $row['total'] - $row['verified_demo'];
        }
        unset($row);
        $plan = ['version' => 1, 'database' => DB::connection()->getDatabaseName(), 'binding' => $this->binding(),
            'ownership_verified' => $verified, 'protected_active_admins' => $protectedAdmins, 'ids' => $ids, 'tables' => $tables, 'candidates' => $candidates,
            'dependencies' => $dependencies, 'blockers' => array_values(array_unique($blockers)), 'files_deleted' => false];
        $plan['eligible'] = $plan['blockers'] === [];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));

        return $plan;
    }

    public function execute(string $expected, string $receipt, string $restoredHash): array
    {
        $result = app(SubmissionIntake::class)->locked(function () use ($expected, $receipt, $restoredHash) {
            $isolation = DB::selectOne('SELECT @@transaction_isolation AS level')->level;
            if (! in_array($isolation, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                throw new LogicException('Cleanup requires repeatable-read/serializable and maintenance; refusing to weaken isolation.');
            }
            // Full range locks also protect soft references without foreign keys.
            foreach ($this->tables() as $table => $engine) {
                DB::table($table)->lockForUpdate()->get();
            }
            $plan = $this->audit();
            if (! $plan['eligible'] || ! hash_equals($plan['plan_hash'] ?? '', $expected)) {
                throw new LogicException('Cleanup blocked or plan changed. Run dry-run and backup again.');
            }
            app(DemoCleanupBackup::class)->validate($receipt, $plan, $restoredHash);
            $preserved = $this->snapshots($plan['ids']);
            foreach (['moderation_reviews', 'customer_submissions', 'users'] as $table) {
                $actual = DB::table($table)->whereIn('id', $plan['ids'][$table])->delete();
                if ($actual !== count($plan['ids'][$table])) {
                    throw new LogicException('Delete count mismatch; rolling back.');
                }
            }
            if ($preserved !== $this->snapshots()) {
                throw new LogicException('Retained data/Dealer changed; rolling back.');
            }

            return ['deleted' => array_map('count', $plan['ids']), 'retained_data_unchanged' => true, 'files_deleted' => false];
        });
        $result['retirement_receipt'] = app(DemoDataGuard::class)->seal($expected);

        return $result;
    }
}
