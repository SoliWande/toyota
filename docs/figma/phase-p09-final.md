# P09-FINAL — C07/C08 Toyota Community V1

## Mục tiêu
Hoàn thiện schema, atomic penalty writer, điểm Dealer tháng và Award draft/API; không đổi published snapshots hoặc tự công bố dữ liệu thật.
## Trạng thái
**Final UAT 12/11/2026: DONE — GO cho bước chuẩn bị production V1 tháng**, theo phạm vi và giới hạn ở mục Final UAT cuối tài liệu; không phải quyền deploy/công bố dữ liệu thật. Kết quả mới:711 tests/9.019 assertions PASS,0 FAIL/ERROR/SKIPPED; full regression nguyên bộ NOT_RUN vì chứa migration/reset/seeder.

**Nghiệm thu mới nhất 12/11/2026: DONE publication backend và kết nối controls C07 trong môi trường test; sẵn sàng UAT V1 tháng.** Các kết luận BLOCKED công bố bên dưới là lịch sử trước phê duyệt mới, đã được phần hoàn tất publication cuối tài liệu thay thế. Không đồng nghĩa deploy/cho phép công bố dữ liệu thật.

12/11/2026: **DONE schema/writer/điểm Dealer tháng/Award draft/API và kết nối danh sách gọi C07→C08. BLOCKED công bố snapshot mới** vì hai contract nghiệp vụ dưới đây chưa xác định. Không coi toàn bộ luồng publication là hoàn tất hoặc deploy-ready. Policy phạt V1 USER_APPROVED theo P09S, không hỏi/phê duyệt lại. Trạng thái khởi tạo IN PROGRESS đã được kết quả này thay thế.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS và nghiệm thu P09O–S, source scoring/Awards/ledger/tests. Các phase trước chưa commit, giữ lịch sử/edits. P09S chưa áp dụng DDL; chưa có writer/provider. P10A publish disabled vì chưa có contract kiểm tra/công bố.
## Các file source code liên quan
- Mới `app/Actions/CallAudit/ApplyDealerPenalty.php`; services `CallAudit/DealerPenaltyIntegrity.php`, `MonthlyDealerScores.php`, `DealerPenaltyReadModel.php`, `AwardPublicationReadiness.php`; `Admin/DealerPenaltyController.php`, requests `DealerPenaltyRequest.php`/`CallAuditFreezeRequest.php`.
- Tích hợp vào `LeaderboardService.php`, `AwardResults.php`, Admin AwardController/CallAuditWorkspaceController, routes; hai Blade C07/C08 và JS C08 hiện hữu. Không sửa A1/E1/E2/C1 writers, approved/verification models, published models/policies hoặc migrations P09S đã review.
- Mới `tests/Feature/AdminDealerPenaltyFinalTest.php`, `tests/Fixtures/p09_final_{snapshot,schema,penalty_worker,bootstrap}.php`, `tests/Browser/p09-final-c07.mjs`. Harness A3 chỉ mở rộng cleanup theo owned fixture IDs để xử lý FK ledger mới; AwardWorkflowTest chỉ cập nhật assertion publication404 cũ thành409 fail-closed.
- Chỉ tạo tracking này; không P09T/U/V/W, không sửa lịch sử O–S. Giữ các edits phase trước có sẵn.
## Những thay đổi đã thực hiện
Khởi tạo tracking trước source. Đã hoàn thành:

1. Preflight actual MySQL8.0.30/TCP127.0.0.1:3306/toyota_testing/FK checks1, audit rỗng và ba target chưa tồn tại. Backup SHOW CREATE TABLE/rows/checksums của19 bảng tại `storage/app/private/p09-final/before.json`; review34 SQL chỉ CREATE/ALTER đúng target. Áp dụng đúng ba migrations P09S theo thứ tự và ghi đúng3 migration records, không DDL bảng nghiệp vụ cũ.
2. Kiểm tra MySQL UNIQUE/FK/CHECK/engine, actors, restrictive deletes và encrypted DB round-trip bằng schema suite thật; không còn NOT_RUN như P09S.
3. Writer atomic: fresh active Admin/capability riêng, verify E1/E2 qua P09P, current confirmed/version/scope, frozen Dealer thống nhất.1 debit/submission/tháng, gắn evidence hai purpose; overlap không trừ hai lần. Actor/time server, encrypted intent/receipt, signed immutable entry/evidence + event chain, payload idempotency, rollback và race có kiểm chứng. Không sửa source/approval/verification, không trừ Sales.
4. Điểm Dealer **tháng đầy đủ**: raw COUNT giữ nguyên; ledger aggregate trước JOIN; signed net và display=max(0,net), score alias=display để callers hiện hữu dùng đúng. Rank display DESC/MAX(submitted_at) live ASC/id ASC; không threshold/loại Dealer hoặc đổi Sales/week/all-time. Dealer chỉ còn ledger vẫn có display0, MAX raw=null theo thứ tự NULL ASC hiện hành, không tự loại vì0/âm. Integrity lỗi thì fail closed, không âm thầm bỏ penalty.
5. C07 draft dùng điểm hiệu lực qua AwardResults, preview fingerprint gắn ledger event state nên không giữ hash cũ khi ledger đổi dù display đã0. Published vẫn đọc AwardWinner snapshot, không recalculate/update/delete hoặc tự đôn hạng.
6. Nút gọi C07 có sẵn dẫn đúng frozen batch award_top3 trong workspace C08 khi có batch; counts lấy C2 thật, không badge Đạt/loại giả. Workspace hỗ trợ scope purpose/batch, GET không claim. Giữ text/controls/layout Figma, không màn/modal/nút mới; publish vẫn disabled.

API mới (web/auth/auth.session/role:admin/account.active/throttle, POST có CSRF):

| API | Hành vi |
|---|---|
| POST `/admin/call-audits/freeze` | A3 create/freeze explicit, purpose/month/idempotency_key/award_id hợp lệ; không client seed/cutoff/gen2, retry không reroll |
| POST `/admin/call-audits/{batch}/findings/{item}/penalty` | Apply hoặc gắn evidence đã verified, required month/purpose/resolution_version/key; không nhận points/actor/closed flag |
| GET `/admin/call-audits/penalties?month=YYYY-MM` | Allowlist debit/audit counts, không ciphertext/reason/seed/PII |
| GET `/admin/call-audits/dealer-scores?month=YYYY-MM` | raw/penalty/net/display/rank thật |
| GET `/admin/awards/{award}/publication` | Readiness/preview hash/counts/blockers thật; không mutation |
| POST `/admin/awards/{award}/publish` | **409 publication_business_contract_required**, không ghi snapshots; client approved boolean/field lạ422, không bypass |
## Quyết định kỹ thuật quan trọng
- Apply lock theo intake mutex hiện hữu → fresh actor shared lock → monthly batch locks → historical Dealer/entry, serialize cả hai purpose và freeze, Laravel retry transaction3. Không giữ khóa trong cuộc gọi, không tăng item.row_version C1. Same actor/key/payload trả receipt commit cũ sau kiểm quyền/scope/integrity; khác payload409; request khác chỉ thêm event/evidence, không debit mới. Mutex làm giảm throughput, chưa benchmark production volume.
- Signature/key IDs bảo vệ receipt và sealed debit/evidence đã verified tại apply. Ledger reads không lấy lại cohort hoặc gọi sampling. Đây là chứng cứ đã commit tại thời điểm apply, không sửa/đoán lại history khi nguồn live thay đổi; không tuyên bố chống toàn quyền DBA/app-key compromise.
- SQL dùng CAST AS SIGNED trước subtraction để tránh unsigned underflow. Population giữ raw participants và historical debit targets, không tự đưa mọi Dealer0 chưa tham gia vào ranking. Khi không còn raw row, raw COUNT toán học0, display0; không xóa phần phạt vượt raw hoặc tự mang sang tháng khác.
- Tiêu chí công bố không được suy từ C1 queue completed/finding_pending, approved penalty hoặc tháng đã qua. API trả actual progress, empty/remaining calls, pending submissions, unapplied confirmed và final Top3 khác audit baseline; hai contract chưa chốt luôn khóa publication. Không đòi ngưỡng[x]% để kích hoạt chính sách flat1 đã duyệt, không tự loại Dealer theo threshold.
- Không migrate/seed/reset/deploy production; DDL test này là ngoại lệ rõ ràng được task cho phép, không chạy migration khác. Không thực hiện công bố thật, kể cả fixture publication trong tests chỉ là chuẩn bị snapshot cách ly.
## Kết quả kiểm thử
**Kết quả mới, không cộng kết quả lịch sử O–S:**

| Kiểm tra | Thực tế |
|---|---|
| Preflight/schema backup/targeted SQL/apply | PASS `php tests/Fixtures/p09_final_snapshot.php before`, `p09_final_schema.php review`, `p09_final_schema.php apply`;34 statements/3 tables, migration records+3 |
| Integration command | `php vendor/bin/phpunit --bootstrap tests/Fixtures/p09_final_bootstrap.php tests/Feature/AdminDealerPenaltyFinalTest.php tests/Feature/DealerPenaltySchemaTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/AdminShellTest.php tests/Feature/AdminCallAuditUiTest.php --no-progress --display-skipped --log-junit storage/app/private/p09-final/tests-integration.xml`: **62 PASS/791 assertions**,0 skipped/fail |
| Related command | C1 Record/E1 Finding/E2 Resolution/C2 ReadIntegration/P09P Impact/P09Q Simulation + DealerPenalty model/policy/preparation/ranking pure tests: **296 PASS/2.375 assertions**,0 skipped/fail; JUnit `storage/app/private/p09-final/tests-related.xml` |
| Tổng hai suites chọn lọc | **358 tests / 3.166 assertions PASS**, không full regression |
| Concurrency mới | Ba ca MySQL thật, hai processes/CONNECTION_ID khác nhau: same request chờ462,39ms, hai Admin cùng finding459,30ms, hai purpose cùng source476,43ms. Chỉ một entry, đúng1/2 evidence và idempotent events. C1/E2 races liên quan vẫn PASS |
| Syntax/Pint/build | Syntax20 PHP mới/sửa và JS/browser script PASS; Pint đúng PHP scope PASS; `npm run build` PASS88 modules |
| Browser Desktop1440 | C08 real Laravel fixture server: claim/release/record/CSRF/tabs, retry/conflict/privacy/toast-after-commit và edges PASS. C07 replay authorized Laravel fixture response, local asset interception, counts/call URL/publish disabled/no overflow/no pageerror PASS; không gọi replay là live C07 session |
| Postflight | `php tests/Fixtures/p09_final_snapshot.php after`: changed_business_tables=[], audit4 bảng0 (guard kiểm), penalty3 bảng0; migration rows+3.2.265 fixture journals cleaned=true. Chỉ4 app files cũ thay có chủ đích: AwardController, CallAuditWorkspaceController, AwardResults, LeaderboardService; protected app sources còn lại giữ hash. QA server8776 không còn listen |

Bootstrap khóa DB127.0.0.1/toyota_testing trước PDO, cấm mọi DDL/reset trong regression và đặt RefreshDatabaseState migrated=true để legacy AwardWorkflow chỉ transaction/rollback trên schema có sẵn; không migrate:fresh. Schema test opt-in được task hiện tại cấp phép, guard/rollback và A3 parent fixture cleanup theo tag/IDs. Worker credentials chỉ structured env, không argv/output. Backup/schema hash bỏ AUTO_INCREMENT counter, không reset counters.

Đã xem ảnh Figma C07 `164:867` session `e3d07f6b-b2b5-4da0-b39a-8345af8e1e88` và ảnh app: [.laravel-tools/p09-final-browser/c07-call-audit-1440.png](../../.laravel-tools/p09-final-browser/c07-call-audit-1440.png), [C08 finding](../../.laravel-tools/p09-final-browser/c08-finding-pending-1440.png). Các ảnh cùng thư mục có Correct/Wrong/no-answer/recall/completed/success và edge states. Data là fixtures, không runtime mẫu/khách thật. Layout/CSS không thay; không tuyên bố data states/badge xử phạt chưa có contract là pixel-perfect.

Lỗi kỹ thuật đã xử lý: runner SQL ban đầu còn guard mode before/after; Blade inline @else dính chữ làm parse lỗi; PowerShell đọc/ghi JS sai UTF-8 làm browser không tìm đúng nhãn. Đã sửa và chạy lại checks/browser tới PASS, không nới nghiệp vụ để chiều tests.
## Những lỗi còn tồn tại
Không còn tests FAIL/SKIPPED trong các suites trên. **Chưa hoàn thành action tạo published snapshot/publication thật**, không có cách client bật publish. Hai quyết định nghiệp vụ thực sự còn thiếu:

1. Mốc chốt/as-of publication và xử lý hồ sơ backdated/duyệt muộn hoặc penalty sau cutoff. A3 chỉ chứng minh review complete as-of freeze, chưa phải durable close kỳ.
2. Chấp nhận “Top3 đã kiểm tra xong” trên **Top3 cuối sau penalty**: nếu đổi Dealer cần mẫu bổ sung/generation nào; no-answer2 và underfill được chấp nhận thế nào khi công bố. Generation2/reroll tự động chưa được duyệt; zero sample không được coi hoàn tất.

Không yêu cầu duyệt lại confirmed/1điểm/unique C07-C08/Dealer-only/Sales unchanged/floor0/rank display đã duyệt. Chưa benchmark tải production hoặc triển khai key rotation/retention vận hành; không dùng các giới hạn đó để tự chặn phần đã hoàn thành. Không thêm UI xử lý finding/penalty ngoài Figma.
## Task hoặc bước tiếp theo
Đủ nghiệm thu phần schema/penalty/ranking/draft/C07→C08 trong môi trường test. Publication giữ fail-closed đúng yêu cầu cho đến khi hai contract trên được quyết định; không yêu cầu prompt/phase trung gian mới, không deploy production hoặc công bố dữ liệu thật trong lượt này.

### Tiếp tục hoàn tất publication — 12/11/2026
**IN PROGRESS. USER_APPROVED** contract khóa snapshot sau tháng/pending=0 và bổ sung kiểm tra Dealer mới vào Top3. Hai blocker hợp đồng ở nghiệm thu trên được thay thế bởi phê duyệt này, giữ nguyên lịch sử. Triển khai trong cùng P09-FINAL; không yêu cầu chữ ký TMV riêng. Chưa có kết quả kiểm thử mới tại thời điểm mở cập nhật.

### Hoàn tất publication — 12/11/2026

**DONE phạm vi V1 tháng trên toyota_testing.** USER_APPROVED của người được TMV ủy quyền thay thế hai blocker hợp đồng cũ; không còn dùng `publication_business_contract_required` để khóa phần đã duyệt.

**Thay đổi và nghiệp vụ:**

- `AwardPublicationReadiness` kiểm tháng kết thúc theo bounds cũ, pending/review timestamp, cohort hợp lệ, reproduction frozen manifests, consistency attempts/projections và chữ ký receipt **mọi outcome của cả C07/C08**, E1/E2 hiện hành, confirmed chưa có ledger, attribution mâu thuẫn và coverage Top3 hiệu lực. C07 zero sample không hoàn tất; underfill lấy thực tế; no-answer2 hoàn tất gọi nhưng không thành Correct. C08 giữ counts/coverage riêng; finding unresolved của cả hai purpose đều chặn công bố, không cộng mẫu C08 vào coverage C07.
- Mở rộng `CreateAndFreezeBatch`, `SamplingPlanner`, `ReproductionVerifier`: supplemental chỉ cho Dealer Top3 chưa từng có stratum C07; giữ nguyên batch/seed/attempt cũ, lưu predecessor/generation/covered IDs trong manifest có chữ ký, deterministic replay và receipt idempotency. Mỗi generation phải thêm Dealer mới; không reroll stratum rỗng hoặc Dealer quay lại Top3. **Mặc định kỹ thuật V1: tối đa 64 generation, tính cả generation1**; đạt giới hạn thì BLOCKED, không tự tăng giới hạn hoặc giả lập completed. Mẫu mới vẫn dùng A2 approved/phone/demo/duplicate guard. Top3 được tính lại sau penalty từ mẫu bổ sung; Dealer quay lại có coverage hợp lệ được tái sử dụng.
- Mới migration `2026_11_12_000301_create_award_publications_table.php`, model `AwardPublication`, action `PublishAuditedAward`. Bảng mới InnoDB, UNIQUE award/month/actor+request; hai FK RESTRICT, ba CHECK generation1–64/month/cutoff. Evidence/receipt mã hóa, HMAC gắn actor/policy/cutoff/ledger event cutoff/generations/manifests/event digests/source state/winners. Model cấm update/delete. Không sửa schema Award hoặc bảng nghiệp vụ cũ.
- Publish top-level transaction RR/SERIALIZABLE, intake mutex chung với freeze/penalty → actor/Award → monthly batches → source rows/ranking joins/ledger; khóa batch serialize với C1/E2. Tính lại readiness/results phía server sau locks; fingerprint client chỉ là optimistic check, không là approval. Tạo Dealer/Sales AwardWinner snapshot và publication evidence, đánh dấu Award published **cùng transaction**. Retry cùng actor/key/payload trả receipt đã verify; khác payload409, publish lần hai409. Request key publication canonical lowercase. Cutoff mới giữ microsecond; so sánh Award timestamp theo precision giây hiện hữu. Snapshot đóng kết quả, không cấm intake sau công bố; dữ liệu duyệt muộn/backdated/penalty sau đó không cập nhật winners.
- `DealerPenaltyController`/routes: POST `/admin/awards/{award}/supplemental` (chỉ key), POST `/admin/awards/{award}/publish` (key, optional preview_fingerprint), GET readiness cũ; auth/session/active Admin/policy/CSRF/throttle hiện hữu. Không nhận điểm/rank/winner IDs/approved boolean; response allowlist/no-store, không seed/manifest/reason/PII.
- `AwardController`, Blade C07, mới `resources/js/admin-awards.js` và Vite entry: gộp đúng coverage qua các generation, nút **Mở danh sách gọi** thực hiện initial/supplemental POST khi cần rồi mở đúng batch C07 trong C08; GET không tạo batch. Nút **Công bố kỳ** chỉ enabled khi readiness thật đạt, chống double-click, giữ request key khi retry, chỉ reload sang badge Đã công bố sau response commit. Không thêm control/modal/toast/text/CSS, không sửa JS/CSS/workflow C08. `FindingEvidence` chỉ mở quyền gọi verifier receipt hiện hữu cho readiness; không đổi C1/E1/E2 nghiệp vụ.

**Kiểm thử mới thực chạy (không cộng kết quả nghiệm thu lịch sử):**

| Kiểm tra | Kết quả |
|---|---|
| Preflight/backup/SQL/DDL | `php tests/Fixtures/p09_publication_snapshot.php before`; `p09_publication_schema.php review/apply`: MySQL8.0.30, TCP127.0.0.1:3306/toyota_testing/FK1, backup22 bảng tại `storage/app/private/p09-final-publication/before.json`; đúng9 SQL/1 migration/1 bảng mới, không chạy migrations khác |
| Integration | Guard bootstrap `tests/Fixtures/p09_final_bootstrap.php`; `AwardPublicationFinalTest`, `AdminDealerPenaltyFinalTest`, `DealerPenaltySchemaTest`, `AwardWorkflowTest`, `AdminShellTest`, `AdminCallAuditUiTest`, `CallAuditFreezeTest`, `CallAuditFreezeConcurrencyTest`, `CallAuditSamplingTest`, `CallAuditSamplingAlgorithmTest`: **158 PASS/2.122 assertions**,0 skipped/fail; JUnit `storage/app/private/p09-final-publication/integration.xml` |
| Related | C1 Record/E1 Finding/E2 Resolution/C2 ReadIntegration/Impact/Simulation và năm unit suites ledger/model/policy/projection/ranking như lệnh Related lịch sử, cùng guard: **296 PASS/2.375 assertions**,0 skipped/fail; `related.xml` |
| Schema mở rộng sau suite | Ca `AwardPublicationFinalTest::test_new_schema_constraints_encryption_and_append_only_history` thêm kiểm UNIQUE month và actor/request độc lập, FK RESTRICT: chạy lại **1 PASS/22 assertions**,0 skipped/fail; `schema-extra.xml`. Không cộng rerun thành test mới vào454 tests của hai suites trên |
| Concurrency publication mới | **7 ca MySQL thật**, hai processes/CONNECTION_ID khác nhau, assert contender chờ>250ms: same-key publish, hai Admin publish, penalty→publish, supplement→publish, publish→penalty, cùng request supplement và hai Admin supplement. Một snapshot/generation hợp lệ; race chạm readiness mới bị409. Ba penalty races và hai A3 races cũ vẫn PASS |
| Browser Desktop1440 | Laravel thật, Chrome headless: C07 ready/publish-error/in-flight/double-click/published-after-commit và call→POST supplemental→đúng workspace C08 PASS. C08 cả hai browser suites cũ PASS; không fake success. Figma C07 `164:867` đã xem lại session `4c657677-9a9f-48ba-a16d-3749e6c470e9`; ảnh `.laravel-tools/p09-final-publication-browser/c07-{ready,published,needs-supplement}-1440.png`, `c08-supplement-1440.png` |
| Syntax/Pint/build/diff | Syntax20 PHP scope PASS; Pint scope PASS; JS/browser syntax PASS; `npm run build` PASS89 modules; tracked diff và11 file mới whitespace PASS |
| Postflight | `php tests/Fixtures/p09_publication_snapshot.php after`: changed_business_tables=[], audit4/penalty3/publication đều0, migration records+1;3.002 fixture journals cleaned=true, không QA server listen8776/8777. Source hashes chỉ đổi7 app files có chủ đích; scoring/approval/verification/Award/AwardWinner cũ giữ hash. AUTO_INCREMENT không reset |

Tests mới còn chứng minh tháng chưa hết/pending, source drift, tampered C07/C08 receipt, unresolved/needs_review/confirmed chưa penalty, dismissed, Top3 đổi rồi quay lại, underfill/zero, no-answer2, same-key conflict, encrypted round-trip, các UNIQUE/FK/CHECK thực, fresh blocked/non-Admin/guest, CSRF/IDOR/privacy, rollback cả sau winners và sau publication INSERT trước Award UPDATE, snapshot bất biến khi nguồn đổi sau công bố. Fixtures dùng guard/journal/scoped cleanup, bảng có self-FK được cleanup theo generation giảm dần; lỗi fixture cleanup ban đầu đã sửa và phục hồi đúng owned journal, không drop/nới constraint. Browser harness sửa lookup Unicode/response bị reload, đã chạy lại tới PASS.

**Giới hạn và kết luận:** Không còn blocker nghiệp vụ đã phê duyệt trong V1 tháng; sẵn sàng kiểm thử nghiệm thu trước production. Không triển khai weekly publication, correction/reversal, threshold[x]%/loại Dealer, hoặc UI xử lý finding/penalty ngoài Figma. UI lỗi/conflict/generation cap/zero coverage chưa có frame riêng: API trả blocker an toàn, controls không giả lập thành công, không thêm thông báo. Enabled publish dùng control/style hiện hữu vì chưa có variant thiết kế riêng; không tuyên bố mọi data state pixel-perfect. Chưa benchmark tải lớn hoặc triển khai rotation/retention vận hành. Toàn bộ publication trong tests là fixtures đã cleanup; không công bố dữ liệu thật, không kết nối/deploy production, không migrate toàn bộ/seed/reset/full regression. Bước tiếp theo là UAT, không cần phase/prompt triển khai trung gian mới.

### Final UAT — 12/11/2026

**IN PROGRESS.** Giữ nghiệm thu phát triển ở trên; chưa có kết luận UAT mới. Rà soát source/harness thực tế: full suite có `DatabaseMigrations` thực thi migrate:fresh và tests seed trực tiếp (`DatabaseFoundationTest`, `Development*SeederTest`, `Toyota*SeederTest`, ...), nên **NOT_RUN full regression nguyên bộ** theo điều kiện an toàn của task. Dùng bootstrap guard no-DDL, suite Phase9 và upstream được rà soát; bổ sung kịch bản xuyên suốt tạo pending → duyệt thật → freeze/call/resolution/penalty/supplement/publish. Không tạo phase hoặc tracking mới.

#### Kết quả Final UAT

**DONE — GO cho chuẩn bị production trong phạm vi C07/C08 V1 tháng.** Kết luận này thay thế trạng thái IN PROGRESS ở đoạn mở mục UAT, không xóa lịch sử nghiệm thu. Không cấp quyền deploy, migrate production hoặc công bố dữ liệu thật.

**Kịch bản thực chạy:** Mới `tests/Feature/FinalCallAuditUatTest.php`:13 ca, gồm một luồng xuyên suốt và12 tổ hợp guest/role/status bị từ chối. Fixture có tag/ID journal, tạo9 CustomerSubmission pending bằng Laravel model (Sales3, QR6), duyệt qua POST thật với CSRF thật; không mock approval/scoring. Ghi9 moderation reviews, điểm Dealer3/2/2/2, Sales3, tháng kế tiếp không nhận nhầm điểm. Ghi bằng chứng thành viên qua SubmissionVerification thật rồi giữ nguyên source/review/group state suốt Call Audit/Penalty/Publication.

Freeze C08 requested60/actual9 và C07 requested15/actual7 qua API, retry không reroll; claim/record qua POST có owner/token/version và double-submit. Ghi Correct/Wrong/No-answer; needs_review→confirmed, confirmed và dismissed; no-answer lần2 sau24h mô phỏng đồng hồ test, vẫn không phải Correct. Hai submission bị confirmed tạo đúng2 debit dù một hồ sơ có finding ở cả C07/C08 (3 evidence); Dealer gốc raw3/penalty2/display1, Sales vẫn3. Top3 đổi sang ba Dealer điểm2; POST supplemental generation2 requested5/actual2, gọi/ghi kết quả hai item, reproduction cả ba batch VERIFIED, readiness đạt. Publish bằng fingerprint server, retry nhận receipt cũ; lưu3 Dealer winners +1 Sales internal winner, HMAC publication verify thành công. Sau công bố, ghi tiếp finding C08 còn lại và penalty thứ3 thật trên DB test, thêm hồ sơ tháng cũ; winners/evidence đã công bố vẫn nguyên vẹn. Public Award không lộ customer phone/name. Luồng này là HTTP/service E2E bằng fixtures, không phải cuộc gọi điện/Facebook thật hoặc manual UAT của TMV.

**Lệnh và kết quả mới (không cộng lịch sử/rerun):** Tất cả PHPUnit dùng `--bootstrap tests/Fixtures/p09_final_bootstrap.php --no-progress --display-skipped`, APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ file test không tồn tại. Schema suite opt-in `TOYOTA_RUN_DEALER_PENALTY_SCHEMA_TESTS=1`, chỉ kiểm schema có sẵn, không DDL.

| Kiểm tra | PASS / FAIL / SKIPPED thực tế |
|---|---|
| Preflight + backup | PASS `php tests/Fixtures/p09_final_uat_snapshot.php before`: MySQL8.0.30, TCP127.0.0.1:3306, actual database toyota_testing, FK1/InnoDB; backup DDL/rows/checksum/source hashes của23 bảng tại `storage/app/private/p09-final-uat/before.json` trước fixtures |
| Toàn bộ suites Phase9 | `AdminCallAudit*Test.php`, `CallAudit*Test.php` trong Feature; `CallAudit*`, `DealerPenalty*`, `ProposedPenalty*` trong Unit; thêm FinalCallAuditUat/AwardPublicationFinal/AdminDealerPenaltyFinal/DealerPenaltySchema/AwardWorkflow/AdminShell: **550 PASS / 5.806 assertions / 0 FAIL-ERROR-SKIPPED**; JUnit `storage/app/private/p09-final-uat/phase9.xml` |
| Upstream an toàn sau sửa test lỗi thời | LeaderboardService/CustomerSubmission/V1Workflow/SubmissionDuplicate/ControlledDuplicateApproval/DealerAuthorization/Authentication/SecurityAudit/CompetitionLeaderboard/CompetitionAwards/PublicPrivacy: **161 PASS / 3.213 assertions / 0 FAIL-ERROR-SKIPPED**; `upstream.xml` |
| Tổng hai suites | **711 PASS / 9.019 assertions**, không tính lại13 UAT hoặc35 ca chạy lại để sửa harness/assertions |
| Concurrency | Các ca MySQL thật hai processes/connections của A3/C1/E2/Penalty/Publication trong suite Phase9 đều PASS, gồm7 publication/supplement races; không thay bằng mock concurrency |
| Browser Desktop1440 | Chrome headless + Laravel fixture server thật: C07 ready/error/in-flight/double-click/published-after-commit và supplemental→đúng workspace; C08 claim/release/record/tabs/masking/full-phone/recall/conflict/lease/empty/last-item/toast-after-commit PASS; không pageerror/overflow. Artifacts mới `.laravel-tools/p09-final-uat-browser/` (C07 captures được copy từ output browser publication vừa chạy) |
| Figma / build / định dạng | Đã đọc lại ảnh C07 `164:867`, C08 `165:832`, session `4785d24c-8047-43eb-b438-40dcb3edca1e`, đối chiếu ảnh app1440. `npm run build` PASS89 modules; syntax/Pint6 PHP mới/sửa PASS; diff check PASS |
| Full regression toàn repository | **NOT_RUN**, không gọi là SKIPPED PHPUnit hoặc PASS: các lớp DatabaseMigrations gọi migrate:fresh/rollback và nhiều tests gọi seeder trực tiếp. Bootstrap no-DDL bảo vệ suites được chọn, không biến toàn suite có seeder thành được phép chạy. Không sửa/disable những test đó để gọi kết quả là full regression |
| Postflight | PASS `php tests/Fixtures/p09_final_uat_snapshot.php after`: changed_business_tables=[], source_changes=[], migration_rows_added=0; audit4 + penalty3 + publication đều0; toàn23 bảng giữ row counts/checksums/schema,3.415 journals cleaned=true, không listener8776/8777. Không reset AUTO_INCREMENT |

**Những lỗi đã sửa:** Không phát hiện lỗi runtime Phase9 cần sửa trong lượt UAT; source app/routes/policies/views/scoring/schema giữ nguyên hash. Chỉ sửa test/harness:

- Mới `FinalCallAuditUatTest.php`, `p09_final_uat_snapshot.php`; `CallAuditFreezeFixtures` cleanup thêm moderation/verification children **chỉ theo owned submission IDs** trước xóa parents để hỗ trợ duyệt/xác minh thật. Trong quá trình dựng UAT, sửa sort key bảng verification_states (PK submission_id), URL đọc batch cho đúng route thật, snapshot model sau fresh; giữ CSRF/throttle, mô phỏng10 giây/operator action thay vì tắt throttle khi test bắn nhanh nhận429.
- `V1WorkflowTest`: bổ sung phone đăng ký bắt buộc; assert Admin không được duyệt đăng ký Sales, dùng Dealer active cùng Dealer theo UserPolicy/ReviewAccountRegistration hiện hữu; dữ liệu kiểm Facebook trùng dùng phone/plate riêng để không đụng guard intake trước bước cần kiểm. Không sửa policy hoặc validation để chạy test.
- `SubmissionDuplicateTest`: thay kỳ vọng cũ cho phép cùng phone được approved hai lần bằng hai ca rõ ràng: cùng tên nhưng khác phone/profile được duyệt; phone alias trùng bị intake từ chối, không tạo hồ sơ/review mới hoặc sửa record cũ. Contract đối chiếu CustomerSubmission/SubmissionIntake/ApproveCustomerSubmission hiện hành và ControlledDuplicateApproval.
- `ControlledDuplicateApprovalTest`: cập nhật assertion C02 lỗi thời `identityConflicts`/inline-edit IDs sang selected/noDuplicates, PATCH JSON422 và field-error target của modal hiện hữu; vẫn kiểm warning generic trên route legacy, không lộ khách khác, validation không sửa source. Không thêm modal/UI hoặc thay cách xử lý business. Hai FAIL/ERROR upstream ban đầu và lỗi assertion C02 khi đối chiếu đã được chạy lại tới PASS (35/422); không che giấu bằng bỏ test.

**Giới hạn và GO/NO-GO:** GO cho bước chuẩn bị production của **V1 tháng** đã được phê duyệt; chưa phải nghiệm thu toàn repository hoặc GO deploy. Full regression nguyên bộ chưa chạy vì lý do an toàn nêu trên. Đối chiếu Desktop xác nhận bố cục/controls/workflow và dữ liệu thật; không tuyên bố pixel-perfect mọi state: C07 còn chênh lệch cách trình bày bước/badge so với ảnh mẫu và publish enabled giữ style cũ vì chưa có variant rõ, cần review thị giác trước phát hành. Không mô phỏng hàng loại Dealer/threshold[x]% ngoài policy. C08 warning Sai theo wording V1 đã duyệt, không dùng lời Figma cũ như đã hủy xác minh/trừ điểm. Không thay calendar/timezone để làm giống giờ00:00 trong ảnh. Manual UAT, tải production, key rotation/retention và quy trình vận hành vẫn cần kiểm tra trước phát hành; weekly publication/correction/reversal ngoài phạm vi. Không kết nối production, không chạy migration/seed/reset, không thêm phase hoặc báo cáo trung gian.

### Production Readiness — 12/11/2026

**IN PROGRESS.** Rà soát source/release/config và thiết kế trong cùng tracking; không kết nối production, không DDL hoặc deploy. Working tree sạch trước task, HEAD `c5251f2`; trạng thái chưa commit trong nghiệm thu cũ là lịch sử, không còn phản ánh checkout hiện tại. Chưa xác minh hạ tầng/key/backup-restore production.

#### Cập nhật rà soát — 13/11/2026

**NOT READY để deploy production.** Đã hoàn thành rà soát tĩnh và kiểm tra an toàn dưới đây; còn các điều kiện phát hành chưa xác minh. Final UAT **711 tests/9.019 assertions PASS là kết quả lịch sử 12/11**, không chạy lại trong lượt này. Giữ GO chuẩn bị V1 tháng; không mở phase, đổi nghiệp vụ, deploy, migrate, ghi dữ liệu hoặc công bố Award thật. Chỉ cập nhật mục Production Readiness của tài liệu này, không sửa source hay tạo tài liệu trung gian; ưu tiên yêu cầu cụ thể của task so với quy tắc tạo tracking mới trong AGENTS.

**Hiện trạng và bản release**

- Đọc trực tiếp `.git/HEAD` và branch ref: `upgrade/laravel-13`, `c5251f2ff95208ffacade0a11f8e12954a49605d`. Tuy nhiên `git status`, `log`, `diff`, `ls-files` đều bị **dubious ownership**; thử cấu hình theo process cũng không thành công, không sửa global/repository config. Vì vậy phát biểu “working tree sạch” ở đoạn mở là lịch sử, **chưa tái xác minh được**; chưa kết luận đầy đủ file đã commit, đã push hay đã nằm trong release. Ref local không chứng minh trạng thái remote hiện thời; không fetch/network Git.
- Trước phát hành, chủ repository cần xử lý ownership trong môi trường được tin cậy, kiểm `git status --short`, `git diff --check`, `git log -1`, SHA/tag release và nội dung artifact. Không triển khai từ working tree chưa kiểm chứng. Đối chiếu artifact với đúng source đã UAT; nếu khác, xác định phạm vi kiểm thử lại trên DB cách ly.
- Artifact phải gồm routes, actions, controllers, FormRequests, models, policies, services, Blade, CSS/JS, 8 migrations, `composer.lock`, `package-lock.json`, `vite.config.js`, `public/build` và static icons/assets được Blade/CSS tham chiếu. Không đưa `.env`, fixtures/test servers, `.laravel-tools`, backup hoặc tài liệu chứa dữ liệu riêng vào webroot public. `vendor` phải được cài từ lock; build assets bị `.gitignore` nên commit source không đủ để có artifact chạy được.

**Migration và dữ liệu cũ — review source, chưa thực thi**

| Thứ tự bắt buộc | Migration trong `database/migrations/` | Phụ thuộc và nhận xét |
|---|---|---|
| 1–4 | `2026_11_11_000101_create_call_audit_batches_table.php`; `000102_create_call_audit_items_table.php`; `000103_create_call_audit_attempts_table.php`; `000104_create_call_audit_events_table.php` (cùng prefix ngày) | Batches → items → attempts → events; batches có self-FK predecessor; FK đến awards/users/dealers/customer_submissions và composite FK item/batch, attempt/item. |
| 5–7 | `2026_11_12_000201_create_dealer_penalty_entries_table.php`; `000202_create_dealer_penalty_evidence_table.php`; `000203_create_dealer_penalty_events_table.php` (cùng prefix ngày) | Entries → evidence → events; evidence phụ thuộc đủ Call Audit và resolution/call events; UNIQUE submission/tháng ngăn debit trùng. |
| 8 | `2026_11_12_000301_create_award_publications_table.php` | FK awards/users; UNIQUE award, tháng, actor/request; CHECK generation 1–64, tháng, cutoff; publication runtime cần cả audit/ledger. |

- Cả 8 `up()` chỉ CREATE bảng mới và ALTER CHECK trên chính bảng mới; không backfill/update/delete dữ liệu nghiệp vụ cũ. `down()` đều drop bảng, không có guard giữ lịch sử: **không dùng rollback migration như phương án rollback vận hành**.
- Baseline phải có các migration cũ, đúng kiểu unsigned BIGINT/index/engine của FK, các cột source/attribution/verification hiện hành và **`submission_intake_mutex.id=1`** từ migration `2026_11_03_000023`. Đây là dependency runtime của freeze/penalty/publication; không tự seed lại nếu thiếu. So sánh migration records với SHOW CREATE TABLE trên bản restore và production do người vận hành kiểm; baseline thiếu/drift phải có kế hoạch riêng được review, không chạy toàn bộ pending migrations theo suy đoán.
- 8 bảng mới rỗng không làm thay đổi winners đã công bố. Award cũ đã published vẫn đọc `AwardWinner` snapshot; không tạo publication evidence giả cho lịch sử cũ. Bảng mới có FK restrictive/NO ACTION nên thao tác xóa users/dealers/submissions/awards đã được tham chiếu có thể bị chặn; cần đưa vào hướng dẫn vận hành.
- DDL dùng MySQL-specific CHECK/REGEXP/MOD, JSON, composite FK, DATETIME(6); ranking dùng CAST AS SIGNED/window functions. Nền đã UAT là MySQL 8.0.30/InnoDB. Production phải xác minh phiên bản tương thích, CHECK thực sự enforced (MySQL trước 8.0.16 không đủ), FK checks bật, SQL mode/charset/collation, default engine InnoDB, đủ quyền DDL và disk/metadata lock budget. Không coi SQLite/MariaDB tương đương khi chưa rehearsal. Freeze/publication yêu cầu transaction isolation REPEATABLE-READ hoặc SERIALIZABLE.
- MySQL DDL có implicit commit: nếu CREATE thành công nhưng ALTER CHECK thất bại, migration có thể chưa được ghi trong `migrations` dù bảng đã tồn tại. Dừng ngay, lưu lỗi/SHOW CREATE/migration records; **không chạy lại mù, drop bảng hoặc tự chèn migration record**. DBA đối chiếu schema và dữ liệu, review repair tiến tới schema đúng. Rehearsal cả trường hợp lỗi giữa chừng trên DB restore cách ly.
- Code monthly ranking/draft đọc ledger trực tiếp, không fallback khi thiếu bảng. Phải chặn traffic/writers trong thời gian đổi code/schema; không để node mới nhận request trước khi đủ schema.

**Cấu hình, quyền và bảo mật**

- Runtime theo `composer.json`: PHP **^8.5**, Laravel **^13.0**. Kiểm `composer check-platform-reqs --no-dev` trên máy đích; PDO MySQL, OpenSSL, mbstring và các extensions theo lock phải đầy đủ. Node local kiểm tra là 22.23.4/npm 10.9.9; build bằng lock trên CI, không copy `node_modules` sang production.
- `config/app.php`: `APP_KEY`, AES-256-CBC; **giữ nguyên chính xác chuỗi APP_KEY hiện hành**, cùng secret trên mọi web/worker node. `SourceFingerprint::keyId`, HMAC của Call Audit/DealerPenalty/Publication dựa trên khóa hiện tại; đổi khóa hoặc đổi biểu diễn chuỗi khóa có thể làm fail integrity dù giải mã còn được. `config/app.php` chưa khai báo `previous_keys`; chỉ đặt `APP_PREVIOUS_KEYS` không giải quyết xác thực HMAC. Không chạy `key:generate`, không rotate trong đợt deploy này. Backup key/cấu hình mã hóa riêng với quyền hạn chế, khôi phục đúng key cùng DB; không ghi giá trị secret/ciphertext/PII vào log hoặc báo cáo.
- Trước mở traffic, trên bản restore dùng key tương ứng: giải mã dữ liệu lịch sử hiện có và verify `ReproductionVerifier`, `DealerPenaltyIntegrity::verify`, `PublishAuditedAward::verify` theo các record phù hợp. Không gọi `execute` để kiểm tra đọc. DB chưa có ledger/publication thì ghi “không có record để kiểm chứng lịch sử”, không tạo fake data; kiểm round-trip/chữ ký bằng fixture chỉ trên môi trường cách ly. Đọc ledger tháng và dealer-scores sau deploy phải thành công; lỗi key/integrity phải chặn, không bỏ penalty hoặc sửa chữ ký để vượt lỗi.
- Routes qua web/CSRF, auth + auth.session, role:admin, account.active, throttle; `CallAuditBatchPolicy`, `DealerPenaltyEntryPolicy`, `AwardPolicy` chỉ cấp quyền tương ứng cho **Admin active**. Capability penalty riêng nhưng V1 hiện cấp cho mọi Admin active, không có ACL vận hành riêng. Writers kiểm actor mới và khóa transaction; giữ nguyên contract này. Không suy từ `CallAuditBatchPolicy::publish=false` rằng Award publish bị khóa: publication dùng `AwardPolicy::publish` và readiness thật.
- `WorkspaceReadModel`: full phone cần đúng Admin/owned claim/token/version/lease chưa hết và source chưa drift; preview dùng phone masked. Controller penalty/publication trả allowlist, `no-store, private`, không reason/seed/manifest/ciphertext. Admin vẫn được thấy tên/biển số theo workspace được duyệt; không tuyên bố mọi PII bị ẩn. Kiểm HTTPS/proxy/cookie/CSRF và CDN không cache trang/API Admin; public Award không lộ customer contact. `AppServiceProvider` ép debug=false và secure cookie khi production, vẫn phải đặt APP_ENV=production, APP_DEBUG=false, APP_URL=https đúng host.
- `config/database.php` dùng **DATABASE_URL**, DB_HOST/PORT/DATABASE/USERNAME/PASSWORD/SOCKET; xác minh effective connection, không chỉ xem DB_DATABASE. Không dùng key hay DB test làm production. Tách tài khoản migration có DDL và runtime theo quyền cần thiết.
- Cache dùng **CACHE_DRIVER** (không phải chỉ CACHE_STORE), mặc định file; queue mặc định sync; session mặc định file. Nếu chọn database cache/jobs/sessions, phải kiểm các bảng tồn tại: repository chưa có migration tạo cache/jobs/sessions/job_batches; không tự sinh migration hay đổi driver. Redis/shared cache phải có prefix riêng; multi-node cần session/cache phù hợp. Restart/drain workers nếu dùng queue; Call Audit/penalty/publication hiện xử lý đồng bộ, không giả định cần job mới.
- Webroot phải là `public/`; web/worker user đọc code/assets, ghi được `storage/` và `bootstrap/cache/`, không chmod 777; giữ persistent uploads/private storage/session theo hạ tầng. Không expose `.env`, logs/backups; kiểm storage symlink và quyền public/private đúng mục đích. Demo/production-demo flags tắt, không chạy seed/demo commands. Backup ngoài webroot và có bản sao ngoài máy triển khai.

**Đối chiếu Desktop1440**

Đã đọc mới screenshot Figma [C07 164:867](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=164-867), [C08 165:832](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=165-832), session `cd366668-1188-45d8-a572-5d1504a6d904`, so trực quan với ảnh UAT có sẵn `.laravel-tools/p09-final-uat-browser/c07-ready-1440.png`, `c08-calling-1440.png` và source Blade hiện tại. **Không chạy UI/chụp browser mới**, ảnh app là fixture lịch sử, không phải kiểm chứng live checkout hay production.

- C08: khung sidebar/header, tabs, hai cột 420/692px, script, câu hỏi, outcomes và nút lưu nhìn chung tương ứng frame. Số liệu/tên thật khác fixture là hợp lệ; full phone phải theo claim. Warning Sai của Figma nói hủy xác minh/trừ điểm ngay thuộc contract cũ, không áp dụng lại lên V1 confirmed/ledger đã duyệt.
- C07: source và ảnh còn dùng bước 1 vòng tròn xám thay dấu check xanh của frame; bước 2 chưa có nền/viền active; period chưa published không có badge “Đang kiểm tra”; cột badge kết quả trống. Khung chính/table/actions tương ứng, nhưng **chưa nghiệm thu thị giác**. Enabled publish chưa có variant rõ. Trước release cần nghiệm thu các trạng thái có thật so với Figma; mọi sửa UI chỉ sau khi xác định đúng state/evidence và trong phạm vi được cho phép. Lượt này chỉ sửa tài liệu theo yêu cầu, không âm thầm sửa Blade/CSS.
- Không thêm loại Dealer/threshold[x]%/đôn hạng theo dữ liệu minh họa Figma; đây là khác biệt nghiệp vụ đã giải thích trong Final UAT. Placeholder threshold hiện vẫn nằm trong Blade C07: cần xác nhận nội dung hiển thị trước release, không coi đó là blocker nghiệp vụ mới hoặc tự đặt ngưỡng. Không đổi timezone/calendar chỉ để ảnh có 00:00 thay vì 07:00.

**Kiểm chứng mới, không ghi DB**

| Lệnh/kiểm tra | Kết quả và giới hạn |
|---|---|
| `php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/DealerPenaltyPolicyTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --do-not-cache-result` | **PASS 26 tests/341 assertions**, PHP 8.5.11/PHPUnit 12.5.40; đã đọc hai lớp extends PHPUnit TestCase thuần, không boot app/kết nối DB/migrate/seed. Chỉ chứng minh policy, không thay kiểm thử HTTP/DB. |
| `php -l` trên 8 migrations, actions CallAudit + PublishAuditedAward, services CallAudit + AwardPublicationReadiness, policies | **PASS 53 files**; chỉ syntax, không chạy migration. |
| `node --check resources/js/admin-awards.js`; `node --check resources/js/admin-call-audits.js` | **PASS**; không chứng minh browser workflow. |
| Đọc `public/build/manifest.json`, kiểm file/css/assets và 5 entry Admin/C07/C08 | **PASS** 49 entries, không thiếu file tham chiếu; `public/hot` không tồn tại. Không chứng minh build khớp source/SHA. `npm ci`/`npm run build` mới **NOT_RUN** để giữ lượt chỉ tài liệu; cần build sạch trong artifact release. |
| Git/release, production config/DB/backup/restore, tải/timeout | **CHƯA XÁC MINH**; Git bị ownership; không truy cập production. |
| HTTP smoke/browser mới, encrypted DB round-trip, rehearsal migration, full regression | **NOT_RUN**; không dùng kết quả lịch sử làm kết quả mới, không chạy fixtures hay bootstrap có DB trong lượt này. |
| Kiểm tài liệu và giới hạn thay đổi | **PASS**: hash toàn bộ phần trước Production Readiness giữ nguyên; hash source app/routes/config/migrations/resources và các manifest dependency/Vite giữ nguyên trước/sau sửa tài liệu. Code fences cân bằng, không trailing whitespace/conflict marker; đủ 8 đường dẫn migration allowlist tồn tại. Không thay thế được Git diff/release verification đang bị chặn. |

**Checklist trước deploy — tất cả mục chưa có bằng chứng phải đóng trước xác nhận GO**

1. [ ] Chốt immutable release SHA/artifact, sạch working tree, đủ source/dependencies/assets, release trước và đường chuyển lại; build sạch/kiểm platform. Xác nhận visual C07 và nội dung còn lệch, manual UAT phù hợp V1; rehearsal tải đại diện/lock timeout của intake mutex và đọc integrity ledger.
2. [ ] Người vận hành xác minh cấu hình/APP_KEY/driver/quyền/DB engine-isolation-baseline và các migration đã áp dụng; kiểm target không ở trạng thái DDL dở dang. Không yêu cầu gửi secret vào hội thoại.
3. [ ] Chốt cửa sổ maintenance, RPO/RTO/người quyết định rollback; chặn traffic ghi, drain/dừng workers/scheduler/import và các writer ngoài web. Backup nhất quán toàn DB (kèm triggers/routines/events/migration records), uploads/private storage, secret/config và artifact trước deploy; kiểm checksum, vị trí ngoài webroot, quyền và bản sao ngoài host.
4. [ ] Restore backup vào **database/host cách ly**, kiểm schema/constraints/counts/checksums, users/awards/winners, key/decryption và ledger/publication nếu đã có; rehearsal đúng 8 migrations theo pending thực tế, smoke V1 trên bản sao và đo thời gian. Không dùng script snapshot fixture UAT làm backup production hay restore đè DB đang phục vụ.
5. [ ] Khi đã có xác nhận triển khai: đưa maintenance, dừng writers, lấy backup cuối nhất quán; stage code/assets, kiểm migration status/SQL dự kiến, áp dụng allowlist từng migration còn pending, dừng ngay nếu lỗi; kiểm SHOW CREATE/FK/CHECK/migration records sau chạy. Chỉ mở traffic khi schema/code/cache cùng phiên bản.
6. [ ] Clear/rebuild cache có kiểm soát; không flush shared cache toàn hệ thống hoặc làm mất lock/idempotency khi writers đang chạy. Reload PHP/opcache, restart workers nếu có và kiểm quyền storage/assets. Chạy smoke bên dưới, lưu bằng chứng không chứa PII trước mở traffic/giám sát sau mở.

**Lệnh dự kiến — CHƯA THỰC THI, chỉ sau xác nhận deploy và checklist trên**

Ví dụ shell POSIX trên máy triển khai; `PRODUCTION_DB`, đường backup và MySQL login-path là placeholder phải được người vận hành thay/xác minh. Không đưa password lên command line. Restore-drill chỉ trỏ host/DB cách ly đã được chuẩn bị; nếu máy đích Windows thì chuyển cú pháp shell tương ứng.

```sh
# CI/release staging: đúng SHA/lock đã chốt, trước chuyển traffic
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build

# Maintenance toàn cụm + drain/dừng mọi writer trước backup cuối
php artisan down
mysqldump --login-path=production-backup --single-transaction --routines --triggers --events --hex-blob --set-gtid-purged=OFF --result-file=/secure-backups/pre-v1.sql PRODUCTION_DB
sha256sum /secure-backups/pre-v1.sql
# Diễn tập riêng trước deploy; tuyệt đối không trỏ restore-drill vào production
mysql --login-path=restore-drill --database=ISOLATED_RESTORE_DB < /secure-backups/pre-v1.sql

# Trong release directory, với env đúng đích và chưa phục vụ traffic
php artisan config:clear
php artisan migrate:status
```

Allowlist theo thứ tự; trên rehearsal và production chỉ giữ những migration **thực sự pending và chưa có bảng dở dang**. Review toàn bộ SQL `--pretend` trước, sau đó mới chạy vòng apply; lỗi bất kỳ phải dừng. Không chạy migrate toàn repository, test schema helper, migrate:fresh/refresh/reset/rollback hoặc seed.

```sh
set -e
MIGRATIONS='
database/migrations/2026_11_11_000101_create_call_audit_batches_table.php
database/migrations/2026_11_11_000102_create_call_audit_items_table.php
database/migrations/2026_11_11_000103_create_call_audit_attempts_table.php
database/migrations/2026_11_11_000104_create_call_audit_events_table.php
database/migrations/2026_11_12_000201_create_dealer_penalty_entries_table.php
database/migrations/2026_11_12_000202_create_dealer_penalty_evidence_table.php
database/migrations/2026_11_12_000203_create_dealer_penalty_events_table.php
database/migrations/2026_11_12_000301_create_award_publications_table.php'
for migration in $MIGRATIONS; do
  php artisan migrate --path="$migration" --pretend --force
done
# CHECKPOINT: review SQL/schema/backup và dừng nếu sai trước vòng apply
for migration in $MIGRATIONS; do
  php artisan migrate --path="$migration" --step --force
done
php artisan migrate:status
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Restart workers chỉ khi thực tế dùng queue và đã kiểm cache/prefix
php artisan queue:restart
# Switch release atomically/reload PHP-opcache theo hạ tầng đã rehearsal
# Smoke qua maintenance bypass được kiểm soát trước mở traffic
php artisan up
```

`--pretend` chỉ là review SQL dự kiến, không thay rehearsal/FK/CHECK thực tế. Việc chuyển release, dừng/khởi động workers/scheduler, storage link và reload PHP phụ thuộc hạ tầng chưa được cung cấp, chưa có lệnh host cụ thể được xác minh. Maintenance phải áp dụng toàn cụm; không mặc định `artisan down` trên một node đủ chặn mọi writer.

**Smoke sau deploy và rollback**

- Kiểm login active Admin, C07 `/admin/awards`, C08 `/admin/call-audits/workspace` ở 1440px; assets đúng manifest, không dev-server/404/overflow/JS error; cookie HTTPS/CSRF và guest/Dealer/Sales/blocked không đọc Admin API. Kiểm deny CSRF trên endpoint an toàn trong staging; không gửi POST hợp lệ có thể tạo dữ liệu production để smoke.
- GET có auth `/admin/call-audits/penalties?month=YYYY-MM`, `/admin/call-audits/dealer-scores?month=YYYY-MM`, `/admin/awards/{award}/publication` với tháng/ID hiện hữu đã chọn: response hợp lệ, no-store, không rò PII/evidence/key; ledger khớp trước deploy, raw/penalty/net/display và public leaderboard đúng. Readiness có blocker hợp lệ là bình thường, không yêu cầu `can_publish=true`. Public Award đã published giữ winners/score/cutoff/snapshot trước deploy. Không claim/record/penalty/supplement/freeze/publish thật để thử. Các luồng ghi/idempotency/race chỉ rehearsal trên bản sao cách ly.
- Trước có dữ liệu mới: rollback code/assets về release đã kiểm tương thích, **giữ schema additive và APP_KEY**; rebuild caches/reload PHP/workers đúng release. Không cần drop 8 bảng để quay code. Phải chắc bản cũ không tính sai điểm hoặc mở publish bỏ qua gate.
- Sau có audit/penalty/publication mới: dừng writers/maintenance, lưu backup và log incident hiện tại. **Ưu tiên forward fix hoặc bản rollback vẫn đọc/verify ledger và giữ publication snapshots**. Bản cũ không biết penalty có thể hiển thị sai điểm; nếu chưa có bản tương thích thì giữ maintenance, không mở lại bằng code cũ. Không xóa/reverse ledger, sửa winners, re-sign evidence hay công bố lại để che lỗi.
- Restore backup trước deploy chỉ là phương án disaster recovery do người có thẩm quyền quyết định sau khi đánh giá mất toàn bộ writes sau backup/RPO và khả năng PITR. Restore sang môi trường cách ly trước, kiểm DB + đúng key + uploads rồi chuyển có kiểm soát; giữ bản DB sau sự cố để đối soát/reconcile. Không restore đè nóng hoặc coi rollback DDL/transaction là khôi phục đủ dữ liệu.

**Bước tiếp theo:** đóng các checkbox bằng bằng chứng release/restore/rehearsal/config/visual; cập nhật kết luận ngay trong mục này. Chỉ chuyển READY khi đủ điều kiện và không còn kiểm tra bắt buộc chưa xác minh; người dùng xác nhận triển khai là bước riêng sau đó. Không còn blocker hợp đồng nghiệp vụ V1 đã duyệt; NOT READY ở đây là kết luận vận hành/phát hành, không phủ nhận Final UAT.

#### Final Release Preparation — 13/11/2026

**IN PROGRESS.** Tiếp tục chuẩn bị local trong cùng mục theo yêu cầu; sửa presentation C07 có bằng chứng Figma, build từ lock và kiểm tra liên quan, không mở phase/tài liệu mới. Trước sửa source: Git 2.42 tại `C:/Program Files/Git/cmd/git.exe` chạy được với `-c safe.directory=F:/laragon/www/toyota` chỉ cho từng lệnh, sau khi xác minh owner repository và `.git` đều `OUSB8MG-PC/Admin`; Git 2.37 từ PATH không xử lý exception này đúng trong sandbox. Không sửa global config/ACL/owner hoặc thêm wildcard trust. HEAD `c5251f2ff95208ffacade0a11f8e12954a49605d`, chỉ tài liệu này modified từ lượt readiness trước, không có source edit sẵn. Local origin ref bằng HEAD; `ls-remote` thất bại kết nối GitHub: push hiện thời chưa xác minh, không push/commit tự động. Kết quả kiểm thử mới sẽ cập nhật bên dưới sau thực thi.

**Kết quả: DONE phần sửa/kiểm chứng local; Build READY theo working tree đã test. Git release CHƯA READY vì chưa commit các sửa đổi; production vẫn NOT READY đến khi đóng điều kiện vận hành.** Kết luận này thay thế IN PROGRESS ngay trên và các giới hạn Git/build/visual tương ứng trong lượt readiness trước, giữ nguyên lịch sử. Không coi bản HEAD cũ là đã chứa UI mới hoặc trạng thái remote đã được xác nhận.

**Source đã sửa, không đổi nghiệp vụ:**

- `resources/views/admin/awards/index.blade.php`: step 1 có check khi có frozen audit và readiness không vướng các điều kiện chốt dữ liệu, hoặc kỳ đã published; step 2 active khi đang kiểm tra, hoàn tất khi server cho phép publish hoặc kỳ đã published; step 3 active khi ready, chỉ có check sau published thật. Đây là chỉ báo tiến trình, không phải bổ sung bằng chứng audit cho kỳ legacy. Badge “Đang kiểm tra” ở kỳ đang chọn có audit thật; “Đã công bố” theo published_at. Dùng component badge có sẵn; không thêm nút/modal/API hoặc thay gate publication.
- Badge kết quả “Đạt” chỉ cho draft có coverage hợp lệ, mẫu thực tế >0 và mọi kết quả đều Correct; underfill không bị ép thành đủ 5, zero sample/no-answer2/Wrong không được gán Correct. “Đang gọi” khi đã có cuộc gọi và vẫn còn needs_call/recall. Không dùng badge loại/đôn hạng theo threshold cũ; không re-certify published winners bằng audit live. Readiness không xác minh được thì không hiện Đạt. Kết quả toàn Correct dùng câu `N/N đúng` có trong Figma.
- Threshold bỏ duy nhất mệnh đề “Ngưỡng loại: sai quá [x]% … chờ TMV chốt”; giữ nguyên câu Figma **“Không nghe máy 2 lần thì không tính là sai.”** Không thêm copy hoặc tự đặt policy mới.
- `resources/css/admin-awards.css`: dùng thông số Figma `164:983–1002`: circle 28px/check 14px, complete #1e9e4a, active #fff1f2/border #fbd3d7/circle #eb0a1e, padding14/gap12/radius14; canh badge kết quả sang phải. Tái dùng asset C07 và màu/component hiện hữu; không đổi C08, public pages, controllers/services/policies/schema.
- `tests/Feature/AwardPublicationFinalTest.php`: thêm 2 ca HTTP kiểm step/badge draft→ready→published và recall/no-answer2 không nhận Đạt. `tests/Browser/p09-publication.mjs`: thêm assert DOM sau commit, step/badge/placeholder và tùy chọn thư mục artifact riêng. Tổng **5 file tracked thay đổi**, gồm 2 source UI, 2 test/harness, 1 tài liệu này; không có phase/tracking/checklist mới.

**Bằng chứng Figma/browser mới:** dùng skill `figma-design-to-code`, đọc design context kèm screenshot C07 `164:867`, session `ea907164-fbdc-44fd-aa56-bf7990ad9f0a`. Đã mở trực quan ảnh mới `.laravel-tools/p09-release-local-browser/c07-ready-1440.png` và `c07-needs-supplement-1440.png`, đối chiếu step/badge/layout với frame. Browser còn chụp `c07-published-1440.png`, `c08-supplement-1440.png`; JSON checks cùng thư mục. Đây là Laravel thật với **fixtures cách ly**, không phải session production/manual UAT TMV. Step active tái dùng style hiện có cho bước hiện tại; enabled publish giữ style hiện hữu do chưa có variant riêng. Không tuyên bố pixel-perfect mọi state; khác biệt dữ liệu, timezone đã duyệt và chính sách loại Dealer cũ không được tái tạo từ ảnh mẫu.

**Tests/lệnh thực chạy trong lượt Final Release Preparation:**

| Kiểm tra | Kết quả mới |
|---|---|
| `npm ci --offline --no-audit --no-fund` → `npm run build` | **PASS**: cài sạch 65 packages từ `package-lock.json`/cache local, build 89 modules. Hai lockfiles không đổi. Log `.laravel-tools/p09-release-build.log`. |
| Composer `validate --no-check-publish`; `install --dry-run --no-scripts --no-plugins --no-interaction`; `check-platform-reqs --no-dev` với network disabled | **PASS exit 0**: composer.json/lock hợp lệ, installed vendor không cần install/update/remove, platform local PHP8.5.11 đạt. Không cài lại vendor, không chạy Composer scripts; CLI Composer cũ có deprecation notices từ thư viện trong PHAR, không coi log sạch warning. Log `.laravel-tools/p09-release-composer.log`. Không chứng minh runtime production. |
| Preflight đọc-only | **PASS**: APP_ENV testing, exact MySQL TCP127.0.0.1:3306, actual database `toyota_testing`, MySQL8.0.30, FK1/InnoDB; chụp DDL/checksum/counts của23 bảng trước fixtures. Dùng bản helper snapshot hiện hữu đổi output sang `storage/app/private/p09-release-local-20261113/`; không ghi đè baseline UAT, không DDL. |
| `php vendor/bin/phpunit --bootstrap tests/Fixtures/p09_final_bootstrap.php tests/Feature/AwardPublicationFinalTest.php tests/Feature/AdminShellTest.php tests/Feature/AdminCallAuditUiTest.php --no-progress --display-skipped --do-not-cache-result --log-junit storage/app/private/p09-release-local-20261113/tests.xml` | **45 tests/532 assertions PASS**, 0 fail/error/skipped, 37.606s. Env được pin testing/mysql/127.0.0.1/3306/toyota_testing, TOYOTA_TEST_DATABASE=toyota_testing, DATABASE_URL/DB_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE là test path không tồn tại. Bootstrap chặn CREATE/ALTER/DROP/TRUNCATE/RENAME/FK reset; fixtures chỉ cleanup owned IDs. Không chạy full regression/migrate:fresh/seed/reset. |
| Browser trong suite trên | **PASS** Chrome headless Desktop1440: ready/error/in-flight/double click/published-after-server-commit; supplemental→đúng C08 batch; C08 claim/release/record/tabs/toast sau response. Không pageerror/overflow trong các ca chạy. Không cộng browser tests lại vào45 tests. `P09FINAL_OUTPUT=.laravel-tools/p09-release-local-browser`. |
| Syntax/Pint/diff | **PASS** `php -l` file PHP test đã sửa +8 migrations; `node --check tests/Browser/p09-publication.mjs`; `php vendor/bin/pint --test tests/Feature/AwardPublicationFinalTest.php`; `git diff --check`. Blade được compile/render qua HTTP suite và browser. Git có notice CRLF→LF ở CSS, không có whitespace error. |
| Postflight so baseline mới | **PASS** changed_business_tables=[], source_changes=[] (hash app PHP), migration_rows_added=0; audit4/penalty3/publication đều0.23 bảng giữ counts/checksums/schema, fixture cleanup hoàn tất; không listener8776/8777 còn chạy. Không reset AUTO_INCREMENT. |

**Release Git/Build cụ thể:**

- Đã xác minh toàn bộ source Phase9 chính, 8 migrations, controllers/services/policies/requests/routes/Blade/JS, assets static và dependency locks có trong Git. `git diff HEAD -- app routes config database/migrations composer.json composer.lock package.json package-lock.json vite.config.js` rỗng: không đổi backend/schema/locks. Các thay đổi UI/test vừa nêu **chưa commit**; không tự gộp vào HEAD hay coi là đã push.
- `public/build/manifest.json`: **49 entries**, đủ **30 Vite inputs**, mọi file/CSS/assets/import/dynamicImport được tham chiếu đều tồn tại; không `public/hot`. Build chạy từ source UI mới trước HTTP/browser, không sửa source UI sau build. Manifest SHA256 `a8291c82f116e8eae6770bbbe45a286bffb20d351cae372bfb5144d26c66b6a4`.
- Inventory SHA256 **702 files** source/static/build tại `storage/app/private/p09-release-local-20261113/release-inventory.json`, gắn HEAD nền và ghi rõ là **tested working tree, chưa phải committed release**. Đây là bằng chứng build, không phải tracking hoặc gói deploy hoàn chỉnh; không chứa secret/vendor/node_modules. CSS SHA256 `955d0692d53860761affee353221dc9bf944080ef32895b354b4fbe3adeae344`, Blade SHA256 `026d1a56561b281ea4386576051ade48d3ac6486b2e672c2584752ff59602f2d`. Giữ inventory ngoài public, không deploy QA artifacts. Nếu source/lock/assets đổi thì inventory/build/test phải đối chiếu lại.
- Bước chốt Git còn lại: review/commit đúng5 file, xác minh SHA mới/tag và push qua môi trường có mạng; gắn artifact build với SHA đó. Remote `origin/upgrade/laravel-13` lưu local bằng HEAD, nhưng `git ls-remote --heads origin upgrade/laravel-13` lỗi kết nối github.com:443; **chưa xác minh remote hiện thời**. Không tuyên bố release Git sẵn sàng chỉ vì build PASS.

**8 migrations/lệnh apply:** cả8 file allowlist ở mục trước đã tracked, không đổi so HEAD, syntax PASS; thứ tự FK/dependency và rủi ro partial MySQL DDL giữ như review trước. Không chạy DDL local hoặc production trong lượt này. Khi vận hành, dùng allowlist trước đã lọc theo pending/schema thực tế và **tách review/apply thành hai lần thực thi**, không copy chạy liền hai vòng lịch sử:

```sh
# Lần 1: maintenance + backup/restore/rehearsal đã đạt; chỉ review
php artisan migrate:status
for migration in $MIGRATIONS; do
  php artisan migrate --path="$migration" --pretend --force || exit 1
done
```

Sau khi người vận hành xác nhận SQL đúng8 target hoặc subset pending, không có bảng dở dang và đã có quyết định deploy, mới thực hiện block riêng:

```sh
# Lần 2: MIGRATIONS là allowlist pending đã duyệt, thứ tự 000101…000301 như trên
for migration in $MIGRATIONS; do
  php artisan migrate --path="$migration" --step --force || exit 1
done
php artisan migrate:status
```

**Bàn giao vận hành — chưa xác minh, cần hoàn tất trước GO production:**

1. DB/config/APP_KEY production đúng đích và cùng key/cipher trên mọi node; MySQL InnoDB/CHECK/FK/isolation, baseline và mutex.id1; đọc/verify ciphertext + ledger/publication lịch sử bằng đúng key, không đổi key lúc deploy.
2. Backup DB/uploads/private storage/key/artifact trước ghi; restore drill vào host/DB cách ly đạt checksum/schema/decryption, chốt RPO/RTO và người quyết định rollback.
3. Rehearsal migration trên bản restore cách ly, đo lock/time và xử lý DDL lỗi giữa chừng; áp dụng chỉ pending allowlist, không seed/reset/chèn migration record để vượt lỗi.
4. Kiểm runtime/platform đích, HTTPS/CSRF/permissions/cache/session/queue, maintenance toàn cụm, rebuild cache/reload PHP/workers; smoke read-only Admin/C07/C08/ledger/dealer-scores/public Award và theo dõi errors. Local platform/browser PASS không thay smoke production hoặc manual UAT.
5. Rollback giữ schema additive/APP_KEY và toàn bộ audit/ledger/publication/winners; chỉ chuyển về code tương thích ledger. Sau có writes mới, ưu tiên forward fix; nếu chưa có bản tương thích thì giữ maintenance. Restore/PITR chỉ theo quyết định có đánh giá mất writes và đối soát, không drop/reverse/re-publish để rollback.

Không deploy/kết nối production/lấy secrets/công bố Award thật. Chỉ các publication fixture trong DB test đã được tạo và cleanup trong suite. Người dùng quyết định triển khai sau khi chốt Git và có bằng chứng cho5 điều kiện vận hành trên.
