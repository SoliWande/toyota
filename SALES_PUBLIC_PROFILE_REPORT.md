# Sales Avatar và Sales Public Personal Profile

> Cập nhật local: migration 000018–000021 đã Ran, slug Sales đã backfill và Public Profile đã mở thành công trên Laragon. Xem [LOCAL_DEALER_SETUP_REPORT.md](LOCAL_DEALER_SETUP_REPORT.md). Các ghi chú pending/chờ xác nhận bên dưới là trạng thái lịch sử, không còn áp dụng.

Ngày 20/10/2026. Source Laravel 13.37.0, PHP Laragon 8.5.11; branch `upgrade/laravel-13`. Không deploy hoặc thay đổi production.

## Kết quả audit và phạm vi

- Đã có `avatar_path`, disk `sales_avatars`, service chuẩn hóa ảnh `SalesAvatars`, upload trong `/profile`, endpoint avatar nội bộ có authorization và component SVG placeholder. Tái sử dụng toàn bộ phần này; không tạo bảng hoặc disk trùng, không di chuyển ảnh hiện có.
- Hệ thống thống kê chỉ đếm trạng thái approved và chia kỳ theo **submitted_at**, không phải reviewed_at. Profile mới giữ quy tắc này. Thời gian theo timezone ứng dụng, tháng dùng khoảng đầu tháng bao gồm đến đầu tháng sau loại trừ.
- Chưa có định danh public ổn định cho Sales. Thêm duy nhất một cột `users.public_slug`; slug ngẫu nhiên độc lập email, điện thoại, tên, ID tài khoản và APP_KEY. Đổi tên, điện thoại hoặc đại lý không đổi URL.
- Yêu cầu mới cho phép công khai profile cá nhân Sales. Các trang home, bảng xếp hạng, awards và Dealer Landing Page tiếp tục chỉ hiển thị dữ liệu Dealer; không bổ sung danh sách/bảng xếp hạng Sales public.

## Chức năng

- Sales đang hoạt động đăng nhập `/profile` để upload/thay avatar và lấy link “Xem profile public của tôi”. Email vẫn không thể sửa.
- Admin đang hoạt động sửa Sales tại `/admin/sales/{id}/edit`, có thể upload/thay avatar trong form hiện có. Dealer hoặc Sales khác không được gọi endpoint Admin hay gán user_id/avatar_path/public_slug để sửa người khác.
- Public URL dạng `/sales/s-<32 ký tự hex ngẫu nhiên>/`. Chỉ tài khoản role Sales, trạng thái active có profile và public avatar; pending/rejected/blocked hoặc ID số không truy cập được trang mới.
- Public hiển thị tên, đại lý hiện tại, avatar/placeholder, số điện thoại chỉ lộ tối đa ba số cuối, tổng khách approved và ba tháng gần nhất gồm tháng hiện tại, kể cả tháng 0 khách.
- Thống kê chỉ lấy `sales_id` của đúng người, `source=sales`, `status=approved`; không lấy khách từ QR không có Sales. Chuyển đại lý giữ tổng/tháng của Sales, trong khi Dealer tiếp tục nhận điểm từ dealer_id lịch sử của submission. Không sửa LeaderboardService hoặc quy tắc giải thưởng.
- Blade chỉ nhận array projection gồm thông tin đã cho phép công khai và các số đếm. Không truyền User model, email, điện thoại gốc, mã tài khoản, đường dẫn file, khách hàng hoặc evidence vào view public. Yêu cầu Accept: application/json vẫn nhận nội dung an toàn, không serialize User.

## Avatar và bảo mật

- Upload JPG, PNG, WebP: kiểm tra file, ảnh, MIME thực, định dạng, tối đa 5 MB và 4096 × 4096px. Không chấp nhận SVG, nội dung giả ảnh, quá dung lượng/kích thước.
- Tái mã hóa thành JPEG tối đa 512px, bỏ metadata/nội dung dư, tên UUID do server tạo. Không dùng tên file client làm đường dẫn storage.
- Thay ảnh trong transaction; file cũ chỉ xóa sau commit, file mới được dọn nếu cập nhật thất bại. Chỉ thao tác disk sales_avatars.
- Disk vẫn ở `storage/app/private/sales-avatars`. Endpoint mới `/sales/{public_slug}/avatar` chỉ đọc ảnh của Sales active được tìm theo slug; trả JPEG, nosniff, no-store/private. Cách này phục vụ ảnh public nhưng không mở cả thư mục hoặc cache avatar của tài khoản vừa bị khóa.
- Endpoint nội bộ `/sales/{numeric_id}/avatar` giữ nguyên yêu cầu đăng nhập/policy. Evidence vẫn là disk và route private riêng. Không có endpoint public danh sách khách hàng, evidence hoặc export mới.
- Placeholder SVG dùng chung; ảnh lỗi/mất file sẽ được thay bằng placeholder nhờ JavaScript hiện có.
- Tài khoản demo dự trữ `demo.sales.NN@example.test` dùng placeholder thay cho ảnh AI demo cũ, dù tên đã đổi. Khi Sales/Admin chủ động upload ảnh mới thì ảnh đã chọn được phép hiển thị, kể cả tài khoản demo. Tên file do server tạo có prefix `uploaded-`; seeder avatar demo được đánh dấu `demo-` để không bị coi là ảnh người dùng chọn. Không chạy seeder trên local, không tạo AI mới và không xóa ảnh cũ trong lần triển khai này.
- Public profile/avatar có rate limit 120 requests/phút/IP; URL chỉ chứa slug, không chứa dữ liệu liên hệ. Route slug có pattern riêng, không bắt nhầm dashboard/submissions hoặc private avatar số.

## Migration

Mới: `database/migrations/2026_10_20_000021_add_public_slug_to_sales_accounts.php`.

- Thêm `users.public_slug VARCHAR(34) NULL`, unique index `users_public_slug_unique`.
- Backfill các Sales hiện có bằng `s-` + UUID v4 bỏ dấu gạch. Admin/Dealer không cần slug.
- Backfill qua query builder để không đổi updated_at, email, mật khẩu, vai trò hoặc dữ liệu cũ. Tài khoản Sales tạo mới được model cấp slug tự động.
- Chặn đổi slug ở cả User model, validation và trigger `users_immutable_public_slug`.
- Không sửa bất kỳ migration lịch sử nào. Không rollback/recreate cột slug sau khi đã phát hành URL, vì tạo lại sẽ sinh URL khác.
- **Chỉ chạy trên toyota_testing_upgrade_20261019. Chưa chạy migration trên database local toyota**, theo yêu cầu xác nhận riêng đã có từ trước.
- Local còn pending `000018`, `000019`, `000020` từ các giai đoạn trước và `000021` mới. Cần phê duyệt áp dụng schema trước khi kiểm tra tính năng đầy đủ bằng dữ liệu local.

## Kiểm thử

Kết quả cuối trên PHP Laragon 8.5.11: **655 PASS, 0 FAIL, 0 ERROR, 12.902 assertions**; thời gian 3 phút 15,735 giây. Exit code 0 với fail-on-deprecation, fail-on-phpunit-deprecation và fail-on-warning.

| Nhóm | PASS | FAIL / ERROR | Assertions |
|---|---:|---:|---:|
| Unit | 112 | 0 | 416 |
| Feature | 543 | 0 | 12.486 |
| Tổng | 655 | 0 | 12.902 |

- Nhóm avatar/profile/Admin/privacy đầu tiên: **101 PASS, 0 FAIL, 2.291 assertions**.
- Nhóm profile mới + backfill slug: **21 PASS, 0 FAIL, 174 assertions**. Backfill kiểm chứng không thay đổi các field tài khoản/khách hàng/evidence path cũ; slug duy nhất, ổn định và có chốt database.
- Browser QA trên fixture HTML/ảnh từ DB test riêng: **320/390/768/1440px**, cả avatar và placeholder; không tràn ngang, không lộ điện thoại/email, đủ ba tháng, không lỗi JavaScript; ảnh 404 quay về placeholder. Browser mô phỏng network, không gửi dữ liệu vào toyota.
- Nhóm kiểm tra sau khi bổ sung phân biệt upload/demo: **30 PASS, 0 FAIL, 246 assertions**. Suite đầy đủ cuối cùng ở trên đã chạy lại trên bản cuối này.
- Build Vite, Pint cho các file thay đổi, `git diff --check` đều đạt. `route:list` đã kiểm tra hai route public slug/throttle và việc giữ auth/policy cho private avatar/evidence.
- Dealer Landing Page regression browser ở 320/390/768/1440px cũng đạt: form, consent, danh mục phường/xã và QR đúng URL. Không thay đổi QR đã phát hành.
- Snapshot trước/sau xác nhận schema và dữ liệu **10 bảng local không đổi**, **1.001 file evidence được bảo toàn**; xem `.laravel-tools/sales-public-profile/local-preservation.json`. Bản backup SQL và snapshot nằm trong thư mục ignored, không commit dữ liệu hoặc thông tin môi trường.
- Tests bao phủ JPG/PNG/WebP hợp lệ, MIME giả/SVG/dung lượng/kích thước, owner/Admin, IDOR, demo avatar, placeholder, mask số, HTML/JSON privacy, approved/pending/rejected, tháng 0, ranh giới năm, submitted_at khác reviewed_at, QR, chuyển đại lý, unique/immutable slug và backfill.
- Log/artifact: `.laravel-tools/sales-public-profile/related.log`, `profile-tests.log`, `full-suite.log`, `full-suite.xml`, `qa/results.json` và ảnh QA. Test config `.laravel-tools/composer/phpunit-upgrade.xml` ép schema test riêng; có guard TestDatabase. Không migrate/reset/seed trên DB đang phục vụ local.

## File thay đổi trong lần này

| File | Nội dung |
|---|---|
| `app/Services/SalesPublicProfile.php` (mới) | Projection an toàn, mask, thống kê, lọc ảnh demo |
| `app/Http/Controllers/PublicSalesProfileController.php` (mới) | Profile public và stream avatar theo slug |
| `resources/views/sales/public-profile.blade.php` (mới) | Template mobile đồng bộ public Dealer |
| `app/Models/User.php` | Cấp slug và chặn đổi slug |
| `database/migrations/2026_10_20_000021_add_public_slug_to_sales_accounts.php` (mới) | Cột, backfill, unique, trigger |
| `app/Services/SalesAvatars.php` | Rules upload dùng chung, tái sử dụng encode/storage hiện tại |
| `database/seeders/DevelopmentSalesAvatarSeeder.php` | Phân biệt file demo tự gán với upload chủ động, không chạy seeder trên local |
| `app/Http/Requests/ProfileRequest.php` | Rules ảnh dùng chung, không nhận slug từ client |
| `app/Http/Requests/Admin/UpdateSalesRequest.php` | Rules avatar Admin và trường bảo vệ |
| `app/Http/Controllers/Admin/SalesController.php` | Cập nhật avatar theo policy/transaction |
| `resources/views/profile/edit.blade.php` | Thông báo phạm vi public và link profile |
| `resources/views/admin/sales/edit.blade.php` | Upload avatar Admin, multipart và link public |
| `routes/web.php` | Hai route public slug, giữ private route cũ |
| `app/Providers/RouteServiceProvider.php` | Rate limit public profile/avatar |
| `tests/Feature/SalesPublicProfileTest.php` (mới) | Feature Tests chức năng, bảo mật và browser fixtures |
| `tests/Feature/SalesPublicSlugMigrationTest.php` (mới) | Kiểm chứng backfill bảo toàn dữ liệu |
| `tests/Feature/PublicPrivacyTest.php` | Danh mục route public mới đúng phạm vi được phép |
| `SALES_PUBLIC_PROFILE_REPORT.md` (mới), `PUBLIC_PRIVACY_REPORT.md`, `WORKING_TREE_CHANGES.md` | Báo cáo, ghi chú thay đổi phạm vi public và inventory toàn working tree |

Các thay đổi chưa commit của giai đoạn trước được giữ nguyên. Danh sách tổng thể so với HEAD trong WORKING_TREE_CHANGES.md không chỉ bao gồm tính năng Sales lần này. Không thêm package.

Không còn lỗi kiểm thử. Bước còn chờ: phê duyệt chạy các migration local pending để sử dụng đầy đủ bằng dữ liệu toyota. Chưa chạy migration local, reset/seed dữ liệu hay deploy production.

## Kiểm tra thủ công trên Laragon

1. Hiện tại có thể chạy test riêng bằng PHP 8.5 và config test bên dưới. Để kiểm tra với tài khoản/data toyota, trước hết cần xác nhận riêng chạy bốn migration đang pending; chưa thực hiện trong lần này. Backup DB và storage trước khi áp dụng schema.
2. Sau khi schema được áp dụng: đăng nhập Sales active → `/profile`. Upload JPG/PNG/WebP, lưu và mở “Xem profile public của tôi” trong cửa sổ ẩn danh. Kiểm tra tên/đại lý, điện thoại chỉ còn ba số cuối, avatar và ba tháng.
3. Thay avatar, tải lại profile public: ảnh mới xuất hiện. Thử SVG, file text đổi đuôi JPG, ảnh >5 MB hoặc >4096px phải báo lỗi. Không có avatar thì hiện icon chung.
4. Đăng nhập Admin → Sales → Chỉnh sửa. Kiểm tra upload/thay avatar. Dealer/Sales khác không thể PATCH endpoint Admin hoặc chèn user_id vào form profile để sửa người khác.
5. Tạo/duyệt dữ liệu kiểm tra chỉ trên DB test hoặc dùng lịch sử hiện có: pending/rejected không tính; approved tính theo tháng submitted_at. Kiểm tra có tháng 0, chuyển đại lý không đổi URL/tổng cá nhân và không chuyển điểm Dealer cũ.
6. Khóa Sales bằng chức năng Admin hiện có: profile và avatar public phải 404. Guest không lấy được private avatar số hoặc evidence; không có email/số đầy đủ/dữ liệu khách trong HTML hoặc response public.
7. Tài khoản demo có ảnh AI cũ vẫn hiện ảnh ở khu vực nội bộ theo quyền; public dùng icon placeholder. Chủ động upload một ảnh mới thì public hiển thị ảnh đã chọn.

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' vendor/phpunit/phpunit/phpunit -c .laravel-tools/composer/phpunit-upgrade.xml --filter 'SalesPublicProfileTest|SalesPublicSlugMigrationTest|SalesAvatarTest' --do-not-cache-result
```

Trước production: review toàn working tree, thử migration trên bản sao DB trong cửa sổ bảo trì, giữ nguyên các slug đã phát hành, MySQL cho phép tạo trigger, cấu hình APP_URL HTTPS và trusted proxy/rate limit đúng, APP_DEBUG=false. Nginx chỉ phục vụ public; không mở private storage. Chưa SSH hoặc deploy.
