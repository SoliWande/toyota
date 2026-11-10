# P05 — C06 Duyệt tài khoản

## Mục tiêu
Đồng bộ riêng C06 Manager approval/inline rejection, tab và dữ liệu thật; giữ P01, Sales B08/B11/B12 và approval hai cấp.
## Trạng thái
BLOCKED các phần thiếu thiết kế/contract nêu dưới; phần Manager approval và C06 có thể triển khai đã kiểm chứng — 2026-11-10.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, chỉ C06/P05, controller/request/action/policy/models/routes/test liên quan. P02/P03 có thay đổi sẵn, giữ nguyên. Trang hiện liệt kê Manager pending, duyệt/từ chối text reason; action có transaction/audit, không gửi mail. Figma session d6becc36-515f-44e0-813f-a86e98c78c2e đã đọc164:658/173:1760/164:789.
## Các file source code liên quan
Sửa `app/Http/Controllers/Admin/DealerRegistrationController.php`, `resources/views/admin/dealer-registrations.blade.php`, `routes/web.php` (chỉ adapter Admin), `vite.config.js` (2entry C06), `tests/Feature/AccountRegistrationApprovalTest.php`.
Thêm `app/Http/Requests/Admin/ReviewDealerRegistrationRequest.php`, `resources/css/admin-account-approval.css`, `resources/js/admin-account-approval.js`,6PNG `public/admin/icons/c06-{approve,down,info,phone,clock,bell}.png` và tracking này.
Shared AccountRegistrationReviewController/Request/Action, UserPolicy/models, P01 và toàn bộ views/source Sales/Manager chỉ đọc, không sửa.
## Những thay đổi đã thực hiện
Tạo tracking trước sửa source. Đã đồng bộ heading/tabs, card Manager, thông tin/phone mask, inline reason/note, khung kiểm tra và Sales overdue. Card lặp theo toàn bộ pending thật, không tự tạo pagination controls ngoài Figma. Duyệt/từ chối qua adapter Admin gọi action cũ; CSRF/quyền/transaction/audit giữ nguyên. Glyph lấy PNG nguyên node MCP; không temporary Figma URL runtime. Đã đọc4toast C06; chỉ bật toast duyệt173:2066 sau response thành công.
## Quyết định kỹ thuật quan trọng
Không đổi action transaction/audit/policy/schema. Không Admin override Sales/mail. Chỉ kết quả kiểm tra có bằng chứng mới hiển thị. Inline selected reason và ghi thêm đều là lý do công khai: adapter request nối bằng newline, tổng≤2000 ký tự theo validation cũ; không biến thành admin_note. Request riêng tránh thay hành vi Sales B08/B11/B12.
Sales quá3ngày: role Sales/pending, strict `< now−72h` theo timezone ứng dụng; clock MAX(account review to_pending) hoặc created_at lần đầu, giống source C01; không dùng updated_at. Manager hiển thị ở hàng Sales phải là role Dealer active cùng dealer_id. Thông tin “chưa có quản lý” chỉ hiện khi Dealer active và query xác nhận chưa có Manager active.
## Kết quả kiểm thử
Preflight mới PASS actual loopback MySQL `toyota_testing`/15bảng, APP_CONFIG_CACHE riêng tiến trình. Bootstrap giữ schema hiện có, fixtures transaction rollback; không migrate/seed/reset/full regression.
`php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result tests/Feature/AccountRegistrationApprovalTest.php .laravel-tools/P05VisualFixtureTest.php` **PASS24tests/255assertions** (suite23/249 + fixture1/6). Bao gồm account hai cấp/unique Manager/resubmit/audit rollback, C06 clock strict72h, quyền/CSRF419, rejection ghép/limit/forged fields, không mail, mask/escape/không nội bộ, approve2xx và stale không audit lặp. Lượt trước18/183 và23/244 là các lượt trước bổ sung case cuối.
`npm run build` **PASS79modules**; Pint --test3filePHP P05 và node --check JS PASS; git diff --check PASS (chỉ cảnh báo CRLF của P02 có sẵn).
## Những lỗi còn tồn tại
BLOCKED variant Đã xử lý173:1759 vẫn sai nhãn trên Figma mới đọc, chưa có layout/cohort lịch sử: tab giữ đúng label của main và inert aria-disabled, không route/data giả; `tab=processed` bị validation từ chối. Hai tab pending/overdue hoạt động bằng GET thật.
Email domain/phone evaluator thiếu: không render2greenchecks hoặc câu xác nhận, giữ trống đúng vùng104px đã dành trong Figma. Reminder/override chưa có dịch vụ/quyền: buttons giữ label Figma nhưng disabled theo P01; bỏ subtitle hứa nhắc/duyệt thay. Không render toast2073/2080/2088 hoặc thông báo email chưa gửi. Những sai khác có chủ ý này chặn nghiệm thu nguyên văn toàn màn.
Empty/tab history/menu option lý do chưa có thiết kế bổ sung: không tự thêm empty copy/section hoặc lựa chọn ngoài option đã đọc. Chưa nghiệm thu Mobile.
## Task hoặc bước tiếp theo
Chốt variant/lịch sử đã xử lý; thiết kế trạng thái chưa xác minh/empty; contract gửi email/cooldown và quyền override nếu có task riêng. Không tự triển khai các nghiệp vụ đó trong P05.

## Đối chiếu trực quan cuối 2026-11-10
- Đã xem [pending1440](qa/artifacts/p05-admin-account-approval-20261110/c06-pending-1440.png), [overdue1440](qa/artifacts/p05-admin-account-approval-20261110/c06-overdue-1440.png) đối chiếu trực tiếp [Figma1440](qa/artifacts/p05-admin-account-approval-20261110/figma-c06-1440.png), tabs173:1760 và inline164:789. Head1128×60 tại280/92; card540×632.6≈633, system572×314/gap16; info490×187; inline490×238; overdue1128×210. Đã sửa width nút Duyệt240/Từ chối242 trong CSS C06; P01 không đổi, giữ sai khác logout đã duyệt trước.
- `node .laravel-tools/p05-qa.mjs` **PASS** sau build/sửa cuối: inline mở/Huỷ không mutation, blocked controls inert, lỗi422 không toast, approve response thành công mới toast thật, reject refresh không email claim, chuyển tab GET/count thật, không overflow/broken images/pageerrors.6asset nonempty, positions/callsites đã xem.
- Ảnh dùng HTML/JSON từ HTTP Laravel có middleware trên fixture toyota_testing rồi rollback; Playwright replay response thành công này và mô phỏng422 riêng để kiểm tra JS. Đây không phải phiên browser đăng nhập live với dữ liệu local. Tests approval thật ở PHPUnit như trên. Script/artifacts tạm ở vùng QA ignored, không tạo báo cáo QA dài; server đã dừng sau kiểm tra. Không commit/push/deploy. P02/P03 có sẵn không thuộc diff P05.
