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

## Cập nhật 2026-11-06 — tiếp tục B09/B10/standee theo quyết định người dùng

**IN PROGRESS.** Người dùng chốt năm nhóm phễu loại trừ nhau, hồ sơ kẹt quá 24 giờ theo timestamp nghiệp vụ, `last_login_at` Sales chỉ khi đăng nhập thành công và PDF A4 dùng QR canonical/tên/logo Toyota thật. Giữ phân quyền/privacy/điểm/dữ liệu; chỉ test liên quan trên `toyota_testing`, QA1440/390, không full regression.

Đã đọc lại tracking, controllers/dashboard/Sales/auth, model User/customer, workflow verification/audits, DealerProfile QR, routes, tests và helpers QA. Đọc trực tiếp context Figma B09/B10 Desktop219:661/219:840 và Mobile155:398/155:557. Git Laragon cũ từ chối ownership dù cấu hình command-local; dùng Git tại Program Files với safe.directory command-local, working tree trước triển khai sạch. Không sửa global Git config.

Hướng mapping: phễu tháng theo `submitted_at` và timezone hiện hành như leaderboard; rejected là trạng thái kết thúc, đếm riêng ngoài năm nhóm để không giả là yêu cầu bổ sung. Pending ưu tiên supplement open → Cần bổ sung; supplement submitted/blocker có thật → Chờ xác minh; group có evidence khớp profile requested/member → Chờ xác minh, not_requested → Chờ vào group; chưa có evidence → Mới đăng ký. Approved → Đã xác minh hồ sơ (không suy ra membership group). Kẹt xét pending toàn kỳ và thời điểm bắt đầu bước hiện tại từ submit/group/supplement/blocker/close audit, không dùng updated_at hoặc coi thiếu timestamp là đã quá hạn. Sẽ ghi chi tiết và kiểm thử boundary/stale evidence trước bàn giao.

Môi trường không tải được Packagist (curl7); lần thử thêm PDF dependency tự hoàn nguyên, cần phương án PDF có thể chạy bằng thư viện/runtime hiện có. Chưa migrate database local/production; chưa chạy test ghi dữ liệu trong lượt này.

### Kết quả triển khai 2026-11-06

**DONE trong phạm vi tiếp tục B09/B10/standee đã chốt ở lượt này.** Mục bàn giao IN PROGRESS ngày05 được thay thế bởi kết quả mới bên dưới đối với ba hạng mục này; giữ nguyên toàn bộ lịch sử. Không tuyên bố nghiệm thu toàn Website hoặc pixel-perfect các widget khác của B09.

**Mapping cuối cùng (thay hướng dự kiến ngay phía trên):**

| Nhóm | Điều kiện theo thứ tự ưu tiên |
| --- | --- |
| Ngoài phễu | Rejected đã kết thúc; đếm riêng để không giả là đang yêu cầu bổ sung |
| Đã xác minh | Approved theo moderation; không khẳng định membership Group |
| Cần bổ sung | Pending có supplement_state=open |
| Chờ xác minh | Pending đã gửi bổ sung, có blocker nghiệp vụ đã ghi nhận, hoặc có evidence member khớp profile |
| Chờ vào group | Pending có evidence not_requested hoặc requested khớp profile; requested chưa chứng minh đã là thành viên |
| Mới đăng ký | Pending còn lại, chưa có evidence tiến độ phù hợp |

Phễu đếm hồ sơ đã submit trong tháng (`submitted_at`), cùng biên tháng/timezone hiện hành của LeaderboardService; không đọc draft file cache thành khách đã đăng ký. Mỗi hồ sơ trong phễu thuộc đúng một nhóm. Query theo snapshot `submission.dealer_id`, giữ demo exclusion hiện hành; không chuyển attribution khi Sales chuyển đại lý.

Khách kẹt xét pending toàn kỳ, chỉ khi bước hiện tại kéo dài **hơn**24giờ; đúng24giờ chưa quá hạn. Đọc audit workflow theo ID để tái hiện lần bắt đầu bước từ timestamp submit hoặc audit chuyển bước (group, yêu cầu/gửi bổ sung, đóng bổ sung, blocker). Lưu lại cùng nhóm không khởi động lại đồng hồ; member sau requested và gửi lại bổ sung bắt đầu bước mới. Không dùng updated_at, không backfill; thiếu mốc hoặc profile đã đổi làm evidence group cũ mất hiệu lực thì hiển thị thiếu mốc, không suy ra quá hạn. Hiển thị tổng và tối đa5hồ sơ chờ lâu nhất, link tới trang khách hiện hành. Batch250hồ sơ và nạp state/audit theo batch, không query evidence riêng từng khách.

B10 thêm migration000025: nullable timestamp users.last_login_at, không index/backfill/reset. Chỉ ghi giờ server sau Auth::attempt thành công với role Sales; GET/dashboard, login sai, đăng ký tự đăng nhập và Manager/Admin không có code ghi thời gian Sales. Giữ middleware/session/CSRF/password/role-status redirect cũ. Cast immutable và hidden khi serialize, không thêm fillable. Manager cùng Dealer đọc cột được select riêng; không còn dùng MAX khai báo thay login. Dữ liệu cũ trống hiển thị “Chưa ghi nhận đăng nhập”; mobile có nhãn Đăng nhập. Không đổi timezone.

Standee: route GET /dealer/standee trong group Manager active hiện hành, authorize DealerPolicy, throttle10/phút, lấy Dealer từ user (bỏ qua dealer_id/Host từ client), attachment PDF no-store/private. PHP GD/FreeType dựng A4 dọc2480×3508(~300dpi), PDF một trang với ảnh RGB nén lossless và link canonical; không Chrome/network/service ngoài ở runtime, không thêm Composer dependency. QR dùng nguyên DealerProfile::qr và URL canonical từ DealerProfile::url (cùng endpoint PNG cũ); không sinh slug/link mới. Logo PNG640×464 xuất từ SVG Toyota hiện có để in rõ; tên đầy đủ tiếng Việt tự xuống dòng/co chữ. Font Roboto Light có glyph Việt lấy bản binary không sửa trong JetBrains Runtime local, metadata Google2012/Apache2.0; bundle cùng license/notice, không phụ thuộc font hệ điều hành khi chạy. Giữ QR PNG cũ và thêm nút PDF thật trên dashboard.

### File thay đổi của lượt tiếp tục

- Backend: `app/Services/DealerRegistrationReport.php`, `DealerStandee.php`; mở rộng `SubmissionVerification` để dùng chung kiểm tra evidence với audit đã nạp; `app/Http/Controllers/Dealer/{Dashboard,Sales,Standee}Controller.php`, `Auth/AuthenticatedSessionController.php`; `app/Models/User.php`; `routes/web.php`.
- Schema: `database/migrations/2026_11_06_000025_add_last_login_at_to_users.php`.
- UI/assets: `resources/views/dealer/{dashboard,sales/index}.blade.php`, `resources/css/account.css`; `resources/images/standee-toyota.png`; `resources/fonts/standee/{Roboto-Light.ttf,LICENSE.txt,NOTICE.md}`.
- Tests mới: `tests/Feature/{DealerRegistrationReportingTest,SalesLastLoginTest,DealerStandeeTest}.php`. Không sửa các assertion test cũ để bỏ FAIL.
- Tài liệu: chỉ cập nhật tracking hiện có này. Helper/log/XML/fixture/screenshot/PDF sample ở `.laravel-tools/account-followup-*`, `.laravel-tools/AccountFollowupVisualTest.php`, `docs/figma/qa/artifacts/account-followup-20261106/` đều ignored, không thuộc runtime.

### Kiểm chứng mới và giới hạn

- **PASS isolation trước test ghi:** process-local APP_CONFIG_CACHE trỏ `.laravel-tools/account-followup-no-config.php` không tồn tại; `php docs/figma/qa/competition-db-check.php` xác nhận actual MySQL loopback toyota_testing/15tables, không config cache. Helper migrate require chính preflight rồi chỉ chạy `migrate --path=database/migrations/2026_11_06_000025_add_last_login_at_to_users.php --force`: PASS, số user trước/sau giữ nguyên. Không migrate/seed/reset database local/production, không sửa .env.
- PHPUnit luôn `--bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result`. Lần cuối **12tests/87assertions PASS** ở ba class mới: phân nhóm/exclusive/rejected/scope tháng và Dealer, strict24h, repeat cùng stage, chuyển member, đóng/gửi bổ sung, stale profile, thiếu mốc, giới hạn5, query không tăng theo từng khách, login đúng/sai/GET/role/hidden/cross-Dealer; PDF A4/xref/offset/permission và đối chiếu360000pixel nguồn QR (gồm quiet zone) với artwork PDF lossless.
- Nhóm liên quan **103tests/2598assertions:102PASS,1FAIL ở lần đầu**, gồm AuthenticationTest43, AccountRegistrationApprovalTest18, DealerDashboardTest5, DealerAuthorizationTest3, PublicPrivacyTest8, RegistrationLookupStatesTest26. FAIL là dataset dealer của `test_reject_and_resubmit_preserve_identity_unique_manager_and_append_only_history`: ViteManifestNotFoundException trong đúng thời gian build đang thay thư mục output. Đây là lỗi bố trí kiểm tra của lượt này. Sau build hoàn tất, chạy lại riêng filter đó cả2dataset: **2tests/34assertions PASS**. Không sửa source/test để né lỗi; không gọi lần103tests đầu là PASS hoàn toàn và không chạy full regression. Các test còn lại đều đã PASS ở lần đầu.
- **PASS build68modules**, `npm run build`; Pint PHP thuộc task; Git Program Files `-c safe.directory=F:/laragon/www/toyota diff --check`. Composer.json/lock không đổi sau lần tải dependency thất bại.
- **PASS quick QA1440/390:** export HTTP Laravel từ transaction toyota_testing rollback **1test/5assertions**, B09 có dữ liệu/B09 empty/B10, QR/PDF cùng fixture. Playwright kiểm tra6ảnh, không overflow/broken image/console/request lỗi, đủ5nhóm, login thật/unknown, mobile menu/Escape và tải attachment PDF. Đã xem trực quan ảnh ở hai viewport và artwork trích từ PDF, đối chiếu context Figma Desktop/Mobile vừa đọc; không POST vào local/production. Sửa nhãn login mobile và logo in nét rồi build/export/QA lại PASS. Evidence: `qa/artifacts/account-followup-20261106/{b09,b10,b09-empty}-{1440,390}.png`, `checks.json`, `standee-a4.pdf`, `standee-a4.png`.
- PDF sample dùng **Dealer fixture trong transaction đã rollback** nên URL của sample không phải Dealer production để phát hành; runtime luôn lấy Dealer thật hiện tại. Chưa in giấy/quét smartphone thực tế hoặc QA trình duyệt khác Chrome. Chưa test tải lớn; không thay SLA, timezone, hệ thống điểm hoặc phạm vi quản trị khách hàng.

### Bàn giao

Không còn lỗi kiểm thử chưa giải quyết trong phạm vi lượt này. Phần source/QA hoàn tất; migration000025 mới chỉ áp dụng toyota_testing. Khi triển khai môi trường sử dụng, phải chạy migration additive này trước khi phục vụ code mới; dữ liệu cũ giữ NULL đến lần login Sales thành công tiếp theo. Không deploy/commit/push hoặc đổi dữ liệu thật trong lượt này. Các khác biệt thiết kế B09 cũ ngoài ba hạng mục (ví dụ khoảng cách vượt hạng/CTA vượt quyền) vẫn theo giới hạn đã ghi ngày05, không tự mở rộng nghiệp vụ.

## Cập nhật 2026-11-06 — áp dụng migration000025 trên local toyota

**IN PROGRESS.** Người dùng đã cho phép kiểm tra và áp dụng đúng migration thêm last_login_at trên database local toyota; yêu cầu xác minh kết nối/schema/lịch sử trước khi ghi, backup users an toàn, so sánh dữ liệu trước/sau và chỉ GET kiểm tra login/B10. Không tạo account, giả đăng nhập, seed/reset hoặc full regression. Nếu schema ngoài dự kiến sẽ dừng trước ghi.

Đã đọc lại AGENTS/tracking, migration000025, config database/app, auth controller và Dealer SalesController. Các thay đổi source/UI/tests từ lượt trước còn trong working tree được giữ nguyên. Lượt áp dụng này không sửa application source; chỉ thêm helper vận hành ignored và cập nhật tracking hiện có. Chưa có thay đổi database local tại thời điểm ghi mục này.

### Kết quả áp dụng local — DONE, 2026-11-06

- **PASS xác minh thực tế:** config đang dùng (giữ nguyên cache) APP_ENV=local; PDO `127.0.0.1 via TCP/IP`, SELECT DATABASE()=toyota, MySQL8.0.30/3306, hostname trùng máy hiện tại, PID file xác nhận14108 và process là Laragon mysqld. Không ép DB_ENV/config sang database khác, không production/toyota_testing. Users InnoDB có3rows; trước áp dụng chưa có cột và chưa có migration row.
- **PASS preflight schema/dry-run:**19cột cũ đúng tên/kiểu/nullability theo migration source, có unique/index và2trigger bảo vệ email/public_slug. `php .laravel-tools/local-last-login-apply.php` chỉ kiểm tra + `migrate --path=database/migrations/2026_11_06_000025_add_last_login_at_to_users.php --pretend --force`, xác nhận duy nhất ALTER bổ sung nullable timestamp; schema/history không mâu thuẫn.
- **Backup trước DDL:** `.laravel-tools/backups/local-users-20261106-021256-f9eb3f71/users.backup.enc`, kèm manifest.json. Backup mã hóa Laravel gồm schema,3rows đủ19cột, SQL phục hồi (bỏ generated column trong INSERT), trigger và migration history; không lưu plaintext/credential/key. File ghi/flush/fsync, đọc lại giải mã và so sánh nguyên payload trước DDL. SHA256 ciphertext `8cbc55b6d4aa63f67797160448556317680f3109b25a7fd51cb25d8ed7350bf1`. Đã kiểm tra APP_KEY trong .env khớp encryption key đang có hiệu lực: cần giữ .env/APP_KEY để giải mã; chưa chạy restore.
- **PASS migration:** `php .laravel-tools/local-last-login-apply.php --apply` backup rồi gọi đúng `migrate --path=database/migrations/2026_11_06_000025_add_last_login_at_to_users.php --force`,26.98ms; migration history id28/batch3. Khóa users/migrations trong cùng kết nối để tránh ghi tài khoản đồng thời trong khoảng backup/DDL, giải phóng ở finally. Không migrate:fresh/seed/reset hoặc chạy migration khác.
- **PASS bất biến:** users3→3; so sánh chính xác toàn bộ giá trị19cột trước/sau và SHA256 snapshot `eeea1e4160b544c024625754b65ea3a492666a5bade575ff580f75200bfdaf22` không đổi. Metadata cột cũ, index, trigger giữ nguyên; chỉ thêm timestamp nullable/defaultNULL,3/3last_login_at=NULL, không backfill. Migration history cũ giữ nguyên, thêm đúng1row. Kiểm tra backup và đối chiếu dữ liệu lần nữa sau GET vẫn PASS.
- **PASS GET local thật:** `php .laravel-tools/local-last-login-get-check.php` gọi Apache local qua loopback với Host từ APP_URL; `/login`200 có form email/password/CSRF/POST; `/sales/dashboard` và `/dealer/sales`302→login khi guest. Truy vấn cùng SELECT/join/scope của B10 đọc được1Sales active tại Dealer của Manager hiện có, có cột last_login_at, không lỗi SQL. Đây là read-only query, không giả lập auth/request user. Sau GET toàn bộ users vẫn không đổi.
- **Giới hạn:** theo yêu cầu không POST/đăng nhập giả/tạo account trên local nên chưa mở B10 trong phiên đã đăng nhập và chưa thử login Sales thành công tại local. Kết quả chức năng đăng nhập ở toyota_testing đã ghi lượt trước, không coi là test mới. Lượt này không chạy PHPUnit/build/full regression hoặc QA dài; `git diff --check` PASS. Không sửa application source, .env/config cache hoặc dữ liệu cũ; tài liệu thay đổi duy nhất là tracking này, helper/backup đều ignored ngoài public. Không còn bước schema local phải thực hiện; người dùng có thể đăng nhập thật để xem B10.
