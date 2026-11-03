# Phase 2C.4B — Demo isolation & identity write audit

Ngày 03/11/2026. Đã đọc các báo cáo Phase 2C.1, 2C.2, 2C.3, 2C.4A và đối chiếu
source, migrations, demo service/seeders/cleanup, controllers, model, actions,
aggregate services, Artisan commands, scheduler và tests. Source là căn cứ chính.

## Kết luận và phạm vi chắc chắn

Đã cách ly **dataset ToyotaRealisticDemo có manifest xác minh được** khỏi các
submission metrics và điểm tính mới. Không đổi submission status, không xóa demo,
không sửa dữ liệu thật hoặc viết lại award snapshots. Không thể xác nhận toàn bộ
demo lịch sử đã được cách ly: DevelopmentDemoSeeder và Phase2A demo không có
manifest sở hữu đủ tin cậy; award snapshots demo cũ cũng chưa có marker.
Đây vẫn là **blocker trước release trên database có demo không phân loại được**.

Không kết nối/kiểm kê toyota hoặc production. Không suy luận dữ liệu thật từ test DB.
Không chạy migration, migrate:fresh hoặc RefreshDatabase. Seeder/cleanup realistic
chỉ được chạy trong transaction fixtures trên toyota_testing rồi rollback.

## Demo detection strategy

Lỗi thực tế: ToyotaRealisticDemo::EMAIL trước đây là dev-admin@toyota.test, trong
khi user() tạo toyota.realistic.v1.admin@example.com. Status/reseed/cleanup không
tìm đúng manifest. Đã sửa constant cho khớp nguồn tạo; không cập nhật email trong DB.

Email này chỉ là địa chỉ tìm envelope. **Email, tên, notes, tiền tố plate/phone
không đủ để loại một submission.** Quyền sở hữu lấy từ danh sách 1.500 IDs trong
manifest và bằng chứng toàn vẹn sau:

- Seed mới ghi thêm ownership envelope được Laravel Crypt mã hóa/xác thực bằng
  APP_KEY. Nội dung gồm dataset, database name, admin ID và danh sách submission IDs.
  Envelope được tạo cùng transaction seed, không có raw PII, không đặt trong URL.
  Khi đọc phải giải mã và so khớp toàn bộ nội dung; IDs phải là 1.500 số nguyên
  dương phân biệt. Sửa nội dung khách hàng/notes không làm mất ownership này.
- Manifest cũ chưa có ownership: chỉ chấp nhận khi fingerprint toàn bộ users,
  submissions và reviews khớp chính xác với fingerprint seeder đã ghi. Không ký lại,
  backfill hoặc sửa manifest lịch sử khi đọc. Fingerprint lệch sau chỉnh sửa hợp lệ
  cũng cần operator xác minh; không tự suy đoán phần còn lại.
- Có reserved manifest account nhưng manifest sai/mất/tampered/không xác minh được:
  chặn truy vấn metrics bằng lỗi generic, không âm thầm tính demo hoặc loại dữ liệu
  thật. Hiện có thể dẫn tới HTTP 500 trên trang dùng metrics; cần xử lý ownership
  trước release, không dùng chế độ demo để né kiểm tra trên môi trường thật.
- Không có manifest account: không tự loại row nào. Điều này **không chứng minh
  database không có demo**; demo khác/manifest bị mất phải được audit riêng.

Manifest nằm ở admin_note của account demo vốn dùng để lưu manifest; không tìm kiếm
notes tự do để phân loại. Ownership mới chịu vòng đời APP_KEY và database name:
đổi tên database/rotation key cần kế hoạch riêng và giữ previous keys thích hợp.
Không có thao tác ký lại tự động hoặc bypass khi token không hợp lệ.

## Các aggregate đã áp dụng

DemoSubmissionScope dùng whereNotIn đúng IDs đã xác minh, không global scope model.

| Aggregate | Hành vi |
| --- | --- |
| LeaderboardService Sales/Dealer tuần/tháng/all-time/range | Loại demo đã xác minh trước group/rank, giữ attribution và time boundaries |
| Landing page, public Dealer directory/profile, Admin top Sales/Dealer | Kế thừa các query LeaderboardService |
| DealerDashboardData::standings | Kế thừa leaderboard; không đổi customer authorization/query |
| SalesPublicProfile | Approved total và 3 tháng cùng bộ lọc |
| Admin dashboard | Submission total/approved/pending/week/month dùng bộ lọc |
| Sales dashboard | Submission counts theo status dùng bộ lọc, rank theo leaderboard |
| Dealer dashboard | Customer count dùng bộ lọc |
| Admin Dealer detail | Approved customer count dùng bộ lọc |
| AwardResults::preview | Monthly/weekly results mới kế thừa range query đã lọc |

Giữ operational lists/review queues/detail/history/recent rows để Admin/Dealer/Sales
vẫn kiểm tra được demo. Do đó tổng metric có thể khác tổng record trong danh sách
nghiệp vụ. User/Dealer account counts là inventory, không phải submission score,
không loại bằng email/tên. Không lọc demo khỏi duplicate detection: pending/approved
demo vẫn chiếm identity, rejected vẫn không chiếm, theo business rule hiện tại.

Published Award/AwardWinner snapshots, PublicAwardResults và DealerDashboardData::awards
không được tính lại hoặc đoán từ title/winner account. DevelopmentDemoSeeder có thể
đã xuất bản demo awards; đây là phần chưa cách ly và cần quyết định/marker riêng.

## Chế độ demo có cấu hình

Mặc định TOYOTA_DEMO_INCLUDE_IN_METRICS=false, độc lập với quyền chạy seeder.
Trên installation demo cách ly, operator có thể cấu hình:

```dotenv
TOYOTA_DEMO_ENABLED=true
TOYOTA_DEMO_INCLUDE_IN_METRICS=true
```

Chỉ local/testing hoặc production demo có thêm TOYOTA_DEMO_ALLOW_PRODUCTION=true
được dùng chế độ này. Cấu hình include nhưng chưa enable/môi trường không hợp lệ
bị từ chối. Chế độ demo bao gồm toàn bộ rows trong metrics, không sửa DB/status.
Không bật trên production thực. .env.example bổ sung defaults an toàn; không sửa .env.

## Identity writer audit

Đã tìm CustomerSubmission/customer_submissions và thao tác insert/update/upsert/
raw SQL/save trong app, database, routes, scripts có trong repo và .laravel-tools.
Không tìm thấy import runtime hoặc scheduled job ghi submission identity.

| Writer/path | Kết quả |
| --- | --- |
| Final Dealer | Intake mutex → Dealer row → model insert + receipt trong transaction; không sửa |
| Legacy Dealer | Intake mutex → Dealer row → model insert; không sửa/form plate mới |
| Sales create | Intake mutex → User row → model save; không sửa |
| Sales edit | Intake mutex → submission row → instance save, giữ authorization/evidence; không sửa |
| Admin edit | Named phone lock → intake mutex → submission → User khi cần; version/audit và instance guard giữ nguyên |
| Admin approve | Mutex → submission → canonical duplicate check → approved/review; giữ nguyên Phase 2C.4A |
| Reject / Sales delete | Chỉ giải phóng identity; row transaction hiện có, không reverse dependency lên mutex |
| Eloquent instance save/update/create/updateOrCreate/saveQuietly | performInsert/performUpdate vẫn là guard; quiet save không bỏ model override |
| ToyotaRealisticDemo seed | Nay mutex trước Dealer rows, sau đó model/action; ownership ghi cùng transaction |
| DevelopmentDemoSeeder / Phase2A demo seeder | Nay mutex trước business rows; vẫn tạo submission bằng model, không đổi status fixtures hoặc import policy |
| Realistic cleanup | Nay mutex trước Dealer rows; giữ fingerprint/external-reference refusal, chỉ fixture cleanup đã chạy |
| Query Builder hiện hữu | Submission queries là reads/aggregate/collision/fingerprint; insert receipt không ghi identity |
| Migrations | DDL/index/checks và attribution/slug backfill lịch sử, không thêm/chạy migration trong lượt này |

Khe hở có bằng chứng đã sửa: model insert trước đây chỉ kiểm tra raw phone và plate,
bỏ qua phone_normalized của row mới nếu raw phone null/khác alias. Nay kiểm tra cả
hai bằng conflicts() trong cùng mutex/connection. Không đổi normalization hoặc rule
pending/approved/rejected. Regression có insert alias và saveQuietly update.

Eloquent bulk update/insert/upsert, toBase()/Query Builder, raw SQL và writer ngoài
repo vẫn **không tự được model guard bảo vệ**. Không tìm thấy runtime writer kiểu
đó cần chuyển trong source đã audit. Không tuyên bố DB tự cấm mọi bypass; chưa có
unique canonical phone/plate constraint. Import/bulk bên ngoài chưa được operator
xác nhận là blocker nếu sẽ sử dụng; phải đi qua guarded service/mutex và có tests
trước khi đưa vào runtime. Không thêm constraint trên dữ liệu lịch sử.

## Rejected reactivation

Không có route/UI Admin approve rejected hoặc reactivation mới. Approve action chỉ
pending; request edit không nhận status. Code path model forceFill(status)->save
từ rejected sang pending/approved đã có full identity check dưới mutex từ 2C.4A.
Giữ nguyên và chạy lại regression: collision bị chặn, không collision vẫn hợp lệ;
không thêm authorization/audit shortcut hoặc feature reactivation.

## Tests và kết quả

Preflight ép APP_ENV=testing, MySQL, DATABASE_URL rỗng, xác minh config và
SELECT DATABASE() thực tế là toyota_testing trước khi chạy. Fixture test mới cũng
xác minh tên DB trước begin transaction. Không chạy tests dùng RefreshDatabase.

Kết quả cuối: **235 tests / 1.989 assertions PASS**, 1 phút 43,671 giây, peak 98 MB.

```powershell
php vendor/bin/phpunit tests/Feature/DemoIsolationTest.php tests/Feature/ToyotaRealisticDemoSeederTest.php tests/Feature/ControlledDuplicateApprovalTest.php tests/Feature/RegistrationHttpConcurrencyTest.php tests/Feature/SubmissionIntegrityUpdateTest.php tests/Feature/SubmissionIntakeRetryTest.php tests/Feature/RegistrationReceiptCleanupTest.php tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/DealerRegistrationStepTwoValidationTest.php tests/Unit
php vendor/bin/pint --test
```

Pint toàn repo PASS. Không đổi frontend assets; không chạy lại build trong lượt này.
git diff --check PASS. Kiểm tra read-only sau tests trên toyota_testing: submissions,
receipts, moderation_reviews, users và dealers đều 0 rows; fixtures đã rollback/cleanup.

- DemoIsolationTest: 9 cases cho real/demo cùng Sales/Dealer, arbitrary tag không
  loại nhầm real, metrics Admin/Dealer/Sales/public/monthly award preview nhất quán,
  demo mode, tampered/unsigned unverifiable manifests, ownership sau edit,
  identity occupancy độc lập metrics và normalized alias/quiet save guards.
- ToyotaRealisticDemoSeederTest: 6 cases transaction rollback; thực tạo dataset
  1.500 rows trên testing, kiểm tra signed và legacy fingerprint ownership,
  demo metrics 6 tháng, idempotent reseed, cleanup chỉ owned fixture, cleanup từ chối
  row đã sửa trong khi signed ownership vẫn lọc được. Không cleanup dữ liệu local.
- Sửa setup snapshot test reserved account: refresh DB defaults trước snapshot;
  trước đó so object mới tạo với row đầy đủ gây false failure. Không nới assertion
  bảo toàn account; so toàn raw snapshot bằng boolean để không dump account data.
- Regression Phase 2A/B, Admin approval/edit, Sales/legacy, leaderboard/unit và
  10 HTTP concurrency cases hai server/session/connection PASS. Không disable CSRF.
- Chưa chạy DevelopmentDemoSeederTest vì dùng RefreshDatabase; Phase2A demo seeder
  khóa cứng local toyota nên không gọi trên testing. Hai thay đổi lock ordering
  ở seeders đó được source audit/Pint, chưa có full seeder runtime verification.

## Dữ liệu chưa thể phân loại và đề xuất additive riêng

1. DevelopmentDemoSeeder: 500 submissions, không ID manifest; names/email/notes và
   synthetic formats không đủ chứng minh ownership của dữ liệu hiện tại.
2. DealerRegistrationPhase2aDemoSeeder: 6 submissions và file credentials chỉ chứa
   tài khoản/secret, không submission ID ownership manifest. Không đọc secret hoặc
   dùng credentials như classifier.
3. Realistic manifest unsigned đã sửa/mất, demo/import thủ công, dataset khác không
   biết nguồn: không tự đoán, không backfill và không sửa thật.
4. Published demo awards: chưa có dataset marker đáng tin cậy, không lọc title.

Đề xuất **chưa tạo/chạy migration**: bảng demo_datasets + mapping submission_id
duy nhất sang dataset_id, mapping award_id riêng cho snapshots demo; registry lưu
provenance/version, operator phê duyệt và audit. Hoặc nullable demo_dataset_id trên
submissions/awards nếu review schema chọn phương án đó. Default null không chứng
minh real cho rows lịch sử chưa audit. Marker chỉ được viết server-side trong cùng
transaction seeder, không mass-assign từ khách; không dùng unique phone/plate.
Backfill chỉ bằng danh sách IDs đã được operator xác minh với backup/rollback plan,
không dùng heuristics. Giữ điểm/status lịch sử, phân biệt việc lọc hiển thị và sửa DB.

Operator cần xác nhận database audit, provenance của các dataset/award cũ, marker
schema và cách xử lý historical published snapshots. Chưa có quyết định nghiệp vụ
cho việc tự sửa snapshot; không triển khai vượt phạm vi. Trước đó không thể đánh
dấu demo-isolation production là PASS toàn diện.

NOT IN 1.500 IDs là giải pháp giới hạn dataset hiện hữu; manifest unsigned cần hash
toàn dataset mỗi query và không cache dài hạn ownership chưa xác minh. Chưa load-test
production; mapping có index phù hợp hơn khi nhiều datasets/lớn. Mutex seed dài
có thể chặn intake, chỉ seed demo cách ly/off-peak sau operator review.

## Files thay đổi riêng lượt này

- .env.example; config/realistic_demo.php.
- app/Services/DemoSubmissionScope.php (mới).
- app/Services/ToyotaRealisticDemo.php, LeaderboardService.php, SalesPublicProfile.php.
- app/Models/CustomerSubmission.php — chỉ bổ sung insert alias check so với 2C.4A.
- app/Http/Controllers/Admin/DashboardController.php, Admin/DealerController.php,
  Dealer/DashboardController.php, Sales/DashboardController.php.
- app/Console/Commands/ToyotaDemoCommand.php — cập nhật warning theo metrics mode.
- database/seeders/DevelopmentDemoSeeder.php, DealerRegistrationPhase2aDemoSeeder.php.
- tests/Feature/DemoIsolationTest.php (mới), ToyotaRealisticDemoSeederTest.php,
  RegistrationBusinessIntegrationTest.php (đổi tên characterization thành guard
  chống loại nhầm từ arbitrary note; vẫn giữ assertion real counts).
- docs/figma/phase-2c-4b-demo-isolation.md (báo cáo này).

Working tree đã có thay đổi các phase trước; danh sách trên chỉ là phần lượt này.
Không commit, push, deploy, tạo migration, tự gắn marker hoặc thay đổi production data.
