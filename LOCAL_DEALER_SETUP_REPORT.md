# Áp dụng migration và tài khoản Dealer trên Laragon

Ngày 20/10/2026. Thực hiện theo chỉ dẫn cho phép áp dụng trên local, không yêu cầu phê duyệt lại. Dùng PHP Laragon 8.5.11, database MySQL `toyota`; không SSH hoặc deploy production.

## Đã thực hiện

- Sao lưu SQL và hash dữ liệu/ảnh trước khi chạy: `.laravel-tools/local-dealer-setup/toyota-before-dashboard-implementation.sql`, `pre-migration.json`, `baseline-metadata.json`. File backup nằm ngoài public và bị Git bỏ qua.
- Chạy thành công migration 000018, 000019, 000020, 000021 trên toyota, batch 5. Không tạo hoặc sửa migration lịch sử; không migrate:fresh, db:wipe hay reset dữ liệu.
- Tạo **88 tài khoản Dealer cho 88 đại lý**, hiện đều active; mỗi đại lý chỉ có một tài khoản. Giữ nguyên 1 Admin, 51 Sales và 500 khách hàng có sẵn.
- Seeder riêng `DevelopmentDealerAccountSeeder` sinh mật khẩu ngẫu nhiên 32 ký tự cho mỗi tài khoản, hash trong DB. Email local có dạng `demo.dealer.<dealer_id>@example.test`; không gửi email ra ngoài.
- File thông tin đăng nhập được tạo riêng dưới `.laravel-tools/dealer-accounts/credentials-20261019-195602-acee786e-369b-4048-882b-4353a9d40ba7.json`. Không ghi mật khẩu vào source, console/log hoặc public storage. Live check xác nhận file này không truy cập được qua web.
- Đã chạy lại seeder: **0 tài khoản mới, giữ nguyên 88 tài khoản hiện hữu**. Không đổi mật khẩu, email, trạng thái hoặc nhân sự của đại lý đang có tài khoản quản lý.

## Seeder

- Chỉ cho môi trường local/testing; từ chối production.
- Chỉ tạo tài khoản cho đại lý hiện hữu, không tạo thêm đại lý/Sales/khách hàng. Đại lý inactive sẽ có tài khoản blocked; không tự mở khóa tài khoản đã có.
- Khóa dòng Dealer cùng transaction, dùng unique index manager theo dealer_id; nếu email dự trữ bị trùng tài khoản khác thì rollback toàn bộ lượt tạo.
- Xuất file credentials riêng chỉ cho tài khoản mới, không ghi đè file cũ. Lỗi xuất file sẽ rollback tạo tài khoản; file mới được dọn nếu transaction lỗi.
- Không gắn vào DatabaseSeeder mặc định, để lệnh seed thông thường không bất ngờ tạo tài khoản hoặc xuất credentials. Chạy đúng class khi cần tạo tài khoản cho đại lý mới.

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' artisan db:seed --class=DevelopmentDealerAccountSeeder --force --no-interaction
```

Không cần chạy migrate lại cho bốn migration đã áp dụng. Không reset hoặc chạy seed toàn bộ để tạo lại dữ liệu demo.

## Bảo toàn dữ liệu

Đối chiếu các cột gốc trước/sau, bỏ qua riêng các cột mới được migration bổ sung và các dòng tài khoản/migration mới theo đúng yêu cầu:

- Giữ nguyên dữ liệu cũ của cả **10 bảng**, gồm đủ **52 tài khoản ban đầu**, email, password hash, trạng thái, quan hệ Sales/Dealer, khách hàng và lịch sử duyệt/giải thưởng.
- **500 khách hàng**, dealer_id lịch sử và điểm Dealer không đổi. QR source được bổ sung theo migration, không gán lại Sales hoặc Dealer cho lịch sử.
- **1.001 evidence** và **50 avatar** giữ nguyên đường dẫn/nội dung hash. Không tạo ảnh AI mới hoặc thay đổi ảnh hiện hữu.
- Toàn bộ 88 mật khẩu xuất file đã được kiểm tra khớp hash trong DB. Slug backfill Dealer/Sales hoàn tất; không đưa thông tin đăng nhập vào public profile/QR.
- Bằng chứng: `.laravel-tools/local-dealer-setup/verification.json`, `migrate.log`, `migrate-status.log`, `seed.log`, `seed-rerun.log`.

## Kiểm thử

- Nhóm seeder/Dealer account/integration: **14 PASS, 0 FAIL, 332 assertions**.
- Toàn bộ suite cuối: **663 PASS, 0 FAIL, 0 ERROR, 13.181 assertions**, PHP 8.5.11, 3 phút 15,018 giây. Chạy trên database test riêng, không reset toyota.
- Unit: 112 PASS / 416 assertions; Feature: 551 PASS / 12.765 assertions. Các cờ fail-on-warning/deprecation đều đạt.
- Đã sửa hai assertion trong test seeder mới để lấy đủ các field DB bằng refresh trước khi so sánh; không phải lỗi thay đổi dữ liệu của seeder. Không có lỗi còn tồn tại.
- Pint và git diff --check đạt. Log: `.laravel-tools/local-dealer-setup/related.log`, `full-suite.log`, `full-suite.xml`.

## Kiểm tra live Laragon

Đã dùng Chrome headless truy cập **trực tiếp `http://toyota.test`**, không mô phỏng network:

- Dealer Landing Page trả 200 và có form đăng ký.
- Sales Public Profile trả 200, có đủ ba tháng thống kê.
- Đăng nhập bằng **hai tài khoản Dealer mới** với mật khẩu từ file credentials, vào đúng Dealer Dashboard/danh sách khách hàng.
- Dealer truy cập Admin Dashboard bị chặn 403; logout hoạt động.
- File chứa credentials bị chặn khỏi web; không có lỗi JavaScript trong kịch bản.
- Không gửi đăng ký khách hàng mới hoặc thay đổi lịch sử trong smoke test live. Kịch bản tạo/duyệt/chuyển đại lý đã được chạy trong suite tích hợp trên DB test riêng.

Bằng chứng: `.laravel-tools/local-dealer-setup/live-results.json`. Script không in mật khẩu; lỗi browser cũng được che các giá trị mật khẩu.

## File thay đổi

- Mới: `database/seeders/DevelopmentDealerAccountSeeder.php`.
- Mới: `tests/Feature/DevelopmentDealerAccountSeederTest.php`.
- Mới: `LOCAL_DEALER_SETUP_REPORT.md`.
- Cập nhật ghi chú trạng thái trong các báo cáo Dealer Dashboard, LDP, Sales Public Profile và integration; cập nhật `WORKING_TREE_CHANGES.md`.
- Script, backup, credentials và log trong `.laravel-tools` bị Git bỏ qua. Không commit/reset/revert Git.

## Sử dụng

1. Mở `http://toyota.test/login`.
2. Mở file credentials riêng, tìm đại lý theo dealer_name/dealer_slug và dùng email/password tương ứng.
3. Dealer xem Dashboard, Sales và khách hàng của đại lý mình, tải QR. Admin vẫn quản lý tại các màn hình hiện có.
4. Mở trang danh sách đại lý hoặc URL profile public, tải QR PNG để thử form đăng ký. Sales tiếp tục đăng nhập bằng tài khoản/mật khẩu cũ, xem link public và upload avatar trong `/profile`.

**Sẵn sàng demo trên Laragon của máy hiện tại.** Nếu quét bằng điện thoại, thiết bị phải truy cập được hostname/URL trong QR; chưa kiểm tra camera điện thoại hoặc cấu hình DNS mạng ngoài máy. Production chưa được thay đổi.
