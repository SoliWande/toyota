# P09J — C1 Claim & Record Call Result

## Mục tiêu

Backend claim/release có ownership, lease/token/version, idempotency và audit nguyên tử; chỉ ghi kết quả khi workflow V1 được xác nhận riêng. Không UI, scoring, approval, verification hoặc công bố.

## Trạng thái

BLOCKED một phần — 2026-11-11. Claim/ReleaseClaim đã hoàn thiện và kiểm thử **56 tests/652 assertions PASS**, không SKIPPED/FAIL, gồm một test hai connection MySQL thật. RecordCallResult/outcome/retry chưa triển khai vì task ghi rõ quy tắc đang V1_PROPOSED, chưa được xác nhận cụ thể. Đã gửi câu hỏi xác nhận nhưng chưa nhận câu trả lời; không tự coi im lặng là approval, không đánh dấu DONE toàn C1.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, P09B mục 9, P09H, P09I và riêng C08 inventory; kiểm tra schema/models/policy/read routes/actions/tests thực tế. A1 có claim fields, attempts/events append-only và policy claim/recordAttempt; chưa có mutation workflow. Working tree chứa nhiều phase trước chưa commit, giữ nguyên. Test read P09I có assertion chỉ GET cho cả namespace, phải giới hạn assertion vào bốn read routes khi bổ sung POST.

## Các file source code liên quan

- Mới: `app/Actions/CallAudit/ClaimItem.php`, `ReleaseClaim.php`; `app/Services/CallAudit/ClaimCoordinator.php`, `CallCommandRejected.php`.
- Mới: `app/Http/Controllers/Admin/CallAuditClaimController.php`, `app/Http/Requests/Admin/CallAuditClaimRequest.php`.
- Sửa `routes/web.php`: import controller và đúng hai POST trong Admin group hiện hữu, giữ nguyên routes/edits phase trước.
- Sửa `app/Services/CallAudit/BatchReadModel.php`: thêm `row_version` vào item allowlist để client lấy version hiện tại bằng GET, không đoán version hoặc cần truy cập DB. Không đổi metric/tab/outcome.
- Mới: `tests/Feature/AdminCallAuditClaimTest.php`, `tests/Fixtures/call_audit_claim_worker.php`, `call_audit_claim_snapshot.php`.
- Sửa `tests/Feature/AdminCallAuditReadTest.php`: assertion GET chỉ áp dụng đúng bốn read routes, không cấm các POST C1 được yêu cầu; giữ nguyên test GET-no-write/privacy/IDOR.
- Tracking này là tài liệu duy nhất sửa trong task. Không sửa A1 models/policy/migrations, A2/A3 source, business logic hoặc UI.

## Những thay đổi đã thực hiện

Hai POST `/admin/call-audits/{batch}/items/{item}/claim` và `/release`, route names `admin.call-audits.claim`/`.release`. Đọc actor từ Auth session, authorize fresh active Admin/policy trong transaction và scope item theo batch frozen/purpose. Tạo token, lease, row_version và append-only event trong cùng transaction; lỗi giữa chừng rollback hoàn toàn.

Request body claim: `purpose`, `row_version` (integer dương), `idempotency_key` (1–64 ký tự ASCII chữ/số/_/-); release thêm `claim_token` (64 hex). Không nhận actor, phone, timestamp, source snapshot hoặc query string. CSRF chuẩn web (`_token` hoặc header) vẫn hoạt động. Validation trả mã an toàn, không echo/log/flash input. Claim response chỉ item_id/row_version/claim_token/claim_expires_at; release response item_id/row_version/released. Cache private/no-store. Mã lỗi 401/403/404/409/422 và lỗi kỹ thuật 503 không chứa private exception/SQL.

Kiểm tra source fingerprint dưới khóa trước claim mới. Source drift trả conflict và không thay source/snapshot/item/event. Release không cần refresh source và vẫn cho chủ claim hợp lệ nhả item. GET không claim/write/sampling. Không action/route RecordCallResult, retry, correction, void hoặc generation2.

## Quyết định kỹ thuật quan trọng

Không cấp full phone/PII, correction/void/generation2/penalty/publication. Actor từ Auth session, kiểm tra lại active Admin server-side; GET không mutation. Lease là cơ chế kỹ thuật, không tự chốt lịch gọi lại 24 giờ. Record/outcome/retry giữ BLOCKED nếu chưa có xác nhận.

- **Mặc định kỹ thuật V1:** policy `claim_lease_600s_v1`, lease 600 giây theo FreezeClock server/DB hiện hữu, không đổi calendar/timezone. Không heartbeat/renew endpoint hoặc takeover lease còn hiệu lực, kể cả cùng owner với key mới. Khi `now >= expires_at`, Admin active có version hiện tại có thể nhận lease mới; không sửa/xóa lịch sử claim cũ. Lease hết hạn không phải lịch gọi lại.
- Token CSPRNG 32 bytes; item chỉ lưu SHA-256 hash. Receipt token nằm trong private_payload_encrypted của event để cùng request replay sau commit không sinh token khác. Receipt chỉ trả cho đúng actor/session, key, intent, owner/version và lease còn hiệu lực. Receipt đã obsolete trả409, không khôi phục lease cũ. Release retry trả receipt cũ nhưng không clear claim mới.
- Idempotency `(batch, actor, command_name, command_key, event_ordinal=0)` dùng unique A1; HMAC canonical intent gồm command/batch/item/purpose/version/token. Cùng key khác payload hoặc item =>409; key không là quyền truy cập. Claim/release khác command có namespace riêng. Không tạo aliases receipt sau conflict, không tăng attempt_count.
- Lock actor bằng shared lock để trạng thái active không đổi giữa kiểm quyền và commit; batch FOR UPDATE serialize event sequence, item FOR UPDATE bảo vệ version/ownership. Claim mới khóa thêm source và Dealer để fingerprint ổn định. Transaction top-level bắt buộc, retry deadlock tối đa3 của Laravel; có thể tranh khóa với freeze/intake do thứ tự nguồn khác, lỗi sau retry fail closed503, không partial commit. Không giữ khóa trong lúc gọi điện hoặc chờ network.
- Event lưu actor/item/batch, before/after version, policy/correlation, previous_event_digest và HMAC envelope/versioned private receipt. Audit event model A1 giữ append-only. Freeze event/snapshots không sửa, ReproductionVerifier vẫn VERIFIED sau claim/release. HMAC chain mới không phải tuyên bố đã bổ sung verifier toàn bộ call-event chain.
- Chỉ claim item uncalled, không attempt/projection kết quả/retry/stale marker. Chưa có contract gọi thì không diễn giải dữ liệu attempt hiện hữu hoặc cho claim lại item đã xử lý. Không tự cộng calls/completed hoặc tạo finding; C08/C07 vẫn tách theo batch/item.
- Không sửa tài liệu P09B/P09H/P09I lịch sử: phần P09I nói toàn namespace chỉ GET được thay riêng bởi hai POST C1 này; read semantics vẫn giữ, chỉ thêm optimistic row_version vào payload item.

## Kết quả kiểm thử

| Lệnh/kiểm tra | Kết quả hiện tại |
|---|---|
| `php tests/Fixtures/call_audit_claim_snapshot.php before` | PASS exact127.0.0.1:3306/toyota_testing, TCP loopback, MySQL8.0.30, FK checks1; cả4 bảng audit tồn tại/InnoDB/0 rows; snapshot19 bảng |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditClaimTest.php tests/Feature/AdminCallAuditReadTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped` | **56 tests/652 assertions PASS**, không SKIPPED/FAIL; chỉ suites liên quan, không full regression |
| Concurrency | Hai PHP processes/connection thật, holder giữ batch lock đợi contender bắt đầu SQL; chỉ một claim/event mới, contender item_version_conflict sau chờ khoảng439ms. Private artifacts `storage/app/private/p09j-races/<fixture-tag>/results.json` không chứa token/PII |
| Syntax/Pint/diff | PASS kiểm cuối12 PHP mới/sửa, gồm routes và read row_version; git diff --check PASS, cảnh báo CRLF hai Blade phase cũ không thuộc task; kiểm file mới/9 mục tracking riêng PASS |
| `php tests/Fixtures/call_audit_claim_snapshot.php after` | PASS kiểm cuối audit_rows=[0,0,0,0], changed_tables=[], source_changed=false; dữ liệu/checksum/schema19 bảng giữ nguyên |
| RecordCallResult: no-answer/null, finding, repeated save, attempt/event/projection atomic, retry24h | **NOT_RUN/BLOCKED**, không có code/route/schema thay thế approval. Tests chỉ chứng minh record/correction routes không mở |

Coverage claim: auth guest/Sales/Dealer/Manager theo dealer account/inactive; active Admin khác creator; stale Auth object không vượt fresh active check; source drift; purpose/parent-child IDOR/missing/non-frozen; same-key retry/conflict; owner/token/version; exact lease expiry và old receipt; release retry không xóa lease mới; event-insert failure rollback; actor/chain/encrypted token receipt; GET405/no-write/private payload; CSRF thực419 và valid token200; middleware/throttle registration; unknown/PII input không echo/flash/log; customer/verification/Award/audit/users/Dealer checksums không đổi; freeze reproduction VERIFIED. Không tuyên bố đã test call recording.

Harness dùng guard/bootstrap và CallAuditFreezeFixtures của A3, committed fixture ID/tag journal + cleanup dependency; worker có guard exact loopback/database trước PDO, xác minh actor/batch/item thuộc run, process env có credentials dạng structured env không in ra. Worker dừng trước teardown. Không migrate/seed/reset, RefreshDatabase/DatabaseMigrations, build, UI hoặc production. Sau các lần chạy, mọi fixture journal `cleaned=true`. Artifact pre/post private tại `storage/app/private/p09j-call-audit-claim/{before,after}.json`, chỉ counts/checksums/schema/hash không PII. Baseline source hash205 file gồm6 file mới C1, so sánh199 file được bảo vệ sau khi loại đúng6 file mới; read model được phép sửa row_version và kiểm riêng diff. AUTO_INCREMENT counters không reset, schema checksum chỉ loại counter này.

## Những lỗi còn tồn tại

Workflow kết quả gọi chưa được phê duyệt rõ theo chính yêu cầu P09J; chờ câu trả lời xác nhận. Không lấy quyền quyết định V1 chung thay cho approval cụ thể mà task yêu cầu.

Căn cứ trực tiếp: “Các quy tắc này là V1_PROPOSED, chưa được người dùng xác nhận cụ thể cho workflow gọi điện. Chỉ triển khai phần nào được phê duyệt rõ”. Không chặn vì thiếu chữ ký TMV riêng. Điều kiện tiếp tục: người dùng xác nhận mapping ba câu/outcome, finding pending, no-answer null/24h/hai lần và bất biến attempt. Full-phone disclosure/correction/effects/publication vẫn cần task/quyền riêng. Lease600s là lựa chọn kỹ thuật được ghi rõ, chưa benchmark throughput production; không gọi phần này là workflow C1 hoàn chỉnh.

## Task hoặc bước tiếp theo

Sau xác nhận workflow, tiếp tục cùng tracking: RecordCallResult với claim owner/token/version/expiry, source fingerprint, append-only attempt/event/projection nguyên tử, idempotency/save concurrency/rollback và no side effects. C2 mới kết nối retry/counts/read tabs tương ứng contract đã chốt. Không thêm UI C08/correction/penalty/publication từ task này.
