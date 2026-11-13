# Sửa cấu hình filesystem avatar Dealer

## Mục tiêu
Sửa missing driver tại /profile, giữ nguyên dealer_avatars và đường dẫn ảnh; thêm kiểm tra config thật không bị Storage::fake che khuất.

## Trạng thái
DONE — 13/11/2026. Đã xử lý config cache local và kiểm thử cả cấu hình không cache/có cache mới, không đổi disk hoặc đường dẫn dữ liệu.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, dealer-personal-profile-refinement.md và avatar-upload-path-fix.md, source/config/controller/service cùng log lỗi hiện tại. Source config/filesystems.php có dealer_avatars.driver=local, root storage/app/private/dealer-avatars, private/throw=true, không phụ thuộc env riêng. Laravel local http://toyota.test đang nạp bootstrap/cache/config.php cũ (06/11/2026), effective filesystems.disks.dealer_avatars=NULL trong khi source có disk. Đó là nguyên nhân missing driver; không phải SalesAvatars chọn sai disk hoặc thiếu biến môi trường.

## Các file source code liên quan
config/filesystems.php, ProfileController và SalesAvatars đã đúng; không dự kiến sửa logic hoặc di chuyển ảnh. Thêm tests/Feature/DealerAvatarFilesystemTest.php và tài liệu này; cache local là file generated ignored.

## Những thay đổi đã thực hiện
- Đã kiểm tra uncached source qua tiến trình riêng: vẫn local/toyota.test/MySQL loopback; so sánh APP_KEY/cipher với cache cũ khớp (chỉ xuất boolean, không xuất secret). Sau preflight chạy **`php artisan config:clear`** trên local. Không clear application cache/session hoặc rebuild production config. Effective dealer_avatars sau đó có driver local/root đúng như source.
- Thêm `tests/Feature/DealerAvatarFilesystemTest.php`: assert config driver/root/private/throw thật trước khi resolve Storage; không Storage::fake. Giữ driver/config gốc, chỉ đổi root sang thư mục fixture UUID cách ly trong test. Kiểm tra file thật, URL profile.avatar trả byte JPEG đúng, Profile/header/menu dùng URL này; NULL/rỗng, thay PNG→JPG→WebP và xóa ảnh cũ đúng.
- Không sửa config/filesystems.php, SalesAvatars, controller, env, APP_KEY, UI hoặc schema trong task này vì source đã có cấu hình chính xác. Những thay đổi source từ các task trước được giữ nguyên; thêm test và tracking này là thay đổi mới.

## Quyết định kỹ thuật quan trọng
Xác minh uncached config vẫn local/loopback và APP_KEY/cipher khớp cache trước khi clear duy nhất config cache local. Không fallback sang disk khác, không đăng ký disk runtime để che cache cũ, không thay đường dẫn, APP_KEY, env, UI hoặc quyền. Tests assert driver/root/private/throw trước mọi fake; upload bằng real local adapter tại root fixture cách ly, DB toyota_testing guarded/transaction.

## Kết quả kiểm thử
- Trước sửa: inspect runtime thực tế configuration_cached=true, effective disk=NULL, source disk có driver local. Cache file last-write06/11/2026. Sau config:clear: configuration_cached=false, effective disk trùng source. Đây là căn cứ xác định cache cũ, không phải thiếu disk/biến env.
- Preflight `php .laravel-tools/dealer-profile/preflight.php`: PASS, actual MySQL toyota_testing/hostname local/loopback/cột avatar_path. Không DDL/migrate/seed/reset. Test mới lặp guard trước transaction.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/DealerAvatarFilesystemTest.php` không config cache: **3 tests/131 assertions PASS**.
- Tạo config cache **cách ly** bằng APP_CONFIG_CACHE=.laravel-tools/avatar-disk-fix/config.php, APP_ENV=testing, DB_DATABASE=toyota_testing và cache/session array; `php artisan config:cache`: PASS. Sau đó chạy `php vendor/phpunit/phpunit/phpunit tests/Feature/DealerAvatarFilesystemTest.php tests/Feature/AvatarUploadPathTest.php tests/Feature/DealerPersonalProfileTest.php`: **27 tests/402 assertions PASS** với cache mới. Đã clear riêng cache thử nghiệm sau kiểm tra. Local bootstrap/cache/config.php vẫn không tồn tại; không tạo cache production hoặc lưu secrets trong tài liệu.
- `php .laravel-tools/avatar-disk-fix/verify-local.php`: boot config local thực tế, vô hiệu hóa DB cho probe, dùng SalesAvatars + dealer_avatars thật để lưu/re-encode/đọc ảnh PNG từ asset có sẵn thành JPEG, xuất bản sao bằng chứng vào ignored qa/avatar.jpg rồi xóa đúng file probe UUID. PASS; danh sách avatar cũ trước/sau giữ nguyên, không ghi DB ứng dụng.
- `node .laravel-tools/avatar-disk-fix/browser.mjs`: Chrome1440/390/320 PASS, ảnh thật decode được ở Profile/header/menu, preview, validation/fallback, không overflow/JS error/asset thiếu. Đã xem ảnh desktop/mobile sau test: `.laravel-tools/avatar-disk-fix/after/saved-1440.png`, `saved-390.png`. HTML render response Laravel từ fixtures test; ảnh là asset minh họa đã qua service/disk thực tế, không thay avatar của tài khoản ứng dụng. Không tuyên bố browser login/submit live session của người dùng.
- Pint --test test mới/config: PASS. php -l test mới/config: PASS. Git diff --check: PASS. Không build assets vì không sửa Blade/CSS/JS. Những tests lịch sử có override cache/Storage::fake không được dùng làm bằng chứng duy nhất cho cấu hình runtime trong task này.

## Những lỗi còn tồn tại
Không còn lỗi liên quan được phát hiện trên local. Chưa kiểm tra phiên trình duyệt đăng nhập thật của người dùng; không kết nối hoặc thay đổi production. Source disk hợp lệ sẵn; không có migration hoặc thay đổi quyền/nghiệp vụ.

## Task hoặc bước tiếp theo
Người dùng có thể upload lại tại /profile local. Nếu sau này deploy source mới với config cache, cần clear/rebuild config trong bước deploy được xác nhận riêng; không mang cache generated cũ sang bản release mới. Không tự deploy.
