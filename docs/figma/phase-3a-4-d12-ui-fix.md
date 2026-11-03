# Phase 3A.4 — D12 lookup UI and state verification

## Cause and actual state evidence

The reported “Đã tìm thấy đăng ký” and community card are explicitly the `found` branch in `dealers.registration.lookup`. They are not the D12 branch. Controller passes the lookup DTO unchanged; there is no controller substitution of `not_requested` with `found`.

Read-only local metadata inspection again confirmed `toyota` lacks both `submission_verification_states` and `submission_verification_audits`. Therefore the Phase 3A.3 schema guard returns neutral `found` for a unique submission match. No Facebook progress can be established from that database. This is not evidence of `not_requested`.

No identifying lookup pair/record was supplied for this task, so the exact customer record was not selected or claimed to have been inspected. No PII was exported and no local data was modified. Isolated fixtures on `toyota_testing` prove both relevant paths:

1. Pending fixture, no workflow row: resolver/controller DTO `found`; neutral view.
2. Same fixture after authorized Admin POST records `group_progress=not_requested` with matching profile evidence/audit: DTO `not_requested`; dedicated D12 view.

`facebook_progress` is a conceptual label; the actual column is `submission_verification_states.group_progress`. The source has no separate progress Eloquent model: `SubmissionVerification` owns the workflow queries/writes.

## Resolver before / after

No resolver business-rule change was warranted. Existing verified mapping already passes: `not_requested` → D12; `requested` → D13; `member` → D14. Null/unknown/no schema/no row/stale profile evidence → neutral. Rejected moderation does not become a membership claim. Supplement request/authorized response and structured blocker retain precedence for D07/D08/D15. No backfill, mutation or inferred Group evidence was introduced.

To establish D12 locally, an operator must first review/apply the existing additive Phase 3A.2 workflow migration on the authorized target; this task did not run it. Then an active Admin opens the submission detail, finds **Trạng thái Facebook Group → Xác nhận trạng thái Group**, selects **Chưa ghi nhận yêu cầu vào Group**, and submits after checking the real Group. The existing POST authorization, CSRF, version and audit checks apply. Lookup phone + plate and clicking the Facebook link do not grant update rights.

## View before / after and Figma

Figma file `yKmWQI6BYJ6dponEnidlsq`, session `5b6a635f-73aa-4a9c-9633-4293f9ea7f31`. Read full design context/text/styles/assets and screenshots for D12 `213:797` and M12 `204:541`.

Before: all nonempty states shared generic result/card markup. The D12 action copy differed from Figma; generic card sizing, padding, avatar/text and mobile action panel did not express the distinct layout.

After: `not_requested` renders only `lookup-waiting-group.blade.php`. Other states retain their existing components. D12 uses:

- Desktop: information area + 420px action area, 32/40/36px information padding, 32px action padding, subtle divider and #fafafb action background.
- Masked identity card, blue “Chờ vào group” badge, completed green registration node, current red Group node, upcoming gray membership node and connecting rails.
- Figma headings/copy: “Bước tiếp: vào group”, “Đã nhận thông tin của bạn”, “Xin vào group và trả lời đúng số điện thoại, biển số.” The desktop action repeats only the caller's submitted bounded phone/plate, not additional database PII.
- CTA “Vào group Facebook”: official configured Group URL `https://www.facebook.com/groups/376631239337942`, real external anchor, `_blank`, `noopener noreferrer`; no write handler.
- Mobile: one white 24px-radius card, 20px padding, 44px avatar, compact badge, divider, vertical timeline and 52px CTA; desktop-only action heading/paragraph omitted from mobile presentation as in M12. Tablet keeps a narrower action column without changing state logic.

Privacy is deliberately preserved: all name parts remain masked; no new owning Dealer attribution or full customer information is disclosed. Existing local assets and CSS node primitives are reused; no temporary asset URL or full-frame image is used at runtime. Shared header/footer are unchanged, including previously documented differences; this change does not certify their complete fidelity. No SLA or new explanatory UI was added.

## Files

- `resources/views/dealers/registration/lookup.blade.php`: explicit D12 branch.
- `resources/views/components/dealer-registration/lookup-waiting-group.blade.php`: dedicated D12/M12 result.
- `resources/css/dealer-registration.css`: D12-only card/timeline/responsive rules.
- `tests/Feature/RegistrationLookupStatesTest.php`: Admin POST → D12 regression and read-only checks.
- This report; generated production assets rebuilt.

## Verification

Tests use `toyota_testing`; a live database-name assertion precedes transaction fixtures. No migrations, seeders, migrate:fresh or real-data modifications.

- **123 tests / 1,366 assertions PASS**: lookup states, lookup, Dealer drafts/final, controlled duplicate approval and business integration.
- Covers requested/member/unknown, supplementation/blockers, ownership, CSRF/throttle, public write rejection, Admin version/authorization, unchanged moderation/points, lookup privacy, D01–D06 and D07–D15 regressions.
- New D12 test uses the actual Admin verification POST, then actual lookup POST. It checks DTO/view/content, official external URL, one CTA, active progress step, absence of neutral result card and unchanged submission/audit count during lookup. Passive external-link markup has no application mutation endpoint; external Facebook behavior was not exercised.
- **63 Blade render checks PASS**.
- `npm run build` **PASS**, 62 modules; Dealer CSS `dealer-registration-d6SKtf9P.css` and existing JS in manifest.
- Pint on changed PHP test **PASS**.

## NOT VERIFIED / outstanding

Actual browser screenshots, geometry, font download, keyboard behavior and visual comparison at 1440/390px: **NOT VERIFIED**. MCP screenshots are reference designs, not rendered application evidence. No pixel-perfect claim.

The exact user's lookup record remains unidentified; no guessed record was edited. Local workflow migration activation remains an operator prerequisite. Unknown results intentionally remain neutral until Admin-backed evidence exists. No commit, push or deploy.
