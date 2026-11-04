# Đồng bộ Result Panel và Sidebox D7–D15

## Mục tiêu

Audit và sửa toàn bộ kết quả tra cứu: thông tin người đăng ký, progress, badge,
Sidebox và CTA theo Figma, giữ nguyên dữ liệu/quyền/nghiệp vụ Laravel.

## Trạng thái

DONE — hoàn tất triển khai, kiểm thử và kiểm tra metadata browser ngày 2026-11-04.
Mốc khởi tạo: IN PROGRESS ngày 2026-11-04.

## Hiện trạng trước khi chỉnh sửa

- Đã đọc AGENTS.md, lookup-sync, Phase 3A.2, 3A.4, 3B UI refinement và Phase 3C.
- Root Laravel được xác nhận; Git ở nhánh upgrade/laravel-13, HEAD 063e552,
  working tree sạch trước task. File tracking đề xuất chưa tồn tại.
- Đã đọc routes/controller/resolver, progress service, Blade, CSS/JS và tests.
- Figma MCP hoạt động; đã lấy design context đủ desktop D7–D15 và mobile
  M7–M15 trước khi sửa. D9 là trang thể lệ, không phải một lookup result state.

## Những sai lệch với Figma

- Nhánh trung tính `found` render hai đoạn giải thích dư và Sidebox cộng đồng
  chung với CTA đen, không phải D11/D12. Backend chưa có Group evidence nên không
  được đổi kind sang D12 chỉ để có UI mong muốn.
- D12 có markup/CSS riêng; nhánh khác có gap/header/font/mobile padding chưa
  đồng nhất. Một số màu hover bị base button ghi đè lên nút đen/nút viền.
- Mobile D10 còn icon88px thay vì56px; title18/26 thay vì17/22.
- Mobile D7 panel bổ sung padding20/radius20 thay vì16/radius18; helper và label
  đang dùng typography desktop. Mobile D14 còn nền panel thừa quanh CTA.
- Phase 3C thêm timeline M15 và dấu hỏi blocked theo yêu cầu trước. Yêu cầu mới
  ưu tiên Figma: M15 chỉ có hộp thông báo + CTA; D15 desktop dùng vòng tròn rỗng.

## Các file source code liên quan

`resources/views/dealers/registration/lookup.blade.php`, components
`lookup-state`, `lookup-next`, `lookup-waiting-group`, `lookup-avatar`, `link`;
`resources/css/dealer-registration.css`; progress/resolver services,
controllers lookup/supplement, routes và tests lookup.

### Danh sách file thay đổi trong task

- `resources/views/dealers/registration/lookup.blade.php`.
- `resources/views/components/dealer-registration/lookup-person.blade.php` (mới).
- `resources/views/components/dealer-registration/lookup-next.blade.php`.
- `resources/views/components/dealer-registration/lookup-state.blade.php`.
- `resources/views/components/dealer-registration/lookup-waiting-group.blade.php`.
- `resources/css/dealer-registration.css`.
- `tests/Feature/RegistrationLookupTest.php`.
- `tests/Feature/RegistrationLookupStatesTest.php`.
- `tests/Feature/RegistrationLookupResultPanelTest.php` (mới).
- `docs/figma/qa/lookup-visual-router.php` (chỉ fixture loopback).
- `docs/figma/qa/lookup-result-panel-visual.mjs` (mới).
- `docs/figma/qa/lookup-result-panel-design-evidence.json` (mới).
- File tracking này và thư mục evidence mới
  `docs/figma/qa/screenshots/result-panel-sidebox/`.

Build được tạo lại trong public/build (Git ignored). Không sửa controllers,
services nghiệp vụ/progress mapping, routes, API, schema, JavaScript ứng dụng,
dữ liệu thật hoặc tracking cũ.

## Những thay đổi đã thực hiện

Mốc tạo tracking: mới khảo sát, chưa sửa source. Kết quả sau triển khai:

- Tách header người đăng ký dùng chung cho cả D12 và các result còn lại:
  avatar/tên đã che/xe/biển số và một badge. Found dùng nhãn trung tính sẵn có
  “Chưa xác nhận”, không biến kind thành blocked hoặc not_requested.
- Bỏ hai đoạn giải thích found và Sidebox cộng đồng mặc định. Found là card
  một cột; progress đặt ngay sau header, không margin bù cho đoạn đã xóa.
- Tái sử dụng lookup-next cho cả draft/D12 thay vì lặp copy và CTA trong view.
  D12 dùng link component hiện có để không tạo link Group giả khi thiếu URL.
  Nếu response không có cặp phone/plate của caller, dùng hướng dẫn hiện có,
  không in định danh trống hoặc thay bằng dữ liệu mẫu.
- Đồng bộ header desktop22/28/700, mobile16/22/600; badge mobile12/16,
  gap6/padding5x10; khoảng cách header/progress28 desktop/20 mobile.
- Giữ desktop Sidebox420px, padding32px, gap14px, nền #fafafb và divider
  #ededf0; không thêm shadow hay đặt fixed height gây cắt nội dung dài.
- D7 mobile panel padding16/radius18, label15/20/600, helper12/16, input48px.
  Contact-only vẫn giữ quyền và hướng dẫn hiện hành; CTA liên hệ có viền trắng.
- D10 mobile icon56px, icon con24px căn giữa; title17/22/600, mô tả14/20.
- D14 mobile bỏ nền panel thừa; CTA đen đúng Figma. Hover nút đen/nút viền không
  còn bị rule đỏ chung ghi đè; trạng thái disabled/focus giữ design system cũ.
- D15 desktop dùng SVG vòng tròn Figma gốc thay dấu hỏi; M15 dùng message
  padding16/radius16, title15/20, mô tả13/19 và CTA ngoài message. Không lặp lý
  do trên màn hình, không chừa gap của timeline bị ẩn. CTA mobile cao52px.
- Fixture QA thêm tình huống thiếu phone/link Group, Dealer inactive và tên dài;
  không có route fixture hoặc mock data trong production.

## Những thành phần đã loại bỏ

Đã bỏ khỏi nhánh kết quả thông thường:

- “Đã tìm thấy đăng ký”.
- “Thông tin tra cứu khớp với một đăng ký. Kết quả này không xác nhận tư cách thành viên Facebook.”
- Sidebox “Cộng đồng Toyota Veloz & Hilux” và CTA đen mặc định của found.
- Margin riêng của found sau đoạn giải thích, nền CTA dư trên M14 và khoảng
  trắng timeline M15. Không thay bằng một đoạn giải thích chung khác.

## Mapping Sidebox theo từng trạng thái

| Screen/kind | Sidebox / CTA theo thiết kế |
| --- | --- |
| D7 supplement | Form Facebook và Gửi bổ sung đỏ khi có quyền; thiếu quyền giữ hướng dẫn liên hệ an toàn |
| D8 supplemented | Card xác nhận độc lập, hai CTA; không Sidebox trạng thái |
| D9 rules | Trang thể lệ, không Result Panel/progress/badge đăng ký |
| D10 not_found | Không Sidebox/progress/badge; CTA Đăng ký tham gia đỏ khi Dealer active |
| D11 draft | Còn một bước nữa; mô tả xe/Facebook; Tiếp tục đăng ký đỏ |
| D12 not_requested | Hướng dẫn xin vào Group với dữ liệu caller; Vào group Facebook đỏ |
| D13 requested | Không Sidebox hoặc CTA; chờ duyệt Group |
| D14 member | Chào mừng bạn vào nhóm; Mở group Facebook đen (đúng Figma) |
| D15 blocked | Chưa thể xác nhận; lý do thật/hỗ trợ; Gọi đại lý trắng có viền nếu số hợp lệ |
| found/unknown | Không Sidebox/CTA mặc định; không giả trạng thái D11/D12/member |

## Quyết định kỹ thuật quan trọng

- Không sửa resolver, API, schema hoặc query thêm dữ liệu người đăng ký.
- Giữ masking, Group evidence và điều kiện receipt/quyền bổ sung. Không điền
  Dealer sở hữu hồ sơ hoặc SLA48h bằng dữ liệu mẫu từ Figma.
- Figma D12 hiện ghi “Bước tiếp: vào group”; user yêu cầu cụ thể “Bước tiếp theo:
  vào group”. Dùng copy user yêu cầu và ghi rõ khác biệt nhỏ với frame.
- M15 khôi phục layout thông báo theo Figma, supersede phần mở rộng timeline
  mobile/dấu hỏi của Phase 3C; giữ semantic blocked và dữ liệu backend.
- Chưa thấy hover/focus/disabled variant riêng trong frame; giữ token/outline
  có sẵn, không tự tạo màu hover ngoài design tokens.
- Found không có frame Figma riêng; header/badge trung tính và progress chỉ
  phản ánh phần đã có bằng chứng, không tạo CTA yêu cầu Group hoặc blocker giả.
- Giao diện không sửa được tình trạng thiếu schema/evidence trên môi trường
  thật. Các kết luận Phase 3A.4 về schema local là lịch sử, không suy ra rằng
  database hiện tại vẫn thiếu bảng khi chưa kiểm tra.
- Harness Phase 3C mô tả yêu cầu timeline M15 trước đây. Bộ nghiệm thu cho yêu
  cầu hiện tại là lookup-result-panel-visual.mjs; không ghi đè ảnh/tracking Phase 3C.

## Kết quả kiểm thử

Các kết quả dưới đây được chạy trong task này:

- `php docs/figma/qa/lookup-progress-db-check.php`: PASS, actual database
  toyota_testing, đủ schema cần thiết; chỉ đọc. Không migration hoặc seeder.
- `php vendor/bin/phpunit --do-not-cache-result tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/CampaignRulesTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Unit/RegistrationLookupProgressTest.php`:
  **79 tests / 1.079 assertions PASS**. HTTP lookup/Admin Group/supplement vẫn
  dùng guard database testing và transaction fixtures. Test panel mới dùng
  model chưa lưu và xác nhận render không query thêm dữ liệu.
- Kiểm tra mới: đủ badge/Sidebox/CTA theo kind; found không còn copy/CTA dư;
  D13 không Sidebox; D7 không được mở form khi thiếu quyền; thiếu Dealer phone
  không có tel giả; Group URL thiếu tạo disabled control không có href giả.
- `php docs/figma/qa/render-check.php`: **63 checks PASS** cho Blade, privacy,
  CSRF, IDs, registration và rules.
- Lookup/supplement frontend harness với
  `node --experimental-specifier-resolution=node ...`: PASS, không thay JS runtime.
- Pint các PHP thay đổi và Node syntax check harness mới: PASS.
- `npm run build`: PASS, Vite6.4.3/62 modules; Dealer CSS
  `dealer-registration-Bys1FyS5.css`; JS `dealer-registration-D6sP_pS-.js` giữ nguyên.
- `node docs/figma/qa/lookup-result-panel-visual.mjs before`: 20 ảnh baseline mới.
- `node docs/figma/qa/lookup-result-panel-visual.mjs after`: **95 ảnh PASS**,
  19 cases × 320/390/768/1024/1440. Bao gồm đủ D7–D15, found và các nhánh
  contact-only/unknown/long/missing-phone/missing-Group/inactive/long-name.
- Browser kiểm tra: badge không đè tên, progress đúng state/gap, Sidebox/CTA
  đúng số lượng/label/màu/hover, disabled không href, mobileCTA52px, M10 icon
  căn giữa, M15 message đúng gap20px. Không overflow, lỗi JS, tài nguyên lỗi;
  font Inter và ảnh local tải được. Chặn CSS Google Fonts để xác minh font local.
- Đã mở xem ảnh ứng dụng và ảnh Figma trực tiếp để đối chiếu; ảnh trước/sau
  và 17 ảnh Figma tải mới được lưu riêng, không ghi đè evidence lịch sử.
- Kiểm tra tổng hợp JSON cuối: đủ95 records, 0 overflow, 0 JS/request/font/image
  failures. Server QA đã dừng sau khi kiểm chứng.
- Browser fixture chỉ ở server loopback, DB disabled. Luồng backend thật được
  kiểm chứng riêng qua HTTP feature tests trên toyota_testing, không phải browser
  chạy dữ liệu khách thật.

### Các điểm phát hiện trong vòng kiểm tra

Đã sửa CTA contact-only còn xám, M10 icon lệch trái, M15 chừa gap của header
khi không còn timeline và nút viền mobile cao hơn52px. Harness phân biệt text
hiển thị với suffix chỉ cho screen reader ở disabled link. Build/chụp lại bộ cuối.

Kiểm tra tổng hợp metadata phát hiện request SVG responsive bị ghi nhận hủy
trong lúc đóng browser context dù ca render đã PASS. Harness cuối ẩn scrollbar
native trong ảnh để giữ viewport ổn định, chờ ảnh sau capture và sao chép evidence
trước context cleanup; không đổi CSS ứng dụng hoặc bỏ qua lỗi request lúc trang hoạt động.

## Những lỗi còn tồn tại

Không còn lỗi phát hiện trong phạm vi Result Panel/Sidebox và các ca đã kiểm
chứng. Không tuyên bố khớp tuyệt đối toàn trang hoặc mọi trình duyệt:

- D7 contact-only không có frame riêng; không thêm form khi không có receipt.
  D7/D15 chỉ có bước Group xanh khi backend có evidence.
- D8/D13 giữ nội dung không SLA48h theo nghiệp vụ; khác câu mẫu trong Figma.
- D12 dùng “Bước tiếp theo” theo yêu cầu user, dài hơn copy “Bước tiếp” trong frame.
- Found là fallback trung tính, không có frame thiết kế riêng. Không giả D12,
  blocker hoặc membership để loại bỏ phần giải thích cũ.
- Tên vẫn che mọi từ; backend không cấp Dealer sở hữu hồ sơ nên không tự thêm
  dòng đại lý mẫu. Phone/link thiếu thì CTA không khả dụng, không dùng dữ liệu giả.
- Header/footer và form tra cứu ngoài Result Panel vẫn có khác biệt lịch sử
  (ví dụ vị trí toàn khối trên mobile); task này không thiết kế lại các khu vực đó.
- Chưa chạy browser trên khách hàng/production, Safari/Firefox hoặc screen reader
  thật; chưa xác nhận migration/activation trên database local/production.

## Task hoặc bước tiếp theo

Commit/push các file của task theo ủy quyền trong phiên. Việc deploy và xác nhận
Group/schema thực tế là bước riêng, không được suy ra từ fixture hoặc giao diện.

## Bảng nghiệm thu từng màn hình

| Screen | Result Panel | Progress | Badge | Sidebox | CTA | Đối chiếu Figma |
| --- | --- | --- | --- | --- | --- | --- |
| D7 | Header dùng chung, giữ masking | Warning, Group theo evidence | Cam | Form có quyền / liên hệ | Đỏ / viền | Đã so desktop/mobile; ngoại lệ quyền |
| D8 | Card xác nhận độc lập | Không có | Không có | Không có | Đỏ + viền | Đã so; giữ khác biệt SLA |
| D10 | Empty card/icon căn giữa | Không có | Không có | Không có | Đăng ký đỏ | Đã so desktop/mobile |
| D11 | Hai cột, header/progress đúng gap | Current/pending/pending | Xám | Còn một bước nữa | Tiếp tục đỏ | Đã so desktop/mobile |
| D12 | Hai cột desktop, responsive | Completed/current/pending | Xanh dương | Bước tiếp theo: vào group | Vào group đỏ | Đã so; copy user dài hơn frame |
| D13 | Một cột | Completed/completed/current | Xanh dương | Không có | Không có | Đã so; giữ khác biệt SLA |
| D14 | Hai cột desktop | Ba bước completed | Xanh lá | Chào mừng; mobile chỉ CTA | Mở group đen | Đã so desktop/mobile |
| D15 | Desktop result; mobile message | Blocked desktop; M15 không timeline | Xám | Lý do thật và hỗ trợ | Trắng có viền | Đã so; không giả phone/evidence |

D9 đã đối chiếu và regression: trang thể lệ, không có result/progress/badge đăng ký.

## Xem evidence và chạy lại

- [Gallery so sánh](qa/screenshots/result-panel-sidebox/index.html).
- [Số đo và kết quả browser](qa/screenshots/result-panel-sidebox/after/evidence.json).
- [Thông số Figma](qa/lookup-result-panel-design-evidence.json).

Chạy build, sau đó server QA
`php -S 127.0.0.1:8766 -t public docs/figma/qa/lookup-visual-router.php` và
`node docs/figma/qa/lookup-result-panel-visual.mjs after`.
