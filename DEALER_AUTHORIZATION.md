# Dealer authentication and authorization stage

Login roles are `admin` and `dealer`. Sales data remains audit-only.
Deploy this application stage with the verified Dealer-only database migration;
the development `toyota` database has not been migrated by these tasks.

## Access rules

- A dedicated Eloquent authentication provider restricts credential, session,
  remember-token and password-broker lookups to admins and Dealer accounts with
  an existing `dealer_id`. Legacy Sales rows are rejected even before archival.
- `account.login` rechecks the persisted account before protected web/API requests,
  including Sanctum bearer tokens. `account.active` blocks pending/rejected/blocked
  accounts from business endpoints while allowing status/logout. Password reset
  never activates an account or changes its identity.
- Registration creates a pending Dealer, serializes by Dealer row lock and handles
  the database unique constraint on all statuses. It cannot create a second login
  for an occupied Dealer. Admin approval remains required.
- Dealer self-service accepts name/phone and a separately validated password change.
  Email, Dealer, role, status and target-user identifiers are rejected, including
  explicitly supplied NULL values. Admin account edit also cannot transfer the
  Dealer identity. Account moderation remains an authorized transactional action.
- Submission policies and collection queries use the historical submission
  `dealer_id`, never creator ID or the current Dealer of a legacy Sales account.
  NULL attribution grants no Dealer access. Private evidence uses the same policy.
  Pending-only edit/delete and admin-only moderation remain enforced.
- New submissions take their creator and Dealer from the locked session account;
  request-supplied attribution is ignored. Historical Sales relationships use a
  read-only `LegacySalesAccount` model that cannot authenticate.

## Routes and limited compatibility

- Dealer business routes are `/dealer/*`; login redirects to `dealer.dashboard`.
  The dashboard now shows Dealer-scoped counts, recent submissions, period scores
  and global Dealer ranks, with links to submission creation and profile.
- Account management URLs are `/admin/dealer-accounts/*`, using canonical
  `admin.dealer-accounts.*` routes and DealerAccount controller/request/action
  names. Old `/sales/*` and `/admin/sales/*` URLs are not registered.
- `UserRole::cases()` contains exactly admin/dealer, and `tryFrom('sales')` is NULL.
  `UserRole::Sales` is a compatibility **constant** pointing to a separate audit
  enum, not a login enum case. It cannot be cast as a User login role.
- Subsequent reporting and UI stages use Dealer-only rankings and new awards.
  Published Sales award details and submission audit references remain available
  as historical records. Current menus and landing-page rankings use Dealers.
  See `DEALER_LEADERBOARD_AWARDS.md` for publication and scoring rules.

## Verification

Use `DEALER_INTEGRITY_MANIFEST` pointing to the restored/verified backup described
in `DEALER_ONLY_DATABASE.md`, then run PHPUnit with:

```text
tests/Feature/AuthenticationTest.php
tests/Feature/LegacyAuthenticationTest.php
tests/Feature/DealerOnlyDatabaseIntegrityTest.php
tests/Unit
```

Authentication tests migrate only a NEW restored schema, isolate fixtures in
transactions, and never reset `toyota` or `toyota_testing`. Legacy tests verify
rejection against a pre-migration copy as well, including a correct password and
a real Sanctum token. No destructive Laravel database-testing helpers are used.
Unrelated legacy Feature tests still describe the old Sales workflows and must be
updated in the relevant future stages; they were not run through RefreshDatabase.
