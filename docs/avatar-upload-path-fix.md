# Sửa đường dẫn upload avatar tại /profile

## Mục tiêu
Sửa ValueError đường dẫn rỗng, kiểm chứng upload/DB failure và bảo vệ ảnh cũ; không đổi UI/nghiệp vụ.

## Trạng thái
DONE — 13/11/2026. Lỗi đường dẫn được tái hiện với source trước sửa và kiểm thử sau sửa; không thay đổi middleware/UI.

## Hiện trạng trước khi chỉnh sửa
Log local lúc08:08:41/08:09:03 ghi lỗi tại Illuminate/Http/FileHelpers.php:64: getimagesize(''), gọi bởi validateDimensions khi resolve ProfileRequest, trước khi vào ProfileController. EnsureAccountIsActive.php:22 chỉ là return $next($request). Không phải thao tác xóa avatar NULL. Controller và validation đều đang phụ thuộc UploadedFile::getRealPath(), có thể trả false trong khi getPathname vẫn có giá trị. Cần tái hiện và sửa cả hai chỗ; không catch ValueError hoặc sửa vendor. Tracking dealer-personal-profile-refinement.md trước đó chỉ chứng minh UploadedFile::fake, chưa kiểm tra HTTP upload native.

## Các file source code liên quan
app/Services/SalesAvatars.php, app/Http/Requests/ProfileRequest.php, app/Http/Controllers/ProfileController.php, tests/Feature/DealerPersonalProfileTest.php; luồng upload Sales/Admin liên quan dùng cùng service/rules. Model User không có file delete observer; config dealer_avatars private, throw=true. Không sửa middleware.

## Những thay đổi đã thực hiện
- Thêm `app/Rules/ReadableAvatarImage.php`: lấy getPathname, kiểm tra không rỗng/không NUL/hợp lệ/tồn tại/đọc được trước mọi thao tác; đọc kích thước từ pathname đã kiểm tra, giữ giới hạn4096px. `SalesAvatars::rules()` dùng rule này thay dimensions của Laravel, vẫn giữ bail/file/image/MIME/5MB. Không sửa vendor hoặc catch ValueError để bỏ qua lỗi.
- `app/Services/SalesAvatars.php`: bảo vệ source path trước file_get_contents, từ chối nội dung rỗng, giữ re-encode JPEG/UUID; dọn file mới nếu adapter put trả false/ném lỗi (kể cả đã ghi một phần), rethrow lỗi gốc. `deleteManaged()` chỉ thao tác hai disk avatar và filename uploaded-/demo-UUID.jpg; NULL/rỗng/default/absolute/traversal/path lạ bị bỏ qua trước khi gọi Storage.
- `app/Http/Controllers/ProfileController.php`: dùng getPathname thay getRealPath; chỉ chuyển RuntimeException thành lỗi avatar, không nuốt ValueError. File mới thành công trước transaction, lock/active/role/owner giữ nguyên; chỉ xóa ảnh cũ hợp lệ sau commit; DB lỗi dọn ảnh mới, rethrow. `app/Http/Controllers/Admin/SalesController.php` đổi đúng các thao tác avatar tương ứng vì dùng chung rules/service; không đổi quyền/nghiệp vụ quản trị.
- Thêm `tests/Feature/AvatarUploadPathTest.php`; cập nhật fixture trong `tests/Feature/DealerPersonalProfileTest.php` sang tên UUID thực tế và partial mock để kiểm tra cleanup mới. Không sửa ProfileRequest, model, config, middleware, Blade/CSS/JS, migration hoặc dữ liệu ứng dụng.

## Quyết định kỹ thuật quan trọng
Chặn đường dẫn rỗng trước thao tác file; dùng đường dẫn file tạm có kiểm tra hợp lệ/đọc được, không dựa getRealPath trả false. Chỉ xóa filename avatar do hệ thống sinh trong disk cho phép sau commit; bảo vệ default/path lạ. Giữ thứ tự file mới thành công → transaction DB → xóa cũ sau commit.

## Kết quả kiểm thử
- Đã kiểm tra log08:08:41/08:09:03 và toàn bộ stack: lỗi dimensions khi resolve FormRequest; không phải avatar cũ hoặc middleware. Baseline ignored `.laravel-tools/avatar-path-fix/before/`. `BaselineAvatarUploadPathTest.php --filter test_first_upload_and_replacement_with_unresolvable_realpath` nạp source cũ: cả NULL/rỗng tái hiện **ValueError getimagesize('') tại FileHelpers.php:64**. Cùng regression test sau sửa PASS; bảo đảm không chỉ dựa vào UploadedFile::fake có realpath luôn hợp lệ.
- `php .laravel-tools/dealer-profile/preflight.php`: PASS, actual MySQL loopback toyota_testing/hostname local/users.avatar_path. Test fixtures dùng transactions và guard thực tế, không RefreshDatabase/DDL/migrate/seed/reset.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/AvatarUploadPathTest.php tests/Feature/DealerPersonalProfileTest.php .laravel-tools/dealer-profile/SafeProfileManagementTest.php`: **47 tests/405 assertions PASS**. Bao gồm NULL/rỗng, realpath=false nhưng pathname đọc được, invalid/missing temp file, thay ảnh lần hai, file sai/quá giới hạn, Storage put=false/ghi một phần, simulated QueryException khi save và rollback, cleanup không mất ảnh cũ, bảo vệ default/path ngoài thư mục, ownership và mật khẩu.
- Hồi quy Sales avatar scoped qua wrapper transaction từ task trước, filter `test_sales_can_upload_replace|test_avatar_endpoint_is_private|test_invalid_uploads_are_rejected|test_avatar_path_cannot_be_injected`: **7 tests/57 assertions PASS**. Hồi quy Sales public/Admin upload qua wrapper `SafeSalesPublicAvatarTest.php`, filter `test_uploaded_avatar_is_reencoded|test_admin_can_manage_avatar|test_admin_upload_validation`: **5 tests/57 assertions PASS**. Tổng ba lệnh **59 tests/519 assertions PASS**. Không chạy lại test dashboard Admin lỗi lịch sử ngoài phạm vi đã ghi trong tracking trước.
- `php .laravel-tools/avatar-path-fix/storage-check.php`: private disk root đúng storage/app/private/dealer-avatars; tạo file probe riêng/read/delete thành công, không kết nối DB. Thư mục tồn tại sau kiểm tra, không đổi php.ini hoặc ACL. PHP CLI8.5.11 upload_tmp_dir rỗng (dùng Windows Temp), upload_max_filesize/post_max_size2G; temp đọc được. Đây là cấu hình CLI local, không khẳng định PHP web/production giống hệt.
- Native HTTP multipart qua PHP server127.0.0.1:8789: lần đầu smoke bị419 do CSRF nên không tính PASS; fixture sau đó bật đúng unit-test console mode chỉ trong router ignored, user fixture + transaction trên toyota_testing, disk nằm `.laravel-tools/avatar-path-fix/native-storage`. Native is_uploaded_file=true; PATCH /profile qua kernel trả302, DB path và ảnh JPEG đọc được, rendered Profile/header/mobile menu có3 ảnh đúng route. Cuối request rollback và dọn file; đã dừng server. Không phải smoke tài khoản thật trên production. Upload probe CLI-server không tự tái hiện realpath=false; điều kiện này được chứng minh bằng regression có kiểm soát và log web thực tế, chưa kết luận nguyên nhân môi trường làm canonical resolution thất bại.
- `node .laravel-tools/dealer-profile/browser.mjs`: Chrome1440/390/320 PASS, saved avatar ở Profile/header/menu, preview/validation/fallback, không JS error/missing asset/overflow. HTML fixture render từ response thật trong test transaction; không tuyên bố browser login/upload live trên tài khoản thật. Screenshot giữ tại `.laravel-tools/dealer-profile/after/`.
- Pint --test và php -l sáu file PHP liên quan: PASS; git diff --check PASS. Không build lại assets vì không có Blade/CSS/JS thay đổi trong task này.

## Những lỗi còn tồn tại
Không còn lỗi liên quan được phát hiện trong phạm vi kiểm thử. Chưa tái kiểm bằng tài khoản thật/PHP SAPI của phiên người dùng gặp lỗi; không truy cập production hoặc secrets. Không thay đổi ngày/luồng nghiệp vụ/UI. Ảnh legacy không có tên UUID được giữ nguyên thay vì xóa theo suy đoán.

## Task hoặc bước tiếp theo
Người dùng thử lại /profile trong phiên web hiện tại. Nếu còn lỗi, cần stack trace/log mới để đối chiếu SAPI/temp runtime; không tự deploy hoặc thay php.ini/ACL.
