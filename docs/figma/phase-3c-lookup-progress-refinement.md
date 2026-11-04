# Phase 3C — Tối ưu progress tra cứu D7–D15

## Mục tiêu

Chuẩn hóa progress ba bước dùng chung, khớp Figma và dữ liệu Laravel đã xác minh;
kiểm tra đủ D7–D15, desktop/mobile và luồng tra cứu hiện hữu.

## Trạng thái

DONE — hoàn tất kiểm chứng ngày 2026-11-04.
Mốc khởi tạo: IN PROGRESS ngày 2026-11-03.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS.md, context recovery và tracking Phase 3A.2, 3A.4, lookup-sync,
Phase 3B refinement. Đã kiểm tra routes, controllers lookup/supplement,
RegistrationLookup/StateResolver/SubmissionVerification, Blade, CSS/JS và tests.
File tracking này chưa tồn tại trước task. Đã kiểm tra Git cho các file liên
quan trước khi sửa; không gán thay đổi hay lịch sử các phase trước cho task mới.

## Nguyên nhân lỗi progress

- View `found` cố ý không vẽ progress vì thiếu bằng chứng Group; không thể đổi
  sang D12 chỉ vì tìm thấy hồ sơ. Resolver vẫn trả `found` khi thiếu bảng/row,
  bằng chứng profile không còn hợp lệ hoặc moderation không cho phép claim.
- D12 có markup/CSS progress riêng, draft và các trạng thái khác dùng timeline
  khác; mapping và marker phân tán, thiếu semantic state `blocked`.
- CSS mobile chủ động `display:none` progress D15 theo frame M15 cũ, trái yêu
  cầu mới phải hiển thị đầy đủ tiến trình trên mobile.
- Chưa kiểm tra schema local trong task này; không khẳng định database hiện tại
  thiếu migration chỉ dựa vào báo cáo cũ.

## Các file source code liên quan

`app/Services/RegistrationLookupStateResolver.php`, `SubmissionVerification.php`,
`RegistrationLookup.php`; controllers lookup/supplement; `routes/web.php`;
`resources/views/dealers/registration/lookup.blade.php`; components
`lookup-state.blade.php`, `lookup-waiting-group.blade.php`; stylesheet
`resources/css/dealer-registration.css`; JS validation và tests lookup.

Các file thực sự sửa/tạo trong task:

- `app/Services/RegistrationLookupProgress.php` (mới, presentation mapping).
- `resources/views/components/dealer-registration/lookup-state.blade.php`.
- `resources/views/components/dealer-registration/lookup-waiting-group.blade.php`.
- `resources/views/components/dealer-registration/lookup-next.blade.php`.
- `resources/views/dealers/registration/lookup.blade.php`.
- `resources/css/dealer-registration.css`.
- `resources/js/dealer-registration.js` (sửa blur/submit của lookup/supplement).
- `tests/Feature/RegistrationLookupStatesTest.php`.
- `tests/Feature/RegistrationLookupTest.php`.
- `tests/Unit/RegistrationLookupProgressTest.php` (mới).
- `docs/figma/qa/lookup-progress-db-check.php` (mới).
- `docs/figma/qa/lookup-progress-visual.mjs` (mới).
- `docs/figma/qa/phase-3c-progress-figma-evidence.json` (mới).
- `docs/figma/qa/lookup-visual-router.php`.
- `docs/figma/qa/lookup-validation-check.mjs`.
- `docs/figma/qa/supplement-validation-check.mjs`.
- File tracking này; ảnh/evidence/gallery mới trong
  `docs/figma/qa/screenshots/phase-3c-progress/` và baseline mới tại
  `docs/figma/qa/screenshots/lookup-sync/phase-3c-before/`.

Build Vite được tạo lại trong `public/build` (Git ignored). Không sửa controller,
resolver, workflow service, routes, API, schema, dữ liệu thật hoặc tracking cũ.

## Những thay đổi đã thực hiện

Mốc khởi tạo: chưa sửa source. Kết quả triển khai ngày 2026-11-03:

- Một mapping thuần chỉ đọc DTO cung cấp ba step và năm trạng thái
  completed/current/pending/warning/blocked. Kind `found` vẫn trung tính,
  có progress chỉ xác nhận bước nhận đăng ký, không suy ra Group/member.
- Một Blade component cho D7/D11/D12/D13/D14/D15 và found; bỏ ba bản markup
  timeline tách biệt. D8/D9/D10 tiếp tục không có progress theo thiết kế.
- Marker/rail thành phần DOM thật, không phụ thuộc pseudo-element timeline
  generic. Dùng lại SVG Figma đã có; marker blocked dùng vòng tròn dấu hỏi trung
  tính để phân biệt pending, không gợi ý hồ sơ bị reject.
- Desktop ba cột đều, rail đúng tâm icon; mobile dọc, mô tả dài xuống dòng.
  Badge được phép xuống hàng ở màn hẹp để tên không bị ép thành từng chữ.
  D12 tablet 768–900 chuyển panel hành động xuống dưới, giữ progress ngang đủ rộng.
- Mỗi step có tên/mô tả/trạng thái đọc được bằng screen reader và chỉ báo icon;
  aria-current theo bước đang xử lý/cảnh báo/bị chặn. Những mô tả mà Figma không
  hiển thị được giữ ở dạng chỉ dành cho screen reader để không thêm chữ vào frame.
- D15 mobile hiện đầy đủ progress; lý do chỉ hiển thị một lần trong progress,
  bỏ bản lý do mobile lặp ở panel hành động.
- Browser phát hiện lỗi focusout cũ: khi ô trống mất focus vào nút Gửi, lỗi chèn
  trước mouseup làm nút dịch chuyển và mất click submit. Lookup/supplement nay để
  submit handler xử lý trường hợp focus chuyển vào chính nút Gửi; blur thông
  thường vẫn validate. Giữ nguyên server validation, ownership, POST và chống gửi lặp.
- QA router buộc ASSET_URL về loopback, thêm fixture found/unknown/contact/long;
  không kết nối DB. Harness chặn stylesheet Google Fonts bằng CSS rỗng để kiểm
  chứng font local thật, không dùng font mạng hoặc mock trong ứng dụng production.

## Mapping trạng thái

Mapping đã triển khai, không thay resolver/authorization:

| Kết quả | Đăng ký | Group | Thành viên |
| --- | --- | --- | --- |
| D7 supplement | completed | completed chỉ khi group_confirmed=true, còn lại pending | warning |
| D8 supplemented | Không có progress trong Figma; xác nhận đã nhận bổ sung không chứng minh Group/member | — | — |
| D9 rules | Trang thể lệ, không có progress đăng ký | — | — |
| D10 not_found | Không có progress | — | — |
| D11 draft | current, chưa submit final | pending | pending |
| D12 not_requested | completed | current, cần xin vào group | pending |
| D13 requested | completed | completed = đã xác nhận gửi yêu cầu, không phải đã là thành viên | current, chờ duyệt Group |
| D14 member | completed | completed | completed |
| D15 blocked | completed | completed chỉ khi group_confirmed=true, còn lại pending | blocked |
| found/unknown | completed, đã tìm thấy đăng ký | pending, chưa có xác nhận | pending, chưa có xác nhận |

## Quyết định kỹ thuật quan trọng

- Figma MCP đang hoạt động. Đã đọc trực tiếp desktop D7/D8/D9/D10/D11/D12/
  D13/D14/D15 và mobile M7/M8/M10/M11/M12/M13/M14/M15, file
  `yKmWQI6BYJ6dponEnidlsq`.
- Desktop node 28px, gap node/rail 8px, rail 2px, ba cột bằng nhau;
  text gap 12px, title Inter600 16/22, description Inter400 14/20, inset20.
  Mobile node24px, text gap14px, rail2px, text padding-top2/bottom22,
  title15/20, description13/18, gap3.
- D8/M8, D9 và D10/M10 không có progress; không tự tạo timeline cho các trang này.
- M15 không có timeline trong Figma hiện hành; yêu cầu mới ưu tiên thông tin
  tiến trình đầy đủ. Sẽ ghi rõ khác biệt khi thêm timeline mobile.
- Không thêm SLA 48 giờ; không suy ra Group từ moderation hay click CTA.
- Presentation mapping chỉ đọc DTO, không query thêm PII, không sửa schema,
  API hoặc nghiệp vụ ghi dữ liệu. Unknown phải diễn đạt thiếu bằng chứng rõ ràng.
- Không đổi requirement schema của workflow hoặc tự áp dụng migration local.
  Có row không có nghĩa có bằng chứng hợp lệ; resolver vẫn kiểm tra audit/profile.
- Kết luận "chưa có browser screenshots" của các tracking đầu kỳ đã được thay
  thế bởi lookup-sync và evidence mới của task này. Không xóa kết luận lịch sử.
- Không dùng lại assertion min-width2ch đã lỗi thời của rules-css-check để thay
  thiết kế thể lệ; task này không sửa rule số mục D9 hoặc harness cũ đó.

## Kết quả kiểm thử

### Backend và component

- `php docs/figma/qa/lookup-progress-db-check.php`: PASS. Kiểm tra cấu hình
  testing/mysql và SELECT DATABASE() = toyota_testing trước fixture; đủ bảng
  lookup/verification. Script chỉ đọc, không migrate/seed.
- `php vendor/bin/phpunit --do-not-cache-result tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/CampaignRulesTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Unit/RegistrationLookupProgressTest.php`:
  **66 tests / 937 assertions PASS**, DatabaseTransactions và guard live database.
  Bao gồm Admin xác nhận → HTTP lookup → Blade, Group unknown/requested/member,
  D7/D15 có/không evidence, quyền D8, D10 không progress, draft D11, stale profile,
  thiếu bảng, không đổi moderation/điểm/audit trong lookup, regression D9 và D04.
- Unit mapping chạy độc lập không bootstrap Laravel cũng PASS: 12 tests /
  190 assertions (là một phần của 66 tests trên, không cộng hai lần).
- `php docs/figma/qa/render-check.php`: 63 render/route/privacy/Blade checks PASS.
- Pint trên PHP thay đổi, PHP lint QA và Node syntax check JS: PASS.

### Frontend và browser

- Bốn harness Step One, Step Two, lookup và supplement chạy bằng
  `node --experimental-specifier-resolution=node ...`: PASS. Hai harness sau
  thêm regression submit-target blur không chèn lỗi làm nút di chuyển; blur
  thường, giữ input, focus/ARIA, loading và chống double-submit vẫn PASS.
- `npm run build`: PASS, Vite6.4.3 /62 modules. Bundle bàn giao:
  `dealer-registration-CUopeeLm.css`, `dealer-registration-D6sP_pS-.js`.
- `node docs/figma/qa/lookup-progress-visual.mjs`: PASS **14 cases × 5 widths =
  70 ảnh trạng thái**, cộng **2 ảnh validation/focus**. Width 320/390/768/1024/
  1440; đủ D7–D15, found và variants unknown/contact/long.
- Kiểm tra tự động từng step: state, một aria-current khi cần, đủ ba mô tả,
  node28/24px, text gap12px desktop/14px mobile, rail2px đúng chiều dài/màu,
  chia đều cột, không chồng lấn, không overflow, font Inter và mọi ảnh tải được.
  **0 lỗi JS, 0 request lỗi, 0 overflow trong 70 ca**. Submit chuột và Enter
  nhận lỗi inline/focus đúng, không điều hướng giả sang D8.
- Đã chụp baseline 27 ảnh trước sửa ở thư mục mới; không ghi đè baseline cũ.
- Đã mở xem trực quan các ảnh desktop D7–D15 và các ảnh mobile/edge cases;
  đối chiếu D12/D13/D14 với design context và ảnh Figma (M12, D13, D14 tải mới).
  D12/D14 desktop progress rộng700px, D13 rộng1120px; mobile390 rộng310px.
  Title16/22 và mô tả14/20 desktop; mobile15/20 và13/18 đúng context.

### Các lỗi gặp trong quá trình kiểm tra và đã xử lý

- Lần browser đầu font thất bại do ASSET_URL QA trỏ toyota.test; đã buộc tài
  nguyên QA về loopback và kiểm tra font local. Không sửa cấu hình production.
- Lần mở rộng kiểm tra chuột phát hiện lỗi focusout làm mất click; đã tái hiện
  bằng chuỗi mousedown/focusout/mouseup, sửa JS như trên và kiểm tra lại PASS.
- Xem ảnh320/768 phát hiện tên bị ép hẹp/D12 tablet thiếu chỗ; đã cho badge
  wrap và action tablet xuống hàng, build/chụp lại bộ ảnh cuối.
- Git diff --check trên source thay đổi đã PASS; chỉ có cảnh báo CRLF/LF.

## Những lỗi còn tồn tại

Không còn lỗi được phát hiện trong phạm vi mapping/progress và các ca QA đã
kiểm tra. Các giới hạn/khác biệt có chủ đích:

- D15 mobile có timeline theo yêu cầu mới, khác M15; dấu hỏi blocked là phần
  mở rộng phân biệt trạng thái vì Figma desktop dùng vòng tròn chờ giống pending.
- Found/unknown có progress tối thiểu mới, không có frame Figma riêng. Không
  đánh dấu Group/member hoàn tất khi backend chưa xác nhận.
- D7/D15 thiếu evidence khác mẫu Figma có hai bước xanh; đó là bảo toàn nghiệp vụ.
  D8/D13 không SLA48h. Tên/Dealer/hotline vẫn theo hợp đồng privacy/cấu hình thật.
- Chưa kiểm tra dữ liệu khách hàng/production trong browser, Safari/Firefox,
  screen reader thật hoặc mọi tổ hợp thiết bị. Ảnh browser là fixture cách ly;
  luồng Laravel được kiểm chứng riêng bằng HTTP feature tests trên toyota_testing.
- Chưa xác nhận schema/activation trên database local thật. Thiếu evidence vẫn
  không thể cho trạng thái D12/D13/D14 dù progress nay có hiển thị phần đã biết.
- Không tuyên bố toàn trang/từng pixel khớp Figma: header/footer và một số phần
  form ngoài progress vẫn có khác biệt đã nêu trong lookup-sync.

## Task hoặc bước tiếp theo

Đã hoàn tất kiểm chứng trong phạm vi task. Người dùng đã yêu cầu commit/push
lên nhánh hiện tại; kết quả giao nhận và commit được báo trong phản hồi kết thúc.
Việc triển khai lên môi trường chạy thật, xác nhận dữ liệu Group và activation
migration là công việc riêng. Không thực hiện deploy trong task này.

## Xem bằng chứng và chạy lại

- [Gallery desktop/mobile](qa/screenshots/phase-3c-progress/index.html).
- [Số đo browser](qa/screenshots/phase-3c-progress/evidence.json).
- [Thông số Figma lấy mới](qa/phase-3c-progress-figma-evidence.json).

Build bằng `npm run build`, chạy server QA loopback:
`php -S 127.0.0.1:8766 -t public docs/figma/qa/lookup-visual-router.php`, rồi
`node docs/figma/qa/lookup-progress-visual.mjs`. Playwright Core được cài trong
`.laravel-tools/visual-qa`; script dùng Chrome có sẵn. Không thêm route fixture
vào ứng dụng public hoặc thay dữ liệu thật.
