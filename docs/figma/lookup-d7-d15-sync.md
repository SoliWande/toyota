# Đối chiếu giao diện D7–D15 với Figma

Ngày kiểm tra: 03/11/2026. Đã đọc Figma trực tiếp trước khi sửa code, sửa giao diện,
chạy build, chụp ảnh bằng Chrome/Playwright và xem trực quan từng màn hình.
Không tuyên bố khớp tuyệt đối: các khác biệt về dữ liệu, quyền truy cập và nội dung
nghiệp vụ còn lại được ghi rõ bên dưới.

## Thiết kế và bằng chứng

File Figma: [Toyota Community](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq).
MCP session: `a1dd4408-c48b-4067-87c0-95a7b2a3ed67`.
Đã gọi `get_design_context` kèm screenshot, `get_metadata` và `get_screenshot` cho
các frame desktop. Có đọc thêm M08 và M12 để lấy asset đúng kích thước cho mobile.

- [Thông số lấy từ Figma](qa/lookup-sync-design-evidence.json).
- [Trang so sánh Figma / trước sửa / sau sửa](qa/screenshots/lookup-sync/index.html).
- [Số đo và kiểm tra tài nguyên trên trình duyệt](qa/screenshots/lookup-sync/final/evidence.json).
- Ảnh Figma ở `qa/screenshots/lookup-sync/figma/`; ảnh trước sửa ở `before/`;
  ảnh bàn giao ở `final/`. File không có hậu tố là ảnh desktop 1440px;
  `-390` và `-768` là ảnh kiểm tra responsive.

## Kết quả từng màn hình

| Màn hình | Node Figma | Sai lệch phát hiện và đã sửa | Kiểm tra trực quan |
| --- | --- | --- | --- |
| D7 – Cần bổ sung | 213:533 | Cột phải 400px thay vì 420px; tên 18px thay vì 22px; chip quá nhỏ; timeline 24px và gap 16px thay vì node 28px và ba cột liền nhau; đường nối sai màu; input bổ sung nền xám, label thiếu độ đậm, helper sai màu; dư CTA gọi đại lý khi đã có form bổ sung được cấp quyền. Đã sửa các điểm này, dùng SVG cảnh báo gốc và placeholder đúng thiết kế. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D07.png) |
| D8 – Đã gửi bổ sung | 212:647 | Khối thông báo bị đẩy xuống, vùng minh hoạ cao quá mức, PNG 160px mờ thay vì SVG 180px, card bo góc ở cạnh giữa, nút phụ nền xám thay vì trắng có viền. Đã sửa padding trên 72px, khối 480/720px, chiều cao 420px, radius ngoài 32px, gap và typography. Mobile dùng đúng SVG 96px của M08, logo có lề 20px, nội dung căn giữa và không hiển thị footer như thiết kế M08. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D08.png) |
| D9 – Thể lệ đầy đủ | 213:1447 | **Có tồn tại trong Figma**, đã có trang trong source; không tạo màn hình mới. Khoảng cách sau số thứ tự bị rộng bởi `min-width:2ch`. Đã bỏ chiều rộng ép, dùng độ rộng chữ thực như Figma; đồng bộ font, logo và chiều cao header. Đã kiểm tra đủ tám mục, sidebar, hàng được chọn, CTA và footer. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D09.png) |
| D10 – Không tìm thấy | 213:636 | Thiếu vòng tròn 88px và icon tìm kiếm 38px. Đã bổ sung SVG gốc; giữ padding 48px, gap 32px và card cao 184px; đồng bộ nút tra cứu có icon. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D10.png) |
| D11 – Chưa hoàn tất | 213:702 | Sai chia cột, font tên, chip và timeline; panel hành động căn giữa dọc thay vì bám trên. Đã đưa panel về 420px, padding 32px, gap 14px, node hiện tại màu đỏ và hai node chờ đúng SVG, card 258px. Giữ nguyên link tiếp tục đăng ký. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D11.png) |
| D12 – Chờ vào group | 213:797 | CSS chung ghi đè font tên/tiêu đề panel, line-height và màu mô tả; đường nối bước đã hoàn tất bị xám và thiếu 8px chiều dài. Đã sửa specificity, khoảng cách rail, SVG desktop 28px/mobile 24px, font tên 22/28px, tiêu đề panel 18/24px, mô tả 14/20px. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D12.png) |
| D13 – Chờ duyệt | 213:894 | Timeline dàn sai khoảng cách và kích thước; icon chờ dùng ký tự thay vì SVG; chip quá nhỏ. Đã giữ bố cục một cột 1200px, card 238px, ba bước chia đều, SVG đồng hồ và màu mô tả chờ đúng Figma. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D13.png) |
| D14 – Đã là thành viên | 213:989 | Chip xanh sai màu, sai kích thước; timeline sai hình học; dư mô tả dưới hai bước đã hoàn tất. Đã sửa chip `#e9f7ee`/`#1a7f3c`, ba SVG hoàn tất, bỏ hai mô tả dư và dùng nội dung xác nhận thành viên theo Figma. Panel và CTA nền tối giữ link group hiện hành. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D14.png) |
| D15 – Chưa thể xác nhận | 213:1086 | Sai font/chip/timeline/panel; CTA gọi đại lý nền xám thay vì trắng có viền; thiếu số liên hệ trên nút. Đã sửa hình học và style, hiển thị số đại lý thật khi hợp lệ; nội dung lý do vẫn lấy từ backend. Không đánh dấu bước Group hoàn tất khi backend chưa xác nhận. | [Ảnh sau sửa](qa/screenshots/lookup-sync/final/D15.png) |

Các thay đổi dùng chung trong phạm vi tra cứu/thể lệ: header tổng cao 72px thay vì
73px kể cả border; logo SVG gốc; khoảng cách brand và header action; footer đúng
nội dung thiết kế; font Inter/Montserrat được tải cục bộ; inset chữ trong input
tính cả border; nút tra cứu có icon 18px; ẩn dấu sao thị giác theo Figma nhưng giữ
thuộc tính `required` và toàn bộ thông báo lỗi/ARIA. Avatar dùng chữ đầu của từ
đầu/cuối trong **tên đã che do backend cung cấp**, không đọc thêm thông tin hồ sơ.

## Số đo sau sửa

Các số dưới đây đã đo từ DOM với CSS/JS build thực tế. Bộ kiểm tra đối chiếu mốc
Figma với dung sai 0,5px; các giá trị chính trong bảng đều đạt.

| Màn hình | Kích thước frame | Card kết quả / thông báo | Footer Y |
| --- | --- | --- | --- |
| D7 | 1440 × 883 | x120, y424, 1200 × 258 | 782 |
| D8 | 1440 × 765 | x120, y144, 1200 × 420; minh hoạ trái 480px | 664 |
| D9 | 1440 × 1578 | Sidebar 320px, gap 24px, bài thể lệ 856px | 1477 |
| D10 | 1440 × 809 | x120, y424, 1200 × 184 | 708 |
| D11 | 1440 × 883 | x120, y424, 1200 × 258 | 782 |
| D12 | 1440 × 883 | x120, y424, 1200 × 258 | 782 |
| D13 | 1440 × 863 | x120, y424, 1200 × 238 | 762 |
| D14 | 1440 × 883 | x120, y424, 1200 × 258 | 782 |
| D15 | 1440 × 883 | x120, y424, 1200 × 258 | 782 |

Form tra cứu chung: x120/y224, rộng 1200px, cao 140px; hai input 477,5 × 56px;
gap 20px; radius card 28px, input 16px; nút 141 × 56px. Các card hai cột có cột
thông tin 780px và cột hành động 420px. Font heading Inter 800, 40/46px, tracking
−0,8px; tên người đăng ký 700, 22/28px; timeline 600, 16/22px và mô tả 400,
14/20px. Không thêm shadow khi Figma không có.

## File source đã thay đổi trong tác vụ này

- `resources/css/dealer-registration.css`.
- `resources/views/dealers/registration/lookup.blade.php`.
- `resources/views/components/dealer-registration/layout.blade.php`.
- `resources/views/components/dealer-registration/lookup-state.blade.php`.
- `resources/views/components/dealer-registration/lookup-next.blade.php`.
- `resources/views/components/dealer-registration/lookup-waiting-group.blade.php`.
- Thêm `resources/views/components/dealer-registration/lookup-avatar.blade.php`.

Tài nguyên thêm tại `public/dealer-registration/`:

- `lookup-logo.svg`, `lookup-search.svg`, `lookup-dealer.svg`, `lookup-submit.svg`,
  `lookup-empty.svg`, `lookup-success.svg`, `lookup-success-mobile.svg`.
- `lookup-done.svg`, `lookup-current.svg`, `lookup-upcoming.svg`,
  `lookup-warning.svg`, `lookup-waiting.svg`.
- `lookup-dot-neutral.svg`, `lookup-dot-blue.svg`, `lookup-dot-green.svg`,
  `lookup-dot-orange.svg`.
- `lookup-done-mobile.svg`, `lookup-current-mobile.svg`,
  `lookup-upcoming-mobile.svg`.
- `lookup-fonts.css`, `inter-latin.woff2`, `inter-latin-ext.woff2`,
  `inter-vietnamese.woff2`, `montserrat-latin.woff2`, `inter-OFL.txt`,
  `montserrat-OFL.txt`. Các font local chỉ được nạp trên lookup/thể lệ.

QA/báo cáo thêm: file này, `qa/lookup-sync-design-evidence.json`,
`qa/lookup-visual-router.php`, `qa/lookup-visual.mjs`, trang so sánh và các ảnh/bằng
chứng trong `qa/screenshots/lookup-sync/`. Không sửa controller, resolver, API,
migration, dữ liệu khách hàng, route ứng dụng hoặc JavaScript nghiệp vụ.
Repo có nhiều thay đổi sẵn từ trước; danh sách trên chỉ ghi thay đổi của tác vụ này.

## Kiểm tra đã chạy

- `npm run build`: PASS, dùng bản build này để chụp ảnh cuối.
- `php vendor/bin/phpunit tests/Feature --filter 'RegistrationLookup|CampaignRules'`:
  **49 tests, 599 assertions, PASS** trên database kiểm thử `toyota_testing`, với
  transaction và kiểm tra cách ly có sẵn của dự án.
- `php docs/figma/qa/render-check.php`: PASS cho Blade, route, dữ liệu động,
  CSRF, các trạng thái tra cứu, quyền form bổ sung, nội dung thể lệ và ID truy cập.
- Hai harness `lookup-validation-check.mjs` và `supplement-validation-check.mjs`:
  PASS cho lỗi inline, focus/ARIA, giữ giá trị, loading và chống gửi lặp.
- Chrome/Playwright: chụp **27 ảnh cuối** cho chín màn ở 1440, 390 và 768px;
  không phát hiện tràn ngang, lỗi JavaScript hay tài nguyên local bị lỗi.
  Kiểm tra font thực sự được load, mọi ảnh hiển thị tải thành công và hình học
  marker được ghi trong evidence JSON. Đã mở xem trực quan chín ảnh desktop cuối.
- `git diff --check`: PASS; Git chỉ cảnh báo chuyển CRLF/LF ở một số file.

Ảnh được tạo bằng chính Blade và Vite build của ứng dụng thông qua router QA chỉ
cho phép loopback. Fixture QA không trở thành route public, không kết nối DB và
không thay thế dữ liệu thật trong ứng dụng. Bộ ảnh desktop dùng kích thước chính
xác từng frame Figma. Kiểm tra 390/768px là smoke test responsive; không nghiệm thu
toàn bộ bộ frame M7–M15.

## Điểm còn khác Figma và phạm vi chưa kiểm tra

- D7/D11–D15: backend che mọi từ của tên và không trả đại lý sở hữu hồ sơ.
  Vì vậy không hiện tên mẫu `Nguyễn V** A*` hoặc dòng `Đăng ký qua Toyota An Sương`
  khi dữ liệu đó không được cấp. Không thêm dữ liệu giả để khớp hình.
- D7: form chỉ mở khi có receipt/quyền bổ sung hợp lệ; phiên không đủ quyền vẫn
  hiển thị hướng dẫn liên hệ. Timeline chỉ hiển thị bước Group hoàn tất khi có
  bằng chứng backend. Nhánh không đủ quyền đã có render/feature test nhưng không
  có ảnh đối chiếu Figma riêng vì Figma không có frame tương ứng.
- D8/D13: nội dung “trong vòng 48 giờ” có trong Figma nhưng bị loại khỏi hợp đồng
  nghiệp vụ và test hiện hành. Giữ nội dung đang hoạt động, chưa thêm SLA.
  Điều này cũng làm vị trí xuống dòng và căn giữa vùng chữ D8 khác mẫu.
- D9: route thể lệ độc lập không có dealer context nên header không có chip đại lý
  và link tra cứu riêng của đại lý như mẫu Figma. Chưa thêm đọc DB/session hoặc
  gán một đại lý mặc định vì sẽ thay đổi hành vi hiện tại.
- Footer lookup: hotline chỉ hiện nếu đã cấu hình; liên kết bảo mật có thể ở trạng
  thái unavailable khi chưa cấu hình URL. Không thêm hotline placeholder từ Figma.
- D15: số điện thoại và lý do dùng dữ liệu thật; nút gọi chỉ hiện khi có số hợp lệ.
  Không suy diễn Group đã hoàn tất hoặc dùng lý do mẫu thay dữ liệu backend.
- Không có màn desktop D7–D15 nào bị bỏ qua hoặc không chụp được. Tuy nhiên bộ ảnh
  là kiểm tra trình bày với fixture; **chưa kiểm tra toàn bộ luồng trên dữ liệu
  khách hàng thật từ đầu đến cuối trong browser**, chưa kiểm tra Safari/Firefox và
  chưa xác nhận độ giống từng pixel trên mọi hệ điều hành. Không lấy việc build
  hoặc test pass làm bằng chứng giao diện đã khớp tuyệt đối.

Để chụp lại: dùng Playwright Core 1.48.2 tương thích Node 18 tại
`.laravel-tools/visual-qa` (dependency QA, không thay package.json của ứng dụng),
chạy `npm run build`, sau đó
`php -S 127.0.0.1:8766 -t public docs/figma/qa/lookup-visual-router.php` và
`node docs/figma/qa/lookup-visual.mjs final`. Chrome có sẵn trong máy được dùng làm
browser. Server QA đã dừng sau khi chụp; trang so sánh HTML đọc ảnh local trực tiếp.
