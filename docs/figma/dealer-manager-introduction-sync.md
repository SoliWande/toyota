# Ánh xạ thông tin quản lý vào khung giới thiệu đại lý

## Mục tiêu
Khung giới thiệu dùng ảnh/SĐT quản lý đã được chỉ định công khai khi Dealer thiếu dữ liệu riêng; chỉ đúng quản lý/Dealer được cho phép, không ghi đè hoặc đồng bộ toàn hệ thống.

## Trạng thái
DONE — 13/11/2026. Mapping và chức năng local đã kiểm thử; không thay đổi layout Figma. Giới hạn xuất ảnh Figma mobile của task UI trước vẫn giữ nguyên, không tuyên bố nghiệm thu pixel1:1 mobile.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS và tracking referring-dealer-manager-phone-sync, avatar/filesystem fixes, source/model/config/media/controller/tests. Local đã xác minh manager2 quản lý Dealer1, users.phone có dữ liệu nhưng dealers.phone thiếu; avatar tài khoản trên dealer_avatars không liên quan dealers.photo_path/dealer_photos nên card vẫn icon và không có nút gọi. Profile lưu đúng, thiếu quy tắc public fallback giữa hai nguồn. Dealer đã có cơ chế logo/hotline Admin riêng nên phải bảo toàn dữ liệu đó.

## Các file source code liên quan
DealerIntroductionContact (dự kiến), DealerCallPhone, DealerProfileMediaController, component dealer-registration/dealer-card, config/dealer-registration.php, .env.example và tests liên quan. Không sửa C07/C08/UI form/DB schema.

## Những thay đổi đã thực hiện
- Thêm `app/Services/DealerIntroductionContact.php`: resolve manager theo config dealer slug→ID, bắt buộc User active/role Dealer/dealer_id đúng và Dealer active. `photoFor()` ưu tiên ảnh Dealer tồn tại; nếu thiếu ảnh hợp lệ mới đọc avatar của quản lý được chỉ định trên dealer_avatars.
- `app/Services/DealerCallPhone.php`: hotline Dealer có dữ liệu vẫn theo validator/approval hiện hữu, không dùng SĐT khác thay thế; chỉ khi trống mới dùng SĐT hợp lệ của quản lý trong mapping công khai đã xác nhận. Thay SĐT tại profile phản ánh ở lần đọc sau, không copy sang dealers.phone.
- `app/Http/Controllers/DealerProfileMediaController.php`: route dealers.photo hiện có đọc đúng disk/path theo resolver; ảnh quản lý trả JPEG/private/no-store/nosniff để tránh ảnh cũ sau thay/khóa/mất quyền mapping. Ảnh Dealer riêng giữ cache policy cũ. Không mở public route theo User ID hoặc lộ path private.
- `resources/views/components/dealer-registration/dealer-card.blade.php`: kiểm tra ảnh qua resolver, vẫn dùng URL theo Dealer; giữ toàn bộ chữ/layout/CSS/icon/address/hours như task Figma trước. Avatar riêng trong profile/header giữ nguyên cơ chế đã có.
- `config/dealer-registration.php`, `.env.example`: thêm TOYOTA_DEALER_MANAGER_CONTACTS, default{}. Local `.env` chỉ bật đúng pair đã kiểm chứng manager2/Dealer1; giữ các entry đã có, không đưa tên/số/avatar vào source và không bật toàn hệ thống. Config không chứa bản sao SĐT/avatar nên lần thay sau phản ánh ngay.
- Thêm `tests/Feature/DealerManagerIntroductionTest.php`. Không migration/DB write vào ứng dụng; không sửa ProfileController/ProfileRequest/CSS/JS/C07/C08. Các thay đổi cũ giữ nguyên.

## Quyết định kỹ thuật quan trọng
Yêu cầu mới cho phép dùng thông tin quản lý liên kết trong khung công khai, thay kết luận trước chỉ tách biệt/ẩn nút khi thiếu Dealer fields. Vẫn giữ bốn cột riêng trong DB: projection đọc fallback, không copy hoặc ghi đè. Logo/hotline Dealer riêng ưu tiên. Fallback chỉ khi có config map dealer slug→manager ID được chỉ định; kiểm tra active Dealer và User active/role Dealer/dealer_id đúng. Default map rỗng để không công khai người khác. Local chỉ bật map cho manager2/Dealer1 đang được người dùng yêu cầu, không cập nhật dữ liệu DB. SĐT cá nhân khác hotline đã có không thay thế hotline. Ảnh fallback trả qua route Dealer, private/no-store để đổi avatar hoặc khóa user có hiệu lực ngay. Không hardcode identity/phone/photo vào source, không migration/form mới.

## Kết quả kiểm thử
- Preflight toyota_testing actual MySQL loopback/hostname local PASS; các tests mới guard trước transaction. Không chạy migrate/fresh/seed/reset hoặc DDL, không kết nối production.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/DealerManagerIntroductionTest.php tests/Feature/ReferringDealerManagerPhoneTest.php tests/Feature/DealerPersonalProfileTest.php tests/Feature/DealerAvatarFilesystemTest.php tests/Feature/AvatarUploadPathTest.php`: **43 tests/616 assertions PASS**. Bao gồm đổi avatar/phone qua profile và reload public/private URLs, byte ảnh đúng nguồn, không ghi đè fields Dealer, logo/hotline riêng ưu tiên, unapproved Dealer phone không bị thay số khác, thiếu/mất ảnh/số, wrong Dealer/role/status, không có mapping mặc định, thu hồi mapping, storage/DB failures và identity guards từ các suite hiện có.
- Bật local qua helper ignored `enable-local.php`: guard APP_ENV local, URL toyota.test, actual toyota/hostname local/loopback; chỉ SELECT để xác nhận exact pair, ảnh JPEG tồn tại và SĐT hợp lệ trước khi ghi một key config local. Sau bật, diagnostic manager2/Dealer1: dealers.phone vẫn trống, call_button_available=true; không sửa dữ liệu demo hoặc tạo dữ liệu giả. Tên/address/hours hiện có của Dealer local vốn là fixture/demo cũ được giữ nguyên từ DB, không hardcode vào runtime.
- `curl` GET public photo qua host toyota.test resolve127.0.0.1: HTTP200. `live-expectations.php` đối chiếu manager ID, disk ảnh, phone/byte hashes nhưng không in số thật/secrets. Không ghi DB ứng dụng.
- `node .laravel-tools/manager-introduction/browser.mjs`: Chrome **local thật**1440/390 PASS, GET-only, không đăng nhập/POST/đổi dữ liệu. Public D01 tải ảnh/nút gọi; phone hash và ảnh byte hash khớp đúng manager2; ảnh response private/no-store; reload hai lần vẫn đúng; không JS error/overflow/request ghi. Screenshots `after/live-dealer-1440.png`, live-dealer-390.png, đã xem trực quan.
- Cùng browser kiểm tra thêm fixture responses từ toyota_testing trước/sau đổi avatar/SĐT, missing/other Dealer, profile: PASS; ảnh khác nhau và tel chuyển0912345678→0987654321 chỉ trong fixtures, không gọi điện. Screenshot `before-*`, `after-*`, `profile-*` và checks.json trong `.laravel-tools/manager-introduction/after/`.
- Đã mở lại Figma desktop209:464/mobile99:545, session0d9195ca-d308-42f1-abf4-b43f17def057. Layout/text/icon không thay đổi; desktop có vùng ảnh64px, mobile frame không có vùng ảnh nên giữ như Figma. Không sáng tạo layout mobile mới; giới hạn screenshot Figma mobile clip đã ghi trong task UI trước.
- Pint --test và php -l service/controller/config/test/Blade: PASS. Git diff --check PASS. `npm run build`: PASS, manifest asset references hợp lệ. Không deploy production.

## Những lỗi còn tồn tại
Không còn lỗi chức năng liên quan được phát hiện trong phạm vi kiểm thử. Mapping public là opt-in theo từng Dealer/manager và khác nhau giữa môi trường; default không dùng toàn bộ tài khoản. Dealer có hotline riêng vẫn cần approval số đó theo policy cũ, không bị ghi đè. Thiếu ảnh quản lý sẽ fallback icon, thiếu/sai số sẽ ẩn nút. Không thay dữ liệu Demo hiện có thành thông tin giả khác để chụp ảnh.

## Task hoặc bước tiếp theo
Local đã hoạt động với dữ liệu đã lưu trong /profile. Khi có triển khai được xác nhận riêng, operator cấu hình đúng mapping của môi trường đó và rebuild config cache nếu sử dụng; không copy pair local sang production hoặc tự deploy.
