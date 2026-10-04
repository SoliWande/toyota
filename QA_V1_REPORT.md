# Final QA V1 — 2026-10-04

## Kết luận

Các flow hiện có đã pass kiểm tra tự động và kiểm tra UI trên Open Server. **Chưa xác nhận V1 sẵn sàng production**: admin chưa có workflow publish awards, và runtime/framework hiện tại đã hết hỗ trợ bảo mật với dependency advisories còn tồn tại.

QA đọc SPEC.md, routes, auth/role/status middleware, policies, FormRequests, actions, models, relationships, services, migrations, factories/seeders, Blade, cấu hình và các báo cáo security/performance trước đó. Không thêm feature hoặc thay đổi quy tắc nghiệp vụ. Thay đổi của đợt này là test flow liên hoàn, thêm case validation UTF-8 và báo cáo QA; không cần sửa logic ứng dụng sau các kiểm tra.

## Kết quả các lệnh đã chạy

| Kiểm tra | Kết quả cuối cùng |
| --- | --- |
| PHPUnit full suite, MySQL `toyota_testing` | **445 pass, 0 fail/error/skip; 2.854 assertions** |
| Integration `V1WorkflowTest` | 1 test / 89 assertions: đăng ký → duyệt tài khoản → login → submit/edit → review/approve → duplicate/reject → leaderboard → award preview → authorization/public privacy |
| Race-condition test trong full suite | Hai PHP processes / hai MySQL transactions cùng duyệt profile normalized: chỉ một approved, record còn lại pending và chỉ một history |
| `npm run build` | PASS; Vite build 58 modules, tạo manifest/CSS/JS |
| Laravel Pint `--test` | PASS, 147 PHP files |
| PHP syntax `php -l` | PASS, 146 files thuộc app/bootstrap/config/database/routes/tests; không tính vendor hoặc compiled caches |
| Composer `validate --strict --no-check-publish` | PASS |
| Migrations từ database trống | PASS, 16 migrations, 10 tables; metadata xác nhận 12 foreign keys, 10 CHECK constraints, 10 UNIQUE constraints và indexes duplicate/listing |
| Browser, Chrome headless trên Apache/Open Server | **75 lượt tải trang PASS** ở 320/390/1440px; login admin/Sales thật, forms/list/detail/pagination, ảnh private; không tràn ngang hoặc lỗi JavaScript |
| Apache privacy smoke test | 8 đường dẫn source/secrets/logs/tests/git/preview trả 403; landing trả 200 |
| Blade `view:cache` | PASS; đã clear sau kiểm tra |
| `route:cache` | PASS; đã clear sau kiểm tra |
| `git diff --check` | PASS |
| `npm audit --json` | PASS, 0 vulnerabilities |
| Composer `audit --locked --format=json` | **FAIL về dependency security**, 4 advisory entries của Laravel / 3 vấn đề riêng; không phải lỗi PHPUnit |

Project không cấu hình ESLint hoặc PHPStan; không báo những công cụ đó đã chạy. PHPUnit được gọi trực tiếp với PHP 8.1 và `-d sys_temp_dir` phù hợp Open Server Windows. JUnit result nằm trong thư mục local ignored `.laravel-tools/final-qa-junit.xml`.

Chỉ schema `toyota_testing` đã được `migrate:fresh`, với guard kiểm tra tên database trước khi chạy. Không reset database development hoặc xóa dữ liệu demo/chính thức. Browser sử dụng dataset development 50 Sales/500 submissions/4 awards; các mutation nghiệp vụ trong integration tests chạy trên database test tách biệt. Browser kiểm tra đọc trang/forms/login, không tuyên bố đã thao tác approve/publish production bằng browser.

## Ma trận 20 flow

| # | Flow | Trạng thái / bằng chứng |
| --- | --- | --- |
| 1 | Sales registration | PASS: active Dealer, validate, password hash, pending, từ chối role/status giả mạo; inactive Dealer và rate limit có regression tests |
| 2 | Admin approve Sales | PASS: pending → active, quyền admin active, transaction và history; reject/block/reactivate được kiểm tra riêng |
| 3 | Sales login | PASS: login bằng tài khoản đã duyệt, redirect theo role/status, session/logout/rate limit; browser login thật |
| 4 | Sales submit customer | PASS: owner từ auth, pending/time backend, URL normalize, Toyota/year/color/plate/photo bắt buộc; ảnh private |
| 5 | Sales edit pending | PASS: giữ submitted_at/owner, giữ/thay ảnh, rollback cleanup; approved/rejected không sửa/xóa |
| 6 | Admin review submission | PASS: filters/search/time/pagination, detail/Facebook link, reviewer/admin note, private vehicle/evidence |
| 7 | Admin approve | PASS: reviewed_by/reviewed_at/history, pending-only và constraint; không đổi submitted_at |
| 8 | Duplicate detection | PASS: variants normalized, same/other Sales, backend recheck và MySQL race test; same-name/different-profile không bị block |
| 9 | Reject workflow | PASS: pending → rejected, reason Sales thấy, admin note không lộ, history atomic |
| 10 | Sales dashboard | PASS: owner-scoped stats/recent/rank; active-only; query budgets và responsive |
| 11 | Weekly leaderboard | PASS: approved-only, submitted_at, thứ Hai/boundaries/timezone/tie-break/global rank |
| 12 | Monthly leaderboard | PASS: khai báo 31/10, duyệt 02/11 vẫn thuộc tháng 10; không thuộc tháng 11 |
| 13 | All-time leaderboard | PASS: aggregate/ranking/pagination, giữ Top 3 trên trang sau |
| 14 | Dealer leaderboard | PASS: tổng approved của Sales, week/month/all và quy tắc hòa điểm |
| 15 | Weekly awards | **PARTIAL**: tạo draft/preview/top-three/period uniqueness và đọc weekly snapshot PASS; chưa có admin publish endpoint/button |
| 16 | Monthly awards | **PARTIAL**: tạo draft/preview/top-three/period uniqueness và đọc monthly snapshot PASS; chưa có admin publish endpoint/button |
| 17 | Award snapshot | PASS cho model/DB/history hiện có: snapshot tên/Dealer/rank/score, thay dữ liệu live không đổi lịch sử, published immutable, draft không public. Snapshot fixture/demo không thay thế test một workflow publish chưa tồn tại |
| 18 | Public pages | PASS: landing/stats/leaderboard/awards/rules anchor/auth forms; không customer name/FB/phone/notes/vehicle/photo công khai |
| 19 | Authorization | PASS: guest/admin/Sales/status, IDOR, mass assignment, CSRF, account bypass, sensitive API/logs và private evidence |
| 20 | Responsive UI | PASS trên Chrome: 320/390/1440px, 75 lượt tải trang gồm registration/login/create/edit/list/dashboard/moderation/awards; có kiểm tra overflow, headings và JavaScript errors |

Các tests chi tiết nằm trong AuthenticationTest, AdminSalesManagementTest, CustomerSubmissionTest, SubmissionVehicleEvidenceTest, SubmissionDuplicateTest, SubmissionApprovalRaceTest, AdminSubmissionModerationTest, SalesDashboardTest, AdminDashboardTest, LeaderboardServiceTest, PublicLeaderboardTest, AwardWorkflowTest, DatabaseFoundationTest, SecurityAuditTest, PerformanceQueryTest, DevelopmentDemoSeederTest, ToyotaDealerSeederTest, LandingPageTest và các unit tests.

## Phần hoàn chỉnh và phần còn mở

Hoàn chỉnh trong phạm vi hiện có: authentication/registration/status; Admin Sales/Dealer Management; Sales submission CRUD pending và private evidence; admin moderation/duplicate protection/history; hai dashboard; Sales/Dealer leaderboard theo tuần/tháng/all; public landing/rules/leaderboard/award history; award draft/preview/snapshot display; official/development seeders.

Chưa hoàn chỉnh: admin công bố award từ draft. SPEC mục 7/11 còn mở về chốt kỳ, duyệt muộn, kỳ thiếu đủ winners, sửa/hủy/công bố lại; không tự thêm workflow này trong final QA. Chưa kiểm tra duplicate publish qua HTTP vì chưa có endpoint publish; unique award period và snapshot immutability đã được test.

Các giới hạn được giữ theo SPEC: normalized Facebook URL không đối chiếu chắc chắn username với numeric ID/alias; xác minh Facebook vẫn thủ công. Không có production load test, Safari/Firefox/device-lab accessibility audit hoặc kiểm chứng hạ tầng production. Exact leaderboard/count/search và filter options vẫn tăng chi phí khi dữ liệu lớn; query budgets/indexes hiện tại pass, chưa có bằng chứng cần Redis/cache.

## Những việc cần làm trước production

1. Nâng PHP/Laravel lên phiên bản được hỗ trợ và có bản vá; chạy lại suite/build/audit. Hiện PHP 8.1.9 và Laravel 10.50.3: [Laravel 10 kết thúc security support 04/02/2025](https://laravel.com/docs/10.x/releases#support-policy), [PHP 8.1 kết thúc support 31/12/2025](https://www.php.net/eol.php). Composer đang là development build cũ; dùng bản stable được hỗ trợ trong môi trường deploy.
2. Dependency advisories hiện tại: [debug XSS](https://github.com/laravel/framework/security/advisories/GHSA-jh5r-qr3c-85q8), [email CRLF](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq), [temporary signed URL confusion](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp). Debug=false và auth validation đã giảm thiểu hai bề mặt liên quan; project không sử dụng temporary signed filesystem URLs. Các biện pháp đó không làm dependency audit trở thành pass.
3. Chốt quy tắc award còn mở rồi triển khai/QA admin publish nếu V1 cần công bố kết quả thật. Bốn awards demo chỉ để kiểm tra lịch sử/UI.
4. Deploy với `APP_ENV=production`, debug=false, APP_URL/hostname đúng, HTTPS và DocumentRoot trỏ `public/`. Kiểm tra trusted proxy/host, cookie, quyền ghi storage/bootstrap/cache và auth/upload limits trên server thật. Smoke test Apache local không thay thế kiểm tra server production.
5. Dùng database/secrets/admin credentials riêng cho production. Không import development/demo accounts/submissions/awards/passwords/private placeholder images. Lấy danh bạ Dealer chính thức bằng ToyotaDealerSeeder nếu cần; kiểm tra cập nhật dữ liệu nguồn trước launch.
6. Thiết lập và kiểm tra restore backup cả MySQL và private evidence storage, bảo vệ logs/retention. Chốt timezone/lịch chương trình/thể lệ/chính sách lưu dữ liệu khách theo các quyết định còn mở trong SPEC trước khi có dữ liệu thật. Migrations production dùng migrate, không migrate:fresh.
