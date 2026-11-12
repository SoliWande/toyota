# P09Q — E3 Penalty Simulation (Read-only)

## Mục tiêu

Mô phỏng SIMULATION_ONLY trừ1 theo confirmed submission/tháng duy nhất từ P09P, so sánh điểm/ranking/Top3 và Award draft/published, không ghi dữ liệu hoặc phát sinh chế tài thật.

## Trạng thái

DONE backend simulation — 12/11/2026: **146 tests / 1.248 assertions PASS**, không SKIPPED/FAIL. Khởi tạo IN PROGRESS trước source; kết quả mới bên dưới thay trạng thái chưa nghiệm thu. Toàn bộ giả định là SIMULATION_ONLY, không phải chế tài V1 được phê duyệt.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09P/nghiệm thu E2 P09O và source scoring/LeaderboardService/AwardResults/readers/models/policies/routes/tests. Scoring hiện tại COUNT approved, tie-break score DESC/MAX(submitted_at) ASC/id ASC; Dealer theo submission.dealer_id, Sales COUNT theo Sales có role phù hợp và Dealer hiện hành. P09P xác minh evidence và gộp submission, chưa mô phỏng điểm. Working tree có nhiều phase trước chưa commit, giữ nguyên.

## Các file source code liên quan

Mới:

- `app/Services/CallAudit/PenaltySimulationReadModel.php` và `SimulationRanking.php`.
- `app/Http/Controllers/Admin/CallAuditPenaltySimulationController.php`.
- `app/Http/Requests/Admin/CallAuditPenaltySimulationRequest.php`.
- `tests/Unit/CallAuditSimulationRankingTest.php`, `tests/Feature/AdminCallAuditPenaltySimulationTest.php`.
- `tests/Fixtures/call_audit_simulation_snapshot.php` và tracking này.

Sửa `routes/web.php`: import và một GET. Không sửa LeaderboardService/AwardResults/P09P/E1/E2, policies/models/migrations, scoring, UI/assets hoặc tài liệu lịch sử; giữ edits phase trước.

## Những thay đổi đã thực hiện

Tạo tracking trước source; các giả định task được dùng riêng cho mô phỏng, không là approval chế tài V1 và không mở mutation.

GET `/admin/call-audits/penalty-simulation?month=YYYY-MM`, chỉ GET/HEAD, middleware web/auth/auth.session/role:admin/account.active/throttle:admin-writes. Month bắt buộc, không nhận purpose/item/Dealer/key/mức phạt tùy ý; luôn xét cả C07/C08 của tháng, P09P kiểm quyền/evidence server-side. Input lạ hoặc sai422 generic, guest401, không đủ quyền403, evidence/attribution mâu thuẫn409, lỗi thực thi503 generic. Response no-store/private, không echo/flash/log PII/private exception.

Payload có `mode=SIMULATION_ONLY`, policy version `confirmed_minus_one_simulation_v1` và assumptions rõ ràng; scope/counts/provenance từ P09P; candidates; bảng Dealer/Sales trước/sau; Top3 trước/sau và entered/exited/reordered IDs; Award reference records so với simulated live Top3. Không tạo bảng/penalty ledger/event, không POST hoặc nút/UI.

## Quyết định kỹ thuật quan trọng

Trừ1 theo submission duy nhất trong tháng, Dealer frozen phải rõ ràng; snapshot mâu thuẫn không tự chọn đại diện. So sánh với live baseline, không trừ vào published snapshots. Điểm mô phỏng là signed, không tự clamp0. Giữ tie timestamp live vì không xóa/sửa source. Sales chỉ mô phỏng khi nguồn hiện có thực sự đóng góp1 và attribution nhất quán, không suy ra chế tài Sales khi không có bằng chứng hỗ trợ. Các mặc định kỹ thuật sẽ được ghi rõ trong API/tracking và tests, chỉ SIMULATION_ONLY.

- P09P làm input có current confirmed/evidence E1/E2 hợp lệ; dedup submission/tháng ngay từ P09P và assert identity duy nhất trước arithmetic. C07/C08 overlap vẫn hai evidence nhưng chỉ một Dealer deduction và tối đa một deduction trong bảng Sales riêng. Dismissed/needs_review/chưa resolution không trừ.
- Dealer dùng attribution frozen `submission.dealer_id` của nguồn khi lấy mẫu; không chuyển penalty sang Dealer hiện tại của Sales hoặc source. Nếu hai frozen snapshots không thống nhất Dealer, trả409, không chọn đại diện. Không tuyên bố snapshot là bằng chứng về một chỉnh sửa nguồn xảy ra trước mọi batch; không đủ lịch sử thì không đoán attribution khác.
- Live baseline lấy trực tiếp `LeaderboardService::dealerScores/salesScores` theo bounds tháng chuẩn, giữ moderation/role/demo/calendar hiện hành. Pure SimulationRanking trừ trong memory, giữ score DESC, achieved_at ASC, id ASC và ROW_NUMBER tuần tự như MySQL. `achieved_at` là MAX(submitted_at) live giữ nguyên vì đây là penalty arithmetic, không loại hồ sơ hoặc tính lại thời điểm bằng giả định reject.
- **TECH_DEFAULT cho simulation:** giữ Dealer/Sales đã có baseline trong population kể cả sau đó0/âm; không tự đặt điều kiện đủ điểm Top3/loại Dealer. Nếu Dealer lịch sử hiện không có score row, thêm target chỉ cho mô phỏng với arithmetic baseline0, `baseline_present=false`, before_rank=null, after_score âm; không giả rằng database có row0 hoặc live rank. Null timestamp cho target không có baseline, MySQL ASC null-first và id tie-break. Các quy tắc này công khai trong assumptions, không phải quy tắc đủ điều kiện Vinh danh chính thức.
- Sales là bảng điểm riêng: chỉ trừ khi frozen sales_id duy nhất, khớp nguồn hiện hành và P09P chứng minh source đang thực sự vào COUNT Sales hiện hữu. QR không có Sales → không tạo tác động Sales. Source chuyển Sales hoặc không còn đóng góp hợp lệ → không tự gán/trừ; `sales_deduction=null`, effect status và `sales.complete=false`/not_simulated_submission_ids. Có thể có phần Sales hỗ trợ còn lại; Top3 Sales đó chỉ là mô phỏng phần đã hỗ trợ, không được coi kết luận đầy đủ. Dealer vẫn trừ1 theo giả định task kể cả live source hiện không đóng góp nên có thể âm. Không cộng các deduction của hai bảng thành một tổng phạt2 điểm/submission.
- Award draft dùng reference preview thật từ P09P/AwardResults; published dùng reference AwardWinner snapshot thật, không gọi AwardResults.preview cho published. `comparison_basis=published_snapshot_vs_simulated_live` tách snapshot đã công bố với ranking live giả định; không lấy điểm snapshot làm baseline để trừ hoặc dựng snapshot mới. Chỉ monthly exact bounds; không đổi tuần/all-time/kỳ khác.
- API allowlist chỉ IDs/scope/provenance/điểm/rank/trạng thái hỗ trợ. Không tên khách/Sales/winner, phone, Facebook, biển số, notes/reasons, seed/manifest/key/digest/ciphertext. GET cùng read transaction với P09P, không khóa hàng hoặc resolve writer, không claim/freeze/sampling/verification/audit mutation. Không hủy xác minh, reject, threshold tỷ lệ sai hoặc quyền công bố.

## Kết quả kiểm thử

Khởi tạo NOT_RUN chờ preflight; kết quả mới 12/11/2026:

| Kiểm tra | Kết quả thực tế |
|---|---|
| `php tests/Fixtures/call_audit_simulation_snapshot.php before` | PASS trước fixtures: MySQL8.0.30, actual TCP127.0.0.1:3306/toyota_testing, FK checks1, InnoDB; audit4 bảng0 rows; snapshot19 bảng và hash228 app/migration files có trước task |
| `php vendor/bin/phpunit tests/Unit/CallAuditSimulationRankingTest.php tests/Feature/AdminCallAuditPenaltySimulationTest.php tests/Feature/AdminCallAuditImpactPreviewTest.php tests/Feature/AdminCallAuditFindingResolutionTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped --log-junit storage/app/private/p09q-call-audit-claim/tests.xml` | **146 PASS / 1.248 assertions**,0 skipped/fail. Simulation unit12/24 + feature27/228; P09P38/327; E2 56/452; policy13/217 |
| Syntax/Pint/diff | `php -l` và `php vendor/bin/pint --test` đúng8 PHP mới/sửa PASS; git diff --check, whitespace file mới và tracking9 mục kiểm riêng |
| `php tests/Fixtures/call_audit_simulation_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false;19 bảng giữ row counts/checksum/schema và228 source/migrations giữ nguyên. Tất cả1.912 fixture journals cleaned=true |

Coverage mới: một source C07/C08 chỉ trừ1; nhiều source cùng Dealer; signed âm−2 khi không còn live baseline và0 không tự loại; score/time/id ties so trực tiếp MySQL; Top3 entrant/exit/đổi thứ tự; before ranks/scores khớp LBS; Dealer source attribution độc lập Sales transfer; source chuyển Dealer không chuyển penalty; QR không giả Sales, đổi Sales/không đóng góp đánh dấu incomplete; hai frozen Dealer khác nhau409; draft/published reference và không preview lại published; non-confirmed không trừ; auth/fresh blocked/direct service/other active Admin; tampered E1/E2; query allowlist/POST405/month empty; privacy/no logs; repeated GET chỉ SELECT, bind writers để throw và checksum toàn19 bảng trước/sau. E2 concurrency thật trong suite liên quan vẫn PASS; simulation không có writer/concurrency action mới. Lượt đầu37 tests/240 assertions PASS; sau bổ sung hai ca tie/zero và chạy suites liên quan đạt tổng cuối phía trên, không có lỗi còn lại.

Env PHPUnit: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ file test không tồn tại. Preflight trước PDO và identity/transport thực, harness A3 committed fixture ID/tag+journal+cleanup scoped. Test AwardWinner chỉ tạo/xóa child trên Award fixture thuộc run; không publication runtime hoặc thay snapshot có sẵn. Schema hash chỉ bỏ AUTO_INCREMENT counter, không reset counter.

Artifacts private/ignored: `storage/app/private/p09q-call-audit-claim/{before,after}.json`, `tests.xml`. Không migrate/seed/reset/full regression/production. Không build/browser vì không sửa UI/assets. Source scoring/LBS/AwardResults/E1/E2/P09P/models/policy và published data không bị simulation thay đổi.

## Những lỗi còn tồn tại

Không còn FAIL/SKIPPED/BLOCKED trong simulation backend. Attribution Dealer mâu thuẫn fail closed; Sales thiếu hỗ trợ chỉ trả phần mô phỏng có bằng chứng và gắn incomplete rõ ràng. Quy tắc âm/zero/population/timestamp là giả định simulation được công khai, không là approval chế tài hoặc Award eligibility. Chế tài thật, correction, hủy xác minh, publication, UI và benchmark nhiều năm/multiple generations ngoài scope.

## Task hoặc bước tiếp theo

Review bảng giả định trước/sau để quyết định chế tài chính thức, bao gồm cách xử lý nguồn đã thay đổi, điểm âm/zero, Sales và tie-break/điều kiện công bố. Mọi mutation/ledger/penalty thật cần task/phê duyệt riêng; simulation này không cấp quyền thực thi hoặc deploy production.
