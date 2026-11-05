# Đăng ký và phê duyệt tài khoản Website Thi đua

## Mục tiêu
B05/B06/B06b/B09/B10 và header theo vai trò; Sales được Manager cùng Dealer duyệt, Manager được Admin có quyền hiện hành duyệt. B08/B11–13 chỉ navigation/access, không redesign. Không đổi điểm/attribution/customer/snapshot/privacy.

## Trạng thái
IN PROGRESS — 2026-11-05, tạo trước sửa source.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS và tracking login/dealer/dashboard, audit routes/RegisterRequest/User/UserPolicy/ReviewSalesAccount/DealerAccount. Public register chỉ Sales, phone chưa yêu cầu; Admin duyệt Sales, Manager chỉ đọc. Một Manager/Dealer được DB generated unique giữ kể cả rejected/blocked. User.reviewed_by và ModerationReview chỉ cho Admin; không nới guard duyệt hồ sơ khách hàng. Working tree có task trước chưa commit, giữ nguyên.

## Các file source code liên quan
Auth RegisterRequest/RegisteredUserController/AccountStatus; UserPolicy/User/ReviewSalesAccount; Dealer Sales/Dashboard; Admin DealerAccount; routes; auth/dealer Blade và component/header/assets; tests auth/management/authorization.

## Những thay đổi đã thực hiện
Xác minh Figma đúng Website Desktop207:333/Mobile148:252, file yKmWQI6BYJ6dponEnidlsq, session4f9245d2-ea3d-4bb0-ad3e-47a63b828dc5. Desktop B05=217:537,B06=217:633,B06b=217:703,B08=218:611,B09=219:661,B10=219:840; Mobile B05=152:367,B06=152:444,B06b=205:793,B08=154:473,B09=155:398,B10=155:557, menus Manager154:375/Sales205:561. Đã đọc context/screenshots; không có ảnh người dùng đính kèm ở lượt hiện tại nên dùng Figma làm nguồn.

## Quyết định kỹ thuật quan trọng
Người dùng xác nhận: Admin có quyền hiện hành được duyệt Manager; giữ1Manager/Dealer. Không nới uniqueness hoặc ghép/đổi email. Dự kiến audit riêng cho vòng đăng ký/gửi lại để giữ reviewer scope và không thay ModerationReview/customer. Migration mới chỉ thêm cấu trúc cần thiết, không sửa migration đã chạy/reset dữ liệu; test ghi chỉ toyota_testing sau preflight. Rejected gửi lại chính hồ sơ có xác thực/CSRF, không tạo account/email mới, không tự kích hoạt. Không hứa email khi chưa có mail thật.

## Kết quả kiểm thử
Chưa chạy mới. Sẽ ghi tests liên quan và QA1440/390, không full regression mặc định.

## Những lỗi còn tồn tại
Đang audit/map thiết kế; schema/lịch sử review mới chưa triển khai. Không coi kết quả lịch sử là PASS task mới.

## Task hoặc bước tiếp theo
Hoàn thiện backend permission/locks/audit/resubmit; UI đúng frame; tests và visual QA ngắn, cập nhật evidence và phần thiếu.

## Cập nhật 2026-11-05 — triển khai theo xác nhận của người dùng

- **Contract:** Admin đang active và có quyền DealerPolicy hiện hành được duyệt Manager; không thêm allowlist tổ chức. Một Manager/Dealer ở mọi trạng thái, không đổi unique guard. Sales chỉ Manager cùng Dealer duyệt; Admin không dùng endpoint Sales cũ để approve/reject hoặc kích hoạt thẳng rejected.
- **Thay quyết định dự kiến ở trên:** audit schema xác nhận nhánh account của `moderation_reviews.sales_id` là FK users, cho phép pending/active/rejected/blocked. Tái sử dụng nhánh này cho Sales/Manager/gửi lại; không tạo migration. Nhánh customer vẫn chỉ Admin, CustomerSubmission guard không đổi, lịch sử append-only. User reviewer chỉ kiểm tra khi gán reviewer/time mới, tránh vô hiệu lịch sử khi Sales chuyển Dealer.
- Action mới `ReviewAccountRegistration`: transaction/retry, khoá Dealer rồi actor/target theo ID, tải lại và kiểm tra quyền/trạng thái/Dealer trước ghi; rollback nếu lưu lịch sử lỗi. Pending→active/rejected; active↔blocked theo quyền; rejected chỉ POST resubmit chính account→pending, xoá reviewer hiện hành nhưng giữ lịch sử/email/password/role/Dealer. Không tự gửi mail. Chỉ công khai lý do mới cho chính người đăng ký; không xuất admin_note hay lý do Sales bị Admin từ chối cũ.
- B05 chọn role/Dealer active thật, phone bắt buộc và chuẩn hoá, password confirmation giữ contract cũ, chống role/status giả. Manager tồn tại (kể cả rejected/blocked) không đăng ký trùng. B06/B06b có Dealer/role/cấp duyệt/ngày/lý do an toàn, logout/resubmit. B09 pending staff + review thật; B10 tabs/search/pagination/điểm tháng/query MAX khai báo tại đúng Dealer, approve/reject/block/reactivate. Mặc định tab active; `status=` giữ danh sách tất cả để tương thích. Tab pending dùng chính paginator cho nút duyệt, không giới hạn hành động vào 10 người đầu.
- Header Sales/Manager có liên kết theo quyền và logout POST trên Desktop/Mobile; B08/B11–13 giữ nội dung và middleware cũ. Manager không được cấp quyền khai báo/duyệt khách hàng để tạo CTA giống dữ liệu Figma. Footer hiện có được tách nguyên markup thành component tái sử dụng; không sửa giao diện public khác.

### File thuộc task
PHP: `app/Actions/{ReviewAccountRegistration,ReviewSalesAccount}.php`; controllers `AccountStatusController`, `AccountRegistrationReviewController`, `Auth/RegisteredUserController`, `Dealer/{Dashboard,Sales}Controller`, `Admin/{DealerAccount,DealerRegistration}Controller`; requests `Auth/RegisterRequest`, `ReviewAccountRegistrationRequest`; models `User`, `ModerationReview`; `UserPolicy`; `routes/web.php`.

UI: `auth/{register,account-status}`, `dealer/{dashboard,sales/index}`, `admin/{dashboard,dealer-registrations,sales/show,dealers/account}`; `components/layout`; competition components `account-layout/account-art/account-input/staff-header/pending-staff/staff-pagination/footer`, header optional mobileTitle và layout gọi footer; `resources/{css/account.css,js/account.js}`; Vite entries; 4 SVG trong `public/competition/account`. Tái sử dụng scene/login/fonts/logo, không thay chúng. Các sửa login/importer/navigation/rules có sẵn thuộc task trước.

Tests: mới `AccountRegistrationApprovalTest`; AuthenticationTest chỉ thêm phone vào fixture theo contract bắt buộc mới. AdminSalesManagementTest đổi các assertion Admin approve/reject thành 403, vẫn giữ toàn bộ ma trận trạng thái/rollback/history/notes; approve/reject/resubmit/duyệt lặp được bảo vệ thêm trong class mới. Không sửa DashboardNavigationTest để bỏ FAIL.

### Giới hạn thiết kế cần ghi nhận
B05 thêm confirmation và ô tìm Dealer thật nên cao hơn Figma; không prefill demo. Không hứa email hoặc đổi timezone. B10 giữ search/trạng thái rejected và dùng MAX khai báo thay “đăng nhập gần nhất” vì chưa có last-login. B09 giữ số liệu nghiệp vụ thật thay phễu và “khách đang kẹt”: chưa có mapping báo cáo được xác nhận giữa workflow evidence/group, moderation và các nhóm Figma; không suy trạng thái group từ approved. Chưa có PDF standee, dùng QR PNG thật, không tạo link PDF giả. Chưa nghiệm thu khớp hoàn toàn B09; không tuyên bố pixel-perfect. Cần xác nhận mapping các widget này trước triển khai tiếp.

### Kiểm chứng cuối task (kết quả mới, 2026-11-05)
- **PASS isolation:** preflight `APP_CONFIG_CACHE` riêng process tới file không tồn tại; `php docs/figma/qa/competition-db-check.php`: actual MySQL loopback `toyota_testing`, 15 bảng, không config cache. PHPUnit dùng `--bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result`; không migrate/seed/reset, không test ghi vào `toyota`.
- **9 class / 160 tests / 2812 assertions: 159 PASS, 1 FAIL**. AccountRegistrationApprovalTest18, AuthenticationTest43, AdminSalesManagementTest42, DealerAccountTest6, DealerDashboardTest5, DealerAuthorizationTest3, AdminSubmissionModerationTest30, PublicPrivacyTest8 đều PASS; DashboardNavigationTest4/5 PASS. Log/XML ignored `.laravel-tools/account-approval-final.*`. Bao gồm malformed input/phone/email/password; role/status giả; hai cấp review; self/cross-Dealer/stale actor/target/review lặp; reject/resubmit bất biến identity; rollback lỗi audit; block/unblock không đổi điểm/hồ sơ; pending/rejected/blocked bị chặn; pagination/search và query không tăng theo nhân viên. Có lock và kiểm tra lại trạng thái, chưa stress test hai process duyệt đồng thời thực tế.
- **FAIL chưa xử lý:** `DashboardNavigationTest::test_historical_top_dealer_still_has_a_detail_page_after_deactivation` dòng79 kỳ vọng chuỗi “Ngừng hoạt động” tại landing QR D01. HTTP200 và kiểm tra public directory trước đó vẫn PASS. Controller PublicDealer/dealers.show/registration.landing-content không đổi so HEAD; assertion đã tồn tại trong HEAD. Không sửa test hoặc trang khách hàng ngoài phạm vi, chưa tái chạy toàn bộ cây HEAD riêng để kết luận tuyệt đối thời điểm phát sinh.
- **PASS build/JS/style:** `npm run build`68modules, không warning asset; `node --check resources/js/account.js`; Pint các PHP thuộc task, class mới đã format và kiểm tra lại; `git diff --check`. Không sửa auth password/session/CSRF middleware, không thay schema hoặc `.env`, không deploy.
- **PASS quick QA1440/390:** B05 live GET local (88 Dealer active thực tế); B06/B06b/B09/B10/empty và header B08 từ **HTTP Laravel trong transaction toyota_testing đã rollback**, không giả làm luồng browser production. Export fixture1test/8assertions PASS; QR cùng fixture, không gọi nhầm ID fixture trên local. Browser kiểm tra overflow/images/console/assets, mobile menu/Escape, role/search/password, form reject mở rộng, logout POST Desktop. Không POST tài khoản vào local. Xem trực quan đủ10 ảnh và so screenshot/context Figma; sửa rank typography/mobile, label tìm kiếm, metadata margin và header back đã xác minh.
- Evidence ignored: `docs/figma/qa/artifacts/account-approval-20261105/{b05,b06,b06b,b09,b10}-{1440,390}.png`, `checks.json`; helper/export ở `.laravel-tools/account-approval-*`, không tạo thêm báo cáo QA. Source runtime không chứa dữ liệu fixture.

### Trạng thái bàn giao và bước tiếp theo
**IN PROGRESS, chưa DONE toàn bộ yêu cầu.** Luồng đăng ký/phê duyệt chính và UI có dữ liệu backend đã kiểm chứng; còn mapping phễu/cảnh báo B09/PDF/last-login nêu trên và1FAIL regression. Admin vẫn có thể provision Manager bằng luồng nội bộ cũ với quyền/password rõ ràng; hồ sơ Manager đăng ký public pending/rejected không được kích hoạt qua editor cũ. Cần xác nhận định nghĩa các widget thiếu và xử lý riêng regression D01 trước nghiệm thu đầy đủ. Giữ nguyên source/DB các task trước, không commit/push/deploy.

Kiểm tra cuối: tách số điện thoại/ngày đăng ký thành hai dòng, đặt Từ chối trước Duyệt theo Figma; build68modules và QA1440/390 chạy lại PASS, ghi đè đúng10 ảnh tạm của cùng task. Đã dừng server PHP8771 phục vụ QA.

## Cập nhật 2026-11-05 — kiểm tra regression D01
IN PROGRESS: đã đọc lại source/tracking và chạy riêng test lỗi sau preflight actual `toyota_testing` (15 bảng, loopback, không config cache). Tái hiện **FAIL 1test/6assertions**: landing HTTP200 đã có panel “Mã QR không còn hiệu lực”/không tiếp nhận đăng ký mới, thiếu chính chuỗi cũ “Ngừng hoạt động”. Đang đối chiếu Figma D01/D06 và contract intake trước sửa assertion/runtime; không sửa B05–B10 hoặc dữ liệu production. Log tạm ignored `.laravel-tools/account-d01-regression-before.log`.

**Kết quả regression: DONE.** Assertion đã lỗi thời, runtime không thiếu trạng thái. Figma MCP đọc lại D01 `209:233`, panel bước1 `209:278`, D06 `212:587`, M06 `57:714` (file yKmWQI6BYJ6dponEnidlsq): trạng thái inactive dùng “Mã QR không còn hiệu lực”. Contract đã chốt trong `phase-1-implementation.md`/`analysis-report.md` đặt D06 vào panel D01, giữ canonical slug/profile lịch sử HTTP200, ẩn intake và chặn POST; không yêu cầu nhãn cũ “Ngừng hoạt động”. Source `dealer-registration/step-one` và các guard intake hiện thực đúng contract này.

Chỉ sửa `tests/Feature/DashboardNavigationTest.php`: thay nhãn cũ bằng heading/thông báo inactive thực tế; thêm kiểm tra Dealer vẫn inactive, score/rank lịch sử1/#1 còn nguyên, không render `data-step-one`/input customer_name. Giữ các assertion home link và directory loại Dealer inactive. Không sửa application code, UI, B05–B10 hoặc DB production.

Kiểm thử mới, cùng bootstrap cách ly/process-isolation/do-not-cache-result:
- Riêng `--filter test_historical_top_dealer_still_has_a_detail_page_after_deactivation`: **PASS 1test/11assertions** (trước sửa FAIL).
- `DashboardNavigationTest`, `DealerProfileTest`, `DealerLandingRegistrationTest`: **PASS 54tests/612assertions**; log/XML `.laravel-tools/account-d01-related.*`.
- Filter `test_inactive_dealer_cannot_create_or_continue|test_missing_expired_foreign_draft_and_inactive_dealer` trên DealerRegistrationDraftTest/DealerRegistrationFinalTest: **PASS 2tests/19assertions**, xác nhận guard inactive ở bước1/tiếp tục/final. Log `.laravel-tools/account-d01-intake-guards.log`.
- Pint test file và `git diff --check`: PASS. Không chạy full regression/build/screenshot Laravel vì chỉ đổi test; hình Figma đọc trực tiếp là bằng chứng contract, không phải visual QA runtime mới.

Kết luận FAIL D01 ở mục bàn giao trước đã được giải quyết. Không suy kết quả160tests lịch sử thành PASS mới; trạng thái toàn task đăng ký vẫn IN PROGRESS do các widget B09 còn thiếu mapping, ngoài phạm vi lần sửa regression này.
