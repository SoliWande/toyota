# P09L — Admin C08 UI theo Figma

## Mục tiêu

C08 Desktop1440 theo Figma, dữ liệu C2 và POST C1 thật; workspace contact tối thiểu có claim/quyền/source drift, không ảnh hưởng điểm/approval/Awards.

## Trạng thái

BLOCKED một phần về thiết kế — 2026-11-12. Luồng UI/API Desktop đã triển khai và PASS tests/build/browser thật; chưa thể nghiệm thu “tuyệt đối Figma” cho warning mâu thuẫn C1 và các variant chưa có thiết kế. **139 tests/1.711 assertions PASS** trong suite liên quan; refinement cuối UI riêng **13 tests/103 assertions PASS**, không SKIPPED/FAIL. Không cộng kết quả lặp thành số tests mới.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, riêng C08 inventory, P09H/P09J-B/P09K, source shell/read/write/tests và skill Figma design-to-code. Đã đọc context/ảnh C08 165:832, tabs173:1809, toast173:2097 trong session1fa33736-7b10-486c-ac8f-022ed9661b03. Working tree có P09J-B/P09K chưa commit, giữ nguyên. Sidebar C08 disabled; API C2/C1 có sẵn, chưa có page/contact disclosure.

## Các file source code liên quan

- Mới `app/Http/Controllers/Admin/CallAuditWorkspaceController.php`, `app/Http/Requests/Admin/CallAuditWorkspaceRequest.php`, `app/Services/CallAudit/WorkspaceReadModel.php`.
- Sửa `app/Policies/CallAuditBatchPolicy.php` và `tests/Unit/CallAuditBatchPolicyTest.php`: capability tối thiểu viewCallContact riêng, viewSensitiveData/reproduce/correction/penalty/publish vẫn deny.
- Sửa `routes/web.php`, `resources/views/components/admin/layout.blade.php`, `sidebar.blade.php`, `vite.config.js`: nối page/navigation/assets đúng C08; giữ edits P09J-B đã có.
- Mới `resources/views/admin/call-audits/index.blade.php`, `resources/css/admin-call-audits.css`, `resources/js/admin-call-audits.js`; sáu PNG glyph gốc trong `public/admin/c08/`.
- Mới `tests/Feature/AdminCallAuditUiTest.php`, `tests/Browser/admin-call-audits.mjs`, `tests/Fixtures/call_audit_ui_router.php`, `call_audit_ui_snapshot.php`; tracking này. Không sửa source A1/A2/A3/C1/C2/scoring, models/schema/migrations, các page khác hoặc dữ liệu production.

## Những thay đổi đã thực hiện

Trang `GET /admin/call-audits/workspace` (`admin.call-audits.workspace`) và sidebar active. Heading/progress/ba tab/list/Dealer card/workspace/questions/outcomes/note/save theo context Figma; dùng shell/badge/toast styles P01, không sửa component primitives. UI gọi bốn GET C2 và POST claim/release/record C1 hiện hữu, không tạo sample hoặc sửa C1 mapping. Chọn tháng qua query month optional nếu cần deep link; mặc định batch general_audit frozen mới nhất; Figma không có month selector nên không thêm control. C08 không trộn award_top3, backend contact vẫn kiểm đúng purpose/parent/item.

Thêm POST read-only `/admin/call-audits/{batch}/items/{item}/workspace`, name `admin.call-audits.workspace-details`, CSRF/auth/session/active Admin/policy/throttle như các actions Admin. Preview không claim hoặc trả full phone. Nút phone Figma mới bắt đầu claim và đọc contact theo đúng token/owner/version/lease/source; full phone chỉ trong workspace, không list/query/URL/tel link/log/storage. Thao tác gọi là thủ công từ số đã được phép đọc, không tích hợp tổng đài hoặc tự gọi ngoài ứng dụng. Full phone xóa khỏi workspace khi rời/chuyển/hết lease/lỗi quyền; claim không heartbeat/gia hạn.

Workspace render tên, model/year/plate/Dealer từ frozen snapshots có fingerprint; ba câu hỏi/script dùng dữ liệu thật, không names/phone/plate mẫu Figma. Đã xác minh/date chỉ hiện khi SubmissionVerification có member evidence/profile hiện hành và audit thật; không đổi approved thành group verified. Latest outcome/ba answers đọc từ attempt thật để hiển thị readonly ở tab đã xử lý; không trả notes/Facebook/audit payload. Bắt đầu cuộc gọi mới reset toàn bộ câu trả lời/kết quả, không lấy no-answer lần trước làm câu trả lời lần2.

## Quyết định kỹ thuật quan trọng

Không auto claim khi load/chọn tab/row. Green phone control bắt đầu claim/contact, không heartbeat. Full phone chỉ workspace khi token/owner/version/lease/source hợp lệ, không list/URL/log. Không đổi mapping/outcome/điểm/verification.

Figma warning nói huỷ xác minh/trừ1điểm không đúng C1; câu này BLOCKED vì không được biến thành thông báo sai hoặc sửa nghiệp vụ. Chỉ phần nguyên văn no-answer phù hợp được dùng. Missing empty/error/expired/last-item/correction/confirmation variants ghi riêng, không sáng tạo wording/modal/UI. Toast đúng chỉ khi Correct commit và có next item thật; không nói chuyển tiếp nếu không còn item.

- Contact reader lấy actor fresh từ Auth, shared-lock user/batch/item/source/Dealer trong read transaction để kiểm quyền/lease/fingerprint trước disclosure, không writes. Wrong key/owner/version/expired/source drift/unknown snapshots trả409 safe; guest401/nonadmin/inactive403, child scope404. Kiểm cả frozen snapshot fingerprint và source live; không tự cập nhật PII. Policy contact không thay capability raw sensitive data; không serialization Eloquent.
- UI chọn row/tab chỉ đọc preview; không auto claim/record hoặc đặt đáp án mẫu. Claim key/token/record intent/release key giữ trong bộ nhớ. CSPRNG dùng randomUUID hoặc getRandomValues khi HTTP local không có randomUUID, không Math.random. Retry record sau503 giữ nguyên payload/key, khóa sửa dữ liệu trong lúc kết quả chưa rõ; không nhân attempts. Chặn submit lặp, validate đủ ba câu trên client và C1 server. No-answer reset ba null. Disabled navigation trong lúc request để không bỏ qua thao tác do race refresh. Không POST khi chuyển radio/tab để ghi kết quả; tab có claim thì chỉ release chính lease đang giữ.
- Lease600s dùng expiry server/date response cho timer giao diện; server vẫn là quyết định cuối. Không heartbeat/renew. Gọi lại chưa tới hạn không được start; đúng hạn được backend cấp claim (HTTP test), khi chọn lại row/tab luôn đọc C2 mới. Không tự dời retry, không tự nhận khách kế tiếp sau lưu.
- Toast 173:2097 nguyên văn chỉ khi Correct POST thành công và next item thật đã được đọc/hiển thị. Wrong/no-answer chỉ cập nhật counts/list, không dùng toast Đúng. Last item không next thì đóng workspace, không toast “chuyển sang khách tiếp theo”. Không tạo error/success modal/banners/wording ngoài Figma.
- Assets: curl Figma asset bị sandbox chặn kết nối. Đã lấy native node exports qua get_screenshot inline đúng glyph phone165:1009, tick-white165:1023, cross165:1027, tick165:1051, info165:1075 và toast-tick173:2099, lưu6 PNG14/16px local, không redraw hoặc dùng screenshot cả màn làm UI. Không có temporary Figma URLs runtime. Code Connect không có mapping phải thay component. Dùng skill [figma-design-to-code](skill://figma/figma-design-to-code/SKILL.md); session/frame bằng chứng như trên.

## Kết quả kiểm thử

| Kiểm tra mới | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_ui_snapshot.php before` | PASS exact127.0.0.1:3306/toyota_testing/TCP, MySQL8.0.30, fk_checks1;4 bảng audit tồn tại InnoDB/0 rows; snapshot19 bảng, hash211 source/migrations được bảo vệ |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditUiTest.php tests/Feature/AdminCallAuditReadIntegrationTest.php tests/Feature/AdminCallAuditReadTest.php tests/Feature/AdminCallAuditRecordTest.php tests/Feature/AdminCallAuditClaimTest.php tests/Unit/CallAuditBatchPolicyTest.php tests/Feature/AdminShellTest.php --no-progress --display-skipped` | **139 PASS/1.711 assertions**, không SKIPPED/FAIL. Đây là lần chạy sau sửa race navigation; không full regression |
| Refinement cuối `php vendor/bin/phpunit tests/Feature/AdminCallAuditUiTest.php --no-progress --display-skipped` | **13 PASS/103 assertions**, không SKIPPED/FAIL; bổ sung sibling-token denial, geometric check và browser dùng CSPRNG fallback, không chạy lại toàn regression |
| `php -l`/`php vendor/bin/pint --test` | PASS đúng9 PHP mới/sửa kể cả routes/policy/unit; Blade được render thật trong HTTP/browser |
| `node --check resources/js/admin-call-audits.js`, `node --check tests/Browser/admin-call-audits.mjs`, `npm run build` | PASS; Vite88 modules, bản cuối sau fallback CSPRNG. Không cài thêm dependency |
| `php tests/Fixtures/call_audit_ui_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false; mọi fixture journal cleaned=true. Schema hash chỉ bỏ AUTO_INCREMENT counter; không reset counter. Port8776/server QA đã dừng |
| Diff/tracking/assets | Git diff --check, file mới/9 mục tracking/native glyph sizes kiểm riêng; các edits phase trước được giữ nguyên |

HTTP/DOM coverage: heading/nav active/đúng3tabs/không modal/không bootstrap PII hoặc toast sớm; guest/Sales/Dealer/Manager theo dealer account/inactive; preview không claim/full phone; owner/token/version/expiry; sibling item token không dùng nhầm, purpose/IDOR/missing item; fresh blocked Admin; sourcedrift409 không log hoặc ghi source; CSRF419/valid200; no-answer recall trước hạn chỉ preview, đúng24h Admin khác claim/contact được; raw sensitive capability vẫn deny. C1/C2 suites tiếp tục kiểm idempotency, real MySQL concurrent claims/saves, frozen masking/consistency/counts và không tác động moderation/verification/điểm/Awards.

**Browser thật:** Chrome headless Desktop1440×1046 qua Playwright đã có trong môi trường, PHP Laravel server loopback trên toyota_testing. Auth harness chỉ set đúng fixture Admin theo ID/tag, không test form login/password; mọi request vẫn qua middleware/auth session/CSRF/policy/services thật. Five approved submissions/three Dealers là fixtures test có ID journal và cleanup, không runtime mock hoặc dữ liệu production. Correct/Wrong/no-answer ghi5 attempts thật (3Correct,1Wrong,1no-answer), release/chuyển tab không ghi attempt, double-click không lặp; giữ response **sau commit thật** để assert toast vẫn hidden đến khi response được thả. Một503 được inject riêng để test retry giữ nguyên payload/key, mọi success là Laravel thật. Empty recall/current tab, disabled future retry, readonly completed, next preview không claim, last item không fake toast, no browser errors/overflow, no local/session storage secrets. Kiểm assets loaded/non-empty/slot/root geometry14/16px và split420/692px; workspace x716/y236/w692/h762 sai lệch≤1px.

Đã xem trực tiếp ảnh calling/success so Figma1440 và kiểm geometry các ảnh preview/recall/completed/last. Evidence local/private: [calling](../../.laravel-tools/p09l-browser/c08-calling-1440.png), [success](../../.laravel-tools/p09l-browser/c08-success-1440.png), [recall](../../.laravel-tools/p09l-browser/c08-recall-1440.png), [completed](../../.laravel-tools/p09l-browser/c08-completed-1440.png), [last](../../.laravel-tools/p09l-browser/c08-last-item-1440.png), [checks](../../.laravel-tools/p09l-browser/checks.json), [Figma](../../.laravel-tools/p09l-figma-reference.png). Các ảnh dùng fixture thật, không phải dữ liệu đang hoạt động production hoặc bằng chứng đã gọi khách ngoài đời.

Harness mới `call_audit_ui_router.php` chỉ chạy từ testserver, guard exact DB/config trước PDO, localhost+fixture tag, static asset path giới hạn public root, không là production auth/route. Windows Symfony stop có lần đầu để lại child PHP; đã dừng đúng owned PID và sửa harness ghi actual PHP PID theo fixture tag, dừng bằng native Stop-Process, không kill các PHP khác. Lần đầu browser cũng phát hiện click-tab race khi refresh, đã sửa và toàn suite PASS lại. Test browser ghi NOT_RUN/skip rõ nếu thiếu local Playwright/Chrome, không giả PASS; môi trường hiện tại có đủ và0skip. Không migrate/seed/reset/RefreshDatabase/DatabaseMigrations/production, không thay schema/dữ liệu cha ngoài fixture; setup/cleanup theo ID/tag A3 hiện hữu. Private snapshots `storage/app/private/p09l-call-audit-claim/{before,after}.json`/journals và test session/PID evidence giữ nội bộ.

## Những lỗi còn tồn tại

Không còn test FAIL liên quan. **BLOCKED thiết kế**: câu warning về huỷ xác minh/trừ1điểm mâu thuẫn C1 đã duyệt; missing confirmation Sai, empty/error/conflict/expired/last-item feedback, toast Wrong/no-answer, badge no-answer2 và visual active Wrong/no-answer variants chưa đủ thiết kế. UI dùng controls hiện có, no extra messages/modal/badge; fail closed/disabled/blank workspace và counts thật, không tuyên bố đã match các trạng thái chưa có frame.

Figma mẫu Cần gọi chứa cả badge Đúng/Sai/Gọi lại và progress/counts mẫu không khớp; giữ semantics C2 theo task: từng item chỉ đúng tab thực tế, không giả kết quả để sao chép sample. Chỉ có Desktop Figma, không tạo mobile layout mới; shell overflow hẹp kế thừa P01. Account/logout shell có sai khác P01 lịch sử (dòng logout ngoài frame, thông tin account thật khác mẫu) được giữ, không sửa ngoài scope. Chưa có thiết kế thông báo lỗi/khôi phục khi mất response quá lease hoặc reload giữa claim; backend vẫn bảo vệ600s và idempotency, không tự cấp lại lease. Không tích hợp tổng đài/telephony, không đánh giá phone consent ngoài contract hiện có.

## Task hoặc bước tiếp theo

Chốt với thiết kế/TMV câu warning đúng nghiệp vụ chỉ finding pending và các variant bị BLOCKED; sau đó refinement đúng frame để nghiệm thu thị giác đầy đủ. Không tự mở penalty/verification/publication hoặc đổi C1/C2 từ task UI. Source đủ dùng cho luồng Desktop chính đã kiểm thật; không coi tracking này là approval triển khai production.
