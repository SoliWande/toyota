<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run only after a database + private-evidence backup has been restored and verified.
     * MySQL DDL commits implicitly: rehearse on that restored copy before deployment.
     * Unknown historical dealer_id values deliberately remain NULL. They are NOT
     * inferred from users.dealer_id; resolving them requires a separately audited mapping.
     */
    public function up(): void
    {
        $this->preflight();

        // Exact archival copy, including original IDs, credentials, statuses and timestamps.
        // CREATE LIKE copies columns/indexes, but not FKs or authentication triggers.
        DB::statement('CREATE TABLE legacy_sales_accounts LIKE users');
        DB::statement("INSERT INTO legacy_sales_accounts SELECT * FROM users WHERE role = 'sales'");
        if (DB::table('legacy_sales_accounts')->count() !== DB::table('users')->where('role', 'sales')->count()) {
            throw new RuntimeException('Legacy account archival verification failed. Stop deployment.');
        }
        DB::statement("ALTER TABLE legacy_sales_accounts
            ADD CONSTRAINT legacy_accounts_sales_check CHECK (role = 'sales'),
            ADD CONSTRAINT legacy_accounts_dealer_fk FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE RESTRICT,
            ADD CONSTRAINT legacy_accounts_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT");

        DB::statement('ALTER TABLE customer_submissions
            DROP FOREIGN KEY customer_submissions_sales_id_foreign,
            MODIFY sales_id BIGINT UNSIGNED NULL,
            ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL,
            ADD CONSTRAINT submissions_legacy_sales_fk FOREIGN KEY (sales_id) REFERENCES legacy_sales_accounts(id) ON DELETE RESTRICT,
            ADD CONSTRAINT submissions_creator_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
            ADD CONSTRAINT submissions_owner_origin_check CHECK (
                (sales_id IS NOT NULL AND created_by_user_id IS NULL)
                OR (sales_id IS NULL AND created_by_user_id IS NOT NULL AND dealer_id IS NOT NULL)),
            ADD INDEX submissions_dealer_time_index (dealer_id, submitted_at)');

        DB::statement('ALTER TABLE award_winners
            DROP FOREIGN KEY award_winners_sales_id_foreign,
            ADD CONSTRAINT winners_legacy_sales_fk FOREIGN KEY (sales_id) REFERENCES legacy_sales_accounts(id) ON DELETE RESTRICT');

        DB::statement("ALTER TABLE moderation_reviews
            DROP CHECK reviews_subject_status_check,
            DROP FOREIGN KEY moderation_reviews_sales_id_foreign,
            ADD COLUMN account_user_id BIGINT UNSIGNED NULL,
            ADD CONSTRAINT reviews_legacy_sales_fk FOREIGN KEY (sales_id) REFERENCES legacy_sales_accounts(id) ON DELETE RESTRICT,
            ADD CONSTRAINT reviews_account_user_fk FOREIGN KEY (account_user_id) REFERENCES users(id) ON DELETE RESTRICT,
            ADD INDEX reviews_account_time_index (account_user_id, reviewed_at),
            ADD CONSTRAINT reviews_subject_status_check CHECK (
                ((sales_id IS NOT NULL AND account_user_id IS NULL AND customer_submission_id IS NULL)
                  OR (sales_id IS NULL AND account_user_id IS NOT NULL AND customer_submission_id IS NULL))
                  AND from_status IN ('pending', 'active', 'rejected', 'blocked')
                  AND to_status IN ('pending', 'active', 'rejected', 'blocked')
                OR (customer_submission_id IS NOT NULL AND sales_id IS NULL AND account_user_id IS NULL
                  AND from_status IN ('pending', 'approved', 'rejected')
                  AND to_status IN ('pending', 'approved', 'rejected')))");

        // Archival references have been reattached. No submission/review/winner is rewritten.
        DB::table('users')->where('role', 'sales')->delete();
        DB::statement("ALTER TABLE users
            DROP CHECK users_sales_dealer_check,
            MODIFY role ENUM('admin', 'dealer') NOT NULL DEFAULT 'dealer',
            ADD COLUMN dealer_login_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN role = 'dealer' THEN dealer_id ELSE NULL END) STORED,
            ADD UNIQUE INDEX users_one_dealer_account_unique (dealer_login_id),
            ADD CONSTRAINT users_dealer_required_check CHECK (role <> 'dealer' OR dealer_id IS NOT NULL)");

        // Existing awards keep every original value; only their ruleset is annotated.
        DB::statement("ALTER TABLE awards ADD COLUMN competition_scope ENUM('legacy_mixed', 'dealer') NOT NULL DEFAULT 'legacy_mixed'");
        DB::statement("ALTER TABLE awards ALTER COLUMN competition_scope SET DEFAULT 'dealer'");

        DB::unprepared("CREATE TRIGGER users_dealer_identity_immutable BEFORE UPDATE ON users FOR EACH ROW
            BEGIN
                IF OLD.role = 'dealer' AND (NEW.role <> 'dealer' OR NOT (NEW.dealer_id <=> OLD.dealer_id)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A dealer login cannot change its dealer identity or role';
                END IF;
            END");

        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared('CREATE TRIGGER legacy_sales_no_'.strtolower($event)." BEFORE $event ON legacy_sales_accounts FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy sales audit records are immutable'");
        }
        DB::unprepared("CREATE TRIGGER legacy_sales_no_insert BEFORE INSERT ON legacy_sales_accounts FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy sales archive is closed'");

        DB::unprepared("CREATE TRIGGER submissions_dealer_insert BEFORE INSERT ON customer_submissions FOR EACH ROW
            BEGIN
                IF NEW.sales_id IS NOT NULL OR NEW.created_by_user_id IS NULL OR NEW.dealer_id IS NULL
                   OR NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.created_by_user_id AND role = 'dealer' AND dealer_id = NEW.dealer_id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A new submission requires its dealer account and dealer attribution';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER submissions_attribution_immutable BEFORE UPDATE ON customer_submissions FOR EACH ROW
            BEGIN
                IF NOT (NEW.sales_id <=> OLD.sales_id) OR NOT (NEW.dealer_id <=> OLD.dealer_id)
                   OR NOT (NEW.created_by_user_id <=> OLD.created_by_user_id) OR NOT (NEW.submitted_at <=> OLD.submitted_at) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Submission attribution and submitted time are immutable';
                END IF;
            END");

        DB::unprepared("CREATE TRIGGER winners_dealer_insert BEFORE INSERT ON award_winners FOR EACH ROW
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM awards WHERE id = NEW.award_id AND published_at IS NULL AND competition_scope = 'dealer')
                   OR NEW.winner_type <> 'dealer' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New winners require a dealer-only draft award';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER winners_preserve_update BEFORE UPDATE ON award_winners FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT 1 FROM awards WHERE id IN (OLD.award_id, NEW.award_id) AND (published_at IS NOT NULL OR competition_scope = 'legacy_mixed'))
                   OR NEW.winner_type <> 'dealer' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical winners are immutable; new winners must be dealers';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER winners_preserve_delete BEFORE DELETE ON award_winners FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT 1 FROM awards WHERE id = OLD.award_id AND (published_at IS NOT NULL OR competition_scope = 'legacy_mixed')) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical winners are immutable';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER awards_dealer_insert BEFORE INSERT ON awards FOR EACH ROW
            BEGIN
                IF NEW.competition_scope <> 'dealer' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New awards must be dealer-only';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER awards_preserve_update BEFORE UPDATE ON awards FOR EACH ROW
            BEGIN
                IF OLD.published_at IS NOT NULL OR OLD.competition_scope = 'legacy_mixed'
                   OR NOT (NEW.competition_scope <=> OLD.competition_scope) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical awards and competition scopes are immutable';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER awards_preserve_delete BEFORE DELETE ON awards FOR EACH ROW
            BEGIN
                IF OLD.published_at IS NOT NULL OR OLD.competition_scope = 'legacy_mixed' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical awards are immutable';
                END IF;
            END");

        // Review history remains append-only even through query-builder/raw SQL.
        DB::unprepared("CREATE TRIGGER reviews_dealer_insert BEFORE INSERT ON moderation_reviews FOR EACH ROW
            BEGIN
                IF NEW.sales_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.reviewed_by AND role = 'admin')
                   OR (NEW.account_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.account_user_id AND role = 'dealer')) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New account reviews require a dealer account and admin reviewer';
                END IF;
            END");
        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared('CREATE TRIGGER reviews_no_'.strtolower($event)." BEFORE $event ON moderation_reviews FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Moderation history is append-only'");
        }
    }

    private function preflight(): void
    {
        if (DB::getDriverName() !== 'mysql' || Schema::hasTable('legacy_sales_accounts')) {
            throw new RuntimeException('Requires the original MySQL schema. Do not retry partial MySQL DDL; restore the verified backup.');
        }
        foreach (['users' => 'reviewed_by', 'customer_submissions' => 'reviewed_by', 'moderation_reviews' => 'reviewed_by', 'awards' => 'published_by'] as $table => $column) {
            if (DB::table($table.' as subject')->join('users as actor', 'actor.id', '=', 'subject.'.$column)->where('actor.role', 'sales')->exists()) {
                throw new RuntimeException('A legacy sales account is a reviewer/publisher. Resolve this audit anomaly before migration.');
            }
        }
        foreach (['customer_submissions', 'moderation_reviews', 'award_winners'] as $table) {
            if (DB::table($table.' as subject')->join('users as actor', 'actor.id', '=', 'subject.sales_id')->where('actor.role', '<>', 'sales')->exists()) {
                throw new RuntimeException('A historical sales reference targets a non-sales user.');
            }
        }
        if (DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)
            ->whereIn('tokenable_id', DB::table('users')->select('id')->where('role', 'sales'))->exists()
            || DB::table('password_reset_tokens')->whereIn('email', DB::table('users')->select('email')->where('role', 'sales'))->exists()) {
            throw new RuntimeException('Legacy login tokens exist. Archive/revoke them explicitly before migrating.');
        }

        $this->verifyBackup();
    }

    private function verifyBackup(): void
    {
        $path = config('dealer_migration.backup_manifest');
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('A restored and verified backup manifest is required before changing data.');
        }
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $dump = dirname($path).'/toyota.sql';
        $database = DB::connection()->getDatabaseName();
        $isolatedTest = app()->environment('testing') && preg_match('/^toyota_dealer_integrity_test_[a-f0-9]{16}$/D', $database);
        if (empty($manifest['verified_at']) || empty($manifest['restore_database'])
            || ! isset($manifest['tables'], $manifest['evidence'], $manifest['dump_sha256'])
            || (! $isolatedTest && ($manifest['source_database'] ?? null) !== $database)
            || ! is_file($dump) || ! hash_equals($manifest['dump_sha256'], hash_file('sha256', $dump))) {
            throw new RuntimeException('Backup verification metadata or SQL checksum is invalid.');
        }
        foreach ($manifest['tables'] as $table => $expected) {
            if ($table === '_trigger_definitions') {
                continue;
            }
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                throw new RuntimeException('Invalid backup table name.');
            }
            $rows = DB::connection()->getPdo()->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $hashes = array_map(fn ($row) => hash('sha256', serialize($row)), $rows);
            sort($hashes);
            if (count($rows) !== $expected['rows'] || ! hash_equals($expected['sha256'], hash('sha256', implode('', $hashes)))) {
                throw new RuntimeException('Database differs from the verified backup. Repeat backup/restore verification during maintenance.');
            }
        }
        foreach ($manifest['evidence'] as $relative => $hash) {
            if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, ':')) {
                throw new RuntimeException('Invalid evidence backup path.');
            }
            $copy = dirname($path).'/evidence/'.$relative;
            if (! is_file($copy) || ! hash_equals($hash, hash_file('sha256', $copy))) {
                throw new RuntimeException('Private evidence backup is missing or corrupt.');
            }
        }
    }

    public function down(): void
    {
        throw new LogicException('Forward-only data migration. Restore a verified backup or use a separately reviewed forward migration; never discard dealer data or audit history.');
    }
};
