# Hồ sơ cá nhân Quản lý đại lý

## Mục tiêu
Đồng bộ trang hồ sơ Dealer với design system Toyota, thêm upload/preview avatar tự quản lý; giữ email cố định, đổi tên và đổi mật khẩu hiện tại.

## Trạng thái
DONE — 13/11/2026. Phạm vi profile Dealer đã triển khai và kiểm thử; một test Admin cũ ngoài phạm vi vẫn FAIL, tái hiện được với source trước task (chi tiết bên dưới).

## Hiện trạng trước khi chỉnh sửa
Trang profile chung đang dùng hai form hẹp; chỉ Sales có avatar. Schema đã có users.avatar_path (migration2026_10_19_000016); SalesAvatars có re-encode JPEG512px, giới hạn5MB/4096px. Có nhiều thay đổi từ task trước, không thuộc task này; baseline các file liên quan lưu ignored .laravel-tools/dealer-profile/before/.

## Các file source code liên quan
ProfileController, ProfileRequest, SalesAvatars, config/filesystems.php, routes/web.php; profile/edit.blade.php, competition/staff-header, account.css/js; test DealerPersonalProfileTest dự kiến.

## Những thay đổi đã thực hiện
- Thêm nhánh view `resources/views/profile/dealer.blade.php`: hai card thông tin cá nhân/đổi mật khẩu, avatar96px, tên/email/vai trò, preview ảnh mới, input họ tên, email dạng thông tin cố định; mobile một cột. Giữ `profile/edit.blade.php` Sales/Admin nguyên vẹn.
- Thêm component `resources/views/components/dealer-avatar.blade.php`, module `resources/js/dealer-profile.js`; mở rộng `resources/css/account.css`, import module trong `resources/js/account.js`. Header `resources/views/components/competition/staff-header.blade.php` dùng avatar cho Dealer desktop/menu mobile, giữ nguyên nhánh Sales. Có fallback ảnh lỗi về chữ cái tên.
- `app/Http/Requests/ProfileRequest.php`: Dealer được upload theo rule ảnh hiện có + kiểm tra extension JPG/JPEG/PNG/WebP; vẫn cấm email/role/status/dealer_id/user_id/id/avatar_path. Giới hạn5MB,4096×4096px.
- `app/Http/Controllers/ProfileController.php`: chọn view theo role; upload cho chính user đăng nhập, lock/check active và role, lưu path vào cột hiện có. Chỉ xóa file cũ sau commit, dọn file mới nếu cập nhật thất bại; Storage lỗi trả lỗi avatar cho Dealer. GET `profile.avatar` (`/profile/avatar`) không nhận user đích, chỉ đọc ảnh của chính Dealer active; route trong `routes/web.php` dưới auth/account.active và role:dealer. JPEG, private/no-store, nosniff.
- `config/filesystems.php`: disk private `dealer_avatars` tại `storage/app/private/dealer-avatars`, throw=true. Tái sử dụng `app/Services/SalesAvatars.php` để re-encode JPEG512px/UUID; bổ sung kiểm tra kết quả put=false để không ghi path không tồn tại. Không đổi rule/upload/public endpoint Sales.
- Thêm `tests/Feature/DealerPersonalProfileTest.php`. Không sửa C07/C08, approval hoặc trang khác. Không migration mới; đã xác nhận users.avatar_path tồn tại trong DB test thực tế.

## Quyết định kỹ thuật quan trọng
Figma file yKmWQI6BYJ6dponEnidlsq, session61c8e9dd-dcc6-4608-b387-d2f7ddd177c3. Đã đọc metadata desktop207:333/mobile148:252: không có frame riêng Hồ sơ cá nhân trên hai page này; B08 là Sales, B09 là dashboard Dealer. Đọc trực tiếp design context B09219:661/155:398 và screenshot desktop; tái sử dụng header/footer, card trắng radius24, nền#f5f5f7, Inter, đỏ#eb0a1e. Thiết kế profile là áp dụng design system theo yêu cầu, không tuyên bố có frame Profile khớp1:1. Không thêm trường ngoài avatar/họ tên/email/đổi mật khẩu; giữ nguyên Sales/Admin qua nhánh view riêng Dealer. Dùng cột avatar_path hiện có, disk private riêng cho Dealer và route ảnh self-only; không migration mới.

## Kết quả kiểm thử
- Preflight `php .laravel-tools/dealer-profile/preflight.php`: PASS; chỉ SELECT, xác minh MySQL loopback, hostname máy hiện tại, database thực tế toyota_testing, cột users.avatar_path. Các tests mới lặp guard trước transaction. Không chạy DDL/RefreshDatabase/migrate/seed/reset hoặc truy cập production.
- `vendor/bin/phpunit tests/Feature/DealerPersonalProfileTest.php`: **16 tests /200 assertions PASS**. Bao gồm đổi tên/identity bất biến; JPG/PNG/WebP tái mã hóa và thay nhiều lần; SVG/PHP/nội dung giả/ảnh mang đuôi PHP/>5MB/>4096px bị từ chối; Storage put=false không mất ảnh cũ; account bị khóa giữa lúc upload được chặn và dọn ảnh mới; guest/role/owner access; mật khẩu hiện tại sai/confirmation sai/đổi đúng; Sales/Admin giữ view/quyền hiện có.
- Hồi quy bằng wrapper ignored `SafeProfileManagementTest.php` và `SafeSalesAvatarTest.php`: thay duy nhất refreshDatabase bằng transaction guarded để chạy tests cũ mà không migration. Lệnh `vendor/bin/phpunit .laravel-tools/dealer-profile/SafeProfileManagementTest.php .laravel-tools/dealer-profile/SafeSalesAvatarTest.php`: **32 PASS/1 FAIL** (33 tests/201 assertions tổng). FAIL `test_dashboard_shows_avatar_and_missing_file_falls_back_to_placeholder` kỳ vọng ảnh Sales trên Admin dashboard mặc định; source Admin hiện mặc định ranking=dealers và view không render avatar theo giả định test cũ. Chạy riêng bằng `SafeSalesAvatarBaselineTest.php` nạp lại ProfileController/ProfileRequest/SalesAvatars từ baseline trước task: tái hiện cùng FAIL tại SalesAvatarTest.php:69. Không sửa Admin hoặc làm test im lặng; đây không phải regression mới do profile Dealer.
- `npm run build`: PASS, log ignored `.laravel-tools/dealer-profile/build.log`. Pint trên sáu PHP source/test/config/routes liên quan: PASS. `php -l` Blade mới: PASS. `node --check resources/js/dealer-profile.js` và account.js: PASS. Git diff --check: PASS (chỉ cảnh báo CRLF/LF).
- `node .laravel-tools/dealer-profile/browser.mjs`: Chrome1440/390/320 PASS; hai cột desktop/một cột mobile, preview blob ảnh mới, từ chối SVG/quá5MB, native required fields, avatar đã lưu ở profile/header/menu, fallback ảnh hỏng, lỗi validation, không overflow/JS error/asset thiếu/request ghi. HTML là response thật Laravel trong transaction, served fixture qua Playwright; POST backend được kiểm thử bằng PHPUnit, không tuyên bố browser E2E live session. Fixture/test storage cách ly; không ghi dữ liệu giả vào database ứng dụng.
- Đã xem screenshots browser1440/390 và Figma B09 desktop219:661/mobile155:398, đối chiếu header/card/font/palette/radius/spacing design system. Ảnh sau sửa: `.laravel-tools/dealer-profile/after/profile-1440.png`, `profile-390.png`; thêm `saved-*`, `preview-*`, `errors-*` và checks.json. Không có frame Profile riêng để đối chiếu pixel1:1.

## Những lỗi còn tồn tại
Không còn lỗi liên quan được phát hiện trong phạm vi triển khai. Một test Admin cũ FAIL như bằng chứng ở trên. Ảnh tham chiếu người dùng nhắc đến không có attachment trong lượt hiện tại; đối chiếu source và Figma đang truy cập. Chưa kiểm chứng cấu hình/quyền ghi Storage trên production; không deploy hoặc kết nối production.

## Task hoặc bước tiếp theo
Người dùng review screenshot và thay đổi. Khi triển khai được xác nhận riêng: bảo đảm cột users.avatar_path từ migration hiện hữu đã có và PHP được ghi storage/app/private/dealer-avatars; không cần storage:link cho ảnh private. Test Admin cũ cần cập nhật theo thiết kế Admin ở task riêng nếu được yêu cầu.
