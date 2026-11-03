# Phase 2C.1 — Business integration & regression

Ngày 03/11/2026. Kiểm thử trên PHP 8.5.11, PHPUnit 12.5.40 và MySQL local.

## Phạm vi và tài liệu

Đã đọc analysis-report.md, phase-2a-implementation.md,
phase-2b-implementation.md và d04-success-ui-refinement.md; đối chiếu controllers,
requests, policies, models, registration draft/receipt/intake, Admin actions,
LeaderboardService, DealerDashboardData, demo service, routes, submission/snapshot/
QR/edit/Phase 2B migrations và tests liên quan.
phase-2b-success-page-refinement.md không tồn tại.

Source thực tế được ưu tiên:

- analysis-report là khảo sát trước triển khai: draft, ODO, final, success routes
  và global duplicate protection hiện đã có, không còn là đề xuất.
- Phase 2A ghi thiếu database testing và D02 chưa POST final: đã lỗi thời.
  Database toyota_testing hiện kết nối được và endpoint cũ phân luồng final qua
  `_flow=two-step`/`_draft`, đồng thời giữ legacy payload.
- Phase 2B ghi heading chờ xác minh: D04 hiện dùng “Đăng ký thành công!” theo
  refinement mới. Backend vẫn pending; Group CTA không cần approval.
- Public Dealer canonical URL do DealerProfile tạo có dấu `/` cuối; test dùng
  service này, không sửa URL/QR để khớp giả định trong fixture.

## Bảo vệ database

Trước khi chạy DB tests, bootstrap APP_ENV=testing, DB_CONNECTION=mysql,
DB_DATABASE=toyota_testing và DATABASE_URL rỗng; gọi TestDatabase guard, sau đó
`SELECT DATABASE() AS name` trả đúng `toyota_testing`.
Test mới kiểm tra lại database thực tế trước khi bắt đầu từng transaction fixture.

Chỉ chạy các file test được liệt kê bên dưới. DB fixtures dùng DatabaseTransactions,
rollback sau mỗi case. Không chạy suite cũ sử dụng RefreshDatabase, migration,
migrate:fresh, seed hoặc cleanup dữ liệu nghiệp vụ. Không truy vấn hoặc thay đổi
database toyota/production. Auto-increment của test DB có thể tăng dù row rollback.
Ảnh evidence giả và file cache draft chỉ nằm trong vùng fixture testing riêng.

## Kết quả tích hợp

| Luồng | Kết quả đã kiểm chứng |
| --- | --- |
| D01 → draft → D02 → final → D04 | Step One không tạo submission; final tạo đúng một pending, source dealer_qr, sales_id null, đúng dealer_id; lưu address_line, ODO null/0/25000 |
| D04, retry và refresh | Heading thành công, anchor Group có href thật; retry cùng draft và refresh không thêm row; no-store/private; receipt bị giới hạn session/Dealer |
| Admin queue/detail | Khách mới nằm trong queue pending; Admin đọc được detail, thông tin xe/địa chỉ và ODO sau bản sửa |
| Admin edit pending | Sửa tên qua HTTP PATCH, version HMAC hợp lệ, có SubmissionEdit; không đổi status, không tự duyệt |
| Admin approve QR | Thiếu verified_facebook_url bị 422; xác minh hợp lệ mới approved; một moderation review; duyệt lại bị từ chối |
| Admin reject | Chuyển rejected, một moderation review, không approved identity, không điểm; fixture lịch sử không bị đổi |
| Admin edit approved | Sửa thông tin theo quyền cũ giữ approved identity/status; không được chuyển Dealer để đổi thành tích lịch sử |
| Dealer/Sales authorization | Dealer sở hữu xem được; Dealer khác không xem được; Sales không sở hữu không xem khách QR; Dealer không gọi được Admin approve |
| Điểm khách QR | Pending/rejected không điểm; approved cộng đúng Dealer, không tạo điểm Sales khi sales_id null |
| Sales tạo/sửa/chuyển Dealer | Tạo pending bằng form có evidence; pending edit giữ snapshot Dealer; bản tạo sau chuyển thuộc Dealer mới |
| Leaderboard tháng | Gửi 31/10, duyệt 02/11 vẫn tính tháng 10; bản gửi tháng 11 tính tháng 11; hai Dealer giữ điểm riêng, tổng điểm Sales đúng |
| Legacy Dealer | Giữ endpoint và payload cũ, tạo pending không Sales, không tự thêm plate; phone pending/approved toàn hệ thống bị chặn; rejected cho phép |
| Sales create duplicate | Phone/plate pending/approved xuyên Sales/Dealer bị chặn, gồm định dạng lịch sử chưa chuẩn hóa; rejected không chặn; evidence của request bị từ chối được dọn |
| Dealer profile/QR/inactive | Legacy numeric profile redirect canonical; QR PNG giữ nguyên sau đổi tên/deactivate; slug không đổi; inactive không nhận legacy, draft/final bị chặn trong regression |
| Bảo mật | Regression CSRF thiếu/sai token bị từ chối, token hợp lệ được chấp nhận; honeypot, throttle, system-field validation và draft isolation PASS |

Không gọi Facebook để xác nhận membership. Click CTA không thực hiện approval
hoặc cập nhật membership trong ứng dụng. Tests HTTP chạy qua Laravel test kernel,
không phải browser end-to-end.

## Lỗi đã phát hiện và sửa

ODO được final intake lưu đúng nhưng component thông tin xe chưa hiển thị.
DealerDashboardData còn thiếu `odo` trong projection. Test mới với ODO 0 và 25000
thất bại trước khi sửa, còn null đúng hành vi không hiển thị.

Sửa:

- app/Services/DealerDashboardData.php: lấy thêm cột odo cho chi tiết Dealer.
- resources/views/components/submission-vehicle-details.blade.php: hiển thị
  “Số km (ODO)” khi khác null, định dạng km; giữ cả giá trị 0. Component nội bộ
  dùng chung Admin/Dealer/Sales; record lịch sử có ODO null giữ giao diện cũ.
- tests/Feature/RegistrationBusinessIntegrationTest.php: mới, 19 cases /
  306 assertions, transaction fixtures và preflight database thực tế.
- docs/figma/phase-2c-1-integration.md: báo cáo này.

Các lỗi ban đầu khác là fixture/setup, đã sửa trong test mới: reviewed statuses
phải dùng factory state có reviewer/time để tuân thủ CHECK; snapshot so sánh lịch sử
phải refresh sau insert để lấy generated column/giá trị DB; URL canonical có `/` cuối.
Không sửa schema hoặc nghiệp vụ để vượt những lỗi setup đó.

Không thay approval, Facebook verification, draft/receipt, chống trùng, routes,
LeaderboardService, schema, dữ liệu lịch sử hoặc quy tắc Sales edit.

## Tests và build

Lệnh đã chạy thành công:

```powershell
php vendor/bin/phpunit tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/DealerRegistrationStepTwoValidationTest.php tests/Unit/DealerRegistrationDraftStorageTest.php tests/Unit/SubmissionIntakeNormalizationTest.php
php vendor/bin/pint --test app/Services/DealerDashboardData.php tests/Feature/RegistrationBusinessIntegrationTest.php
php docs/figma/qa/render-check.php
node --experimental-specifier-resolution=node docs/figma/qa/frontend-check.mjs
node --experimental-specifier-resolution=node docs/figma/qa/step-two-validation-check.mjs
npm run build
```

Kết quả: **69 tests / 891 assertions PASS**, Pint PASS, 33 render checks PASS,
hai frontend scripts PASS, Vite build PASS (62 modules). Không tắt CSRF để làm
test pass; regression CSRF sử dụng middleware thực theo setup hiện có.

## Rủi ro và những điều chưa đạt/chưa kiểm chứng

1. **Chưa bảo đảm demo không xuất hiện trong bảng xếp hạng thực nếu trộn dữ liệu.**
   Leaderboard hiện không lọc ToyotaRealisticDemo::TAG. Test characterization tạo
   một approved fixture mang tag demo và xác nhận vẫn được tính 1 điểm. Đây là
   bằng chứng rủi ro, không phải assertion rằng đã có demo isolation. Không tự thêm
   bộ lọc theo notes/email hoặc đổi business scoring. Không cài/đọc dataset tại
   toyota/production nên không kết luận môi trường đó đang sạch demo; cần tách DB
   demo hoặc chốt chính sách nhận diện/loại trừ trước khi dùng bảng xếp hạng thật.
2. **Chống trùng create không đồng nghĩa chống trùng edit.** Test characterization
   chứng minh Sales sửa pending vẫn có thể đổi phone/plate trùng pending khác.
   Hành vi này đúng giới hạn Phase 2B đã ghi, không được tính là coverage PASS cho
   uniqueness của update. Admin edit chỉ kiểm tra phone khi đổi phone; không sửa
   plate qua form Admin hiện tại. Không tự mở rộng quy tắc edit/approval. Nếu yêu
   cầu uniqueness áp dụng cả update, cần một thay đổi riêng với chính sách và khóa
   dùng chung; hiện chưa thể tuyên bố chống trùng toàn bộ vòng đời submission.
3. Admin edit/approval và intake chưa cùng một mutex cho identity updates. Race
   giữa update lịch sử và intake mới chưa được giải quyết; không thay nghiệp vụ
   Admin trong lượt này. Direct SQL/import không đi qua Eloquent guard cũng không
   được bảo vệ bởi unique phone/plate constraint.
4. Test hai MySQL connections xác nhận mutex giữ đến outer transaction kết thúc;
   chưa chạy hai HTTP requests concurrent end-to-end hoặc kill-process recovery.
5. Chưa browser end-to-end, screenshot mobile, click Group trong trình duyệt hoặc
   xác minh quyền truy cập Group trên Facebook. Không kiểm thử mọi màn hình Admin/
   Sales và toàn suite awards/auth bằng transaction mới; chỉ các đường đi nêu trên.
6. Chưa đo tải O(N) duplicate scan/mutex; chưa kiểm chứng multi-node file draft cache,
   receipt retention/cleanup hoặc schema/deployment readiness của toyota/production.
   Không triển khai public lookup/bổ sung hồ sơ ngoài phạm vi Phase 2C.1.

Các PASS là kết quả hồi quy trong phạm vi nêu trên; các rủi ro 1–3 vẫn tồn tại và
không được coi là nghiệm thu đầy đủ cho việc vận hành dữ liệu thực.
Không commit, push hoặc deploy.
