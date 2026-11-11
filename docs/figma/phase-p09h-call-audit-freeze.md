# P09H — A3 Atomic Freeze Batch C08/C07

## Mục tiêu

Triển khai action atomic freeze và verifier từ dữ liệu đã lưu; gate tháng kết thúc/pending0, idempotency/generation1, mã hóa/snapshots/audit, kiểm thử test DB an toàn. Không UI/routes/calls/điểm/publication.

## Trạng thái

DONE phạm vi A3 — 2026-11-11: tests chọn lọc **84 PASS/1.332 assertions**, không SKIPPED/FAIL, gồm hai ca nhiều connection MySQL thật. Baseline V1 được người dùng được TMV ủy quyền phê duyệt. Dùng cutoff server tại thời điểm lấy mẫu, chỉ chứng minh review complete as-of, không tuyên bố tháng đóng vĩnh viễn.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09B mục9/P09C/P09D/P09F/P09G và source A1/A2/intake/approve/reject/edit/race harness. Working tree có nhiều phase chưa commit, giữ nguyên. Reject dùng submission row lock nhưng không intake mutex; freeze phải khóa nguồn ngoài mutex. A2 không persist và chưa có closure gate.

## Các file source code liên quan

- Mới `app/Actions/CallAudit/CreateAndFreezeBatch.php`.
- Mới `app/Services/CallAudit/FreezeClock.php`, `FreezeBlocked.php`, `FreezeResult.php`, `ReproductionVerifier.php` (cùng thư mục).
- Sửa `app/Policies/CallAuditBatchPolicy.php`, `tests/Unit/CallAuditBatchPolicyTest.php`: thêm riêng ability verifyReproduction; reproduce/viewSensitiveData/penalty/publish vẫn deny.
- Mới `tests/Feature/CallAuditFreezeTest.php`, `CallAuditFreezeConcurrencyTest.php`; `tests/Support/CallAuditFreezeFixtures.php`; `tests/Fixtures/call_audit_freeze_worker.php`, `call_audit_freeze_snapshot.php` và tracking này.
- Không sửa A1 models/migrations, A2 services, moderation, verification, LeaderboardService, AwardResults hoặc published snapshots.

## Những thay đổi đã thực hiện

Action nhận purpose/month/request key/Award ID, lấy actor từ Auth session, không nhận actor/cutoff/closed flag/seed/generation từ client. Transaction tự sở hữu khóa nguồn, kiểm gate, dùng A2 lập mẫu rồi persist encrypted batch/manifest/seed/baseline/review evidence, item snapshots và một freeze event. Receipt retry trả cùng batch trước khi clock/planner/seed được gọi; payload khác cùng key conflict, request khác cùng business generation bị chặn.

Verifier decrypt nội bộ và kiểm manifest/counts/strata/seed commitment/digests, selected IDs/order/attribution/source snapshots/fingerprints, timestamps/policies/actor và event integrity. Không đọc lại cohort/ranking live; response chỉ summary VERIFIED/BLOCKED, không seed/manifest/PII. Không có routes/UI/calls/scheduler/điểm/publication hoặc action generation2/void. Không chạy DDL/migrate/seed/reset.

## Quyết định kỹ thuật quan trọng

Gate V1 no-pending-as-of dưới intake mutex + khóa month rows/users/Dealer/Award; dynamic server cutoff, không deadline VN00/client boolean. Không period-close durable: backdated submissions sau commit cần xử lý ở flow hiện hữu, không tự vào batch đã freeze. A3 yêu cầu transaction top-level/RR hoặc Serializable để tránh snapshot cũ. Generation1 only; request hash là HMAC canonical intent, không phụ thuộc seed/live pool.

### Gate và chống race V1

- Lấy bounds monthly từ LeaderboardService hiện hành, không đổi timezone. FreezeClock đọc UTC_TIMESTAMP(6) của DB rồi đổi về app timezone cho storage convention hiện hữu; cutoff là instant server dưới khóa, không tự chọn VN00/grace deadline. Chặn tháng chưa kết thúc và reviewed_at null/future vượt cutoff.
- Trình tự khóa: intake_mutex1 → tất cả submissions trong khoảng tháng (mọi moderation status, order ID, FOR UPDATE) → users actor/Sales liên quan → Dealer lịch sử và Dealer hiện tại của Sales → Award nếu có → audit receipt/business key. Các read trước snapshot là locking current reads; A2 consistent reads chỉ bắt đầu sau khi khóa nguồn. Pending bất kỳ trong tháng, kể cả hồ sơ không vào mẫu, chặn freeze; không bỏ pending bằng demo/phone filter.
- Actor được đọc lại từ DB và authorize sau khóa, không tin trạng thái user object/session cũ. Row lock giữ actor active trong transaction. Actor created_by/frozen_by/event actor_id cùng người được Auth xác thực, không lấy từ payload.
- Dùng intake mutex tương thích gửi/approve/edit hiện hữu; row locks bổ sung bảo vệ Reject vốn không dùng mutex. Users/Dealer locks giữ role/names/attribution baseline ổn định. Quét/range lock có thể giữ phạm vi rộng trên MySQL; chưa benchmark production volume, tuyệt đối không giữ khóa trong lúc gọi điện.
- Sau A2, current-read lại toàn month source và fingerprint từng selected source; có thay đổi thì rollback/BLOCKED, không reroll. Snapshot selection/core data không sửa nguồn. Proof encrypted lưu policy, scope, row count, pending0, server cutoff, actor, mutex ID, review state HMAC và permanent_period_close=false.
- Bằng chứng này hợp lệ cho **as-of snapshot** dưới các khóa hiện hành, không là lệnh đóng kỳ vĩnh viễn. Hồ sơ gửi/duyệt muộn sau commit không tự vào manifest; hai purpose có thể cutoff khác nhau. Durable close/grace deadline/cấm backdate hoặc mở generation bổ sung vẫn **BLOCKED** nếu yêu cầu trong tương lai vì chưa có contract/storage/workflow đó; A3 không bypass bằng boolean.
- Action từ chối chạy trong outer transaction hoặc isolation ngoài REPEATABLE-READ/SERIALIZABLE; không giả định locking read sửa được snapshot đã mở trước đó. Isolation/engine prerequisite là MySQL InnoDB như preflight đã xác minh; không thay session/global timezone hoặc isolation bằng SET.

### Atomicity, idempotency, crypto và privacy

- Chỉ generation1. Unique scope/month/generation và creator/request key là guard DB cuối; mutex serialize creators. Same creator/key + same canonical HMAC intent trả receipt cũ, không clock/planner/seed mới; different payload → idempotency_payload_conflict. Khác key/Admin khi generation đã tồn tại → generation_already_frozen, không cấp alias receipt mới hoặc tự gen2.
- Batch/items/event chỉ commit cùng nhau; retry deadlock trước commit do Laravel transaction, không có batch preparing exposed. Exception trả mã safe, không response/log SQL/private exception. Freeze response chỉ batch ID/purpose/month/counts/created; ciphertext dùng encrypted casts A1 cho seed/manifest/baseline/source/Dealer/proof. Counts theo A2, empty vẫn frozen với 0 items, không completed/Đạt.
- Manifest thêm encrypted freeze_evidence/frozen_at vào versioned envelope, canonical manifest digest và selection digest; event có metadata không PII + HMAC trỏ manifest digest. Core snapshots bất biến, event append-only theo guards A1; không biến raw query builder hoặc DBA thành append-only tuyệt đối.
- Metadata key_id hiện gắn app key; phiên bản encryption laravel_encrypted_cast_v1. Verifier/retry fail closed khi key_id khác current, không coi key rotation là khách sai hoặc âm thầm rehash lịch sử. Restore/key rotation strategy cần task vận hành riêng; A3 không tạo key ring/config mới.
- Active Admin có capability verifyReproduction server-side, không có quyền lấy raw seed/manifest. Raw reproduce và viewSensitiveData vẫn deny; không mở correction, penalty, Manager hoặc publication. Verifier chỉ đọc auth User + bốn bảng audit cần thiết; tạo CustomerSubmission/Dealer trong memory từ encrypted snapshots để tính fingerprint, không query live các model đó.

### Harness an toàn

Action bắt buộc top-level và concurrency cần nhiều connection nhìn thấy fixture, nên tests dùng **committed fixtures + cleanup ID/tag riêng**, đúng quyền cleanup P09H, thay outer transaction của P09F/G. Fixture ID journal private được ghi trước commit; teardown chỉ xóa records thuộc run tag/IDs theo thứ tự FK, không truncate/drop/reset. Child late submission được thu lại theo tag + Dealer fixture sau khi worker đã dừng. Toàn bộ fixture journals được kiểm cleaned=true sau các lần chạy.

Không chạy harness approval cũ có DatabaseMigrations. Worker dùng bootstrap/guard toyota_testing, subprocess env có credentials dạng structured env không in ra log, endpoint cố định127.0.0.1:3306. Holder lấy mutex rồi đợi contender phát tín hiệu trước khi nhả; đo SQL lock wait thực, không coi hai action chạy tuần tự là concurrency.

## Kết quả kiểm thử

| Kiểm tra vừa thực hiện | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_freeze_snapshot.php before` | PASS actual127.0.0.1:3306/toyota_testing, MySQL8.0.30, fk_checks1; audit0 rows; snapshot19 bảng, hash193 source/migration được bảo vệ |
| `php vendor/bin/phpunit tests/Feature/CallAuditFreezeTest.php tests/Feature/CallAuditFreezeConcurrencyTest.php tests/Unit/CallAuditBatchPolicyTest.php tests/Unit/CallAuditSamplingAlgorithmTest.php tests/Feature/CallAuditSamplingTest.php --no-progress --display-skipped` | **84 PASS, 1.332 assertions**, 0 skipped/fail; chỉ suites A3/policy/A2 liên quan |
| Hai ca concurrency thực | PASS hai Admin một batch; contender BLOCKED/generation_already_frozen, SQL wait440.91ms. PASS late backdated insert; contender LATE_PENDING sau freeze, SQL wait433.41ms, batch vẫn1 item và verifier VERIFIED |
| `php tests/Fixtures/call_audit_freeze_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false |
| Syntax/Pint/diff/tracking | PASS đúng12 PHP mới/sửa và tracking; đủ9 mục. Git status chỉ thêm11 file mới, không mất/tăng trạng thái file cũ; policy/unit hiện có được sửa có chủ đích. Cảnh báo CRLF ở Blade phase cũ không thuộc P09H |

Các ca gồm general N0/1/59/60/61; từng Top3 N0/1/4/5/6 (N0 dùng approved phone-invalid giữ Top3 thật); month end/end-exclusive và pending ngoài tháng; pending→late approve trong cutoff; review timestamp future; từ chối outer transaction; retry sau commit/payload conflict/new request; source đổi trong operation bị abort; lỗi giữa ghi event rollback toàn batch/items; source/late approval sau freeze không sửa mẫu; inactive actor/frozen evidence/crypto/privacy; tampered item/order/manifest/seed/event bị verifier chặn. Reproduction query log không có customer_submissions/award_winners live.

Lần test đầu có2 lỗi mock harness cố mock final SamplingPlanner; đã thay bằng clock spy và beforeExecuting source-drift injection, không nới business code/A2 để mock được. Các lần kiểm tiếp PASS. Event failure injection chỉ trong test và được gỡ sau case; không để callbacks ảnh hưởng suite sau.

Env test: APP_ENV=testing; DB_CONNECTION=mysql; DB_HOST=127.0.0.1; DB_PORT=3306; DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing; DATABASE_URL/DB_URL/DB_SOCKET rỗng; APP_CONFIG_CACHE trỏ absolute `tests/Fixtures/.p09h-disabled-config-cache.php` không tồn tại, không sửa cache/.env. Không production, full regression, DDL/migrate/seed/reset.

Private evidence: `storage/app/private/p09h-call-audit-freeze/{before,after}.json`, `storage/app/private/p09h-fixtures/*.json`, `storage/app/private/p09h-races/*/results.json` và barrier files. Snapshot19 bảng so row counts/checksums/schema hash, chỉ normalize AUTO_INCREMENT vì commit/rollback fixtures làm counter tăng; không reset counter. Source193 file có sẵn giữ nguyên ngoài policy capability được phép; A1 schema/A2/scoring/verification/Award/AwardWinner không sửa. Data cha và audit rows sau cleanup khớp trước test.

## Những lỗi còn tồn tại

Không có test A3 liên quan còn FAIL hoặc concurrency NOT_RUN. Durable period close/grace deadline/generation2/void/correction/seed disclosure/key rotation orchestration vẫn chưa triển khai; không được suy từ server proof pending0. Chưa benchmark memory/range-lock contention ở volume production hoặc kiểm ứng dụng ngoài các suites chọn lọc. Không có UI/call/points/publication mới.

## Task hoặc bước tiếp theo

Review gate as-of V1 và API nội bộ, rồi task riêng API read/counts/C08/calls nếu được giao. Publication và xử lý điểm giữ riêng; không dùng FROZEN/VERIFIED hoặc pending0 as-of làm chứng chỉ Đạt/công bố/đóng kỳ vĩnh viễn.
