# Toyota realistic demo — audit và vận hành

## Kết quả audit

- Source khai báo PHP ^8.5, Laravel ^13.0. Schema dùng MySQL: enum, CHECK, generated identity và window function. Không thể coi SQLite là kiểm chứng tương đương.
- `DevelopmentAdminSeeder`, `DevelopmentDealerSeeder`, `DevelopmentDealerAccountSeeder`, `DevelopmentDemoSeeder` đều giới hạn local/testing. Giữ nguyên các bảo vệ này. Admin hiện có không bị đổi mật khẩu; Dealer account seeder bỏ qua manager hiện có và xuất secret ra thư mục riêng. DevelopmentDemoSeeder có 50 Sales/500 hồ sơ, tỷ lệ 60/30/10, có duplicate chủ ý, không phù hợp bộ demo mới.
- `ToyotaDealerSeeder` đọc snapshot 87 địa điểm, có thể cập nhật thông tin danh bạ hiện có. Seeder mới KHÔNG gọi nó, KHÔNG tạo/sửa/xóa Dealer. Điều kiện đầu vào: có đúng 87 Dealer mang `toyota_source_id`, ít nhất 25 Dealer active có tỉnh khớp danh mục hành chính. Có 82 địa điểm khớp trực tiếp trong snapshot hiện tại; những tỉnh không khớp được loại khỏi lựa chọn, không suy đoán sau sáp nhập.
- Điểm là COUNT hồ sơ approved; thời gian xét là `submitted_at`, không phải `reviewed_at`. Khoảng thời gian là [start, end). Tie-break: điểm giảm dần, thời điểm hoàn thành tăng dần, id tăng dần. Không có cột điểm để seed.
- `ApproveCustomerSubmission` kiểm tra quyền Admin active, trạng thái pending, tính toàn vẹn, Facebook trùng, rồi lưu hồ sơ và moderation review trong transaction. QR phải được Admin bổ sung Facebook khi duyệt. `RejectCustomerSubmission` cũng tạo audit review.
- CustomerSubmission có `dealer_id` snapshot tại lúc tạo, không suy ra từ đại lý hiện tại khi đọc lịch sử. Model cấm sửa attribution thông thường. Admin chuyển Sales bằng `users.dealer_id`, không có bảng transfer history riêng. Dealer dashboard/leaderboard dựa snapshot; Sales leaderboard cộng cho Sales và hiển thị Dealer hiện tại. Manifest demo lưu thêm 5 sự kiện chuyển đại lý để kiểm tra, không thay thế lịch sử nghiệp vụ.
- QR có source `dealer_qr`, sales_id null, địa chỉ/mã tỉnh/xã, phone_normalized và consent bắt buộc. QR approved chỉ cộng Dealer, không cộng Sales.
- Public leaderboard chỉ trình bày Dealer; public Sales dùng projection scalar và phone che số. Địa chỉ/evidence không được đưa vào projection. Seeder không sửa logic public hoặc nới quyền.

## File thay đổi

1. `database/seeders/ToyotaRealisticDemoSeeder.php`: entry point, cấm gọi trực tiếp để bypass xác nhận.
2. `app/Services/ToyotaRealisticDemo.php`: tạo dữ liệu, manifest, kiểm tra, cleanup.
3. `app/Console/Commands/ToyotaDemoCommand.php`: CLI tương tác `toyota:demo`.
4. `config/realistic_demo.php`: cấu hình opt-in và secret.
5. `app/Providers/AppServiceProvider.php`: đăng ký singleton giữ trạng thái xác nhận trong một lần chạy CLI.
6. `tests/Feature/ToyotaRealisticDemoSeederTest.php`: kiểm thử tích hợp, không migrate/RefreshDatabase.
7. `tests/Unit/ToyotaRealisticDemoSafetyTest.php`: kiểm thử bảo vệ không kết nối database.
8. `TOYOTA_REALISTIC_DEMO.md`: báo cáo và runbook này.

Không thay `DatabaseSeeder`, Development Seeder, migrations, factories hay thuật toán xếp hạng. Seeder chạy được với dependency production (`--no-dev`); không phụ thuộc Faker hoặc factories.

## Dữ liệu tạo

- 120 Sales active, 1 Admin demo riêng, tối đa 25 manager demo (bỏ qua Dealer đã có manager).
- 1.500 khách: 1.125 approved, 270 pending, 105 rejected. 250 khách mỗi tháng trong tháng hiện tại và 5 tháng trước; tháng hiện tại dừng tại thời điểm chạy.
- 192 đăng ký QR; một số QR pending/rejected chưa có Facebook, đúng workflow hiện có.
- Xe Veloz/Hilux; doanh số khác nhau giữa các Sales, nhóm dẫn đầu đổi theo tháng; 5 Sales chuyển đại lý sau 3 tháng. Dữ liệu thật nếu đã có vẫn tham gia bảng xếp hạng, nên không đảm bảo Dealer demo đứng đầu toàn hệ thống.
- Tên ghép từ danh sách tên Việt tổng hợp; trùng tên không có nghĩa trùng khách. Số `000…` cố ý không phải số thuê bao Việt Nam; mỗi khách có phone/biển số/identity riêng. Địa chỉ là đường demo trong một xã thuộc tỉnh Dealer, không sao chép địa chỉ nhà thật.
- Tất cả email tài khoản dùng `example.com`. Schema khách không có cột email nên không tự thêm cột.
- Facebook dạng `toyota.demo.synthetic.v1.NNNN` là placeholder phục vụ rule chuẩn hóa/duyệt, không phải profile được xác minh hay lấy từ người thật. Không dùng để liên hệ hoặc kiểm tra profile ngoài mạng. Không sinh ảnh người thật; evidence để null theo schema, không gọi dịch vụ ngoài.
- Notes tag `[TOYOTA-REALISTIC-V1]`; email namespace `toyota.realistic.v1.*@example.com`; biển số `DEMO-V1-*`. Manifest ID chính xác, chuyển đại lý và fingerprint nằm trong admin_note của Admin demo (field ẩn, không nằm trong public projection).
- Tạo trong một transaction, khóa directory để serialize seed/cleanup. Lỗi thì rollback toàn bộ. Chạy lại giữ nguyên dữ liệu và các thay đổi nghiệp vụ sau seed; dataset thiếu/collision thì dừng, không tự chữa bằng ghi đè.

## Cấu hình local

Đặt bằng secret manager hoặc `.env` không commit:

```dotenv
TOYOTA_DEMO_ENABLED=true
TOYOTA_DEMO_ALLOW_PRODUCTION=false
TOYOTA_DEMO_PASSWORD=<secret ngẫu nhiên tối thiểu 16 ký tự>
```

Không dùng giá trị placeholder làm mật khẩu. Dùng secret riêng cho VM demo. Tất cả tài khoản mới dùng secret cấu hình; không đổi secret của tài khoản hiện có. `APP_ENV` giữ nguyên. Cấu hình DB_* phải trỏ đúng database local đã có schema hiện hành và danh bạ 87 địa điểm. Runbook này không yêu cầu chạy migrate hoặc import lại danh bạ.

Nếu ứng dụng có config cache, cập nhật cache theo quy trình hiện có sau khi thay biến môi trường. Thay biến shell sau khi cache đã tạo sẽ không tự thay giá trị trong cache.

## Chạy local

```sh
php artisan toyota:demo status
php artisan toyota:demo seed
php artisan toyota:demo status
```

CLI hiển thị tên database và môi trường. Phải nhập chính xác `seed:<tên database>`, kể cả database rỗng. Non-interactive bị từ chối, không có cờ bỏ qua xác nhận. Không gọi `db:seed --class=ToyotaRealisticDemoSeeder --force`: bị chặn có chủ ý. Không tự chạy từ DatabaseSeeder.

Admin demo: `toyota.realistic.v1.admin@example.com`. Sales: `toyota.realistic.v1.sales.000@example.com` đến `.119@example.com`. Dealer: `toyota.realistic.v1.dealer.N@example.com` với N=0..24, chỉ tạo nếu Dealer chưa có manager. Password lấy từ secret TOYOTA_DEMO_PASSWORD, không in ra console.

## Kiểm tra dữ liệu

`php artisan toyota:demo status` chỉ đọc, không yêu cầu bật opt-in, không in mật khẩu. Kỳ vọng users_present 121..146, submissions_present 1500, statuses approved 1125 / pending 270 / rejected 105 ngay sau seed. Manifest chứa ID cần dùng khi kiểm tra bộ demo trên database có dữ liệu thật.

SQL chỉ đọc, có scope tag để không tính lẫn dữ liệu thật:

```sql
SELECT status, COUNT(*) FROM customer_submissions
WHERE notes = '[TOYOTA-REALISTIC-V1]' GROUP BY status;

SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*) AS total
FROM customer_submissions WHERE notes = '[TOYOTA-REALISTIC-V1]' GROUP BY month;

SELECT COUNT(*) AS total, COUNT(DISTINCT phone) AS phones,
       COUNT(DISTINCT license_plate) AS plates, COUNT(DISTINCT dealer_id) AS dealers
FROM customer_submissions WHERE notes = '[TOYOTA-REALISTIC-V1]';

SELECT source, status, COUNT(*) FROM customer_submissions
WHERE notes = '[TOYOTA-REALISTIC-V1]' GROUP BY source, status;

SELECT COUNT(*) AS invalid_times FROM customer_submissions
WHERE notes = '[TOYOTA-REALISTIC-V1]'
AND (submitted_at > NOW() OR reviewed_at < submitted_at);

SELECT s.sales_id, s.dealer_id AS historical_dealer, u.dealer_id AS current_dealer,
       COUNT(*) AS submissions
FROM customer_submissions s JOIN users u ON u.id = s.sales_id
WHERE s.notes = '[TOYOTA-REALISTIC-V1]'
GROUP BY s.sales_id, s.dealer_id, u.dealer_id;
```

`NOW()` yêu cầu DB session timezone trùng timezone ứng dụng. Bảng xếp hạng/dashboard sử dụng LeaderboardService như trước; không có bảng điểm demo riêng.

## Chạy trên VM demo production (người vận hành thực hiện)

1. Backup database, xác minh đây là VM demo, xác minh schema hiện hành/danh bạ, ghi nhận baseline Dealer và manager. Chưa chạy trên dữ liệu thật để trình diễn chung nếu chưa chấp nhận điểm demo tham gia bảng thật.
2. Giữ `APP_ENV=production`, cấu hình ba biến phía trên, riêng `TOYOTA_DEMO_ALLOW_PRODUCTION=true`. Cập nhật config cache theo quy trình deploy hiện có. Không cần dev dependencies.
3. Chạy tương tác:

```sh
php artisan toyota:demo status
php artisan toyota:demo seed
php artisan toyota:demo status
```

4. Nhập `seed:<database thực tế>` sau khi đọc lại database/environment. Xác minh số lượng và trang public, dashboard theo các role. Không gửi email/SMS đến placeholder; seeder không gửi thông báo.
5. Sau khi xong đặt TOYOTA_DEMO_ENABLED=false và TOYOTA_DEMO_ALLOW_PRODUCTION=false, cập nhật config cache. Các biến chỉ cho phép CLI seed/cleanup, không đổi môi trường hoặc logic website. Việc tắt biến không tự vô hiệu hóa tài khoản demo; chỉ giữ các tài khoản này trên VM demo có kiểm soát.

Không SSH, push Git, deploy hoặc chạy seeder trên VM trong quá trình thực hiện task này.

## Rollback dữ liệu demo

Backup trước. Bật lại opt-in đúng môi trường rồi:

```sh
php artisan toyota:demo status
php artisan toyota:demo cleanup
php artisan toyota:demo status
```

Phải nhập `cleanup:<database thực tế>`. Chỉ xóa ID trong manifest, theo thứ tự moderation reviews → submissions → users, trong transaction. Không xóa Dealer hoặc manager cũ, không dùng truncate, wipe, reset hoặc migration rollback.

Cleanup kiểm tra fingerprint; hồ sơ/tài khoản đã sửa, thêm review, submission edit hoặc access token thì từ chối. FK tới dữ liệu ngoài bộ demo làm transaction rollback, không tự xóa/cascade dữ liệu liên quan để ép thành công. Không tự bỏ FK, không tự sửa manifest. Khi bị từ chối, người vận hành phải đối chiếu backup và ID manifest, quyết định riêng cho dữ liệu đã phát sinh. Award đã xuất bản là snapshot nghiệp vụ; không xuất bản award dựa dữ liệu demo nếu sau đó cần rollback sạch điểm lịch sử. Cleanup không sửa award có sẵn.

Nếu tạo lỗi giữa chừng thì transaction rollback, không có file ảnh hay credential file cần dọn. InnoDB có thể để khoảng trống auto increment sau rollback; không reset counter.

## Test và tình trạng kiểm chứng

```sh
php vendor/bin/phpunit tests/Unit/ToyotaRealisticDemoSafetyTest.php
php vendor/bin/phpunit tests/Feature/ToyotaRealisticDemoSeederTest.php
```

Feature test yêu cầu MySQL database `toyota_testing` có schema hiện hành nhưng rỗng, DB_USERNAME/DB_PASSWORD phù hợp; phpunit.xml ép môi trường testing và tên database này. Có guard TestDatabase trước mọi fixture. Không chạy toàn bộ feature suite hiện tại vì nhiều test cũ dùng RefreshDatabase có thể chạy migrate:fresh. Test mới chỉ tạo fixture trong transaction và rollback; không tự tạo database/schema và không đụng dữ liệu đã có (schema không rỗng thì skip).

Đã cài dependencies theo composer.lock, không chạy Composer scripts; Pint pass; 4 unit safety tests pass (6 assertions). Feature tests chưa xác minh được: workspace không có `.env`; tài khoản mặc định `forge` bị từ chối, thử user local `root` kết nối được server nhưng database `toyota_testing` chưa tồn tại. Không tạo schema hoặc chạy migrate theo ràng buộc. Không coi các yêu cầu idempotence/ranking/privacy/cleanup là đã kiểm chứng tích hợp cho đến khi chạy feature tests trên schema test cô lập được chuẩn bị sẵn.
