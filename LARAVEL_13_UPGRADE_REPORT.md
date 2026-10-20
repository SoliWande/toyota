# Toyota Community — Laravel 13 upgrade

Date: 2026-10-19. Branch: `upgrade/laravel-13`, based on clean `main` at `9f3ffa3`.

## Status

Completed sequentially: Laravel 10.50.3 → 11.57.0 → 12.70.0 → 13.37.0. Final installed vendor and composer.lock match. Final Composer validation/platform checks and audit pass, with no security advisories or abandoned packages and no temporary advisory exceptions remaining.

During the framework upgrade, no application migrations, demo seeds, production changes, Git reset/revert, commit or push were performed. The subsequent user-authorized local migration is recorded at the end of this report. Existing application structure and business logic were preserved. The actual source baseline has Admin/Sales login roles and a Dealer entity; this upgrade does not introduce a third login role or change public Sales visibility.

## Runtime and tools

- All Composer, Artisan and PHPUnit commands use `D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe` (PHP 8.5.11).
- Composer 2.10.3 is local to `.laravel-tools/composer/composer.phar`; global Composer was not changed.
- Final PHP constraint is `^8.5`, matching the requested local/production runtime.
- No Docker or production VM was used.
- Composer updates used `--no-scripts`. Package discovery was invoked explicitly; Composer's asset-publishing post-update hook was not run.

## Major-version checkpoints

| Gate | Framework | PHPUnit | Test result | Routes | Remaining issues at checkpoint |
|---|---|---|---|---|---|
| 10 → 11 | 11.57.0 | 11.5.57 | 489 tests, 3,159 assertions; zero errors/failures | 50 | Two vendor PDO constant deprecations on PHP 8.5; four approved temporary framework advisory exceptions |
| 11 → 12 | 12.70.0 | 11.5.57 | 489 tests, 3,159 assertions; clean | 50, unchanged | None in tested scope; all advisory exceptions removed |
| 12 → 13 | 13.37.0 | 12.5.40 | 492 tests, 3,170 assertions; clean, warning/deprecation failure flags enabled | 50, unchanged | No upgrade test failures; real-data browser check limited by absent local application schema |

Each subsequent framework was installed only after the preceding checkpoint passed its test gate. Laravel 11's vendor-only PHP 8.5 deprecations were documented and disappeared on Laravel 12; vendor source was not patched.

### Laravel 11

Official guide: https://laravel.com/framework/docs/11.x/upgrade

- Updated framework, Sanctum 3 → 4, Collision 7 → 8 and PHPUnit 10 → 11. Upgraded compatible dependencies within their declared constraints, including Carbon 2 → 3.
- Retained HTTP/Console Kernels, bootstrap bindings, application providers, routes, policies and Eloquent models.
- Renamed Sanctum middleware config key to `validate_csrf_token`, retaining application cookie/CSRF middleware behavior.
- Existing token migration already exists; no duplicate Sanctum migration was published.
- Updated the application's deprecated PDO SSL constant reference with a version-aware fallback.
- Migrated PHPUnit data providers from docblock annotations to native attributes.
- Replaced repeated hard-coded test database guards with one isolated-database guard; added tests proving application databases, production environment and another connection are rejected.
- Fixed a pre-existing stale public test label (`Top Dealer` → `Top Đại Lý`) to match the source UI. Adjusted untrusted-host test to Laravel/Symfony's 400 response while retaining rejection assertions.
- The default landing-page example test now uses RefreshDatabase on the guarded test database.
- Race-test workers send PHP diagnostics to stderr so their stdout protocol remains machine-readable.

### Laravel 12

Official guide: https://laravel.com/framework/docs/12.x/upgrade

- Only composer.json/composer.lock changed at this gate: framework 11.57.0 → 12.70.0 and addition of symfony/polyfill-php84.
- Carbon 3 and PHPUnit 11 were already installed and tested at the preceding gate.
- Existing local/evidence disk roots stay explicit; no evidence path changed.
- Existing image validation excludes SVG already. No UUID trait, diffIn* application call, column change(), custom concurrency implementation or relevant DBAL API required changes.
- All routes/middleware matched Laravel 11; full regression suite passed without deprecations.

### Laravel 13

Official guide: https://laravel.com/framework/docs/13.x/upgrade

- Updated framework, Tinker 2 → 3 and PHPUnit 11 → 12.
- Application `VerifyCsrfToken` class keeps its existing name/Kernel references and now extends `PreventRequestForgery`, avoiding the deprecated framework alias.
- Laravel 13's exception handler no longer falls back to the login route when an authentication exception lacks a redirect URL. Registered the legacy browser redirect explicitly in AppServiceProvider. Kept JSON responses at 401; both browser and JSON session revocation are covered by regression tests.
- Explicitly retained session `serialization = php` to preserve existing payload format. Session cookie and cache/Redis prefixes already have explicit application configuration.
- Added cross-site logout rejection without token and same-origin logout verification tests, alongside existing token-based CSRF tests.
- No application object-cache payloads, custom queue/cache contracts, upsert(), domain routes, polymorphic pivot naming or other relevant overrides were found requiring a change.
- Assessed removals before installation: `sebastian/code-unit` and `sebastian/code-unit-reverse-lookup` are obsolete transitive PHPUnit/coverage dependencies with no application references; `symfony/polyfill-php83` is unnecessary for the selected PHP 8.5 dependency graph. No direct application package was removed or replaced. Sail remains installed as a dev dependency but was never used.

## Temporary Laravel 11 security exceptions

Composer initially blocked all Laravel 11 releases. Following explicit user approval, reviewed Packagist advisory metadata and limited the temporary exception to four IDs affecting the latest 11.57.0 release:

| Advisory | Finding | Patched release ranges relevant to this upgrade |
|---|---|---|
| PKSA-d5tc-s1qs-h781 | XSS in debug page information | Laravel 12.69.0 / 13.30.0 |
| PKSA-m5cs-t1y6-qpcs | Temporary signed URL path confusion | Laravel 12.61.1 / 13.12.0 |
| PKSA-3r5d-mb8f-1qw9 | CRLF injection in default email validation | Laravel 12.60.0 / 13.10.0 |
| PKSA-mdq4-51ck-6kdq | Same email validation issue, alternate advisory record | Laravel 12.60.0 / 13.10.0 |

Four older advisories listed by the initial broad Laravel 11 constraint did not affect 11.57.0 and were never exempted. Exceptions applied only to dependency blocking (`on-block: true`, `on-audit: false` on ignore rules), so audit continued to report the findings. No global policy was disabled. All exceptions were removed before Laravel 12 installation.

Metadata retained locally: `.laravel-tools/composer/laravel-advisories.json`. Composer policy: https://getcomposer.org/doc/06-config.md#policy

## Database, evidence and test isolation

- Created a new MySQL schema `toyota_testing_upgrade_20261019`, after checking that it did not exist. Existing `toyota_testing` was not reused.
- Schema migrations, factories and test-triggered seeders ran only inside that isolated schema. No migration files were modified.
- Central `Tests\Support\TestDatabase` verifies testing environment, mysql connection, an allow-listed test schema name and equality with the explicitly selected `TOYOTA_TEST_DATABASE` before tests can migrate/write.
- Race-test subprocesses inherit the selected isolated database and run the same guard.
- `.env` remains unchanged. Source MySQL configured by `.env` reports `Unknown database toyota` on the local server. The source schema was absent before tests; no application database was created, migrated or populated to hide that issue. Restore/connect the existing business database separately for a real-data browser check.
- Source `.env`, all 18 migration files and 501 existing private evidence-directory files were SHA-256 snapshotted before database-backed tests. All 520 hashes match after upgrade; no protected file was changed or removed. Source database absence was also rechecked and unchanged.
- Evidence tests use fake storage. Real evidence paths and the private evidence controller/policy remain unchanged.

Local test command (isolated XML contains only test overrides and absolute paths, no credentials):

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' vendor/phpunit/phpunit/phpunit -c .laravel-tools/composer/phpunit-upgrade.xml --do-not-cache-result
```

Standard `phpunit.xml` still defaults to `toyota_testing` and now clears DATABASE_URL to prevent environment URL overrides. Creating/populating that default test schema is not part of this upgrade. The isolated local configuration sets TOYOTA_TEST_DATABASE and DB_DATABASE to the new schema.

## Validation artifacts

Ignored local artifacts under `.laravel-tools/composer/` include:

- `tests-11.log`, `tests-11.xml`; `tests-12.log`, `tests-12.xml`; `tests-13.log`, `tests-13.xml` (final full suite); `auth-13.log` (56 targeted tests / 430 assertions).
- `composer-11.lock`, `composer-12.lock` dependency checkpoints.
- `routes-11.json`, `routes-12.json`, `routes-13.json`: all 50 routes match by method, URI, name and middleware across the major checkpoints.
- `upgrade-protected-hashes.json`, `source-database-before.json` (hashes/counts or absence metadata, no row contents).

Full suites exercise authentication, authorization/policies, customer submission validation/evidence, MySQL approval races/unique constraints, immutable email, historical Dealer attribution, awards, leaderboards, recovery, security and query counts. Tests do not establish production environment compatibility or validate historical business data absent from this MySQL instance.

Final PHPUnit command adds `--fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning --log-junit .laravel-tools/composer/tests-13.xml` to the isolated command above. Final run exited 0. Composer `validate --strict --no-check-publish`, `check-platform-reqs --lock` and `audit --locked --format=json` passed. Syntax checks passed for 36 modified/new PHP files. Pint checks passed for AppServiceProvider and the two new test safety files. `schedule:list` confirms no scheduled tasks. `git diff --check` passed.

## Final dependency versions

| Direct dependency | Final locked version |
|---|---|
| laravel/framework | 13.37.0 |
| laravel/sanctum | 4.3.3 |
| laravel/tinker | 3.0.2 |
| guzzlehttp/guzzle | 7.16.0 |
| fakerphp/faker | 1.24.1 |
| laravel/pint | 1.32.1 |
| laravel/sail | 1.69.0 |
| mockery/mockery | 1.6.15 |
| nunomaduro/collision | 8.9.5 |
| phpunit/phpunit | 12.5.40 |
| spatie/laravel-ignition | 2.12.0 |

Carbon is 3.15.0 and main Symfony components are 8.1.x. Full transitive versions are recorded in composer.lock.

## Files changed by checkpoint

- Laravel 11: composer.json, composer.lock, config/database.php, config/sanctum.php, tests/CreatesApplication.php, tests/Fixtures/approve_submission.php and the regression test files listed below; new tests/Support/TestDatabase.php and tests/Unit/TestDatabaseSafetyTest.php. phpunit.xml clears DATABASE_URL as part of test isolation.
- Laravel 12: composer.json and composer.lock only; no further runtime/schema changes required.
- Laravel 13: composer.json, composer.lock, app/Http/Middleware/VerifyCsrfToken.php, app/Providers/AppServiceProvider.php, config/session.php, tests/Feature/AuthenticationTest.php, tests/Feature/PasswordRecoveryTest.php and this report.
- No enums, models, policies, routes, migrations, controllers, views or frontend dependency files changed.

Complete changed test-file list:

- `tests/CreatesApplication.php`
- `tests/Feature/AdminDashboardTest.php`
- `tests/Feature/AdminSalesManagementTest.php`
- `tests/Feature/AdminSubmissionModerationTest.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AwardWorkflowTest.php`
- `tests/Feature/CustomerSubmissionTest.php`
- `tests/Feature/DatabaseFoundationTest.php`
- `tests/Feature/DealerHistoryTest.php`
- `tests/Feature/DealerManagementTest.php`
- `tests/Feature/DevelopmentDemoSeederTest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Feature/LandingPageTest.php`
- `tests/Feature/LeaderboardServiceTest.php`
- `tests/Feature/PasswordRecoveryTest.php`
- `tests/Feature/PerformanceQueryTest.php`
- `tests/Feature/ProfileManagementTest.php`
- `tests/Feature/PublicLeaderboardTest.php`
- `tests/Feature/SalesDashboardTest.php`
- `tests/Feature/SecurityAuditTest.php`
- `tests/Feature/SubmissionApprovalRaceTest.php`
- `tests/Feature/SubmissionDuplicateTest.php`
- `tests/Feature/SubmissionVehicleEvidenceTest.php`
- `tests/Feature/ToyotaDealerSeederTest.php`
- `tests/Feature/V1WorkflowTest.php`
- `tests/Fixtures/approve_submission.php`
- `tests/Support/TestDatabase.php`
- `tests/Unit/CustomerSubmissionPolicyTest.php`
- `tests/Unit/FacebookProfileUrlTest.php`
- `tests/Unit/LeaderboardPeriodTest.php`
- `tests/Unit/TestDatabaseSafetyTest.php`

## Follow-up: user-authorized local application migration

After the framework upgrade, the user explicitly requested running migrations for the application database. Inspected the active server: MySQL 8.0.30 at 127.0.0.1:3306, data directory `D:\laragon\data\mysql-8\`. The configured `toyota` schema was absent on this instance. An older OSPanel schema directory exists at `D:\OSPanel\userdata\MySQL-8.0-Win10\toyota`; its data was not modified or imported.

Created an empty `toyota` schema on the local Laragon instance and ran `artisan migrate --no-interaction` with Laragon PHP 8.5.11. All 18 migrations completed successfully in batch 1. Verified migrate:status and table counts: users=0, dealers=0, customer_submissions=0. No migrate:fresh, rollback, seeding or data restore was performed. Existing evidence remains untouched.

A SQL backup is available at `.laravel-tools/backups/dealer_20261016_115437_95f5ed/toyota.sql` (2026-10-16). It has not been restored. Migration created schema only; historical data recovery is a separate operation. The earlier source-database absence snapshot describes the pre-migration state, not the current state after this explicit user request.

## Follow-up: user-authorized default seed

Ran `artisan migrate --seed --no-interaction` against local Laragon `toyota` using PHP 8.5.11. No pending migrations. Default DatabaseSeeder completed: 87 Toyota directory dealers, one development dealer and one active development Admin using the existing DEV_ADMIN_EMAIL/DEV_ADMIN_PASSWORD configuration. Verified totals: 18 migrations, 88 dealers, one Admin, zero Sales and zero customer submissions. DevelopmentDemoSeeder was not invoked. Existing evidence and all protected files remain unchanged. This seeds reference/development data; it does not restore historical records from the SQL backup.

## Follow-up: user-authorized demo Sales and submissions

Ran `artisan db:seed --class=DevelopmentDemoSeeder --no-interaction` on local `toyota` using PHP 8.5.11. Created 50 marked demo Sales (36 active, 8 pending, 3 rejected, 3 blocked), 500 synthetic customer submissions (300 approved, 150 pending, 50 rejected), 500 private placeholder evidence images, moderation history and published demo award snapshots. Existing Admin credentials and existing evidence files were preserved. Demo Sales authenticate using DEV_DEMO_PASSWORD configured in the existing untracked .env. Verified all submission evidence files exist and all submissions have Dealer attribution snapshots. All 520 pre-upgrade protected-file hashes remain unchanged.
