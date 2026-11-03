# Toyota Community — Phase 2B

Ngày 03/11/2026. Đã đọc analysis-report.md, phase-1-implementation.md,
phase-2a-implementation.md, step-two-validation-ux.md và source liên quan. Source
thực tế được ưu tiên: CSRF Phase 2A đã sửa và bộ tests hiện chạy được; báo cáo cũ
về thiếu toyota_testing không còn đúng. Chưa có final endpoint/ODO trước lượt này.

## Luồng và mapping

D01 POST step-one → draft mã hóa server-side TTL 30 phút → D02 POST endpoint
`/{slug}/registrations` → pending submission + receipt cùng transaction → GET
`/{slug}/registrations/success` (route dealers.registrations.success).

Endpoint một bước cũ giữ nguyên URL/controller/action. `_flow=two-step` và `_draft`
định tuyến payload mới sang final controller. Không tin họ tên/phone/vehicle gửi
lại từ client; các trường đó bị prohibited và dữ liệu được lấy từ draft. Hidden
vehicle dùng cho UI được disabled, không gửi lại. Thiếu/hết hạn/sai session/Dealer,
stale draft hoặc Dealer inactive bị từ chối. Dealer active được khóa/kiểm tra lại
trong transaction trước insert. Preview local/testing vẫn là dialog, không intake.

| Nhãn yêu cầu | Field HTML / cột thật |
| --- | --- |
| province/ward | province_code, ward_code; tên lấy từ AdministrativeDivisions |
| địa chỉ | address_line |
| vehicle | vehicle_description từ draft, không thay danh sách xe Sales |
| registration_year | first_registration_year, 1950–năm hiện tại như D02 |
| odo | odo nullable unsigned integer, 0–4294967295 |
| facebook_profile_url | facebook_url; xác nhận personal URL bằng FacebookUrlNormalizer |
| terms_accepted | consent accepted → consented_at + consent_version server-side |

Hỗ trợ link Facebook bỏ scheme như frontend bằng cách thêm https:// trước validation.
Facebook do khách khai báo không phải verified identity: pending, reviewed_by/null,
approved_facebook_identity/null; Admin QR approval vẫn yêu cầu verifiedFacebookUrl.
source=dealer_qr, sales_id=null và dealer_id của Dealer giới thiệu. Không điểm khi
pending, không xác nhận group membership, không sửa approval/LeaderboardService.

## Schema và dữ liệu lịch sử

Đã kiểm tra migrations cũ, review/source/profile CHECK constraints, generated
approved_facebook_identity unique index, các cột vehicle/address. Không có ODO.
Preflight thực tế: MySQL 8.0.30, SELECT DATABASE()=toyota_testing, customer_submissions
trống. Không kiểm kê hoặc thay đổi database production.

Migration mới `2026_11_03_000023_support_final_dealer_registration.php`:

- odo nullable unsigned integer trên customer_submissions; lịch sử giữ null.
- submission_intake_mutex: một hàng InnoDB để serialize intake.
- dealer_registration_receipts: primary key HMAC draft_key, owner_key index,
  submission_id FK restrict delete, confirmation ciphertext và expires_at.

Không thêm unique phone/plate vào lịch sử, không backfill/đổi dữ liệu cũ. down()
từ chối nếu có receipt hoặc ODO để tránh rollback mất dữ liệu. Cần rollback code
tương ứng nếu gỡ mutex; không gỡ schema dưới các workers đang chạy phiên bản mới.

**Chỉ đã áp dụng migration mới vào toyota_testing**, sau kiểm tra actual connection.
Chưa áp dụng vào database local ứng dụng toyota hoặc production. Cần migrate schema
trước khi dùng code mới tại các môi trường đó; mọi insert giờ cần hàng mutex:

```powershell
php artisan migrate --path=database/migrations/2026_11_03_000023_support_final_dealer_registration.php
```

Lệnh trên là hướng dẫn áp dụng vào môi trường đã được xác nhận, không được chạy
mù vào production. Không migrate:fresh, không sửa migration cũ, không xóa dữ liệu.

## Chống trùng và compatibility

SubmissionIntake dùng RegistrationPhone cho phone; plate chuyển uppercase, bỏ
whitespace, dấu chấm và gạch ngang: `51k - 123.45` → `51K12345`. Chỉ pending/approved
chặn. Đọc cả raw phone và phone_normalized, cùng raw plate chuẩn hóa tại thời điểm
so sánh, để bao phủ Sales/Dealer và lịch sử chưa chuẩn hóa. Không tiết lộ record
khớp hoặc chủ sở hữu. Phone conflict đưa về D01/D05; plate conflict inline D02.

CustomerSubmission::performInsert bao bọc mọi create/save mới qua Eloquent bằng
transaction và SELECT FOR UPDATE hàng mutex. Khóa giữ đến outermost commit, không
release khi inner savepoint kết thúc. Kiểm tra lại dùng locking/current read trước
insert, tránh snapshot cũ sau khi chờ transaction khác. Final và legacy lấy mutex
trước Dealer lock. Mutex đồng bộ giữa các PHP workers dùng cùng MySQL.

Sales create và legacy Dealer create được bảo vệ qua cùng model guard. Sales map
lỗi phone sang customer_phone của form hiện có; dọn evidence khi create thất bại.
Legacy vẫn giữ cooldown/CSRF/throttle/form đầy đủ, nhưng nay từ chối phone đang
thuộc pending/approved trên toàn hệ thống (trước đây chỉ cooldown theo Dealer).
Đây là thay đổi compatibility có chủ đích theo chính sách mới. Legacy không có
plate field nên chỉ có thể kiểm tra phone; không tự thêm plate vào payload cũ.

Admin edit, Sales edit, approval/rejection và lịch sử không bị áp quy tắc cập nhật
mới. Admin edit hiện có khóa phone riêng; **không bảo đảm chống race giữa thay đổi
identity của một record cũ và intake mới**. Cần chốt riêng nếu muốn mở rộng guard
sang các update đó; không tự đổi nghiệp vụ Admin edit. Guard hiện bao phủ các luồng
tạo mới hiện có dùng Eloquent save/create, không phải unique constraint DB cho
SQL/import/bulk insert ngoài model. Các writer mới phải đi qua guard; direct SQL
cần audit riêng và không được coi là được bảo vệ. Source app hiện không có bulk
insert submission. Không tuyên bố mọi writer SQL tùy ý đã được bảo vệ.

Chi phí kiểm tra O(N) pending/approved và mutex toàn intake có thể giới hạn throughput.
Thiết kế ưu tiên correctness không sửa lịch sử; cần đo tải/thiết kế registry normalized
nếu triển khai quy mô lớn. Deadlock có transaction retry; lỗi còn lại trả generic,
khách có thể retry bằng cùng draft key.

## Idempotency, privacy và validation

Mỗi draft có UUID mới. `_draft` chỉ trong POST, không URL. Key receipt là HMAC gắn
session/Dealer/UUID; owner_key cũng HMAC. Receipt và submission commit cùng nhau.
Request retry cùng key kiểm tra receipt trước, không tạo lại; race cùng draft kiểm
tra receipt bằng current read bên trong mutex. Sau commit consume draft có khóa và
kiểm tra UUID, không xóa nhầm một draft mới của tab khác. Crash sau commit trước
consume vẫn recover qua receipt. Receipt hết quyền đọc sau 24h; draft 30 phút không
gia hạn khi lỗi. UUID cũ không thể tạo lại submission khi draft bị consume/hết hạn.

D04 dùng PRG, không ID tuần tự hoặc token trong URL. Confirmation DTO chỉ gồm tên,
phone, plate, vehicle và địa chỉ của người vừa đăng ký; lưu ciphertext trong receipt.
Chỉ session/Dealer khớp mới đọc được. Refresh D04 chỉ GET. Nội dung “Đã nhận đăng ký,
đang chờ xác minh”; group link vẫn qua component kiểm tra URL. Không nhận là approved.
Receipt hết hạn không tự xóa vật lý; retention/cleanup cần kế hoạch riêng, không xóa
dữ liệu trong lượt này. FK receipt hiện ngăn xóa submission đã có receipt.

Validation backend dùng allowlist, required/accepted/format/ward-province pairing,
honeypot, CSRF và quota dealer-registrations hiện có. Payload hợp lệ/không hợp lệ
được giữ có giới hạn trong draft mã hóa, không flash old input chung. Giữ inline
errors, focus, live regions và không summary. Form production submit POST thật,
không còn thông báo chưa gửi/lưu. Dữ liệu draft/file vẫn cần cùng filesystem hoặc
sticky routing trên multi-node như Phase 2A. App key không được đổi giữa các workers.

## Kiểm chứng

- DB tests chỉ toyota_testing; TestDatabase guard + SELECT DATABASE() trước suite.
- 49 tests / 515 assertions PASS: final (29 cases), Phase 2A (12), Step Two render
  (3), draft storage (4), normalization (1). Fixtures dùng DatabaseTransactions,
  không RefreshDatabase/migrate:fresh. Mọi fixtures rollback.
- Cases: pending/attribution/no points, ODO null/value/invalid, từng field, spoof
  Step One, thiếu/hết hạn/sai Dealer/session/inactive, phone/plate pending/approved/
  rejected xuyên Sales, raw historical formats, replay/refresh, DTO privacy,
  CSRF missing/wrong/valid, honeypot/throttle, model/legacy/Sales guard và Admin
  approval contract không đổi.
- Hai MySQL connections: kết nối thứ hai timeout 1205 khi outer transaction giữ
  mutex; chứng minh lock không release sớm. **Chưa chạy race hai HTTP requests
  đồng thời end-to-end hoặc test crash recovery bằng kill process.** Không tuyên
  bố an toàn tuyệt đối từ một test lock contention.
- Frontend regression và production POST check: PASS.
- Render-check.php: PASS, 33 assertions, gồm POST/CSRF/draft key và bỏ obsolete notice.
- Production build: PASS, 62 modules. PHP syntax/Pint được kiểm tra.
- Browser/mobile visual chưa kiểm chứng mới; không có screenshot 390px.

Không chạy toàn suite cũ dùng RefreshDatabase vì user cấm migrate:fresh; hồi quy
legacy/Sales/Admin được kiểm tra bằng transaction fixtures trong final suite mới.

## Files

Tạo:
- app/Services/SubmissionIntake.php
- app/Services/DealerRegistrationReceipt.php
- app/Http/Controllers/DealerRegistrationFinalController.php
- database/migrations/2026_11_03_000023_support_final_dealer_registration.php
- tests/Feature/DealerRegistrationFinalTest.php
- tests/Unit/SubmissionIntakeNormalizationTest.php
- docs/figma/phase-2b-implementation.md

Chỉnh:
- app/Models/CustomerSubmission.php
- app/Services/DealerRegistrationDraft.php
- app/Http/Controllers/DealerRegistrationDraftController.php
- app/Http/Controllers/DealerRegistrationController.php
- app/Http/Controllers/Sales/SubmissionController.php
- app/Exceptions/Handler.php
- routes/web.php
- resources/views/components/dealer-registration/step-one.blade.php
- resources/views/dealers/registration/step-two-content.blade.php
- resources/views/dealers/registration/success.blade.php
- resources/js/dealer-registration.js
- docs/figma/qa/step-two-validation-check.mjs
- docs/figma/qa/render-check.php

Ignored public/build được tạo lại. Không commit, push hoặc deploy.
