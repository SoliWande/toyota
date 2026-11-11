# P09G — A2 Eligibility & Sampling Algorithm

## Mục tiêu

Triển khai services cohort approved theo tháng, phone/identity checks, manifest/fingerprint và HMAC sampling tái lập cho C08/C07; chỉ đọc dữ liệu nguồn, không freeze/persist batch.

## Trạng thái

DONE phạm vi A2 — 2026-11-11. Kiểm thử chọn lọc **46 tests/993 assertions PASS**, không SKIPPED/FAIL; syntax/Pint/diff và postflight được ghi bên dưới. UC01–UC10/V1 được người dùng được TMV ủy quyền phê duyệt; A1 đã nghiệm thu tại P09F. Không cần approval riêng để viết A2. READY ở A2 chỉ nghĩa là plan/cohort không mâu thuẫn, không phải đã freeze/hoàn tất gọi/Đạt C07.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, mục9 P09B, P09C/P09D/P09F, source Leaderboard/AwardResults/DemoScope/normalizers/integrity và test guard. Working tree có thay đổi các phase trước; giữ nguyên. Chưa có sampler/eligibility services trước A2; không sửa source nghiệp vụ hiện hữu.

## Các file source code liên quan

Chỉ thêm các file mới:

- `app/Services/CallAudit/CohortBuilder.php`, `PhoneEligibility.php`, `SourceFingerprint.php`.
- `app/Services/CallAudit/ManifestCodec.php`, `SamplingAlgorithm.php`, `SamplingPlan.php`, `SamplingPlanner.php` (cùng thư mục trên).
- `tests/Unit/CallAuditSamplingAlgorithmTest.php`, `tests/Feature/CallAuditSamplingTest.php`.
- `tests/Fixtures/call_audit_sampling_snapshot.php` và tracking P09G này.

LeaderboardService/AwardResults/DemoSubmissionScope/CustomerSubmission/approval/verification/public snapshots, A1 models/policy/schema và các tests/trackings cũ giữ nguyên. Không thêm config/.env hoặc migration.

## Những thay đổi đã thực hiện

Đã triển khai cohort approved theo submitted_at trong bounds tháng của LeaderboardService, phone QR/C03 và alias consistency, demo scope/integrity/duplicate checks, source fingerprints. C08 một pool max60; C07 đọc lại Award monthly draft từ DB, dùng preview/fingerprint AwardResults hiện hữu và strata max5/Dealer Top3 đúng tháng, không đôn hạng/bù Dealer.

Đã có canonical codec/version, seed CSPRNG32 bytes, deterministic HMAC sort/domain separation, seed commitment/selection digest và replay chỉ từ manifest/seed không query live. SamplingPlan giấu manifest/seed trong private properties, JSON chỉ summary/counts/issues; entry planner kiểm active Admin qua policy create hiện hữu.

Không có CreateAndFreezeBatch, persistence/encryption service, route/UI/scheduler, gọi điện, thao tác approval/verification/điểm/công bố hoặc INSERT/UPDATE call_audit_*. SQL guard trong feature tests từ chối mọi write audit và DDL; fixtures cha ghi trong transaction rollback riêng.

## Quyết định kỹ thuật quan trọng

Một submission là một unit; Dealer từ submission.dealer_id, tháng theo bounds hiện hành. Conflicts BLOCKED với codes/IDs không PII; không tự chọn representative. Manifest versioned/private và seed CSPRNG chỉ trong bộ nhớ; A3 mới persist/encrypt/gate cutoff/freeze.

### Cohort và trạng thái

- `CohortBuilder::build(month)` là API backend nội bộ chỉ đọc, caller phải authorize; `SamplingPlanner::general/awardTop3(actor, ...)` kiểm policy active Admin. Status READY/BLOCKED, không trả candidate khi có conflict, không repair nguồn. Không cần member group/Dealer active hoặc lấy Dealer hiện tại của Sales. QR và Sales đều được xét nếu integrity hợp lệ.
- Cohort áp dụng approved và `[period_start,period_end)` theo submitted_at trong storage convention/timezone hiện tại; reviewed_at không phân tháng. Không hard-code VN00 hoặc đổi calendar. Current-approved sau tháng vẫn có trong read cohort nếu chưa có cutoff filter: cutoff/approval completion/visibility và tháng đã kết thúc là **gate A3**, không tự đặt deadline trong A2.
- Raw phone/normalized dùng RegistrationPhone và regex `^0[1-9][0-9]{8,9}$/D`. Thiếu/sai phone nhất quán → EXCLUDED; hai alias không rỗng khác nhau → BLOCKED dù một alias hợp lệ. Missing normalized của Sales legacy không tự loại khi raw hợp lệ. Không ghi canonical value ngược vào hồ sơ.
- Kiểm identity trên toàn monthly-approved cohort sau demo scope, trước phone exclusions/chia Top3. Phone aliases hợp lệ trùng, plate canonical trùng, Facebook raw/normalized/generated identity không nhất quán/trùng, thiếu/invalid Facebook hoặc source integrity lỗi → BLOCKED. Invalid phone không che duplicate plate/Facebook. Groups/issues chỉ code + sorted IDs, không phone/biển/URL/name thô. Không tự giữ newest/first hoặc bỏ cả nhóm để đủ quota.
- Demo dùng nguyên `DemoSubmissionScope::apply`/guards, không tự loại thêm theo tag/name. Normal branch loại owned IDs; retired branch yêu cầu guard hợp lệ; include_in_metrics khi enabled/environment hợp lệ vẫn giữ semantics hiện hữu. Guard/ownership LogicException → demo_scope_blocked, không pool rỗng READY. Các nhánh demo bị mock ownership/receipt trong **tests cách ly**, không đổi code/config runtime hoặc tạo receipt thật.

### Counts, Top3 và replay

- C08 requested60/actual min(60,N); C07 mỗi Dealer requested5/actual min(5,N_d). Khi baseline m<3, giữ đúng m Dealer, requested tổng5×m/cap15, không tạo Dealer giả; m=0 thì requested/actual0, cap15. Underfill/empty không phải completed/Đạt hoặc quyền công bố.
- C07 lấy cả Sales/Dealer baseline private cùng fingerprint đúng AwardResults, chỉ chọn từ các Dealer thật trong Top3 của monthly draft. Published/weekly/missing/stale-object Award không được dùng làm draft: planner query lại trạng thái nguồn. Preview/live ranking vẫn live, không thay published snapshots. Cohort conflicts ngoài Top3 vẫn BLOCKED trước khi chia pool.
- Manifest candidates của C08 là toàn pool hợp lệ, C07 là pool thuộc Dealer Top3 sau global checks; context giữ digest monthly cohort đã kiểm và demo exclusion digest. Candidates chỉ IDs/attribution/fingerprint, không phone/name/address; baseline có private names nên manifest tuyệt đối không là public API payload. Đọc nguồn select field allowlist, không lấy notes/evidence/address thừa.
- `call_audit_manifest_v1` canonical JSON: sort object keys, giữ list order, chỉ strings/integers/bools/null/arrays; không floats/objects hoặc sparse numeric maps. Candidate order theo submission ID; context version riêng eligibility/phone/fingerprint/calendar/demo. Manifest đổi observed_at khi đọc mới; không gọi đó là batch frozen.
- `hmac_sha256_sort_v1`: CSPRNG seed32 bytes; HMAC-SHA256 trên canonical algorithm/domain/submission ID, sort full binary digest tăng dần rồi ID tie-break, prefix min(k,N) không hoàn lại. Domain có purpose/month/bounds/generation/stratum. C08 không Dealer quota/weight; C07 từng namespace Dealer. Uniform dựa PRF HMAC trên submission units, không suy hiệu quả thống kê từ pixel/count mẫu Figma.
- Seed SHA256 commitment và selection digest kiểm replay, kể cả pool rỗng; reject sai seed/version/strata/duplicate IDs/out-of-strata/selection digest. Replay chỉ dùng manifest/seed, không query source. Vector cố định đối chiếu Python stdlib HMAC độc lập: seed bytes0..31, IDs1..8, domain fixture → order `[2,7,1,5,4,8,3,6]`. Sweep512 seeds deterministic là smoke test phát hiện thiên lệch thô, không phải chứng minh thống kê hoặc threshold nghiệp vụ.
- Source fingerprint HMAC dùng key derive có domain riêng từ app.key, key_id không phải secret, bao state/aliases/script/Dealer lịch sử/timestamps; đổi phone mà updated_at giữ nguyên vẫn phát hiện. Không đưa users.dealer_id hiện tại vào fingerprint. Key rotation làm key_id/hash đổi, A3 phải phân biệt key change với source drift; không tự coi hash đổi là lỗi để trừ điểm.
- Seed/manifest chỉ ở memory. Optional seed/generation là tham số backend tin cậy cho test/replay, không contract cho input client/reroll. A3 phải tạo seed một lần, giữ key/version/manifest/digests, encrypt khi persist, validate business key và không resample khi reload. A2 không bảo đảm claim/freeze/publication concurrency bằng một read plan.

### Giới hạn giao dịch

Các read plan dùng DB transactions/nested transactions cho các truy vấn cohort và baseline. Chưa đặt lại isolation server hoặc triển khai closure barrier/locks/source recheck; A3 phải enforce snapshot isolation/cutoff và concurrency đúng quy trình trước khi lưu. Toàn manifest/index/sort hiện ở memory, chưa benchmark volume production; không thêm candidate table/schema/artifact storage ngoài scope.

## Kết quả kiểm thử

| Kiểm tra vừa chạy | Kết quả |
|---|---|
| Preflight/snapshot `php tests/Fixtures/call_audit_sampling_snapshot.php before` | PASS: actual db toyota_testing, host127.0.0.1:3306 TCP/IP, MySQL8.0.30/fk_checks1; audit0 rows; snapshot19 bảng/187 source nghiệp vụ+migrations |
| `php vendor/bin/phpunit tests/Unit/CallAuditSamplingAlgorithmTest.php tests/Feature/CallAuditSamplingTest.php --no-progress --display-skipped` | **PASS 46 tests, 993 assertions**, không skipped/fail |
| `php -l` và `php vendor/bin/pint --test` đúng10 PHP mới | PASS, không chạy formatter trên working tree cũ |
| `php tests/Fixtures/call_audit_sampling_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false |
| Diff/whitespace và cấu trúc tracking | PASS: đúng11 file mới (7 services +3 test/helper +tracking), đủ9 mục; trạng thái các file có sẵn không mất/tăng. Cảnh báo CRLF của Blade phase cũ không thuộc P09G |

Coverage: normalization/alias/invalids, approved/month boundaries, duplicate phone/plate/Facebook và source integrity, demo normal/include/retired guards, global60/no quota/no duplicate/underfill/zero, actual Top3 strata and fingerprint, m<3, historical Dealer transfer/legacy phone, source drift và replay không query, wrong seed/commitment/digest/strata, active Admin authorization, canonical/deterministic vector/domain separation và sweep logic.

Lần feature đầu có1 lỗi fixture: published_at thiếu published_by, bị awards_publication_check chặn. Đã sửa **fixture test** đủ cặp theo schema cũ và chạy lại PASS; không nới CHECK hay đổi AwardResults/publication. Các lần chạy tiếp kiểm các concern canonical/replay/seed commitment, không full regression.

Feature tests dùng bootstrap/guard test hiện hữu, force process env APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing; không URL/socket/read-write override. APP_CONFIG_CACHE là absolute `tests/Fixtures/.p09g-disabled-config-cache.php` không tồn tại, không sửa/xóa cache/.env. SQL guard chỉ cho read/savepoints và write các parent fixture thuộc test trong transaction, cấm write audit và DDL.

Bằng chứng private/ignored: `storage/app/private/p09g-call-audit-sampling/before.json`, `after.json`. Tất cả19 bảng có row count/checksum/schema hash khớp trước/sau, chỉ normalize AUTO_INCREMENT vì rollback không reset counter; không có fixture còn lại. Hash187 file app/migrations có sẵn giữ nguyên; helper không so7 services A2 mới như source bị cấm thay đổi. Không migrate/seed/reset, không production hoặc full regression/build/UI.

## Những lỗi còn tồn tại

Không có test/lỗi source A2 còn FAIL. Runtime production/capacity, atomic freeze/reproduction key retention, cutoff completion và isolation/lock protocol chưa được nghiệm thu ở A2. READY không mở thao tác C07; A3/publication/calls/scoring không nằm trong phạm vi task. Không có source nghiệp vụ cũ hoặc dữ liệu persisted thay đổi; không reset AUTO_INCREMENT của test fixtures.

## Task hoặc bước tiếp theo

Review contract services/manifest v1, sau đó giao A3 riêng để tạo/freeze batch atomically với business key/idempotency/cutoff/evidence/actor/source snapshots/encryption và concurrency tests. A3 phải authorize/mask và giữ private material khỏi routes/public APIs, không reuse calls giữa purposes. Không tự triển khai A3 hoặc UI/calls/penalty/publication từ P09G.
