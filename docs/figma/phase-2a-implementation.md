# Toyota Community — Phase 2A: server-side registration draft

Ngày: 02/11/2026. Tham chiếu analysis-report.md và phase-1-implementation.md.

## Kết quả triển khai

D01 production/local thật POST bước 1 → kiểm tra Dealer active, validation và số
điện thoại toàn hệ thống → lưu draft server-side → redirect D02. Không tạo
CustomerSubmission tại bước 1. D02 refresh đọc lại draft. Quay lại/Sửa dùng URL
Dealer canonical với anchor registration; D01 đọc lại cùng draft. Preview fixture
vẫn chỉ local/testing và giữ DOM-only flow riêng, không gọi backend intake thật.

Source thực tế được ưu tiên: POST /{slug}/registrations vẫn là endpoint một bước
đang tạo pending; không phải final handler của flow mới. Giao diện D02 vẫn chưa
gửi final submission. Không nối D02 vào endpoint cũ. Nội dung D02 phân biệt draft
bước 1 đã lưu tạm và các thông tin bước 2 chưa lưu/gửi.

## Khảo sát và khác biệt với báo cáo Phase 1

Đã đọc hai báo cáo, routes/web.php, DealerRegistrationController,
DealerRegistrationRequest, PublicDealerController, CustomerSubmission,
RegistrationPhone, các Blade/JS Dealer Registration và feature tests liên quan
(DealerLandingRegistrationTest, DealerProfileTest, SubmissionDuplicateTest),
session/cache config, kernel, CSRF middleware, rate limit provider và exception handler.

- Workspace không có .env hoặc bootstrap config cache. Config mặc định là session
  file, cache file, session HTTP-only, SameSite=lax. Cấu hình môi trường triển khai
  bên ngoài workspace không được xác minh.
- Production bundle và npm run build hiện hoạt động, khác giới hạn build ghi trong
  Phase 1. Không thay package dependencies.
- Legacy validation đã dùng RegistrationPhone nhưng chỉ cooldown 10 phút theo
  Dealer/phone; không có chính sách chặn pending/approved phone toàn hệ thống.
- Sales cho phép danh sách xe rộng trong config/vehicles.php và phone tùy chọn;
  dữ liệu cũ có thể thiếu phone_normalized. Không thêm FJ vào danh sách Sales.
- Schema có ràng buộc trạng thái/reviewer. Fixtures mới tuân thủ ràng buộc này;
  không sửa schema hay workflow approval.

## Draft, TTL và nhiều tab

DealerRegistrationDraft lưu ciphertext JSON bằng Laravel Crypt trong cache store
file server-side hiện có. Khóa cache là HMAC-SHA256 qua RegistrationPhone::key của
session ID + Dealer ID; không chứa tên, số điện thoại hay slug. Cookie/session
không chứa PII draft, kể cả nếu driver session sau này đổi sang cookie. Không có
draft token hoặc dữ liệu cá nhân trong URL/localStorage/flash old input.

Payload chỉ gồm ba trường bước 1 đã vượt validation, errors generic, duplicate flag,
complete flag và expires_at. TTL 30 phút từ lần POST; GET/refresh không gia hạn.
Cache TTL và expires_at đều được kiểm tra. Dữ liệu lỗi không đủ điều kiện đi D02.
Draft thiếu/hết hạn → redirect D01 với thông báo generic không chứa PII. Dealer
inactive không thể POST; GET D02/D01 loại bỏ draft đã có khi thấy Dealer inactive.
Payload không giải mã được bị loại bỏ an toàn. Cache write thất bại không báo lưu
thành công.

Một draft cho mỗi cặp session/Dealer. Các Dealer khác nhau có cache entries riêng,
không dùng chung mutable session map nên không ghi đè draft của nhau khi mở nhiều
tab. Với cùng Dealer, lần POST hoàn tất sau cùng thay bản trước; các tab D02 refresh
thấy bản sửa mới nhất. Không hỗ trợ các draft độc lập cho cùng Dealer trong một
session. Logout/regenerate session ID khiến draft cũ không truy cập được; ciphertext
cũ hết TTL. Draft không cấp quyền xem submission hay quyền Admin/Sales.

File cache phù hợp môi trường single-node hiện tại. Multi-node cần shared file
storage hoặc chuyển có chủ đích sang persistent shared server store trước khi triển
khai; không được giả định local file cache đồng bộ giữa máy chủ. File expired không
đọc có thể còn ciphertext trên đĩa đến lúc cache được dọn; TTL chặn sử dụng nhưng
không phải tác vụ xóa vật lý định kỳ.

## Routes và bảo mật

| Method | URL | Name |
| --- | --- | --- |
| POST mới | /{slug}/registrations/step-one | dealers.registrations.step-one |
| GET mới | /{slug}/registrations/step-two | dealers.registrations.step-two |
| POST giữ nguyên | /{slug}/registrations | dealers.registrations.store |

Hai routes mới đặt trước catch-all /{slug}, giữ pattern slug hiện có. Không đổi
/register, QR, canonical slug, Sales/Admin, leaderboard hoặc awards.

POST mới thuộc web middleware (CSRF) và limiter dealer-registrations hiện có:
5/phút + 20/giờ theo HMAC IP, xuyên Dealer và cả requests validation sai. Quota
chia sẻ với legacy endpoint. Honeypot website phải rỗng. Chỉ ba trường hợp lệ được
lưu, system fields/unknown non-empty fields bị prohibited. Errors không phản chiếu
giá trị gửi lên, không withInput hoặc withErrors vào error bag chung. Exception
handler mở rộng phạm vi các routes dealers.registrations.* để trả thông báo generic,
giữ HTTP status/Retry-After, không log raw request hoặc trace chứa PII.

Landing và D02/draft redirects có Cache-Control no-store,private. JS production
submit form POST thật sau frontend validation; không chuyển dữ liệu sang form cũ.
Không có JS vẫn có POST + CSRF server validation. D02 còn method=dialog và chỉ
validation UI; không tạo submission giả hoặc báo đăng ký thành công.

## Số điện thoại trùng

Chuẩn hóa bằng RegistrationPhone::normalize hiện có, kiểm tra format Việt Nam,
required name (max 255), required vehicle trong Veloz/Hilux/Fortuner/FJ.
Kiểm tra CustomerSubmission pending/approved không lọc Dealer hoặc source. So sánh
cả raw phone và phone_normalized sau chuẩn hóa để không bỏ sót Sales lịch sử.
Rejected không chặn. Truy vấn chỉ đọc id/phone/phone_normalized theo lazyById(500),
không truyền raw phone vào query binding. Không sửa/backfill dữ liệu lịch sử.

Kết quả trùng chỉ là boolean + thông báo generic cho D05 inline trong D01, không
trả model/ID/tên/người sở hữu. Giữ dữ liệu hợp lệ người đang nhập bằng draft mã hóa.
Kiểm tra sớm này không đặt reservation, không chống race ở final intake. Việc đọc
toàn bộ pending/approved có chi phí O(N); cần đánh giá quy mô và chuẩn hóa dữ liệu
theo kế hoạch riêng trước tối ưu index, không tự thêm constraint trong Phase 2A.

## Kiểm tra

| Kiểm tra | Kết quả |
| --- | --- |
| Unit storage | PASS: 4 tests, 16 assertions; không kết nối DB |
| Render contract QA | PASS: 14 assertions, đã cập nhật assertion production POST mới |
| Frontend checks | PASS: chuẩn hóa phone, Facebook, validation, không đoán trùng |
| PHP syntax và Pint | PASS cho các PHP đã thay đổi |
| route:list --path=registrations -v | PASS, đúng 3 routes, POST mới có web/throttle |
| npm run build | PASS, 62 modules; registration JS mới Chw9fg_w; app CSS/JS hash giữ nguyên |
| Feature DB integration | BLOCKED: MySQL báo Unknown database toyota_testing |
| Browser end-to-end | Chưa thực hiện |

Đã viết DealerRegistrationDraftTest gồm 11 tests: lưu/refresh/sửa/không tạo
submission; TTL; session/Dealer/multi-tab isolation; inactive; global pending/approved
và rejected/D05/privacy; validation/honeypot/system fields; throttle xuyên Dealer;
CSRF token thiếu/hợp lệ; routes và Sales choices; bốn xe; legacy fallback intake và
duplicate qua Dealer khác. Tests dùng DatabaseTransactions trên schema test hiện
có, không RefreshDatabase và không migration. Lần chạy ban đầu 9 tests dừng ở setup
vì toyota_testing không tồn tại, 0 assertions; hai tests bổ sung cũng chưa chạy được.
Không tuyên bố feature suite, duplicate SQL, CSRF hay throttle đã được xác minh
end-to-end. Không chạy suite cũ dùng RefreshDatabase vì sẽ chạy migrations.

Unit tests kiểm tra roundtrip, ciphertext không có raw PII, session không có PII,
cả tình huống config cookie driver, session/Dealer isolation, sửa độc lập, fixed TTL,
inactive invalidation và error state không hoàn chỉnh. Assertions so sánh dữ liệu
nhạy cảm bằng boolean, không in raw phone/name trong failure messages.

Để chạy feature suite khi schema test cách ly đã được chuẩn bị bên ngoài lượt này:
`php vendor/bin/phpunit tests/Feature/DealerRegistrationDraftTest.php` với credentials
DB test phù hợp. TestDatabase guard buộc APP_ENV=testing và database toyota_testing.
Không tạo DB, chạy migration, đổi schema hoặc kết nối production để vượt blocker.

## File tạo/chỉnh sửa

Tạo:

- app/Services/DealerRegistrationDraft.php
- app/Http/Controllers/DealerRegistrationDraftController.php
- tests/Feature/DealerRegistrationDraftTest.php
- tests/Unit/DealerRegistrationDraftStorageTest.php
- docs/figma/phase-2a-implementation.md

Chỉnh:

- app/Http/Controllers/PublicDealerController.php — đưa draft đúng session/Dealer vào D01.
- app/Exceptions/Handler.php — privacy/error handling cho routes draft.
- routes/web.php — POST step-one và GET step-two.
- resources/views/components/dealer-registration/step-one.blade.php — POST/CSRF/honeypot/errors.
- resources/views/dealers/registration/step-two-content.blade.php — thông báo trạng thái Phase 2A.
- resources/js/dealer-registration.js — production POST thật, preview vẫn tách riêng.
- docs/figma/qa/render-check.php — contract assertion mới.

Vite tạo lại ignored public/build artifacts. Không sửa DealerRegistrationController,
DealerRegistrationRequest, CustomerSubmission, RegistrationPhone, config/vehicles,
Admin approval, LeaderboardService, schema hoặc migrations. Không commit/push/deploy.

## Còn lại cho Phase 2B và giới hạn

Legacy form/endpoint giữ nguyên khả năng tạo pending; chưa áp dụng global phone/plate
duplicate policy vào endpoint cũ hoặc Sales/Admin edit. Do vậy fallback vẫn có thể
tạo số điện thoại trùng theo quy tắc cũ; không coi Phase 2A đã chặn trùng toàn bộ intake.
Không thay chính sách Admin edit hoặc dữ liệu lịch sử trong lượt này.

Phase 2B cần final POST contract, revalidation phone/plate toàn hệ thống, race control,
idempotency, consent, Facebook, consume draft và confirmation an toàn. Chưa lưu ODO,
chưa final submission. Các field D02 người dùng nhập thêm chưa persist khi refresh;
chỉ dữ liệu bước 1 được giữ. Feature/browser QA phải hoàn tất trước triển khai.
