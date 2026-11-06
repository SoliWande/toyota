# Sửa layout header Sales/Manager

## Mục tiêu
Header một hàng trên Desktop, tên dài ellipsis, menu Mobile ở viewport hẹp; giữ link/quyền/active/logout. QA1440/1280/390, không full regression.
## Trạng thái
DONE — 2026-11-06; tạo IN PROGRESS trước sửa source.
## Hiện trạng trước khi chỉnh sửa
Đọc AGENTS/tracking account, staff-header/layout/CSS/JS. Gap nav staff20px thay Figma4px; link thiếu nowrap/flex sizing; tên tài khoản overflow-wrap:anywhere, account flex:none. Breakpoint account1024 lệch nav1200. Không có screenshot người dùng đính kèm ở lượt này. Working tree có task trước, giữ nguyên.
## Các file source code liên quan
resources/views/components/competition/staff-header.blade.php; resources/css/account.css. Chỉ đọc competition.css/JS/layout; không đổi public header/dashboard.
## Những thay đổi đã thực hiện
Đã đọc Figma Desktop219:662/B10219:840, Mobile155:398/155:557, file yKmWQI6BYJ6dponEnidlsq; ảnh Web/Header Desktop. Chưa sửa source.
## Quyết định kỹ thuật quan trọng
Scope .wc-staff-header; gap4px, nowrap, min-width:0/ellipsis, đồng bộ breakpoint1200 với menu hiện có. CTA chỉ Sales theo route sẵn có; Manager không được cấp quyền khai báo để giống CTA Figma.
## Kết quả kiểm thử
Chưa chạy; dùng fixture cách ly/bộ ảnh nhanh, không ghi dữ liệu local.
## Những lỗi còn tồn tại
Đang thực hiện; không tuyên bố pixel-perfect toàn màn.
## Task hoặc bước tiếp theo
Sửa header/CSS, build và QA ba viewport với tên ngắn/dài, menu/dropdown/active/logout.

## Kết quả 2026-11-06
Đã sửa đúng2source nêu trên: nav gap4px/flex:none/nowrap; account co được, min-width:0 và ellipsis/title, avatar32px/pill/caret theo Figma. CTA Desktop chỉ tách link khai báo Sales sẵn có; mobile/link/quyền/logout giữ nguyên. Account/CTA/nav cùng ẩn dưới1200px, dùng menu Mobile và JS breakpoint hiện có; không sửa competition.css/public header/JS/nội dung Dashboard. Các thay đổi có sẵn ở account.css của task trước được giữ nguyên.

PASS build68modules và diff --check. Export fixture HTTP thật trong transaction toyota_testing rollback sau preflight loopback/database thực tế,1test/9assertions PASS; không tạo/đăng nhập vào local. Playwright PASS40case (B09/B10/Sales dashboard/Sales create × tên ngắn/dài ×1440/1280/390/1199/1200) và5public-header cases: menu một hàng cao36px, không overlap/overflow, tên dài ellipsis, active/CTA/dropdown/logoutPOST+CSRF/mobilemenu/Escape đúng. Đã xem ảnh header Desktop/Mobile đối chiếu context và ảnh Figma Web/Header/B10Mobile. Evidence ngắn ignored `qa/artifacts/staff-header-20261106/`, helper `.laravel-tools/StaffHeaderVisualTest.php` và `staff-header-*`; không thêm test runtime/QA report dài, không full regression. Không còn lỗi trong phạm vi kiểm tra; chưa QA browser khác Chrome hoặc đối chiếu screenshot người dùng vì lượt này không có ảnh đính kèm. Không có bước bắt buộc tiếp theo.
