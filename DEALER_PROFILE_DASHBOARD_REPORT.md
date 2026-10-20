# Public Dealer Profile, QR và Dealer Dashboard

> Cập nhật local: migration 000018–000021 đã được áp dụng theo chỉ dẫn mới, đã tạo 88 tài khoản Dealer và kiểm tra đăng nhập live trên Laragon. Không còn bước chờ duyệt áp dụng local. Xem [LOCAL_DEALER_SETUP_REPORT.md](LOCAL_DEALER_SETUP_REPORT.md); nội dung bên dưới lưu kết quả của giai đoạn triển khai trước đó.

Ngày kiểm tra hoàn tất: 20/10/2026. Branch: `upgrade/laravel-13`.

Code được tiếp tục từ working tree đang có, không reset/revert, không commit/push và không deploy production. Các thay đổi nâng Laravel, địa chỉ khách hàng, ảnh và giao diện của những lượt trước được giữ nguyên.

## Chức năng đã triển khai

- Bổ sung role `dealer` vào hệ thống đăng nhập hiện có; dùng lại bảng `users` và quan hệ `users.dealer_id`.
- Mỗi đại lý có tối đa một tài khoản Dealer, được bảo vệ bằng unique index trên generated column có điều kiện, kể cả khi tài khoản bị khóa.
- Admin tạo tài khoản qua trang chi tiết đại lý → **Quản lý tài khoản Dealer**. Email duy nhất, mật khẩu do Admin nhập riêng và xác nhận, tối thiểu 12 ký tự. Không tự tạo tài khoản, email demo hoặc mật khẩu mặc định. Khôi phục mật khẩu dùng cơ chế hiện có.
- Public profile theo `/{slug}/`, chỉ có thông tin đại lý, ảnh, số khách hàng hợp lệ, điểm và thứ hạng. Không có danh sách Sales, khách hàng hoặc evidence trong HTML/view data; endpoint avatar Sales public cũ đã bị gỡ.
- Slug tự sinh duy nhất khi tạo đại lý; backfill đại lý hiện hữu theo ID tăng dần, xử lý trùng và từ khóa dành riêng. Slug bất biến tại model và database, không đổi khi sửa tên/địa chỉ. URL `/dealers/{id}` cũ chuyển hướng 301 tới URL slug.
- QR PNG chứa URL từ `DEALER_PUBLIC_BASE_URL`, hoặc `APP_URL` khi chưa cấu hình riêng, cộng slug ổn định. Không dùng Host header hoặc URL do caller truyền. Base URL có credentials/query/fragment hoặc scheme khác HTTP(S) bị từ chối. Có endpoint/nút tải PNG.
- Dealer Dashboard có thông tin đại lý, Sales hiện tại, khách hàng theo đại lý ghi nhận khi khai báo, chi tiết khách hàng, Sales khai báo, trạng thái xét duyệt, evidence theo quyền, điểm/thứ hạng tuần/tháng/toàn thời gian và giải thưởng của đại lý đã công bố.
- Dealer không sửa/xóa/duyệt khách hàng, không quản lý Sales qua chức năng Admin và không thay đổi điểm hoặc giải thưởng. Các policy đọc/ghi được tách rõ.
- Truy vấn danh sách/chi tiết nội bộ scope theo Dealer đăng nhập; sửa ID không mở được dữ liệu đại lý khác. Danh sách Sales dựa trên phân công hiện tại; khách hàng và evidence dựa trên `customer_submissions.dealer_id` lịch sử.
- Logic tính điểm, duyệt/chống trùng và công bố giải thưởng được dùng lại, không thay đổi.

## Hai lỗi cuối từ lần chạy trước

1. **GET `/logout` bị route slug bắt nhầm:** đã sửa constraint của route slug để loại các từ khóa hệ thống. GET `/logout` vẫn trả 405; các route đăng nhập/nội bộ không bị slug ghi đè.
2. **Test coi `dealer` là role không hợp lệ:** đã đổi giá trị thử sai thành `unknown_role`. Database vẫn từ chối role ngoài enum; role Dealer được kiểm thử riêng.

Log toàn suite cũ `full-suite.log` ghi 585 tests/2 failures là lịch sử trước hai sửa đổi này. Kết quả cuối của lượt tiếp tục được lưu riêng trong `resume-related.log/xml` và `resume-full-suite.log/xml` để giữ lại bằng chứng cũ.

## Migration

| File | Local `toyota` | Database test riêng |
|---|---|---|
| `2026_10_19_000015_add_customer_address_to_customer_submissions.php` | Đã chạy ở lượt trước | Đã chạy |
| `2026_10_19_000016_add_avatar_path_to_users.php` | Đã chạy ở lượt trước | Đã chạy |
| `2026_10_19_000017_add_photo_path_to_dealers.php` | Đã chạy ở lượt trước | Đã chạy |
| `2026_10_20_000018_support_unique_dealer_accounts_on_users.php` | **Pending — chưa chạy** | Đã chạy/kiểm thử |
| `2026_10_20_000019_add_stable_slug_to_dealers.php` | **Pending — chưa chạy** | Đã chạy/kiểm thử |

Migration 18 mở rộng enum, thêm CHECK Dealer phải có đại lý và generated column `dealer_manager_key` có unique index. Migration 19 thêm/backfill slug, unique index, NOT NULL và trigger chống đổi slug. Không sửa các migration lịch sử. Migration 18 từ chối rollback nếu đã có tài khoản Dealer để tránh làm mất/chuyển đổi role ngoài ý muốn.

**Chưa kích hoạt đầy đủ trên database local hiện tại:** code mới cần hai migration Pending. Không thử Dashboard/public slug trên database đó trước khi được xác nhận áp dụng schema. Không có tài khoản Dealer nào được tự tạo trên local.

## Kiểm thử và bảo toàn

- PHP 8.5.11 của Laragon: `D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe`.
- Database kiểm thử: `toyota_testing_upgrade_20261019`, config `.laravel-tools/composer/phpunit-upgrade.xml`. `Tests\Support\TestDatabase` chặn chạy test trên database khác hoặc ngoài APP_ENV testing. Refresh/rollback trong test backfill chỉ tác động database test này.
- Nhóm tài khoản/đăng nhập trước đó: 71 PASS; public/slug/QR: 22 PASS; Dashboard/lịch sử/backfill: 20 PASS. Sau sửa regression: 86 PASS.
- Lượt tiếp tục: **224 tests liên quan PASS, 0 FAIL; 3.376 assertions**.
- Toàn bộ suite cuối: **590 tests PASS, 0 FAIL, 0 ERROR; 12.371 assertions**, PHP 8.5.11, thời gian 1 phút 51 giây. Chạy với fail-on-warning/deprecation/phpunit-deprecation. Log: `.laravel-tools/dealer-dashboard/resume-full-suite.log`; JUnit: `resume-full-suite.xml`.
- Chi tiết: **478 Feature Tests** (11.955 assertions) và **112 Unit Tests** (416 assertions), đều không có failure/error.
- `route:list` đã kiểm tra và lưu tại `.laravel-tools/dealer-dashboard/routes.json`: Dealer có auth + auth.session + role:dealer + account.active; Sales/Admin giữ group role riêng; evidence có auth/account.active và policy; endpoint avatar Sales public không tồn tại.
- Browser QA dùng HTML tạo từ HTTP Feature Tests và dữ liệu tổng hợp trên database test, không đăng nhập hoặc tạo fixture trên database local. 8 trang × 320/390/1440px, kiểm tra điều hướng, QR giải mã ra đúng URL slug, nút tải PNG và evidence fixture. HTTP authorization/evidence/download được kiểm tra ở Laravel Feature Tests. Kết quả: `.laravel-tools/dealer-dashboard/qa/results.json`.
- Vite build và Pint/diff checks được chạy trong lượt tiếp tục.
- SQL backup trước triển khai: `.laravel-tools/dealer-dashboard/toyota-before-dashboard-implementation.sql` (ignored, không công khai). Snapshot và đối chiếu xác nhận **schema + dữ liệu của cả 10 bảng local không đổi**, **1.001 file evidence nguyên hash**, và không có migration 18/19 trong migration history local. Kết quả: `.laravel-tools/dealer-dashboard/local-preservation.json`.

## File thuộc nhóm chức năng này

Backend/cấu hình sửa:

- `.env.example`
- `app/Enums/UserRole.php`
- `app/Models/User.php`, `app/Models/Dealer.php`
- `app/Policies/CustomerSubmissionPolicy.php`, `app/Policies/DealerPolicy.php`, `app/Policies/UserPolicy.php`
- `app/Http/Requests/Admin/DealerRequest.php`
- `app/Http/Controllers/Admin/DealerController.php`
- `app/Http/Controllers/PublicDealerController.php`, `app/Http/Controllers/DealerProfileMediaController.php`
- `app/Services/DealerProfile.php`
- `database/factories/UserFactory.php`
- `routes/web.php`

Backend mới:

- `app/Http/Controllers/Admin/DealerAccountController.php`
- `app/Http/Requests/Admin/DealerAccountRequest.php`
- `app/Http/Controllers/Dealer/DashboardController.php`
- `app/Http/Controllers/Dealer/SalesController.php`
- `app/Http/Controllers/Dealer/CustomerController.php`
- `app/Http/Controllers/Dealer/AwardController.php`
- `app/Http/Requests/Dealer/SalesIndexRequest.php`
- `app/Http/Requests/Dealer/CustomerIndexRequest.php`
- `app/Services/DealerDashboardData.php`, `app/Services/DealerSlugs.php`
- `config/dealer.php`
- Hai migration 18/19 nêu trên.

UI sửa/mới:

- `resources/views/admin/dealers/show.blade.php`, `account.blade.php`
- `resources/views/dealers/show.blade.php`
- `resources/views/components/public/layout.blade.php`
- `resources/views/components/dealer-profile-media.blade.php`
- `resources/views/components/dealer-nav.blade.php`, `dealer-customer-list.blade.php`, `dealer-awards.blade.php`
- `resources/views/dealer/dashboard.blade.php`
- `resources/views/dealer/sales/index.blade.php`, `show.blade.php`
- `resources/views/dealer/customers/index.blade.php`, `show.blade.php`
- `resources/views/dealer/awards/index.blade.php`

Tests sửa/mới:

- `tests/Feature/DatabaseFoundationTest.php`
- `tests/Feature/DealerAccountTest.php`, `DealerAuthorizationTest.php`, `DealerDashboardTest.php`
- `tests/Feature/DealerProfileTest.php`, `DealerSlugTest.php`, `DealerSlugBackfillTest.php`
- `tests/Feature/DealerBrowserFixturesTest.php`
- `tests/Feature/PublicPrivacyTest.php`, `DashboardNavigationTest.php`

Toàn bộ file đang thay đổi trong Git, gồm các công việc từ trước, được liệt kê riêng trong `WORKING_TREE_CHANGES.md`. Không coi toàn bộ diff nâng Laravel/địa chỉ/avatar là thay đổi mới của lượt này.

## Kiểm thử trên Laragon

### Có thể làm ngay, không thay đổi database local

Mở PowerShell tại project và dùng đúng PHP 8.5:

```powershell
$toyotaPhp = 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe'
& $toyotaPhp vendor/phpunit/phpunit/phpunit -c .laravel-tools/composer/phpunit-upgrade.xml --do-not-cache-result --fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning
& $toyotaPhp artisan route:list --except-vendor
& $toyotaPhp artisan migrate:status
```

Chỉ dùng config PHPUnit trên khi database test đã được tạo đúng tên và thông tin kết nối test hợp lệ. Không trỏ test vào `toyota`.

### Sau khi người dùng xác nhận chạy migration local

1. Sao lưu lại SQL và `storage/app/private` tại thời điểm áp dụng; kiểm tra danh sách migration Pending chỉ có các thay đổi đã duyệt. Không dùng migrate:fresh/db:wipe/seed demo.
2. Áp dụng riêng hai file đã duyệt, theo thứ tự, bằng PHP 8.5:

```powershell
& $toyotaPhp artisan migrate --path=database/migrations/2026_10_20_000018_support_unique_dealer_accounts_on_users.php
& $toyotaPhp artisan migrate --path=database/migrations/2026_10_20_000019_add_stable_slug_to_dealers.php
```

Các lệnh trên **chỉ là hướng dẫn sau phê duyệt, chưa được thực thi trên local**.

3. Giữ `APP_URL=http://toyota.test` cho Laragon. Để `DEALER_PUBLIC_BASE_URL` trống nếu muốn QR local, hoặc đặt thành URL public chính thức đã xác nhận. Điện thoại phải phân giải/truy cập được domain đó; hostname `.test` trên máy tính không tự hoạt động trên điện thoại.
4. Build assets bằng Node tương thích: `& 'C:\Program Files\nodejs\node.exe' node_modules/vite/bin/vite.js build`.
5. Đăng nhập Admin, mở đại lý → **Quản lý tài khoản Dealer**, nhập email thật và mật khẩu riêng. Thử tạo lần thứ hai phải bị từ chối; email của tài khoản đã tạo không được đổi.
6. Đăng nhập Dealer tại `/login`; kiểm tra `/dealer/dashboard`, `/dealer/sales`, `/dealer/customers`, `/dealer/awards`. Thử ID thuộc đại lý khác phải bị chặn; thử URL/chức năng Admin phải 403.
7. Guest mở URL slug, kiểm tra chỉ có đại lý + số hợp lệ/điểm/thứ hạng; tải/quét QR. Thử endpoint avatar Sales public cũ và các endpoint nội bộ.
8. Với dữ liệu kiểm thử được cho phép, chuyển một Sales giữa hai đại lý: nhân sự đổi theo phân công mới; khách hàng, điểm và quyền evidence cũ vẫn thuộc đại lý ghi nhận ban đầu. Sales và Admin tiếp tục dùng các màn hình cũ.

## Trước khi deploy Ubuntu VM (chưa thực hiện)

1. Chốt URL HTTPS chính thức và cấu hình `APP_URL`/`DEALER_PUBLIC_BASE_URL`, không in QR dùng hostname local để phát hành bên ngoài.
2. Rehearsal trên bản sao database với đúng PHP 8.5/MySQL mục tiêu; kiểm tra CHECK, generated-column unique index và quyền tạo trigger. Chạy suite và so sánh dữ liệu/ảnh trước-sau.
3. Kiểm tra PHP extensions, đặc biệt GD để tạo QR/ảnh, cùng `composer check-platform-reqs`; cài dependency theo lock và build assets.
4. Sao lưu SQL + toàn bộ private storage; chuẩn bị khoảng bảo trì và kế hoạch khôi phục/forward fix. Không tự rollback role khi đã có tài khoản Dealer.
5. Nginx trỏ DocumentRoot vào `public/`; bảo vệ source, `.env`, backup và private storage. Chỉ cấp quyền ghi cần thiết cho `storage`/`bootstrap/cache`. `APP_DEBUG=false`, HTTPS và cấu hình session/cookie phù hợp.
6. Chỉ sau phê duyệt triển khai mới áp dụng migration và smoke test đủ Public/Dealer/Sales/Admin. Tài khoản Dealer do Admin cấp với email/mật khẩu riêng. Không chạy seed demo trên production.
