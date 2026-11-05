# Đồng bộ public Đại lý Website Thi đua Desktop/Mobile

## Mục tiêu
Triển khai B14 danh sách và B15 hồ sơ công khai theo đúng hai page Website Thi đua. Giữ nguyên QR/landing đăng ký D01, Dashboard/Admin, attribution, scoring, snapshots, privacy và DealerCallPhone.

## Trạng thái
IN PROGRESS — tạo trước sửa source, 2026-11-05.

Cập nhật cuối 2026-11-05: **DONE phạm vi triển khai public Dealer và kiểm thử**, có những khác biệt chủ đích để giữ contract Laravel ghi rõ bên dưới; không tuyên bố pixel-perfect toàn trang hoặc nghiệm thu mọi nội dung dưới fold Mobile.

## Frame Figma đã đọc
File yKmWQI6BYJ6dponEnidlsq, session11c1da80-6528-45a7-98b0-45d739aed7a2. Metadata toàn page Desktop207:333/Mobile148:252; xác minh B14 Desktop216:382(1440×942), Mobile157:489(390×844); B15 Desktop216:562(1440×1205), Mobile157:624(390×844). Đã đọc high-fidelity design context và screenshot cả4 root, đúng Website không phải Khách hàng/Admin. Mobile B15 CTA dẫn D01 trong prototype: đây là hai màn khác nhau.

## Hiện trạng trước khi chỉnh sửa
Git sạch đầu task. Đã đọc AGENTS/context-recovery và tracking home/leaderboard/awards/dealer-call/landing-fidelity/phase1/regression-contract cùng source hiện tại. Directory /dealers dùng public.layout cũ, search q,20/page, score/rank toàn chương trình, score desc/name/id. Slug /{slug} hiện là landing QR D01 và chứa form/draft hai bước, không được thay bằng B15. /dealers/{id} legacy301 sang slug; DealerProfile::url/QR phải giữ nguyên. Dashboard/Admin routes là private, ngoài phạm vi. Directory cũ đang in phone raw không qua approval; UI mới không có cột phone, cần bỏ exposure này.

## Bảng đối chiếu Desktop/Mobile
| Section | Desktop | Mobile | Mapping/backend |
|---|---|---|---|
| B14 | heading40/46, filters52, table1200w/24radius | heading26/32, search full/chips/list card358w | Dùng collection/paginator hiện có; không đổi score/order/tie-break |
| B15 | banner280h, title40, stats2, chart, contact400w | header back, banner200h, name card, stats dọc, chart, CTA trước contact | Tạo presentation public riêng, CTA tới landing QR cũ |
| Filter/rank | Province/sort, monthly rank illustration | chips/monthly badge | Contract hiện tại là rank toàn chương trình, phải ghi nhãn đúng; sort mặc định giữ nguyên. Không gán rank all-time thành monthly |
| Phone | contact Dealer | dưới fold | Chỉ DealerCallPhone approved, không dùng Sales/customer |
| Empty/photo fallback | không có empty frame riêng; B15 dùng image placeholder | Mobile clip sau844 | Dùng tokens/fallback có source, không ảnh demo production; giới hạn raster ghi rõ |

## Các file source code liên quan
PublicDealerController, routes/web.php, DealerProfile/LeaderboardService/DealerCallPhone (đọc), dealers/index/show, competition layout/navigation/cards, DealerProfileTest/PublicDealerDirectoryTest/PublicPrivacyTest và QA harness.

## Những thay đổi đã thực hiện
Ban đầu chỉ audit/Figma/tracking. Sẽ cập nhật implementation/evidence bên dưới; chưa sửa database hay source Dashboard/Admin.

### Triển khai cuối
- `/dealers` dùng competition layout/header/navigation/footer; B14 table Desktop và list Mobile riêng, search GET, filter province chính xác, empty state, pagination20/page giữ q/province. Tên/province dài wrap, không overflow. Province lấy DISTINCT từ Dealer active thật, không từ danh sách minh họa.
- Thêm **GET `/dealers/{dealer}/profile`**, route `dealers.competition`, view `dealers.competition` cho B15. Numeric ID không tồn tại404. Historical inactive Dealer vẫn200 theo contract cũ nhưng ẩn đăng ký/call, không có mặt directory. Không đổi `/dealers/{id}`301 hoặc `/{slug}` landing D01/QR, không đổi DealerProfile::url/QR.
- B15 có banner ảnh qua endpoint private media cũ (chỉ khi file tồn tại), placeholder Figma khi thiếu file, tên/mã/tỉnh, stats score/rank toàn kỳ, chart3 tháng, CTA đăng ký sang landing cũ, contact và nút Gọi đại lý có approval. Không hard-code tên/tháng/điểm hoặc thêm history/awards không có trong frame.
- Chart chỉ gọi `LeaderboardService::dealerScores(start,end)` và standing dùng `dealers(AllTime)`. Không chạm formula, tie-break, submitted_at boundaries, snapshot Dealer, publication/authorization/uniqueness/demo scope. Không tính từ review time, không đếm pending/rejected, không dựa Dealer hiện tại của Sales sau chuyển chi nhánh.
- Phone directory bị loại khỏi SELECT vì không có UI phone tại B14; profile lấy qua DealerCallPhone rồi bỏ attribute khỏi model view-data nếu không approved. Chỉ approved exact slug+normalized DB phone mới có tel/display, inactive cũng ẩn. Không dùng config làm nguồn thay thế hoặc dùng số Sales. Photo path bị unset trước view-data, URL là endpoint, không xuất đường dẫn storage.
- Competition links ở Home/Leaderboard/Awards đi thẳng B15; dealer-link chỉ đổi URL khi public=true, nhánh account/private giữ nguyên. Shared layout chỉ thêm prop dealerProfile=false mặc định cho back-header Mobile riêng B15; Dealer nav aria-current cho hai route mới. Không thay hình học header/footer của Home/Leaderboard/Awards.
- CSS chỉ `.competition-site .wc-d-*` và body flag `.wc-d-profile-site`; tokens Inter/Montserrat Vietnamese/font files hiện có được tái sử dụng, không đổi @font-face. Province JS chỉ chọn `[data-dealer-province]`, native form requestSubmit và nút search hoạt động cả khi không JS bằng GET submit.
-24 SVG xuất trực tiếp từ high-fidelity design context,16.810bytes tổng; root dimensions giữ nguyên, desktop/mobile assets riêng. Logo/header/menu/footer tái sử dụng component/asset chính thức hiện có. Không dùng screenshot làm background.

### Source/file thay đổi
1. `app/Http/Controllers/PublicDealerController.php`, `routes/web.php`.
2. `resources/views/dealers/index.blade.php`, mới `dealers/competition.blade.php`.
3. Mới `resources/views/components/competition/dealer-{directory,profile,chart,icon,pagination}.blade.php`.
4. `resources/css/competition.css`, `resources/js/competition.js`.
5. `resources/views/components/competition/{layout,navigation,leaderboard-card}.blade.php`, `resources/views/components/dealer-link.blade.php`, `resources/views/{competition/home,leaderboard/index}.blade.php` (chỉ đổi link public, không thiết kế lại Home/Leaderboard).
6. `public/competition/dealer/*.svg` (24 assets); Vite output/manifest trong public/build được build, đang ignored như convention.
7. Mới `tests/Feature/CompetitionDealerTest.php`; cập nhật `PublicDealerDirectoryTest`, `DealerLandingRegistrationTest`, `CompetitionLeaderboardTest`, `PublicPrivacyTest` với căn cứ bên dưới.
8. Mới `docs/figma/qa/{dealer-state-fixtures.php,dealer-visual.mjs,dealer-interactions-regression.mjs}`; tracking này. Temporary HTML/log/XML/reference/screenshots ở ignored paths, không ghi đè baseline cũ.

Đã đọc thêm `DEALER_PROFILE_DASHBOARD_REPORT.md`, `DEALER_PROFILE_UPDATE.md`, `DEALER_LANDING_PAGE_REPORT.md`, `PUBLIC_PRIVACY_REPORT.md`, `DASHBOARD_NAVIGATION_REPORT.md`. Các mô tả cũ coi /{slug} là profile có score/photo, role chỉAdmin/Sales,88 Dealer hoặc mọi Dealer phone được public là lịch sử đã bị các phase landing-fidelity/dealer-call/schema hiện hành thay thế. Source/kiểm thử mới là căn cứ; không sửa/xóa báo cáo cũ để che mâu thuẫn. B15 mới tách khỏi D01 đúng link CTA trong Figma.

## Quyết định kỹ thuật quan trọng
QR canonical/legacy301 và landing /{slug} phải giữ. Thêm public presentation route cho B15 và điều hướng các link Website Thi đua tới đó; không thay DealerProfile::url hoặc endpoint registration. Source Laravel chuẩn nghiệp vụ: chỉ đọc approved projections qua LeaderboardService, không dữ liệu mẫu. Empty/long/zero ngoài variant được kiểm tra responsive nhưng không tự gọi là pixel-perfect Figma.

- B14 Figma dùng minh họa “Hạng tháng này”; contract directory trước task lấy AllTime. Giữ nguyên rank/order hiện có, hiển thị **Hạng toàn kỳ**/aria-label toàn chương trình và badge chỉ#rank. Không gắn nhãn tháng vào rank toàn kỳ. Muốn đổi kỳ directory cần task/contract riêng, không sửa cách tính trong UI task.
- Sort hiện có chỉ score DESC/name/id; control trong thiết kế được thể hiện bằng **nhãn thứ tự cố định Nhiều thành viên**, không làm dropdown giả hoặc invent sort options. Province mới là filter đọc; không thay query eligibility/ordering/pagination gốc. Không gọi API/Admin mới hoặc expose employee counts.
- Mobile chip tỉnh có44px hit area thay vì38px raster prototype; danh sách dịch xuống6px chủ đích. Pagination và shared Mobile footer cần cho chức năng nhưng không có trong prototype B14/B15 Mobile. Call CTA xám được giữ như yêu cầu chức năng/approval dù Figma B15 chỉ có row phone. Không tự công khai số để lấp layout.
- Chart dùng SVG **dữ liệu** có rect/text/title từ aggregate thật, không dùng ảnh chart/reference; không inline arbitrary styles. Zero có height0, không tạo thanh/điểm giả. Trạng thái dài tăng chiều cao tự nhiên, footer sau nội dung, không fixed/absolute footer.

## Kết quả kiểm thử
Chưa chạy mới; phải preflight toyota_testing trước fixture transaction. Không migrate/seed/reset DB nghiệp vụ.

### Kết quả mới 2026-11-05
| Kiểm tra | Kết quả | Bằng chứng |
|---|---|---|
| Isolation | PASS sau xử lý cache đúng phạm vi | Lượt đầu preflight từ chối vì bootstrap/cache/config.php đang cache DB toyota. Không xóa/cache-clear file đó; chỉ truyền APP_CONFIG_CACHE sang đường dẫn nonexistent trong `.laravel-tools` ở process QA/testing. Preflight sau đó PASS actual loopback MySQL toyota_testing,15tables; không sửa .env hoặc config production |
| Focused Dealer | PASS7 tests /80 assertions của CompetitionDealerTest cuối; trước đó7 tests /104 assertions gồm directory ở lượt đầu | Coverage privacy, photos, approved phone, inactive, monthly bounds/transfer snapshot, filter/pagination, rank không reset; không cộng rerun thành coverage |
| Full regression cuối | **PASS265 tests /4.578 assertions**,0 failures/errors/skipped,23classes | `.laravel-tools/dealer-regression.xml` và `.txt`; PHP8.5.11/PHPUnit12.5.40;1m31.287s; không dùng số lịch sử làm kết quả mới |
| N+1 directory | PASS |4 queries:1 ownership lookup + paginator count/data +1 DISTINCT provinces. Budget cố định ở page20/22participants, search variants; giữ score/order/global rank assertions |
| N+1 B15 | PASS |9 queries cố định khi1→21Sales: Dealer projection +4existing ownership lookups +4aggregates; không load Sales relations/customer rows |
| Build/Pint/Git | PASS | npm run build64 modules; Pint explicit7PHP file; node --check2QA scripts; Git diff --check bằng quyền đọc được duyệt do sandbox ownership, không sửa safe.directory/policy |
| Browser states | **PASS72 tổ hợp** |12states×320/390/768/1024/1440/1920; zero overflow/text clipping/broken images/JS errors, static icon dimensions đúng, Montserrat600/700 tiếng Việt load, menu/Escape/footer/CTA visibility |
| Browser interactions | PASS | Native GET search/province autosubmit, query state, B14→B15, CTA href sang D01; responses là Blade DTO fixtures, không giả làm luồng DB thật |
| Live GET regression | **PASS42 tổ hợp** |Home/Leaderboard/Awards/directory/B15/landing/lookup×6viewport, HTTP200/nooverflow/noJSerror/brokenimage; chỉ đọc local DB, không seed/POST qua browser |

Full23classes: CompetitionDealerTest, PublicDealerDirectoryTest, DealerProfileTest, DealerSlugTest, DealerLandingRegistrationTest, DealerAuthorizationTest, DealerDashboardTest, DealerHistoryTest, LeaderboardServiceTest, CompetitionLeaderboardTest, PublicLeaderboardTest, LandingPageTest, CompetitionHomepageTest, MonthlyDashboardTest, PublicPrivacyTest, CompetitionAwardsTest, AwardWorkflowTest, DealerRegistrationDraftTest, DealerRegistrationFinalTest, RegistrationLookupTest, RegistrationLookupStatesTest, RegistrationLookupResultPanelTest, RegistrationBusinessIntegrationTest. Tests transaction rollback sau xác minh cách ly; bootstrap hiện có chặn migrate:fresh. Không migrate/seed/reset DB ứng dụng, không gọi reset/cleanup command, không tạo demo production.

### Lỗi bắt được và review test (giữ lịch sử)
- Bản đầu có Blade inline @php trộn block làm compile lỗi chart; chuyển block PHP đúng cách. Aggregate value('score') trên query đã select nhiều cột trả cột đầu ID; test tháng bắt lỗi và sửa chỉ phần đọc projection sang first()->score, không sửa service/tính điểm.
- Browser bắt CTA Desktop bị rule Mobile display:flex ghi đè nên hiện2CTA/tràn ngang320; tăng specificity cho variant thật, rồi thêm assert luôn chỉ1CTA active. Sửa typography/spacing đúng context: B14Mobile26/32, B15Mobile24/28; SVG display:block loại baseline gap7px Desktop.
- Fixture interaction ban đầu dùng response không giữ selection tỉnh và glob route không match nested profile nên timeout/đi vào live page. Sửa **QA harness**: fixture filtered có state, predicate URL chính xác; production filter đã có backend tests giữ trạng thái. Rerun interactions/live PASS, không sửa runtime để làm fixture test PASS.
- Lượt QA đầu generate HTML trước build có thể trỏ bundle cũ bị Vite thay thế (log server có CSS404). Quy trình cuối là build trước, regenerate Blade fixtures sau;72case cuối kiểm tra response>=400/requestfailed/broken assets đều không lỗi. Đã dừng PHP server tạm do task khởi tạo sau khi hoàn tất QA.
- PublicDealerDirectoryTest cũ tạo nhiều pending/approved cùng một phone, bị uniqueness chặn **trước HTTP**. Đổi sang deterministic unique phone/normalized trong fixture, không bỏ guard. Budget cũ2 cũng không tính lookup ownership hiện hành; budget mới4 có assert chính xác ownership binding và DISTINCT query, không dùng giới hạn rộng. Giữ mọi assertion ordering/score/rank/pagination/search.
- DealerLandingRegistrationTest có2assertion lỗi thời: số PUBLIC_DEALER_PHONE không hợp lệ/chưa approved và form legacy/flash copy đã bị gỡ ở landing-fidelity. Thay bằng phone hợp lệ + trước approval ẩn/sau approval tel đúng; action StepOne thực, giữ QR/name/address ổn định. Consent vẫn bảo vệ qua invalid consent→session errors/không oldInput và các legacy POST tests; thành công kiểm tra **flash message chính xác +row count1+redirect cũ+không lộ PII**, không ép UI thêm thông báo/biểu mẫu cũ.
- PublicPrivacyTest giữ explicit route allowlist, chỉ thêm route B15; không suy allowlist từ router. CompetitionLeaderboardTest link kiểm tra href đầy đủ B15 thay vì chuỗi URL legacy có thể match nhầm prefix. Không sửa controller/service nghiệp vụ để chữa assertions.

### Screenshot evidence và đối chiếu
- Figma references: [B14 Desktop](qa/artifacts/competition-dealer/reference/directory-desktop.png), [B14 Mobile](qa/artifacts/competition-dealer/reference/directory-mobile.png), [B15 Desktop](qa/artifacts/competition-dealer/reference/profile-desktop.png), [B15 Mobile](qa/artifacts/competition-dealer/reference/profile-mobile.png). Asset SHA256/root dimensions: `qa/artifacts/competition-dealer/reference/assets.json`.
- Sau sửa: [B14 Desktop1440](qa/artifacts/latest/screenshots/competition-dealer/after/directory-1440.png), [B14 Mobile390](qa/artifacts/latest/screenshots/competition-dealer/after/directory-390.png), [B15 Desktop1440](qa/artifacts/latest/screenshots/competition-dealer/after/profile-1440.png), [B15 Mobile390](qa/artifacts/latest/screenshots/competition-dealer/after/profile-390.png), [long320](qa/artifacts/latest/screenshots/competition-dealer/after/long-profile-320.png), [empty320](qa/artifacts/latest/screenshots/competition-dealer/after/empty-320.png).
- `after/visual.json` ghi72cases/computed geometry/assets/fonts. `qa/artifacts/latest/screenshots/competition-dealer/regression/evidence.json` ghi native interactions và42liveGET screenshots cùng thư mục. Source fixture `.laravel-tools/competition/dealer-states/*.html` chỉ DTO/DB-disabled, tách biệt browser GET Laravel thật. Không có screenshot trước sửa; audit before dựa source/tracking, không claim pixel diff before/after.
- Đã xem và so trực quan4 ảnh design ở1440/390 với references, cùng profile320 trước fix. B14 Desktop list x120/y298/w1200/h443 đúng metadata; Mobile title x20/y84/w350/h32, list x16/y280/w358/h372 (Figma y274, lệch6 vì hit area). B15 Desktop banner x120/y148/1200×280, stats y552/776×110, chart y686/776×318 đúng metadata; Mobile banner y60/390×200, stats y364/358×148, chart y524/358×246 đúng context. Các khác biệt rank/pagination/call/footer nêu riêng, không gọi pixel-perfect.

Lệnh tái kiểm tra (PowerShell từ root; APP_CONFIG_CACHE chỉ ảnh hưởng process này):
```powershell
$env:APP_CONFIG_CACHE = (Join-Path (Get-Location) '.laravel-tools/dealer-no-cache.php')
php docs/figma/qa/competition-db-check.php
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/dealer-regression.xml tests/Feature/CompetitionDealerTest.php tests/Feature/PublicDealerDirectoryTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerSlugTest.php tests/Feature/DealerLandingRegistrationTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerHistoryTest.php tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/CompetitionAwardsTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/RegistrationBusinessIntegrationTest.php
npm run build
php docs/figma/qa/dealer-state-fixtures.php
# Local PHP server127.0.0.1:8771 phục vụ public/assets, không route QA vào application.
node docs/figma/qa/dealer-visual.mjs
node docs/figma/qa/dealer-interactions-regression.mjs
```

## Những lỗi còn tồn tại
Chưa triển khai/visualQA; Mobile root clip844px. Chưa có frame empty riêng. Không có history vinh danh trong B15 nên không thêm section giả.

Cập nhật cuối: không còn lỗi triển khai/test FAIL trong phạm vi đã xác minh. Giới hạn: raster Mobile clip844 nên contact/footer dưới fold dựa context+browser, không có full-reference; empty/long/inactive/zero không có variant Figma riêng. B14 rank vẫn AllTime (copy/ARIA đúng contract), sort là readonly, Mobile filter hit44 thay38, pagination/call/footer là chức năng giữ theo source/yêu cầu. Không kiểm tra cuộc gọi thật trên thiết bị hoặc publish config phone; chưa test browser khác Chrome/load/concurrency/full toàn ứng dụng. Không tuyên bố release production đã triển khai.

## Task hoặc bước tiếp theo
Assets, presentation/Blade/CSS, tests và QA6 viewports; cập nhật kết quả thật. Không deploy hoặc sửa phone approval production.

Cập nhật cuối: bàn giao source/screenshots/tracking; không còn bước bắt buộc trong phạm vi implementation này. Nếu muốn đổi directory sang thứ hạng tháng hoặc bổ sung sort options, cần chốt contract riêng thay vì đổi rank/ordering hiện có. Khi có Mobile full-height/empty variants mới, đối chiếu bổ sung. Không commit/push/deploy, không sửa .env, config cache hiện hữu, database hoặc phone approval.
