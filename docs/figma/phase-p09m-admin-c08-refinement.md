# P09M — C08 V1 UI Refinement

## Mục tiêu

Chốt Desktop C08 trong phạm vi Figma hiện có, cảnh báo V1 được duyệt và hành vi an toàn của các trạng thái thiếu frame; giữ nguyên API/privacy/C1/C2.

## Trạng thái

DONE phạm vi UI V1 được yêu cầu — 2026-11-12: **18 tests/306 assertions PASS**, không SKIPPED/FAIL; build và browser Desktop1440 PASS. Không tuyên bố pixel-perfect các variant thiếu frame.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09L/P09J-B/P09K, kiểm tra source Blade/CSS/JS/backend/tests và working tree có P09J-B/K/L chưa commit. Giữ nguyên lịch sử/edits trước đó. Đọc lại context và ảnh Main165:832, tabs173:1809, toast173:2097 bằng skill figma-design-to-code, session4f3d8db8-e1d0-4440-8c43-700f0ac45ec3. Backend/privacy đã có; thiếu nghiệm thu browser no-batch/empty/due/expiry ở P09L.

## Các file source code liên quan

- Sửa `resources/views/admin/call-audits/index.blade.php`, `resources/css/admin-call-audits.css`, `resources/js/admin-call-audits.js`.
- Sửa `tests/Feature/AdminCallAuditUiTest.php`, `tests/Browser/admin-call-audits.mjs`; mới `tests/Browser/admin-call-audits-refinement.mjs`, `tests/Fixtures/call_audit_refinement_snapshot.php` và tracking này.
- Không sửa controllers/requests/routes/policies/models/services/schema/C1/C2 hoặc tài liệu P09L/J/K; giữ các edits có sẵn của phase trước.

## Những thay đổi đã thực hiện

Tạo tracking trước sửa; áp dụng cảnh báo V1 vào đúng hộp warning hiện hữu khi chọn hoặc xem Wrong/finding_pending. Correct/no-answer và lần gọi mới vẫn dùng nguyên văn phần no-answer Figma, không báo khách chưa khớp khi chưa có kết quả Wrong. Reset/open/read-only đều cập nhật warning đúng outcome, không POST/đổi kết quả khi đổi cảnh báo.

Sửa sai lệch có bằng chứng rõ: node165:995/1002 dùng marker xanh6×6px cho tỷ lệ0% có mẫu số; CSS cũ co width về0 nên mất marker. Chỉ thêm min-width6px với data-zero của tỷ lệ0 thật, không tạo marker hoặc “0 sai” cho mẫu số0/null. Không đổi biểu thức counts/tỷ lệ C2, icon/label hoặc layout.

Bổ sung browser states no-batch/zero/underfill/due/future/expiry/denied/second-no-answer và ảnh Wrong/No-answer/finding_pending. Dùng chung runner/guard/cleanup P09L để không nhân route hoặc auth harness. Không đổi chức năng/API/runtime fixtures.

## Quyết định kỹ thuật quan trọng

Câu V1 “Thông tin khách hàng chưa khớp. Kết quả sẽ được ghi nhận để kiểm tra và xử lý.” chỉ hiện khi chọn/đọc Wrong; không khẳng định khách sai khi chọn Correct/no-answer. Không thêm UI mới cho empty/error/expired/last. Không thay label hoặc mapping C1.

Quyết định V1 của P09M thay thế blocker warning tại P09L; giữ nguyên tài liệu lịch sử, không thêm confirmation/penalty/huỷ xác minh. Các trạng thái thiếu frame dùng hành vi an toàn đã được yêu cầu ở mục6 của task, không còn là blocker để chốt V1. Nếu yêu cầu nghiệm thu pixel-perfect riêng các variant đó, vẫn cần frame trước khi đổi màu/icon/wording hoặc thêm feedback.

Clock trong runtime giữ nguyên. Fixtures chuẩn bị một no-answer lịch sử đã quá24h và một no-answer hiện tại qua actions C1 với clock test; PHP server browser vẫn dùng FreezeClock thật. Client clock fast-forward chỉ kiểm timer che phone/đóng workspace sau lease, không được coi là bằng chứng thời gian server đã trôi600s. POST403/503 được inject riêng để kiểm fault handling; mọi save thành công đều qua Laravel/CSRF/policy/C1 và commit thật, không mock success/counts.

## Kết quả kiểm thử

| Kiểm tra mới | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_refinement_snapshot.php before` | PASS exact127.0.0.1:3306/toyota_testing/TCP, MySQL8.0.30, FK checks1; audit4 bảng InnoDB/0 rows; snapshot19 bảng và215 backend PHP/migrations, không loại trừ policy/contact/C1/C2 |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditUiTest.php tests/Feature/AdminShellTest.php --no-progress --display-skipped` | **18 PASS/306 assertions**,0 skipped/fail, gồm2 browser cases. `CALL_AUDIT_ARTIFACT_DIR=.laravel-tools/p09m-browser`; không full regression |
| Syntax/Pint/JS | `php -l`2 PHP test/fixture +Blade PASS; `php vendor/bin/pint --test`2 PHP PASS; `node --check` runtime JS và2 browser scripts PASS |
| `npm run build` | PASS Vite88 modules, không cài thêm dependency |
| `php tests/Fixtures/call_audit_refinement_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false; mọi fixture journal cleaned=true; QA server/port8776 đã dừng. AUTO_INCREMENT counters không reset, schema hash chỉ bỏ counter |
| Diff/tracking | git diff --check và file mới/9 mục tracking kiểm riêng; chỉ8 file thuộc P09M được sửa/tạo, không quy edits P09L/J/K sang task này |

Browser Chrome headless1440×1046: no auto claim/record; masking/full-phone/claim-release/CSRF/idempotency; giữ response sau commit để kiểm toast chưa hiện sớm; retry503 cùng payload/key; Wrong→finding_pending và warning V1; no-answer1 và lần2; next thật/last không fake toast; readonly completed/finding; no-batch0 tabs, zero0/0, underfill2/5 không bù mẫu; future recall disabled/masked, due recall nhận claim và reset câu trả lời trước gọi mới; expiry UI che phone, không auto renew; denied403 đóng workspace/che phone/no toast. Không có dialog mới, overflow/pageerror hoặc local/session storage secrets. Bổ sung kiểm native rendered geometry của mọi glyph đang visible, marker0%6×6px, split420/692, card x716/y236/w692/h762 (sai lệch≤1px), active tabs13px500/600 và count12px đúng Figma.

Đã xem trực tiếp ảnh Wrong/V1, recall-due, completed-no-answer2 và đối chiếu với context/ảnh Figma mới. Evidence: [Correct](../../.laravel-tools/p09m-browser/c08-calling-1440.png), [Wrong](../../.laravel-tools/p09m-browser/c08-wrong-1440.png), [No-answer](../../.laravel-tools/p09m-browser/c08-no-answer-1440.png), [finding](../../.laravel-tools/p09m-browser/c08-finding-pending-1440.png), [toast](../../.laravel-tools/p09m-browser/c08-success-1440.png), [edge states/checks](../../.laravel-tools/p09m-edges/checks.json). Ảnh no-batch/zero/underfill/expiry/denied/recall-future/recall-due/completed-no-answer2 nằm cùng thư mục edge checks. Dữ liệu ảnh là fixtures DB thật, không runtime production hoặc bằng chứng gọi khách ngoài đời. Mỗi run ID/tag journal+cleanup kế thừa A3/P09L; không migrate/seed/reset/RefreshDatabase/DatabaseMigrations/production. Snapshot private `storage/app/private/p09m-call-audit-claim/{before,after}.json`.

## Những lỗi còn tồn tại

| Phân loại | Kết luận V1 |
|---|---|
| Match có bằng chứng Figma | Main shell/content spacing, heading/progress, split/list/workspace dimensions, typography/control sizes, Correct selected và các inactive controls, native glyph slots, note64px/save40px, marker0%,3 active tab variants và toast nguyên văn173:2097 sau commit/next thật |
| Ngoại lệ đã duyệt | Warning Wrong dùng đúng câu V1 trong slot cũ; không huỷ xác minh/trừ điểm hoặc hứa thao tác đã xảy ra |
| Chỉ safe behavior, thiếu frame riêng | No batch/zero/underfill, retry future/due, readonly completed/no-answer2, failure/conflict/expiry/last item: blank/hidden/disabled hoặc data thật như đã kiểm; không modal/badge/message mới. Không gọi đây là match pixel-perfect |
| BLOCKED thực sự nếu yêu cầu thiết kế thêm | Visual selected Wrong/no-answer, feedback cụ thể cho lỗi/empty/expiry/last, confirmation/effect/correction workflows chưa có frame/contract. Chưa được đổi style/wording hoặc tạo interaction mới cho các nhánh đó; **không chặn phạm vi V1 safe đã được task cho phép** |

Không còn FAIL hoặc BLOCKED ngăn chốt V1 hiện tại. Không thay màu selected Wrong/no-answer theo suy đoán; giữ controls P09L và chỉ chứng minh mapping/readonly/safe behavior. Không tạo Admin Mobile; account/logout sai khác P01 ngoài scope vẫn giữ. Lần kiểm mới đầu có assertion Playwright isDisabled không áp đúng semantics fieldset; đã sửa kiểm DOM fieldset.disabled và nút Save, không sửa backend để làm test pass.

## Task hoặc bước tiếp theo

Có thể review/chốt C08 Desktop V1 trong các giới hạn trên. Muốn feedback/visual variants đầy đủ thì bổ sung đúng frame rồi giao refinement riêng. Finding processing/correction/penalty/huỷ xác minh/công bố C07 vẫn ngoài phạm vi, không phát sinh từ V1 UI này. Không coi tracking là approval deploy production.
