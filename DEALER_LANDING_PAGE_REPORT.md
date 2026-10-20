# Dealer Landing Page — Toyota Community

> Cập nhật local: migration 000018–000021 đã Ran trên toyota, 88 tài khoản Dealer đã được tạo và Landing Page đã kiểm tra live thành công. Xem [LOCAL_DEALER_SETUP_REPORT.md](LOCAL_DEALER_SETUP_REPORT.md). Các ghi chú pending/chờ xác nhận bên dưới là trạng thái lịch sử, không còn áp dụng.

Ngày 20/10/2026. Laravel 13.37.0; PHP Laragon 8.5.11. Không deploy production.

## Luồng đã triển khai

- Dùng chung template tại URL slug hiện có của từng đại lý; ảnh, địa chỉ, điện thoại, số khách hợp lệ, điểm và thứ hạng giữ projection public hiện tại. Đại lý ngừng hoạt động vẫn có profile lịch sử nhưng không nhận đăng ký.
- Form bắt buộc họ tên, điện thoại Việt Nam, tỉnh/thành + phường/xã + địa chỉ chi tiết, xe đang sử dụng và đồng ý xử lý thông tin. Danh mục hành chính dùng dữ liệu có sẵn, không gọi dịch vụ bên ngoài.
- Đại lý được tìm từ slug phía server. Không nhận dealer_id/sales_id/source/status/điểm/dữ liệu duyệt từ trình duyệt. Không tạo tài khoản hay gán Sales giả.
- Bản ghi `source=dealer_qr`, `sales_id=NULL`, `status=pending`; dealer_id được giữ cố định. Lưu thời gian và phiên bản nội dung đồng ý. Xe QR là mô tả tự do để không thay đổi danh mục xe Toyota và yêu cầu evidence của luồng Sales.
- Admin bổ sung **Facebook cá nhân đã xác minh** trong thao tác duyệt QR. Nếu thiếu, sai hoặc trùng bản ghi đã duyệt thì vẫn pending. Facebook được lưu cùng thao tác duyệt trong transaction có khóa; moderation review vẫn append-only như cũ. Admin có thể từ chối QR chưa có Facebook.
- Chỉ bản ghi approved mới được tính một điểm Dealer; QR không có điểm Sales. Bộ lọc kỳ, thứ tự xếp hạng và cách chốt giải giữ nguyên. Giải đã công bố không bị ghi lại.
- Dealer chỉ thấy đăng ký của đại lý mình; Admin vẫn quản trị; Sales không có quyền xem/sửa đăng ký QR không thuộc mình. Không cập nhật dealer_id lịch sử khi Sales chuyển đại lý.
- QR và slug không đổi khi cập nhật tên/địa chỉ; vẫn tải PNG bằng chức năng cũ.

## Chống spam và bảo vệ dữ liệu

- CSRF thực, honeypot; giới hạn 5 POST/phút và 20 POST/giờ/IP, bao gồm yêu cầu không hợp lệ. Endpoint danh mục phường/xã giới hạn 60 GET/phút/IP.
- `Cache::add` chặn gửi lặp cùng điện thoại chuẩn hóa + đại lý trong 10 phút. Khóa cache dùng HMAC; không lưu số điện thoại thô trong tên khóa. Nếu lưu thất bại thì bỏ khóa chờ để cho phép thử lại.
- Điện thoại không thay thế định danh Facebook. Pending có thể trùng sau thời gian chờ, đúng quy tắc hiện có; chỉ một Facebook được duyệt toàn hệ thống nhờ unique index cũ. Không áp đặt unique điện thoại hoặc từ chối vĩnh viễn người dùng chung số.
- POST redirect về profile + `#registration`; không đưa thông tin cá nhân vào URL hoặc trả JSON bản ghi. Thông báo lỗi không nhắc lại giá trị đầu vào; không flash thông tin form public. Khi lỗi người dùng cần nhập lại thông tin.
- Profile không phục hồi old input từ form Sales nội bộ. Không có analytics mới hoặc lưu form trong localStorage. Response form/đăng ký `no-store, private`.
- Lỗi public POST được trả thông báo chung ngay cả khi local bật APP_DEBUG. Logger không ghi request payload, SQL bindings hay exception message chứa thông tin cá nhân của luồng đăng ký.
- Không có public danh sách/chi tiết/export khách hàng hoặc Sales. Evidence vẫn dùng disk riêng và endpoint yêu cầu đăng nhập, kiểm tra policy; QR không yêu cầu/tạo evidence giả.

## Migration mới và trạng thái local

`database/migrations/2026_10_20_000020_support_dealer_qr_registrations.php`

| Thay đổi | Mục đích |
|---|---|
| sales_id nullable, vẫn giữ FK users | Đăng ký không có Sales |
| facebook_url / facebook_url_normalized nullable | Chờ Admin xác minh QR trước khi duyệt |
| source enum sales/dealer_qr, default sales | Phân biệt nguồn; dữ liệu cũ thuộc nguồn Sales |
| phone_normalized varchar(16) nullable + index | Điện thoại QR chuẩn hóa; không unique |
| vehicle_description varchar(255) nullable | Xe do khách QR nhập, không thay đổi luật xe Sales |
| consented_at datetime nullable | Thời điểm đồng ý phía server |
| consent_version varchar(40) nullable | Nội dung đồng ý đã áp dụng |
| CHECK nguồn/định danh khi approved | QR không gắn Sales; không approved nếu Facebook NULL |

Giữ nguyên generated column `approved_facebook_identity` và unique index `submissions_approved_profile_unique`. Không sửa migration lịch sử. Rollback từ chối nếu vẫn có đăng ký QR; không xóa QR để rollback. Khi chưa có QR, roundtrip giữ nguyên dữ liệu, evidence path và các bản ghi lịch sử chưa xác minh được Dealer.

Đã thử migration trên **toyota_testing_upgrade_20261019**. **Chưa chạy trên database local toyota.** Local còn pending cả:

1. `2026_10_20_000018_support_unique_dealer_accounts_on_users` — từ công việc trước.
2. `2026_10_20_000019_add_stable_slug_to_dealers` — từ công việc trước.
3. `2026_10_20_000020_support_dealer_qr_registrations` — mới trong lần này.

Vì local chưa có slug/schema mới, cần xác nhận riêng trước khi áp dụng các migration trên toyota; không thể dùng đầy đủ trang mới trên schema cũ.

## Kiểm thử và bằng chứng

Kết quả cuối: **634 PASS, 0 FAIL, 0 ERROR, 12.723 assertions**; thời gian 2 phút 39,898 giây, PHP 8.5.11. Chạy với fail-on-deprecation, fail-on-phpunit-deprecation và fail-on-warning, exit code 0.

| Nhóm | PASS | FAIL / ERROR | Assertions |
|---|---:|---:|---:|
| Unit | 112 | 0 | 416 |
| Feature | 522 | 0 | 12.307 |
| Tổng | 634 | 0 | 12.723 |

- Nhóm liên quan đầu tiên: 120 PASS, 0 FAIL, 2.674 assertions.
- Test migration roundtrip và chặn rollback mất QR: 2 PASS, 0 FAIL, 7 assertions. Đã xử lý yêu cầu khóa ngoại MySQL khi đổi ngược sales_id về NOT NULL.
- Sau sửa test backfill slug cũ: nhóm LDP + migration + backfill slug **45 PASS, 0 FAIL, 357 assertions**. Test backfill kiểm tra trực tiếp migration slug thay vì giả định hai migration cuối; migration lịch sử không bị sửa.
- Browser QA trên fixture HTML/JSON tạo từ database test riêng: **320/390/768/1440px**, không tràn ngang, không lỗi JavaScript; chọn tỉnh/phường, validation, checkbox đồng ý, POST payload và giải mã QR đều đạt. Network của browser được mô phỏng; hành vi lưu/duyệt database được kiểm chứng bằng Feature Tests, không gửi form vào toyota.
- Regression browser Dealer Dashboard: 8 trang × 320/390/1440px, không tràn ngang/lỗi JavaScript; điều hướng nội bộ, link tải PNG, QR đúng URL và fixture evidence có quyền truy cập đều đạt. Vite build, Pint kiểm tra file liên quan và `git diff --check` đạt.
- `route:list` đã kiểm tra: POST đăng ký có web/CSRF và throttle; public JSON mới chỉ trả danh mục phường/xã; evidence vẫn có auth, auth.session, account.active và policy trong controller. GET /logout giữ 405; role Dealer vẫn hợp lệ.
- Các bài mới bao phủ validation, ward/province, consent, honeypot, dealer spoofing, IP/cooldown, CSRF thật, Facebook trùng cả hai chiều QR/Sales, unique index khi bypass service, điểm/giải Dealer không cộng Sales, quyền nội bộ/guest, lỗi debug không lộ dữ liệu, slug/QR và HTML/JSON public.
- Mọi test dữ liệu dùng schema test riêng với `Tests\Support\TestDatabase::assertIsolated`; không chạy migration/reset/seed trên toyota.
- Log: `.laravel-tools/dealer-landing/related-tests.log`, `migration-tests.log`, `full-suite.log`, `full-suite.xml`; browser `.laravel-tools/dealer-landing/qa/results.json`.
- Log `full-suite-before-slug-test-fix.log` và các log thử nghiệm ban đầu lưu lại lỗi đã xử lý; kết quả cuối là `full-suite.log` / `full-suite.xml` ở trên, không còn test lỗi.
- Backup SELECT/mysqldump và snapshot hash 10 bảng, 1.001 evidence trước khi sửa được lưu trong thư mục ignored `.laravel-tools/dealer-landing/`; không commit SQL hoặc thông tin môi trường. Đối chiếu sau triển khai xác nhận schema, toàn bộ dữ liệu và 1.001 evidence không đổi; xem `local-preservation.json`.

## File có thay đổi chức năng trong lần này

| Nhóm | File |
|---|---|
| Đăng ký public | `app/Http/Controllers/DealerRegistrationController.php` (mới), `app/Http/Requests/DealerRegistrationRequest.php` (mới), `app/Services/RegistrationPhone.php` (mới) |
| Profile, routes, throttle | `app/Http/Controllers/PublicDealerController.php`, `routes/web.php`, `app/Providers/RouteServiceProvider.php`, `config/dealer.php` |
| Dữ liệu, điểm, chống trùng | `app/Models/CustomerSubmission.php`, `app/Services/LeaderboardService.php`, `app/Services/ApprovedSubmissionFinder.php`, `app/Services/DealerDashboardData.php` |
| Duyệt Admin | `app/Actions/ApproveCustomerSubmission.php`, `app/Http/Controllers/Admin/SubmissionReviewController.php`, `app/Http/Requests/Admin/ReviewSubmissionRequest.php` |
| Lỗi bảo mật | `app/Exceptions/Handler.php`, `resources/views/errors/dealer-registration.blade.php` (mới) |
| LDP | `resources/views/dealers/show.blade.php`, `resources/views/components/dealer-registration-form.blade.php` (mới), `resources/views/components/customer-address-fields.blade.php` |
| Hiển thị QR không có Sales | `resources/views/admin/dashboard.blade.php`, `resources/views/admin/dealers/show.blade.php`, `resources/views/admin/submissions/index.blade.php`, `resources/views/admin/submissions/show.blade.php`, `resources/views/dealer/customers/show.blade.php`, `resources/views/components/dealer-customer-list.blade.php` |
| Review và xe | `resources/views/components/submission-review-form.blade.php`, `resources/views/components/submission-vehicle-details.blade.php` |
| Schema | `database/migrations/2026_10_20_000020_support_dealer_qr_registrations.php` (mới) |
| Tests | `tests/Feature/DealerLandingRegistrationTest.php` (mới), `tests/Feature/DealerRegistrationMigrationTest.php` (mới), `tests/Feature/DealerSlugBackfillTest.php`, `tests/Feature/PublicPrivacyTest.php` |
| Báo cáo | `DEALER_LANDING_PAGE_REPORT.md` (mới), `WORKING_TREE_CHANGES.md` |

Working tree đã có nhiều thay đổi của các giai đoạn trước. Lần kiểm tra Pint cũng chuẩn hóa import/định dạng tại một số file dirty có sẵn trong config/tests, không thay đổi logic của chúng. Danh sách tổng thể so với HEAD nằm trong `WORKING_TREE_CHANGES.md`; không nhầm toàn bộ working tree là tính năng LDP mới.

Không có lỗi kiểm thử còn tồn tại. Bước còn chờ là phê duyệt áp dụng ba migration pending vào toyota để sử dụng tính năng bằng dữ liệu local. Không bổ sung/thay thế package trong phạm vi LDP.

## Kiểm thử trên Laragon

1. Hiện tại có thể chạy tests bằng lệnh bên dưới, không cần áp dụng schema lên toyota. Cấu hình test ép DB test riêng; không dùng `.env` để đổi DB đang phục vụ local.
2. Muốn kiểm tra bằng dữ liệu local: xác nhận riêng chạy ba migration đang pending, sao lưu DB/evidence và kiểm tra `APP_URL=http://toyota.test`; chưa thực hiện bước này trong lần triển khai.
3. Sau khi được phép áp dụng schema, mở `http://toyota.test/dealers`, chọn đại lý, quét/tải QR. Profile cùng slug có form mới. Kiểm tra trên điện thoại có thể truy cập hostname local và URL chính thức cấu hình đúng.
4. Gửi form với số điện thoại mới, chọn đúng tỉnh/phường và đồng ý. Admin thấy bản ghi chờ duyệt, ghi nguồn QR; Dealer thấy trong đại lý mình; Sales không xem được.
5. Duyệt thiếu Facebook phải bị chặn. Xác minh Facebook rồi duyệt: Dealer tăng một điểm, Sales không tăng; dùng lại Facebook đã approved phải bị chặn. Guest không thấy tên khách/Sales/evidence.
6. Gửi lặp số điện thoại cùng đại lý trong 10 phút hoặc quá giới hạn IP phải nhận thông báo chờ, không tạo thêm bản ghi. Không xóa cache toàn hệ thống để kiểm tra; dùng số khác hoặc đợi hết thời gian.

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' vendor/phpunit/phpunit/phpunit -c .laravel-tools/composer/phpunit-upgrade.xml --do-not-cache-result --fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning
```

## Trước khi deploy Ubuntu VM

- Chốt và review toàn bộ working tree, backup/restore thử DB và storage private; không dùng seed demo. Chạy suite trên schema test riêng tương ứng MySQL thực tế.
- Thử migration mới cùng hai migration Dealer đang pending trên bản sao dữ liệu; DDL MySQL tự commit nên thực hiện trong cửa sổ bảo trì và có bản sao khôi phục. Không rollback schema QR khi đã có đăng ký.
- Đặt origin HTTPS chính thức tại APP_URL và DEALER_PUBLIC_BASE_URL trước khi in QR; giữ APP_KEY, slug và dữ liệu hiện có. Không phát hành QR mang hostname toyota.test.
- Nginx document root trỏ public; PHP 8.5 + extensions dự án/GD; storage evidence/avatar/photo vẫn private; không công khai file nguồn hoặc log. APP_DEBUG=false, cookie session an toàn trên HTTPS.
- Cache hỗ trợ atomic add và dùng chung nếu nhiều app instance; cấu hình IP proxy tin cậy đúng để throttle không bị giả mạo hoặc gom sai tất cả khách. Không ghi POST body/PII vào access log/APM/analytics.
- Xác nhận nội dung đồng ý và thông tin liên hệ đại lý đúng chiến dịch; khi đổi nội dung xử lý dữ liệu, tăng consent_version.
- Kiểm tra guest + Admin/Dealer/Sales, QR PNG, form và duyệt trên staging trước production. Chưa SSH hay thay đổi VM.
