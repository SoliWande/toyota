# P09F — Hoàn tất schema tests A1 với fixtures transaction

## Mục tiêu

Chạy đủ 14 CallAuditSchemaTest, không skip, bằng fixtures cha tối thiểu trong transaction trên toyota_testing; không DDL/reset hoặc sửa nghiệp vụ, đối chiếu dữ liệu/schema trước và sau.

## Trạng thái

DONE — 2026-11-11. **14/14 tests PASS, 322 assertions, 0 SKIPPED/FAIL**. Người dùng đã cho phép fixtures cha trong transaction rollback; không thay schema/constraints để ép pass. Postflight xác nhận không còn dữ liệu fixture và source nghiệp vụ/migrations giữ nguyên.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, P09E, test/bootstrap/guard, factories và model guards liên quan. P09E có 14 tests với 6 PASS/8 SKIPPED vì cấm fixtures cha. Working tree có các task cũ chưa commit; giữ nguyên. Tracking này tiếp nối phạm vi mới, không sửa kết quả lịch sử P09E.

## Các file source code liên quan

- Sửa `tests/Feature/CallAuditSchemaTest.php`.
- Thêm `tests/Fixtures/call_audit_fixture_snapshot.php` và tracking P09F này.
- `tests/CreatesApplication.php`, `tests/Support/TestDatabase.php`, models/factories/migrations chỉ đọc, không sửa. Helper/tracking P09E giữ nguyên.

## Những thay đổi đã thực hiện

Đã thay phụ thuộc vào parent có sẵn bằng fixtures lazy: Admin khi cần batch, Admin thứ hai khi kiểm creator khác/restrictive event actor, Dealer/CustomerSubmission QR khi cần item, Award monthly chỉ cho case hai purpose. Không tạo Sales hoặc fixtures cho các ca metadata/negative checks không cần parent.

SQL guard chỉ cho parent INSERT trong helper fixture và khi outer transaction đang mở; không UPDATE dữ liệu cha, DELETE chỉ cho ID Admin do test tạo để kiểm FK từ chối. Cho SAVEPOINT của intake guard trong transaction; DDL/commit bằng SQL và ghi bảng ngoài scope đều bị chặn. Tất cả fixtures được rollback trong tearDown, không dùng trait tự migrate/reset. Thiếu schema nay là lỗi precondition, không skip để che thiếu nghiệm thu.

Ca restrictive FK tạo actor chỉ được event tham chiếu và kiểm tên constraint `call_audit_events_actor_id_foreign` với error1451; tránh pass do FK reviewer của CustomerSubmission. Bổ sung helper read-only chụp/đối chiếu 19 bảng và hash 187 file app/migrations trước/sau, không tự sửa/xóa/reset dữ liệu.

## Quyết định kỹ thuật quan trọng

Chỉ cho INSERT fixtures users/dealers/customer_submissions/awards trong transaction, không ghi bản ghi cũ; CustomerSubmission dùng nguồn dealer_qr để không tạo Sales ngoài phạm vi. Mọi audit fixture/parent phải rollback. Không sửa migrations, disable FK/CHECK, seed/reset hoặc tạo dữ liệu production. AUTO_INCREMENT có thể tăng dù rollback; không reset counter bằng DDL.

- Preflight effective/actual host `127.0.0.1`, port3306, SELECT DATABASE()=`toyota_testing`, MySQL8.0.30, PDO transport `127.0.0.1 via TCP/IP`, foreign_key_checks=1. Bốn audit và các parent fixture đều InnoDB; audit đều0 rows trước/sau.
- Dùng APP_CONFIG_CACHE trỏ `tests/Fixtures/.p09f-disabled-config-cache.php` không tồn tại cho riêng tiến trình, tránh cache local cũ trỏ toyota. Giữ bootstrap/guard hiện hữu, kiểm exact host/db, không URL/socket/read-write override; không sửa .env/cache hoặc kết nối production.
- Approved QR fixture đi qua model/intake guards hiện có, reviewed_by là Admin fixture; có fields QR/consent/phone/Facebook hợp lệ về cấu trúc, không gọi action approval/verification/scoring hoặc tạo lịch sử nghiệp vụ thật. Dữ liệu giả chỉ trong transaction test được phép, không tồn tại sau test.
- So schema chuẩn hóa **chỉ** AUTO_INCREMENT counter vì MySQL không rollback sequence. Dữ liệu row count/CHECKSUM vẫn so nguyên vẹn; columns/index/FK/CHECK không được bỏ qua. Không hứa counter giữ nguyên hoặc ALTER TABLE reset chúng.

## Kết quả kiểm thử

| Kiểm tra vừa chạy | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_schema_check.php preflight` (chỉ mode đọc) và `php tests/Fixtures/call_audit_fixture_snapshot.php before` | PASS loopback/toyota_testing, bốn bảng tồn tại/0 rows; snapshot19 bảng và187 source files |
| `php vendor/bin/phpunit tests/Feature/CallAuditSchemaTest.php --no-progress --display-skipped` với env test riêng | Lần đầu14 PASS/320 assertions; sau khi làm rõ FK event actor, lần cuối **14 PASS/322 assertions, không SKIPPED/FAIL** |
| `php tests/Fixtures/call_audit_fixture_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false |
| `php -l` hai file test/helper, Pint và `pint --test`, diff/whitespace/tracking | PASS. Đủ9 mục tracking; chỉ sửa test, thêm helper snapshot và tracking. Git status chỉ thêm hai file mới, không mất/tăng trạng thái các file cũ; cảnh báo CRLF từ các file phase cũ không thuộc thay đổi P09F |

Đã thực thi đủ các ca: unique purpose/month/generation và create request; item/attempt/event duplicates; composite FK cross-batch/item; CHECK invalid counts/purpose/claim; ciphertext seed/manifest DB round-trip và hidden serialization; valid underfill/zero; restrictive actor FK. Không thay constraints hoặc schema khi tests chạy.

Lệnh PHPUnit dùng APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing; DATABASE_URL/DB_URL/DB_SOCKET rỗng; APP_CONFIG_CACHE là absolute path không tồn tại nêu trên. Không chạy full regression, migrate/DDL, seed/reset hoặc chuẩn bị schema.

Bằng chứng private/ignored: `storage/app/private/p09f-call-audit-schema/before.json` và `after.json`, gồm identity, engine/row count/checksum/schema hash mỗi bảng và source hashes, không raw PII/credentials. So sánh covers cả migrations repository, mọi parent, history/audit cũ và bốn bảng mới; không có rows fixture nào còn lại.

## Những lỗi còn tồn tại

Không phát hiện lỗi schema hoặc test liên quan còn tồn tại trong phạm vi14 ca. Không có source nghiệp vụ/migration thay đổi, không cần sửa schema. AUTO_INCREMENT có thể tiến lên do fixtures/negative inserts và được giữ nguyên, không reset. Kết quả này không nghiệm thu sampler/A2/A3, concurrency nhiều connection, calls/scoring/publication hoặc UI.

## Task hoặc bước tiếp theo

Có thể dùng bằng chứng14/14 này để review nghiệm thu A1 và giao A2/A3 riêng. P09F giải quyết8 ca SKIPPED của P09E nhờ quyền fixtures mới, không sửa hoặc phủ nhận giới hạn lịch sử P09E. Giữ nguyên schema và dữ liệu sau rollback; không tự triển khai lấy mẫu/gọi điện/điểm/công bố.
