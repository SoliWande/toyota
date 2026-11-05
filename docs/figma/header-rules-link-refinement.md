# Header dẫn tới trang Thể lệ

## Mục tiêu
Theo yêu cầu bổ sung của người dùng, nút Thể lệ trên header Website Thi đua mở trang Thể lệ hiện có ở cả Desktop và Mobile.

## Trạng thái
DONE — 2026-11-05. Tracking tạo trước khi sửa Blade; đã kiểm tra render mới.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md và phase-3b-full-rules.md, routes/controller/layout/navigation hiện tại. Header dùng route home kèm #the-le; footer và CTA xem đầy đủ đã dùng route campaign.rules tại /chuong-trinh/the-le. Task audit import đang tiếp tục độc lập, không có ghi database.

## Các file source code liên quan
resources/views/components/competition/navigation.blade.php; routes/web.php; CampaignRulesController; competition/layout và tracking phase-3b-full-rules.md (chỉ đọc).

## Những thay đổi đã thực hiện
Đổi duy nhất href Thể lệ trong component navigation dùng chung Desktop/Mobile sang route campaign.rules. Không thay thiết kế, nội dung thể lệ, route hoặc business logic.

## Quyết định kỹ thuật quan trọng
Dùng named route đã tồn tại, không tạo /the-le có thể xung đột Dealer slug. Không chỉnh footer hoặc section thể lệ trên trang chủ.

## Kết quả kiểm thử
PASS mới: `php .laravel-tools/header-rules-link-check.php` render cả Desktop/Mobile bằng Blade thực tế, mỗi variant có đúng một link Thể lệ tới `http://toyota.test/chuong-trinh/the-le`. CampaignRulesController và view render HTTP 200. Database driver bị vô hiệu trong process kiểm tra; không ghi hoặc đọc database. Helper ở ignored path, không thêm test ứng dụng cho thay đổi href đơn giản. Không chạy build vì chỉ thay Blade, không có assets mới.

## Những lỗi còn tồn tại
Không còn lỗi trong phạm vi sửa href. Chưa kiểm tra click bằng browser; không đổi visual nên không tuyên bố QA screenshot mới. Chính sách nội dung mẫu local/testing và chờ phê duyệt production của trang Thể lệ giữ nguyên.

## Task hoặc bước tiếp theo
Bàn giao thay đổi link; tiếp tục báo cáo dry-run import riêng. Không deploy.
