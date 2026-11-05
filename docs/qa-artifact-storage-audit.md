# Audit dung lượng QA và phương án lưu trữ

## Mục tiêu
Kiểm kê QA, phân loại nguồn/artifact, kiểm tra Git và tham chiếu, đề xuất giảm dung lượng local/repository. Chỉ phân tích; mọi xóa file, untrack hoặc thay .gitignore phải chờ người dùng duyệt.

## Trạng thái
DONE — 2026-11-04, hoàn tất **phân tích và đề xuất**. Chưa duyệt/chưa thực hiện dọn dẹp. Không đồng nghĩa artifact đã được xóa hoặc đã tối ưu repository.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, .gitignore, tracking Awards/sticky footer, README screenshot và các script QA. Git status ban đầu sạch. Không có qa ở root; tìm thấy `docs/figma/qa` và `.laravel-tools/monthly-dashboard/qa`. Quét filesystem bỏ .git/vendor/node_modules và không đi theo symlink; dependencies bên trong vendor/node_modules không thuộc QA của dự án.

## Các file source code liên quan
docs/figma/qa, tests, app/routes/resources, cấu hình build/test và CI nếu có. Chỉ đọc.

Đối chiếu package.json, composer.json, phpunit.xml, vite.config.js, .gitignore, .gitattributes, các tracking docs/figma, MonthlyDashboardTest, các QA PHP/MJS/Python và tất cả Git refs có sẵn local. Không fetch remote hoặc suy đoán cấu hình CI bên ngoài checkout.

## Những thay đổi đã thực hiện
Tạo báo cáo này và công cụ kiểm kê tạm dưới .laravel-tools (đã ignored). Không sửa source ứng dụng, script QA hiện có hoặc .gitignore; không xóa file hay thay Git index/history.

Thêm [manifest kiểm kê và danh sách đề xuất](qa-artifact-storage-audit-manifest.json): đường dẫn từng file, size, SHA256, trạng thái tracked, lý do giữ, 343 bản sao đề xuất cùng bản canonical giữ lại, 741 ảnh ứng viên lưu trữ. `CANDIDATE_AFTER_APPROVAL_AND_ARCHIVE` là đề xuất, không phải lệnh xóa. Manifest giúp duyệt từng file thay vì dùng wildcard xóa cả thư mục.

## Quyết định kỹ thuật quan trọng
Tách artifact có thể sinh lại khỏi baseline/reference và bằng chứng lịch sử. Không coi ảnh không được ứng dụng dùng là được xóa ngay; phải kiểm tra các script so sánh ảnh và tài liệu.

## Tổng dung lượng và cấu trúc hiện tại

Đo mới bằng tổng file size logic, không phải Windows Size on disk; MB=1.000.000 bytes, MiB=1.048.576 bytes. Không tính .git hoặc node_modules vào dung lượng QA. Vì vậy con số hiện tại không bằng 300MB ước tính ban đầu.

| Vị trí | File | Bytes | MiB |
|---|---:|---:|---:|
| docs/figma/qa | 1.251 | 239.109.609 | 228,03 |
| Trong đó screenshots | 1.198 | 238.737.923 | 227,68 |
| 53 file trực tiếp tại QA root | 53 | 371.686 | 0,35 |
| .laravel-tools/monthly-dashboard/qa | 4 | 51.001 | 0,05 |
| **Tổng hai QA roots** | **1.255** | **239.160.610** | **228,08** |

Các dòng screenshots/QA root là thành phần của docs/figma/qa, không cộng thêm vào tổng lần nữa.

Kiểm tra thêm các thư mục có hậu tố QA ngoài hai qa roots: `.laravel-tools/visual-qa` có339 file/7.704.877 bytes (Playwright tooling/dependencies, phải giữ để Codex kiểm thử); `storage/app/private/release-qa` có1 archive `phase-2c-4c-source-NOT-DEPLOYABLE.zip` 3.762.408 bytes, được release manifest/checksum tham chiếu nên giữ. Nếu gộp cả hai khu vực phụ trợ, tổng QA-related là250.627.895 bytes (250,63MB), không bao gồm .git. Hai khu vực này không nằm trong allowlist xóa và không được cộng lại vào 1.255 file của manifest.

Toàn bộ thư mục cấp tính năng dưới `docs/figma/qa/screenshots/`:

| Thư mục | File | MiB |
|---|---:|---:|
| lookup-sticky-footer | 243 | 62,36 |
| awards-sticky-footer | 194 | 31,37 |
| competition-leaderboard | 162 | 30,80 |
| website-competition | 45 | 29,01 |
| competition-awards | 186 | 27,51 |
| hero-heading | 44 | 22,90 |
| result-panel-sidebox | 135 | 10,15 |
| lookup-sync | 85 | 6,43 |
| phase-3c-progress | 77 | 6,15 |
| phase-3a | 19 | 0,84 |
| dealer-call | 6 | 0,13 |
| phase-3b | 1 | 0,03 |

Manifest có cả các thư mục con và bytes chính xác; ví dụ lookup-sticky-footer/before 32.697.528 bytes, after 32.691.641; awards-sticky-footer/before 16.444.050, after 16.448.846. Không cộng các nhóm cha/con trùng nhau.

| Loại file | Số file | Bytes | Phân loại |
|---|---:|---:|---|
| PNG | 1.161 | 234.493.466 | Screenshot render tự sinh **và** ảnh Figma reference; không phải tất cả đều disposable |
| JSON | 45 | 4.414.404 | Geometry/report tự sinh, matrix input, Figma/design evidence, release manifest |
| MJS | 23 | 128.261 | Script kiểm thử/visual/font/interaction, giữ |
| PHP | 15 | 50.713 | DB guard/bootstrap, fixture/router, diagnostic/test, giữ |
| HTML | 6 | 60.593 | 3 gallery QA +3 HTML fixture monthly-dashboard ignored |
| Markdown | 3 | 7.010 | Hướng dẫn/bằng chứng lịch sử QA, giữ |
| Python | 1 | 6.027 | release-manifest.py, giữ |
| SHA256 | 1 | 136 | Checksum release lịch sử, giữ |
| Video/trace/archive/log | 0 | 0 | Không có .webm/.mp4/.zip/.log trong hai QA roots tại lần đo này |

Ảnh chiếm khoảng98% dung lượng. Không phát hiện một video/trace khổng lồ là nguyên nhân; dung lượng đến từ nhiều ma trận before/after, viewport và bản screenshot lặp.

## Git tracking và lịch sử file lớn

- **1.251/1.251 file docs/figma/qa đang tracked**, gồm toàn bộ1.198 file screenshots. Không có artifact untracked trong thư mục đó tại baseline.
- 4 file monthly-dashboard/qa nằm dưới `/.laravel-tools`, đã ignored. Ignore hiện tại chưa có rule cho screenshots QA, test-results, trace hoặc video.
- Dùng `git ls-files`, `git rev-list --objects --all -- <qa roots>` và `git cat-file --batch-check`, không chỉ kiểm tra HEAD. Repository local không shallow; đã quét các refs main, upgrade/laravel-13 và remote-tracking origin/HEAD, develop_new, main, upgrade/laravel-13 đang có. Không fetch nên không khẳng định phản ánh remote mới nhất, reflog hoặc objects unreachable.
- QA history có **798 blob khác nhau, 137.785.957 bytes nội dung chưa nén**. Git tự chia sẻ blob trùng giữa các đường dẫn, vì vậy không bằng tổng size working tree. 8 blob không còn trong QA index hiện tại chỉ tổng28.673 bytes, đều là phiên bản script cũ; không phát hiện artifact lớn chỉ còn trong lịch sử ở phạm vi refs đã quét.
- `git count-objects -v`: loose objects133.356KiB, pack5.931KiB, cộng khoảng136,02MiB **cho toàn bộ repository object store**, không phải riêng QA. Không dùng số này để hứa dung lượng sẽ giải phóng.

Các blob QA lớn đã vào history (đường dẫn đại diện; cùng blob có thể được nhiều path dùng):

| Đường dẫn dưới screenshots/ | Bytes | Blob |
|---|---:|---|
| lookup-sticky-footer/after/inactive-1920.png | 2.024.139 | cc94d09659b66fd32c44691b125a1bd08e12aa14 |
| lookup-sticky-footer/after/landing-1920.png | 1.968.140 | 8e552cbf4fdd67c44ab098950be11003e1bf1a80 |
| lookup-sticky-footer/after/inactive-1440.png | 1.913.955 | 7284a652bbb40d6d643b256b4ff67c61fc359e22 |
| lookup-sticky-footer/after/landing-1440.png | 1.859.584 | 52711e8e13261034eaddb6b911986241fae7fc6b |
| website-competition/figma-desktop.png | 1.688.815 | 033a29ea1ef06b0500a5a66c7020b546109f064c |

Quét thêm mọi blob trên tất cả refs local: lớn nhất toàn repository là `docs/figma/D01 · Trang đại lý.png` 2.227.884 bytes; tiếp theo `database/data/demo-avatars/portrait-02.png` 2.075.957 bytes. Hai file này ngoài QA và ngoài danh sách dọn. Không có blob>=5MB trong refs được kiểm tra. Đây là vấn đề nhiều file ảnh tích lũy, không phải file đơn50–100MB.

**Giới hạn dung lượng Git:** thêm .gitignore không bỏ tracking file cũ. Untrack/xóa bằng commit mới làm nhẹ checkout HEAD, nhưng blob vẫn nằm trong lịch sử và full clone. Xóa các bản screenshot trùng còn được canonical dùng cũng không làm giảm blob store vì Git vốn đã deduplicate. Không rewrite history theo ràng buộc; không hứa giảm ngay136MiB .git, không chạy gc/clean/reset.

## Tham chiếu, script và dữ liệu cần giữ

Quét tĩnh541 file văn bản, ghi nhận163 file QA có tham chiếu literal; kết hợp đọc thủ công các đường dẫn template/dynamic trong scripts. Phép quét literal không phải chứng minh không có tham chiếu động; danh sách dọn luôn cần review trước khi chạy.

| Nguồn | Phụ thuộc hiện có | Quyết định |
|---|---|---|
| app/, routes/, resources/, config/ | Không tìm thấy tham chiếu docs/figma/qa; production assets nằm public, không phải QA screenshots | Không sửa hoặc xóa public assets |
| phpunit.xml | Auto-discover tests/Unit và tests/Feature, không tự discover mọi PHP trong qa | Giữ test helpers/diagnostics trong qa để gọi tường minh |
| MonthlyDashboardTest | Sinh admin.html/home.html/leaderboard.html và links.json vào .laravel-tools/monthly-dashboard/qa | 4 output sinh lại được, không xóa test; nếu cần chạy lại phải DB testing cách ly |
| awards-sticky-footer-visual.mjs | Đọc before/evidence.json + ảnh before regression | Giữ toàn bộ before ở kế hoạch bảo thủ |
| lookup-sticky-footer-visual.mjs | Đọc before/evidence.json và ảnh baseline before | Giữ, không ignore/xóa trọn screenshots |
| awards-regression-visual.mjs | Đọc competition-awards/before/{home,leaderboard,lookup}-*.png | Giữ |
| leaderboard-visual.mjs | Đọc competition-leaderboard/before/home-*.png | Giữ |
| hero-heading-check.mjs | Đọc hero-heading/before/evidence.json | Giữ JSON; vẫn giữ before PNG làm bằng chứng |
| leaderboard-final-visual.mjs | Đọc final-qa/matrix.json do LeaderboardFinalMatrixTest sinh | Không ignore/delete mọi JSON dù ở final-qa |
| Các script visual khác | Đọc fixture .laravel-tools, public/build manifest và Playwright trong .laravel-tools/visual-qa/node_modules | Không dọn dependencies/fixture tools ngoài phạm vi; không đổi import khiến Codex mất khả năng test |
| Tracking Markdown và gallery HTML | Liên kết ảnh Figma/before/after và bằng chứng đã nghiệm thu | Giữ các file được link trực tiếp trong phương án; không xóa lịch sử hoặc để link gãy |
| package/composer/Vite | Không có script cấu hình gọi qa trực tiếp trong các file này | QA vẫn được gọi thủ công/Codex theo tracking; không có npm QA script không có nghĩa được xóa QA |
| CI/CD | Không có .github/workflows, .gitlab-ci.yml, Jenkinsfile, azure-pipelines.yml hoặc .circleci trong checkout | Chưa xác minh job cấu hình ngoài repository; .gitattributes export-ignore .github không phải bằng chứng có pipeline |

**Giữ toàn bộ53 file tại QA root**, đặc biệt:

- Guards: competition-db-check.php, competition-test-bootstrap.php, lookup-progress-db-check.php, release-preflight.php.
- Fixture/router: *-state-fixtures.php, *-sticky-footer-fixtures.php, router.php, lookup-visual-router.php.
- Tests/diagnostics: LeaderboardFinalDiagnosticTest.php, LeaderboardFinalMatrixTest.php; mọi script *-check.*, *-visual.mjs, *-interactions*.mjs, competition-font-*.mjs và script baseline.
- Design evidence: *design-evidence.json, *figma-evidence.json, figma-reference-2c-4c.json.
- Lịch sử/report: browser-manual-2c-4c.md, browser/dependency evidence, release-manifest-2c-4c.json, release-artifact-2c-4c.sha256 và release-manifest.py. Dù tự sinh, các report rất nhỏ và có ý nghĩa audit; không tái chạy release-manifest.py chỉ để dọn vì nó tạo archive mới.
- Trong screenshots giữ mọi JSON/HTML/Markdown, thư mục before, ảnh Figma/reference/phase-3a, ảnh được link trực tiếp từ docs/gallery. README screenshots gốc mô tả riêng phase2C.4C NOT VERIFIED là lịch sử, không sửa nó thành trạng thái chung của mọi ảnh mới.

## File có thể bỏ khỏi local sau khi được duyệt

**Chưa có file nào được xóa.** Không có quyền tự dọn dù đã biết file sinh tự động. Phương án bảo thủ tạo allowlist từng file và không xóa nguyên thư mục tính năng.

| Phương án | Số file | Dung lượng logic giải phóng | Điều kiện |
|---|---:|---:|---|
| A — bỏ bản sao trùng byte trong nhóm output | 343 PNG | **77.947.839 bytes =77,95MB=74,34MiB** | Giữ canonical SHA256 như manifest; review dependencies; duyệt archive/untrack/delete cụ thể |
| B — đưa các PNG output ứng viên ra kho lưu trữ | 741 PNG | **153.505.944 bytes =153,51MB=146,39MiB** | Bao gồm A, không cộng hai số; archive ngoài workspace/local disk cần giải phóng, checksum + index phục hồi, duyệt trước khi bỏ local |
| Phần giữ sau B | 514 file | 85.654.666 bytes =81,69MiB | Giữ metadata/scripts/baselines/references/docs-linked images, gồm4 file ignored nhỏ |
| Tùy chọn bổ sung:4 monthly-dashboard outputs ignored | 4 | 51.001 bytes | Chỉ khi không có phiên QA đang dùng; test có thể sinh lại, hầu như không đáng ưu tiên |

254 nhóm trùng SHA256 có tổng phần dư lý thuyết101.349.311 bytes (96,65MiB), nhưng **không dùng con số này làm mức xóa an toàn** vì nhiều bản sao ở baseline, reference hoặc file được tham chiếu. Phương án A chỉ chọn343 bản sao output, giữ canonical, không bỏ baseline.

Ví dụ: `awards-sticky-footer/after/home-1440-900.png`, `competition-awards/after/home-1440.png`, `competition-leaderboard/after/home-1440.png`, `hero-heading/font-restored/home-1440.png` và `lookup-sticky-footer/after/home-1440.png` cùng SHA256 `11fab41476001004194dab5bf6d5ff77989c573a4465f3e55f0ee11e7cf96b50`; giữ `awards-sticky-footer/before/home-1440-900.png`. Danh sách đầy đủ343 bản sao và741 ứng viên ở manifest, không tự xóa mọi file có tên home.

Ảnh ứng viên B theo nhóm (chỉ các file trong manifest, không phải toàn thư mục):

| Nhóm screenshots | PNG ứng viên | Bytes |
|---|---:|---:|
| lookup-sticky-footer/after | 120 | 32.204.506 |
| competition-leaderboard | 146 | 28.658.744 |
| website-competition | 37 | 28.102.913 |
| competition-awards | 151 | 20.386.231 |
| awards-sticky-footer/after | 93 | 15.700.049 |
| hero-heading | 25 | 15.000.897 |
| result-panel-sidebox | 75 | 6.008.976 |
| phase-3c-progress | 44 | 3.702.509 |
| lookup-sync | 45 | 3.602.920 |
| dealer-call | 5 | 138.199 |

Các output có thể sinh **ảnh mới** bằng script, nhưng không hứa tái tạo chính xác bằng chứng lịch sử sau khi source/data/browser đổi. Vì vậy archive bản gốc/hash/path trước, giữ các JSON báo cáo và baseline. Archive đặt cùng ổ đĩa không giải phóng tương ứng dung lượng ổ; PNG thường nén thêm ít, cần đo thay vì giả định zip giảm mạnh.

## Nội dung .gitignore đề xuất — chưa áp dụng

Phần1 dành cho output tạm tương lai, không chặn scripts, references, baseline hoặc JSON evidence hiện hữu:

```gitignore
# Disposable QA output; keep scripts/design references/baselines in Git
/docs/figma/qa/artifacts/
/docs/figma/qa/test-results/
/docs/figma/qa/playwright-report/
/docs/figma/qa/blob-report/
/docs/figma/qa/**/*.log
/docs/figma/qa/**/*.webm
/docs/figma/qa/**/*.mp4
/docs/figma/qa/**/trace*.zip
```

Phần2 có thể thêm khi duyệt allowlist bỏ tracking PNG output hiện tại:

```gitignore
# Generated screenshots; untrack only files approved in the audit manifest
/docs/figma/qa/screenshots/**/after/*.png
/docs/figma/qa/screenshots/**/interactions/*.png
/docs/figma/qa/screenshots/**/final-qa/*.png
/docs/figma/qa/screenshots/hero-heading/font-restored/*.png
```

File bằng chứng đã tracked và được quyết định giữ sẽ tiếp tục tracked dù match ignore. Không chạy untrack trọn screenshots: chỉ các path được duyệt. Nếu thêm bằng chứng curated mới vào nhóm bị ignore, thêm ngoại lệ `!` chính xác hoặc đặt vào khu vực evidence được quản lý riêng. Các PNG output trực tiếp dưới website-competition/phase-3c-progress có thể thêm ignore từng path từ allowlist, không wildcard toàn thư mục vì có Figma/reference/gallery.

**Không đề xuất** `/qa/`, `/docs/figma/qa/`, `*.png`, `*.json` hoặc `/docs/figma/qa/screenshots/` blanket. Những rule đó che mất scripts, baseline, input matrix và bằng chứng quan trọng. .gitignore chỉ ngăn add mới, không chặn script sinh file, không tự dọn local và không tự untrack file cũ.

Phương án bền vững ở task tiếp theo: cho QA scripts xuất artifact theo run ID vào artifacts/ hoặc .laravel-tools đã ignored, tách baseline/reference cố định, lưu báo cáo gọn trong Git và archive PNG/trace/video có retention. Hiện script hard-code đường dẫn và so sánh byte; cần sửa QA scripts đồng bộ sau khi được duyệt, không chỉ đổi ignore rồi xóa input. Không chuyển PNG sang WebP/re-encode baseline để tiết kiệm vì có `Buffer.equals`, SHA256 và evidence hash.

## Các bước thực hiện an toàn sau phê duyệt

1. Người dùng chọn A hoặc B và kho archive/retention; duyệt manifest path-level. Phê duyệt audit đọc Git hiện tại không phải phê duyệt xóa.
2. Kiểm tra không có run QA đang ghi file; chạy lại inventory/status/hash để phát hiện file mới hoặc thay đổi từ lúc audit. Không xóa file có hash khác manifest.
3. Archive bằng chứng ngoài phạm vi cần giải phóng, lưu path/commit/source SHA256, xác nhận đọc và restore được; ghi mapping vào bản cập nhật tracking, giữ lịch sử cũ.
4. Giữ nguyên toàn bộ sources/fixtures/guards/input baselines và metadata được liệt kê; review thêm đường dẫn template/dynamic và CI ngoài repo nếu có. Không xóa .laravel-tools/visual-qa dependencies hoặc public assets.
5. Áp dụng ignore đã duyệt, bỏ tracking **chỉ allowlist** bằng thao tác index có kiểm soát, giữ local khi thực hiện bước này; review staged diff bảo đảm không script/baseline bị loại. Đây là hành động tương lai cần người dùng duyệt, chưa chạy trong task này.
6. Chỉ sau xác nhận archive/index mới bỏ local từng path allowlist. Trên Windows phải resolve absolute path, kiểm tra nằm trong QA root đã duyệt, không junction/symlink; dùng cùng một shell end-to-end, không chuyển danh sách sang cmd, không recursive delete theo wildcard.
7. Kiểm tra inputs và Markdown/gallery links, chạy QA phù hợp khi có môi trường; giữ bằng chứng/hash của bản trước, không ghi đè để giả tạo baseline lịch sử. Nếu cần test ghi DB để tái tạo matrix/fixtures phải preflight testing cách ly theo AGENTS.md.
8. Đo lại local size, git status/staged diff và object store. Commit mới giúp HEAD nhẹ và chặn tăng trưởng tương lai; full-history Git vẫn giữ blob cũ. Không git clean/reset/rewrite-history, không thay đổi ứng dụng. Nếu sau này cần giảm full-history size, đó là quyết định riêng ngoài phạm vi và ràng buộc hiện tại.

## Kết quả kiểm thử
PASS kiểm kê filesystem/size/type, SHA256 duplicate scan, Git index/all-ref blob inspection, đối chiếu tham chiếu tĩnh và dependency đọc thủ công. Quét541 file văn bản; xác nhận các tổng size/file và mỗi duplicate candidate có canonical cùng hash. PASS kiểm tra cấu trúc tracking/manifest và whitespace sau hoàn thiện.

Không chạy ứng dụng/test/migration/seed hoặc ghi database. Không có screenshot/video mới được sinh. Công cụ audit chỉ tạo file báo cáo nhỏ, không sửa index hoặc objects. Git read trong sandbox ban đầu báo dubious ownership; chạy lại công cụ đọc với approval và danh tính phù hợp, không sửa safe.directory hoặc cấu hình bảo mật. Chỉ2 file tài liệu dưới docs là thay đổi được đề xuất commit của task này; công cụ tạm và kết quả kiểm kê trung gian nằm trong .laravel-tools đã ignored.

Lệnh kiểm chứng read-only chính: `git status --short`, `git ls-files`, `git rev-list --objects --all`, `git cat-file --batch-check`, `git for-each-ref`, `git count-objects -v`, `git rev-parse --is-shallow-repository`; rg cho app/test/config/QA sources, filesystem/stat và SHA256 bằng Node. Không đổi ref, không fetch remote.

## Những lỗi còn tồn tại
Chưa thực hiện tối ưu, local vẫn giữ nguyên artifact. Các số153,51MB/77,95MB là mức giảm logic có điều kiện, không phải dung lượng đã giải phóng và không cộng dồn. Tham chiếu động/CI ngoài repo, số byte filesystem allocated và objects unreachable/reflog chưa được kiểm chứng. Không hứa full clone sẽ nhỏ lại nếu không rewrite history.

## Task hoặc bước tiếp theo
Chờ người dùng duyệt phương án A (343 duplicate PNG) hoặc B (741 PNG archive, bao gồm A), cùng retention và các thay đổi .gitignore/index tương ứng. Không tự thực hiện bước dọn nào trong task audit này.
