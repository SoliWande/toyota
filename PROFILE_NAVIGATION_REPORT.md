# Điều hướng profile và chi tiết theo quyền

Ngày: 20/10/2026. Laravel 13, PHP Laragon 8.5.11. Giữ nguyên working tree trên branch `upgrade/laravel-13`, gồm công việc chưa commit từ các lượt trước.

## Phạm vi triển khai

Audit xác nhận danh sách Dealer, Sales, khách hàng và Top 3 đã có nhiều liên kết đúng. Giữ các liên kết này, bổ sung những tên còn là văn bản: Sales/Dealer trong khai báo và Dashboard Admin, Dealer bên cạnh Top Sales, Dealer trên Dashboard Sales/Dealer, các kết quả vinh danh và khách hàng lịch sử. Không tạo chức năng hoặc bảng mới.

- Dùng `<a href>` thông thường, focus bằng bàn phím, mở tab mới và hoạt động khi không có JavaScript. Không dùng click handler, không ghép email/số điện thoại/tên khách hoặc nội dung tìm kiếm vào URL profile.
- Admin mở trang quản trị Dealer/Sales/khách hàng hiện có. Profile Sales nội bộ có thêm liên kết “Xem profile công khai” khi Sales còn active và có slug.
- Dealer mở Sales hiện đang thuộc quyền trên trang nội bộ. Với Sales đã chuyển sang đại lý khác, khách hàng lịch sử vẫn thuộc Dealer cũ; tên Sales chỉ dẫn sang public profile nếu Sales active. Sales blocked/pending/rejected hoặc thiếu slug không có liên kết public gây 404. Dealer không được mở trang nhân sự nội bộ của đại lý khác.
- Sales mở public profile của chính mình từ tên trên Dashboard, mở public Dealer Profile từ tên đại lý; chi tiết khách hàng vẫn dùng route Sales và policy sở hữu.
- Public giữ các link Dealer có sẵn trong directory/leaderboard, bổ sung link Dealer trong vinh danh. Public không thêm danh sách Sales hoặc khách hàng. Sales Public Profile riêng vẫn chỉ hiển thị các trường cho phép, điện thoại được che.
- Tên/thành tích trong vinh danh giữ nguyên snapshot. Link mở hồ sơ hiện tại, không thay tên lịch sử bằng tên hiện tại. Public award projection chỉ thêm `dealer_id` công khai để tạo link; không select thêm User, email, điện thoại Sales, Sales ID hoặc quan hệ nội bộ.
- Khách QR không Sales và khai báo legacy chưa xác minh Dealer hiển thị nhãn thay thế; không sinh href rỗng hoặc link đến ID giả.
- Thêm trường cần thiết vào eager-load nội bộ để chọn đích Sales mà không phát sinh truy vấn từng hàng. Không đổi logic tính điểm, duyệt, chuyển Dealer, QR, slug, scheduler hoặc schema.

## Route được sử dụng

Không tạo hoặc sửa route. Các URL profile dùng ID/slug hiện có; không có thông tin cá nhân trong URL mới.

| Method / URL | Route name | Phạm vi |
|---|---|---|
| GET `/dealers/{dealer}` | `dealers.show` | Public; 301 đến slug ổn định |
| GET `/{slug}/` | `dealers.profile` | Public Dealer Profile / Landing Page |
| GET `/sales/{slug}` | `sales.public.show` | Public Sales active, slug `s-` + 32 ký tự hex |
| GET `/sales/{slug}/avatar` | `sales.public.avatar` | Avatar được phép công khai |
| GET `/admin/dealers/{dealer}` | `admin.dealers.show` | Admin active |
| GET `/admin/sales/{sales}` | `admin.sales.show` | Admin active |
| GET `/admin/submissions/{submission}` | `admin.submissions.show` | Admin active, policy review |
| GET `/dealer/dashboard` | `dealer.dashboard` | Dealer active, đại lý của tài khoản |
| GET `/dealer/sales/{sales}` | `dealer.sales.show` | Dealer active, Sales hiện tại thuộc đại lý |
| GET `/dealer/customers/{submission}` | `dealer.customers.show` | Dealer active, snapshot Dealer của khai báo |
| GET `/sales/submissions/{submission}` | `sales.submissions.show` | Sales active sở hữu khai báo |
| GET `/submissions/{submission}/evidence` | `submissions.evidence` | Đăng nhập, active và policy; không public |

Dealer slug nằm sau các route hệ thống và loại trừ các tên reserved. Sales slug theo pattern riêng không bắt `/sales/dashboard`, `/sales/submissions` hoặc avatar ID nội bộ. Tests kiểm tra `/login`, GET `/logout` trả 405 đúng thiết kế POST-only, Dashboard các vai trò, slug Dealer tên Login và Sales slug hợp lệ/không hợp lệ. Không sửa reserved list hoặc đổi slug đã phát hành.

## File thay đổi trong lượt này

| File | Thay đổi |
|---|---|
| `app/Http/Controllers/Admin/DashboardController.php` | Lấy Dealer ID cùng truy vấn avatar Top Sales để tạo link, không thêm query |
| `app/Services/DealerDashboardData.php` | Eager-load trạng thái, slug và Dealer hiện tại của Sales trong dữ liệu nội bộ |
| `app/Services/PublicAwardResults.php` | Allowlist thêm Dealer ID công khai |
| `resources/views/components/dealer-link.blade.php` | Component mới chọn link Dealer theo ngữ cảnh/quyền |
| `resources/views/components/sales-link.blade.php` | Component mới chọn link Sales theo ngữ cảnh/quyền và trạng thái |
| `resources/views/components/public/ranking-preview.blade.php` | Tên Dealer cạnh Top Sales bấm được |
| `resources/views/components/award-results.blade.php` | Tên trong preview/snapshot vinh danh bấm được |
| `resources/views/components/dealer-awards.blade.php` | Link Dealer trong thành tích nội bộ |
| `resources/views/components/dealer-customer-list.blade.php` | Link Sales theo quyền, gồm trường hợp đã chuyển Dealer |
| `resources/views/admin/dashboard.blade.php` | Link tên khách, Sales, Dealer ở danh sách gần đây và truyền ID Top Sales |
| `resources/views/admin/submissions/index.blade.php` | Link Sales/Dealer trên hàng khách hàng |
| `resources/views/admin/submissions/show.blade.php` | Link Sales/Dealer trong chi tiết và kết quả trùng khách |
| `resources/views/admin/sales/index.blade.php` | Link tên Dealer |
| `resources/views/admin/sales/show.blade.php` | Link Dealer và public profile khi hợp lệ |
| `resources/views/admin/dealers/show.blade.php` | Link Sales từ danh sách khách hàng lịch sử |
| `resources/views/dealer/dashboard.blade.php` | Link tên đại lý đến public profile |
| `resources/views/dealer/sales/index.blade.php` | Link tên đại lý |
| `resources/views/dealer/sales/show.blade.php` | Link Dealer và public profile khi hợp lệ |
| `resources/views/dealer/customers/index.blade.php` | Link tên đại lý |
| `resources/views/dealer/customers/show.blade.php` | Link Sales và Dealer theo quyền |
| `resources/views/dealer/awards/index.blade.php` | Link tên đại lý |
| `resources/views/sales/dashboard.blade.php` | Link tên Sales và Dealer |
| `tests/Feature/ProfileNavigationTest.php` | 9 tests mới cho tên/link, quyền, lịch sử, privacy, phân trang, collision và browser fixtures |
| `tests/Feature/PublicPrivacyTest.php` | Cập nhật allowlist Dealer ID; giữ kiểm tra chống lộ dữ liệu Sales |
| `PROFILE_NAVIGATION_REPORT.md`, `WORKING_TREE_CHANGES.md` | Báo cáo và inventory |

Các script, log, HTML QA và ảnh chụp ở `.laravel-tools/profile-navigation`, assets `public/build` là ignored. Những file sửa từ công việc cũ không bị reset/revert.

## Kiểm thử và dữ liệu

- Nhóm liên quan ban đầu: **79 PASS, 0 FAIL; 2.389 assertions**. Sau đó bổ sung fixture QR thiếu Sales vào test fallback để kiểm tra trong toàn bộ suite.
- Toàn bộ suite: **678 PASS, 0 FAIL, 0 ERROR, 0 SKIP; 13.467 assertions**, 3 phút 29 giây. Gồm 112 Unit Tests và 566 Feature Tests, có fail-on-warning/deprecation.
- Browser Chrome với HTML render từ Laravel test và assets đã build: Dashboard Admin và danh sách khách hàng Dealer ở **320px, 390px, 1440px**, không tràn ngang, không lỗi JavaScript. Click 5 liên kết tên Sales/Dealer/khách hàng tới đúng URL; điều hướng vẫn hoạt động khi tắt JavaScript. Đã xem ảnh Dealer 390px.
- Ctrl+click tạo tab mới. Giới hạn QA: tab mới của Chrome gửi request thật trước khi fixture interception được gắn, nên khi không có cookie đăng nhập Laragon, tab chuyển tới `/login` đúng guard. Không dùng browser fixture để kết luận đã đăng nhập thật hoặc mở hồ sơ nội bộ thật; quyền và response 200/403/404/401 được xác minh bằng HTTP Feature Tests trên DB test. Timeout ban đầu của bước browser là chờ nhầm URL nội bộ trong tab guest; không sửa code ứng dụng để vượt guard.
- Vite build, Pint các PHP file trong phạm vi, `git diff --check`: PASS.
- HTTP Feature Tests kiểm tra URL đích thật và policy: Admin chi tiết 200, Dealer đúng quyền 200, Dealer khác 404, Sales khác 403, guest HTML chuyển login/JSON 401. 403/404 bảo mật được mong đợi; không có lỗi với liên kết được hiển thị hợp lệ.
- Test phân trang/tìm kiếm/bộ lọc đi qua trang 2 của danh sách khách hàng Admin và Dealer rồi mở chi tiết; href profile chỉ gồm ID/slug, không sao chép query tìm kiếm.
- Tests chạy PHP 8.5 Laragon trên `toyota_testing_upgrade_20261019`, có guard xác nhận DB test; không chạy migration hoặc seed trên database ứng dụng local.
- Không có migration mới, không sửa schema, không seed/reset dữ liệu, không deploy hoặc thay đổi production. Đối chiếu hash 7 bảng trước/sau xác nhận không đổi: users, dealers, customer_submissions, moderation_reviews, awards, award_winners, migrations. Giữ 140 tài khoản, 88 Dealer và 500 khai báo. Kiểm chứng ảnh/historical baseline cũng PASS: 1.001 file evidence, 50 avatar và điểm Dealer được bảo toàn.

Bằng chứng: `.laravel-tools/profile-navigation/related.log`, `related.xml`, `full-suite.log`, `full-suite.xml`, `routes.json`, `audit-before.json`, `audit-after.json`, `qa/results.json` và ảnh viewport trong `qa/`.

Không phát hiện lỗi ứng dụng còn tồn tại trong phạm vi kiểm tra. 404 khi Dealer đoán ID ngoài phạm vi hoặc mở Sales không còn public là kết quả bảo mật mong đợi; giao diện không sinh liên kết đến các trang đó.

## Kiểm tra thủ công trên Laragon

1. Admin vào Dashboard, bấm tên khách/Sales/Dealer trong danh sách chờ và Top 3; thử Ctrl+click hoặc mở tab mới. Tìm kiếm/lọc khách rồi chuyển trang 2 và mở chi tiết.
2. Admin mở Sales, bấm tên đại lý và “Xem profile công khai”. Tài khoản Sales không active không có nút public profile.
3. Dealer mở Dashboard và danh sách khách hàng; bấm tên Sales thuộc quyền để mở nội bộ. Với Sales chuyển Dealer, link chỉ mở public profile nếu active, không mở hồ sơ nhân sự nội bộ của đại lý khác.
4. Sales mở Dashboard, bấm tên mình và tên Dealer; khách hàng vẫn mở qua mục khai báo riêng.
5. Đăng xuất, mở các public profile và kết quả vinh danh: chỉ thấy dữ liệu công khai. Thử URL khách hàng nội bộ để xác nhận bắt đăng nhập. Không tạo URL khách hàng public.
6. Không cần migrate hoặc seed để dùng thay đổi. Múi giờ và nghiệp vụ điểm giữ nguyên cấu hình hiện tại.
