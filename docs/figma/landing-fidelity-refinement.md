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

## Cập nhật UI thời gian áp dụng — 13/11/2026
IN PROGRESS. Yêu cầu mới chỉ định hiển thị cố định `01.11.2026`/`31.01.2027` trong section Thể lệ có ba accordion (D01/M01), thay quyết định cũ ẩn khi thiếu config trong phạm vi UI này; không đổi config/backend hay trang thể lệ đầy đủ D09. Đã đọc Figma `209:382`/`141:380`, session `b904b75b-f46d-4571-8271-71acd69ed9b4`: desktop588×138, mobile358×126, gradient/spacing/icon/calendar/line. Giữ nguyên toàn bộ nội dung accordion. Baseline source lưu ignored `.laravel-tools/rules-dates/before/`; chưa có kết quả kiểm tra mới tại thời điểm ghi.

### Kết quả cập nhật cùng ngày

- Đã bổ sung block ngay dưới tiêu đề Thể lệ và trên ba accordion: label, icon lịch xuất trực tiếp từ Figma, Bắt đầu `01.11.2026`, Kết thúc `31.01.2027`, đường chấm, gradient đỏ và radius24. Ngày là nội dung UI theo yêu cầu mới, không phải thay đổi thời hạn nghiệp vụ hoặc config.
- Source thay đổi: `resources/views/dealers/registration/landing-content.blade.php`, `resources/css/dealer-registration.css`; asset mới `public/dealer-registration/rules-calendar.png`. CSS giới hạn trong `#dr-rules`. Đối chiếu baseline xác nhận toàn bộ Blade ngoài block ngày và toàn bộ markup/nội dung accordion giữ nguyên; backend, trang khác không thay đổi trong task này.
- `npm run build`: PASS. `php -l resources/views/dealers/registration/landing-content.blade.php`: PASS. `vendor/bin/pint --test resources/views/dealers/registration/landing-content.blade.php`: PASS. Kiểm tra 63 tham chiếu asset manifest tồn tại, không có `public/hot`: PASS.
- Render Blade Laravel qua `.laravel-tools/rules-dates-render.php` với dealer trong bộ nhớ, DB driver vô hiệu hóa, session/cache array. Chrome thật qua Playwright `.laravel-tools/rules-dates/browser.mjs`: PASS tại1440/390/320; block lần lượt588×138/358×126/288×126; font, padding, thứ tự/ngày chính xác; ba accordion vẫn đóng/mở; không overflow, lỗi JS, asset thiếu hoặc request ghi dữ liệu. Đây là kiểm tra UI với fixture cách ly, không phải E2E backend hay kiểm thử dữ liệu production. Không chạy feature test/DDL/seed/reset/deploy.
- Ảnh thực tế: `.laravel-tools/rules-dates/after/rules-1440.png`, `rules-390.png` và các ảnh crop `dates-1440.png`/`dates-390.png`; số đo trong `checks.json`. Đã xem ảnh browser desktop/mobile và đối chiếu trực tiếp ảnh Figma desktop209:382. Mobile141:380 đã đọc design context đầy đủ và đối chiếu thông số, nhưng Figma screenshot xuất ra chỉ1px do node bị clip trong frame; thử isolation vẫn bị clip. Vì vậy chưa xác minh hoàn toàn bằng ảnh Figma mobile; không tuyên bố khớp100% hoặc DONE nghiệm thu thị giác. Trạng thái IN PROGRESS chỉ còn giới hạn này; cần bản export mobile không bị clip để chốt đối chiếu ảnh cuối.
