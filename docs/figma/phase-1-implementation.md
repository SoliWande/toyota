# Toyota Community — Phase 1: kết quả triển khai giao diện

Ngày: 02/11/2026. Tham chiếu: [analysis-report.md](analysis-report.md).

## Phạm vi đã thực hiện

Triển khai Blade, Tailwind CSS 4 qua entry riêng và JavaScript ES modules. Không tạo SPA, React hoặc Next.js. Không chạy migration, không đọc/ghi dữ liệu khách thật trong QA, không thay đổi database, approval, scoring hoặc phân quyền. Không commit, push hoặc deploy.

Figma file `yKmWQI6BYJ6dponEnidlsq`: đã lấy lại design context cho D01–D06, M01–M06, metadata và các ảnh tham chiếu. D05 context và một số screenshots có lần timeout; D05 đã retry context thành công. Screenshot D02 trong lần lấy mới bị lỗi; screenshot D02 đọc thành công từ đợt phân tích trước và design context mới vẫn có. Không coi mọi lần gọi MCP là thành công.

## Các màn hình và cách hoạt động

| Màn | Kết quả Phase 1 |
|---|---|
| D01/M01 | Landing gồm header, hero riêng desktop/mobile, panel bước 1, radio xe, quyền lợi, carousel mobile, hướng dẫn, thể lệ, FAQ, Dealer và footer |
| D02/M02 | Blade riêng, địa chỉ chi tiết bắt buộc, province/ward, xe, biển số, năm, ODO, Facebook, consent và sidebar responsive |
| D03/M03 | Dùng cùng D02, nhận field errors từ caller; JS validation thật cho required/phone/Facebook/numeric, summary tự đếm, focus theo thứ tự DOM, aria-invalid/describedby |
| D04/M04 | View riêng, ảnh group riêng desktop/mobile, illustration, hướng dẫn, timeline và Dealer; chưa nối public route hoặc cấp confirmation chứa PII |
| D05/M05 | Component state trong panel bước 1, chỉ render nếu caller đưa `duplicatePhone=true` cùng lỗi phone từ backend; không đoán trùng trong JS |
| D06/M06 | Dealer inactive hiển thị QR không còn hiệu lực trong panel D01, không có form intake; slug không tồn tại vẫn theo 404 hiện có |

### Giữ đăng ký hiện tại hoạt động

- Không sửa `DealerRegistrationController`, `DealerRegistrationRequest`, `CustomerSubmission`, `DealerProfile`, Admin approval, leaderboard hoặc các migration.
- Giữ nguyên POST `/{slug}/registrations` và các routes QR đã phát hành.
- Biểu mẫu hoàn chỉnh hiện tại vẫn có trong mục mở rộng “Gửi đăng ký với đại lý bằng biểu mẫu hiện tại”. Nó vẫn có CSRF, địa chỉ, consent, honeypot và đủ các trường server yêu cầu.
- Khi có validation errors hoặc registration_success, mục này mở sẵn. Khi redirect về `#registration`, JS đưa focus về biểu mẫu đầy đủ đang mở.
- Panel mới không gọi endpoint cũ. Trên production, Tiếp tục kiểm tra đầu vào rồi mở biểu mẫu hiện tại, chuyển họ tên/phone/xe trong bộ nhớ trang; không ghi draft, không tự gửi form.
- Trong local/testing preview, Tiếp tục chuyển sang view bước 2 đã render ẩn bằng state DOM, giữ dữ liệu trong bộ nhớ trang. Quay lại không xóa thông tin đang nhập. Refresh không khôi phục draft.
- Hoàn tất ở view bước 2 chỉ kiểm tra frontend và thông báo chưa gửi/lưu; không tạo submission.
- UI form dùng method dialog, nút submit bị disabled trước khi JS init; không có cơ chế fallback GET làm lộ PII trong URL khi JS không tải.
- Không dùng localStorage, session draft hoặc public submission ID.

### Dữ liệu thật và dữ liệu chưa có

Tên, địa chỉ, điện thoại, giờ mở cửa và ảnh Dealer lấy từ model hiện tại. Nếu có photo_path, dùng route ảnh Dealer hiện có; không thay bằng ảnh Dealer mẫu. Thông tin profile/QR/điểm approved còn trong mục “Hồ sơ và thành tích đại lý”. Canonical URL được giữ qua DealerProfile cho model thật.

Không hardcode Toyota An Sương, 1.126 thành viên, hotline 1900 xxxx hoặc lịch chiến dịch. Config mới `config/dealer-registration.php` mặc định null cho dữ liệu chưa được xác nhận. Số thành viên bị ẩn nếu không có; lịch hiển thị đang cập nhật; CTA group/lookup/terms/privacy chưa có nguồn được thể hiện không khả dụng, không trỏ link giả.

Lookup chưa được triển khai; không dùng số điện thoại để trả dữ liệu khách khác. Dealer Facebook profile không tự được xem là Facebook group.

## Danh sách file tạo/chỉnh sửa

### File hiện có đã chỉnh

1. `resources/views/dealers/show.blade.php`: dùng layout Dealer riêng, landing include và preview bước 2 local.
2. `resources/views/components/dealer-registration-form.blade.php`: thêm prop sectionId; không đổi fields, action hay validation của form cũ.
3. `vite.config.js`: thêm hai entry riêng; giữ nguyên app.css/app.js.
4. `routes/web.php`: chỉ thêm GET previews local/testing. Controller còn kiểm tra environment tại request time để chống route cache nhầm môi trường.

### File mới của giao diện

```text
config/dealer-registration.php
app/Http/Controllers/DealerRegistrationPreviewController.php
resources/css/dealer-registration.css
resources/js/dealer-registration.js
resources/views/components/dealer-registration/layout.blade.php
resources/views/components/dealer-registration/icon.blade.php
resources/views/components/dealer-registration/link.blade.php
resources/views/components/dealer-registration/field.blade.php
resources/views/components/dealer-registration/dealer-card.blade.php
resources/views/components/dealer-registration/step-one.blade.php
resources/views/dealers/registration/landing-content.blade.php
resources/views/dealers/registration/step-two.blade.php
resources/views/dealers/registration/step-two-content.blade.php
resources/views/dealers/registration/success.blade.php
public/dealer-registration/*.png (22 assets)
```

### Tài liệu và công cụ QA mới

```text
docs/figma/assets-manifest.json
docs/figma/phase-1-implementation.md
docs/figma/qa/render-check.php
docs/figma/qa/frontend-check.mjs
docs/figma/qa/asset-check.php
docs/figma/qa/router.php
```

`analysis-report.md` được giữ nguyên. Không thay global app.css/app.js, requests/models/services nghiệp vụ, migrations hoặc package manifests để hạ phiên bản dependency.

## Assets đã lấy

Nguồn node và slot đầy đủ trong [assets-manifest.json](assets-manifest.json). Tất cả assets runtime nằm local, không có URL MCP tạm trong views.

| Tên | Node | Kích thước PNG |
|---|---|---|
| hero-desktop | 223:854 | 1440×680 |
| hero-mobile | 123:342 | 390×780 |
| logo | 57:851 | 50×36 |
| benefit-service | 209:334 | 384×240 |
| benefit-caravan | 209:342 | 384×240 |
| benefit-expert | 209:351 | 384×240 |
| group | 212:419 | 300×240 |
| group-mobile | 99:574 | 316×120 |
| success | 212:405 | 96×96 |
| qr-invalid | 133:614 | 104×104 |
| dealer | 209:281 | 11×11 |
| lock | 209:293 | 16×16 |
| arrow | 209:271 | 18×18 |
| wrench | 209:338 | 22×22 |
| tent | 209:346 | 22×22 |
| chat | 209:355 | 22×22 |
| location | 209:475 | 16×16 |
| clock | 209:480 | 16×16 |
| phone | 209:485 | 18×18 |
| search | 212:471 | 18×18 |
| info | 212:425 | 18×18 |
| car | 211:351 | 22×22 |

`download_assets` trả nguồn export nhưng HTTP trực tiếp qua curl/Invoke-WebRequest bị chặn. Assets được lưu bằng PNG isolated-node render qua `get_screenshot` có base64. Đây là render đúng các node ảnh/icon, không phải screenshot toàn trang làm nền thay HTML. Hero chỉ có artwork; chữ và form vẫn là HTML/CSS.

**Còn thiếu:** SVG vector gốc; font Inter và Montserrat SemiBold/Bold. Kênh export SVG qua use_figma yêu cầu approval không khả dụng trong phiên này. CSS khai báo đúng tên font với Arial fallback, không tải font bên ngoài hoặc dùng font khác giả làm Inter. PNG hiện ở kích thước native, chưa có bộ 2x cho màn hình mật độ cao. Cần hoàn thiện SVG/fonts/2x khi có kênh tải được phép.

## Kết quả kiểm tra

| Kiểm tra | Kết quả |
|---|---|
| PHP syntax controller/config/routes | Qua |
| `php artisan view:cache` với DB_CONNECTION vô hiệu hóa | Qua |
| `php docs/figma/qa/render-check.php` | Qua 14 assertions về render và contract routes |
| `node --check resources/js/dealer-registration.js` | Qua |
| `node --experimental-specifier-resolution=node docs/figma/qa/frontend-check.mjs` | Qua chuẩn hóa phone, Facebook, required validation, số lỗi và không đoán duplicate |
| `php docs/figma/qa/asset-check.php` | Qua 22 PNG có kích thước/non-empty, không URL MCP tạm |
| HTTP GET preview landing/step-two/success/inactive | Cả bốn HTTP 200, Cache-Control no-store/private |
| Preview production guard | Qua, controller trả 404 cả khi route còn trong cache |
| Frontend production build | Chưa qua do dependency không cài được trong môi trường |
| Screenshot ứng dụng 1440px/390px | Chưa thực hiện được do Chrome headless bị policy từ chối |
| DB integration / approval race / production pages browser regression | Không chạy; chưa có kết quả xác minh runtime |

### Giới hạn build

- Node hiện có: 18.8.0.
- `npm ci --ignore-scripts`: lỗi kết nối EACCES tới npm registry; có cảnh báo Tailwind oxide 4.3.3 yêu cầu Node >=20.
- Thử `npm ci --offline`: thiếu cache vite-6.4.3.tgz.
- Thử giải dependency build local không lưu manifest: vẫn thiếu metadata @tailwindcss/postcss trong cache. Không giữ thay đổi hạ phiên bản trong package.json/package-lock.json.
- `npm run build`: vite không có trong node_modules nên thất bại. Không có production bundle mới được xác nhận.
- Cần Node đáp ứng engines của dependency đã khóa và quyền truy cập registry/cache đầy đủ, sau đó `npm ci` và `npm run build` trước khi đưa giao diện mới lên môi trường sử dụng.

Không báo cáo UI đã đạt pixel-perfect: chưa có browser visual diff. QA router chỉ phục vụ CSS nguồn (bỏ Tailwind import) và ES modules để kiểm tra local khi thiếu build, không thay thế Vite/Tailwind production build.

### Cách xem trước an toàn

Trong app chạy local/testing, routes:

```text
/_preview/dealer-registration/landing
/_preview/dealer-registration/step-two
/_preview/dealer-registration/success
/_preview/dealer-registration/inactive
```

Hoặc chạy QA server chỉ trên loopback:

```powershell
php -S 127.0.0.1:8765 -t public docs/figma/qa/router.php
```

Router QA chỉ nhận loopback, đặt database connection không tồn tại, cache/session array; không truy vấn DB. Preview sử dụng Dealer model chưa lưu, không customer fixture và không fake phone/plate. Không deploy router QA như production entry point.

Để xem D03, gửi form bước 2 trống hoặc nhập Facebook không hợp lệ; số lỗi được tính thực tế. Không có URL/query parameter bật kết quả phone/plate duplicate giả. D05 được kiểm tra render contract bằng dữ liệu lỗi server đưa trực tiếp vào view; backend duplicate chưa tồn tại và chưa được triển khai.

## Khác biệt còn lại so với Figma

1. Arial fallback khi máy khách chưa có Inter/Montserrat; chưa thể bảo đảm typography chính xác.
2. Vector dùng PNG native; chưa đạt độ sắc nét như SVG hoặc ảnh @2x.
3. Không hiển thị số thành viên, hotline, lịch, link group khi thiếu cấu hình xác nhận; không cam kết 48 giờ.
4. D02 thêm address_line theo ràng buộc hiện tại; nội dung consent giữ nguyên bản đang dùng và dài hơn mẫu Figma.
5. Production có mục đăng ký hiện tại và thông báo hai bước chưa kích hoạt để giữ chức năng; đây là khác biệt có chủ đích trong Phase 1.
6. D04 khi chưa có confirmation hiển thị trạng thái chưa có dữ liệu, không nhận là đã có submission và không hiển thị PII mẫu.
7. D05/D06 được đặt trong landing panel theo nghiệp vụ đã chốt, không thành page độc lập.
8. M02 có các dữ liệu chưa nhập và hướng dẫn thay placeholder cá nhân từ Figma; sidebar chuyển xuống dưới trên mobile.
9. Breakpoints tablet là phương án CSS đề xuất; chưa được kiểm chứng bằng browser screenshots.
10. Hero desktop/mobile và ảnh group mobile dùng đúng nguồn riêng; chưa có đối chiếu rendered crop/spacing cuối trong trình duyệt.

## Phase 2 cần xử lý

- Draft hai bước có TTL, session/Dealer binding, quay lại sửa, nhiều tab và refresh; không tạo submission ở bước 1.
- Contract final POST, idempotency và race khi nhận đăng ký; không bỏ cooldown/rate limit/CSRF/honeypot hiện tại.
- Chốt duplicate phone/plate, phạm vi và trạng thái; đối chiếu logic Admin edit đã có. Không thêm unique index tùy tiện cho dữ liệu lịch sử.
- Nhận/chuẩn hóa Facebook của khách nhưng vẫn giữ Admin xác minh verified_facebook_url trước approval.
- Lưu ODO bằng schema bổ sung nếu được yêu cầu sau này; không sửa migration cũ.
- Mapping FJ và model/vehicle_description tương thích dữ liệu QR hiện tại.
- Cấp confirmation DTO an toàn cho D04, không public lookup theo ID/phone đơn thuần.
- Public lookup chỉ sau khi có cơ chế quyền xem và thiết kế đầy đủ D07–D15.
- Xác nhận config group/hotline/thể lệ/consent và hoàn thiện fonts/vector/@2x.
- Build đúng môi trường, browser QA 390px/tablet/1440px, keyboard/safe-area, focus, aria và visual diff.
- Kiểm thử hồi quy approval/Dealer-only scoring/QR cũ/Sales/Admin trên DB test cách ly; không chạy suite trên database production.

## Các hành động bị môi trường từ chối

Kênh export JavaScript `use_figma` trả thông báo yêu cầu approval trong khi policy là never. Lệnh khởi chạy Chrome headless qua Start-Process bị công cụ thực thi chặn theo policy. Không thay đổi policy hoặc yêu cầu cấp lại quyền; chuyển sang render checks và báo cáo rõ các phần chưa xác minh. Git status vẫn bị dubious ownership; không sửa Git config/ownership và không tuyên bố working tree sạch dựa trên Git.
