# Thiết lập AGENTS.md duy trì quy trình làm việc

## Mục tiêu

Lưu quy tắc tracking và kiểm chứng trong AGENTS.md ở root Laravel để áp dụng
qua các phiên Codex và tài khoản mới, theo mười yêu cầu của người dùng.

## Trạng thái

DONE — cập nhật ngày 2026-11-03 sau khi kiểm tra nội dung.
Trạng thái khởi tạo: IN PROGRESS.

## Hiện trạng trước khi chỉnh sửa

- Root thực tế: `F:\laragon\www\toyota`, có composer.json Laravel 13/PHP 8.5.
- `Test-Path -LiteralPath AGENTS.md` trả về False; không có quy tắc cũ tại file
  root cần hợp nhất.
- Đã đọc báo cáo khôi phục ngữ cảnh, quy trình tracking cuối báo cáo và mẫu
  Phase 3B refinement. Dự án có lịch sử tracking tại root và `docs/figma/`.

## Các file source code liên quan

Không sửa source code. Đã đọc composer.json để xác nhận bối cảnh dự án.
Các file tài liệu được tạo:

- `AGENTS.md`.
- `docs/agents-workflow-setup.md` (file này).

## Những thay đổi đã thực hiện

- Tạo hướng dẫn root bao quát đọc tracking/source trước task, tracking riêng,
  mẫu nội dung bắt buộc, bốn trạng thái và điều kiện DONE.
- Ghi giới hạn task UI, Figma/screenshot, dữ liệu production, giữ lịch sử,
  xử lý mâu thuẫn tài liệu và báo cáo tiếng Việt.
- Bổ sung cách phân biệt test mới/lịch sử, fixture/luồng thật và database test
  cách ly để tránh diễn giải sai bằng chứng.

## Quyết định kỹ thuật quan trọng

- Không cố định đường dẫn ổ đĩa cho các phiên sau; hướng dẫn dùng root repository.
- Dùng `docs/agents-workflow-setup.md` cho task quy trình, giữ các tài liệu Figma
  và lịch sử cũ nguyên trạng. Không tạo phase Figma giả cho thay đổi tài liệu.
- Chỉ yêu cầu kiểm chứng nội dung cho task này; không chạy test ứng dụng hay
  thao tác database vì không có thay đổi hành vi ứng dụng.

## Kết quả kiểm thử

- Đã đọc lại toàn bộ AGENTS.md và đối chiếu thủ công đủ mười yêu cầu: PASS.
- PowerShell kiểm tra đủ chín mục bắt buộc trong tracking và mẫu AGENTS: PASS.
- Đọc UTF-8 và kiểm tra không có ký tự thay thế U+FFFD ở hai file mới: PASS.
- Đã xác nhận hai file được tạo đúng đường dẫn root/docs.
- Không chạy PHPUnit, build hoặc browser: chỉ thay đổi tài liệu, không thay đổi
  hành vi ứng dụng. Không thao tác database hay sửa source code.

## Những lỗi còn tồn tại

Không phát hiện lỗi còn tồn tại trong phạm vi tài liệu mới sau kiểm tra.
Các vấn đề ứng dụng đã ghi trong `context-recovery-2026-11-03.md` không thuộc
phạm vi sửa của task này.

## Task hoặc bước tiếp theo

Các task sau đọc AGENTS.md và tracking liên quan trước khi triển khai; chọn
task tiếp theo theo báo cáo khôi phục ngữ cảnh và yêu cầu của người dùng.
