# Đồng bộ Vinh danh Website Thi đua Desktop/Mobile

## Mục tiêu
Thay UI public /awards và /awards/{award} theo Website Thi đua, tái sử dụng shell, giữ nguyên publication gate, snapshots, weekly/monthly history, quyền và các trang đã hoàn thiện.

## Trạng thái
DONE — 2026-11-04. Tạo tracking trước khi sửa runtime; hoàn tất triển khai, kiểm thử nghiệp vụ, responsive và đối chiếu trực quan trong phạm vi các frame hiện có. Không tuyên bố pixel-perfect; giới hạn thiết kế được ghi riêng bên dưới.

## Các frame Figma đã đọc
- File yKmWQI6BYJ6dponEnidlsq, session44359547-2d32-42d7-a441-704a9fe66d5a. Metadata đúng page207:333 Desktop và148:252 Mobile.
- Xác minh B03 Desktop215:504 (1440×879), Mobile151:441 (390×844), đọc high-fidelity design context/screenshot cả hai và metadata descendants.
- Desktop heading215:529, split215:535, periods215:536, panel215:561, winners215:567, footer215:593. Mobile intro151:453, periodchips151:458, panel151:466, winners151:472/481/490, history151:499/501.
- Không tìm thấy frame public empty, detail riêng, filter tuần/tháng hoặc pagination của Awards trong hai page. Không lấy Admin/Khách hàng làm thiết kế. Mobile history vượt844px, cần ghi giới hạn raster.

## Hiện trạng trước khi chỉnh sửa
- Đọc AGENTS.md; tracking home, leaderboard-sync/final-QA, regression-contract; context-recovery, MONTHLY_DASHBOARD_REPORT, PUBLIC_PRIVACY_REPORT và các references awards trong integration/performance. Source là chuẩn khi tài liệu lịch sử khác hiện tại.
- Public routes awards.index GET/awards, awards.show GET/awards/{numeric}; AwardHistoryController index lọc period_type weekly/monthly/all, paginate12 theo period_start DESC/id DESC; show dùng PublicAwardResults::find.
- PublicAwardResults chỉ published_at!=null và có Dealer winners, projection không title/publisher/Sales; withDealers eager-load snapshot Dealer orderedrank. Home dùng latest theo published_at/id. Không lấy leaderboard live để vẽ snapshot.
- Award/AwardWinner đã published bất biến; AwardPolicy chỉ activeAdmin được draft/preview/publish authorization. Source chỉ có CreateAwardDraft + preview; chưa có action/endpoint publish. Không thêm publication hoặc thay state vào DB.
- UI cũ x-layout, cards kỳ và detail genericaward-results (shared Admin), thiếu selector/mainpanel B03. Index chưa eager winners vì UI cũ không cần; cần thêm eager projection public cho leadingname/selectedpanel, tránh N+1.
- Working tree có thay đổi từ regression/footer và QA trước; không ghi đè hoặc nhận là phần Awards. File tracking đề xuất chưa tồn tại.

## Bảng đối chiếu Desktop/Mobile và nghiệp vụ
| Thành phần | Figma Desktop | Figma Mobile | Laravel/data và quyết định |
|---|---|---|---|
| Shell | Header72/container1200/footer | Header60/container358 | Tái dùng competition layout/brand/navigation/footer, active Awards |
| Intro | Eyebrow13/18, heading40/46 | Eyebrow12/16, heading24/30 | Giữ tiêu đề; copy theo kỳ để không loại bỏ weekly history |
| Periods | Sidebar300/padding12/rows66 | Chips scroll + historyrows dưới | Public snapshot names từ bounded eager winners, click route show thật |
| Panel | 876width/padding40/radius32, heading Montserrat44/50 | padding24/20/radius24, heading28/34 | Selected kỳ công khai, publishdate/timezone thật |
| Winners | 3cards ngang, gap16/padding24 | 3rows, badges40/icons18 | Dùng rank/score/name/code snapshot, không cá nhân hoặc ảnh giả |
| Filter/pages | Không có variant | Không có variant | Giữ weekly/monthly/all và12/page đang hoạt động; UI bổ sung tối thiểu có chủ đích |
| Empty/detail | Không có frame riêng | Không có frame riêng | Emptycopy hiện hữu với tokens B03; detail tái dùng panel/history, không giả dữ liệu |

## Các file source code liên quan
- Read-only nghiệp vụ: Award/AwardWinner/AwardPolicy, PublicAwardResults/AwardResults, CreateAwardDraft, Admin/Dealer controllers, routes, LandingPageController, enums.
- Trong phạm vi: public AwardHistoryController chỉ data composition/eagerloading qua service hiện hữu; awards/index/show và components/competition/awards*, competition.css, navigation active route.
- Tests AwardWorkflowTest/PublicPrivacyTest/LandingPageTest và regression; sẽ thêm test AwardsUI riêng, không sửa test cũ để PASS.

## Những thay đổi đã thực hiện
Ghi chép ban đầu: đã audit nguồn và tạo tracking, chưa sửa runtime; chuẩn bị baseline và assets.

### Cập nhật triển khai — 2026-11-04
- `app/Http/Controllers/AwardHistoryController.php`: tách phần chuẩn bị history dùng chung index/detail; eager-load winners qua chính `PublicAwardResults::withDealers` để sidebar và panel dùng public snapshot. Giữ validation, filter, thứ tự period_start/id DESC và paginate12. Detail vẫn gọi `find()` trước khi dựng view; không bypass publication gate.
- `resources/views/awards/index.blade.php`, `show.blade.php`: chuyển từ x-layout cũ sang component Website Thi đua. Detail giữ thời gian đầy đủ, khoảng ngày và lời giải thích snapshot bất biến.
- Thêm `resources/views/components/competition/{awards-page,award-history,award-panel,award-period}.blade.php`: intro, sidebar Desktop, chips Mobile, panel kỳ, Top 3 đúng thứ tự rank, lịch sử, empty state, filter tuần/tháng/tất cả và pagination thật. Tên, mã, điểm, ngày đều lấy từ dữ liệu. Weekly label dùng đầy đủ khoảng ngày để không nhầm các tuần trong cùng tháng.
- `resources/views/components/competition/navigation.blade.php`: chỉ thêm active state cho awards.*. Header, brand, footer, mobile menu/JS và generic award-results dùng chung Admin được tái sử dụng, không viết lại.
- `resources/css/competition.css`: chỉ nối các rule `.competition-site .wc-aw-*`, không sửa rule home/leaderboard/font-face. Tablet có bố cục riêng; Mobile xếp panel trước history, chips cuộn ngang, tên dài tự wrap, không crop.
- Thêm 8 asset SVG gốc từ design context vào `public/competition/awards/`: `period-active-16.svg`, `period-16.svg`, `period-18.svg`, `trophy-22.svg`, `trophy-18.svg`, `chevron-18.svg`, `glow-desktop.svg`, `glow-mobile.svg`. Giữ root size 16/18/22, glow 640/440; không tái vẽ hoặc dùng screenshot làm background.
- Thêm `tests/Feature/CompetitionAwardsTest.php` (4 tests); không sửa tests cũ trong task Awards. Các diff test Dealer/Leaderboard và CSS lookup có sẵn từ task trước được giữ nguyên.
- Thêm QA riêng: `qa/awards-state-fixtures.php`, `qa/awards-visual.mjs`, `qa/awards-regression-visual.mjs`, `qa/awards-interactions-regression.mjs`, ảnh/JSON trong `qa/screenshots/competition-awards/`. Không có route QA trong ứng dụng.
- Build mới bằng Vite. Không sửa routes, Services/Actions, Models/Policies, Admin/Dealer views, config nghiệp vụ, schema, công thức điểm hoặc trạng thái công bố.

## Quyết định kỹ thuật quan trọng
- Không sửa công thức, thứ hạng, period bounds, filter query/gates/snapshot/model/policy/schema. Controller chỉ bổ sung data public đã có để panel/periodnav dùng cùng màn hình, giữ filter/paginator và detail ID.
- Giữ bộ lọc loại kỳ và pagination vì source có thật, dù frame không vẽ chúng. Không hard-code months, winnernames, points, prizes, publisher hoặc demo. Không có dữ liệu giải thưởng/ảnh winner thì không tự thêm.
- Không sửa generic award-results dùng chung Admin; component Awards public chỉ dùng projection Dealer. Missingname/code/ID hiển thị thiếu thông tin hoặc text, không đoán identity/href.
- Home widget giữ PublicAwardResults::latest; kiểm tra linkdetail/snapshots nhất quán. History vẫn sort period_start như hiện hành, không đổi sort thành publicationtime.
- Testwrite chỉ toyota_testing sau preflight, bootstrap chặn migrate, transactionrollback. UIfixture DBdisabled, không QA/mockroute production.

## Kết quả kiểm thử
Ghi chép ban đầu: chưa chạy mới. Các kết quả regression168tests và stickyfooter112tests là lịch sử, không phải kết quả task Awards.

### Môi trường và an toàn dữ liệu
- `php docs/figma/qa/competition-db-check.php`: PASS mới trước tests, actual MySQL loopback `toyota_testing`, 15 tables, không config cache; đọc guard `tests/Support/TestDatabase.php` và bootstrap.
- `competition-test-bootstrap.php` đặt RefreshDatabaseState::$migrated=true: dùng schema testing có sẵn, transaction rollback; không migrate/seed/reset database thật hoặc database test. Test mới có guard assertIsolated trước RefreshDatabase.
- Fixture ảnh là Award/AwardWinner/Dealer **chưa lưu**, DB connection bị vô hiệu hóa. Render Blade ra file private `.laravel-tools/`, Playwright chỉ intercept trong browser. Dữ liệu minh họa Figma chỉ tồn tại trong fixture này, không vào runtime production.
- HTTP browser regression/filter là GET dữ liệu local thật, không ghi dữ liệu. Fixture ảnh không được trình bày như luồng ghi/công bố thật.

### Kết quả mới — 2026-11-04
| Kiểm tra | Kết quả |
|---|---|
| Baseline rồi sau triển khai: AwardWorkflowTest + PublicPrivacyTest | PASS 19 tests / 1.736 assertions ở mỗi lần chạy; không cộng các lần chạy thành coverage |
| CompetitionAwardsTest riêng | PASS 4 tests / 97 assertions; kiểm tra projection public, Sales/internal/live-name không lộ, guest/Admin/Dealer/Sales đều không thấy draft/Sales-only, detail404, snapshot trước/sau bằng nhau |
| Query budget mới | PASS index 3 queries cho cả 3 và 14 kỳ, detail 5 queries gồm lịch sử; không tăng theo số kỳ, không query customer_submissions/tính lại leaderboard |
| Nhiều kỳ/filter/pagination | PASS 14 kỳ monthly, weekly riêng; 12+2 không trùng/mất kỳ, page/filter và selected ID đúng; không trộn snapshots |
| Thiếu dữ liệu | PASS defensive Blade DTO: thiếu tên/ID không dựng identity/link giả; schema thực tế cấm blank snapshot và thiếu subject nên không bypass constraint để lưu fixture lỗi |
| Full regression 20 classes | **PASS 197 tests / 4.004 assertions, 0 failures/errors/skipped**, PHP8.5.11/PHPUnit12.5.40, 73,831 giây wall-clock |
| `npm run build` | PASS 64 modules; competition CSS mới, dealer-registration CSS và JS dùng chung giữ nguyên hash |
| Pint cho controller/test/fixture PHP mới | PASS sau format các file mới; php-lint và node --check QA scripts PASS |
| `git diff --check` | PASS; Git chỉ cảnh báo CRLF→LF, không whitespace error |

Full regression gồm đúng 16 classes của báo cáo Leaderboard, thêm AwardWorkflowTest, CompetitionAwardsTest, RegistrationLookupResultPanelTest và DealerRegistrationSuccessPresentationTest. Chạy process isolation để tránh static trusted-host state từ CampaignRulesTest (có đổi APP_ENV production) ảnh hưởng test kế tiếp; không thay test/auth logic để PASS. Không coi kết quả lịch sử là kết quả lần này.

```powershell
php docs/figma/qa/competition-db-check.php
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/competition/awards-regression.xml tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerHistoryTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/CampaignRulesTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/CompetitionAwardsTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php
php vendor/bin/pint app/Http/Controllers/AwardHistoryController.php tests/Feature/CompetitionAwardsTest.php docs/figma/qa/awards-state-fixtures.php --test
npm run build
php docs/figma/qa/awards-state-fixtures.php
node docs/figma/qa/awards-visual.mjs
node docs/figma/qa/awards-regression-visual.mjs after
php docs/figma/qa/lookup-sticky-footer-fixtures.php
node docs/figma/qa/awards-interactions-regression.mjs
```

### Các lỗi phát hiện trong quá trình QA và xử lý
- Mobile panel ban đầu Y246 thay vì Figma Y262: copy ngắn chỉ một dòng và padding chips thêm4px. Đổi copy bao quát kỳ tuần/tháng, bỏ padding chips không có trong thiết kế và scrollbar chiếm chỗ; sau build/render lại khớp Y262. Không hard-code chiều cao intro.
- Fixture test ban đầu thử lưu blank snapshot bị MySQL3819 winners_snapshot_check từ chối. Đây là constraint đúng; chuyển riêng kiểm tra defensive missing-data sang DTO chưa lưu, giữ nguyên schema/guards.
- Probe font lần đầu chỉ `document.fonts.check` cho weight600 chưa được Awards dùng nên báo chưa loaded. Sửa phép đo bằng FontFaceSet.load mẫu tiếng Việt cho cả600/700 rồi check; không sửa font/runtime để che kết quả. Chi tiết kết quả font mới nằm trong visual.json.
- Tải Figma assets/reference trong sandbox có curl7 (Failed to connect, Bad access). Chạy lại chính curl với yêu cầu cấp quyền rõ ràng rồi tải thành công; không tắt sandbox hoặc đổi chính sách. Git diff trong sandbox không đọc được repo; đọc diff với approval riêng, không sửa safe.directory/global config.

## Screenshot evidence
Đã xem screenshot Figma trong context; lưu evidence riêng, không đè ảnh của các task trước.

### Evidence mới và kết quả đối chiếu
- Reference Figma gốc: [Desktop1440×879](qa/screenshots/competition-awards/reference/desktop.png), [Mobile390×844](qa/screenshots/competition-awards/reference/mobile.png).
- Before UI cũ: `qa/screenshots/competition-awards/before/awards-{width}.png`. Baseline 24 live page/viewports chụp trước chỉnh, có regression.json.
- Sau triển khai: [Desktop fixture](qa/screenshots/competition-awards/after/design-1440.png), [Mobile fixture](qa/screenshots/competition-awards/after/design-390.png), [empty320](qa/screenshots/competition-awards/after/empty-320.png), [weekly390](qa/screenshots/competition-awards/after/weekly-390.png), [live Awards1440](qa/screenshots/competition-awards/after/awards-1440.png).
- `after/visual.json`: **66 tổ hợp PASS**, 11 trạng thái × 6 viewport320/390/768/1024/1440/1920, height900, fullPage. Trạng thái design, empty, long, many, page-two, weekly, missing, one, detail, selected-2, selected-3. Không overflow/clipping text, asset lỗi, placeholder link hoặc Blade directive chưa compile; menu Mobile/Escape và click đổi kỳ PASS.
- Đo khớp hình học frame: Desktop1440 panel x444/y252/w876/h426, cards222px; Mobile390 panel x16/y262/w358/h446, thứ tự rank1/2/3 theo frame. Title Inter và period Montserrat700, tiếng Việt đọc được, kiểm tra local Montserrat600/700 bằng FontFaceSet.
- `after/regression.json`: **24 live page/viewports PASS HTTP200**, home/leaderboard/awards/lookup. **18 screenshot home/leaderboard/lookup byte-identical** so với baseline mới ở đủ6viewport; navigation/footer cũ không bị CSS Awards thay đổi.
- `interactions/evidence.json`: pagination click trang2 (ID13, 2 kỳ, linkprev hợp lệ) bằng fixture và3filter GET Laravel thật PASS ở6width. Thêm **66 screenshot lookup mới** (default, validation, D07/D08/D10/D11/D12/D13/D14/D15, long) ×6viewport: không overflow/lỗi JS/assets, footer sau nội dung/không trắng thừa/không che CTA. D08 dưới768px vẫn ẩn footer theo thiết kế cũ; không sửa lại hành vi này.
- Đã mở và đối chiếu trực quan ảnh design Desktop/Mobile với reference, cùng empty320/weekly390/one1440. Các tổ hợp còn lại được chụp và kiểm tra geometry/overflow/assets bằng Playwright; không tuyên bố đã mở thủ công tất cả ảnh.

## Những lỗi còn tồn tại
Ghi chép ban đầu: chưa triển khai/QA. Sau triển khai không còn lỗi runtime/test liên quan được phát hiện trong phạm vi đã kiểm tra.

### Giới hạn và khác biệt có chủ đích
- **Chưa kiểm chứng bằng raster đầy đủ:** Mobile frame clip ở844px. Metadata151:499/501 xác nhận history section đến969px, list153px gồm Tháng9 và Tháng8. Phần dưới fold triển khai theo metadata/design context, không khẳng định pixel-perfect dưới fold.
- Không có frame public empty/detail/filter/pagination riêng trong hai page đã đọc. Empty/detail dùng tokens/component B03 và nghiệp vụ hiện hữu; không coi đây là variant đã được Figma phê duyệt riêng.
- Giữ filter weekly/monthly/all và paginate12 từ source; đặt dưới history để không dịch panel. Intro dùng “mỗi kỳ thi đua” thay “mỗi tháng” vì weekly là chức năng thật. Đây là khác biệt có chủ đích, không tự bỏ nghiệp vụ theo dữ liệu minh họa.
- Figma ghi “Giờ Việt Nam”, config hiện tại UTC. UI dùng timezone thực từ config (UTC), time ISO đúng instant, không tự gắn nhãn VN sai hoặc đổi config nghiệp vụ. Nếu đổi timezone ứng dụng cần task/quyết định riêng.
- Reuse header/footer hiện có; không thêm hotline1900xxxx minh họa vào production và không sửa kích thước menu chung chỉ để giống ảnh B03. Footer Mobile dùng component hiện hữu dù frame B03 không vẽ dưới fold.
- Không có backend prize/photo-winner/public-pending/publish endpoint để dựng thêm. Chỉ rank/score/snapshot Dealer đã công bố được public; draft kết quả404 dù người xem đăng nhập Admin. Không thêm preview công khai.
- Không chạy toàn bộ suite của toàn ứng dụng, load/concurrency hoặc thao tác ghi qua browser production. 20 classes regression và browser GET/fixture được phân biệt rõ.

## Task hoặc bước tiếp theo
Không còn bước bắt buộc trong phạm vi triển khai này. Khi có thêm Figma variant empty/detail hoặc bản Mobile đầy đủ, đối chiếu bổ sung trong task riêng. Không cần migrate, seed, thay trạng thái công bố hoặc thay đổi business logic để sử dụng giao diện mới.
