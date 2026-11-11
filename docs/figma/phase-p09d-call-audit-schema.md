# P09D — A1 Schema/Models C08/C07

## Mục tiêu

Triển khai source migrations bốn bảng call audit, bốn Eloquent models và policy Admin để review. Không triển khai lấy mẫu, actions gọi, routes/UI, scoring hoặc công bố. Không chạy DDL trong task này.

## Trạng thái

DONE phạm vi source A1 để review — 2026-11-11: syntax/Pint/unit không DB/diff PASS. **DDL và test schema runtime: NOT_RUN**, không có tuyên bố schema đã hoạt động. Baseline UC01–UC10/V1 được người dùng có toàn quyền TMV ủy quyền phê duyệt theo yêu cầu P09D. Không tiếp tục chặn vì thiếu chữ ký riêng; quyền thực hiện DDL vẫn chưa được cấp trong A1.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, mục 9 P09B và P09C, models/policies/migrations/test guard hiện có. Working tree có nhiều thay đổi task trước; giữ nguyên. Chưa có bốn bảng/models audit trong source trước A1. Runtime schema/isolation chưa xác minh; không đọc .env hoặc kết nối DB.

## Các file source code liên quan

Các file mới:

- `database/migrations/2026_11_11_000101_create_call_audit_batches_table.php`
- `database/migrations/2026_11_11_000102_create_call_audit_items_table.php`
- `database/migrations/2026_11_11_000103_create_call_audit_attempts_table.php`
- `database/migrations/2026_11_11_000104_create_call_audit_events_table.php`
- `app/Models/CallAuditBatch.php`, `app/Models/CallAuditItem.php`, `app/Models/CallAuditAttempt.php`, `app/Models/CallAuditEvent.php`
- `app/Policies/CallAuditBatchPolicy.php`
- `tests/Unit/CallAuditBatchPolicyTest.php`, `tests/Unit/CallAuditModelTest.php`
- `tests/Feature/CallAuditSchemaTest.php`
- Tracking: `docs/figma/phase-p09d-call-audit-schema.md`.

Các source CustomerSubmission/Approval/SubmissionVerification/LeaderboardService/AwardResults/publication chỉ đọc, không sửa. Đọc thêm models Award/AwardWinner/User, AwardPolicy, AuthServiceProvider, Laravel Gate/HasAttributes/Schema grammar, migrations hiện hữu, test guard và harness để theo convention.

## Những thay đổi đã thực hiện

Đã thêm bốn migration InnoDB theo dependency batches → items → attempts → events; monthly scope, purpose, counts/actor/FK, metadata encryption, frozen source/Dealer/baseline/manifest và seed; claim/retry/version fields dùng cho giai đoạn sau. Không có sampler, crypto service/key provisioning, record-call action, scheduler, routes hoặc UI mới.

Bốn models guarded mass assignment, encrypted casts cho nội dung private, hidden serialization; guards chặn thay frozen content và xóa batch/item; attempt/event append-only qua model lifecycle. Giữ projections queue/claim/lifecycle có thể cập nhật bằng action tương lai, không tạo action trong A1. Relations theo submission/batch/item/attempt và actor riêng, không sửa models hiện hữu.

Policy tự được Laravel khám phá theo convention tên, có unit test qua Gate. Mọi active Admin được read/create/freeze/claim/recordAttempt, không giới hạn creator. Các ability đó là permission, chưa có executable workflow. Default deny disclosure PII/seed/reproduce, correction/update/delete/void/regenerate, score adjustment và publish; không đổi AwardPolicy hoặc mở quyền Manager.

## Quyết định kỹ thuật quan trọng

- P09D thay gate thiếu chữ ký TMV ở P09B/P09C bằng quyền quyết định được người dùng xác nhận; giữ nguyên hai tài liệu lịch sử.
- A1 chỉ có source schema/models/policy. Không tự chạy migrate, seed, reset hoặc tests dùng trait tự chuẩn bị schema.
- Cutoff/privacy/retention/generation chưa chốt dùng metadata/version và quyền mặc định hạn chế; không tự thêm lịch xóa, cutoff mẫu, penalty hoặc publication.

### Constraints K01–K08 đã đưa vào source

| Review | Source thực hiện và giới hạn |
|---|---|
| K01 | Unique `(purpose,monthly_scope_key,generation)` non-null, scope ASCII case-sensitive; actor/seed/fingerprint không vào key. Scope format YYYY-MM, monthly only, end>start; validate canonical bounds/Award cùng tháng còn thuộc A2/A3 |
| K02 | Unique `(created_by,create_request_key)`, create_payload_digest; keys 64 ASCII binary. Chỉ storage cho idempotency, replay/payload conflict response cần action A3 |
| K03 | Unique item `(batch_id,submission_id)` và `(batch_id,selection_order)`, positive selection_order; overlap hai purposes được phép. Không global unique phone/submission |
| K04 | Unique attempt `(item_id,attempt_no)` và `(item_id,actor_id,idempotency_key)`; item_version_used positive, recorded_at>=started_at, nullable boolean 0/1, retry_due/policy cùng null hoặc cùng có. Không gắn cứng outcome/answer mapping, retry 24h hoặc giới hạn 2 attempts |
| K05 | Unique event `(batch_id,event_sequence)` và command tuple với actor/name/key/ordinal; composite FK item+batch và attempt+item có unique targets, CHECK attempt cần item. Không cho tham chiếu nhầm item/batch qua FK đơn |
| K06 | FK users/Dealer/submission/Award/predecessor restrictive, không cascade/null actor. Các cột dùng CHECK để default NO ACTION, không thêm referential-action clause; các FK khác explicit RESTRICT. Retention/xóa identity lịch sử phải review trước áp dụng DDL |
| K07 | CHECK purpose-award/baseline pairing; general requested/cap60, award requested bội5<=15/cap15, actual<=requested/eligible; positive generation/version, freeze sau period_end và cutoff; claim all-null/all-set. CHECK không thể chứng minh child counts/manifest membership hoặc active role; các phần đó phải có trong A2/A3 |
| K08 | Index scope/state, queue/due/order, claim expiry, item/recorded_at và event sequence; không index plaintext phone/PII |

Đã đối chiếu [MySQL CHECK constraints](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html) cho giới hạn AUTO_INCREMENT và referential actions. Không đưa `id` AUTO_INCREMENT vào CHECK predecessor<>id; kiểm self-cycle/same-scope của predecessor thuộc workflow generation tương lai. Không có generation/void action để mở hành vi này ở A1. CHECK/FK thực tế vẫn cần test trên server được phép, không suy pass từ tài liệu MySQL.

### Lựa chọn linh hoạt và rủi ro V1

- Monthly scope theo calendar hiện hành, không đổi timezone. Metadata bắt buộc `approval_cutoff_at`, cutoff_policy_version và encrypted review evidence **không có default cutoff mẫu**. CHECK chỉ bảo vệ thứ tự cơ bản; hoàn tất duyệt, visibility/cutoff boundary, approved+phone QR/C03/demo/duplicate resolution và late approval là nhiệm vụ A2/A3.
- A1 dùng generation mặc định 1, có self FK predecessor để hỗ trợ revision sau; không action tăng generation/void/correction. Dùng strings/versioned metadata cho queue/outcome/lifecycle/retry thay enum khóa cứng nghiệp vụ chưa chốt. Đổi lifecycle không tự được policy update cho phép.
- Counts thật do action tương lai ghi; general requested60, award requested5×m<=15, actual có thể0. Award m<3 dùng target_cap15 và strata snapshot để không che thiếu Dealer. Không CHECK/trigger tự chọn Top 3 hay enforce đủ5/Dealer; A3 phải kiểm JSON/selected items và per-Dealer count. Default lifecycle frozen, queue uncalled, không tự đánh completed/Đạt khi empty.
- Instants mới DATETIME(6), models ghi microseconds; không viết lại DATETIME/calendar cũ. created_at NOT NULL cho batch/item; attempts/events dùng recorded_at/occurred_at explicit, không updated_at cho append-only history.
- Chọn **derive latest attempt qua relation/order**, không lưu latest_attempt_id; tránh vòng FK items↔attempts và pointer sai item như P09C đã cho phép. latest_outcome/attempt_count chỉ là projections, action C phải giữ transaction/event/version nhất quán.
- Encryption dùng Laravel encrypted/encrypted:array với encrypter ứng dụng hiện hữu; seed là string, private snapshots/manifests JSON array. Key_id/encryption_version/digest_key_id là metadata bắt buộc, **không** tự tạo key ring, key rotation hoặc digest/HMAC service. A2 phải xác thực metadata khớp key thực/rotation plan; không ghi plaintext hoặc tự cấp quyền xem PII vì active Admin. Default deny disclosure tới khi có contract cụ thể.
- Giữ ciphertext LONGTEXT và source fingerprint/version/digests; không chạy benchmark dung lượng/packet/snapshot isolation. Private payload hidden khỏi toArray/JSON nhưng truy cập thuộc tính trực tiếp có thể decrypt trong code tin cậy; API tương lai phải authorize/projection riêng.
- Append-only/frozen guards là bảo vệ đường Eloquent lifecycle, **không** chống raw query builder, saveQuietly/events muted hoặc DBA. Không có raw mutation endpoint mới; A3/C phải dùng actions/transactions/locks, không bypass guards. Không tự tạo triggers/grants vận hành hoặc tuyên bố journal chống sửa tuyệt đối.
- Privacy/retention chưa có lịch tự xóa; restrictive FKs giữ lịch sử, có thể ngăn xóa parent. Cần quyết định retention/erasure/backup/key recovery trước áp dụng schema hoặc dữ liệu thật; đây không còn gate thiếu chữ ký TMV riêng của A1.

## Kết quả kiểm thử

| Kiểm tra vừa thực hiện | Kết quả |
|---|---|
| `php -l` từng file trong 12 file PHP mới | PASS |
| `php vendor/bin/pint <12 paths A1>` và `php vendor/bin/pint --test <12 paths A1>` | PASS; chỉ formatter trên file mới A1, không `--dirty` toàn working tree |
| `php vendor/bin/phpunit tests/Unit/CallAuditBatchPolicyTest.php tests/Unit/CallAuditModelTest.php --no-progress` | PASS — 26 tests, 215 assertions. Plain PHPUnit TestCase, không boot Laravel, không DB/traits chuẩn bị schema; key crypto cố định chỉ trong bộ nhớ test |
| `git diff --check`, `git diff --no-index --check` từng file mới, regex whitespace và đối chiếu baseline status | PASS: chỉ thêm đúng 13 file A1 (12 PHP + tracking), không tăng/giảm trạng thái file có sẵn. no-index exit 1 thể hiện file mới khác /dev/null, không phải lỗi whitespace; diff tracked có cảnh báo CRLF từ file cũ |
| `tests/Feature/CallAuditSchemaTest.php` | **NOT_RUN** — cần schema đã được chuẩn bị trong task riêng, không có quyền DDL trong A1 |
| Migration up/down, seed/reset, database preflight/schema introspection, app HTTP, build/full regression | **NOT_RUN**; không kết nối/thay đổi production hoặc bất kỳ DB nào |

Unit tests xác nhận role/status, policy discovery, encryption round-trip/hidden serialization, nullable answers và model event guards trong bộ nhớ. Test schema đã viết có guard toyota_testing + kiểm bảng có sẵn + transaction fixtures; không có RefreshDatabase/DatabaseMigrations, không tự tạo schema. Nó sẽ kiểm unique scope/request/item/attempt/event, restrictive actor FK, composite FK cross-item/batch, count/purpose/claim CHECK và zero/underfill. Fixtures chỉ phục vụ kiểm cấu trúc, không chứng minh eligibility/manifest sampling; tất cả DB cases chưa chạy. Chưa có race test nhiều connection hoặc thực thi DDL nên không có kết quả runtime/schema/concurrency PASS.

## Những lỗi còn tồn tại

Chưa chạy DDL, constraints/schema chưa được xác nhận hoạt động trên toyota_testing. Không coi source pass syntax/unit là DB runtime pass. DDL MySQL có thể implicit commit và fail một phần; task áp dụng cần kiểm server/engine/constraint compatibility, quyền chuẩn bị test schema, backup/rollback và không drop/reset dữ liệu theo suy đoán.

Canonical bounds, active actor at commit, purpose-Award cùng kỳ, per-stratum counts, manifest/digest/seed consistency, conflict/late approval và transaction concurrency chưa có actions để enforce trong A1; thuộc A2/A3/C, không business logic giả trong model. Không còn blocker “thiếu chữ ký TMV” do quyền ủy quyền mới, nhưng không được tự chạy DDL hoặc mở penalty/publication.

## Task hoặc bước tiếp theo

Review source A1, sau đó task riêng được phép chuẩn bị schema trên toyota_testing và chạy CallAuditSchemaTest, kiểm compatibility/constraints và harness concurrency. Chỉ sau đó triển khai A2/A3 với cutoff/privacy/key/generation contracts cụ thể; không tự migrate trong P09D. Lấy mẫu/gọi điện/scoring/công bố/routes/UI chưa được triển khai.
