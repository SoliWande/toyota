# Dealer Registration Phase 1 — typography audit, 02/11/2026

## Evidence and cause

Read analysis-report.md and phase-1-implementation.md, the dedicated CSS, layout,
Vite config, registration Blade components, legacy form and production manifest.
The existing layout calls @vite for the correct dedicated entries. A production
CSS entry existed before this fix; npm run build also succeeded before editing.
No public/hot file was present. No repository heading rule assigning a serif
family was found in the inspected registration views/styles.

Confirmed defect: Inter/Montserrat were named in CSS but had no local font assets,
@font-face declarations or external font stylesheet. Clients without installed
fonts therefore used Arial. Section headings inherited the body family rather
than declaring it explicitly. Missing fonts alone do NOT explain Times New Roman:
the original body stack already ended in Arial,sans-serif. The reported serif
rendering could not be reproduced in this environment. A live-page CSS/network
inspection is still needed to distinguish stale/missing CSS from external overrides.

## Figma verification

New get_design_context calls succeeded for all twelve frames, using the Figma
design-to-code skill. D01's screenshot was inspected. The read-only use_figma
JavaScript call was rejected because the tool requires approval and policy is never;
get_design_context supplied the typography instead. No design was modified.

| Frame | Node | Families in returned context |
| --- | --- | --- |
| D01 | 209:233 | Inter; Montserrat hero, dates, journey numbers |
| D02 | 211:287 | Inter; Montserrat sidebar numbers |
| D03 | 211:450 | Inter; Montserrat sidebar numbers |
| D04 | 212:377 | Inter |
| D05 | 212:501 | Inter |
| D06 | 212:587 | Inter |
| M01 | 99:376 | Inter; Montserrat hero and dates |
| M02 | 57:452 | Inter |
| M03 | 129:346 | Inter |
| M04 | 57:571 | Inter |
| M05 | 57:664 | Inter |
| M06 | 57:714 | Inter |

Corrected section headings to Inter 800 36/42px, tracking -0.72px; mobile
28/32px, -0.7px. Dealer title explicitly uses 800, desktop 26/32px, -0.26px.
D02/D04 titles use 40/46px, -0.8px; mobile D02 24/30px and D04 26/32.5px.
D06 uses 40/46px desktop and 24/30px mobile. Journey heading and labels,
Montserrat journey numbers, dates (28/34px desktop, 22/28px mobile) and sidebar
numbers were corrected. Input text explicitly uses Inter 400, 16px/1.2.
Existing hero Montserrat 700 72/78px (-1.44px), mobile 38/42px (+0.38px),
Inter 500 labels and Inter 600 buttons are retained.

## Font integration and scope

Only the Dealer Registration layout loads the official Google Fonts CSS2 endpoint:
Inter 400/500/600/700/800 and Montserrat 600/700 with display=swap. No text/subset
restriction is requested, allowing Google Fonts to serve Vietnamese glyph ranges.
Preconnects cover fonts.googleapis.com and fonts.gstatic.com. Both font families
are SIL OFL 1.1, verified against the official Google Fonts repository:

- https://raw.githubusercontent.com/google/fonts/main/ofl/inter/OFL.txt
- https://raw.githubusercontent.com/google/fonts/main/ofl/montserrat/OFL.txt

**Self-hosting remains incomplete.** Attempts to fetch the Google Fonts CSS via
PowerShell and the npm registry via Node failed (connection denied/EACCES).
No font binary was downloaded, copied from an unlicensed source or embedded in
the production bundle. Runtime font delivery currently requires access to Google
Fonts. When unavailable, the explicit Arial,sans-serif fallback applies.

All added family rules use .dr-site. Headings h1–h6, buttons, inputs, selects,
textareas, labels and legends now explicitly use Inter,Arial,sans-serif; component
rules override specific Montserrat positions. Body retains its existing stack.
Control inheritance no longer uses a font shorthand that would erase the family.
Admin/Sales layouts do not load the new font links. Vite config already had the
correct entries and needed no change. No business routes, backend, database,
migrations or registration logic changed. No commit, push or deploy.

## Validation and remaining limits

- npm run build: PASS, Vite 6.4.3, 62 modules, 1.21s.
- New production CSS: public/build/assets/dealer-registration-DglHuNwB.css,
  60.75 kB, referenced by public/build/manifest.json.
- app-bcS3SntT.css, app-BkPPOm6w.js and registration JS hashes are unchanged.
- php docs/figma/qa/render-check.php: PASS, 14 assertions.
- Existing frontend-check.mjs: PASS.
- Chrome headless launch failed with GPU-process crashes; no computed family,
  actual rendered font, font request success or browser visual diff is claimed.
- Exact live cause of the reported serif headings remains unverified. Full
  typography parity, Vietnamese rendering and mobile reflow need browser QA.
- CSS is bundled; font binaries are externally served and are NOT bundled.

Files edited: resources/css/dealer-registration.css,
resources/views/components/dealer-registration/layout.blade.php and this report.
Ignored public/build artifacts were regenerated by Vite.
