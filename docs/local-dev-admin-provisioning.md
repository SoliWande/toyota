# Tạo tài khoản Dev Admin trên local

## Mục tiêu
Tạo một Admin active với email dev-admin@toyota.test và mật khẩu người dùng cung cấp trên database local toyota. Không ghi mật khẩu rõ vào tài liệu hoặc helper.

## Trạng thái
DONE — 2026-11-06; tracking khởi tạo IN PROGRESS trước khi ghi dữ liệu.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md và tracking account-registration-approval-sync.md; kiểm tra User model, enums và helper xác minh local từ task migration. Working tree có thay đổi từ task trước, giữ nguyên. Chưa ghi tài khoản ở thời điểm tạo tracking.

## Các file source code liên quan
Chỉ đọc app/Models/User.php, app/Enums/UserRole.php, app/Enums/UserStatus.php; không sửa application source. Tài liệu thay đổi: docs/local-dev-admin-provisioning.md. Helper vận hành ở .laravel-tools (ignored).

## Những thay đổi đã thực hiện
Đang kiểm tra kết nối thực tế/email trước khi tạo. Không chạy seed/migration/reset.

## Quyết định kỹ thuật quan trọng
Chỉ local MySQL toyota đã xác minh. Tên hiển thị Dev Admin; role admin, status active, không gán Dealer/reviewer. Dùng Eloquent và cast hashed hiện có; nhận mật khẩu qua stdin, không lưu plaintext. Nếu email tồn tại thì dừng, không ghi đè tài khoản/mật khẩu/quyền hiện có.

## Kết quả kiểm thử
Chưa thực hiện; sẽ kiểm tra mật khẩu bằng Hash::check và so sánh các user cũ trong transaction. Đây là provisioning được yêu cầu, không phải test fixture; không POST đăng nhập hoặc chạy feature test trên local.

## Những lỗi còn tồn tại
Chưa có kết luận cho đến khi chạy provisioning và kiểm chứng.

## Task hoặc bước tiếp theo
Tạo duy nhất tài khoản được yêu cầu, kiểm tra kết quả và cập nhật tracking.

## Kết quả hoàn tất — 2026-11-06

- Xác minh mới: APP_ENV=local, PDO loopback127.0.0.1:3306, MySQL8.0.30 cùng máy/PID Laragon, actual database toyota. Email chưa tồn tại.
- Đã tạo Dev Admin / dev-admin@toyota.test, role admin, status active, không gán Dealer. Mật khẩu được hash bằng cast User và Hash::check PASS; homeRoute=admin.dashboard.
- PASS số user3→4 và so sánh chính xác toàn bộ bản ghi cũ không đổi trước khi commit transaction. Không lộ mật khẩu/hash trong output; không lưu mật khẩu rõ trong file. Không đăng nhập thử, không sửa schema/source/.env hoặc các thay đổi có sẵn.
- Không còn lỗi trong phạm vi provisioning; không cần chạy PHPUnit/build/full regression. Tài liệu thay đổi duy nhất là file này. Không có bước tiếp theo bắt buộc; tài khoản đã sẵn sàng đăng nhập.
