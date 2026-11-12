# P09O — E2 Finding Resolution Backend

## Mục tiêu

Chuẩn bị backend quyết định xử lý finding có bằng chứng E1, actor/time/reason và lịch sử append-only. Không sửa attempt C1, verification, moderation, điểm, Dealer, Award/AwardWinner hoặc snapshot đã công bố; không UI.

## Trạng thái

**Nghiệm thu hiện hành 12/11/2026: DONE backend E2 — 172 tests/1.740 assertions PASS, không SKIPPED/FAIL.** USER_APPROVED đầy đủ như mục tiếp tục dưới đây; lịch sử BLOCKED không còn áp dụng. Không triển khai effects/UI hoặc cấp quyền deploy production.

**Hiện hành 12/11/2026: IN PROGRESS — USER_APPROVED toàn bộ E2**, gồm ba quyết định, transition matrix, reason bắt buộc tối đa2.000 ký tự, actor/time, không reopen/correction hoặc tác động điểm/xác minh. Người dùng xác nhận trong task tiếp tục; không cần hỏi lại. Phê duyệt này thay trạng thái chờ duyệt bên dưới, giữ nguyên lịch sử.

BLOCKED — chờ phê duyệt riêng ba quyết định E2, ngày 2026-11-12. Đề xuất `confirmed`, `dismissed`, `needs_review` vẫn là **V1_PROPOSED**, chưa có USER_APPROVED cho E2 tại thời điểm ghi nhận. Đã gửi câu hỏi phê duyệt trong phiên này; không coi yêu cầu triển khai, quyền TMV ủy quyền, approval P09J-B hoặc im lặng là approval workflow mới. Không cần chữ ký TMV riêng sau khi người dùng phê duyệt.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS.md, P09N, phần nghiệm thu P09J-B, P09K và P09M; kiểm source thực tế: finding reader/evidence, WorkflowReadModel, RecordCallResult, ClaimCoordinator, models/policy, migration events, requests/controllers/routes và harness tests. P09N ghi lịch sử93 tests/1.066 assertions PASS; chưa chạy lại trong lượt chờ phê duyệt P09O.

E1 dùng item ID làm logical finding ID, chỉ đọc Wrong terminal có evidence; không có finding resolution. C1 đã ký receipt kết quả và clear claim khi Wrong. C2 counts/queue phản ánh kết quả cuộc gọi, chưa có semantics xử lý finding. E1 yêu cầu item.row_version bằng attempt.item_version_used+1; tự tăng item version khi resolution sẽ phá contract hiện tại. Working tree có nhiều thay đổi P09J–P09N chưa commit; giữ nguyên, không quy chúng thành task này.

## Các file source code liên quan

Lượt hiện tại **chỉ tạo tài liệu** `docs/figma/phase-p09o-finding-resolution.md`; không sửa source/routes/policies/models/tests/migrations hoặc tài liệu lịch sử.

Files dự kiến sau approval, chưa được tạo/sửa:

- `app/Actions/CallAudit/ResolveFinding.php`.
- `app/Services/CallAudit/FindingResolutionPolicy.php`, `FindingResolutionReadModel.php`, `FindingResolutionCoordinator.php`.
- `app/Http/Requests/Admin/CallAuditFindingResolutionRequest.php`, `app/Http/Controllers/Admin/CallAuditFindingResolutionController.php`.
- `app/Policies/CallAuditBatchPolicy.php`: ability riêng `resolveFinding`, không mở correctAttempt/adjustScore/publish.
- `routes/web.php`: POST resolution và GET current/history nếu được duyệt; giữ GET E1 read-only.
- `tests/Feature/AdminCallAuditFindingResolutionTest.php`, unit policy/mapping tests, fixture snapshot/worker concurrency riêng dùng guard/cleanup hiện có.

## Những thay đổi đã thực hiện

Đã tạo tracking và thiết kế sơ bộ có căn cứ source; chưa triển khai mutation, chưa ghi database hoặc kết nối database/production. Không có màn hình/modal/nút mới.

Decision register:

| Quyết định | Ý nghĩa đề xuất | Trạng thái |
|---|---|---|
| confirmed | Xác nhận có sai lệch; chỉ lưu quyết định, không thực thi penalty | V1_PROPOSED / chờ người dùng |
| dismissed | Không xác nhận sai lệch; không đổi Wrong thành Correct hoặc tự khôi phục xác minh | V1_PROPOSED / chờ người dùng |
| needs_review | Cần kiểm tra thêm; không tự lên lịch gọi hoặc mở lại attempt | V1_PROPOSED / chờ người dùng |

## Quyết định kỹ thuật quan trọng

Đây là **thiết kế đề xuất, chưa phải runtime hoặc contract đã được nghiệm thu**:

1. **Storage không DDL:** ưu tiên event mới trong `call_audit_events`, `event_type=finding_resolution_recorded`, command riêng, schema/policy version riêng; item/attempt/batch FK đã có. Source migration A1 dùng string cho event/command, không CHECK đóng danh sách event; unique `(batch_id,event_sequence)` và `(batch_id,actor_id,command_name,command_key,event_ordinal)` hỗ trợ sequence/idempotency. Không sửa/xóa event/attempt C1; không update projection item hoặc batch. Nếu kiểm thử phát hiện cần schema mới, chỉ viết migration source và ghi NOT_RUN phần cần schema, chờ task DDL riêng.
2. **Version độc lập:** resolution_version bắt đầu0 khi chưa có event xử lý, tăng1 mỗi quyết định đã commit. Current state được fold từ event sequence đã validate, không tạo finding record khi đọc; chỉ một current resolution trên mỗi item. `before_version/after_version` của event resolution mô tả version riêng, metadata ghi rõ namespace/policy để không nhầm item.row_version. Request còn mang item.row_version và attempt_id làm evidence anchor; không nhận actor/time/Dealer ID từ client. Không thay C1/C2 queue, outcome, counts, tỷ lệ hoặc is_complete để suy công bố. Reader resolution trả trường riêng, không đổi status `finding_pending` của kết quả gọi thành Correct/Completed.
3. **Chuyển trạng thái đề xuất cần chốt cùng approval:** pending ban đầu → một trong ba quyết định; needs_review là chưa kết luận, có thể → confirmed/dismissed bằng request mới và đúng version; confirmed/dismissed kết thúc E2, không reopen/correction. Retry cùng request trả receipt cũ; không thêm event cho cùng trạng thái bằng key mới. Không sửa quyết định đã lưu; correction/reopen cần workflow riêng. Các chi tiết này chưa được coi là người dùng đã duyệt chỉ vì có tên ba trạng thái.
4. **Transaction/concurrency:** action top-level transaction, fresh actor shared lock → batch FOR UPDATE → child item FOR UPDATE theo thứ tự C1. Policy active Admin và purpose/batch/item scope kiểm server-side; Admin khác creator được phép theo phạm vi task. Dưới lock, kiểm receipt, validate E1 signed evidence, current resolution history và expected resolution_version trước insert event. Batch lock serialize cả C1/E2 sequence; hai Admin dùng cùng version chỉ một commit, bên còn lại409. Không cần giữ claim 600s của cuộc gọi để review Wrong terminal.
5. **Idempotency:** key actor/batch/command scoped theo unique A1, payload HMAC canonical gồm item/purpose/attempt/item version/resolution version/decision/reason đã normalize. Cùng key+payload trả receipt đã commit, kể cả version đã tăng; khác payload cùng key409; không dùng replay để vượt fresh authorization/IDOR. Receipt encrypted, event digest versioned và nối previous_event_digest. Source attempt/event không hợp lệ hoặc receipt/history không xác minh được thì fail closed, không tự sửa.
6. **Actor/reason/privacy:** actor_id lấy Admin session, timestamp server; reason đề xuất bắt buộc, trim và giới hạn2.000 ký tự như note C1, lưu encrypted private payload. Metadata chỉ ID/version/decision/policy, không chứa reason hoặc PII. Response allowlist current decision/version/actor/time/anchors; không echo reason, answers, phone, claim token, seed/manifest hoặc SQL vào response/log. GET current/history chỉ active Admin, masked nếu có customer; không trả full phone. Nếu cần đọc reason, phải có contract disclosure rõ; không tự cấp từ quyền review.
7. **Evidence và scope:** dùng Wrong đã xác minh E1, không suy từ queue_state; không coi thay đổi nguồn live sau freeze là sửa lịch sử. Snapshot/fingerprint/receipt tampered/stale/conflicting trả409. Cùng submission ở C07/C08 là hai logical findings và hai quyết định độc lập; không propagate quyết định sang batch khác.
8. **HTTP đề xuất:** POST `/admin/call-audits/{batch}/findings/{item}/resolution`, middleware web/auth/auth.session/role:admin/account.active/throttle:admin-writes, CSRF và policy `resolveFinding`; GET không ghi hoặc claim. Input allowlist purpose, attempt_id, row_version, resolution_version, decision, reason, idempotency_key; rejects query/unknown fields generic422, không flash input. GET resolution/history có purpose scope, pagination/order rõ, no-store; không serialize encrypted models.

## Kết quả kiểm thử

Chưa triển khai source nên **NOT_RUN** PHPUnit/Pint/syntax PHP/build/concurrency và toàn bộ nghiệm thu E2. Không dùng93 tests lịch sử E1 để tuyên bố E2 PASS. Không preflight/kết nối DB vì lượt này chỉ tài liệu, không fixture/DDL/migrate/seed/reset/full regression.

Đã kiểm source/schema bằng đọc file, không thực thi migration. Kiểm tra nội dung/đủ9 mục tracking và `git diff --check`/whitespace riêng tài liệu trước bàn giao.

Kế hoạch kiểm thử sau approval:

- Unit transition matrix, normalize reason và deterministic payload digest; deny unsupported correction/reopen và zero evidence.
- Feature thật/giả/stale Wrong; Correct/no-answer không resolution; missing/duplicate/tampered event/receipt; C07/C08 overlap không trộn.
- Guest/Sales/Dealer/Manager/inactive/fresh-blocked Admin403, IDOR/purpose404, CSRF419, throttle, actor server-side.
- Same key retry sau commit cùng event/response; khác payload409; key khác cùng version409; version stale; current/history một state và audit append-only.
- Hai connection/process MySQL thật tranh cùng version hoặc cùng request; chỉ một event hợp lệ. Nếu harness không an toàn thì NOT_RUN, không thay bằng tuần tự và gọi PASS.
- Inject failure ở insert/event/receipt rồi rollback, không event dở; nguồn/moderation/verification/điểm/Award/snapshot và attempt/event C1 giữ checksum.
- Privacy reason encrypted DB round-trip/no response/log/URL; GET không mutation/locking read; preflight exact127.0.0.1/toyota_testing và postflight audit fixture cleanup, data cũ không đổi.

## Những lỗi còn tồn tại

BLOCKED mutation do yêu cầu rõ của P09O: “Các trạng thái này đang là V1_PROPOSED, chưa được người dùng phê duyệt riêng. Không tự triển khai mutation nếu chưa có phê duyệt rõ.” Đây là điều kiện từ task, không phải thiếu chữ ký TMV hoặc restriction suy diễn từ skill. Chưa có backend E2 hoạt động, tests hoặc nghiệm thu mới. Cần xác nhận transitions/reason contract đề xuất trước khi khóa hành vi mới; không tự coi proposal là approval.

## Task hoặc bước tiếp theo

Người dùng phê duyệt ba quyết định và hướng transition/reason ở trên (hoặc chỉ rõ điều chỉnh). Sau đó tiếp tục **cùng tracking này**, ghi approval mới và giữ lịch sử chờ duyệt; triển khai source/policy/POST/read/tests có guard, chạy syntax/Pint/diff và targeted tests toyota_testing. Không tự áp dụng migration nếu phát sinh nhu cầu. Penalty, thu hồi xác minh, approval, correction, publication và UI vẫn task riêng.

### Tiếp tục và nghiệm thu E2 — 12/11/2026

**USER_APPROVED** theo task tiếp tục: initial → confirmed/dismissed/needs_review; needs_review → confirmed/dismissed; confirmed/dismissed cuối, không reopen/correction. Mỗi quyết định có reason bắt buộc tối đa2.000 ký tự, actor từ session và thời điểm server. Chỉ ghi quyết định, không thay kết quả gọi/điểm/xác minh. Phần thiết kế/chờ duyệt phía trên là lịch sử; kết quả thực tế dưới đây thay các ghi chú chưa triển khai/NOT_RUN và BLOCKED trước approval. Không cần hỏi lại nghiệp vụ đã duyệt.

**Files đã triển khai:**

- Mới `app/Actions/CallAudit/ResolveFinding.php`.
- Mới `app/Services/CallAudit/FindingResolutionPolicy.php`, `FindingResolutionHistory.php`, `FindingResolutionReadModel.php`.
- Mới `app/Http/Controllers/Admin/CallAuditFindingResolutionController.php`, `app/Http/Requests/Admin/CallAuditFindingResolutionRequest.php`.
- Sửa `app/Policies/CallAuditBatchPolicy.php`: chỉ thêm ability `resolveFinding` cho active Admin; các quyền correction/void/adjustScore/publish vẫn false.
- Sửa `routes/web.php`: import và đúng ba routes E2; giữ edits các phase trước.
- Mới `tests/Feature/AdminCallAuditFindingResolutionTest.php`, `tests/Fixtures/call_audit_resolution_worker.php`, `call_audit_resolution_snapshot.php`; sửa `tests/Unit/CallAuditBatchPolicyTest.php` để kiểm ability mới với toàn bộ role/status.
- Cập nhật cùng tracking này. Không sửa source C1/C2/E1/models/migrations, UI hoặc các tài liệu lịch sử. Logic coordinator đặt trực tiếp trong action thay vì tạo thêm lớp chỉ chuyển tiếp; History là verifier/fold dùng chung cho action và reads.

**API thực tế:**

| Method/path sau `/admin/call-audits/{batch}/findings/{item}` | Contract |
|---|---|
| POST `/resolution` | Body bắt buộc purpose, attempt_id, row_version, resolution_version, decision, reason, idempotency_key; month tùy chọn. Reject query và field lạ. Purpose/month/batch/item/attempt/version kiểm server-side |
| GET `/resolution` | Query purpose bắt buộc, month tùy chọn. Chưa có quyết định: version0, decision/actor/time=null; không tạo event |
| GET `/resolution/history` | Query purpose, month, page/per_page (mặc định20, tối đa50); thứ tự resolution mới nhất trước; validate cả lịch sử trước pagination, không pagination URL chứa input |

Middleware chuẩn web/auth/auth.session/role:admin/account.active/throttle:admin-writes và CSRF POST. Fresh authorization tại request/service/action, ability resolveFinding riêng tại mutation. Child ID sai scope hoặc không có Wrong thật404; invalid input422; stale version/payload/transition/integrity409; lỗi thực thi bất ngờ503 generic. Cả ba response no-store/private, chỉ allowlist ID/version/decision/actor/time và batch metadata trên GET; không reason/phone/answers/PII/keys/seed/manifest/receipt.

**Persistence và tính toàn vẹn:**

Không cần migration hoặc bảng mới. Mỗi commit chỉ INSERT một `finding_resolution_recorded` vào `call_audit_events` hiện hữu. attempt_id/item_id/batch_id/actor_id và composite FK giữ đúng anchor; resolution event có schema `call_audit_resolution_event_v1`, policy `finding_resolution_v1`, command `resolve_finding`. Metadata khai báo namespace=resolution. Không UPDATE item/batch, không tăng item.row_version, không sửa/xóa attempt hoặc event C1; resolution_version độc lập0→1→2. Wrong/finding_pending/counts/tỷ lệ/is_complete C1/C2 giữ nguyên nghĩa, không được coi resolution là thay kết quả gọi hoặc cho phép công bố.

Reason trim, encrypted trong private payload cùng input đã normalize/intent digest/receipt. HMAC intent bao gồm actor/batch/item/payload; HMAC event namespace riêng, nối previous_event_digest. History validate signature, payload digest, ciphertext/receipt, anchor Wrong E1, policy/schema/key, version liên tiếp, transition, actor/time/sequence, metadata và predecessor trước fold. Evidence C1/Snapshot bị sửa hoặc history hỏng fail closed, không đoán/repair. Snapshot là chứng cứ lịch sử: nguồn live đổi sau khi gọi không tự sửa/huỷ finding hoặc resolution. Không query nguồn live để thay evidence.

Top-level transaction với fresh actor shared lock → batch FOR UPDATE → item FOR UPDATE theo thứ tự C1. Batch lock serialize event sequence; không dùng claim lease của cuộc gọi cho review. Receipt scope actor/batch/command/key/ordinal theo unique A1; cùng request trả receipt cũ đã validate kể cả sau quyết định tiếp theo, khác payload409, khác request cùng stale version409. Key trùng khác item trong batch không được tái dùng. Chỉ lịch sử hợp lệ mới dựng được một current resolution. GET sử dụng read transaction không writes/locking reads, không resolve writer, không tự tạo trạng thái persisted. Model append-only guards A1 được giữ nguyên.

**Kiểm thử mới, môi trường và bằng chứng:**

| Kiểm tra | Kết quả thực tế |
|---|---|
| Preflight `php tests/Fixtures/call_audit_resolution_snapshot.php before` | PASS trước fixtures: MySQL8.0.30, TCP `127.0.0.1:3306`, database thực `toyota_testing`, FK checks1, InnoDB, bốn bảng audit0 rows; snapshot19 bảng và hash218 file app/migrations có trước task, chỉ loại đúng policy dự kiến sửa |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditFindingResolutionTest.php tests/Feature/AdminCallAuditFindingTest.php tests/Feature/AdminCallAuditReadIntegrationTest.php tests/Feature/AdminCallAuditRecordTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped --log-junit storage/app/private/p09o-call-audit-claim/tests.xml` | **172 PASS / 1.740 assertions**,0 skipped/fail. E2 mới56/452; E1 37/308; C2 22/311; C1 record44/452; policy13/217 |
| MySQL concurrency E2 hai processes/connections thật | PASS cùng actor/request: chờ433,27ms, hai responses giống nhau, chỉ một event. PASS hai Admin: chờ430,41ms, chỉ holder commit; contender `resolution_version_conflict`. CONNECTION_ID khác nhau đã assert. C1 concurrency tests trong suite liên quan cũng PASS |
| Syntax/Pint | `php -l` đúng12 PHP mới/sửa PASS; `php vendor/bin/pint --test` đúng12 PHP PASS |
| Diff/tài liệu | `git diff --check` và whitespace riêng file mới, tracking đủ9 mục; không format/refactor file ngoài phạm vi |
| Postflight `php tests/Fixtures/call_audit_resolution_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false. Toàn19 bảng giữ row counts/checksum/schema; toàn218 source app/migrations được bảo vệ không đổi;1.579 fixture journals đều cleaned=true |

Lệnh PHPUnit dùng APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ file test không tồn tại. Guard trước PDO và xác minh identity/transport thực tế. Dùng harness A3 committed fixtures có ID/tag journal+cleanup scoped sau khi workers dừng; không RefreshDatabase/DatabaseMigrations. Snapshot schema bỏ AUTO_INCREMENT counter; không reset counter. Artifacts private/ignored: `storage/app/private/p09o-call-audit-claim/{before,after}.json`, `tests.xml`, `storage/app/private/p09o-resolution-races/<tag>/results.json`; không reason/PII/credentials trong output races.

Coverage E2 gồm đủ12 transitions, required/invalid reason/input, fresh blocked Admin và ability riêng, CSRF/IDOR/month/purpose, stale evidence/item/resolution version,11 loại tampering C1/E2, fake pending không attempt, non-Wrong thật (uncalled/Correct/no-answer), retry receipt sau quyết định mới, payload conflict, current/history/pagination/empty, rollback sau INSERT trước COMMIT, reason encrypted DB round-trip, append-only, GET không mutation, nguồn/caller đổi giữ lịch sử, C07/C08 cùng source độc lập. Checksum trước/sau resolution giữ mọi bảng ngoài event, item/batch/attempt và toàn bộ events C1 giữ nguyên; E1/C2 đọc vẫn hợp lệ và freeze reproduction VERIFIED.

Lượt đầu có3 fixture authorization errors vì thiếu dealer_id theo CHECK users; đã sửa chỉ fixture. Lượt tích hợp tiếp có1 helper test tìm attempt sau khi test chủ động xóa evidence; đã dựng request trước khi xóa. Không nới constraint hoặc sửa backend nghiệp vụ để làm PASS; kết quả cuối phía trên thay các lượt lỗi này.

**Phần còn thiếu/phạm vi tiếp:** không còn FAIL/SKIPPED/BLOCKED trong E2. Reason được lưu để audit nhưng không có endpoint disclosure lý do; API không trả reason theo privacy đã yêu cầu. Không correction/reopen, xử phạt/trừ điểm, thu hồi verification, moderation, thay Dealer/Award/AwardWinner/published snapshots hoặc UI mới. Counts C2 vẫn là kết quả cuộc gọi, không dùng số resolution để suy lại tỷ lệ sai hoặc trạng thái công bố; effects/counts resolution bổ sung cần task riêng. Chưa benchmark throughput production hoặc triển khai key rotation; verifier không tuyên bố chống toàn quyền DBA xóa cả ledger. Không migrate/seed/reset/full regression/production. Không build/browser vì E2 không thay UI/assets.
