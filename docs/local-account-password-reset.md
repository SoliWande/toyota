# Đặt lại mật khẩu tài khoản local

## Mục tiêu
Đặt lại mật khẩu tất cả user trên MySQL local toyota theo giá trị người dùng yêu cầu; không áp dụng production/toyota_testing. Không lưu mật khẩu rõ vào tài liệu/helper.

## Trạng thái
DONE — 2026-11-06; khởi tạo IN PROGRESS trước khi ghi dữ liệu.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, tracking provisioning Admin và User model. Dùng lại preflight local đã kiểm tra thực tế; working tree có các task trước, giữ nguyên.

## Các file source code liên quan
Chỉ đọc app/Models/User.php và controller auth đã kiểm tra trong lượt trước. Không sửa application source; tài liệu mới là docs/local-account-password-reset.md. Helper .laravel-tools ignored.

## Những thay đổi đã thực hiện
Chưa ghi dữ liệu tại thời điểm tạo tracking; sẽ cập nhật các user hiện có trong transaction, không tạo user.

## Quyết định kỹ thuật quan trọng
Nhận mật khẩu qua stdin, hash bằng cast User hiện hành, đổi remember_token để vô hiệu remember-me cũ; giữ nguyên các cột khác (kể cả updated_at và last_login_at). Dùng Eloquent, không seed/reset schema. File credentials demo cũ là dữ liệu lịch sử, không ghi mật khẩu mới dạng rõ vào file đó; sau reset không còn là nguồn mật khẩu hiện tại.

## Kết quả kiểm thử
Chưa chạy; sẽ Hash::check mọi user và so sánh các giá trị còn lại trước commit. Đây là thao tác quản trị được yêu cầu, không chạy feature test hoặc POST đăng nhập trên local.

## Những lỗi còn tồn tại
Chưa có kết luận trước khi hoàn tất.

## Task hoặc bước tiếp theo
Xác minh local, reset tài khoản hiện có, kiểm chứng và báo cáo.

## Kết quả hoàn tất — 2026-11-06

PASS: actual APP_ENV=local, MySQL loopback127.0.0.1:3306/database toyota cùng máy/PID Laragon. Đã đổi mật khẩu đủ4user hiện có trong transaction bằng Eloquent và xoay remember_token. Hash::check PASS4/4; mọi cột ngoài password/remember_token giữ nguyên, số tài khoản vẫn4. Không tạo/xóa user, không POST login, không sửa schema/application source hoặc các task trước. Không chạy PHPUnit/build/full regression vì chỉ provisioning; không còn lỗi hoặc bước bắt buộc tiếp theo. Mật khẩu demo ngẫu nhiên ghi trong credentials.json/tracking Phase2A trước đây đã được thay thế bởi lần reset này; giữ lịch sử, không ghi plaintext mới vào file. File tài liệu thay đổi duy nhất là tracking này, helper ignored.
