# Step Two validation UX — 02/11/2026

Removed the Step Two error summary markup, generated count/list and its spacing.
Frontend validation now uses field-specific Vietnamese messages. Blade normalizes
supplied errors to one specific message per field; it does not query duplicates or
create backend rules. A duplicate plate message is rendered only when explicitly
supplied by the caller (for example Laravel `unique` or a duplicate message).
There is still no final Step Two submission endpoint in Phase 2A.

Errors appear only after field interaction, submission, or supplied server errors.
Corrections update that field without clearing other errors or values. Unchanged
server errors survive unrelated edits and resubmission; editing their own value
invalidates the old server result. Client-side code never checks plate uniqueness.
Live regions update only when message text changes, avoiding repeated announcements.
First error receives focus and nearest scrolling; a disabled Ward select focuses
its Province control, and a missing vehicle focuses the existing change-vehicle link.

The existing selected vehicle now has an explicit hidden UI value and its own inline
error/focus link. Preview Step One transfers that value when opening Step Two.
Its IDs are separate from Step One so both preview screens can coexist accessibly.
This does not trust the hidden value server-side or change the Phase 2A draft.

Step Two input errors use a light red border and very pale pink fill. Error text
wraps, fields can shrink in grid/flex, consent text can wrap, and existing mobile
single-column rules are retained. There is no reserved summary gap. ODO uses a
text input with numeric input mode and a digits pattern so invalid text survives
validation; the frontend checks nonnegative safe integers. A supplied invalid year
is preserved as a disabled selected option until the user chooses a valid year.

The shared field component opts into polite/atomic live regions only for Step Two.
The address helper suppresses the extra successful-load “choose Ward” prompt only
inside Step Two, avoiding a duplicate of the inline field message. Address loading
and error/retry handling elsewhere are unchanged.

## Verification

- frontend-check.mjs: PASS, including Step One regression checks.
- step-two-validation-check.mjs: PASS for empty, individual and simultaneous errors,
  valid correction, no initial errors, preserved values, focus/scroll, optional invalid
  ODO, backend duplicate preservation and no client-inferred duplicates.
- DealerRegistrationStepTwoValidationTest: PASS, 3 tests / 63 assertions. Unsaved
  model/view fixtures only; no DB reads, writes or migrations.
- Existing render-check.php: PASS, 31 assertions including backend error normalization,
  no summary, unique field messages, retained values and ARIA.
- Draft storage regression: PASS, 4 tests / 16 assertions.
- npm run build: PASS. Dedicated CSS/JS and the shared address module rebuilt.
- PHP syntax/Pint and JS syntax checks: PASS.

Chrome headless fails with GPU process errors; Edge headless did not return a DOM
and was stopped. No browser screenshot, actual 390px layout measurement, or screen
reader test is claimed. Responsive CSS was inspected, but 390px visual QA remains.
DB-dependent Phase 2A tests were not run against local demo data; this change has
dedicated view feature coverage that does not require the missing toyota_testing schema.

## Files

- resources/views/dealers/registration/step-two-content.blade.php
- resources/views/components/dealer-registration/field.blade.php
- resources/js/dealer-registration.js
- resources/js/customer-address.js
- resources/css/dealer-registration.css
- docs/figma/qa/frontend-check.mjs
- docs/figma/qa/step-two-validation-check.mjs
- docs/figma/qa/render-check.php
- tests/Feature/DealerRegistrationStepTwoValidationTest.php
- docs/figma/step-two-validation-ux.md

No backend draft, session/cache configuration, business routes, duplicate logic,
database/schema, approval or leaderboard changes. No migration, commit, push or deploy.
