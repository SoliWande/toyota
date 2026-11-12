# P09R — E3 Penalty Ledger Preparation

## Mục tiêu

Review ledger/audit/idempotency và chuẩn bị logic thuần không ghi dữ liệu cho PROPOSED_V1: unique submission/tháng, Dealer gốc, raw/penalty/net/display floor0. Không kích hoạt chế tài hoặc tích hợp ranking thật.

## Trạng thái

DONE phạm vi preparation — 12/11/2026: **51 pure tests / 116 assertions PASS**, không skipped/fail. Khởi tạo IN PROGRESS trước source; kết quả mới bên dưới thay ghi chú chưa nghiệm thu. **BLOCKED kích hoạt ledger/điểm hiệu lực/ranking thật** vì chính sách PROPOSED_V1 chưa USER_APPROVED, đúng yêu cầu task; không cần chữ ký TMV riêng.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09Q/P09P/nghiệm thu E2 P09O và source LeaderboardService/AwardResults/Award/AwardWinner/scoring, simulation, E1/E2 và migrations audit hiện hữu. P09Q cho phép signed scores và có Sales simulation; P09R đề xuất Dealer-only/floor0 khác với P09Q, không sửa/xóa lịch sử hoặc đổi API simulation cũ. Chưa có penalty ledger. Working tree nhiều phase trước chưa commit, giữ nguyên.

## Các file source code liên quan

Chỉ tạo năm file:

- `app/Services/CallAudit/ProposedPenaltyLedger.php`.
- `app/Services/CallAudit/ProposedPenaltyProjection.php`.
- `tests/Unit/ProposedPenaltyLedgerTest.php`.
- `tests/Unit/ProposedPenaltyProjectionTest.php`.
- Tracking này.

Không sửa source có trước task, controllers/routes/models/policies/LBS/AwardResults/P09Q hoặc migrations. Không cần DDL cho preparation thuần; schema persistence được review cụ thể dưới đây, chưa tạo migration. Chưa có storage/schema mới cần áp dụng; không gọi đây là ledger hoạt động trên MySQL.

## Những thay đổi đã thực hiện

Tạo tracking trước source, không kết nối DB.

`ProposedPenaltyLedger::prepare` nhận tháng, findings/anchors đã chuẩn hóa, recorded references và audit context để kiểm cấu trúc, lập kế hoạch. Chỉ decision confirmed; dismissed/needs_review không tạo entry. Mỗi business key có một điểm đề xuất, nhiều evidence được giữ riêng; duplicate event chính xác được gộp, conflicting item/version/event/Dealer trả lỗi generic. Recorded reference cùng key không tạo điểm mới; khác Dealer/policy/amount hoặc recorded key trùng báo conflict. Key không chứa batch/purpose/Dealer/policy, tránh đổi các trường đó để trừ lại.

Plan luôn `mode=PREPARATION_ONLY`, `approval=PROPOSED_V1`, `executable=false`; cờ approval/executable do input cung cấp không thay đổi được. Audit draft có actor/prepared_at/request key, không phải event đã commit. Fingerprint canonical giữ ổn định khi đổi thứ tự/đọc trùng evidence hoặc retry sau recorded reference; thay actor/key/tháng/payload thì khác. Không dùng timestamp chuẩn bị hoặc trạng thái recorded để làm retry thành request mới.

`ProposedPenaltyProjection::preview` tách raw_score, penalty_points, net_score signed, proposed_display_score=max(0,net). Không giảm raw hoặc làm mất phần phạt vượt raw. Unique submission/tháng được tính một lần kể cả gộp recorded và plan lặp. Dealer không có raw row có baseline_present=false, arithmetic raw0; không giả rank. Sales giữ ID/score/rank đầu vào, không có entry Sales. Award draft chỉ reference và BLOCKED_PROPOSED_POLICY; published reference giữ nguyên, gắn PUBLISHED_SNAPSHOT_UNCHANGED. Không trả ranking/Top3 hiệu lực hoặc tự chọn eligibility.

## Quyết định kỹ thuật quan trọng

Không có writer, route hoặc adapter nối preparation vào runtime. Source thuần chỉ tạo kế hoạch/đối chiếu gắn PREPARATION_ONLY/PROPOSED_V1/executable=false, không xác thực chữ ký E1/E2 thay cho verifier thật. Điểm floor0 là display proposal, không tự quyết định ranking/tie/Top3. Chính sách chưa được duyệt không trở thành capability phạt của active Admin.

### Review schema đề xuất, chưa có migration/DDL

| Bảng dự kiến | Dữ liệu/constraints chính |
|---|---|
| `dealer_penalty_entries` | ID, submission_id, monthly_scope_key ASCII YYYY-MM, dealer_id lịch sử, positive points, policy code/version + encrypted policy snapshot, applied_by/applied_at/effective_at, source attribution proof/digest/key version. **UNIQUE(submission_id, monthly_scope_key)**, index(monthly_scope_key,dealer_id). FK source/Dealer/actor RESTRICT. Entry nghiệp vụ bất biến; không lưu raw/effective score vì đó là projection live |
| `dealer_penalty_evidence` | entry_id, batch_id/item_id, attempt_id, call_event_id, resolution_event_id, resolution_version, verified_at/verified_by và digest/proof version. UNIQUE(resolution_event_id) để một event không cấp điểm cho hai entries; UNIQUE(entry_id,item_id,resolution_version). FK entry + `(item_id,batch_id)` và `(attempt_id,item_id)` dùng unique A1 hiện hữu; event IDs FK RESTRICT |
| `dealer_penalty_events` | entry_id, actor_id, sequence, event type/schema, occurred_at, before/after version nếu có projection, command/key/ordinal, correlation_id, previous/event digest + key ID, private encrypted intent/receipt. UNIQUE(entry_id,sequence) và UNIQUE(actor_id,command_name,command_key,event_ordinal). Events append-only; creation/evidence attachment riêng, không UPDATE audit hoặc attempt C1 |

Đây là schema review, **không phải schema đã tồn tại**. Chưa hard-code CHECK amount=1 hoặc quyền actor role=Admin trong DDL trước policy review; CHECK points>0, month/positive versions và index/FK cần review migration riêng. Version policy không ở business key để policy mới không âm thầm phạt lần hai. Không thêm reversal/reopen/correction workflow; nếu cần phải thiết kế bù trừ append-only riêng sau approval, không xóa entry cũ.

Event ID FK đơn chỉ chứng minh tồn tại. A1 events chưa có composite unique `(event_id,item_id)`; future writer phải kiểm đúng event type/schema/decision/item/attempt/batch/month/actor dưới khóa và xác minh chữ ký. Không tự sửa A1 để giả rằng FK đã chứng minh confirmed. P09P/E2 public current response chưa trả resolution_event_id: adapter server sau này phải lấy đúng persisted current event theo item/version, verify qua E2; không nhận event ID hoặc verified boolean từ client làm bằng chứng.

Original Dealer cần bằng chứng nguồn được chấp thuận. Snapshot E1 chứng minh attribution tại freeze, không tự chứng minh mọi chỉnh sửa trước freeze hoặc lịch sử từ lúc gửi. Nhiều snapshots khác Dealer phải BLOCKED, không chọn Dealer hiện tại của Sales/nguồn. Tháng lấy từ submitted_at/bounds LeaderboardService, không từ ngày gọi/xác nhận/apply hoặc giả định VN00.

### Transaction/idempotency/audit sau approval

Future action mới phải lấy Auth actor và server clock, kiểm fresh active Admin bằng **capability applyDealerPenalty riêng**; quyền E2 resolveFinding không tự cấp quyền phạt. Các service chuẩn bị hiện tại không authorize actor: actor/time chỉ là dữ liệu audit draft, không chứng nhận session.

Intent bind policy version/month/submission IDs/frozen Dealer/current evidence anchors/actor/request key; fingerprint source hiện tại là SHA256 canonical phục vụ review, **không phải HMAC hoặc chứng thực**. Writer tương lai dùng HMAC intent/key ID và encrypted receipt; cùng key/payload trả receipt commit cũ, khác payload conflict, kiểm quyền/scope lại cả khi replay. Không client boolean để bypass policy approval.

Top-level transaction, lock/reverify E1/E2 và recorded business keys theo thứ tự xác định, không tăng item.row_version hoặc sửa C1/E2 events. Hai Admin/C07-C08 cùng key nghiệp vụ: unique DB chỉ cho một debit; contender sau conflict đọc lại trong transaction mới để trả existing receipt hoặc gắn evidence hợp lệ, không ghi debit thứ hai. Nhiều evidence cùng source chỉ thêm evidence link/event; entries/evidence/events/receipt commit nguyên tử. Unique receipt bảo vệ concurrent request; duplicate/deadlock phải rollback toàn bộ trước khi retry, không coi duplicate SQL là thành công chưa kiểm evidence/payload. Không khóa khi gọi điện. Cần test nhiều connections thật sau task schema riêng, hiện NOT_RUN.

Không lưu phone/note/reason/customer name/manifest/seed trong ledger metadata. Chỉ IDs/anchors/policy và private receipt cần thiết; reason E2 vẫn ở encrypted history hiện hữu, không nhân bản hay mở quyền đọc mặc nhiên. Key rotation, retention/access/erasure và quyền xem audit riêng cần chốt trước persistence. Model guards append-only chưa đủ chống raw SQL/DBA; cần quyền DB/backup/ledger verification đúng vận hành khi triển khai thật.

### Kế hoạch nối LeaderboardService/AwardResults, chưa triển khai

1. Giữ approved sources và raw scoring như hiện tại. Tạo provider điểm Dealer theo **tháng được duyệt rõ**, dùng bounds service hiện hữu; không thay salesScores, verification/approval hoặc cohort.
2. Aggregate applied ledger theo month/dealer **trước** JOIN vào raw score để nhiều evidence không nhân raw COUNT/debit. Raw điểm hiện từ COUNT approved, penalty từ SUM positive ledger entries; `net=CAST(raw AS SIGNED)-CAST(penalty AS SIGNED)`, display=`GREATEST(0,net)` để tránh MySQL unsigned subtraction underflow. Không clamp số điểm ledger hoặc xóa debt vì display0. Chuẩn bị thuần kiểm công thức, chưa chạy SQL provider này.
3. Population Dealer có raw row hay thêm Dealer chỉ có ledger, penalty cho hồ sơ hiện không còn vào raw, late-approved cùng tháng và điểm âm/0 phải được duyệt; hiện projection chỉ gắn baseline_present để không giả raw row/rank. Không tự mang debit tháng này sang tuần/tháng khác/all-time.
4. Chốt dùng net hay display để rank trước khi ROW_NUMBER, giữ/chọn lại achieved_at, tie-break và điều kiện Top3. Hiện LBS dùng ROW_NUMBER(score DESC, MAX(submitted_at) ASC,id ASC), không có đồng rank dù bằng điểm. Floor tạo nhiều display0 cùng net khác nhau: không tự lấy net ẩn để phá hòa hoặc loại0/âm khỏi Top3, cũng không tự đổi sang DENSE_RANK.
5. Sau policy/integration approval, Award draft tháng mới opt-in Dealer effective provider; Sales results vẫn raw. Fingerprint preview/publication phải gắn policy/version/ledger cutoff để tránh preview cũ lúc ledger thay đổi. Không coi confirmed, prepared plan hoặc display0 là quyền công bố.
6. Published Award/AwardWinner luôn đọc snapshot như hiện tại, không recalculate/update/delete/auto-unpublish. Live adjusted ranking có thể khác snapshot; chỉ đối chiếu. Mọi xử lý kỳ đã công bố, correction, đôn hạng/công bố lại phải task riêng, không tự thực hiện từ ledger.

## Kết quả kiểm thử

| Kiểm tra mới | Kết quả |
|---|---|
| `php vendor/bin/phpunit tests/Unit/ProposedPenaltyLedgerTest.php tests/Unit/ProposedPenaltyProjectionTest.php tests/Unit/CallAuditSimulationRankingTest.php --no-progress --display-skipped` | **51 PASS / 116 assertions**,0 skipped/fail;39 ca mới +12 ca kernel P09Q, đều PHPUnit TestCase thuần |
| Syntax/Pint | `php -l` và `php vendor/bin/pint --test` đúng4 PHP mới: PASS |
| Diff/tài liệu | `git diff --check`, whitespace file mới, tracking đủ9 mục. Chỉ năm file mới của P09R, không thay edits/source phase trước |
| SQL/schema/concurrency thực | **NOT_RUN**: không có schema/writer mới hoặc permission kích hoạt; không dùng unit PASS để tuyên bố unique/FK/transaction MySQL đã hoạt động |

Coverage: overlap/duplicate evidence và duplicate request input; business key submission/tháng, same key sau recorded không tạo debit; canonical intent và payload/actor/key/month conflict; attribution/anchor/current-version conflicts; audit actor/time trước/sau confirmation; unknown/private fields không lọt plan; input approval flags không kích hoạt; raw/penalty/net/display tách biệt và số âm giữ audit; floor0 không tự rank/Top3; Sales unchanged; draft blocked/published reference unchanged; recorded policy/amount conflict; empty/invalid scope. Input arrays không bị sửa.

Không bootstrap Laravel, không fixture, không PDO/DB/production connection, không migrate/seed/reset/full regression. Không cần preflight/cleanup DB cho pure tests; không tuyên bố đã đo row count0 trong task này. Test bootstrap chỉ vendor/autoload.php, không tạo application; hai services chỉ dùng PHP/ManifestCodec thuần. Không build/browser vì không UI/assets. Lượt đầu49/112 PASS, sau thêm hai ca safety/scope đạt tổng cuối51/116, không lỗi còn lại.

## Những lỗi còn tồn tại

Không còn FAIL trong preparation. Các phần **BLOCKED thực sự trước kích hoạt**:

- USER_APPROVED chính sách phạt1/submission/tháng, Dealer-only, floor0 và không thay source/verification/published; hiện tất cả vẫn PROPOSED_V1. P09Q SIMULATION_ONLY không là approval và không bị P09R thay thế.
- Định nghĩa/proof Dealer gốc nếu source có chỉnh sửa trước freeze hoặc hai cohort khác attribution; xử lý nguồn không còn đóng góp raw, late approval và thời điểm hiệu lực/cutoff kỳ. Không tự giả định VN00.
- Rank theo net hay display, zero/negative population, tie/achieved_at/Top3 eligibility; tuần/all-time/retroactive kỳ đã công bố; không tự bổ sung threshold hoặc loại Dealer.
- Capability apply riêng, manual/automatic trigger, audit disclosure/retention/key strategy và appeal/reversal/correction nếu có. Không suy từ Admin active hoặc resolveFinding.
- Schema review/migration source rồi task DDL/test riêng; DB unique/FK/race/rollback/idempotent receipts thực chưa có nghiệm thu. Không có model/writer/provider effective score hoặc runtime adapter mới trong task này.

## Task hoặc bước tiếp theo

Trình policy/schema/integration review ở trên để chốt riêng. Sau USER_APPROVED: task migration source/models/policy → task DDL/constraints test chỉ toyota_testing → writer atomic/evidence/receipt/concurrency → provider Dealer tháng và Award draft/publication gate được duyệt. Không reuse PROPOSED policy identifier hoặc plan.executable flag như approval runtime; không mở route/UI hoặc phạt thật trong P09R.
