# Customer Submission: Dealer-only

This change uses the Dealer-only schema described in `DEALER_ONLY_DATABASE.md`.
It does not run a migration, rewrite legacy rows, backfill ownership, or move evidence files.

- Active Dealers use `/dealer/submissions` to create, list, search and view submissions belonging to their authenticated `dealer_id`.
- Creation locks and reloads the authenticated account, checks its active Dealer role, and records its `created_by_user_id` and `dealer_id`. Frontend owner, status, review, evidence-path and timestamp fields are ignored.
- Updates and deletes reload and lock both the account and submission before checking ownership and pending status. Approved and rejected submissions remain read-only. Existing throttling, input validation and private evidence access remain in place.
- Edits preserve historical ownership and `submitted_at`. The model also rejects attempts to change `dealer_id`, `sales_id`, `created_by_user_id` or `submitted_at`.
- A verified historical `dealer_id` controls Dealer access even for legacy submissions. A NULL historical dealer stays unknown and grants no Dealer access; the archived Sales account's current dealer is never used as a fallback.
- Admin moderation displays the historical Dealer and either the Dealer creator or archived Sales reference. Sales references are retained for audit, without a Sales filter or login dependency.
- Approval/rejection, review history and duplicate detection retain their existing rules. A normalized Facebook profile can be approved only once across all Dealers. The unique database index remains the final concurrency guard.
- Evidence bytes and paths stay unchanged unless a Dealer explicitly replaces an image or deletes a pending submission under the existing rules. A failed database write cleans up only the new image; the previous image is removed after a successful write.

## Validation

`DealerSubmissionTest` covers forged ownership/system fields, scoped search and pagination, cross-Dealer access, pending edits/deletes, review states, moderation history/rollback, global duplicates, evidence cleanup, stale authorization, immutable attribution and preservation of legacy rows.

`SubmissionApprovalRaceTest` runs two independent MySQL transactions against the same normalized profile and verifies one approval with exactly one review. It restores a verified backup into a uniquely named test database; its worker refuses any database outside that test namespace.

Run these tests with `DEALER_INTEGRITY_MANIFEST` pointing to the verified backup manifest. The test helpers verify the backup before restoring and migrating isolated test databases. They do not use `RefreshDatabase`, `migrate:fresh` or `db:wipe`.
