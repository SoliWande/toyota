# Dealer-only database stage

Do not deploy the existing Sales authentication, models or UI against the migrated
schema. The application must be refactored in a subsequent release and deployed
together with this migration. This task only rehearses the database stage on
restored copies; it does not migrate `toyota` or `toyota_testing`.

## Preservation

- Submissions, evidence paths, submitted/reviewed timestamps, moderation history,
  awards and winner snapshots retain all original IDs and original field values.
- Sales rows are copied exactly to immutable `legacy_sales_accounts`, including
  original credentials, status, Dealer and review metadata. Three historical
  `sales_id` foreign keys now reference that archive. These are audit identities,
  not login users. No Sales is converted; no Dealer login is provisioned.
- `users.role` accepts only `admin/dealer`. A generated unique key enforces one
  login per Dealer across every status. Login identity cannot transfer Dealers.
  Uniqueness guarantees **at most one**: exactly one per Dealer still requires
  independently verified account emails and a provisioning workflow.
- New submissions require `dealer_id` and `created_by_user_id` of the matching
  Dealer account. New rows cannot use `sales_id`. Attribution and submitted time
  are immutable even through raw SQL.
- Unknown historical `dealer_id` stays NULL, never inferred from the current
  Dealer. The audited dataset has **500 unresolved submissions (300 approved)**.
  They remain reviewable with evidence and audit links intact. Universal
  `dealer_id NOT NULL` is blocked until an authoritative per-submission mapping
  exists. A later reviewed migration must record that mapping's evidence and
  explicitly handle the attribution trigger. Do not invent an unknown Dealer or
  use an award's Dealer as proof of submission attribution.
- Existing awards are annotated `competition_scope=legacy_mixed`; all original
  fields/winners remain unchanged. New awards default to `dealer` and cannot have
  Sales winners. Published/historical awards and winners and all review history
  are protected against raw SQL updates/deletes.
- Dealer scoring must subsequently aggregate submission snapshots directly,
  without joining/filtering the legacy user's role. Unresolved rows cannot be
  counted for an assumed Dealer. That query/auth refactor is outside this stage.

## Verify backup before migration

Stop application/worker writes during backup and deployment. MySQL DDL commits
implicitly. Run `scripts/verify-dealer-backup.php` with the project's PHP binary;
set `MYSQL_BIN_DIR` if MySQL executables are elsewhere. The script:

1. Reads local `.env` privately; refuses a source other than audited `toyota`.
2. Dumps schema/data/triggers, without DROP TABLE SQL.
3. Copies all referenced private evidence and verifies SHA-256 hashes.
4. Restores to a uniquely named NEW `toyota_dealer_integrity_*` schema.
5. Compares every table's rows/hashes and trigger definitions; confirms the source
   did not change during backup.
6. Writes private `verified.json` under ignored `.laravel-tools/backups/`.

Backups contain customer data and credentials: keep them outside Git and private.
Restored schemas are retained for inspection; this task does not delete them.
The script's `toyota` restriction is intentional; another deployment database
requires an independently reviewed backup configuration.

Set `DEALER_MIGRATION_BACKUP_MANIFEST` to that manifest's absolute path before
deployment, rebuilding cached configuration as appropriate. Migration preflight
refuses missing/corrupt backups, changed data, legacy login tokens, invalid Sales
references or Sales reviewers/publishers **before DDL**. Legacy tokens, if present,
need an explicit archive/revocation plan. Never reassign old Sales sessions/tokens
to new accounts.

`down()` refuses destructive reversal. If deployment fails after DDL begins, keep
writes stopped and restore the verified backup; do not retry partially committed
DDL. After new writes are accepted, recovery needs reviewed reconciliation.

## Integrity tests

Set `DEALER_INTEGRITY_MANIFEST` to the verified manifest, then run:

```powershell
& 'D:/OSPanel/modules/php/PHP_8.1/php.exe' -d sys_temp_dir="$env:TEMP" vendor/bin/phpunit tests/Feature/DealerOnlyDatabaseIntegrityTest.php --do-not-cache-result
```

Each test restores the verified dataset into a NEW schema, checks all table hashes
and evidence before migration, and uses transactions for adversarial fixtures.
It never invokes `migrate:fresh`, `db:wipe`, `RefreshDatabase` or
`DatabaseMigrations`. These opt-in tests skip without a manifest and intentionally
require the audited dataset's mixed historical cases. They do not mutate either
existing application/test database.

Coverage includes exact preservation, archived FKs, NULL attribution, new Dealer
submissions, forged ownership, unique accounts across statuses, immutable
identity/time, review append-only behavior, duplicate approval, Dealer-only new
awards, immutable published snapshots, restricted deletions, rollback refusal,
and missing/stale-backup/token preflight failures.
