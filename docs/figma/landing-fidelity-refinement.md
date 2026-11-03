# Dealer landing — removal of extra UI

## Reference

Figma MCP design context and screenshots inspected for D01 `209:233` and M01 `99:376`, file `yKmWQI6BYJ6dponEnidlsq`. These references contain no legacy registration accordion, Dealer achievements accordion, or Step One draft-storage instructions. The supplied M01 node renders the mobile hero and sticky CTA; this is not evidence of every mobile section.

## Changes

- Removed the Blade accordion “Gửi đăng ký với đại lý bằng biểu mẫu hiện tại”, including its second form.
- Removed “Hồ sơ và thành tích đại lý”, its score, leaderboard link and profile-media include from the landing page. The normal referring Dealer card remains.
- Removed the always-visible 30-minute draft instruction and obsolete noscript fallback instruction. Removed the form's corresponding obsolete aria-describedby reference; field-level accessibility remains.
- Removed the developer-written campaign-date placeholder paragraph. The existing configured date card renders only when both dates exist; no prototype dates were promoted into official business configuration.
- Removed CSS used exclusively by the two deleted panels and JavaScript that opened/copied data into the deleted fallback form.

No endpoint, backend, consent, database, draft TTL, approval or leaderboard behavior changed. Legacy POST remains available for compatibility and is covered by regression tests. Server-side Step One POST works without JavaScript. Runtime expiry feedback, field validation, D05 duplicate state, D06 inactive state, CSRF and honeypot remain.

## Files

- `resources/views/dealers/registration/landing-content.blade.php`
- `resources/views/components/dealer-registration/step-one.blade.php`
- `resources/css/dealer-registration.css`
- `resources/js/dealer-registration.js`
- `tests/Feature/DealerRegistrationDraftTest.php`
- `docs/figma/qa/render-check.php`
- Generated `public/build` assets and manifest from the production build.

## Verification

- Verified actual database name `toyota_testing` before running database tests. Transaction fixtures; no migration or cleanup run.
- PHPUnit: **110 tests / 1,381 assertions PASS** across Dealer draft/final/Step Two validation/success, business integration, lookup/state and campaign-rules suites.
- Blade render contract checks: **63 PASS**, including absence of added panels, a single real Step One POST, CSRF/honeypot, D05/D06, and D09/lookup regression.
- `npm run build`: **PASS**, 62 modules. Dealer CSS and JS included in production manifest.
- Pint for changed PHP test/check files: **PASS**. JavaScript syntax: **PASS**.

## Remaining differences / NOT VERIFIED

- Browser screenshots, actual font loading, layout geometry and overflow at 390/768/1440px: **NOT VERIFIED**. No claim of pixel-perfect fidelity.
- This change removes confirmed added panels; it does not certify all pre-existing page copy or geometry. Existing summary/FAQ copy still differs from Figma. In particular, Figma's 48-hour review promise remains omitted under the earlier explicit business restriction; no SLA was introduced in this task. Exact copy reconciliation needs to respect that restriction.
- Missing campaign dates and member counts do not receive invented values. Operator must confirm official dates/count data separately.
- No commit, push or deploy performed. No historical data modified.
