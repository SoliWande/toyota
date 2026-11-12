# P09P — E3 Read-only Impact Preview C07/C08

## Mục tiêu

API Admin chỉ đọc các finding confirmed hợp lệ, đối chiếu attribution/tháng, xếp hạng hiện hành và Award draft/published. Không giả định chế tài, không ghi dữ liệu/UI.

## Trạng thái

DONE backend E3 — 12/11/2026: **166 tests / 1.615 assertions PASS**, không SKIPPED/FAIL. Khởi tạo IN PROGRESS trước source; kết quả mới dưới đây thay trạng thái chưa nghiệm thu ban đầu. Không nghiệm thu chế tài/công bố/UI.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, nghiệm thu E2 P09O, E1 P09N, freeze P09H và source LeaderboardService/AwardResults/Award/AwardWinner/readers/policy/routes/tests. E2 lưu resolution riêng không đổi queue/counts C1/C2. Leaderboard Dealer dùng submission.dealer_id, Sales ranking dùng Dealer hiện tại của Sales; phải thể hiện khác biệt, không thay scoring. Published AwardResults.preview bị cấm, chỉ đọc AwardWinner snapshot. Working tree có nhiều phase chưa commit; giữ nguyên.

## Các file source code liên quan

Mới:

- `app/Services/CallAudit/ImpactPreviewReadModel.php`.
- `app/Http/Controllers/Admin/CallAuditImpactPreviewController.php`.
- `app/Http/Requests/Admin/CallAuditImpactPreviewRequest.php`.
- `tests/Feature/AdminCallAuditImpactPreviewTest.php`.
- `tests/Fixtures/call_audit_impact_snapshot.php` và tracking này.

Sửa `routes/web.php`: chỉ import và hai routes GET E3, giữ edits trước task. Không sửa E1/E2, policies/models/migrations, LeaderboardService, AwardResults, Award/AwardWinner, views/assets hoặc tài liệu lịch sử.

## Những thay đổi đã thực hiện

Tạo tracking trước source. Triển khai hai GET dưới middleware web/auth/auth.session/role:admin/account.active/throttle:admin-writes, fresh active Admin/policy server-side. Không tạo màn hình mới vì chưa có frame impact preview được cung cấp cho task này.

| API | Query và ý nghĩa |
|---|---|
| `/admin/call-audits/impact-preview` | `month=YYYY-MM` bắt buộc, `purpose=general_audit|award_top3` tùy chọn. Bỏ purpose để đối chiếu hai nguồn cùng tháng và gộp theo submission |
| `/admin/call-audits/{batch}/findings/{item}/impact-preview` | month và purpose bắt buộc. Child phải thuộc đúng frozen batch/purpose/month và current confirmed; scope sai hoặc chưa confirmed404. Chỉ evidence của item được chỉ định |

Response allowlist: data candidates, scope/bounds/observed_at, counts thực, current_rankings và awards. Evidence giữ batch/item/purpose/Award link/attempt/call event/resolution version/actor/time và frozen attribution; không serialize model. Query lạ/invalid422 generic, authorization403 (guest401), evidence/history hỏng409, lỗi thực thi bất ngờ503 generic; không echo/flash/log input riêng tư. No-store/private; chỉ GET/HEAD, POST405.

Không trả customer/Sales/winner names, full phone, Facebook, biển số, notes/reasons/ciphertext, seed/manifest, key hoặc digest. Source query chỉ lấy ID/status/source/dealer_id/sales_id/submitted_at để đối chiếu. E1/E2 giải mã chứng cứ tối thiểu nội bộ theo verifier hiện hữu, không trả decrypted payload ra ngoài.

## Quyết định kỹ thuật quan trọng

Candidate theo CustomerSubmission ID/tháng, gộp evidence C07/C08 nhưng giữ từng batch/item/resolution riêng, không cộng tác động hoặc điểm. Chỉ current confirmed qua E1/E2 verifier; metadata/queue string không đủ. Context live khác frozen attribution phải thể hiện riêng. Chỉ monthly exact bounds hiện hữu, không tự đổi calendar/timezone. Chưa có chế tài nên không trả delta/new score/rerank/threshold hoặc quyền công bố.

- Đọc toàn bộ finding pages E1 trước chọn confirmed; validate history E2 cả dismissed/needs_review, không lọc bằng metadata decision chưa xác minh. Resolution trỏ item không có trusted finding làm preview fail closed. Không gọi SamplingPlanner/ReproductionVerifier/freeze hoặc ghi audit khi GET.
- `candidate_count` đếm unique submission trong scope; `confirmed_evidence_count` đếm evidence, hai counts theo purpose chỉ là provenance. Một submission có hai confirmed → một candidate, hai evidence. Scope hẹp giữ cùng submission ID để đối chiếu; không được cộng candidate counts của nhiều request thành số tác động.
- `current_submission`, `live_contribution` và comparison là thông tin hiện hành, frozen attribution riêng cho từng evidence; không tự chọn đại diện nếu hai snapshot khác nhau. Nguồn thay sau freeze/call không tự huỷ chứng cứ lịch sử. Membership/đóng góp hiện tại gọi chính `LeaderboardService::dealerScores/salesScores` với submission filter, giữ role/status/demo/month semantics hiện hữu. Đây là boolean membership, không là số điểm trừ.
- Ranking dùng dealersForRange/salesForRange với bounds tháng chuẩn; filter ID **ngoài** window query để giữ rank toàn hệ thống, tránh lọc trước rồi mọi Dealer thành hạng1. Không có ranking hợp lệ trả score/rank=null, không giả0. Sales ranking có dealer_id hiện hành; Dealer ranking dùng source attribution như service gốc.
- Award context chỉ monthly exact `[start,end)` như `scope.award_scope=monthly_exact_bounds`. Draft dùng AwardResults.preview thật; published tuyệt đối không gọi preview, chỉ đọc AwardWinner ID/type/subject/rank/score/sales_dealer_id_snapshot. `record_id` draft=null vì không giả snapshot đã persist. `related_submission_ids` chỉ liên hệ identity frozen/current, không chứng nhận bản ghi sẽ bị thay đổi. Trả cả Top3 context để đối chiếu, không suy đôn hạng hoặc loại Dealer.
- GET nằm trong consistent read transaction MySQL hiện hành, không locking read/claim/writer. Không thay published snapshots dù ranking live khác snapshot. Weekly/all-time và các kỳ khác không thuộc preview tháng này; không suy rằng chúng chắc chắn không bị ảnh hưởng bởi một quy tắc chế tài trong tương lai.

## Kết quả kiểm thử

Khởi tạo: NOT_RUN chờ preflight. Nghiệm thu mới 12/11/2026:

| Kiểm tra | Kết quả mới |
|---|---|
| `php tests/Fixtures/call_audit_impact_snapshot.php before` | PASS trước fixtures: MySQL8.0.30, TCP `127.0.0.1:3306`, database thực toyota_testing, FK checks1, InnoDB; audit4 bảng0 rows; snapshot19 bảng và hash225 app/migration files có trước task |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditImpactPreviewTest.php tests/Feature/AdminCallAuditFindingResolutionTest.php tests/Feature/AdminCallAuditFindingTest.php tests/Feature/AdminCallAuditReadIntegrationTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped --log-junit storage/app/private/p09p-call-audit-claim/tests.xml` | **166 PASS / 1.615 assertions**,0 skipped/fail. E3 mới38/327; E2 56/452; E1 37/308; C2 22/311; policy13/217 |
| Syntax/Pint/diff | `php -l` và `php vendor/bin/pint --test` đúng6 PHP mới/sửa PASS; git diff --check và whitespace file mới/tracking kiểm riêng; tracking đủ9 mục |
| `php tests/Fixtures/call_audit_impact_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false trên19 bảng và225 source/migrations được bảo vệ. Toàn1.766 fixture journals cleaned=true |

Coverage E3: confirmed/dismissed/needs_review/chưa xử lý và current sau needs_review; overlap hai purpose gộp một candidate; hai submission riêng; C07 dismissed không triệt C08 confirmed; signed E1/E2 tampered (9 loại); fake resolution trên uncalled item; frozen attribution vs live Dealer/Sales transfer/role/status; start inclusive/end exclusive và source chuyển tháng; live rank2 giữ đúng window; draft preview/published snapshot tách biệt khi live ranking đổi; Admin active khác creator, fresh blocked/direct service/guest/Sales/Dealer/Manager account/inactive; IDOR/month/purpose/filters; no batch/batch rỗng và confirmed ở vị trí51 sau page E1 đầu tiên; privacy/no logs; GET repeated chỉ SELECT, bind writers để throw, checksum toàn19 bảng trước/sau GET. E2 concurrency thật trong suite liên quan vẫn PASS; E3 không thêm writer hoặc concurrency action.

Env PHPUnit rõ ràng: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ file test không tồn tại. Guard trước PDO và actual database/transport check. Harness A3 dùng committed fixture IDs/tag+journal+scoped cleanup. Test published tạo snapshots chỉ trên Award fixture thuộc run; xóa đúng child fixture trước cleanup, không chạy publication runtime. AUTO_INCREMENT counter không reset, schema hash chỉ bỏ counter.

Artifacts private/ignored: `storage/app/private/p09p-call-audit-claim/{before,after}.json`, `tests.xml`. Không migration/seed/reset/full regression/production; không build/browser vì không sửa UI/assets. Điểm/moderation/verification/Dealer/Awards và published snapshots không bị GET thay đổi; source225 files gồm cả scoring/E1/E2/models/policies/migrations giữ nguyên.

## Những lỗi còn tồn tại

Không còn FAIL/SKIPPED/BLOCKED trong backend E3. Chưa có quy tắc chế tài nên chỉ có impact candidates/context; không tính điểm mới, threshold, loại Dealer hoặc kết quả Vinh danh mới. UI impact chưa có frame phù hợp trong phạm vi task, không tạo UI. Không benchmark khối lượng nhiều generations/nhiều năm hoặc làm verifier toàn ledger/key rotation; monthly read hiện dùng batch đã freeze của V1.

## Task hoặc bước tiếp theo

E3 hoàn tất để review. Người dùng quyết định riêng quy tắc xử phạt, phạm vi kỳ liên quan, thu hồi xác minh hoặc công bố trước bất kỳ effects/mutation tiếp theo; không suy quyền đó từ confirmed hay preview. Tracking này không cấp quyền deploy production.
