# P09C — Review schema lấy mẫu tháng C08/C07 trước giai đoạn A

## Mục tiêu

Hoàn thiện bản review bốn bảng lấy mẫu/lịch sử cuộc gọi, constraints, snapshot, idempotency, seed/manifest và concurrency; chuẩn bị danh sách migration/models/services/tests để duyệt riêng giai đoạn A. Chỉ tạo tài liệu, không tạo migration hoặc triển khai backend.

## Trạng thái

DONE phạm vi tài liệu/schema review — 2026-11-11, kiểm tra tài liệu PASS. **Nghiệp vụ đã được người dùng phê duyệt: USER_APPROVED / PENDING_TMV.** Schema dưới đây là **TECH_REVIEW / PENDING_TMV**, không phải TMV_APPROVED. Triển khai A/migration vẫn BLOCKED đến khi có approval TMV, các quyết định bắt buộc và task thực hiện riêng trên môi trường test được phép. DONE không phải xác nhận migration hoặc runtime đã được kiểm thử.

## Hiện trạng trước khi chỉnh sửa

- Đã đọc `AGENTS.md` và mục 9 hiện hành của [P09B](phase-p09b-sampling-spec.md); đối chiếu source/migrations/tests ở filesystem. Working tree có các thay đổi task trước, bao gồm P09B; giữ nguyên tất cả. File P09C chưa tồn tại trước task.
- Yêu cầu P09C nâng UC01–UC10 và hướng xử lý được nêu từ USER_CONFIRMED/đề xuất lên **USER_APPROVED / PENDING_TMV**. Đó là căn cứ cập nhật của riêng P09C; không sửa P09B hoặc xóa lịch sử cũ. Không coi USER_APPROVED là quyền tạo migration.
- Đã có approved moderation, Dealer attribution, intake mutex, phone normalizer/duplicate guard, verification audits, monthly bounds, Award preview và published snapshots. Chưa có bốn bảng call audit hoặc workflow lấy mẫu/gọi được triển khai trong source đã rà soát.
- Chỉ kiểm tra source tĩnh. Không đọc `.env`/secrets, boot Laravel, query DB, xác minh phiên bản MySQL/runtime isolation/schema thực tế hoặc gọi production. Không cần quét Figma/UI cho schema review; không tuyên bố nghiệm thu thị giác.

## Các file source code liên quan

| Source đã đối chiếu, không sửa | Căn cứ review |
|---|---|
| `app/Models/CustomerSubmission.php`, `app/Services/SubmissionIntake.php`, `app/Actions/ApproveCustomerSubmission.php`, `app/Actions/UpdateCustomerSubmission.php` | Approved/current state, phone/plate guard, version và thứ tự mutex → submission row lock |
| `app/Services/RegistrationPhone.php`, `app/Services/SubmissionIntegrity.php`, `app/Services/ApprovedSubmissionFinder.php`, `app/Services/AdminGroupLookup.php` | Chuẩn hóa, alias ambiguity, identity/nguồn; không phải sampling service |
| `app/Http/Requests/DealerRegistrationRequest.php`, `app/Http/Requests/Admin/GroupLookupRequest.php`, `app/Http/Requests/Sales/SubmissionRequest.php`, `app/Http/Requests/Admin/UpdateSubmissionRequest.php` | QR/C03 regex chặt; approved legacy/Sales không bảo đảm phone hợp lệ |
| `app/Services/LeaderboardService.php`, `app/Services/DemoSubmissionScope.php`, `app/Services/AwardResults.php`, `app/Services/PublicAwardResults.php` | Monthly submitted_at bounds, demo guards, preview fingerprint gồm Sales/Dealer; public snapshot |
| `app/Models/Award.php`, `app/Models/AwardWinner.php`, `app/Models/User.php`, `app/Policies/AwardPolicy.php`, `routes/web.php` | Bất biến published, actor types, active Admin và giới hạn ability publish hiện hữu |
| `database/migrations/2026_10_03_000003_create_customer_submissions_table.php`, `2026_10_03_000004_create_awards_table.php`, `2026_10_03_000005_create_award_winners_table.php` | Kiểu ID/FK, unique approved Facebook, unique Award period và winners; đọc source không chạy migration |
| `config/database.php`, `config/app.php`, `composer.json`, source Laravel Encrypter/EncryptionServiceProvider trong `vendor/laravel/framework/src/Illuminate/Encryption/` | Source hướng MySQL, Laravel ^13, app timezone UTC/cipher AES-256-CBC; encrypter có MAC và previous keys. Không xác nhận cấu hình runtime/khóa thực |
| `app/Services/SubmissionVerification.php`, `tests/Feature/SubmissionApprovalRaceTest.php`, `tests/Feature/AwardWorkflowTest.php` | Audit/version hiện hữu, concurrency harness và các trait có thể chạy migration, chỉ đọc |

## Những thay đổi đã thực hiện

Chỉ tạo `docs/figma/phase-p09c-schema-review.md`. Không sửa P09B, source, routes, policies, views, tests, migrations, config hoặc `.env`; không thao tác database. Các tên cột/file/lệnh logic bên dưới là thiết kế dự kiến, không phải code hoặc command có thể chạy.

## Quyết định kỹ thuật quan trọng

### 1. Baseline được người dùng phê duyệt và giới hạn approval

| Nhóm | Baseline hiện hành | Trạng thái |
|---|---|---|
| UC01–UC03 | C08 monthly tối đa 60; C07 batch riêng tối đa 5 mỗi Dealer Top 3 cùng tháng; submitted_at và monthly bounds LeaderboardService hiện hành, không đổi calendar/timezone | USER_APPROVED / PENDING_TMV |
| UC04/UC06 | Approved + SĐT chuẩn hóa theo QR/C03; một CustomerSubmission là unit; duplicate identity/alias phải phát hiện và xử lý trước freeze, không chọn đại diện | USER_APPROVED / PENDING_TMV |
| UC05/UC07 | Underfill lấy số hợp lệ thực; requested/actual riêng; không trùng trong batch, được overlap hai purpose nhưng không reuse attempt/kết quả | USER_APPROVED / PENDING_TMV |
| UC08/UC09 | Mọi active Admin được tạo/chốt/xử lý batch; created_by/frozen_by/actor_id, audit append-only; middleware/policy/server checks bắt buộc | USER_APPROVED / PENDING_TMV |
| UC10 và hướng freeze | Chốt sau hết tháng và hoàn tất duyệt trong phạm vi cutoff được xác nhận; frozen membership không tự đổi theo reload/filter/source; approval muộn không tự thêm | USER_APPROVED / PENDING_TMV |
| Batch rỗng | Actual bằng 0 không phải hoàn tất kiểm tra/Đạt/publication; không tạo attempt giả | USER_APPROVED / PENDING_TMV |

Mọi phần schema/algorithm/lock protocol là TECH_REVIEW cần review riêng. Không cấp quyền correction, void, generation mới, full PII/seed, trừ điểm hoặc publish từ quyền tạo batch. Giữ bốn thực thể P09A/P09B, không thêm ledger, bảng person hoặc bridge dùng chung attempt.

### 2. Eligibility và bốn mốc thời gian

1. **Kỳ hồ sơ:** bounds `[period_start, period_end)` của LeaderboardService monthly, lọc submitted_at. C07 tham chiếu Award monthly cùng bounds; Dealer lấy submission.dealer_id, không dùng Dealer hiện tại của Sales. Không loại riêng QR/Sales, inactive Dealer/Sales hoặc bổ sung group-member/quota theo suy đoán. Integrity nguồn/quan hệ lỗi cần xử lý trước freeze, không tự gán lại.
2. **Cutoff duyệt:** `approval_cutoff_at` và `cutoff_policy_version` bắt buộc trong batch, giá trị phải được xác nhận. Cần chốt cách xét reviewed_at tại đúng cutoff, độ chính xác, grace/late approval và chứng cứ hoàn tất duyệt. Không tự đặt cutoff 00:00 VN, ngày thứ mấy hoặc quy tắc “pending_count phải bằng 0” khi phạm vi pending chưa được định nghĩa.
3. **Eligibility snapshot:** `eligibility_as_of` ghi thời điểm đọc snapshot nhất quán; approved phải hiện diện trong snapshot, có phone hợp lệ, đúng cutoff policy đã chốt và demo policy. reviewed_at là thời điểm moderation, không thay submitted_at để phân tháng. Timestamp reviewed_at riêng lẻ không chứng minh một transaction đã commit/visible ở snapshot; version và manifest ghi chính nguồn đã đọc.
4. **Freeze:** `frozen_at` sau khi kết thúc tháng và đạt điều kiện review/cutoff đã được duyệt. Server kiểm clock/policy/evidence, không nhận client tự xác nhận bằng boolean để bỏ gate. Giữ `review_completion_evidence` có version/reference/actor theo cơ chế hoàn tất duyệt sẽ được TMV chốt; không coi checkbox hoặc nút disabled là bằng chứng. `created_at` là lúc tạo record, không thay bốn mốc này; publication_at thuộc workflow khác.

**Phone:** normalize cả phone và phone_normalized bằng RegistrationPhone; số canonical phải qua `^0[1-9][0-9]{8,9}$/D` như QR/C03 (10–11 chữ số, bắt đầu 0, số thứ hai khác 0). Không gọi được/ownership/consent không được suy từ regex. Alias không rỗng khác nhau, kể cả một alias invalid, là ambiguity cần resolution; không chọn alias hợp lệ rồi bỏ dấu vết còn lại. Raw hợp lệ với normalized thiếu có thể canonicalize vào manifest, không sửa hồ sơ. Phone thiếu/không hợp lệ không vào pool đủ điều kiện theo policy, không bị đánh Sai.

**Duplicate:** kiểm toàn cohort approved của tháng đã scope để phát hiện phone alias/plate/Facebook canonical identity mâu thuẫn, dùng quy tắc normalizer hiện có; không dùng `first()`/newest để chọn đại diện. Intake so pending/approved, C03 so cả rejected; hai phạm vi đó không tự thay phạm vi cohort. Không sửa guard identity, moderation hoặc dữ liệu để có đủ mẫu. Cần quy trình resolution và evidence trước freeze; conflict không phải shortage có thể bỏ ngầm. Source guard/unique approved Facebook không bảo đảm mọi alias/plate legacy sạch.

**Demo:** áp dụng đúng DemoSubmissionScope/guard hiện hành và ghi branch/version/exclusion digest. Mặc định scope loại owned demo IDs; include_in_metrics có thể cho demo vào, retired cần assertRetired. Nếu scope runtime mâu thuẫn yêu cầu loại demo hoặc guard lỗi, fail closed để review; không đổi config, bypass guard hoặc coi pool lỗi là empty. Đây là gate cần xác minh ở task A sau này, không kết nối DB trong P09C.

**Late approval/drift:** hồ sơ chưa approved/không visible trước freeze không tự nhập manifest khi approved sau đó; không chuyển tháng theo ngày duyệt. Điểm hiện hành vẫn tính theo LeaderboardService, không bị sampling freeze loại contribution. Source đổi sau freeze chỉ tạo stale evidence/projection theo policy, không mutate selected IDs/Dealer snapshot/PII snapshot. Approval muộn đổi ranking có thể làm baseline C07 stale; không auto đôn hạng/reroll hoặc giữ Đạt giả. Generation bổ sung cùng tháng cần contract riêng.

### 3. Quy ước schema chung

- Kiểu dưới đây là logical MySQL proposal: ID/FK `BIGINT UNSIGNED`, counts/order/version `UNSIGNED INT` (event sequence có thể BIGINT), keys ASCII binary có độ dài giới hạn; ciphertext `LONGTEXT`, digest SHA-256/HMAC 64 hex ASCII binary, structured non-PII dùng JSON. Dùng CHECK khi DB thực hỗ trợ/enforce, cộng validation/action invariants; chưa xác minh server version, không giả định CHECK bảo vệ mọi cross-row rule.
- Instants mới đề xuất `DATETIME(6)` với chuẩn serialize timezone được xác định rõ và so sánh absolute instant; lưu calendar/zone và bounds canonical. Submissions hiện dùng DATETIME theo app timezone; adapter phải so đúng storage convention của LeaderboardService, không đổi DATETIME cũ hoặc global timezone. Precision mới không tạo thêm precision cho reviewed_at/updated_at cũ.
- Phân tách frozen columns bất biến, lifecycle/queue projections có version và history append-only. Eloquent guards là lớp ứng dụng, không bảo vệ raw SQL; giới hạn đường ghi, DB constraints và transaction phải bổ sung. Không tuyên bố audit chống chỉnh sửa bởi DBA chỉ vì model có guard; trigger/grant đặc biệt là review vận hành riêng, không tự thêm.
- Bảng có `_encrypted` không đồng thời lưu bản raw trong JSON/logs. Foreign keys giữ record/history, đề xuất RESTRICT/NO ACTION khi xóa users/submission/Dealer/Award có tham chiếu; không cascade delete/set null actor. Retention/erasure và tác động ngăn xóa cần privacy approval trước migration, không tự chốt lưu vĩnh viễn.

### 4. Review từng bảng

#### 4.1. call_audit_batches — freeze envelope

| Cột/nhóm cột dự kiến | Kiểu, nullability | Invariant / review |
|---|---|---|
| id | Bigint PK | Internal ID, không tự làm public capability |
| purpose, period_type | ASCII bounded strings, NOT NULL | Purpose allowlist general_audit/award_top3; period_type monthly cho scope này |
| monthly_scope_key, period_start, period_end, calendar_zone, calendar_version | Scope ASCII YYYY-MM NOT NULL; instants/zone/version NOT NULL | Scope server-derived, end > start, canonical mapping Leaderboard monthly; không nhận filter/bounds tùy ý |
| generation, predecessor_batch_id | Positive integer NOT NULL; self FK nullable | Generation do server điều khiển; initial generation đề xuất 1, chưa có phép tạo generation tiếp theo. Self reference nếu mở workflow sau, không vòng/cross-scope |
| award_id | FK awards nullable | General phải null; award_top3 phải có Award monthly đúng bounds. FK không tự kiểm monthly/published status; action kiểm thêm |
| approval_cutoff_at, cutoff_policy_version, eligibility_as_of, frozen_at | NOT NULL | Gate hết tháng/cutoff/review hoàn tất; cutoff cụ thể và so sánh boundary chưa được tự đặt |
| review_completion_evidence_encrypted | Ciphertext NOT NULL | Reference/policy/actor/time bằng chứng hoàn tất duyệt, format cần chốt; không chứa boolean client đủ để bypass |
| created_by, frozen_by, created_at | FK users NOT NULL; instant NOT NULL | Active Admin tại command, không client actor; có thể cùng người nếu create+freeze atomically |
| policy_version, manifest_version, algorithm_version, fingerprint_version | Bounded strings NOT NULL | Immutable versions; không lồng policy version vào business uniqueness |
| eligible_count, requested_count, actual_count, target_cap | Non-negative integers NOT NULL | actual <= requested, actual <= eligible; C08 target/requested 60, actual min(60,N). C07 per-stratum requested 5, actual min(5,N_d), cap 15 |
| strata_snapshot_encrypted, award_baseline_encrypted, award_baseline_fingerprint | Ciphertext strata NOT NULL; baseline/hash nullable cho general | C07 Dealer rank/score/attribution và counts đủ, baseline fingerprint đúng AwardResults gồm cả Sales/Dealer; no public serialization |
| manifest_encrypted, manifest_digest, selection_digest | Ciphertext/digests NOT NULL | Manifest toàn candidate pool sau validation, không chỉ selected IDs; selection digest phủ ordered selected IDs/strata; không hash ciphertext ngẫu nhiên thay canonical content |
| seed_encrypted, encryption_key_id, encryption_version, digest_key_id | Ciphertext/version/IDs NOT NULL | Seed secret, random server; key IDs không phải raw keys. Phân biệt key mã hóa/HMAC/algorithm version |
| demo_policy_snapshot, lifecycle_state, row_version | JSON non-PII; bounded string; positive version NOT NULL | Lưu nhánh demo/exclusion digest/config identity tối thiểu, không dump .env. A chỉ expose frozen, không endpoint void/complete chưa duyệt |
| create_request_key, create_payload_digest | Bounded ASCII key + digest NOT NULL | Scoped unique với created_by; payload canonical validated intent, không raw customer data |

Batch được insert hoàn chỉnh và freeze atomically, không persist placeholder manifest/seed/counts rồi trả batch đang chuẩn bị. Chưa cần `updated_by`; mọi lifecycle update sau này có event. Zero vẫn frozen với actual_count 0 và trạng thái không đủ bằng chứng hoàn tất; không default complete. Lifecycle transition thêm ở B/C/E phải review trước khi mở.

C07 đủ ba Dealer: requested_count = 15. Nếu baseline dưới ba Dealer, đề xuất requested_count = 5×m và giữ target_cap=15 + baseline_dealer_count trong strata; zero và thiếu Dealer không coi đủ Top 3. Roll-up/lifecycle thiếu Dealer vẫn cần TMV xác nhận trước A, không tự thêm rank 4 hoặc bù từ Dealer khác. Phân biệt eligible_count toàn strata và actual selected count; stratum counts/sum phải khớp manifest/items qua action validation, CHECK không đếm được child rows.

#### 4.2. call_audit_items — selected membership và queue projection

| Cột/nhóm cột dự kiến | Kiểu, nullability | Invariant / review |
|---|---|---|
| id, batch_id, submission_id | PK và FKs NOT NULL | Một row cho một submission trong batch; không global unique submission |
| selection_order, stratum_key | Positive integer; ASCII NOT NULL | Order toàn batch ổn định; general có stratum chung, award stratum theo Dealer frozen |
| dealer_id_snapshot, sales_id_snapshot | Dealer FK NOT NULL; Sales FK nullable | Dealer từ submission.dealer_id; QR có thể null Sales; không thay theo users.dealer_id hiện tại |
| dealer_snapshot_encrypted, source_snapshot_encrypted | Ciphertexts NOT NULL | Snapshot Dealer/code/name và script/contact tối thiểu; exact fields/full phone/retention cần privacy sign-off |
| source_fingerprint, fingerprint_version, source_observed_at | Digest/version/instant NOT NULL | Bao state/time/attribution/aliases/script cần thiết; không dựa chỉ updated_at giây |
| queue_state, attempt_count, row_version | Bounded string; non-negative count; positive version NOT NULL | A khởi tạo uncalled/count0; không phát sinh completed attempt; definitions cuối C chốt trước ghi gọi |
| latest_attempt_id, latest_outcome, retry_due_at | Nullable pointer/string/instant | Projection có thể rebuild từ attempts/events; no-answer không tự có due 24h khi policy chưa chốt |
| claim_owner_id, claim_token_hash, claim_expires_at | Nullable user FK/hash/instant | Nhóm all-null hoặc all-set, owner có quyền hiện tại; raw token không lưu plaintext |
| source_stale_at, source_drift_digest, created_at | Stale metadata nullable; created instant NOT NULL | Stale flag không đổi membership/frozen snapshot; changes có event, không tự loại sample |

latest_attempt_id có thể tạo vòng FK khi attempts còn chưa có: đề xuất tạo pointer nullable ban đầu rồi add FK sau bảng attempts trong migration constraints; verify latest attempt thuộc đúng item bằng composite FK `(latest_attempt_id, id)` → attempts `(id, item_id)` hoặc bỏ pointer và derive khi B nếu reviewer chọn. Không dùng FK đơn thuần rồi cho trỏ attempt item khác. Snapshot ID là attribution lịch sử, FK không làm nó “live”.

#### 4.3. call_audit_attempts — kết quả đã ghi append-only

| Cột/nhóm cột dự kiến | Kiểu, nullability | Invariant / review |
|---|---|---|
| id, item_id, attempt_no | PK/FK NOT NULL; positive integer | Unique item/attempt_no; item lock cấp số, không dùng MAX+1 không khóa |
| actor_id, started_at, recorded_at | FK active Admin khi ghi; instants NOT NULL | Server session/time; started_at từ claim/explicit start theo policy C, không coi click tel là proof cuộc gọi |
| questionnaire_version, outcome | Bounded strings NOT NULL | Ba outcome intent Figma; allowlist/validation ở C, không đổi moderation enum |
| answer_1, answer_2, answer_3 | Nullable boolean | Null là chưa có câu trả lời, không phải Sai; required/mapping theo TMV10/11, no-answer không ép false |
| note_encrypted, source_fingerprint_used, item_version_used | Nullable ciphertext note; digest/version NOT NULL | Không log note; lưu source đã đối chiếu, không lấy current source làm bằng chứng cũ |
| retry_due_at, retry_policy_version | Nullable instant/version | Save snapshot schedule theo policy đã duyệt, không default 24h/tổng 2 lần |
| idempotency_key, payload_digest | Bounded ASCII NOT NULL; digest NOT NULL | Unique `(item_id, actor_id, idempotency_key)`; cùng key khác payload conflict |

Chỉ insert attempt đã ghi hoàn chỉnh; claim/start đi qua item + event, không tạo attempt rỗng rồi overwrite kết quả. Completed attempt không có CRUD sửa/xóa. Correction sau này cần record/event reference và capability riêng, không `UPDATE outcome`. Không đặt constraint đòi đủ ba câu cho tất cả outcome hoặc CHECK ngưỡng sai khi chưa có contract. A tạo structural schema, **không mở record action**; lifecycle/validation còn chờ C không được giả thành rule migration đã được duyệt.

#### 4.4. call_audit_events — command/audit journal append-only

| Cột/nhóm cột dự kiến | Kiểu, nullability | Invariant / review |
|---|---|---|
| id, batch_id, event_sequence | PK/FK NOT NULL; positive bigint sequence | Unique `(batch_id, event_sequence)` nếu dùng sequence; cấp dưới batch lock, phản ánh serialization nội bộ, không giả timestamp là total order |
| item_id, attempt_id | Nullable FKs | Batch event không cần item; attempt event cần item tương ứng; cùng aggregate, không trỏ batch khác |
| actor_id, occurred_at | User FK/instant NOT NULL | Actor từ server; A là Admin command events, không scheduler system actor giả |
| event_type, event_schema_version | Bounded strings NOT NULL | Freeze/claim/save/stale/correction theo action được phép; payload schema version riêng |
| command_name, command_key, event_ordinal | Bounded ASCII NOT NULL; integer NOT NULL | Unique `(batch_id, actor_id, command_name, command_key, event_ordinal)` cho một command có nhiều events, retry không nhân đôi |
| before_version, after_version, reason_code, correlation_id | Nullable versions/reason; bounded correlation NOT NULL | Phủ source/item/batch version có namespaced metadata; không đưa reason/note raw vào logs |
| metadata, private_payload_encrypted, previous_event_digest, event_digest | JSON allowlist non-PII; private ciphertext nullable; previous digest nullable đầu chuỗi; digest NOT NULL | Masked diff/reference, không dump full model/PII/seed/claim token. Hash-chain/HMAC có key/version là TECH_REVIEW, không tự là bằng chứng chống DBA sửa |

Event attempt/item/batch consistency cần composite constraints hoặc action kiểm tra transaction: item unique `(id,batch_id)` và attempt unique `(id,item_id)` làm targets, composite FK event `(item_id,batch_id)` và `(attempt_id,item_id)`; CHECK attempt_id non-null ⇒ item_id non-null. Nullable FK không tự kiểm quan hệ khi thành phần null. Không dùng event journal làm score adjustment ledger. Chưa có background reminders trong A; system actor model chờ contract riêng.

### 5. Constraint/index checklist và idempotency

| ID | Ràng buộc đề xuất | Điều cần kiểm chứng khi triển khai |
|---|---|---|
| K01 | Unique batches `(purpose,monthly_scope_key,generation)`, tất cả NOT NULL | Scope YYYY-MM canonical và bounds được server validate; không đưa actor/seed/fingerprint/config version vào key |
| K02 | Unique `(created_by,create_request_key)` và payload digest | Operation scoped create/freeze; key ASCII có max length để index không quá dài; giữ qua retention được duyệt |
| K03 | Unique items `(batch_id,submission_id)` và `(batch_id,selection_order)` | Không trùng trong batch; overlap giữa purposes được phép, không unique phone/customer toàn hệ thống |
| K04 | Unique attempts `(item_id,attempt_no)` và `(item_id,actor_id,idempotency_key)` | Attempt/event/item projection commit một lần; constraint là guard cuối cho race |
| K05 | Unique event command tuple/sequence; composite parent targets/FKs ở mục 4 | Tên constraints ngắn theo giới hạn DB; nullable groups/attempt ownership không bypass consistency |
| K06 | FK users/Dealer/submission/Award/self refs RESTRICT, không cascade history | Kiểu ID tương thích; privacy/retention/xóa actor phải được duyệt trước khi chốt FK strategy |
| K07 | CHECK count >=0, actual<=requested/eligible, period end>start, purpose-award null pairing, claim all-null/all-set | Monthly map, sum counts, selected membership trong manifest, active Admin và child row consistency không được bảo đảm chỉ bằng CHECK |
| K08 | Index batches purpose/month/state; items batch/queue/due/order và claim expiry; attempts item/recorded_at; events batch/sequence | Dựa read patterns B/C, không index ciphertext hoặc tạo plaintext normalized phone phục vụ sampling |

**Replay protocol:** lookup operation key dưới quyền hiện tại trước khi tính pool live. Cùng key/cùng validated intent trả kết quả batch/attempt đã commit, không đọc lại baseline thay đổi rồi đổi payload hoặc tạo seed mới. Cùng key/khác intent → conflict, không echo PII. Hai actor có request keys khác nhưng cùng business key K01: chỉ một batch thắng; bên còn lại trả batch hiện hữu nếu được đọc hoặc conflict, không ghi mình thành creator. Cùng scope với fingerprint mới không tự sinh generation mới. Request retry sau commit/timeout giữ response/result identity; events nguyên tử bảo đảm không có “đã freeze” thiếu items hoặc toast trước commit. Non-success authorization được kiểm lại kể cả replay, không khôi phục quyền của Admin đã bị khóa.

K02/K04 dedupe command tương ứng, K05 dedupe các events của claim/transition tương lai; không cần bảng request thứ năm cho A. Nếu thêm command không có record receipt ổn định hoặc muốn cache response lỗi không commit, phải review idempotency storage riêng; không tuyên bố bốn bảng đã dedupe mọi command chưa thiết kế.

### 6. Manifest, encryption và snapshot review

- Manifest canonical/versioned lưu **toàn bộ candidates hợp lệ**, ordered submission IDs, source fingerprints, Dealer/strata attribution và policy/cutoff/demo snapshots; selected IDs/order và per-stratum counts lưu riêng trong items/selection digest. Giữ manifest private encrypted trong batch trước mắt. Không chỉ lưu SHA256 hay selected IDs vì không đủ tái lập candidate pool; không query cohort live để tái lập.
- Seed đề xuất 32 bytes CSPRNG server, một seed riêng mỗi batch/purpose, không client seed/timestamp seed. Giữ draft `hmac_sha256_sort_v1`: canonical domain purpose/month/generation/Dealer stratum/unit; sort digest + submission ID tie-break; lấy min counts không hoàn lại. C08 một pool đều, C07 riêng strata Dealer; không lấy 15 toàn hệ thống hoặc quota Dealer cho C08. Version/canonical test vectors phải duyệt ở A, không tự đổi algorithm trên batch frozen.
- Encryption đề xuất dùng authenticated Laravel encrypter theo abstraction có version/key_id; source hiện cipher AES-256-CBC có MAC, không tự đổi sang GCM hoặc tự viết crypto. Mã hóa seed/manifest/PII snapshot/note trước persist; ciphertext LONGTEXT vì overhead/base64, không ép seed vào CHAR(64). Cần chốt app-key ring hay audit-specific managed key, rotation/backup/restore/key access trước migration. Không có raw key trong DB, metadata, log hoặc tài liệu; chưa đọc key thực.
- Content digest tính trên canonical plaintext hoặc HMAC theo loại nội dung, không trên ciphertext có IV ngẫu nhiên. Source fingerprint/payload có phone/note dùng keyed digest để tránh dictionary attack; store digest_key_id/version, không public digest/manifest cho arbitrary probing. Cần chốt integrity scope/digest format; thay key không silently rewrite frozen historical fingerprints. Old keys cần giữ an toàn trong retention hoặc có crypto-erasure policy được duyệt, khi xóa key không còn hứa tái lập lịch sử.
- Full snapshot của selected items là tối thiểu cho script/contact được duyệt; manifest candidates chỉ IDs/fingerprints/metadata đủ review, không toàn phone/name/address để tái lập sort. AwardResults fingerprint hiện gồm cả private Sales/Dealer; giữ baseline private, không expose Sales qua public URL. Digest không phải encryption hoặc authorization; hidden serialization, private storage/no-store/log redaction vẫn bắt buộc.
- Freeze immutable content khác mutable projection: seed/manifest/selected membership/attribution/baseline không thay; queue/latest attempt/claim/stale có version/events. Encryption rewrap nếu cần phải có operational contract/version, giữ canonical digest/history, không gọi rewrap là resample.
- Manifest LONGTEXT có giới hạn packet/memory/sort/transaction duration chưa đo. A cần capacity review từ môi trường test được phép; không thêm bảng candidates/artifact ngoài bốn bảng nếu chưa có lý do và approval schema riêng. Reproduction gồm version + seed + manifest + config → selection digest/count/order đúng, không đòi source live bất biến.

### 7. Transaction và concurrency protocol dự kiến

**Tạo/freeze:** authorize active Admin → validate tháng đã kết thúc/cutoff/review completion/purpose/Award → check receipt/business key → lấy seed server một lần cho logical command → transaction nhất quán → đọc cohort, alias/identity/demo, Dealer và Award baseline từ **cùng consistent snapshot** → validate/compute manifest/selection → insert batch frozen đủ cột với K01/K02 → insert items → append freeze event cùng actor → kiểm counts/digests → commit → response. Một batch actual0 vẫn có freeze event/manifest/counts, không event completed. Không lưu row preparing thiếu mandatory data. Nếu duplicate insert thua race, rollback rồi load batch thắng dưới quyền hiện tại, không update nó hoặc đổi seed để né K01.

Isolation đề xuất explicit repeatable-read consistent snapshot theo khả năng driver/server đã review, không giả default connection. Tránh mixing locking current-read (có thể thấy bản mới) với snapshot cũ rồi gọi đó là một cohort. Nếu cần khóa/recheck Award metadata, acquisition phải trước snapshot đọc và record đúng baseline; locks/gates publication tương lai phải cùng protocol. Approval thay đổi concurrent sau snapshot thuộc late/drift, không tự tăng N; nếu TMV đòi freeze thấy mọi approval trước cutoff thì cần barrier/closure protocol được chốt, timestamp một mình không đủ. Retry deadlock là retry transaction chưa commit với cùng logical intent, không reroll một batch đã commit.

**Claim/ghi kết quả (thiết kế cho C, chưa triển khai A):** authorize lại; token/version/lease do server xác minh, không tin hidden form; atomic row lock/CAS, allocate attempt_no dưới item lock, append attempt + events + projection cùng transaction. Nhiều Admin được phép xử lý nhưng không được ghi đè claim/người khác. Chưa có TTL/takeover policy thì chưa mở mutation đó. Nếu dùng batch event_sequence thì lock batch trước item theo cùng thứ tự ở mọi action; snapshot read không mutate submissions.

Nếu call action cần source current-read, thứ tự đề xuất khi có nhiều loại lock: intake mutex → submission(s) tăng ID → Award nếu liên quan → batch → item → attempt/event. Không action nào lock audit rows rồi quay lại lấy intake mutex; validate protocol với mọi action hiện hữu trước triển khai. Không cầm intake mutex suốt lúc người dùng gọi điện. Generation/void/reopen/correction không được mở để xử lý lỗi race khi chưa có contract. Test deadlock/timeout/replay nhiều connection thật, không coi transaction PHPUnit một connection đủ bằng chứng.

Model read-only guards/app action checks không tạo DB snapshot; contract closure, snapshot isolation và SQL lock order phải được kiểm riêng. Ràng buộc unique bảo vệ duplicate batch, không tự chống sửa source sau freeze hoặc bảo đảm Top 3 live không đổi.

### 8. Decision register còn thiếu — không tự điền mặc định

| ID | Quyết định cần xác nhận | Chặn thời điểm nào |
|---|---|---|
| OPEN01 | Cutoff cụ thể, zone/boundary inclusive/exclusive, grace, nguồn xác nhận review hoàn tất và ai xác nhận; quan hệ cutoff/freeze sau period_end | Trước service freeze A; cột/evidence contract cần ổn định trước migration |
| OPEN02 | TMV approval UC01–UC10 + hướng xử lý mới, effective month/policy version và approval schema | Trước authorization triển khai/migration A |
| OPEN03 | PII snapshot field allowlist, full phone/plate/note/seed/manifest disclosure, retention/deletion/crypto-erasure/backup và quyền truy cập | Trước migration vì ảnh hưởng columns/FK/key management, không chỉ trước UI |
| OPEN04 | Key owner/store/rotation/recovery và choice app ring/audit key; digest/fingerprint/canonical format cuối | Trước encryption schema/services A; không mặc định đã có KMS hoặc keys production |
| OPEN05 | Initial generation, quyền/lý do/scope void/replacement, correction/reopen, cách preserve history; ai được sửa kết quả đã chốt | Structure refs review trước migration; endpoints ngoài initial freeze khóa tới khi duyệt |
| OPEN06 | Duplicate resolution workflow/evidence hợp lệ, identity consistency và demo include/retired runtime policy conflicts | Trước tạo batch A; không chọn representative hoặc bỏ ngầm conflict |
| OPEN07 | Baseline Top 3 được chốt lúc nào sau cutoff, Award draft/published scope, thiếu Dealer/zero lifecycle và roll-up requested khi m<3 | Trước award_top3 A; không coi underfill/empty là pass |
| OPEN08 | Isolation/DB version/enforcement, FK retention vs xóa history, manifest capacity, actor/session race semantics, failure/retry contract | Review kỹ thuật trước migration/A tests; runtime chưa xác minh |
| OPEN09 | Claim TTL/heartbeat/takeover, ba câu/outcome/override, attempt limit/retry sau một ngày, counts/tabs/rate denominator/last-item UX | Trước B/C/D tương ứng; structural nullable fields không phải approval ghi gọi |
| OPEN10 | Threshold [x]%, quyền thu hồi/điểm/loại Dealer, finding dedupe/reversal/đôn hạng/publication/amendment | Chỉ E, không dependency ngầm để A tự làm penalty; published snapshot bất biến |

Mọi active Admin có quyền tạo/chốt và ghi attempt hợp lệ theo UC, nhưng correction, PII/seed disclosure, void/generation mới, penalty và công bố là capabilities tách riêng. AwardPolicy::publish hiện allow active Admin không chứng minh workflow công bố đã được duyệt. Không đổi policy, không thêm Manager, không gọi SubmissionVerification/approve/reject để lưu kết quả audit.

### 9. Danh sách triển khai A dự kiến và tiêu chí nghiệm thu

Mọi path sau chỉ là danh sách chuẩn bị; chưa được tạo. Đề xuất tách A thành ba phần để duyệt, không triển khai B/C/UI/E trong A.

| Phần A | Migration/models/services/tests dự kiến | Tiêu chí hoàn thành tương lai |
|---|---|---|
| A1 — schema/models/policy | `database/migrations/<timestamp>_create_call_audit_batches_table.php`, tiếp theo `..._create_call_audit_items_table.php`, `..._create_call_audit_attempts_table.php`, `..._create_call_audit_events_table.php`; constraint latest_attempt FK sau attempts trong thứ tự migration phù hợp. `app/Models/CallAuditBatch.php`, `CallAuditItem.php`, `CallAuditAttempt.php`, `CallAuditEvent.php`; `app/Policies/CallAuditBatchPolicy.php`; `tests/Feature/CallAuditSchemaTest.php` | Bốn bảng, K01–K08/actor/privacy constraints review; không source schema cũ đổi. DDL MySQL có thể implicit commit nên không hứa rollback transaction migration; kế hoạch partial failure/rollback/history phải duyệt riêng |
| A2 — eligibility/crypto/algorithm | `app/Services/CallAudit/CohortBuilder.php`, `MonthlyScope.php`, `SourceFingerprint.php`, `ManifestCodec.php`, `SeedCipher.php`, `SamplingAlgorithm.php`; `tests/Unit/CallAuditSamplingAlgorithmTest.php`, `CallAuditManifestTest.php`; `tests/Feature/CallAuditCohortTest.php` | QR/C03 phone, submission units, demo guard, duplicate resolution gate, deterministic vectors/key lifecycle; không query/write production, không đổi Leaderboard để đủ mẫu |
| A3 — atomic freeze/reproduction | `app/Actions/CallAudit/CreateAndFreezeBatch.php`; `app/Services/CallAudit/ReproductionVerifier.php`; `tests/Feature/CallAuditFreezeTest.php`, `CallAuditFreezeConcurrencyTest.php`, `CallAuditPrivacyTest.php`; multi-process fixture nếu harness test được duyệt | Consistent snapshot, one batch/generation, actor/event atomicity, underfill/zero/late approval, seed/private manifest bảo vệ; reproduction đúng dù source đổi. Không route/API write/UI/scheduler mới trong A nếu chưa có task riêng |

Tên file rút gọn trong cùng ô nằm trong thư mục path đầy đủ ngay trước đó; timestamp chỉ placeholder thứ tự, không migration thật. Bản P09B nêu một migration bốn bảng là phương án tối thiểu; P09C đề xuất bốn migration theo dependency để review độc lập và xử lý composite FK/cycle rõ ràng. Đây là thay đổi cách đóng gói DDL có lý do kỹ thuật, **không thêm thực thể thứ năm**; reviewer có thể chọn một migration nếu vẫn bảo đảm thứ tự/partial failure plan. Không chỉnh P09B để mất lịch sử phương án cũ.

Test cases bắt buộc dự kiến:

- Monthly boundary start inclusive/end exclusive và cutoff theo rule đã duyệt; trước tháng kết thúc hoặc review chưa hoàn tất phải từ chối. Approved visible/late commit/approved sau freeze; pending/rejected và hồ sơ ngoài tháng không lọt pool, score gốc không đổi.
- N=0/1/59/60/61 cho C08; mỗi Dealer N_d=0/1/4/5/6 cho C07; baseline m=0/1/2/3 theo policy đã duyệt. Counts/order/manifest/selected membership khớp; zero không completed/Đạt; không borrow slot hoặc chọn lặp.
- Phone raw +84/0084/separator; thiếu normalized nhưng raw hợp lệ; hai alias disagree/invalid; duplicate phone/plate/Facebook; source integrity, Sales chuyển Dealer; demo normal/include/retired guard. Không tự chọn đại diện, không rewrite source.
- Hai purpose overlap cùng submission nhưng items riêng và không reuse attempts. Business key duplicate giữa hai Admin/different request key; cùng key khác payload; hai creators cùng scope concurrent; abort giữa insert items/event không để batch dở; timeout sau commit replay đúng frozen batch/actor.
- Seed/manifest codec round-trip, tamper/wrong key/key rotation/vector/canonical ordering; reproduce cùng selection digest khi nguồn đổi; memory/packet/capacity review trên fixture đủ lớn được phép. Không expose plaintext qua serialization/log/error hoặc key metadata.
- Active/blocked/guest/Sales/Dealer/Manager authorization; Admin khác creator vẫn có quyền tạo/chốt theo UC, không tự được xem seed/full PII hoặc correction/publish/penalty. Actor IDs đúng session; duplicate/mismatched composite references bị chặn; constraints không cascade history.
- Frozen fields/attempts/events không update/delete qua đường ứng dụng cho phép; mutable projection có history/version. Published Dealer/Sales snapshots, moderation/verification/điểm không thay do freeze. Test append-only không được quảng cáo là chống DBA.

Trước test ghi dữ liệu: xác minh guard và DB cách ly **toyota_testing**, quyền chạy schema/migration riêng, harness nhiều connection thật với fixture cleanup được duyệt. Tests hiện dùng DatabaseMigrations/RefreshDatabase có thể chạy DDL/reset; không copy/run mù dưới lệnh cấm migrate/reset. Chỉ tests chọn lọc của A sau authorization, không full regression. Trong P09C không chạy test/unit/build/Artisan hoặc bất kỳ DB preflight nào.

### 10. Checklist duyệt riêng trước cho phép triển khai A

- [ ] TMV reference/người duyệt/ngày/effective month cho UC và schema, vẫn phân biệt USER_APPROVED với TMV approval.
- [ ] OPEN01–OPEN08 được giải quyết đến mức đủ migration/freeze; không placeholder cutoff/retention/key hoặc silent business default trong source.
- [ ] Bốn bảng/constraint/index/FK deletion/cross-aggregate refs/nullable groups/actor fields được reviewer duyệt; latest_attempt cycle và DDL partial failure có phương án.
- [ ] Manifest toàn pool, seed encryption/HMAC versions, source fingerprints/PII minimum và quyền disclosure/retention/restore được duyệt.
- [ ] Underfill/empty không fake complete; Top 3/cutoff/late approval/drift và generation không tự reroll; counts có invariants server-side.
- [ ] Snapshot protocol/lock order/idempotency/races được review; không can thiệp scoring/moderation/verification/publication.
- [ ] Môi trường test/harness/schema preparation/rollback được ủy quyền riêng, có guard toyota_testing và kế hoạch test A chọn lọc; không dùng task P09C làm phép chạy migration.

## Kết quả kiểm thử

Kiểm tra tài liệu bằng PowerShell/regex **PASS**: đủ 9 mục tracking, đủ 4 bảng review, 10 quyết định OPEN01–OPEN10 liên tiếp, liên kết Markdown local tồn tại và không trailing whitespace. Đối chiếu Git status `--porcelain=v1 --untracked-files=all` với baseline chỉ thêm đúng `docs/figma/phase-p09c-schema-review.md`, không tăng/giảm trạng thái file có sẵn. Rà soát nội dung bao phủ K01–K08, actor/history, manifest/encryption/seed, snapshot/concurrency và kế hoạch A1–A3; chỉ dùng công cụ chỉnh sửa cho file P09C.

Không chạy PHPUnit, unit tests, build, Artisan, migrate, seed, reset hoặc database preflight; không kết nối production. Tất cả tests mục 9 là kế hoạch tương lai, không có PASS ứng dụng/schema runtime mới.

## Những lỗi còn tồn tại

OPEN01–OPEN08 và phê duyệt TMV/schema authorization còn chặn triển khai A; OPEN09/10 chặn giai đoạn sau tương ứng. Không có schema/runtime evidence hoặc capacity benchmark mới, chưa chốt cutoff/retention/disclosure/generation/void. Không có bug source nào được sửa; review hoàn tất trên giấy không chứng minh DDL hoặc race đã pass. Không đổi điểm, xếp hạng, published snapshots hay quyền ngoài phạm vi.

## Task hoặc bước tiếp theo

Trình TMV/reviewer bản này và decision register, ghi quyết định/approval có reference; hoàn thiện OPEN bắt buộc trước A. Chỉ sau approval và task riêng mới viết/review migration/models/services trên môi trường test, rồi chạy checks được phép. B/C/D và effects/publication E tiếp tục là các giai đoạn riêng; không tạo migration từ P09C.
