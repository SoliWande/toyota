# Admin Dashboard và bảng xếp hạng tháng

Ngày kiểm tra: 20/10/2026. Laravel 13.37.0, PHP Laragon 8.5.11. Branch `upgrade/laravel-13`; giữ nguyên các thay đổi chưa commit từ công việc trước.

## Kết quả audit và nguyên nhân

- Dashboard trước đây hiển thị và truy vấn **tuần**, dù yêu cầu là dẫn đầu tháng. Trang chủ và bảng xếp hạng public cũng mặc định tuần. Đây là sai lệch kỳ thống kê đã xác nhận và đã sửa sang tháng hiện tại.
- **Chưa tái hiện hiện tượng chỉ có một Dealer trên source/database local hiện tại.** Audit trực tiếp bằng `LeaderboardService` trả về 12 Dealer có điểm trong tuần, tháng và toàn chiến dịch. Tháng hiện tại có 117 khai báo approved; Top Dealer có 15, 14, 10 điểm. Top Sales có 10, 10, 6 điểm. Đây là hai nhóm đối tượng khác nhau, không yêu cầu điểm của từng vị trí Top Sales bằng điểm từng vị trí Top Dealer.
- Toàn chiến dịch có 300 khai báo approved, đều thuộc các tài khoản demo được đánh dấu bằng email `demo.sales.*@example.test`. Không có khai báo approved thiếu snapshot `dealer_id` hoặc khác đại lý hiện tại của Sales trên dữ liệu local được audit. Không phát hiện mock/hardcode riêng cho bảng xếp hạng. Không chỉnh sửa hay tạo lại dữ liệu demo.
- `DevelopmentDemoSeeder` tạo khai báo thật trong DB local/test bằng factory; model ghi `dealer_id` tại thời điểm tạo. Cả hai bảng dùng chung `approvedSubmissions`, trạng thái approved và khoảng `submitted_at >= đầu tháng AND submitted_at < đầu tháng tiếp theo`.
- Sales được tổng hợp theo `sales_id`; tên đại lý bên Sales là đại lý hiện tại của tài khoản. Dealer được tổng hợp theo **`customer_submissions.dealer_id` lịch sử**. Khi Sales chuyển đại lý, không suy diễn hay chuyển điểm cũ theo tài khoản.
- Khách QR không có Sales: approved vẫn tăng điểm Dealer lịch sử, không tăng điểm Sales. Bản ghi legacy chưa xác minh Dealer vẫn giữ nguyên quy tắc hiện tại; không tự backfill bằng đại lý hiện tại.
- Lỗi bổ sung đã sửa: danh sách khách hàng mặc định pending. Thẻ tổng/tuần/tháng phải gửi rõ `status=`. Middleware chuyển chuỗi rỗng thành null có thể làm phân trang bỏ tham số này; controller nay giữ `status` tường minh trên mọi liên kết trang tiếp theo.

## Thay đổi chức năng

- Toàn bộ 10 thẻ tổng quan bấm được trên cả vùng thẻ, có nhãn truy cập. Thêm Tổng Sales và Tổng khách hàng. Tổng Sales chỉ gồm role sales; tổng khách hàng là số khai báo mọi trạng thái, cùng đơn vị với danh sách quản trị.
- Các thẻ đang hoạt động/chờ duyệt/đã duyệt gửi đúng bộ lọc trạng thái. Khai báo tuần/tháng gửi ngày bắt đầu và ngày kết thúc bao gồm ngày cuối, mọi trạng thái. Test đi qua các trang và kiểm tra cả `total()` lẫn tổng số hàng thực tế.
- “Dẫn đầu tháng này” dùng Top Sales và Top Dealer của cùng tháng hiện tại. Bằng điểm giữ thứ tự: điểm giảm dần, thời điểm đạt điểm sớm hơn (`MAX(submitted_at)`), ID tăng dần.
- Bỏ tab/xếp hạng tuần ở Admin, trang chủ, leaderboard public và Dashboard Dealer. URL cũ `?period=week` hiển thị tháng để bookmark tiếp tục mở được; UI không còn sinh liên kết tuần. Trang leaderboard vẫn có tháng/toàn chương trình.
- Giữ thẻ **số khai báo tuần** ở Admin vì đây là thống kê khối lượng, không phải bảng xếp hạng tuần. Giữ lịch sử, bộ lọc giải thưởng tuần và dịch vụ tính giải tuần. Không thay đổi `AwardResults`, command hay scheduler. `schedule:list` hiện không có tác vụ được định nghĩa, giống trước sửa.
- Giữ nguyên phân quyền Admin/Dealer/Sales, privacy public, ảnh đại diện và evidence.

## Thời gian và timezone

Nguồn hiện cấu hình `app.timezone = UTC`. Các tháng dùng calendar month của timezone ứng dụng; không tính theo 30 ngày trượt và không đổi sang `reviewed_at`. SQL DATETIME hiện được diễn giải theo quy tắc timezone sẵn có. Không tự đổi timezone toàn hệ thống vì có thể làm thay đổi cách hiểu dữ liệu lịch sử. Tests bao gồm Asia/Ho_Chi_Minh, chuyển mốc UTC sang timezone ứng dụng, hai biên tháng và duyệt trễ sang tháng tiếp theo. Nếu chương trình cần tháng theo giờ Việt Nam trên local/production, cần thống nhất cả timezone và cách diễn giải dữ liệu đã lưu trước khi chuyển cấu hình.

## File thay đổi trong lần này

| Nhóm | File |
|---|---|
| Controller | `app/Http/Controllers/Admin/DashboardController.php` |
| Controller | `app/Http/Controllers/Admin/SubmissionReviewController.php` |
| Controller | `app/Http/Controllers/LandingPageController.php` |
| Controller | `app/Http/Controllers/LeaderboardController.php` |
| Giao diện | `resources/views/admin/dashboard.blade.php` |
| Giao diện | `resources/views/dealer/dashboard.blade.php` |
| Giao diện | `resources/views/leaderboard/index.blade.php` |
| Giao diện | `resources/views/welcome.blade.php` |
| Test cập nhật | `tests/Feature/AdminDashboardTest.php` |
| Test cập nhật | `tests/Feature/LandingPageTest.php` |
| Test cập nhật | `tests/Feature/PublicLeaderboardTest.php` |
| Test cập nhật | `tests/Feature/DevelopmentDemoSeederTest.php` |
| Test mới | `tests/Feature/MonthlyDashboardTest.php` |
| Báo cáo | `MONTHLY_DASHBOARD_REPORT.md`, `WORKING_TREE_CHANGES.md` |

File build và công cụ audit/QA nằm trong thư mục ignored `public/build` và `.laravel-tools/monthly-dashboard`. Không chứa trong danh sách thay đổi source. Các file đã sửa ở lượt trước không bị ghi đè hoặc reset.

## Kiểm thử

- Nhóm liên quan: **69 PASS, 0 FAIL; 591 assertions**.
- Toàn bộ test suite: **669 PASS, 0 FAIL, 0 ERROR, 0 SKIP; 13.312 assertions**, thời gian 3 phút 19 giây. Gồm 112 Unit Tests và 557 Feature Tests. Bật fail-on-warning/deprecation và không ghi test cache.
- Browser Chrome với HTML render từ Laravel test và assets đã build: Admin Dashboard, trang chủ, leaderboard ở **320px, 390px, 1440px** đều không tràn ngang, không có liên kết xếp hạng tuần hoặc lỗi JavaScript. Click cả **10 thẻ** tới đúng URL/bộ lọc. Số lượng thật và phân trang được đối chiếu qua HTTP Feature Tests, không dùng stub browser để kết luận số liệu.
- Kiểm tra **HTTP thật trên Laragon `http://toyota.test`**, không mock: trang chủ và leaderboard đều có đủ 3 Dealer dẫn đầu; bảng tháng có 12 Dealer. Link cũ `?period=week` hiển thị tab tháng. Cả ba viewport không tràn ngang, không lỗi JavaScript. Đã xem ảnh Dashboard 390px để kiểm tra bố cục.
- Vite build: PASS. Pint trên các PHP file trong phạm vi: PASS. `git diff --check`: PASS.
- Route Admin giữ middleware phân quyền, leaderboard là GET/HEAD. Dealer và Sales truy cập Dashboard/danh sách Admin nhận 403; guest chuyển tới đăng nhập (được kiểm tra bởi suite).
- Chạy test bằng PHP 8.5 Laragon, config `.laravel-tools/composer/phpunit-upgrade.xml`, database riêng `toyota_testing_upgrade_20261019` với guard kiểm tra tên DB trước khi chạy. Không chạy lệnh reset database local; fixture/migration của test chỉ chạy trên schema test.

Log và bằng chứng: `.laravel-tools/monthly-dashboard/related.log`, `related.xml`, `full-suite.log`, `full-suite.xml`, `audit-before.json`, `audit-after.json`, `migration-status.log`, `admin-routes.json`, `preservation.log`, `live-guest-results.json`, `qa/results.json`, ảnh responsive trong `qa/`.

## Database và dữ liệu

Không tạo migration, không sửa migration lịch sử, không chạy migration trên DB local, không seed dữ liệu trong lần này. Các migration local hiện có vẫn Ran, mới nhất `2026_10_20_000021_add_public_slug_to_sales_accounts`, batch 5. Không thay đổi production, không reset Git.

Đối chiếu hash trước/sau trên 7 bảng (`users`, `dealers`, `customer_submissions`, `moderation_reviews`, `awards`, `award_winners`, `migrations`) cho kết quả **không đổi**. Giữ nguyên 140 tài khoản, 88 Dealer và 500 khai báo. Kiểm chứng riêng với baseline dữ liệu/ảnh trước đó cũng PASS: bảo toàn 1.001 file evidence, 50 avatar, lịch sử khai báo và điểm Dealer.

## Kiểm tra thủ công trên Laragon

1. Mở `http://toyota.test/login`, đăng nhập Admin bằng tài khoản hiện có rồi vào `/admin/dashboard`.
2. Bấm từng thẻ: kiểm tra trạng thái/ngày trên form lọc và tổng số kết quả. Thử trang 2 ở Tổng khách hàng, kiểm tra vẫn hiển thị mọi trạng thái.
3. Kiểm tra “Dẫn đầu tháng này”, khoảng từ đầu đến cuối tháng, Top 3 Sales và Top 3 Đại Lý. Đối chiếu khách đã duyệt bằng `submitted_at`; khai báo được duyệt muộn vẫn thuộc tháng khai báo.
4. Mở trang chủ, `/leaderboard`, `/leaderboard?period=week`: không có tab tuần; link cũ hiển thị tháng. Kiểm tra thêm tab toàn chương trình.
5. Đăng nhập Dealer/Sales, thử URL Admin và xác nhận bị chặn; Dashboard Dealer chỉ còn xếp hạng tháng. Lịch sử vinh danh tuần vẫn xem được theo quyền.
6. Thử ở 320px, 390px và desktop 1440px. Không cần migrate hoặc seed để dùng thay đổi này.

Vấn đề còn theo dõi: hiện tượng một Dealer chưa tái hiện; không có căn cứ để sửa dữ liệu lịch sử. Không tự đổi UTC sang giờ Việt Nam hay sửa dữ liệu demo để ép các bảng có số dòng giống nhau.
