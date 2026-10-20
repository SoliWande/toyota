# Toyota Community — phạm vi public chỉ có Dealer

> Phạm vi mới theo yêu cầu Sales Public Personal Profile: cho phép riêng `/sales/{public_slug}` và avatar tương ứng công khai tên, avatar, điện thoại đã che, đại lý và thống kê approved giới hạn. Xem [SALES_PUBLIC_PROFILE_REPORT.md](SALES_PUBLIC_PROFILE_REPORT.md). Home, bảng xếp hạng, awards và Landing Page đại lý vẫn giữ phạm vi Dealer; nội dung bên dưới là báo cáo lịch sử trước khi bổ sung profile Sales.

> Cập nhật 20/10/2026: trạng thái mới xem [DEALER_PROFILE_DASHBOARD_REPORT.md](DEALER_PROFILE_DASHBOARD_REPORT.md). Public profile theo slug chỉ công khai Dealer; role Dealer và Dashboard riêng đã được bổ sung trong code, migration local còn chờ xác nhận. Phần bên dưới là báo cáo của lượt triển khai trước.

## Kết quả

- Trang chủ, bảng xếp hạng và vinh danh public chỉ lấy thông tin/thành tích Dealer. Không còn query, thống kê, tên hoặc bảng điểm Sales trong dữ liệu đưa vào view public.
- Giữ bảng xếp hạng cá nhân, khai báo khách hàng, quản lý Sales và snapshot Sales ở các màn hình nội bộ được phân quyền.
- Không thay đổi schema, dữ liệu lịch sử, attribution, điểm số hoặc evidence. Không chạy seed/migration trên database ứng dụng trong lượt sửa này.
- Giữ nguyên thiết kế và component hiện có; bỏ phần Sales và điều chỉnh số cột/nội dung tương ứng.

## Giới hạn baseline về vai trò Dealer

Source hiện tại có hai vai trò đăng nhập `Admin` và `Sales` trong `app/Enums/UserRole.php`. Dealer là model đại lý; chức năng quản lý nhân viên hiện thuộc Admin, chưa có tài khoản/route/policy đăng nhập Dealer riêng. Lượt sửa này bảo toàn baseline đó, không tự thêm vai trò hoặc migration. Vì vậy **chưa thể xác nhận tiêu chí Dealer đăng nhập và quản lý Sales thuộc quyền**; đây là chức năng chưa có trong baseline, không phải chức năng bị xóa bởi thay đổi public.

## Bảo vệ ở backend

1. LandingPageController và LeaderboardController không gọi truy vấn xếp hạng Sales, không đưa `sales`/`topSales` hoặc số tài khoản Sales ra public. Tổng điểm trên trang chủ là tổng điểm Dealer hợp lệ, gồm các bản ghi approved có attribution Dealer.
2. PublicAwardResults chỉ chọn kỳ đã công bố có kết quả Dealer. Chỉ select ID kỳ, loại kỳ, ngày bắt đầu/kết thúc và ngày công bố. Winner chỉ có thứ hạng, điểm, tên/mã Dealer snapshot và khóa kỳ cần cho eager loading; không chọn Sales ID, attribution Sales, publisher hoặc quan hệ Sales.
3. Tên hiển thị winner được lấy từ `dealer_name_snapshot`. Tiêu đề tự do nội bộ không xuất hiện public vì có thể chứa tên nhân viên; public dùng loại kỳ/ngày. Không sửa tiêu đề hoặc snapshot đã lưu.
4. Kỳ chỉ có giải Sales và bản nháp trả 404 ở public, kể cả khi đoán ID; Admin vẫn xem dữ liệu nội bộ theo quyền cũ.
5. Link phân trang chỉ giữ tham số kỳ được phép; selector Sales/format/export tùy ý không mở truy vấn Sales và không được phản chiếu vào link.
6. `/api/user`, quản lý Sales, dashboard/khai báo, hồ sơ và evidence vẫn cần đăng nhập. Guest HTML được chuyển về login, request JSON bị từ chối 401. Không bổ sung API/export public chứa Sales.
7. Các route GET public hiện có gồm trang Dealer, form tài khoản và helper CSRF/health của framework. Health/debug endpoints vẫn bị chặn trong production theo bộ test bảo mật hiện có.
8. Không thay đổi LeaderboardService: chỉ approved tính điểm; Dealer lấy từ `customer_submissions.dealer_id` tại lúc khai báo, không lấy Dealer hiện tại của Sales.

## File thay đổi trong lượt này

Backend:

- `app/Http/Controllers/LandingPageController.php`
- `app/Http/Controllers/LeaderboardController.php`
- `app/Http/Controllers/AwardHistoryController.php`
- `app/Services/PublicAwardResults.php` — mới

Giao diện:

- `resources/views/welcome.blade.php`
- `resources/views/leaderboard/index.blade.php`
- `resources/views/awards/index.blade.php`
- `resources/views/awards/show.blade.php`
- `resources/views/components/award-results.blade.php` — mặc định chỉ có Dealer; Admin truyền Sales như trước
- `resources/views/components/public/layout.blade.php`

Tests:

- `tests/Feature/PublicPrivacyTest.php` — mới, 8 bài test chống rò rỉ/giữ nghiệp vụ
- `tests/Feature/PublicLeaderboardTest.php`
- `tests/Feature/LandingPageTest.php`
- `tests/Feature/AwardWorkflowTest.php`
- `tests/Feature/DealerHistoryTest.php`
- `tests/Feature/DevelopmentDemoSeederTest.php`

Không ghi đè thay đổi Laravel 13 chưa commit từ các lượt trước. Enum, model, policy, routes, middleware, controller nội bộ, migrations và dependencies giữ nguyên trong lượt này.

## Kiểm thử và bảo toàn dữ liệu

- Toàn bộ suite: **500 tests / 4.378 assertions**, exit 0, chạy với `--fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning`.
- PHP 8.5.11 Laragon, PHPUnit 12.5.40, database cách ly `toyota_testing_upgrade_20261019`.
- Test kiểm tra cả HTML và request Accept JSON, dữ liệu view có thể serialize, whitelist field snapshot, truy cập đoán ID, form tài khoản, endpoint riêng tư, export không tồn tại, phân trang, phiên Admin/Sales trên trang public, quản lý Sales của Admin, Sales tạo khai báo và chuyển Dealer rồi duyệt.
- Test nội bộ hiện có tiếp tục xác nhận policy, chống duyệt trùng, chỉ approved tính điểm, snapshot Dealer bất biến, email bất biến và quyền evidence.
- Render qua HTTP kernel local với dữ liệu demo hiện có: 10 response public trả 200, không tìm thấy tên/email/điện thoại của bất kỳ Sales hiện có nào trong HTML. Bao gồm trang chủ, kỳ tuần/tháng/toàn kỳ, lịch sử và bốn kỳ vinh danh.
- Đối soát fingerprint toàn bộ **10 bảng ứng dụng** và SHA-256 **1.001 file evidence** trước/sau: không thay đổi, không mất/thêm bản ghi hay ảnh.
- Kiểm tra assets public HTML/JS/JSON/CSV: không tìm thấy marker Sales demo, `sales_id` hoặc `sales_dealer_id_snapshot`; không thấy export dữ liệu Sales trong public.
- PHP syntax, Pint cho backend/test mới và `git diff --check` đều đạt.

Artifact kiểm chứng local (không commit): `.laravel-tools/public-privacy/full-suite.log`, `full-suite.xml`, `data-before.json`, `data-after.json`, `smoke.json` và các HTML đã render. Snapshot file nguồn trước lượt sửa nằm ở `.laravel-tools/public-privacy/before/` để đối chiếu riêng với thay đổi nâng framework có sẵn.

Lệnh chạy lại toàn bộ test local:

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' vendor/phpunit/phpunit/phpunit -c .laravel-tools/composer/phpunit-upgrade.xml --do-not-cache-result --fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning
```

Chưa commit/push và không thay đổi production VM.
