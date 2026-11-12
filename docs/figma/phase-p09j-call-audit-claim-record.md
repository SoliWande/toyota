# P09J — C1 Claim & Record Call Result

## Mục tiêu

Backend claim/release có ownership, lease/token/version, idempotency và audit nguyên tử; chỉ ghi kết quả khi workflow V1 được xác nhận riêng. Không UI, scoring, approval, verification hoặc công bố.

## Trạng thái

**Hiện hành 2026-11-12: DONE phạm vi C1 sau phê duyệt P09J-B — 100 tests/1.103 assertions PASS.** Xem phần nghiệm thu P09J-B cuối tài liệu; các trạng thái BLOCKED/IN PROGRESS bên dưới giữ nguyên làm lịch sử. C2 counts/tabs/UI và mọi correction/effects/publication không thuộc nghiệm thu C1.

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

### P09J-B — rà soát tiếp tục ngày 2026-11-12

**BLOCKED: chưa có phê duyệt workflow.** Yêu cầu P09J-B vẫn gọi cả bảy quy tắc là đề xuất và yêu cầu: “Nếu chưa có phê duyệt rõ các quy tắc này, không triển khai RecordCallResult.” Quyền TMV ủy quyền cho người dùng đã được ghi nhận; không yêu cầu chữ ký TMV riêng. Đã gửi câu hỏi xác nhận toàn bộ bảy quy tắc trong phiên này; chưa nhận phê duyệt tại thời điểm cập nhật. Không diễn giải việc giao lại task hoặc im lặng thành xác nhận nghiệp vụ.

Đã đọc lại AGENTS/tracking và kiểm tra source thực tế: ClaimItem/ClaimCoordinator, claim controller/request, CallAuditAttempt/Item, CallAuditBatchPolicy, routes, schema attempts và tests C1. Working tree ban đầu sạch. Chỉ có POST claim/release; chưa có action/route ghi kết quả, policy recordAttempt đã có nhưng không xác định mapping outcome/retry. ClaimItem hiện chặn item có attempt/retry/projection đã xử lý, nên khi workflow được duyệt cần tích hợp điều kiện nhận lại item đến hạn một cách nhất quán; không chỉ thêm record action rồi coi lần gọi thứ hai đã hoạt động. Counts/tab/UI vẫn thuộc C2.

**Thay đổi ở lượt chờ duyệt:** chỉ bổ sung mục này trong `docs/figma/phase-p09j-call-audit-claim-record.md`, giữ nguyên kết quả lịch sử phía trên. Không sửa source/routes/policy/tests/migrations, không tạo attempt/finding, không kết nối database hoặc production. Không chạy PHPUnit/Pint/build/migrate/seed/reset; kết quả 56 tests/652 assertions là lịch sử P09J ngày 2026-11-11, không phải nghiệm thu mới P09J-B.

Điều kiện tiếp tục: người dùng phê duyệt rõ cả bảy quy tắc P09J-B (hoặc chỉ rõ phần được duyệt). Sau đó mới tạo source RecordCallResult/POST request/controller, ghi attempt + event + projection nguyên tử với encrypted note, idempotency và kiểm owner/token/version/expiry/source; kiểm thử chọn lọc có preflight/fixtures/cleanup trên toyota_testing. Sai chỉ lưu finding pending; không gọi approve/reject, thay verification/điểm/Awards hoặc mở correction. Chưa có nghiệm thu RecordCallResult trong lượt chờ phê duyệt này.

### P09J-B — phê duyệt và triển khai ngày 2026-11-12

**IN PROGRESS.** Người dùng trả lời “Phê duyêtj” sau câu hỏi phê duyệt toàn bộ bảy quy tắc P09J-B; ghi nhận **USER_APPROVED** cho mapping/outcome/no-answer24h/hai lần/attempt bất biến/finding pending không trừ điểm. Không cần chữ ký TMV riêng. Mục BLOCKED phía trên giữ làm lịch sử, được cập nhật này thay thế về trạng thái phê duyệt. Đã rà soát lại AGENTS/source và working tree: chỉ tracking có thay đổi từ lượt chờ duyệt; không ghi đè source có sẵn. Bắt đầu RecordCallResult, POST và tests chọn lọc; chưa có nghiệm thu mới hoặc database write tại thời điểm ghi mục này.

### P09J-B — nghiệm thu C1 ngày 2026-11-12

**DONE backend C1.** Phê duyệt của người dùng cho cả bảy quy tắc thay thế trạng thái chờ approval phía trên. Kết quả mới **100 tests/1.103 assertions PASS**, không SKIPPED/FAIL. Không dùng kết quả lịch sử 56 tests để thay nghiệm thu lần này.

**Files:** mới `app/Actions/CallAudit/RecordCallResult.php`, `app/Services/CallAudit/CallResultPolicy.php`, `tests/Feature/AdminCallAuditRecordTest.php`, `tests/Fixtures/call_audit_record_worker.php`, `call_audit_record_snapshot.php`. Sửa ClaimItem/ClaimCoordinator, CallAuditClaimController/CallAuditClaimRequest, `routes/web.php`, `tests/Feature/AdminCallAuditClaimTest.php` và tracking này. Test cũ cấm route record được cập nhật sau approval, vẫn kiểm correction/result/retry/void không mở. Không sửa models/policies/schema/migrations, read model B, A2/A3, approval/group/scoring/Awards, assets hoặc UI.

**POST contract:** `/admin/call-audits/{batch}/items/{item}/record`, name `admin.call-audits.record`, nằm trong web/auth/auth.session/role:admin/account.active/throttle:admin-writes; CSRF chuẩn Laravel. Body `purpose`, `row_version`, `idempotency_key`, `claim_token`, `outcome` (`correct|wrong|no_answer`), ba field `answer_1/2/3` bắt buộc có mặt (boolean hoặc null), `note` optional/null, tối đa2.000 ký tự. HTTP boolean được chuẩn hóa trước digest; action validate lại mapping và kiểu. Unknown fields/query bị từ chối an toàn, không echo/flash/log PII. Không nhận actor/timestamp/source fingerprint/seed từ client. Response allowlist chỉ item_id, row_version, attempt_id, outcome, queue_state, retry_due_at, finding_status; không trả answers/note/raw phone/token/seed/manifest/snapshots. Cache private/no-store.

**Workflow đã duyệt:** `correct` chỉ khi ba câu true → queue `completed`; `wrong` cần đủ ba boolean và ít nhất một false → queue `finding_pending`, immutable attempt outcome wrong và event metadata finding_status=pending. Finding dùng chính event/attempt persisted và projection hiện hữu, không tạo bảng hoặc giả lập action xử lý sai lệch. Không gọi approve/reject/verification/penalty. `no_answer` bắt buộc ba null; attempt1 → queue `recall`, due=recorded_at+86.400 giây elapsed tính trong UTC rồi về timezone hiện hành, policy `no_answer_24h_max2_v1`; attempt2 → `completed`, không retry và không finding Sai. Questionnaire version `call_questionnaire_v1`, result policy `call_result_v1`. Không đặt ngưỡng tỷ lệ sai hoặc gate công bố.

Claim dùng cùng CallResultPolicy để kiểm evidence attempts và projection: initial uncalled/no-attempt hoặc đúng một no_answer V1 có retry_due_at khớp, đã đến hạn. Chặn trước hạn, lịch sử/projection mâu thuẫn, completed/finding, stale marker và lần gọi3; không sửa hồ sơ hoặc bỏ qua drift. Admin khác active có thể claim lại sau hạn để ghi lần2; không ghi đè lease còn hiệu lực. C07/C08 giữ item/claim/attempt/event riêng kể cả cùng submission. Không tự chuyển các attempt legacy thiếu version V1 thành retry hợp lệ.

**Atomicity/ownership/source:** kế thừa top-level transaction, fresh actor shared lock, batch/item locks và policy riêng `recordAttempt` server-side. Kiểm owner/token hash/version và lease tại clock server sau khi khóa item; source/Dealer locking reads đối chiếu fingerprint V1 trước insert. Source drift trả409, không refresh snapshot hoặc đổi source. started_at suy từ lease expires_at−600s, recorded_at từ server; client không điều khiển thời gian. Attempt actor_id/ba answers/encrypted note/fingerprint_used/item_version/idempotency digest được lưu cùng event liên kết attempt và projection tăng version, clear claim trong một transaction. Fail tại insert attempt, update item hoặc insert event đều rollback. Event là append-only, chain tiếp previous_event_digest; không sửa freeze event/snapshot. Note trim/empty→null và encrypted cast A1, không xuất note ra receipt/API/log.

Receipt ghi kết quả dùng namespace command `record_call_result`, HMAC canonical intent (batch/item/actor-scoped key/version/token/outcome/answers/note), được lưu encrypted cùng event. Retry cùng request sau commit trả cùng attempt/response kể cả lease đã clear/expire hoặc nguồn sau đó thay đổi; đây là đọc receipt đã commit, không ghi kết quả mới. Khác payload cùng key409, key mới không vượt claim/version/terminal guards. Một batch lock serialize sequence và receipt; unique attempt/event A1 vẫn giữ nguyên. Concurrency/deadlock sử dụng retry3 như C1 trước; không giữ lock khi gọi điện. Generic failure503 không đưa SQL/private exception vào log/response. Model guards giữ attempts/events bất biến, không mở correction/delete/reopen.

| Kiểm tra mới | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_record_snapshot.php before` | PASS actual127.0.0.1:3306/toyota_testing, TCP loopback, MySQL8.0.30, foreign_key_checks1; audit4 bảng InnoDB/0 rows; snapshot19 bảng, hash205 source/migrations được bảo vệ theo exact allowlist C1 |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditRecordTest.php tests/Feature/AdminCallAuditClaimTest.php tests/Feature/AdminCallAuditReadTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped` | **100 PASS/1.103 assertions**, không SKIPPED/FAIL; 44 tests record +56 claim/read/policy hiện hành |
| Real MySQL concurrency mới | PASS hai processes cùng actor/request chờ430,22ms và trả cùng attempt; PASS hai Admin chờ429,46ms, chỉ holder lưu attempt, contender item_version_conflict. Thêm ca tranh claim cũ trong suite vẫn PASS. Không coi lời gọi tuần tự là concurrency |
| Syntax/Pint/diff | `php -l` và `php vendor/bin/pint --test` đúng11 PHP mới/sửa PASS; git diff --check PASS; kiểm file mới/9 mục tracking và giữ lịch sử riêng |
| `php tests/Fixtures/call_audit_record_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false. Mọi fixture journal cleaned=true; dữ liệu/checksum/schema19 bảng và205 source được bảo vệ giữ nguyên, schema hash chỉ bỏ AUTO_INCREMENT counter, không reset counter |

Coverage mới: auth Guest/Sales/Dealer/Manager theo role dealer/inactive/stale Auth; actor khác creator; tám tổ hợp boolean; invalid/missing/null/no-answer/note/extra fields; first no-answer24h (trước hạn1 microsecond bị chặn, đúng hạn được nhận); lần2 correct/wrong/no-answer và không lần3; owner/token/version/expiry/new-owner; changed payload/same-key replay sau commit; source drift; three failure points rollback; real double-submit và two-admin race; purpose/IDOR C07/C08 cùng source nhưng không reuse attempt; CSRF419/valid200, GET405/throttle middleware; note encrypted DB round-trip, không leak response; append-only attempt; source/verification/audit/Award/users/Dealer checksums và freeze reproduction VERIFIED.

Fixtures/bootstrap/guard/cleanup tiếp tục harness A3, chỉ local toyota_testing; committed parent fixtures có ID/tag journal để hai connection cùng thấy, cleanup theo IDs/dependency sau khi workers dừng. Worker xác minh actor/batch/item thuộc fixture, token/DB credentials chỉ truyền structured process environment, không URL/argv/output. Private evidence: `storage/app/private/p09jb-call-audit-claim/{before,after}.json` và `storage/app/private/p09jb-record-races/<tag>/results.json`; output races không chứa token/PII. Không chạy migrate/seed/reset/RefreshDatabase/DatabaseMigrations/full regression/build hoặc production.

**Lỗi còn tồn tại/phạm vi tiếp:** không còn test C1 FAIL/BLOCKED. C2 mới cập nhật counts/tabs/retry hiển thị: read B hiện vẫn trả null/409 cho classification đã có attempts, không tự tuyên bố UI/counters đã kết nối outcome C1. Chưa mở full-phone/PII disclosure, correction/void/generation2, xử lý finding/trừ điểm/huỷ verification hoặc công bố. Chưa benchmark throughput production và chưa bổ sung verifier toàn bộ result-event chain; đó không phải thay đổi trong task backend C1 này.
