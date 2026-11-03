# Phase 3A — Registration Lookup

Ngày 03/11/2026. Đã triển khai public lookup read-only, không migration/production
data changes. **Chưa hoàn thành toàn bộ business states D07–D15**: các nhánh thiếu
bằng chứng Facebook/bổ sung không được giả lập hoặc gán nhãn từ pending/approved.

## Figma đã đọc

File `yKmWQI6BYJ6dponEnidlsq`. Session MCP
`d1009b9e-2ca5-42b1-8410-d782b266deaf`. Đã đọc skill figma-design-to-code và figma-use
từ MCP resources. Đọc file metadata/page listing, page khách desktop `207:332`,
mobile `57:801`, get_design_context và get_screenshot cho đủ 18 frames sau.

| Frame thực tế | Desktop node | Mobile node | Nội dung / CTA |
| --- | --- | --- | --- |
| D07 / M07 — Cần bổ sung | 213:533 | 57:733 | Tên che bớt, xe/plate, tiến trình; lý do link Facebook không mở; cập nhật link/Gửi bổ sung |
| D08 / M08 — Đã gửi bổ sung | 212:647 | 57:941 | Success illustration, xem lại trạng thái/về cộng đồng; có cam kết 48h trong thiết kế |
| D09 / M09 — Thể lệ đầy đủ | 213:1447 | 57:963 | Desktop sidebar 8 sections; mobile content dọc; chỉ khảo sát, không triển khai |
| D10 / M10 — Không tìm thấy | 213:636 | 204:366 | Kiểm tra lại cặp thông tin, CTA đăng ký tham gia |
| D11 / M11 — Chưa hoàn tất | 213:702 | 204:446 | Bước đầu current đỏ, hai bước sau xám; tiếp tục Step Two |
| D12 / M12 — Chờ vào group | 213:797 | 204:541 | Bước đăng ký completed, group current; CTA vào group, nhắc phone/plate |
| D13 / M13 — Chờ duyệt | 213:894 | 204:638 | Đã gửi yêu cầu group, đang chờ duyệt; thiết kế có 48h |
| D14 / M14 — Đã là thành viên | 213:989 | 204:736 | Ba bước completed xanh; CTA mở group |
| D15 / M15 — Chưa thể xác nhận | 213:1086 | 204:833 | Lý do plate đã dùng đăng ký khác; gọi đại lý |

**Khác prompt:** D13 desktop tên chính xác “Tra cứu: chờ duyệt”, mobile frame tên
“Tra cứu: chờ xác minh” nhưng badge là “Chờ duyệt”; không phải “Đã duyệt”. Có D08
và đủ M07–M15, không cần đoán frame thiếu.

18 ảnh tham chiếu đã lưu tại [screenshots/phase-3a](qa/screenshots/phase-3a/README.md),
ví dụ [D10](qa/screenshots/phase-3a/figma-D10.png),
[M10](qa/screenshots/phase-3a/figma-M10.png),
[D11](qa/screenshots/phase-3a/figma-D11.png).
[Evidence JSON](qa/phase-3a-figma-evidence.json) ghi IDs, fonts, colors, PNG dimensions
và SHA-256. PNG lấy từ image blocks trong get_design_context; get_screenshot cũng
được gọi nhưng URL download không truy cập được qua shell. Đây là **ảnh Figma,
không phải screenshots ứng dụng hoặc bằng chứng browser QA**; không đưa vào public
làm implementation assets và không lưu temporary asset URLs vào runtime.

## Phân tích layout và design system

- Desktop 1440: header 72px, container 1200px, content bắt đầu sau 48px; heading
  Inter ExtraBold 40/46, tracking -0.8px, subtitle 17/26. Footer tái sử dụng layout.
  Form trắng radius28, padding28x32, gap20, hai field linh hoạt + CTA dark 56px.
- Mobile 390: topbar back/Tra cứu đăng ký; content margin20px, heading26/32;
  form padding20, inputs48px, CTA52px full-width. Result xếp dọc, timeline dọc,
  action bên dưới; desktop D07/D11/D12/D14/D15 có panel action bên phải.
- Inter dùng toàn bộ lookup, weights400/500/600/700/800; kế thừa existing family
  fallback Arial/sans-serif. Montserrat chỉ giữ ở các trang/vị trí đã có, không thêm
  vào lookup. Fonts còn tải Google Fonts như layout hiện hữu, chưa self-host.
- Tokens chính: page #f5f5f7, card white, input #f2f2f5, text #111/#5e5e66,
  Toyota red #eb0a1e, dark CTA #2f2e2c. Status thiết kế có amber bổ sung, blue chờ,
  green completed/member, grey upcoming/unconfirmed; không dùng màu để suy ra status.
- Tái sử dụng layout, field, link, timeline, button, tokens và logo/header search
  assets hiện có. CSS mới chỉ dùng selectors lookup. Không tạo design system mới.
- Lookup error states không có frame validation riêng: dùng pattern inline đã chốt
  cho Dealer, không summary/count; messages cụ thể phone/plate, nhẹ border đỏ/nền hồng.
  Loading/disabled/focus/aria bổ sung để form hoạt động, không tạo domain status mới.

Giới hạn asset: icon search trang trí của empty-result D10 và icon trắng trên CTA
desktop chưa tải được đúng SVG variants. Curl bị lỗi mạng; read-only export qua
use_figma bị auto-approval từ chối: “requires approval, but approval policy is never”.
Không redraw/substitute SVG từ screenshot hoặc dùng URL Figma tạm. Hai icon đó chưa
được đưa vào view; đây là **blocker visual fidelity**, không được đánh dấu Figma UI
hoàn thiện. Logo và icon header tái sử dụng assets đã có. Runtime browser geometry/
crop/font-loading/overflow/keyboard tại 390/768/1440 vẫn NOT VERIFIED; không có browser
connector và Chrome headless của Phase 2C.4C chưa hoạt động. Chưa tuyên bố pixel-perfect.

## Source và khoảng trống nghiệp vụ

Đã đối chiếu routes, legacy/final/draft controllers, CustomerSubmission,
SubmissionIntake/normalizers, approved identity/Facebook verification, Dealer profile/
QR, Admin approval/reviews, D01–D06 Blade/CSS/JS/tests và báo cáo Phase 2B/2C/2C.4.
Các báo cáo cũ nói chưa final/draft hoặc lookup “unavailable” là snapshot trước phase
này. Không thay rules duyệt/điểm/duplicate/canonical slug hoặc cập nhật dữ liệu cũ.

Database chỉ có pending/approved/rejected, Facebook URL/normalized URL và generated
approved_facebook_identity; generated column chỉ là identity khi status=approved.
Admin UI yêu cầu kiểm tra Facebook/member trước duyệt, nhưng không có event/field
độc lập xác nhận đã xin group/được group chấp nhận hay yêu cầu bổ sung đang mở.
Không dùng lời nhắc UI, link Facebook, click CTA, reviewer hoặc generated column
để suy diễn thành bằng chứng membership. Không gọi Facebook API hoặc thêm OTP giả.

| Bằng chứng thực tế | UI đã triển khai | Không được suy diễn |
| --- | --- | --- |
| Form GET chưa submit | Form trống, chưa có kết quả | Không hiển thị lỗi ban đầu |
| Không khớp cả cặp phone+plate | D10/M10 | Không cho biết riêng phone/plate tồn tại |
| Nhiều rows cùng cặp hoặc phone aliases bất nhất | Cùng thông báo D10, không chọn winner | Không tiết lộ collision/owner khác qua D15 |
| Một submission khớp duy nhất, bất kỳ pending/approved/rejected | Kết quả trung tính “Đã tìm thấy đăng ký”, tên che bớt + vehicle allowlist | Không gán D12/D13/D14/D15 chỉ từ DB status |
| Đúng session/Dealer, draft còn hạn, Step One hợp lệ và Step Two đã lưu plate khớp | D11/M11, tiếp tục Step Two thật | Không tìm draft session khác hoặc chỉ từ phone |
| Draft mới chỉ Step One | D10 vì draft chưa có plate để kiểm chứng cặp | Không bịa plate, không public lookup mọi draft |
| Draft hết hạn/khác session/khác Dealer/inactive | Không có D11 | Không kéo dài TTL để tra cứu |
| Request bổ sung có lý do đã xác minh | D07: BLOCKED, chưa có dữ liệu/quy trình |
| Đã gửi bổ sung qua customer-authorized flow | D08: BLOCKED, chưa có dữ liệu/quy trình |
| Chưa xin group/đã xin group/được group chấp nhận | D12/D13/D14: BLOCKED, chưa có bằng chứng riêng |
| Từ chối vì đúng reason code plate collision | D15: BLOCKED; rejected/rejection_reason tùy ý không chứng minh reason này |

Kết quả trung tính là response về **existence**, không phải trạng thái mới trong DB
hoặc mapping Facebook. Đây là phương án tối thiểu có căn cứ khi chưa thể triển khai
các nhãn Figma an toàn; đã nêu lựa chọn trong câu hỏi clarification và tiếp tục theo
hướng này khi người dùng yêu cầu tiếp tục. Không coi đó là phê duyệt business rules mới.

D11 có phạm vi hẹp: Step Two values chỉ được server lưu sau một lần submit chưa hợp
lệ; lookup không thêm plate vào Step One hoặc mở quyền đọc cache theo phone. Lookup
chỉ đọc draft hiện có, không extend TTL/ghi PII session mới. Read của service hiện
hữu có thể dọn cache draft đã hết hạn/inactive, không sửa CustomerSubmission.

## Security design

Figma form yêu cầu đúng **phone + license_plate**. Cả hai bắt buộc, chuẩn hóa bằng
RegistrationPhone và SubmissionIntake::plate (uppercase, bỏ spaces/dot/hyphen).
Phone hợp lệ theo pattern đăng ký hiện có; plate canonical 3–20 ASCII alphanumeric,
editable input tối đa30 chars. Không coi chỉ phone là quyền xem hồ sơ.

Cặp thông tin là knowledge check giới hạn, **không phải strong authentication**.
Do đó chỉ trả DTO gồm kind, masked_name (mỗi từ giữ một ký tự đầu), vehicle từ
allowlist Veloz/Hilux/Fortuner/FJ, fallback Toyota. Không trả model, ID, DB status,
phone/plate đọc từ DB, Facebook URL/identity, address, evidence, Admin/Sales, review
notes/reason/timestamps. Plate và phone trong input chỉ là bounded text người dùng
vừa POST, không autofill từ hồ sơ. Header Dealer là context trang đang mở, không
thay attribution của submission; lookup submissions toàn hệ thống, kể cả Sales.

- Query Builder chỉ SELECT các cột cần match/mask; canonical scan lazyById(500)
  hỗ trợ historical formats. Phải có duy nhất một match, không first/latest-wins.
  Raw/normalized aliases khác nhau thực sự fail closed. Rejected vẫn searchable
  như existence nhưng không được chọn vượt qua ambiguity với row khác.
- Throttle IP HMAC: 5/phút, 20/giờ; phone canonical HMAC: 3/phút, 10/giờ xuyên
  Dealer/IP. GET page 30/phút/IP HMAC. Lookup identity key không gồm raw phone/plate,
  không thể né phone quota bằng đổi formatting/plate/Dealer. Malformed inputs bounded
  trước HMAC; throttle chạy trước service scan. Đổi IP vẫn chịu identity quota.
- POST có CSRF/honeypot, allowlist fields, unknown/system fields bị từ chối bằng
  lỗi generic không echo tên field do client tự đặt. Không SMS OTP/provider mới.
- Validation thất bại render HTML422 trực tiếp và giữ text bounded; không flash
  old input/errors vào session dùng chung, không redirect chứa PII, không localStorage.
  Hợp lệ render POST200 read-only; refresh/retry không tạo/sửa submissions/reviews/
  receipts. Không dùng GET params cho form; GET không reflect query PII.
- Mọi response lookup 200/422/419/429/404/500 có no-store/private qua controller/
  handler, noindex/nofollow; no-referrer tránh chuyển context tra cứu sang Group.
  Handler log exception type/generic database metadata, không request inputs hoặc
  SQL bindings. Không tuyên bố kiểm soát được access/error/APM logs của hạ tầng;
  operator vẫn phải cấu hình không capture request body/query PII.
- Missing pair, wrong pair và ambiguity đều cùng not-found copy/status. Matched
  response chỉ dành cho caller biết đủ cặp, không phải API tải profile. Tương lai
  nếu cần full PII hoặc sửa Facebook phải có owner receipt/verification mạnh hơn
  được duyệt; cặp thông tin này không cấp quyền mutate.
- Dealer inactive vẫn được tra cứu historical record read-only; không có CTA đăng
  ký mới và không đọc draft inactive. Intake active checks không đổi.

Performance còn O(N); không thêm index/unique constraints hoặc chuẩn hóa lịch sử.
Chưa load-test production hoặc chứng minh timing resistance tuyệt đối; rate limit
không loại bỏ mọi rủi ro người khác biết cả phone/plate. Cần operator privacy review
trước public release và strong verification cho dữ liệu nhạy cảm/luồng ghi.

## Routes và frontend integration

```text
GET  /{slug}/registrations/lookup  dealers.lookup.show
POST /{slug}/registrations/lookup  dealers.lookup.store
```

Routes nằm trước slug catch-all; không thêm global reserved slug, không đổi Dealer
canonical URLs/QR, /register hoặc Admin/Sales routes. GET không cần authorization
account; POST chỉ cho DTO giới hạn sau pair check, không cấp customer edit token.

Bật links tra cứu tại header D01–D06, Step One (kể cả D05), D04. Layout chung giữ
font/tokens; lookup prop chỉ đổi topbar/title/body class của lookup. D01/D02 form
handlers cũ không đổi. New JS validation chỉ gắn data-registration-lookup, focus
first error/result heading, aria state, touched-field correction, loading/disabled,
double-click guard và pageshow reset. Không dùng DOM preview làm backend lookup.
Không thêm public status parameter/route để ép hiển thị các states chưa có dữ liệu.
D09 links vẫn dùng terms_url cấu hình hiện có, không tạo terms page/copy campaign mới.

## Tests/build

Preflight APP_ENV=testing/MySQL, DATABASE_URL rỗng, SELECT DATABASE() thực tế
toyota_testing trước DB tests; new test class cũng guard trước begin transaction.
Không migrations, migrate:fresh, RefreshDatabase, seed/cleanup database thật.
Read-only check sau tests: submissions, receipts, moderation_reviews, users và dealers
trên toyota_testing đều 0 rows; fixtures đã rollback/cleanup.

| Check | Kết quả |
| --- | --- |
| RegistrationLookupTest | 16 tests / 172 assertions PASS, gồm assertions TTL bổ sung ở lượt cuối |
| Full selected regression | 251 tests / 2.159 assertions PASS, 1m47,359s, peak100MB; chạy trước 2 TTL assertions bổ sung |
| Pint toàn repo | PASS |
| npm run build | PASS, Vite6.4.3, 62 modules |
| Frontend harness Step One/Step Two/lookup | PASS |
| Render checks / production route-cache preflight | 33 / 9 PASS |
| Existing assets / git diff --check | 22 PNG assets PASS / whitespace PASS |

Full regression gồm lookup, demo isolation/realistic seeder transaction fixtures,
controlled approval, HTTPConcurrency 10 cases, identity update/retry/receipt cleanup,
business integration, final/draft/success/StepTwo UX và tests/Unit. Không chạy toàn
bộ legacy suite dùng RefreshDatabase/DatabaseMigrations. Lượt targeted cuối tăng
2 assertions xác minh D11 thực có plate rồi expires và không extend TTL; không tuyên
bố full suite mới có assertion count chưa chạy.

```powershell
php vendor/bin/phpunit tests/Feature/RegistrationLookupTest.php tests/Feature/DemoIsolationTest.php tests/Feature/ToyotaRealisticDemoSeederTest.php tests/Feature/ControlledDuplicateApprovalTest.php tests/Feature/RegistrationHttpConcurrencyTest.php tests/Feature/SubmissionIntegrityUpdateTest.php tests/Feature/SubmissionIntakeRetryTest.php tests/Feature/RegistrationReceiptCleanupTest.php tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/DealerRegistrationStepTwoValidationTest.php tests/Unit
php vendor/bin/pint --test
npm run build
php docs/figma/qa/render-check.php
php docs/figma/qa/release-preflight.php
php docs/figma/qa/asset-check.php
node --experimental-specifier-resolution=node docs/figma/qa/frontend-check.mjs
node --experimental-specifier-resolution=node docs/figma/qa/step-two-validation-check.mjs
node --experimental-specifier-resolution=node docs/figma/qa/lookup-validation-check.mjs
```

Tests cover valid pair Sales/Dealer QR, statuses pending/approved/rejected, historical
formats/multiple matches/stale aliases, wrong/missing verification, privacy/minimal
DTO/no mutation/no shared old-input, inactive Dealer, actual CSRF middleware
missing/wrong419/valid200, IP/identity quotas across formatting/IP/Dealer, own draft/
foreign session/Dealer/bare draft/expiration. Approved generated Facebook identity
có dữ liệu trong fixture vẫn không tạo membership UI. Không có state Facebook
riêng để kiểm thử; các case đó BLOCKED, không giả tạo DB columns/events để PASS.

Build lỗi ban đầu do Tailwind auto-scanning JSON QA Phase 2C.4C có serialized font
utility strings, sinh declaration không hợp lệ. Đã sửa **dữ liệu tài liệu** thành
tên font thuần; không sửa app.css/Admin/Sales typography hoặc lockfiles. Build lại
PASS. Không dùng generated CSS từ lượt lỗi làm bằng chứng bundle mới.

Production manifest hiện trỏ Dealer CSS `dealer-registration-CMU_xMLG.css` và JS
`dealer-registration-B4G2GMIV.js`; phải rebuild lại nếu source thay đổi. Source ZIP/
checksum của Phase 2C.4C là snapshot phase trước, **không phải release artifact Phase3A**.
Browser render/390/768/1440/font network/screen-reader/overflow/asset geometry vẫn
NOT VERIFIED; DOM harness và ảnh Figma không thay thế kiểm chứng đó.

## File tạo/chỉnh riêng Phase 3A

Tạo:
- app/Services/RegistrationLookup.php.
- app/Http/Controllers/RegistrationLookupController.php.
- app/Http/Requests/RegistrationLookupRequest.php.
- resources/views/dealers/registration/lookup.blade.php.
- tests/Feature/RegistrationLookupTest.php.
- docs/figma/qa/lookup-validation-check.mjs.
- docs/figma/qa/phase-3a-figma-evidence.json.
- docs/figma/qa/screenshots/phase-3a/README.md và 18 figma-*.png.
- docs/figma/phase-3a-registration-lookup.md.

Chỉnh:
- routes/web.php; app/Providers/RouteServiceProvider.php — lookup routes/throttles.
- app/Exceptions/Handler.php; app/Http/Middleware/SecurityHeaders.php — private safe lookup errors/referrer.
- resources/views/components/dealer-registration/layout.blade.php, step-one.blade.php;
  resources/views/dealers/registration/success.blade.php — links và lookup layout context.
- resources/css/dealer-registration.css; resources/js/dealer-registration.js — scoped UI/validation.
- docs/figma/qa/figma-reference-2c-4c.json — sanitize font metadata để build không đọc utility sai.
- public/build (ignored) được rebuild. Không chỉnh migration/model/intake/approval/leaderboard/session schema.

## Quyết định operator còn cần xác nhận

1. Điều kiện có bằng chứng và actor có quyền cho các trạng thái bổ sung, đã xin
   group, đang chờ group duyệt, thành viên chính thức và refusal reason code.
   Không mặc định approved=member hoặc pending=chờ vào group.
2. Quy trình lưu evidence/audit của các events đó; nếu cần schema additive phải
   được review riêng. Không backfill từ free-text notes/URL/click CTA/dữ liệu giả.
3. Customer authorization cho gửi bổ sung/sửa Facebook, phạm vi receipt hoặc provider
   xác minh được duyệt. Không dùng lookup pair để thay đổi approved identity.
4. Privacy acceptance cho masked existence lookup và quotas; full profile cần strong
   verification. Xử lý dữ liệu lịch sử ambiguous bằng công cụ Admin, không chọn latest.
5. Assets SVG còn thiếu và browser QA cho đủ layouts; terms/privacy nội dung/URL và
   mọi SLA48h cần được owner chốt, không copy placeholders/commitment từ Figma.

**Kết luận:** lookup nền tảng an toàn đã chạy; nghiệm thu toàn bộ D07–D15 và public
release còn BLOCKED bởi business evidence, hai decorative assets và browser QA.
Không commit, push, deploy, tự thêm migration hoặc sửa production data.
