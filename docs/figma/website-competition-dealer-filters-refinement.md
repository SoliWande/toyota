# Hoàn thiện bộ lọc và sắp xếp B14

## Mục tiêu
Nhãn #Hạng nhất quán, audit province thực tế, filter/search/sort/direction tại database trước paginate; giữ AllTime rank/formula/attribution/privacy và mặc định cũ. Chỉ bật members khi có contract dữ liệu được xác nhận.

## Trạng thái
IN PROGRESS — 2026-11-05, tạo trước khi sửa source.

Cập nhật cuối 2026-11-05: **DONE phần triển khai và QA**. **BLOCKED riêng dữ liệu tỉnh/thành trên local**: dealers.province hiện NULL ở mọi Dealer; chưa thể có đầy đủ lựa chọn thật khi DB chưa được cập nhật từ nguồn được xác nhận. Không tuyên bố select đã đầy đủ trên local/production. Không còn BLOCKED về định nghĩa members sau xác nhận của người dùng.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, website-competition-dealer-sync.md và controller/Blade/CSS/JS/tests, AdministrativeDivisions/SubmissionVerification. Working tree còn toàn bộ thay đổi B14/B15 của task trước chưa commit; giữ nguyên, không quy thành thay đổi mới. Province hiện lấy DISTINCT dealers.province của active Dealer nhưng không normalize whitespace; sort mới chưa có, nhãn Nhiều thành viên đang thực chất là score COUNT approved. Không suy luận từ nhãn Figma thành định nghĩa nghiệp vụ.

## Các file source code liên quan
PublicDealerController@index, Dealer/LeaderboardService (chỉ đọc), AdministrativeDivisions và snapshot địa giới34 tỉnh, SubmissionVerification, component competition/dealer-directory, competition CSS/JS và tests/QA. B15/score service/approval không thuộc phạm vi sửa.

## Những thay đổi đã thực hiện
Ban đầu chỉ audit/tracking. Sẽ bổ sung kết quả kiểm tra DB read-only, triển khai và bằng chứng mới bên dưới.

### 1. Nguyên nhân select tỉnh/thành trống và nguồn dữ liệu
- Audit read-only trên **local `toyota` tại127.0.0.1**, config cache hiện hữu:2 Dealer,1 active/1 inactive; cả2 đều `province=NULL`. Không có province name hợp lệ để DISTINCT/populate. Đây là nguyên nhân thực tế, không phải tên field sai, thiếu truyền Blade hoặc JS không gửi query.
- Field là **dealers.province**, nullable string thêm bởi migration `2026_10_03_000009_add_dealer_contact_fields.php`; model Dealer có fillable tương ứng. `select name=province` khớp GET province, controller có truyền provinces; active condition đúng scope directory public và không được nới để lộ Dealer inactive.
- Nguồn chuẩn hiện có: `AdministrativeDivisions` đọc `database/data/vietnam_administrative_units.json` (snapshot34 tỉnh/3321 xã, source/retrieved_at trong JSON), dùng cho **địa chỉ khách đăng ký**. Dealer không có province_code/FK tới nguồn này, field province là tên tự do. Không thể dùng34 tên để suy ra tỉnh của Dealer NULL hoặc giả vờ34 nơi đều có Dealer. Không lấy địa chỉ Customer/Sales, không geocode, seed hoặc backfill.
- Provider mới `DealerDirectoryProvinces` vẫn chỉ lấy province của Dealer active thật; bỏ NULL/rỗng/whitespace/giá trị không có chữ, squish Unicode whitespace, dedup không phân biệt hoa thường, sắp xếp tên bằng khóa transliteration để Đ/diacritics không rơi sauZ. Không tự merge tỉnh lịch sử/alias/viết tắt hoặc chuyển mã hành chính thành tên khi chưa có mapping được duyệt.
- Display label có thể khác raw whitespace; lưu tập raw variants tương ứng rồi **WHERE IN với bound parameters** để lọc đủ records (không chỉ đổi label khiến query mất hàng). Query cũ dùng raw name vẫn tương thích khi cùng tên/case/whitespace. Không sửa DB. Unknown province qua URL vẫn trả empty200 và giữ state bằng option “X — không có đại lý”; đây là trạng thái filter hiện tại, không tạo một tỉnh mới trong DB hay inventory.
- Khi options rỗng: vẫn có **Tất cả tỉnh thành**, thông báo “Chưa có dữ liệu tỉnh/thành của đại lý để lựa chọn.”; các sort/search còn hoạt động. Không âm thầm điền danh sách chuẩn hoặc số liệu demo.
- Evidence private: `.laravel-tools/dealer-filters-audit-before.json`/`after.json`; SHA256 mọi cột Dealer trước/sau đều `03a2ec4e7a43ca3fd1ec651483b96f38be673b74a35feb181d5546656205d900`. Không kết nối production; số2/local không phải số87 production.

### 2. Định nghĩa Thành viên được xác nhận
Audit ban đầu thấy ba tập khác nhau: User role Sales; customer_submissions approved; Group member trong submission_verification_states có profile evidence/audit. Source SubmissionVerification cố ý tách moderation khỏi Group progress, nên không được tự suy số thành viên từ Sales hoặc Group flag.

**Người dùng đã xác nhận trong phiên: “Thành viên chỉ tính những hồ sơ approved”.** Vì vậy `sort=members` đã được triển khai, không còn BLOCKED. `members_count` là COUNT approved hợp lệ theo Dealer snapshot, tái sử dụng aggregate `LeaderboardService::dealers(AllTime)` đang có eligibility/QR-vs-Sales/demo exclusion. Hiện1 approved=1điểm nên aggregate count có giá trị số trùng score; quyết định dùng count được căn cứ vào định nghĩa vừa được duyệt, không phải suy diễn nhãn Figma hoặc tự coi mọi điểm là member. Bổ sung alias `members_count`, giữ `score` và `rank` gốc; không sửa LeaderboardService, công thức hoặc schema. Test riêng chứng minh pending/rejected, Sales accounts và group flag không được tính; chuyển Dealer của Sales không chuyển count đã attribution.

### 3. Mapping sort/direction và state
| sort | Giá trị DB | asc / desc | Tie-break |
|---|---|---|---|
| members | members_count từ approved aggregate | ít→nhiều / nhiều→ít, có0 | dealers.name ASC, dealers.id ASC |
| name | dealers.name | A→Z / Z→A theo collation DB | dealers.id ASC |
| rank | standing.rank từ AllTime window |1→hạng lớn / hạng lớn→1 | Unranked NULL luôn cuối cả2chiều; dealers.id ASC |

Mặc định members/desc giữ chính xác thứ tự trước task: approved score DESC/name ASC/ID ASC. Đổi thứ tự hiển thị không tái tính hoặc reset rank. Rank được tính trong subquery AllTime trước q/province/sort/paginate. Direction không truyền mặc định desc cho members, asc cho name/rank. Giá trị NULL không bị gán thành hạng0.

Validate allowlist sort={members,name,rank}, direction={asc,desc}; array, cột tùy ý và chuỗi SQL bị422. Map server-side tới tên cột cố định; chỉ có raw expression cố định `standing.rank IS NULL ASC`, không chèn input. Database ORDER BY trước paginate20, không sắp xếp danh sách bằng JS. Pagination chỉ giữ q/province/sort/direction đã validate/normalize, không phản chiếu selector riêng tư khác; đổi filter/reset page bằng native GET form.

### 4. UI và accessibility
- Header Desktop **#Hạng**, Mobile badge **#Hạng N**, aria-label “#Hạng: N, toàn kỳ”; chưa có hạng dùng—và aria rõ ràng. Không nhãn Hạng tháng này/toàn kỳ cũ tại B14; B15 không bị sửa.
- Native select3tiêu chí, province select và link đảo chiều ↑/↓ có title/aria mô tả chiều hiện tại/chiều tiếp theo,44px hit area. State sort/direction được render từ server. Link có href GET thật giữ filters; JS chỉ progressive-enhance để gửi cả các input đang nhập, không có tên cột/ordering bằng JavaScript.
- Không JS: chọn select rồi dùng nút search để submit; link đảo chiều và pagination vẫn hoạt động. JS: select tự submit; arrow cập nhật hidden direction rồi requestSubmit, có hỗ trợ mở link bằng modifier keys như bình thường.
- Giữ outer filter slots Desktop; Mobile390 có province +sort+arrow không overflow,320 tách thêm hàng khi cần. Chỉ sửa `.wc-d-*`; không thiết kế lại list/profile/header/footer.

### File thay đổi thuộc task này
- Mới `app/Services/DealerDirectoryProvinces.php`.
- `app/Http/Controllers/PublicDealerController.php`: chỉ index validation/options/sort/approved-count projection; không đổi method B15/QR/landing.
- `resources/views/components/competition/dealer-directory.blade.php`, `resources/js/competition.js`, `resources/css/competition.css`.
- Mới `tests/Feature/DealerDirectoryFiltersTest.php`; `PublicDealerDirectoryTest` giữ score/order/rank assertions và thêm members_count, cập nhật accessible copy theo định nghĩa approved.
- `CompetitionDealerTest`: chỉ cố định5 biển số fixture tháng để loại collision ngẫu nhiên đã được regression bắt; không đổi assertions hoặc runtime uniqueness.
- `docs/figma/qa/dealer-state-fixtures.php`: thêm trường members_count cho DTO cũ tương thích component mới; mới `dealer-filters-fixtures.php`/`dealer-filters-visual.mjs`; tracking này.
- Vite manifest/bundles được build tại public/build theo convention ignored. Không sửa migrations, routes, model business, approval, snapshot, policies, privacy/call phone, B15/Leaderboard/Home/Awards source. Working tree vẫn có thay đổi task trước; Git diff HEAD không đồng nghĩa chỉ task này, không stage/commit/deploy.

## Quyết định kỹ thuật quan trọng
Không thay sort/rank mặc định; sort request phải map allowlist. Province không invent/backfill DB. Membership cần xác nhận: Sales account, approved submission và Group membership có evidence là ba tập khác nhau. Tiếp tục các chức năng độc lập khi members bị BLOCKED.

Cập nhật quyết định: membership ambiguity đã được người dùng giải quyết bằng approved profiles; nội dung ban đầu ở trên là diễn tiến audit. Tracking cũ dealer-sync mô tả sort readonly và nhãn Hạng toàn kỳ được **task mới này thay thế** theo yêu cầu, không xóa lịch sử cũ. Không thay quyết định AllTime/QR/privacy hoặc dùng administrative catalog để suy Dealer NULL.

Figma MCP session `21e089cd-3e32-48fd-bd2b-07a87f7b02c7`: đọc metadata/high-fidelity context+screenshot filter Desktop216:412 và Mobile157:510 trong đúng file/page Website Thi đua, không Admin/Khách hàng. Reuse Inter/Montserrat, native select styling và arrow assets đã có; controls mới theo yêu cầu nghiệp vụ hiện tại nên không tuyên bố giống nguyên raster prototype.

## Kết quả kiểm thử
Chưa chạy bản mới. Chỉ chạy fixture transaction sau preflight toyota_testing; không migrate/seed/reset business DB.

### Kết quả mới 2026-11-05
- Preflight **PASS actual loopback MySQL toyota_testing,15tables**, APP_CONFIG_CACHE riêng trỏ file nonexistent để không dùng config cache business; không xóa config cache hay đổi .env. Bootstrap competition-test-bootstrap.php chặn migrate:fresh; fixture transactions rollback.
- Focused mới: **PASS19 tests /180 assertions**, JUnit `.laravel-tools/dealer-filters-focused.xml`. Đủ mặc định,6sorts, ties, approved-vs-pending/rejected/Sales/group-flag, attribution, province normalization/null/invalid/unknown/search,46rows/3pages, global rank, bad sort/direction/arrays và constant query budget.
- Regression lượt đầu **1 ERROR** trong fixture tháng B15: faker sinh trùng plate, ValidationException “Biển số xe này đã được đăng ký” tại create trước HTTP. Không phải lỗi sort/rank mới. Cố định5plate khác nhau cho5ngày, giữ nguyên expected counts/month bounds/snapshot assertions và uniqueness guard. Không bỏ/skip test.
- **Regression cuối PASS284 tests /4.759 assertions**,24classes,0failures/errors/skipped,1m42.884s; PHP8.5.11/PHPUnit12.5.40. Log/XML `.laravel-tools/dealer-filters-regression.txt|xml`. Bao gồm bộ265tests Dealer/B15/Home/Leaderboard/Awards/privacy/registration/lookup trước đó +19case mới, chạy mới không suy từ kết quả lịch sử. Query count4 cố định khi2→26Dealer ở cả3sorts +province: ownership,1distinct provinces,count/data; không N+1. B15 query budget9 và privacy cũ tiếp tục PASS.
- Build **PASS64modules**; Pint explicit service/controller/tests/QA PHP PASS, node --check runtime/QA JS PASS; Git diff --check PASS bằng quyền đọc được duyệt, không thay safe.directory hoặc policy.
- Visual **PASS54 tổ hợp** (9states×320/390/768/1024/1440/1920): native selectors/toggle44px+, không overlap/overflow/broken assets/JS errors; no-provinces hiển thị đúng1All option +note. Native sort/direction/search/province/pagination giữ state **PASS cả JS bật/tắt**. DTO fixtures dùng driver disabled, không phải bằng chứng SQL; sort/query đã được test bằng HTTP Laravel +MySQL ở PHPUnit. DTO được sắp xếp/lọc trong script QA để ảnh trình bày nhất quán, không phải runtime JS sort.
- Live browser regression **PASS42 tổ hợp**: Home/Leaderboard/Awards/directory/B15/landing/lookup×6width, HTTP200/nooverflow/broken image/JS errors; chỉ GET local, không tạo data hoặc POST production. Native search/province/B14→B15/CTA href được kiểm tra lại bằng production JS và fixture Blade. Không có bằng chứng production.
- Read-only business DB audit cuối: Dealer2/active1/provinceNULL2 và Dealer hash **không đổi**. Không migrate/seed/reset/backfill, không nhập dữ liệu34tỉnh hoặc sửa bất kỳ Dealer nào.

### Screenshot/evidence mới (không ghi đè baseline task trước)
- [Desktop bộ lọc](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/members-desc-1440.png), [Mobile390](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/default-390.png), [Mobile320](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/default-320.png), [Sort tên](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/name-asc-390.png), [Sort hạng giảm](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/rank-desc-1440.png).
- [Không có province DTO](qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/no-provinces-390.png); **live local thiếu dữ liệu thật**: [B14 local390](qa/artifacts/dealer-filters-20261105/screenshots/competition-dealer/regression/directory-live-390.png). Không dùng DTO có tỉnh để tuyên bố local đã có tỉnh.
- `qa/artifacts/dealer-filters-20261105/screenshots/dealer-filters/visual.json`:54geometry/state cases +2native JS on/off; `.../competition-dealer/regression/evidence.json`: native interaction +42liveGETs. Đã xem Desktop1440/Mobile390 và live thiếu province, so với filter Figma/context; giữ phong cách/layout cũ trong phạm vi controls, không pixel-perfect claim.

Lệnh tái kiểm tra từ root (PowerShell):
```powershell
$env:APP_CONFIG_CACHE = (Join-Path (Get-Location) '.laravel-tools/dealer-no-cache.php')
php docs/figma/qa/competition-db-check.php
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/dealer-filters-regression.xml tests/Feature/DealerDirectoryFiltersTest.php tests/Feature/CompetitionDealerTest.php tests/Feature/PublicDealerDirectoryTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerSlugTest.php tests/Feature/DealerLandingRegistrationTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerHistoryTest.php tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/CompetitionAwardsTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/RegistrationBusinessIntegrationTest.php
npm run build
php docs/figma/qa/dealer-state-fixtures.php
php docs/figma/qa/dealer-filters-fixtures.php
$env:QA_RUN_ID='dealer-filters-20261105'
# Local PHP server127.0.0.1:8771 phục vụ public; không thêm route fixture vào app.
node docs/figma/qa/dealer-filters-visual.mjs
node docs/figma/qa/dealer-interactions-regression.mjs
```

## Những lỗi còn tồn tại
Chưa có định nghĩa công khai members hoặc bằng chứng province hiện tại; chưa kiểm tra visual/bộ sort mới.

Cập nhật cuối: không còn test FAIL/ERROR ở bản bàn giao, members đã được chốt/triển khai. **Dữ liệu province local BLOCKED** vìNULL; cần operator bổ sung Dealer province từ nguồn riêng được xác nhận trong task dữ liệu được phép, không thuộc quyền tự suy/backfill của task này. Production chưa truy cập nên không kết luận province production đầy đủ hoặc trống. Standard34tỉnh không tự giải quyết liên kết Dealer thiếu dữ liệu.

Chưa kiểm thử browsers khác Chrome/cuộc gọi thật/load-concurrency/full toàn ứng dụng. Không đổi dataset/công thức/rank/ảnh snapshot để làm PASS. Không gọi cleanup/reset hoặc làm thay đổi deployment/config cache/phone approval production.

## Task hoặc bước tiếp theo
Audit actual provinces và nguồn chuẩn; implement name/rank/direction cùng state/pagination; tests, visual/regression; cập nhật tracking, nêu riêng BLOCKED.

Cập nhật cuối: source và QA hoàn thành. Bàn giao để operator kiểm tra/bổ sung province của Dealer bằng dữ liệu đã xác nhận, chạy audit/dry GET trên đúng database triển khai; không thể nghiệm thu “đủ lựa chọn tỉnh thật” với DBlocalNULL. Nếu cần map tên cũ/tên mới hoặc mã tỉnh, chốt mapping trước; không tự nhập từ catalog/địa chỉ/snapshot bằng suy đoán. Không có quyết định membership còn chờ xác nhận.
