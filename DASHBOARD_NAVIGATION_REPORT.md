# Dashboard navigation and Sales avatars

> Update 2026-10-20: see [DEALER_PROFILE_DASHBOARD_REPORT.md](DEALER_PROFILE_DASHBOARD_REPORT.md) for the current scope. Public remains Dealer-only; authenticated Dealer managers can read current staff and historically attributed customers/evidence. The new Dealer role and slug migrations have not been run on the local application database. This document records the earlier navigation/avatar change.

Completed locally on 2026-10-19, branch `upgrade/laravel-13`. Earlier uncommitted changes were preserved. No commit, push or production changes.

## Result

- Admin: active Dealer count links to `/admin/dealers?status=active`; active Sales count links to `/admin/sales?status=active`. The total Dealer count is retained and a separate active count is shown.
- Admin: each Top 3 Sales name opens the existing private Sales details page; each Top 3 Dealer name opens the new private Dealer details page, including its current Sales and management links.
- Public: the active Dealer count opens the paginated active Dealer directory. Dealer names in the home preview and leaderboard open Dealer details with contact information, approved points and all-time rank.
- Public Dealer pages select only explicit Dealer fields and standing data. They do not load or expose Sales, accounts, customer information or employee counts. Historical inactive Dealers retain a details page because they can still appear in historical rankings.
- Admin Top 3 Sales show avatars with an SVG placeholder when absent; failed image loading also restores the placeholder. Sales can upload an avatar from their own profile (JPG/PNG/WebP, maximum 5 MB, dimensions up to 4096 px). Stored images are re-encoded as JPEG thumbnails, removing metadata.
- Avatar storage is private. The authenticated, active-account endpoint permits only Admin or the Sales account that owns the image. Other Sales, inactive accounts and guests cannot access it. `avatar_path` is hidden from User serialization and cannot be supplied as a profile field.
- Three AI portraits were generated with the built-in `image_gen` tool and assigned as a reusable set across all 50 demo Sales, with distinct portraits for the current weekly top three. Existing uploaded avatars are preserved. Originals and exact prompts: `database/data/demo-avatars/README.md`.

## Files changed for this request

Backend:

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/DealerController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/PublicDealerController.php` (new)
- `app/Http/Controllers/SalesAvatarController.php` (new)
- `app/Http/Requests/ProfileRequest.php`
- `app/Models/User.php`
- `app/Policies/DealerPolicy.php`
- `app/Services/SalesAvatars.php` (new)
- `config/filesystems.php`
- `routes/web.php`
- `database/migrations/2026_10_19_000016_add_avatar_path_to_users.php` (new)
- `database/seeders/DevelopmentSalesAvatarSeeder.php` (new)
- `database/data/demo-avatars/README.md`, `portrait-01.png`, `portrait-02.png`, `portrait-03.png` (new)

UI:

- `resources/js/app.js`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/dealers/index.blade.php`
- `resources/views/admin/dealers/show.blade.php` (new)
- `resources/views/admin/sales/show.blade.php`
- `resources/views/components/dealer-details.blade.php` (new)
- `resources/views/components/sales-avatar.blade.php` (new)
- `resources/views/components/leaderboard-section.blade.php`
- `resources/views/components/public/ranking-preview.blade.php`
- `resources/views/components/public/stat.blade.php`
- `resources/views/dealers/index.blade.php`, `show.blade.php` (new)
- `resources/views/profile/edit.blade.php`
- `resources/views/welcome.blade.php`

Tests:

- `tests/Feature/AdminDashboardTest.php`
- `tests/Feature/PublicPrivacyTest.php`
- `tests/Feature/DashboardNavigationTest.php` (new)
- `tests/Feature/SalesAvatarTest.php` (new)

## Database and data preservation

Added only nullable `users.avatar_path`; the migration was reviewed with `--pretend` and applied on local Toyota. Before migration, saved an SQL backup at `.laravel-tools/dashboard-details/toyota-before-dashboard.sql` and fingerprints of all 10 tables and 1,001 evidence files. The dedicated avatar seeder filled 50 reserved demo accounts only.

Verified against the backup: all 52 existing accounts retained every original field except permitted `updated_at` changes on the 50 demo accounts; only those demo accounts received an avatar path. All 500 customer submissions, reviews, awards, Dealer assignments, account roles/statuses/emails/passwords and 1,001 evidence files are preserved. Migration history has one additional migration. Verification result: `.laravel-tools/dashboard-details/preservation-results.json`.

## Validation

- PHP 8.5.11 from Laragon used for migration, seeder, Artisan, Pint and PHPUnit.
- Full suite on isolated `toyota_testing_upgrade_20261019`: **537 tests, 11,850 assertions, PASS**, with warning/deprecation failures enabled. Log and JUnit: `.laravel-tools/dashboard-details/full-suite.log` / `full-suite.xml`.
- Coverage includes correct filtered links, private Sales details, public Dealer projection, pagination, Dealer points remaining with the original submission Dealer after transfer, approved-only points, avatar permissions, upload validation/replacement, placeholder and safe/idempotent demo seeding.
- Vite production build passed using Node 20.17.0.
- Chrome/Playwright checked actual server-rendered page fixtures at widths 320, 390 and 1440: 9 pages at each size, 13 navigation clicks, all three avatars loaded, failed-image fallback, upload input, no horizontal overflow and no JavaScript errors. HTTP authorization was verified separately by the Laravel integration tests; the browser fixtures do not bypass or change live routes. Results/screenshots: `.laravel-tools/dashboard-details/browser-results.json`, `top-three-320.png`, `top-three-390.png`, `top-three-1440.png`.
- `git diff --check` passed. PHP formatting checked with Pint.

No unresolved functional failures. Public remains Dealer-only; Sales rankings, details and avatars remain internal.
