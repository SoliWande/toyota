# P09K — C2 Call Audit Counts, Tabs & Read Integration

## Mục tiêu

GET đọc workflow C1 đã persist, ba tab/counts/Dealer/retry clock đúng evidence; giữ masking/quyền/pagination, không mutation/UI/schema/scoring.

## Trạng thái

DONE phạm vi C2 read integration — 2026-11-12: **122 tests/1.405 assertions PASS**, không SKIPPED/FAIL. Không nghiệm thu UI, effects hoặc công bố.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, P09I, nghiệm thu P09J-B, riêng C08 inventory và source read/write/policy/tests. C1 được duyệt và có tests lịch sử100/1.103; read B vẫn null/409 khi có attempts, fake projection cũ bị bỏ qua. P09K thay nhánh này bằng consistency validation, không sửa lịch sử tài liệu. Working tree có toàn bộ thay đổi P09J-B chưa commit, giữ nguyên và không quy thành P09K.

## Các file source code liên quan

- Sửa `app/Services/CallAudit/BatchReadModel.php`; mới `app/Services/CallAudit/WorkflowReadModel.php`.
- Mới `tests/Feature/AdminCallAuditReadIntegrationTest.php`, `tests/Fixtures/call_audit_read_integration_snapshot.php`.
- Sửa `tests/Feature/AdminCallAuditReadTest.php`: thay assertion lịch sử bỏ qua fake projection bằng safe409 khi projection/attempt/event mâu thuẫn; giữ auth/privacy/IDOR/zero/pagination/GET-no-write tests.
- Tracking này là tài liệu duy nhất được tạo/sửa cho P09K. C1 writers/policies/models, CallResultPolicy/FreezeClock, controllers/requests/routes, schema và source scoring/approval/group/Awards giữ nguyên.

## Những thay đổi đã thực hiện

Read classifier dùng cùng mapping validate thuần của CallResultPolicy C1, không gọi assertCallable hoặc writer/locking read. Đọc selected items + attempts + result events bằng explicit field allowlist, đối chiếu toàn batch trước trả counts/tab/page. Thiếu/hỏng/unknown history hoặc projection lệch trả409 `audit_read_unavailable`, không đoán hoặc sửa dữ liệu. Bốn GET hiện hữu dùng cùng classifier; không route mới.

Counts và Dealer counts được tính một lượt trên các item đã validate. Tab lọc theo IDs hợp lệ rồi phân trang theo selection_order/id như trước; counts luôn toàn batch, không phải số rows của trang hiện tại. Index/detail/item đều phát hiện mâu thuẫn; purpose/month/parent-child scope và fresh active Admin vẫn server-side. Item trả thêm outcome, attempt_count, retry_due_at, is_due và finding_status trong projection allowlist, phân biệt completed/no-answer và finding_pending.

## Quyết định kỹ thuật quan trọng

Không suy trạng thái từ queue string khi thiếu evidence. completed_count đếm queue completed; finding_pending_count tách riêng, tab Đã xong là tổng hai count. is_complete giữ nghĩa bảo thủ: actual>0, không cần gọi/gọi lại/finding pending; không là xác minh Đúng hoặc quyền công bố. No-answer2 phân biệt bằng outcome, không tính Correct.

**Định nghĩa dữ liệu:** requested_count/actual_count giữ từ frozen batch, đối chiếu actual_count với selected rows. called_count đếm item có ≥1 attempt hợp lệ (không số attempts). needs_call_count đếm chưa có attempt với projection uncalled hợp lệ; claim đang mở không tăng called_count. recall_count đếm đúng một no_answer V1 còn queue recall, gồm cả chưa/đã đến hạn. completed_count chỉ queue completed (Correct hoặc lần2 kết thúc); finding_pending_count đếm Wrong đang pending. Tab Đã xong = completed_count + finding_pending_count; do đó tổng ba tab=actual_count, không cộng finding lần nữa vào completed_count. Item.state phân biệt finding_pending với completed; outcome no_answer không phải Correct.

**Tỷ lệ Dealer — TECH_DEFAULT được ghi rõ:** task không định nghĩa sẵn mẫu số; đã hỏi tùy chọn trong phiên nhưng không nhận phản hồi. Tiếp tục theo phương án bảo thủ đã nêu, không coi im lặng là một approval riêng: `Wrong/(Correct+Wrong)` trên **khách có kết quả trả lời đủ ba câu**, tính mỗi selected item một lần theo kết quả mới nhất. No-answer1/2 không vào mẫu số và không là Correct. `wrong_ratio` là phần trăm0–100 làm tròn2 số thập phân; `wrong_ratio_denominator` trả số khách đủ mẫu số để contract rõ. Mẫu số0 trả null, không fake0%; có Correct thật mà không Wrong thì0% hợp lệ. Không tính tỷ lệ trên requested_count/actual_count/số attempts, không dùng ngưỡng [x] hoặc áp penalty/gate. Nếu người dùng chọn mẫu số khác, điều chỉnh contract/tests read riêng, không đổi outcome C1. Đây là thống kê mô tả, không chứng nhận Dealer Đạt.

**Consistency:** attempt count0–2, thứ tự1..N; mọi attempt đúng questionnaire/mapping C1, source_fingerprint_used khớp frozen item; time hợp lệ không nằm tương lai; lần2 chỉ sau lần1 no-answer và đến hạn. Retry timestamp/policy phải đúng recorded_at+86.400 giây. Mỗi attempt có đúng result event liên kết, actor/item/sequence/version/timestamp/schema/command/policy/finding_status đúng; result event orphan/thiếu/unknown bị chặn. Projection attempt_count/queue_state/latest_outcome/retry_due_at phải khớp kết quả dẫn xuất. Chỉ kiểm consistency các trường persisted, không tuyên bố bổ sung cryptographic verifier toàn bộ event chain.

**Clock/GET/privacy:** lấy một FreezeClock server instant cho mỗi response, cùng timezone/calendar hiện hữu; recall is_due=`now>=retry_due_at` (trước hạn1µs=false, đúng hạn=true), không ghi hoặc dời lịch khi đến hạn. Không recall thì retry_due_at/is_due=null. Các read nằm trong DB transaction consistent snapshot MySQL hiện hành, không FOR UPDATE/claim hoặc side effects. Source thay đổi sau freeze/call không thay name/phone/Dealer/history: dùng encrypted frozen snapshots cho nội dung, không query source/Dealer/Award live. Counts metadata không fetch note_encrypted/private_payload_encrypted/seed/manifest; không serialize Eloquent hoặc paginator ra response; mask/no-store/URL pagination allowlist giữ nguyên. Event/attempt fields nội bộ không trả answers/note/actor/keys/digests/PII. Không đổi full-phone disclosure.

## Kết quả kiểm thử

| Kiểm tra vừa chạy | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_read_integration_snapshot.php before` | PASS exact127.0.0.1:3306/toyota_testing, TCP loopback, MySQL8.0.30, fk_checks1; 4 bảng audit InnoDB/0 rows; snapshot19 bảng, hash210 source/migrations được bảo vệ (gồm toàn bộ C1) |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditReadIntegrationTest.php tests/Feature/AdminCallAuditReadTest.php tests/Feature/AdminCallAuditRecordTest.php tests/Feature/AdminCallAuditClaimTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped` | **122 PASS/1.405 assertions**, không SKIPPED/FAIL; 22 integration tests mới và100 C1/read/policy liên quan hiện hành, không full regression |
| Syntax/Pint/diff | `php -l` + `php vendor/bin/pint --test` đúng5 PHP mới/sửa PASS; git diff --check PASS; file mới/tracking kiểm riêng, không formatter source C1 |
| `php tests/Fixtures/call_audit_read_integration_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false; mọi fixture journal cleaned=true; schema hash chỉ bỏ AUTO_INCREMENT counter, không reset counter |

Fixtures dùng guard/bootstrap/CallAuditFreezeFixtures và C1 ClaimItem/RecordCallResult thật để chuẩn bị evidence; GET bật SQL guard cấm writes và locking reads, không chỉ dựa vào rollback. Trước/sau GET so sánh row counts/checksums toàn19 bảng, bind writers để throw nếu GET resolve; kiểm query log không fetch note/receipt/seed/manifest hoặc live business tables. Setup/cleanup dùng committed ID/tag fixtures và scoped dependency cleanup đã có để suites concurrency C1 an toàn; không migrate/seed/reset hoặc production. Không build/UI.

Coverage: ba tabs/labels/order/pagination/out-of-range; zero/underfill và C07 Dealer actual0; counts trên nhiều attempts cùng khách (7 attempts nhưng5 called); no-answer1/2/Correct/Wrong/finding pending; before/due/after1µs không chuyển state; pending đã xong gọi nhưng chưa hoàn tất xử lý; mỗi Dealer counts/mẫu số riêng, zero/no-answer denominator=null, tỷ lệ33,33%/50%/100%; active/blocked/guest/Sales/Dealer/Manager/IDOR/masking; source/Dealer đổi giữ frozen nội dung, purpose không trộn; 14 loại projection/attempt/event mismatch safe409 trên mọi GET kể cả lọc tab không có item hỏng; GET không repair/mutation/log PII; source/verification/điểm/Awards và210 source bảo vệ giữ nguyên. Suite C1 bao gồm3 tests concurrency MySQL thật vẫn PASS, không bổ sung mutation/concurrency harness mới trong P09K.

Artifacts private/ignored: `storage/app/private/p09k-call-audit-claim/{before,after}.json` (tên directory giữ từ template harness, nội dung là P09K read-only snapshot), fixture ID journals dùng lại; không chứa raw PII/credentials.

## Những lỗi còn tồn tại

Không có tests liên quan FAIL/SKIPPED còn lại. Mẫu số tỷ lệ là TECH_DEFAULT minh bạch như trên, không được suy thành ngưỡng xử lý sai lệch hoặc TMV business approval. Chưa benchmark index với nhiều năm batch; mỗi batch tối đa60/15 items nên validate theo batch là bounded, index giữ pagination tối đa50 batch. Không thêm UI, full phone, correction/effects/publication hoặc đổi semantics C1.

## Task hoặc bước tiếp theo

Review contract read này rồi giao UI C08 task riêng theo Figma. Effects xử lý finding, privacy/full-phone, correction hoặc công bố cần task/phê duyệt riêng; không suy từ counts/is_complete. P09I null/409 vì chưa có C1 và các assertion bỏ qua fake projection là lịch sử đã được P09K thay thế theo workflow C1 thật, không sửa/xóa tài liệu P09I/P09J.
