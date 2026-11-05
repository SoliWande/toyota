# Triển khai phương án A giảm dung lượng QA

## Mục tiêu
Chỉ xử lý tối đa343 PNG duplicate trong manifest đã được người dùng duyệt; xác minh hash/tham chiếu, backup và thử restore trước khi untrack/xóa local. Chuyển output các lần QA mới sang ignored, giữ inputs và lịch sử.

## Trạng thái
DONE — 2026-11-05. Tạo IN PROGRESS ngày04/11 trước thay đổi; tiếp tục sau khi người dùng yêu cầu “tiếp tục”. Hoàn tất phương án A và kiểm tra liên quan. Một QA CSS assertion lỗi thời, ngoài phạm vi, được tái hiện và ghi riêng bên dưới; không tuyên bố toàn bộ QA suite PASS.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, docs/qa-artifact-storage-audit.md, manifest và QA scripts. Git status ban đầu có2 tài liệu audit untracked từ task trước; giữ nguyên. Allowlist343 ảnh/77.947.839 bytes,209 nội dung riêng/45.584.647 bytes. Chưa xác nhận lại từng file.

## Các file source code liên quan
.gitignore, công cụ QA trong docs/figma/qa, chỉ343 PNG trong duplicateCandidates và canonical để đối chiếu. Không sửa app/resources/routes/config/database/public.

## Những thay đổi đã thực hiện
Ghi chép ban đầu: tạo tracking, chuẩn bị preflight và backup. Hai tài liệu audit untracked có sẵn vẫn giữ nguyên, không quy là file mới của bước triển khai.

### Thực hiện 04–05/11/2026
- Preflight lần đầu và kiểm tra lại trước untrack: đủ343 PNG, đúng SHA256 của source/canonical, không có working/index change ở các ảnh, không symlink/reparse point. Quét tham chiếu tĩnh547 file và review thủ công các PNG reader/template. PNG input của visual scripts nằm ở before baselines, không phải343 output được duyệt. Không tìm thấy phiên QA đang chạy sau khi kiểm tra process với quyền đọc phù hợp.
- Sao lưu343 đường dẫn theo209 nội dung riêng vào archive deduplicate. Không ghi đè backup đã có. Đọc lại archive từ disk, kiểm tra checksum TAR/GZIP, thử khôi phục **đủ343 đường dẫn ra file trên disk**, đối chiếu SHA256 rồi chỉ bỏ các file restore-test vừa tự tạo, không xóa thư mục. Công cụ TAR độc lập đọc được210 entries (manifest +209 PNG objects).
- `git rm --cached --pathspec-from-file=.laravel-tools/qa-plan-a/eligible.paths --pathspec-file-nul`: bỏ tracking đúng343 PNG; lúc này file local vẫn còn. Kiểm tra staged diff chỉ có343 deletions cùng allowlist, không file khác.
- Sau đó PowerShell kiểm tra lại index checksum, backup SHA, từng absolute/resolved path thuộc QA screenshots root, ancestor không reparse, extension PNG và source/canonical SHA. Chỉ sau khi toàn bộ343 file đạt điều kiện mới dùng `Remove-Item -LiteralPath` từng file. Không recursive delete, không wildcard, không xóa nguyên thư mục và không chuyển danh sách sang shell khác.
- Hậu kiểm:343 ảnh vắng mặt local,343 canonical còn đúng hash; **873 file bằng chứng/metadata được bảo vệ nguyên hash**. Không có hash mismatch hoặc file phải bỏ qua. Baseline, Figma reference, fixture, JSON evidence, gallery và docs lịch sử giữ nguyên.

## Kết quả dung lượng và backup

| Hạng mục | Bytes | MB thập phân |
|---|---:|---:|
| PNG gốc đã untrack/xóa local | 77.947.839 | 77,95 |
| docs/figma/qa trước task | 239.109.609 | 239,11 |
| docs/figma/qa sau sửa và smoke test | 161.311.778 | 161,31 |
| Giảm thực tế trong QA, đã trừ helper/output mới | 77.797.831 | **77,80** |
| Archive backup deduplicate | 44.147.155 | 44,15 |
| Backup folder gồm verification.json | 44.147.502 | 44,15 |
| Giảm ròng khi tính cả QA và backup giữ trên cùng workspace | 33.650.329 | **33,65** |

Đây là tổng file length logic, không phải số block filesystem/free-space Windows. Mức33,65MB tính QA + backup; chưa trừ các ledger/báo cáo quản trị nhỏ bên ngoài hai vùng này. Không hứa .git hoặc toàn ổ đĩa giảm77,95MB. Git vốn chia sẻ blob duplicate; lịch sử/HEAD commit không bị rewrite.

- Backup: `.laravel-tools/qa-backups/plan-a-20261104/duplicates.tar.gz` (đã ignored, giữ lại).
- SHA256: `59dec685ebd814add3615cc1c5bcf636565d072b41a098f015fed3c1318a2ecd`.
- Commit nguồn: `a9455445397bf764bf4d9b70e701a57d000c80a4`.
- [Kết quả và danh sách chính xác343 file/canonical](qa-artifact-storage-plan-a-result.json). Manifest audit gốc là snapshot trước dọn, được giữ nguyên; không sửa số liệu lịch sử thành hiện trạng mới.

### Khôi phục khi cần
Archive chuẩn tar.gz chứa `manifest.json` với files/path/canonical/sha256 và `objects/<sha256>.png`. Có thể giải nén vào một thư mục restore riêng đã xác minh, chọn record theo path, đối chiếu hash object rồi mới copy về đường dẫn PNG gốc nếu chưa có file tại đó. Không extract/copy hàng loạt đè working tree. Canonical hiện vẫn có thể dùng để phục hồi một bản duplicate sau khi kiểm tra hash. Không tự xóa backup theo retention trong task này.

## Chuyển hướng output QA và .gitignore

- `.gitignore`: thêm8 rule cho artifacts/, test-results/, playwright-report/, blob-report/, log, webm, mp4 và trace*.zip trong QA. Không ignore toàn bộ QA/screenshots/PNG/JSON; không đổi ignore của baseline hoặc reference.
- Thêm `docs/figma/qa/artifact-paths.mjs`: output mới vào `docs/figma/qa/artifacts/<QA_RUN_ID hoặc latest>/screenshots/`. Input ưu tiên dữ liệu do cùng run sinh, fallback read-only về screenshots lịch sử. Validate relative path/run ID; thiếu input vẫn báo lỗi, không tự dựng dữ liệu giả.
- Sửa đúng phần import/output/input/gallery link trong18 MJS writers: Awards visual/regression/interactions/sticky-footer; competition visual/state/font audit/font restored; dealer-call; hero-heading; leaderboard baseline/visual/final/interactions; lookup visual/progress/result-panel/sticky-footer. Không bỏ hoặc nới assertion UI/business nào.
- Thêm `artifact-paths.php`, dùng cho output matrix/diagnostic của `LeaderboardFinalMatrixTest.php` và `LeaderboardFinalDiagnosticTest.php`. Không thay fixtures, guard database, assertions hoặc logic truy vấn. PHP và Node dùng cùng run ID/path; matrix hiện có vẫn là fallback.
- Gallery mới liên kết đúng tới Figma/baseline lịch sử qua relative link tính lại, không dùng độ sâu thư mục cũ. Gallery/report lịch sử không bị ghi đè.
- `release-manifest.py`: report/checksum/archive của **lần QA mới** vào artifacts/<run>/release, giữ nguyên report/archive cũ và logic allowlist/secret checks. Đây vẫn là gói QA NOT-DEPLOYABLE, không phải thay quy trình deploy production. Không chạy lại packaging hoặc build production trong task này.
- Thêm `artifact-paths.test.mjs` và [hướng dẫn vận hành](figma/qa/ARTIFACTS.md). Các CLI/phase argument cũ vẫn dùng được; `COMPETITION_QA_OUTPUT` override hiện hữu vẫn được giữ. Nên dùng run ID mới cho mỗi lượt để tránh latest tích lũy dữ liệu cũ; không dùng chung ID cho hai phiên đồng thời.

## Quyết định kỹ thuật quan trọng
Không xóa hash mismatch hoặc input/ref đang dùng. Giữ baseline/reference/JSON/docs. Backup deduplicate theo SHA256 trong .laravel-tools/qa-backups đã ignored; báo riêng dung lượng backup và giảm net toàn workspace, không đánh đồng với giảm trong QA. Không rewrite history, không clean/reset hoặc xóa nguyên thư mục.

## Kết quả kiểm thử
Kết quả mới trong task này, không tái dùng PASS của các task UI trước:

| Kiểm tra | Kết quả |
|---|---|
| Hash343 source/canonical, path/reparse, staged allowlist | PASS trước untrack và ngay trước delete |
| Backup/restore | PASS343 file,209 unique objects; TAR listing210 entries; archive SHA không đổi sau dọn |
| Hậu kiểm bằng chứng | PASS873 protected files +343 canonical đúng hash, không có file khác bị xóa |
| Node artifact-paths.test.mjs | PASS5 tests: input fallback/fresh, PHP/Node cùng output, gallery link, traversal rejection, helper imports |
| dealer-call-check.php | PASS25 checks, DB disabled; fixture ngoài public |
| dealer-call-visual.mjs | PASS320/390/768/1024/1440, sau khi xóa duplicate; screenshot và JSON thực sự ở ignored run output |
| render-check.php | PASS các kiểm tra render D1–D15/forms/CSRF/preview; DB disabled |
| asset-check.php | PASS22 PNG production tồn tại, không sửa assets |
| Bốn DOM harness validation | PASS frontend/lookup/step-two/supplement với Node22 adapter test-only đã có trong .laravel-tools/lookup-sticky-footer/resolve-js.mjs |
| Syntax/lint | PASS25 MJS node --check, PHP lint3 file, Pint3 PHP QA files, Python AST parse; không chạy release packaging |
| Git diff và staged diff --check | PASS; chỉ343 PNG deletions staged, source production không có diff |
| git check-ignore | PASS output mới/report dirs/backup ignored; scripts và historical inputs vẫn tracked |

**FAIL ngoài phạm vi đã tái hiện:** `node docs/figma/qa/rules-css-check.mjs` exit1, dòng50, `undefined !== '2ch'`. `docs/context-recovery-2026-11-03.md` đã ghi cùng failure: đồng bộ Figma trước đó bỏ min-width nhưng assertion cũ còn đòi2ch. Script này và CSS/build ứng dụng không đổi trong task dọn; không sửa test hoặc thêm CSS để làm PASS. Không coi toàn bộ QA suite xanh.

Lần đầu chạy trực tiếp4 DOM harness bằng Node22 cũng báo ERR_MODULE_NOT_FOUND cho extensionless `resources/js/customer-address`. Đối chiếu tracking lookup-sticky-footer thấy adapter đã có; chạy lại bằng adapter đó PASS cả4. Không sửa import production hoặc test assertion. Loader có ExperimentalWarning, không phải failure.

Lệnh chính:

```powershell
node --test docs/figma/qa/artifact-paths.test.mjs
php docs/figma/qa/dealer-call-check.php
$env:QA_RUN_ID='plan-a-smoke-20261105'
node docs/figma/qa/dealer-call-visual.mjs
php docs/figma/qa/render-check.php
php docs/figma/qa/asset-check.php
node --loader ./.laravel-tools/lookup-sticky-footer/resolve-js.mjs docs/figma/qa/frontend-check.mjs
node --loader ./.laravel-tools/lookup-sticky-footer/resolve-js.mjs docs/figma/qa/lookup-validation-check.mjs
node --loader ./.laravel-tools/lookup-sticky-footer/resolve-js.mjs docs/figma/qa/step-two-validation-check.mjs
node --loader ./.laravel-tools/lookup-sticky-footer/resolve-js.mjs docs/figma/qa/supplement-validation-check.mjs
php vendor/bin/pint docs/figma/qa/artifact-paths.php docs/figma/qa/LeaderboardFinalMatrixTest.php docs/figma/qa/LeaderboardFinalDiagnosticTest.php --test
git diff --check
git diff --cached --check
```

Không chạy feature tests ghi database, migrate/seed, npm build hoặc toàn bộ visual matrix chỉ để sinh thêm hàng trăm ảnh. Không dùng screenshot smoke như bằng chứng nghiệm thu lại toàn giao diện Figma.

## Git status và các file thay đổi
- **Staged:**343 PNG deletions, chính xác allowlist đã duyệt. Không có staged change khác; chưa commit.
- **Modified chưa stage:**22 file: .gitignore;18 MJS writers;2 PHP matrix/diagnostic writers; release-manifest.py. Danh sách đường dẫn đầy đủ trong sourceChanges của result JSON.
- **File mới của task:** ARTIFACTS.md, artifact-paths.mjs, artifact-paths.php, artifact-paths.test.mjs, tracking này và result JSON (6 file).
- **Untracked có sẵn:**2 tài liệu audit gốc vẫn untracked, không stage hoặc ghi đè; vì vậy status tổng có8 untracked.
- Trong QA còn908 file cũ tracked (1.251−343). Toàn bộ baseline, reference và evidence không thuộc allowlist vẫn ở nguyên vị trí.
- Không có thay đổi app/, resources/, routes/, config/, database/, public/, composer/package/CI hoặc production assets; không đổi Git history/ref. Các ledger/backup/smoke output nằm ignored.

## Những lỗi còn tồn tại
Không còn lỗi liên quan được phát hiện trong việc dọn allowlist và routing output. Không có file bị skip do mismatch/ref rủi ro. CIM/Git read bị sandbox access/ownership lúc đầu đã chạy lại với approval đúng thao tác, không đổi safe.directory/security config hoặc dừng process của người dùng.

Giới hạn: chưa chạy mọi script browser/feature matrix, CI ngoài repository hoặc deploy thật. `rules-css-check` FAIL cũ được ghi riêng, cần task assertion theo Figma/source hiện hành nếu muốn toàn QA xanh. Backup vẫn chiếm44,15MB trên cùng workspace; không tự dọn backup hoặc các398 PNG ứng viên B còn lại. Dung lượng .git không được đảm bảo giảm vì không rewrite history.

## Task hoặc bước tiếp theo
Phương án A đã hoàn tất. Review .gitignore/QA changes và các file mới trước khi stage/commit cùng343 deletions; không commit chỉ phần staged mà bỏ quên hướng dẫn/routing mới. Giữ backup cho tới khi người dùng duyệt retention khác. Phương án B hoặc xử lý assertion rules-css cũ là task riêng, chưa được thực hiện.
