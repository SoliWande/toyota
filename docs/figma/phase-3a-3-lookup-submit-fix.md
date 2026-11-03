# Phase 3A.3 — Lookup submission fix

## Root cause and evidence

Read the Phase 3A/3A.2 reports and relevant Phase 2B/2C release context, then traced routes, request validation, controller, canonical lookup, resolver, workflow service, middleware, exception handler and Blade response.

Two independent defects explain the reported page:

1. **Schema prerequisite absent locally.** Read-only metadata inspection of the configured local connection confirmed database `toyota` has `customer_submissions` and receipts, but lacks both `submission_verification_states` and `submission_verification_audits`. Its migration ledger has not applied `2026_11_03_000024_add_submission_verification_workflow`. Existing local log entries at 06:04 and 07:18–07:19 UTC on 2026-11-03 record SQLSTATE `42S02`, driver code `1146` (missing table). The resolver called `SubmissionVerification::state()` unconditionally for a matched submission, querying the missing table. No customer rows or PII were printed during inspection. No local migration or database write was performed.
2. **Wrong exception view.** `App\Exceptions\Handler` explicitly rendered `errors.dealer-registration` for every lookup exception. That view hardcodes “Chưa thể gửi đăng ký” and “Quay lại đăng ký”, even though the handler supplies a lookup-specific message. The underlying exception was therefore presented as an intake failure.

The reports already noted the workflow migration was applied only to `toyota_testing`, not local `toyota`. Successful test runs did not establish local schema readiness.

## Request trace

- GET `/{slug}/registrations/lookup`: `dealers.lookup.show` → `RegistrationLookupController::show` → lookup Blade.
- POST same path: `dealers.lookup.store` → `RegistrationLookupRequest` → `RegistrationLookupController::store` → `RegistrationLookup::find` → `RegistrationLookupStateResolver` for one unambiguous match → lookup Blade.
- The form already uses POST and the correct named action; routes precede the slug catch-all. No duplicate route/action or draft middleware misrouting was found.
- Validation renders bounded values and field errors directly with HTTP 422; it bypasses generic exception rendering via `HttpResponseException`.
- No match/ambiguous returns D10. Own complete draft may return D11. Workflow evidence determines D07/D08/D12–D15; moderation alone never establishes Group membership.

## Changes

- Resolver checks both additive workflow tables before using workflow evidence. If either is absent, it returns the existing masked neutral `found` DTO and emits a fixed operational warning without PII. It does not infer membership, fabricate a request, catch arbitrary database failures or report a system error as D10.
- Lookup exception rendering now reuses the existing lookup form and its inline error region, preserving HTTP status, Retry-After and private/no-store/noindex/no-referrer headers. No registration-error heading/CTA, new result state, banner or layout was added. JSON responses keep their existing contract. Unknown Dealers use the framework error renderer.
- The existing lookup error region displays the escaped message provided by the handler, retaining the existing validation/honeypot fallback.
- No changes to routes, draft/session storage, submission records, approval, Facebook progress, attribution, demo isolation, leaderboard or migrations.

Direct POST rendering is retained deliberately: no redirect is needed for read-only lookup; no PII is flashed, saved as a public result, or placed in a URL. Refresh may repeat the read-only POST and consumes the normal throttle quota; it does not create/update a submission. Supplementation retains its separate existing PRG workflow.

## Files changed

- `app/Services/RegistrationLookupStateResolver.php`
- `app/Exceptions/Handler.php`
- `resources/views/dealers/registration/lookup.blade.php`
- `tests/Feature/RegistrationLookupTest.php`
- `tests/Feature/RegistrationLookupStatesTest.php`
- This report. Production assets rebuilt; no CSS/JS source change was required.

## Tests

Database tests use `toyota_testing`, with a live `SELECT DATABASE()` assertion before transaction fixtures. No migration, RefreshDatabase, migrate:fresh, seed or historical-data change was performed.

- **132 tests / 1,448 assertions PASS**: lookup, lookup states, Dealer draft/final/success, controlled approval, business integration and demo isolation.
- New tests cover each missing workflow table separately; neutral masked response, unchanged submission, absence of registration-error UI, and real exceptions from lookup query and state resolution returning the actual lookup view with HTTP 500 and no false result/trace.
- GET, missing/wrong pair/D10, validation and workflow-state tests assert rendered content/view. Existing coverage verifies pending/approved/rejected, Group progress, D07/D08 ownership, D11, structured blockers, ambiguity, inactive Dealer, CSRF missing/wrong/valid tokens, IP/identity quotas, privacy, no mutation, approval and leaderboard regression.
- **63 Blade render checks PASS**, covering D01–D06 and lookup states.
- `npm run build`: **PASS**, 62 modules.
- Pint on changed PHP files: **PASS**.
- Lookup frontend harness: run with Node 18's required `--experimental-specifier-resolution=node` flag. The initial invocation without this flag failed extension resolution; no application defect was inferred from that invocation.

## Browser QA and outstanding readiness

Desktop/mobile browser automation: **NOT VERIFIED**. HTTP feature requests and DOM/render harnesses are not browser screenshots or visual acceptance. No pixel-perfect claim.

The schema fallback makes basic lookup available during an incomplete local/rolling upgrade. It does **not** enable manual verification/supplementation features without their schema. Operator must review and apply the existing additive workflow migration on an authorized target before using Phase 3A.2 Admin/customer workflows. This task did not apply it to `toyota` or production. Table existence checks do not validate every column/index or repair a partially applied schema; other genuine failures remain HTTP errors.

No commit, push, deploy or real-data modification.
