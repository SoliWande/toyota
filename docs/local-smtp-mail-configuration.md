# Cấu hình SMTP local
## Mục tiêu
Áp dụng SMTP Gmail465 do người dùng cung cấp vào .env local; không gửi email thử hoặc lưu mật khẩu trong source/tracking.
## Trạng thái
DONE cấu hình local — 2026-11-06; chưa kiểm chứng delivery thật.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, tracking B06/B17, config/mail.php và MailManager Laravel đang cài; trước đây local dùng mailcatcher1025 chưa kết nối được. Giữ mọi sửa có sẵn.
## Các file source code liên quan
config/mail.php chỉ đọc, không sửa; thay cấu hình .env (ignored), tạo lại bootstrap/cache/config.php (ignored). Tài liệu: docs/local-smtp-mail-configuration.md.
## Những thay đổi đã thực hiện
Chưa ghi cấu hình tại thời điểm tạo tracking.
## Quyết định kỹ thuật quan trọng
Laravel dùng mailer smtp, host Gmail/cổng465 TLS implicit; transport gmail trong XML được ánh xạ smtp. Mật khẩu nhận qua stdin, chỉ lưu .env/config cache riêng tư. Giữ APP_KEY/DB và mọi config khác.
## Kết quả kiểm thử
Chờ kiểm tra config hiệu lực; không gửi email hoặc chạy test ghi database.
## Những lỗi còn tồn tại
Chưa xác minh xác thực SMTP/delivery thật.
## Task hoặc bước tiếp theo
Cấu hình .env, nạp config cache và kiểm tra không lộ secret; gửi thử chỉ khi được yêu cầu.

## Kết quả 2026-11-06
Đã cập nhật đúng các MAIL_* trong .env: smtp/Gmail465/ssl, username/from theo thông tin người dùng, tên gửi Toyota Community. Mật khẩu chỉ ở .env/config cache ignored; không ghi lại trong tracking/source/helper. `php artisan config:cache` PASS; guard đọc config thực tế vẫn APP_ENV=local/MySQL toyota,4users. Kiểm tra transport Symfony được khởi tạo với implicit TLS, host/port/username/password khớp .env và APP_KEY giữ nguyên; chưa start transport hoặc gửi email. Các giá trị .env không liên quan được so sánh giữ nguyên trước ghi. Không sửa config/mail.php hay application source, không test database/build/full regression. Không có lỗi cấu hình đã phát hiện; xác thực Gmail/delivery cần kiểm tra bằng email thử khi được yêu cầu. Nhận định mailcatcher1025 trong tracking B06/B17 nay là lịch sử, đã thay bằng cấu hình Gmail nhưng chưa thay kết luận chưa nghiệm thu delivery.
