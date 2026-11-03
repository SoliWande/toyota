# Phase 2C.2 — Data integrity & concurrency

Ngày 03/11/2026. Đã đọc Phase 2B, Phase 2C.1 và code liên quan trước khi sửa:
SubmissionIntake, CustomerSubmission, draft/receipt/final/legacy controllers,
Sales submission controller, Admin update/approve/reject actions/requests,
exception handler, migrations, console kernel, demo/seeders và tests liên quan.

## Phạm vi dữ liệu và kiểm kê trước guard update

Preflight APP_ENV=testing, MySQL, DATABASE_URL rỗng và SELECT DATABASE() xác nhận
toyota_testing trước DB tests. Kiểm kê read-only tại đó: 0 submissions, 0 nhóm
phone trùng, 0 nhóm plate trùng trong pending/approved. Không in PII. Không đọc
hoặc sửa toyota/production; vì vậy **chưa kiểm kê được dữ liệu lịch sử thực**.
Không được suy luận từ database testing trống rằng production không có xung đột.
Fixtures lịch sử trùng được mô phỏng trong transaction rollback để bảo vệ hành vi.

| Writer đã audit | Trạng thái bảo vệ / thay đổi |
| --- | --- |
| Final Dealer hai bước | Mutex trước Dealer row, receipt và submission cùng transaction; recheck current draft/receipt; model insert guard |
| Legacy Dealer | Mutex trước Dealer row, model insert guard, phone toàn hệ thống; không thêm plate vào form cũ |
| Sales create | Nay lấy mutex trước User row; tránh thứ tự User → mutex ngược với Admin reassignment; guard insert; reuse evidence path khi transaction retry |
| Sales update | Nay mutex trước submission row; chặn phone/plate mới xung đột; map phone error sang customer_phone; evidence lỗi được dọn |
| Admin edit | GET_LOCK phone hiện có → mutex → submission → User khi reassignment; giữ version/audit; guard identity mới; không tự chuẩn hóa/backfill phone lịch sử khi chỉ sửa tên/địa chỉ |
| Approve / reject | Giữ row lock, pending-only, Facebook verification/unique approved identity và review transaction hiện có; không đổi rule xử lý lịch sử |
| Eloquent instance save/update/updateOrCreate/saveQuietly | performInsert/performUpdate bảo vệ insert và thay đổi identity vào pending/approved; model override vẫn chạy khi tắt events |
| Eloquent bulk update/upsert/insert, Query Builder, SQL/import ngoài model | Không đi qua model override, chưa có DB unique phone/plate constraint; không được coi là an toàn tự động |
| App Query Builder hiện hữu | Truy vấn submission trong app chủ yếu read/aggregate/audit fingerprint; không tìm thấy runtime bulk insert/update identity. Final dùng Query Builder insert receipt trong transaction |
| Demo/seeders | Tạo submissions bằng model và approval actions; chịu insert guard. Demo cleanup có delete dataset do manifest sở hữu; không chạy seeder/cleanup đó trong lượt này |
| Migrations | Chỉ đọc; không chạy hoặc sửa migration cũ. Không backfill/xóa dữ liệu lịch sử |

Guard update so sánh với row DB được lock/current-read, loại chính record khỏi
duplicate scan. Chỉ kiểm tra canonical phone/plate thực sự thay đổi, không kiểm
tra lại identity không liên quan. Rejected không chiếm identity trong scan.
Đổi formatting nhưng cùng canonical identity không tự biến thao tác thành lỗi
trùng lịch sử. Không tự sửa/xóa các row xung đột khác.

## Concurrency và transaction boundaries

SubmissionIntake::locked dùng một InnoDB mutex row SELECT FOR UPDATE, transaction
retry tối đa 3 lần. Nested savepoint giữ lock đến outermost commit. Scanner locking
read tránh snapshot cũ sau khi chờ transaction khác. Final/legacy/Sales/Admin edit
đều lấy mutex trước các row lock nghiệp vụ. Generic model callers phải giữ thứ tự
này nếu tự mở transaction/lock trước save; không thể bảo đảm cho SQL bên ngoài.

Receipt dùng HMAC session/Dealer/draft làm primary key. Final kiểm tra retry trước
validation mutable payload và kiểm tra lại receipt bên trong mutex. Submission và
receipt commit/rollback cùng nhau; draft chỉ consume sau commit, kiểm tra UUID để
không xóa draft mới của tab khác. Không đổi contract này.

### HTTP concurrency thực tế

Đã chạy tests/Feature/RegistrationHttpConcurrencyTest.php qua **hai PHP HTTP
server độc lập trên loopback**, cURL multi gửi đồng thời, hai DB connections,
file sessions/cookies đúng browser; cùng draft dùng bản sao cookie của cùng session.
Barrier trước kernel và giữ mutex 300 ms tạo cửa sổ tranh chấp thật.
Không dùng PHP built-in server đơn luồng duy nhất để giả lập concurrency.
CSRF middleware thật được ép bật trong test router (không dùng testing bypass),
request thiếu token kiểm chứng HTTP 419, request hợp lệ dùng token lấy từ GET form.
Router chỉ nằm tests/Fixtures, bắt buộc testing DB + test flag; không thêm public route.

| Case | Kết quả |
| --- | --- |
| Cùng phone, khác Dealer/session | Một pending, một receipt, không hai registrations |
| Cùng plate, khác phone | Một pending, một receipt |
| Cùng draft/session, hai requests | Cả hai redirect thành công, một submission/receipt |
| Retry sau commit | Redirect lại success, không thêm submission/receipt |
| Lỗi trước commit khi cạnh tranh | Fault injection sau INSERT receipt nhưng trước commit; request lỗi 500, transaction rollback; request hợp lệ còn lại tạo một submission/receipt |
| Lỗi trước commit rồi retry cùng draft | Sau lỗi: không submission/receipt; retry hợp lệ tạo đúng một submission |
| Legacy Dealer vs Sales HTTP đồng thời cùng phone | Một pending; không receipt (đúng hai flow cũ); request thua bị validation từ chối |

7 cases / 220 assertions PASS. Không có deadlock 500 ngoài lỗi cố ý inject.
Test riêng inject lỗi retryable deadlock tại transaction boundary xác nhận thử lại
3 lần và đóng transaction. Đây không phải test ép một chu trình deadlock MySQL thật.
Không tuyên bố an toàn tuyệt đối cho mọi workload hoặc writer SQL ngoài model.

Harness Windows ban đầu bị kẹt khi Symfony dừng server. Đã sửa cleanup process
bằng PID server do fixture ghi, chỉ dừng các process thử nghiệm do test tạo.
Không phải lỗi transaction ứng dụng. Các vòng chạy đầy đủ sau sửa PASS.

Cross-process tests cần fixtures commit để worker nhìn thấy; chúng chỉ tạo Dealer/
Sales/submission riêng trên toyota_testing và xóa đúng fixture sở hữu trong finally,
receipt trước submission để tuân thủ FK. Không chạy migration hoặc truncate.
Các DB feature tests còn lại dùng transaction/rollback. Nếu kill runner trước
finally có thể còn fixture testing; không tự dọn dữ liệu không xác minh ownership.

## Receipt cleanup

Command mới:

```powershell
php artisan dealer-registration:prune-receipts --dry-run --batch=500 --max-batches=20
# Chỉ thực hiện sau khi operator kiểm tra DB/retention và xác nhận:
php artisan dealer-registration:prune-receipts --batch=500 --max-batches=20
```

- Cutoff cố định lúc bắt đầu, chỉ expires_at <= cutoff; delete có predicate kiểm
  tra expiration lại để không xóa receipt đã được gia hạn trong lúc chạy.
- Batch 1–1000, max-batches 1–100; keyset pagination bằng draft_key, không offset.
  Output dry-run là số eligible trong giới hạn lượt chạy, không hứa tổng toàn bảng.
- Không xóa CustomerSubmission, moderation reviews hoặc lịch sử duyệt; chỉ bỏ
  child receipt. FK restrict delete không bị sửa.
- Log/output chỉ count và dry_run, không keys, ciphertext hoặc PII.
- Receipt có hiệu lực 24h; draft chỉ 30 phút và đã consume khi thành công. Xóa receipt
  hết hạn không mở lại draft cũ. Test xác nhận retry còn hiệu lực ở giờ 23, D04 và
  retry draft cũ bị từ chối ở giờ 25, không thêm submission.
- Schedule hourly + withoutOverlapping, nhưng **mặc định tắt** qua
  TOYOTA_RECEIPT_CLEANUP_ENABLED=false. Chỉ bật sau khi operator xác nhận.
- Chưa chạy cleanup thật trên toyota/production và chưa bật schedule. Command delete
  chỉ được thực thi trên fixtures toyota_testing trong transaction test rollback.
- Chưa có index expires_at; xem đề xuất hiệu năng bên dưới. Ciphertext expired có
  thể tồn tại đến lần cleanup được bật/chạy; TTL truy cập không phải xóa vật lý.

## Performance assessment

Benchmark synthetic trong một transaction toyota_testing, rollback toàn bộ sau đo;
median 3 lần scan không tìm thấy match, có mutex/current read, local không có tải:

| Active rows | Median scan |
| --- | --- |
| 100 | 0,77 ms |
| 1.000 | 3,55 ms |
| 5.000 | 14,71 ms |

EXPLAIN với 5.000 rows chọn PRIMARY range (id > cursor), Using where, ước tính
2.500 rows; đây là cardinality estimate, không phải số row thực đo. lazyById(500)
giới hạn bộ nhớ, nhưng tổng scan vẫn O(N) và chuẩn hóa ở PHP. Index phone_normalized
hiện có không xử lý được mọi raw format lịch sử; không thay bằng so sánh exact đơn
thuần làm bỏ sót. Global mutex serialize mọi intake và identity edit, tăng thời gian
chờ; Sales evidence I/O hiện còn nằm trong critical section. Các số trên không
đại diện throughput production, chưa benchmark hàng triệu records/p95 dưới tải.

Receipt hiện có PK draft_key, indexes owner_key và submission_id; không expires_at.
Cleanup keyset không tăng offset nhưng vẫn phải lọc expiration qua nhiều rows.
Đề xuất migration riêng, **chưa tạo/chạy**: index (expires_at, draft_key), đối chiếu
EXPLAIN và lock/DDL impact trước áp dụng. Không thêm unique phone/plate vào lịch sử.
Ở quy mô lớn cần thiết kế normalized identity registry + kiểm kê collision lịch sử,
cutover/backfill có kiểm soát và chính sách grandfathering rõ ràng; không gộp thay
đổi schema lớn vào bản sửa này.

## Files và kiểm tra

Chỉnh:
- app/Services/SubmissionIntake.php
- app/Models/CustomerSubmission.php
- app/Actions/UpdateCustomerSubmission.php
- app/Http/Controllers/Sales/SubmissionController.php
- app/Console/Kernel.php
- config/dealer-registration.php
- tests/Feature/RegistrationBusinessIntegrationTest.php
- tests/Feature/DealerRegistrationFinalTest.php

Tạo:
- app/Console/Commands/PruneDealerRegistrationReceipts.php
- tests/Feature/SubmissionIntegrityUpdateTest.php
- tests/Feature/SubmissionIntakeRetryTest.php
- tests/Feature/RegistrationReceiptCleanupTest.php
- tests/Feature/RegistrationHttpConcurrencyTest.php
- tests/Fixtures/registration_http_router.php
- docs/figma/phase-2c-2-data-integrity.md

Suite chọn lọc gồm HTTPConcurrency, IntegrityUpdate, IntakeRetry, ReceiptCleanup,
BusinessIntegration, Final, Draft, SuccessPresentation, StepTwoValidation,
DraftStorage, IntakeNormalization: **82 tests / 1.157 assertions PASS** trong
46,054 giây ở lượt chạy đầy đủ. Pint PASS cho tất cả PHP đã chỉnh/tạo.
Không chạy suite cũ có RefreshDatabase/DatabaseMigrations. Không disable CSRF,
không commit, push hoặc deploy.

## Quyết định còn cần xác nhận / giới hạn

Đã gửi câu hỏi xác nhận chính sách lịch sử; chưa nhận trả lời tại thời điểm viết:

1. Giữ sửa trường không liên quan và pending → approved khi identity lịch sử không
   đổi; chặn identity mới xung đột; rejected → pending/approved phải kiểm tra lại?
   Hay chặn cả duyệt dữ liệu lịch sử đã trùng? Không tự thay rule duyệt hiện có.
2. **Chưa mở rộng guard cho status-only rejected → active** vì chưa có flow này
   trong actions hiện tại (approve/reject đều pending-only) và cần xác nhận chính
   sách tái kích hoạt lịch sử. Direct model status save có thể vượt reservation
   nếu không đổi identity. Admin edit rejected vẫn giữ phone check cũ, chưa tự nới
   behavior cho trường hợp đổi sang phone đang active.
3. SQL/Query Builder bulk identity writes chưa bị chặn ở DB; các writer mới phải
   đi qua guarded instance save và lấy mutex trước mọi business row lock. Muốn
   bao phủ SQL/import tùy ý cần thiết kế registry/constraint đã kiểm kê lịch sử.
4. Chưa kiểm kê dữ liệu thực, chưa thử crash/kill giữa commit và consume, chưa ép
   deadlock cycle thực, chưa load-test multi-node hoặc xác minh scheduler production.
5. Demo approved vẫn tính điểm nếu trộn DB (rủi ro Phase 2C.1), ngoài thay đổi này.

Vì các điểm 1–3, không coi bản này là bảo đảm uniqueness cho mọi thao tác trên
toàn bộ vòng đời dữ liệu. Các flow đã bảo vệ và kết quả HTTP được tách rõ bên trên.
