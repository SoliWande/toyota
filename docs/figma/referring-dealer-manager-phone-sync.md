# Đồng bộ khung Đại lý giới thiệu và SĐT quản lý

## Mục tiêu
Đồng bộ card D01/M01 theo Figma, hiển thị ảnh/address/hours/hotline đại lý đúng nguồn; bổ sung SĐT tự quản lý trong hồ sơ Dealer, giữ các mapping độc lập.

## Trạng thái
IN PROGRESS — source và kiểm thử local hoàn tất 13/11/2026; còn giới hạn ảnh Figma mobile bị clip, chưa nghiệm thu thị giác mobile100%.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, tracking dealer-call-button-refinement, registration-success-sidebar-refinement, dealer-personal-profile-refinement và avatar/filesystem fixes; đối chiếu routes/controller/models/requests/media/component/CSS/JS/tests. Figma session91d71ca1-9529-4533-9ae7-66c10daa7f65, desktop209:464/mobile99:545. Card đầy đủ D01 đang sai small/font/spacing/mobile bố cục, ảnh lỗi không có fallback cùng khung; form Dealer chưa cho sửa phone dù users.phone đã có. Giữ nguyên toàn bộ thay đổi sẵn có; baseline ignored .laravel-tools/referring-dealer-phone/before/. Hai ảnh người dùng nhắc không có attachment trong lượt hiện tại, đối chiếu frame trực tiếp.

## Các file source code liên quan
dealer-registration/dealer-card.blade.php, dealer-registration.css, profile/dealer.blade.php, ProfileRequest.php, ProfileController.php; tests trực tiếp và icon assets Figma. DealerPhotoMedia/DealerCallPhone/user/dealer models chỉ đối chiếu, không đổi nghiệp vụ.

## Những thay đổi đã thực hiện
- `resources/views/components/dealer-registration/dealer-card.blade.php`: card đầy đủ có class riêng dr-referring-dealer, labels đúng desktop/mobile; ảnh lấy đúng dealers.photo_path và xác nhận tồn tại trên dealer_photos trước khi dùng dealers.photo. Icon fallback vẫn nằm cùng khung khi ảnh tải lỗi. Dữ liệu tên/address/opening_hours giữ nguyên từ model, hotline dùng DealerCallPhone, không lấy dữ liệu users. Compact cards không nhận CSS mới, giữ icon/nhãn/nút hiện hành.
- `resources/css/dealer-registration.css`: CSS scope card đầy đủ; desktop đúng1200×144/padding32×40/radius28/gap32/small12–16/letter1.2, nút48px; mobile358×254/padding24/gap16/title24–30/nút52px, không hiển thị khung ảnh theo frame mobile, hai dòng meta riêng. Giữ màu/spacing và responsive tới320px. Đã sửa hiện tượng icon mất nét khi đổi màu PNG sau khi xem screenshot; bộ lọc giữ nền trắng và đường nét icon.
- Thêm bốn icon PNG xuất trực tiếp Figma desktop: public/dealer-registration/referring-dealer-{store,location,clock,phone}.png (209:465/475/480/485). Không lấy logo mẫu từ Figma làm dữ liệu đại lý.
- `resources/views/profile/dealer.blade.php`: thêm input Số điện thoại type=tel/autocomplete=tel, value từ old()/user.phone, aria-invalid theo validation, dùng CSS form hiện có.
- `app/Http/Requests/ProfileRequest.php`: chỉ Dealer nhận nullable phone, normalize giống Auth/RegisterRequest, regex9–15digits có + tùy chọn, max30; giữ rule Sales/Admin. `app/Http/Controllers/ProfileController.php`: Dealer chỉ cập nhật phone khi field có trong validated data, vẫn lock/active/role/self-only; omitted giữ số cũ, rỗng xóa. Giữ nguyên email/password/approval/avatar service/disk.
- Thêm `tests/Feature/ReferringDealerManagerPhoneTest.php`. Không migration mới; users.phone được xác nhận tồn tại bằng Schema::hasColumn trên toyota_testing. Dữ liệu avatar/photo/hotline không di chuyển hoặc sao chép lẫn nhau.

## Quyết định kỹ thuật quan trọng
- users.avatar_path → dealer_avatars → profile.avatar, chỉ ảnh tài khoản quản lý; không dùng làm logo đại lý.
- dealers.photo_path → dealer_photos → dealers.photo, ảnh/logo đại lý hiện có (không có cột logo riêng). Không tự lấy ảnh từ avatar tài khoản.
- users.phone → SĐT tài khoản quản lý; dùng nullable + normalize/format9–15digits theo Auth/RegisterRequest, không đổi validation Sales/Admin. Omitted phone giữ nguyên cho upload/name-only; gửi rỗng cho phép xóa.
- dealers.phone → hotline gọi qua DealerCallPhone, giữ exact approved_call_phones policy; thiếu/sai/chưa approved thì ẩn nút. Giữ address/opening_hours từ dealers. Không dùng manager phone thay hotline.
- Desktop card1200×144/radius28/padding32×40/gap32; mobile358×254/padding24/gap16, mobile không có icon/logo trong frame. Không sửa các card compact D02/D03/thành công, C07/C08 hoặc hero.
- users.phone có migration2026_10_03_000008; dealers.photo_path có migration2026_10_19_000017. Không cần migration mới.

## Kết quả kiểm thử
- Preflight `php .laravel-tools/dealer-profile/preflight.php`: PASS; tests lặp guard MySQL loopback/actual toyota_testing/hostname local trước transaction. Không DDL/migrate/seed/reset/deploy, không kết nối production hoặc ghi fixtures vào DB ứng dụng.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/ReferringDealerManagerPhoneTest.php tests/Feature/DealerPersonalProfileTest.php tests/Feature/DealerAvatarFilesystemTest.php tests/Feature/AvatarUploadPathTest.php`: **37 tests/513 assertions PASS**. Bao gồm lưu/normalize/reload/omit/xóa phone, invalid formats/type, ownership/identity, avatar upload/replace và bảo vệ DB/storage failures, image URL, real filesystem config, dữ liệu dealer photo/address/hours/hotline khác manager avatar/phone, missing/broken photo và thiếu/chưa approved hotline. Fixture xuất ảnh sau đó được chạy lại riêng khi thay ảnh minh họa, PASS.
- Lỗi harness ban đầu: file UploadedFile::fake() tạo trong biểu thức một dòng bị destruct/unlink trước file_get_contents; sửa fixture giữ biến file rồi đọc. Lần đầu browser phát hiện label rỗng vì @else viết ngay sau text tiếng Việt; tách directive thành dòng riêng. Các lỗi đã sửa trước kết quả PASS cuối, không che exception hoặc dùng ảnh demo làm dữ liệu runtime.
- `npm run build`: PASS; kiểm tra63 manifest asset references tồn tại, không public/hot. Pint --test ba PHP source/test: PASS. php -l PHP và hai Blade: PASS. Git diff --check: PASS, chỉ warning CRLF/LF CSS.
- `node .laravel-tools/referring-dealer-phone/browser.mjs`: Chrome1440/390/320 PASS. Đo desktop1200×144 và mobile358×254, title mobiley58, metay104/141, nút y178/h52/w310; ảnh dealer hiện desktop và fallback khi lỗi; tel đúng hotline, thiếu số không có anchor, SĐT profile và avatar sau reload, preview; không overflow/JS error/asset thiếu/request ghi. Ảnh card: `.laravel-tools/referring-dealer-phone/after/dealer-1440.png`, dealer-390.png; profile-1440.png/profile-390.png; missing-* và checks.json. Đã xem trực quan ảnh desktop/mobile sau sửa. HTML là response Laravel từ transaction fixtures; ảnh minh họa Dealer và tài khoản khác nhau, chỉ trong test storage. POST/backend đã kiểm tra bằng PHPUnit, không tuyên bố browser submit live tài khoản thật hoặc phát sinh cuộc gọi.
- Đối chiếu trực tiếp Figma desktop209:464 và toàn bộ thông số/context mobile99:545. Screenshot Figma mobile trả1px do frame clip; chưa so ảnh mobile trực tiếp đầy đủ, không tuyên bố khớp100%. Không có frame Profile riêng; input phone dùng design system profile Dealer đã triển khai theo yêu cầu bổ sung field.

## Những lỗi còn tồn tại
Không còn lỗi chức năng liên quan được phát hiện trong phạm vi kiểm thử. Mobile Figma screenshot clip như trên. Hotline chưa approved vẫn ẩn theo policy hiện hữu; người vận hành phải xác nhận quyền công khai/cấu hình cho đúng slug và số thực tế nếu cần bật. Nếu chỉ có users.avatar_path mà dealers.photo_path thiếu, desktop vẫn dùng icon theo design, không tự lấy avatar quản lý làm logo; cần ảnh đại lý qua cơ chế quản trị hiện có. Không tự sửa dữ liệu thực để làm card giống ảnh mẫu.

## Task hoặc bước tiếp theo
Review ảnh sau sửa; cần bản export mobile không bị clip để hoàn tất so ảnh Figma1:1. Khi triển khai được xác nhận riêng, bảo đảm ảnh đại lý và hotline công khai đã được cấu hình đúng nguồn. Không mở phase mới/deploy production.

## Kiểm tra lại theo yêu cầu lặp — 13/11/2026
Đã đọc lại AGENTS, source hiện tại và mở lại design context209:464/99:545 trong cùng session Figma. Phần triển khai đã có đủ; không thêm lại field, không đổi mapping hoặc source lần nữa. Chạy mới preflight toyota_testing, build, bốn file tests và browser: **37 tests/513 assertions PASS**, Chrome1440/390/320 PASS, Pint/syntax/diff-check PASS. Log mới ignored `recheck-build.log`/`recheck-tests.log` trong `.laravel-tools/referring-dealer-phone/`; screenshot được render/chụp lại, đã xem desktop/mobile. Giới hạn ảnh Figma mobile clip và điều kiện approved hotline giữ nguyên như trên. Lượt kiểm tra lại chỉ cập nhật tracking này, không có source mới được sửa và không tạo phase/tài liệu mới.

## Kiểm tra nút gọi chưa hiện sau lưu SĐT — 13/11/2026
Đã đọc lại source và kiểm tra SELECT-only trên database ứng dụng **local** toyota sau guard APP_ENV=local, URL toyota.test, host loopback, actual DATABASE()/hostname đúng máy hiện tại. Manager id2/dealer id1: users.phone đã có giá trị, dealers.phone đang thiếu, không có matching public approval; DealerCallPhone trả NULL nên nút bị ẩn đúng điều kiện. Không phải lỗi lưu phone hoặc render CSS. Không xuất giá trị SĐT/secrets ra log chẩn đoán, không ghi dữ liệu. Đã hỏi người dùng muốn hotline riêng hay xác nhận SĐT vừa lưu là hotline được phép công khai; chưa tự copy users.phone sang dealers.phone hoặc thêm approval vì yêu cầu trước phân biệt hai nguồn. Lượt này chỉ cập nhật tracking, không sửa source hoặc chạy test ghi DB.
