# Demo local Phase 2A

Đã seed ngày 02/11/2026 sau xác nhận của người dùng. Kiểm tra trước khi ghi:
APP_ENV=local, MySQL loopback 127.0.0.1, SELECT DATABASE() = toyota;
dealers/users/customer_submissions đều trống. Không chạy migration hoặc đổi schema.

Seeder: `database/seeders/DealerRegistrationPhase2aDemoSeeder.php`.

```powershell
php artisan db:seed --class=DealerRegistrationPhase2aDemoSeeder
```

Seeder chỉ cho phép local, MySQL loopback và database toyota. Không được đưa seeder
này vào DatabaseSeeder mặc định. Chạy lại nhận dataset đã có và giữ nguyên; dataset
thiếu/conflict sẽ bị từ chối thay vì ghi đè. Không có cleanup tự động.

## Đăng nhập

Mở `/login` trên domain local của app:

| Vai trò | Email |
| --- | --- |
| Admin | phase2a.admin@example.test |
| Dealer | phase2a.dealer@example.test |
| Sales | phase2a.sales@example.test |

Mật khẩu ngẫu nhiên dùng chung cho ba tài khoản demo được lưu tại
`.laravel-tools/phase-2a-demo/credentials.json`. File nằm ngoài public, trong thư mục
đã được gitignore; không đưa mật khẩu vào tài liệu/console/log. Đã kiểm tra cả ba
password hashes khớp file và các tài khoản đều active.

## Trang và fixtures

- Active D01: `/demo-phase-2a-active`
- Inactive D06: `/demo-phase-2a-inactive`
- D02 sau POST hợp lệ: `/demo-phase-2a-active/registrations/step-two`

Tất cả tên khách, email, địa chỉ đường phố, Facebook và biển số đều là fixtures
tổng hợp, không lấy thông tin người thật. Tỉnh/phường dùng danh mục hành chính app.
Các số dưới đây chỉ là chuỗi giả lập có format qua validation; không khẳng định số
chưa được cấp ngoài đời. Tuyệt đối không gọi, nhắn tin hoặc dùng dịch vụ xác thực
bên ngoài với fixtures. App/seeder không liên hệ số hay truy cập profile Facebook.

| Phone chuẩn hóa | Source | Trạng thái | Kết quả bước 1 |
| --- | --- | --- | --- |
| 0990000001 | dealer_qr | pending | D05 |
| 0990000002 | dealer_qr | approved | D05 |
| 0990000003 | dealer_qr | rejected | Cho tạo draft |
| 0990000004 | sales | pending | D05 |
| 0990000005 | sales | approved | D05 |
| 0990000006 | sales | rejected | Cho tạo draft |

Sales fixtures lưu raw dạng +84 với khoảng trắng và phone_normalized=null để kiểm
tra chuẩn hóa dữ liệu Sales lịch sử. Có thể dùng phone rejected để thử D01→D02.
Họ tên nhập tùy ý giả lập, chọn Veloz/Hilux/Fortuner/FJ. Bước 2 vẫn chưa final submit.

## Xác minh

- 2 Dealers có slug model sinh đúng, active/inactive.
- 3 Users active đúng role; không tạo awards.
- 6 submissions: 2 pending, 2 approved, 2 rejected; 4 moderation reviews.
- Dùng ApproveCustomerSubmission/RejectCustomerSubmission thật, không sửa approval.
- LeaderboardService giữ nguyên: Dealer 2 điểm, Sales 1 điểm; pending/rejected không
  tính, QR approved chỉ tính Dealer. Đây là điểm demo trên database local.
- HTTP kernel với cookie jar và CSRF thật: active landing 200, inactive D06 200,
  pending QR và approved Sales hiện D05, rejected phone tạo draft, D02 refresh giữ
  draft, quay lại D01 giữ giá trị, tổng submissions vẫn 6.
- Kiểm tra syntax/Pint qua. Seed lần hai xác nhận không ghi đè dataset/mật khẩu.
- Chưa kiểm tra browser visual hoặc đăng nhập tương tác; kiểm tra password hashes
  không thay thế browser login QA. Feature suite database toyota_testing vẫn cần
  schema test riêng; không chạy tests vào toyota để vượt test safety guard.

Không dùng ToyotaRealisticDemoSeeder trong lượt này. Lỗi email nhận diện manifest
của seeder đó được báo trước vẫn chưa sửa; bộ demo nhỏ độc lập không phụ thuộc nó.
Không đổi backend Phase 2A, tính điểm, approval, schema hoặc dữ liệu production.
Không commit, push hoặc deploy.
