# Dealer Account Management

Admin account management lives at `/admin/dealer-accounts`, with canonical
`admin.dealer-accounts.*` route names and dedicated Dealer Account classes/views.
The admin dashboard links to the list. List/search/status/Dealer filters and
pagination exclude admins and archived Sales.

- Active admins create accounts for active Dealers without an existing login.
  Fields: name, normalized unique email, optional phone, Dealer and a confirmed
  initial password. The password is hashed and never displayed or flashed back.
  Creating the account does not replace the admin's authenticated session.
- New accounts are always `dealer/pending`. Role, status, review metadata and IDs
  cannot be supplied by the client. The admin activates the account explicitly.
- Admins activate pending/blocked/rejected accounts, block active accounts, and
  edit name/phone. Repeated or invalid status transitions are rejected. Status
  changes and appended review history commit together under row locks.
- Email, Dealer identity, role and password cannot be edited in the admin account
  form. There is no account delete endpoint. Admin accounts cannot be targets.
- Dealers use their existing `/profile` page for name/phone and password changes.
  Email, Dealer, role/status and target-user IDs stay protected. Current password
  and confirmation are required for a password change. The existing password
  broker/reset flow is unchanged.

## Uniqueness

Backend creation locks the Dealer row and checks for an existing Dealer login in
**any** status. It shares that lock with public registration. A database generated
key and `users_one_dealer_account_unique` from the Dealer-only migration are the
final enforcement, including direct SQL/concurrent writers. Duplicate errors
identify Dealer or email appropriately. Blocking/rejecting an account does not
free its Dealer slot. No new migration or data conversion is needed for this stage.

The application requires the Dealer-only schema; migration has only been exercised
on verified restored copies during these tasks, not applied to `toyota`.

## Tests

With `DEALER_INTEGRITY_MANIFEST` pointing to the verified backup, run:

```text
tests/Feature/DealerAccountManagementTest.php
tests/Feature/DealerAccountCreationRaceTest.php
tests/Feature/AuthenticationTest.php
tests/Feature/LegacyAuthenticationTest.php
tests/Feature/DealerOnlyDatabaseIntegrityTest.php
tests/Unit
```

Tests use new restored schemas; they never reset `toyota` or `toyota_testing`.
The race test starts two PHP workers with independent MySQL transactions and
different active admins: exactly one account is created, the other gets a Dealer
duplicate validation error. Other tests cover all-status uniqueness and raw SQL,
validation, privilege/CSRF protection, activation/block history and rollback,
identity immutability, profile/password/reset regression and listing/escaping.
