# Phase 2C.4A — Historical audit & controlled duplicate approval

Ngày 03/11/2026. Implementation + testing, không thay schema hoặc dữ liệu production.

## Khảo sát và khác biệt với báo cáo trước

Đã đọc analysis-report.md, phase-2b-implementation.md, phase-2c-1-integration.md,
phase-2c-2-data-integrity.md và phase-2c-3-release-readiness.md; đối chiếu
SubmissionIntake, CustomerSubmission, Admin approval/rejection/edit, requests,
SubmissionEdit, ApprovedSubmissionFinder/Facebook normalization, DealerDashboardData,
LeaderboardService, submission/QR/vehicle/edit/receipt migrations và tests liên quan.

Source trước lượt này đã có guard insert/identity edit và HTTP concurrency tests,
khác các báo cáo cũ còn mô tả chưa kiểm chứng. Approval vẫn transaction riêng, chưa
lấy intake mutex hoặc kiểm tra phone/plate. Admin editor chưa hỗ trợ sửa plate.
Các quyết định lịch sử còn treo trong Phase 2C.2/2C.3 nay được chốt theo yêu cầu
2C.4A: pending đang xung đột không được duyệt, không grandfather/bypass approval.
Approved cũ không bị tự động đổi trạng thái/dữ liệu hoặc tính lại điểm.

## Business rules đã triển khai

- Occupancy gồm pending và approved, xuyên Dealer/Sales/source; rejected không chiếm.
- Phone dùng RegistrationPhone::normalize, kiểm tra cả raw phone và phone_normalized
  của target lẫn các record khác, kể cả alias lịch sử chưa chuẩn hóa/stale.
- Plate dùng SubmissionIntake::plate: uppercase, bỏ whitespace, dấu chấm/gạch ngang.
- Khi approve, loại chính record; mọi collision phone hoặc plate đều trả validation
  theo field. Kiểm tra nằm trong transaction sau mutex/row lock, không tin GET warning.
- Thất bại: giữ pending, không approved identity, không review approved, không điểm;
  không sửa record đối chiếu. Hai pending trùng nhau đều bị chặn, không tự chọn winner.
- Admin chỉ có thể sửa identity sai thành giá trị hợp lệ hoặc reject theo flow cũ.
  Không merge/delete/auto-reject/exception approve/bypass button.
- Sửa tên/địa chỉ khi identity không đổi vẫn được lưu, không backfill/chuẩn hóa hàng
  loạt và không viết lại raw formatting lịch sử chỉ vì submit editor.
  Đồng thời giữ phone khi client bỏ qua field đó, giữ alias phone_normalized lịch sử
  khi raw phone null mà Admin chỉ sửa tên; không coi null và chuỗi canonical rỗng
  là một yêu cầu xóa alias.
- Admin editor thêm license_plate, version HMAC bao gồm plate. Field thiếu từ client
  cũ không xóa dữ liệu; plate đã có không được xóa trắng để bỏ qua collision. Chỉ
  normalize/store khi canonical plate thay đổi. Thay đổi có SubmissionEdit audit;
  giá trị plate trong audit được ẩn theo cơ chế auditValue hiện có.
- Bỏ phone SQL comparison riêng trong Admin edit; dùng canonical model guard/mutex
  đã có, tránh bỏ sót historical formats và tránh chặn identity của rejected.
- Model guard kiểm tra lại khi bản ghi rejected được save sang pending/approved
  bằng code path model hiện hữu, kể cả chỉ đổi status. Không thêm route/UI reactivation,
  không cho action approve xử lý rejected hoặc quay approved về trạng thái khác.
- Facebook verification và unique approved Facebook identity được giữ nguyên.
  Không backfill generated identity, không đổi approval/rejection moderation semantics.

## Transaction và lock ordering

Approval hiện dùng SubmissionIntake::locked, thay transaction riêng:

```text
authorize Admin
→ begin transaction (retry tối đa 3)
→ SELECT FOR UPDATE intake mutex
→ SELECT FOR UPDATE target submission
→ authorize/check pending
→ locking/current-read phone + plate conflicts, except target ID
→ integrity + Facebook verification + existing Facebook duplicate check
→ update approved + insert ModerationReview
→ commit
```

Đây là một outer transaction; không bọc thêm transaction quanh approval action.
Save pending→approved không đổi phone/plate nên không mở thêm model identity-edit
transaction. Final/legacy lấy mutex trước Dealer row; Sales create lấy mutex trước
User row; Sales update/Admin edit lấy mutex trước submission row. Admin edit giữ
named phone lock hiện hữu trước mutex. Không dùng GET warnings để cấp quyền approve.

Reject giữ row-lock/pending-only transaction hiện có, không cần thêm mutex vì chỉ
giải phóng occupancy; không tạo dependency ngược lên mutex. Laravel transaction retry
và generic handling cho lock timeout/deadlock của approval được giữ lại.
Unique generated approved_facebook_identity/index không sửa. Direct SQL ngoài
guard vẫn phải tuân thủ domain protocol; đây không phải DB unique phone/plate constraint.

## Admin UX

- Queue và detail chỉ tạo collision warnings sau authorization. Một global scan
  theo batch 500 phục vụ cả trang pending, thay vì scan riêng từng card. Warnings
  giữ keys/IDs của target trên trang trong bộ nhớ, không đưa PII record đối chiếu ra UI.
- Review form hiển thị từng lỗi phone/plate, hướng dẫn sửa hoặc từ chối; không có
  summary lỗi dài hoặc số lượng lỗi. Approve disabled khi GET phát hiện conflict,
  nhưng backend vẫn kiểm tra lại nếu request POST trực tiếp.
- Khi edit lỗi: lỗi phone/plate nằm ngay dưới input, aria-invalid/describedby/alert,
  dữ liệu old input đúng edit_submission_id được giữ. Không lặp lại hai lỗi này ở
  admin-feedback hoặc form review cùng row đang edit. Review old input cũng gắn ID.
- Tái sử dụng submission-inline-edit tại detail; giữ inline editor/approve/reject,
  filter/pagination behavior và version check hiện có. Không redesign dashboard.
- Không sửa D04 và không đưa warnings Admin lên trang khách hàng.

## Read-only audit command

```powershell
# Cú pháp khi runtime đã được cấu hình/vị trí DB xác minh là toyota_testing:
php artisan submissions:audit-collisions --confirm-database=toyota_testing --json

# Operator chạy sau khi tự xác minh connection và quyền được cấp:
php artisan submissions:audit-collisions --confirm-database=DATABASE_NAME_CONFIRMED_BY_OPERATOR
```

Command không chạy nếu thiếu/sai confirmation; so tên cấu hình trước khi kết nối,
sau đó so SELECT DATABASE() thực tế. MySQL standalone command dùng transaction
REPEATABLE READ, READ ONLY, không SELECT FOR UPDATE/mutex và không DML. Khi gọi
trong transaction fixture hiện hữu, dùng transaction đó thay vì đổi isolation.
Không log, không file export, không raw phone/plate/name/Facebook, không dump IDs/keys.
Nhóm trong bộ nhớ dùng HMAC keys, không lưu normalized identity vào bảng mới.

| Metric | Định nghĩa |
| --- | --- |
| active_submissions | Tổng row pending/approved |
| phone_groups | Số canonical phone có ít nhất 2 submission khác nhau |
| plate_groups | Số canonical plate có ít nhất 2 submission khác nhau |
| affected_submissions | Số row phân biệt trong hợp của các collision groups, không đếm lại giữa phone/plate |
| groups_with_approved | Tổng phone/plate groups có ít nhất một approved |
| pending_only_groups | Tổng phone/plate groups chỉ chứa pending |
| cross_dealer_groups | Tổng phone/plate groups có >=2 Dealer ID khác nhau đã biết; null không coi là một Dealer |

Một row có raw phone và normalized phone cùng canonical chỉ tính một lần trong
nhóm. Nếu hai giá trị lịch sử thực sự khác nhau, cả hai alias chiếm dụng theo logic
intake hiện có; command không tự chọn alias đúng hoặc backfill. Blank identity bỏ qua.
Hai fields là hai nhóm riêng ngay cả khi cùng tập row, affected_submissions vẫn union.

Kết quả command standalone trên toyota_testing: tất cả 7 metrics bằng 0, sau fixtures
đã rollback/cleanup. Đây **không phải audit toyota/production**, không kết luận dữ
liệu thực sạch trùng. Không kết nối/chạy audit trên toyota/production trong lượt này.
Lượt kiểm tra standalone gọi command bằng Artisan::call trong bootstrap ép
APP_ENV=testing, DB_DATABASE=toyota_testing và xác minh SELECT DATABASE(), không dùng
database mặc định trong .env local để chạy lệnh.

Nếu cần điều tra chi tiết, đề xuất trang Admin có policy review, pagination,
access audit và dữ liệu tối thiểu, thay vì export PII qua CLI. Chưa triển khai export
hoặc màn hình điều tra mới; Admin dùng editor/review/filter hiện hữu.

## Tests và kiểm chứng

ControlledDuplicateApprovalTest dùng DatabaseTransactions, kiểm tra tên database
thực tế trước fixture; có 19 cases / 155 assertions PASS:

- Pending trùng approved/pending theo từng field → 422, pending giữ nguyên, không
  review/approved identity/điểm; conflicting row và leaderboard được so trước/sau.
- Rejected không chặn; normal approval và self-exclusion hợp lệ.
- Raw historical formats, target phone_normalized alias đều được phát hiện.
- Sửa tên/địa chỉ không bị chặn bởi identity collision không đổi; identity mới
  xung đột bị chặn. Sửa phone/plate hợp lệ rồi approve được; audit ẩn plate.
  Regression thêm cho raw phone null + alias lịch sử và payload không gửi phone.
- Hai pending không có winner tự động; reject thủ công một record giải phóng identity.
- Existing model reactivation pending/approved được kiểm tra, collision bị từ chối,
  không collision vẫn dùng được code path cũ; HTTP approve rejected vẫn bị từ chối.
- Admin warnings theo field xuất hiện một lần ở detail, edit input giữ dữ liệu,
  aria state đúng; CSRF missing/wrong 419 và valid token được nhận; Sales 403;
  stale edit_version do plate thay đổi bị từ chối.
- Audit đúng counts, alias dedup/union affected records, cùng/cross Dealer,
  bỏ rejected; output không PII, không sửa rows; confirmation thiếu/sai bị từ chối.

Mở rộng RegistrationHttpConcurrencyTest với 3 cases thực qua hai loopback HTTP
servers và hai browser sessions/DB connections, CSRF vẫn bật:

| Race | Kết quả |
| --- | --- |
| Intake Final vs approval, cùng phone | Pending target đã tồn tại sau khi draft được lập; approval thành công, intake bị chặn, chỉ một approved và một review, không receipt mới |
| Intake Final vs approval, cùng plate | Như trên, khác phone; không tạo xung đột mới |
| Hai Admin requests approve hai pending lịch sử trùng nhau | Cả hai 422, hai records pending, không review/approved identity/receipt |

3 cases mới / 124 assertions PASS; harness vẫn giữ 7 cases concurrency Phase 2C.2.
Barrier cũ trong tests/Fixtures/approve_submission.php được chuyển trước mutex để
tránh test worker giữ mutex rồi chờ đối thủ không thể vào. Không phải bypass khóa.
Suite legacy SubmissionApprovalRaceTest dùng DatabaseMigrations chưa chạy trong
lượt này; evidence concurrency mới là HTTP suite không chạy migrations.

Lệnh regression chọn lọc:

```powershell
php vendor/bin/phpunit tests/Feature/ControlledDuplicateApprovalTest.php tests/Feature/RegistrationHttpConcurrencyTest.php tests/Feature/SubmissionIntegrityUpdateTest.php tests/Feature/SubmissionIntakeRetryTest.php tests/Feature/RegistrationReceiptCleanupTest.php tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/DealerRegistrationStepTwoValidationTest.php tests/Unit
php vendor/bin/pint --test
npm run build
php docs/figma/qa/render-check.php
php docs/figma/qa/release-preflight.php
node --experimental-specifier-resolution=node docs/figma/qa/frontend-check.mjs
node --experimental-specifier-resolution=node docs/figma/qa/step-two-validation-check.mjs
```

Kết quả regression đầy đủ cuối: **220 tests / 1.858 assertions PASS**, 64,997 giây,
peak memory 88 MB trong môi trường local. HTTP suite gồm 10 cases, trong đó có
3 cases approval mới. Build PASS (62 modules),
Pint toàn repo PASS, 33 render checks và 9 production preflight checks PASS, hai
frontend scripts PASS. Không dùng migrate:fresh/RefreshDatabase hoặc disable CSRF.

## File thay đổi trong Phase 2C.4A

Tạo:
- app/Services/SubmissionCollisionAudit.php
- app/Console/Commands/AuditSubmissionCollisions.php
- tests/Feature/ControlledDuplicateApprovalTest.php
- docs/figma/phase-2c-4a-controlled-approval.md

Chỉnh:
- app/Services/SubmissionIntake.php — thêm target phone aliases cho canonical matching.
- app/Actions/ApproveCustomerSubmission.php — mutex-first và collision validation.
- app/Models/CustomerSubmission.php — kiểm tra model reactivation từ rejected.
- app/Actions/UpdateCustomerSubmission.php — plate edit/version/audit, dùng guard chung.
- app/Http/Requests/Admin/UpdateSubmissionRequest.php — plate validation/normalization.
- app/Http/Controllers/Admin/SubmissionReviewController.php — authorized warnings và editor data.
- resources/views/components/{admin-feedback,submission-inline-edit,submission-review-form}.blade.php.
- resources/views/admin/submissions/{index,show}.blade.php.
- tests/Feature/RegistrationHttpConcurrencyTest.php.
- tests/Fixtures/approve_submission.php.

public/build được rebuild (ignored). Không chỉnh migrations, DealerDashboardData,
LeaderboardService, rejection action, Facebook verification/normalizer/index, public
registration views hoặc routes. Không ghi dữ liệu lịch sử ngoài fixtures test cách ly.

## Giới hạn và rủi ro còn lại

- Chưa audit database thực hoặc giải quyết collision lịch sử thật; command đã sẵn
  cho operator xác nhận chạy riêng. Không sửa, merge hoặc xóa dữ liệu để làm sạch.
- Approval/GET warning scan vẫn O(N). GET warnings chỉ giữ identity của trang và
  batch trong bộ nhớ, tránh O(N × page-size) scans; CLI exact group audit cần O(N)
  metadata bộ nhớ. Chạy audit off-peak và đo tài nguyên trên dataset lớn trước
  production VM 2GB; long read snapshot có thể tăng undo retention.
- Mutex serialize intake/identity edit/approval; chưa load-test production hoặc
  ép deadlock cycle MySQL thật. HTTP races nêu trên PASS, không bảo đảm mọi writer
  ngoài protocol hoặc mọi failure timing.
- Bulk SQL/Query Builder updates/inserts không qua model/domain actions, chưa có
  unique phone/plate constraint DB. Direct pending→approved model saves ngoài action
  không phải API approval được hỗ trợ; phải gọi ApproveCustomerSubmission. Existing
  model rejected→active save đã có guard, không có UI reactivation mới.
- Browser visual/screen-reader Admin mới chưa kiểm chứng; DOM/render/feature tests
  PASS không thay thế manual browser QA. D04 regression vẫn PASS, không warning mới.
- Các blockers release khác của 2C.3 (production data/demo, dependency audit có mạng,
  runtime/TLS/backup/restore/browser) không tự được đóng bởi bản sửa này.

Không commit, push, deploy, chạy migration production hoặc tự động sửa dữ liệu lịch sử.
