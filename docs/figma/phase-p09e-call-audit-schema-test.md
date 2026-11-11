# P09E — Kiểm thử A1 Schema trên toyota_testing

## Mục tiêu

Preflight local loopback/toyota_testing, sao lưu schema, kiểm SQL và chỉ áp dụng bốn migration A1 khi đủ điều kiện; chạy CallAuditSchemaTest không migrate/reset và không ghi vào các bảng nghiệp vụ cũ.

## Trạng thái

BLOCKED nghiệm thu đủ toàn bộ ca DML — 2026-11-11: preflight/backup/dry-run và cả bốn migration **PASS**, nhưng DB test không có bản ghi cha để chạy 8 ca còn lại mà không vượt phạm vi cấm ghi dữ liệu nghiệp vụ. Suite hiện **6 PASS, 8 SKIPPED**, không FAIL. Không đánh dấu DONE hoặc khẳng định đầy đủ runtime A1 đã pass. Người dùng đã cho phép DDL đúng bốn migration sau preflight/backup; không tự rollback/drop.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, tracking P09D, bốn migration, test/bootstrap/guard hiện có. Working tree có thay đổi nhiều phase chưa commit; giữ nguyên. Test A1 hiện tạo fixtures users/submissions/awards và thử delete actor; dù rollback, điều này vượt giới hạn P09E, phải thay bằng tham chiếu read-only tới records test có sẵn trước khi chạy. Chưa kết nối DB hoặc thực hiện DDL tại thời điểm tạo tracking.

## Các file source code liên quan

- Chỉ sửa `tests/Feature/CallAuditSchemaTest.php`; thêm `tests/Fixtures/call_audit_schema_check.php` và tracking này.
- Đọc/áp dụng nguyên trạng bốn migration `database/migrations/2026_11_11_000101_create_call_audit_batches_table.php` đến `000104_create_call_audit_events_table.php` theo thứ tự dependency.
- Dùng bootstrap `tests/CreatesApplication.php`, guard `tests/Support/TestDatabase.php`; không sửa chúng, models/services/policies/routes hoặc file cấu hình/.env/cache.

## Những thay đổi đã thực hiện

Đã thêm helper local-test-only cho preflight/prepare/apply/verify, guard effective config trước PDO và kiểm database/transport thực tế sau kết nối. Đã sao lưu schema, review 43 SQL statements chỉ CREATE/ALTER bốn bảng audit và áp dụng từng migration qua Laravel Migrator, dừng ngay khi có lỗi. Cả bốn thành công, không sửa source migration hoặc thực hiện down/drop.

Đã thay factory tạo users/submissions/awards trong test bằng đọc tham chiếu có sẵn, bỏ thao tác DELETE users thử FK. Thêm guard SQL trước thực thi: chỉ read hoặc INSERT/UPDATE/DELETE bốn bảng audit, không DDL/ghi bảng cũ. Test dùng transaction rollback cho dữ liệu audit, không Reset/RefreshDatabase/DatabaseMigrations. Thêm ca metadata/index/actor/ciphertext type, CHECK rejection và unknown-actor FK có thể chạy khi DB nghiệp vụ rỗng. Không hạ tiêu chí các ca cần tham chiếu hợp lệ; chúng SKIPPED với lý do rõ ràng.

## Quyết định kỹ thuật quan trọng

Mọi kết nối phải được guard effective config host loopback/database exact trước PDO, rồi kiểm SELECT DATABASE() và endpoint server. Nếu DDL lỗi, dừng ngay và chỉ đọc trạng thái từng bảng, không down/drop/rollback DDL. Không sử dụng RefreshDatabase/DatabaseMigrations, seed/reset hoặc full regression.

### Preflight và backup thực tế

- Lần đầu guard phát hiện config cache local đang trỏ database `toyota`, đã **từ chối trước khi mở PDO**. Dùng APP_CONFIG_CACHE trỏ một đường dẫn test không tồn tại cho riêng tiến trình để bỏ cache, không xóa/sửa cache/.env hoặc kết nối `toyota`.
- Effective DB_HOST=`127.0.0.1`, DB_PORT=`3306`, DB_DATABASE=`toyota_testing`; SELECT DATABASE()=`toyota_testing`, VERSION()=`8.0.30`, hostname=`OUSB8MG-PC`, PDO transport=`127.0.0.1 via TCP/IP`, foreign_key_checks=1, default engine InnoDB. Không URL/read-write/socket override.
- Trước DDL cả bốn bảng không tồn tại, không có dữ liệu audit cần giữ; parents users/dealers/customer_submissions/awards và migrations đều InnoDB. Active Admin=0, submission có Dealer=0, Award=0; không xuất PII.
- Backup schema-only trước DDL: `storage/app/private/p09e-call-audit-schema/20261111-080540-before-schema.sql`; SHA-256 `6d44f22417ccc8b997bf3934c0c455eb08fd695c06ae885bf55c65827934529f`.
- SQL dry-run: `storage/app/private/p09e-call-audit-schema/20261111-080540-a1-dry-run.sql`; snapshot/hash migration: `preflight.json` cùng thư mục; postflight: `verification.json`. Artifacts private/ignored, không commit backup SQL hoặc secrets. Backup không có dữ liệu khách và không tự restore.

### DDL và giới hạn chứng minh

| Bảng | Sau migration và sau tests | Repository batch |
|---|---|---|
| call_audit_batches | InnoDB, 0 rows | 5 |
| call_audit_items | InnoDB, 0 rows | 6 |
| call_audit_attempts | InnoDB, 0 rows | 7 |
| call_audit_events | InnoDB, 0 rows | 8 |

Chỉ bốn path A1 được đưa vào Migrator, thêm đúng bốn record bookkeeping trong bảng migrations hiện hữu. Không migrate toàn thư mục/install repository/DDL bảng cũ. Bộ đếm AUTO_INCREMENT của migrations tăng do bookkeeping; khi so cấu trúc repository chỉ chuẩn hóa bộ đếm này, không bỏ qua khác biệt cột/index/FK. Schema và dữ liệu 14 bảng cũ khác giữ nguyên qua SHOW CREATE hash, row count và CHECKSUM TABLE EXTENDED trước/sau; không có seed hoặc fixture nghiệp vụ mới.

Runtime xác nhận 39 constraints: 4 PK, 10 UNIQUE, 15 FK, 10 CHECK enforced. Các ca negative insert thực tế đã bị MySQL từ chối với CHECK error 3819 trên cả bốn bảng và FK error 1452 cho actor không tồn tại. Unique/index order, composite FK targets và restrictive rules đã kiểm từ information_schema. **Không** gọi việc có UNIQUE/FK trong metadata là duplicate/cross-item DML đã pass: các ca đó cần records cha hợp lệ và đang SKIPPED. LONGTEXT columns/actor NOT NULL đã kiểm thực; ciphertext DB round-trip chưa chạy, không thay bằng bằng chứng crypto unit lịch sử.

## Kết quả kiểm thử

| Kiểm tra vừa chạy | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_schema_check.php preflight` | PASS sau khi cô lập config cache; ban đầu guard chặn cached toyota trước PDO |
| `php tests/Fixtures/call_audit_schema_check.php prepare` | PASS schema backup/hash, snapshot 14 bảng cũ, 43 dry-run statements đúng scope |
| `php tests/Fixtures/call_audit_schema_check.php apply` | PASS bốn migration, không lỗi giữa chừng; không rollback/drop |
| `php vendor/bin/phpunit tests/Feature/CallAuditSchemaTest.php --no-progress --display-skipped` với env test/loopback và APP_CONFIG_CACHE riêng | Lần đầu 13 tests/184 assertions/8 skipped; bổ sung negative CHECK và SQL scope guard, lần cuối **14 tests, 198 assertions, 8 skipped = 6 PASS, 0 FAIL** |
| `php tests/Fixtures/call_audit_schema_check.php verify` | PASS: bốn bảng InnoDB/0 rows, legacy_changed=[], old_schema_changed=[]; bookkeeping 4 migration records |
| `php -l`, Pint/`--test` trên hai file test/helper, diff/whitespace và cấu trúc tracking | PASS; chỉ thêm helper/tracking và chỉnh CallAuditSchemaTest hiện hữu. Bốn migration A1 và source nghiệp vụ giữ nguyên; Git status không mất/thêm trạng thái file cũ |

Environment lệnh PHPUnit: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DATABASE_URL/DB_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE=`tests/Fixtures/.p09e-disabled-config-cache.php` (absolute path, không tồn tại). Không ghi các giá trị này vào .env/config. Mỗi test vẫn dùng bootstrap/guard hiện hữu và xác minh SELECT DATABASE().

Tám ca SKIPPED: ciphertext sau DB round-trip; duplicate business key/creator/request; overlap/duplicate item; combined count/purpose/claim DML với parent hợp lệ; valid underfill/zero insert; attempt duplicate; composite FK cross-item/batch DML; event duplicate/actor restriction với parent hợp lệ. Không thể chạy các ca này khi không có active Admin/submission/Award mà vẫn giữ lệnh cấm ghi bảng nghiệp vụ. Không disable foreign_key_checks hoặc thêm records giả để ép tests xanh.

## Những lỗi còn tồn tại

Chưa hoàn tất 8 ca cần parent fixtures; không được tuyên bố nghiệm thu toàn bộ A1. Không có migration/test FAIL còn treo, không có DDL partial failure cần phục hồi. Chưa test race nhiều connection/A2/A3/lấy mẫu/gọi điện/điểm/publication; ngoài phạm vi P09E. Không reset AUTO_INCREMENT các bảng audit sau negative insert, dù rows=0.

## Task hoặc bước tiếp theo

Giữ nguyên bốn bảng đã tạo và backup. Muốn nghiệm thu đủ 8 ca còn lại cần task riêng được phép cung cấp fixtures cha tối thiểu cách ly, hoặc bộ dữ liệu test tham chiếu đã có sẵn; hiện không tự tạo chúng vì yêu cầu P09E cấm ghi khách/Dealer/Sales/Award. Không tự migrate/reset lại hoặc mở nghiệp vụ ngoài A1. P09E thay kết luận NOT_RUN DDL của P09D bằng bằng chứng thực tế tại đây, giữ nguyên tài liệu lịch sử.
