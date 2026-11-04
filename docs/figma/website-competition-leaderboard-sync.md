# Đồng bộ Bảng xếp hạng Website Thi đua Desktop/Mobile

## Mục tiêu
Đồng bộ giao diện public leaderboard theo hai page Website Thi đua Figma, giữ nguyên nguồn dữ liệu, thứ hạng, kỳ và privacy Laravel.

## Trạng thái
DONE — triển khai và kiểm thử hoàn tất 2026-11-04; giới hạn raster reference Mobile dưới fold ghi rõ bên dưới, không nghiệm thu pixel-perfect toàn trang. Tracking được tạo trước khi sửa source.

## Danh sách frame Figma đã đọc
- File yKmWQI6BYJ6dponEnidlsq, session a0e79341-2b0d-4e5b-9cf0-aa9c81f16dbb.
- Đã đọc metadata đúng page Desktop207:333/Mobile148:252, xác minh B02 Desktop215:322 (1440×1399), Mobile151:303 (390×844); design context high-fidelity và screenshot cả hai.
- Đang rà interaction segmented và state khác; chưa mặc định có empty/detail riêng.
- Hoàn tất: Desktop Top3215:369/full215:406/pagination215:489; Mobile Top3151:336/full151:377/list151:379/pagination151:435. Interaction sets Desktop222:1629/Mobile173:540, cả hai active variants. High-fidelity context đọc từ hai root bao gồm toàn bộ descendants, thêm metadata151:377; ảnh root và vùng list visible đã lưu. File tổng hợp `qa/leaderboard-design-evidence.json`.

## Hiện trạng trước khi chỉnh sửa
- Đọc AGENTS.md, context-recovery, tracking home/font, MONTHLY_DASHBOARD_REPORT.md, PUBLIC_PRIVACY_REPORT.md và DEALER_PROFILE_DASHBOARD_REPORT.md; source thực tế là chuẩn nghiệp vụ. Tài liệu privacy cũ chỉ có hai role đã bị thay thế bởi báo cáo Dealer và source ba role; không thay quyền.
- GET /leaderboard → LeaderboardController → LeaderboardService; SSR, hai kỳ month/all, URL week chuyển month; paginate20, chỉ appends period. Không async/loading. Giao diện x-layout cũ, grid3card rồi table trùng top3, links paginator mặc định.
- Chỉ approved, theo submitted_at [start,end), attribution submissions.dealer_id; Sales hoặc dealer_qr không sales; DemoSubmissionScope loại demo theo ownership. Thứ tự score DESC, MAX(submitted_at) ASC, id ASC, ROW_NUMBER. View chỉ id/name/code/score/rank Dealer. Public leaderboard không có published gate riêng; draft award không liên quan tới bảng live, không thêm gate mới.
- Git status đầu lượt bị dubious ownership, không đổi config hoặc reset. Có nhiều thay đổi có sẵn từ home/font/dealer-call; chỉ chỉnh file trong phạm vi task.

## Bảng đối chiếu Desktop/Mobile
| Phần | Desktop Figma | Mobile Figma | Source cũ |
|---|---|---|---|
| Shell | Header72/container1200/footer | Header60/container358 | x-layout cũ |
| Heading/filter | 40/46, filter bên phải | 24/30, filter full width dưới intro | Tabs vuông |
| Meta | Hàng ngang date/tie | Hai hàng13/18 | Hai paragraph nhỏ |
| Top3 | Podium2–1–3, gradient hạng1 | Hạng1 nổi bật rồi2/3 dạng list | Ba card đồng dạng |
| Bảng | Rank/name/code/score, từ4 | Rank/name+code/score list từ4 | Table desktop cả mobile |
| Pagination | Trong card, nhãn trái | Ngoài card, nhãn giữa | Paginator mặc định |

## Các file source code liên quan
- routes/web.php; app/Http/Controllers/LeaderboardController.php; app/Services/LeaderboardService.php; app/Enums/LeaderboardPeriod.php; app/Models/Dealer.php; DemoSubmissionScope (chỉ đọc).
- resources/views/leaderboard/index.blade.php; components/leaderboard-section.blade.php; components/competition/{layout,navigation,brand,icon}.blade.php; resources/css/competition.css; resources/js/competition.js.
- Tests PublicLeaderboardTest, MonthlyDashboardTest, PublicPrivacyTest, LandingPageTest và registration/lookup; QA/bootstrap DB isolation có sẵn.

## Những thay đổi đã thực hiện
- Đã chuyển leaderboard sang competition layout, intro/meta/segmented, podium2–1–3 desktop, card/list riêng mobile, danh sách rank>3 và pagination theo paginator thật. Tạo leaderboard-card/pagination components; CSS scope wc-lb không đổi các selector home.
- Navigation shared sửa aria-current theo route home/leaderboard; kiểm tra home hồi quy sau build. Không sửa controller/service/model.
- Đã tải8 SVG gốc Figma (calendar/info/trophy22/20/18/watermark200/150) vào public/competition/leaderboard, giữ root dimensions. Curl sandbox Bad access; tải sau phê duyệt ngoài sandbox PASS. Logo/menu dùng assets shared hiện có.
- Hai component set interaction xác minh Desktop222:1629, Mobile173:540, có cả month/all; không tìm thấy frame leaderboard empty/detail riêng trong hai page. B15 là dealer page riêng, link hiện hữu giữ nguyên, không đồng bộ B15 trong task này.

## Quyết định kỹ thuật quan trọng
- Tái sử dụng shell/tokens/fonts; CSS leaderboard scope riêng. Không sửa service/controller/model/schema/permissions.
- Dữ liệu Figma chỉ minh họa. Giữ20/page và timezone config hiện tại (UTC), không hard-code tháng/số trang/điểm.
- Top3 hiển thị riêng; list bỏ các rank<=3 đã có card ở mọi trang, không thay paginator/query hoặc re-rank. Empty không card giả. Giữ chức năng links chi tiết và period thực.
- Test ghi dữ liệu chỉ sau preflight xác nhận toyota_testing loopback; không migrate/seed/reset. Fixture browser DB disabled tách biệt với GET dữ liệu local thật.

## Kết quả kiểm thử
- Preflight DB `php docs/figma/qa/competition-db-check.php` PASS toyota_testing/loopback15tables, không config cache; dùng bootstrap chặn migrate có sẵn.
- Trước sửa: PublicLeaderboardTest8tests/50assertions,1FAIL query count expected2 actual3,7PASS. Không sửa test để PASS.
- Build sau sửa PASS64 modules. Lần test tiếp theo vẫn query-count FAIL, thêm1ERROR fixture random biển số trùng ở test23records trước HTTP; đang phân biệt lỗi fixture với UI và kiểm tra lại. Không bỏ guard uniqueness.
- Các kết quả88tests của home là lịch sử, không coi là kết quả mới.

## Ảnh screenshot
Đã xem reference Figma Desktop/Mobile qua MCP; chưa chụp UI hiện tại.

## Những lỗi còn tồn tại
Chưa triển khai/kiểm chứng; không tuyên bố pixel-perfect. Cần xác minh assets icon và trạng thái filter.

## Task hoặc bước tiếp theo
Đọc interaction/assets, chụp baseline, triển khai Blade/CSS scoped, test cách ly + browser6viewport/long/empty, cập nhật evidence và kết luận.

## Cập nhật kiểm chứng 2026-11-04 — sau triển khai
- Giữ nguyên ghi chép baseline ở trên. Có2 lỗi UI phát hiện bằng screenshot: Blade inline `@elseKhông` không compile; điểm9chữ số ép tên thành cột hẹp. Đã tách directive ra dòng riêng; thêm class presentational dựa độ dài điểm định dạng để điểm lớn xuống hàng trên mobile, không thay giá trị/thứ hạng. Bỏ list/header rỗng khi chỉ có top1–3, không tạo card giả.
- Bổ sung info-mobile.svg đúng màu Figma; calendar mobile/desktop cùng SHA256 nên dùng chung calendar.svg. Tổng9SVG local, đúng root16/18/20/22/150/200px. Watermark giữ nguyên vùng clip như Figma, không sửa SVG; shared logo/menu đã có.
- Layout shared thêm prop title với default y nguyên home; leaderboard có document title riêng. Shared navigation chỉ sửa aria-current theo route, không đổi JS/menu/auth.
- **Hai test baseline được sửa theo contract thực, không đổi runtime để ép PASS:** PublicLeaderboardTest giữ nguyên budget2/3 query nghiệp vụ, tách riêng chính xác1/2 query lookup demo nhận diện bằng SQL users + binding email ToyotaRealisticDemo::EMAIL. Test mới tăng23→46 đại lý vẫn cùng budget, kiểm tra order và links từng bản ghi. Không miễn trừ query tùy ý.
- MonthlyDashboardTest QR trước đây tạo approved rồi pending cùng phone_normalized, guard hiện tại từ chối ở Model trước HTTP. Chỉ thay phone của bản ghi pending thứ hai bằng số test khác; vẫn cùng Dealer/source, vẫn xác nhận pending không tính điểm, không có Sales rank. Không tắt uniqueness/lock.
- Lần test mới đầu tiên thất bại do closure giữ expected collection cũ sau tăng dữ liệu, đã capture reference để đối chiếu snapshot đúng cardinality. Đây là lỗi QA đã sửa, không sửa service hay expectation thứ tự. Một lần random factory biển số trùng trong test cũ không lặp lại ở các lượt sau; giữ guard, ghi rủi ro fixture lịch sử.
- `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/LandingPageTest.php`: **PASS39tests/2075assertions**. Bao gồm đồng điểm achieved_at→Dealer ID, month/week compatibility, boundaries, approved/pending/rejected, snapshot attribution/QR, draft award privacy, pagination/page2, escaping, query budgets, home/navigation.
- Cùng bootstrap: DealerRegistrationDraftTest + RegistrationLookupTest + RegistrationLookupStatesTest + CampaignRulesTest: **PASS66tests/850assertions**. Không migrate/seed/reset DB; toyota_testing hiện hữu, giao dịch rollback theo tests/guard. Tổng105tests/2925assertions, không phải full suite.
- `npm run build`: PASS64 modules. Pint các PHP QA/test trong phạm vi PASS sau sửa formatting fixture; Node syntax PASS. `git diff --check` sau đó với đúng cwd PASS exit0 (cảnh báo CRLF2file thay đổi có sẵn), không sửa safe.directory.
- `node docs/figma/qa/leaderboard-visual.mjs`: **PASS48 tổ hợp**8states×6viewport: live-month/live-all GET dữ liệu local thật; design/long/empty/page-two/one/two là Blade fixtures private với DB disabled, không route mock trong Laravel. Không overflow/text clipping, lỗi JS/request/HTTP; đúng asset visible/root sizes; tháng/toàn chương trình click thật, mobile menu/Escape, active nav. Hero/home screenshots390/1440 byte-identical baseline.
- Lượt visual giữa chừng có1request inter-vietnamese.woff2 thất bại, script báoFAIL đúng; thêm errorText và chờ document.fonts.ready khi mở menu, chạy lại toàn bộ48PASS. Không bỏ kiểm tra request/font.
- Bằng chứng `qa/screenshots/competition-leaderboard/before/`: baseline leaderboard/home390/1440; `after/`:8states×6viewport, evidence.json. Đã mở xem design1440/390 đối chiếu reference Figma, long390 sau sửa, empty390, live-month1440 và one390.
- Reference gốc đã tải `figma-desktop.png`1440×1399, `figma-mobile.png`390×844, `figma-mobile-list-visible.png`390×166. Mobile root clip sau844; screenshot riêng section151:377 chỉ chứa vùng visible166px, chưa có reference raster cho toàn bộ phần cuộn dưới. Dưới fold dựa high-fidelity context151:377 và browser; không tuyên bố pixel-perfect toàn trang. Date/timezone/page count khác sample là có chủ đích theo backend thật, không đổi timezone/paginator.
- Các kết quả mới trên thay thế nhận định “chưa triển khai/kiểm chứng/còn lỗi” trong các mục bắt đầu; giữ lịch sử để truy nguyên. Đang chốt interaction chi tiết/font và note-only12viewport sau sửa markup cuối.

## Kết luận cuối — 2026-11-04
- **Đã hoàn tất triển khai trong phạm vi:** header/navigation/footer shared; intro/segmented/meta; podium Desktop2–1–3; Mobile1–2–3 với layout riêng; list và pagination thật; empty,1–2đại lý, trang2, tên dài/điểm lớn. Không thêm filter/search/async loading hoặc nút giả ngoài source/Figma. Không chỉnh trang Khách hàng/Admin.
- `node docs/figma/qa/leaderboard-interactions.mjs`: PASS click Dealer thật ở390/1440, theo redirect legacy tới trang chi tiết HTTP200; Enter đổi kỳ, click Home desktop/mobile; CDP chuỗi `MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI CỘNG ĐỒNG` trên chính leaderboard chỉ Montserrat custom, PostScript SemiBold600/Bold700. Không fallback. Probe chỉ trong DOM QA, không runtime.
- Sau bỏ list/header rỗng, private fixture1/2đại lý chụp lại12tổ hợp6viewport PASS, không vệt nền trắng của ol rỗng/overflow/directive Blade; `after/interaction-evidence.json` lưu riêng bằng chứng cuối. Các states còn lại giữ nguyên markup đã PASS48.
- PHPUnit nhóm39tests/2075assertions đã chạy lại sau sửa markup cuối và PASS; nhóm66tests/850assertions đăng ký/tra cứu không bị ảnh hưởng. Không còn test FAIL/ERROR trong105tests đã chạy. Đây không phải full suite toàn ứng dụng.
- Build cuối PASS64 modules, hash competition-BLpAqQAq.css giữ nguyên với build đã dùng visual QA. Pint3test+fixture PASS; Node syntax3scripts PASS. Git check có lúc PASS, có lúc môi trường trả Not a git repository/dubious ownership; không đổi config. Không dùng Git unavailable để suy luận sạch toàn bộ workspace.
- Các thay đổi source runtime: `resources/views/leaderboard/index.blade.php`, `resources/css/competition.css`; shared `resources/views/components/competition/layout.blade.php` (title prop giữ default home), `navigation.blade.php` (active route); mới `leaderboard-card.blade.php`, `leaderboard-pagination.blade.php` cùng thư mục;9assets SVG trong `public/competition/leaderboard/`.
- Tests: mới `tests/Feature/CompetitionLeaderboardTest.php`, sửa có căn cứ `PublicLeaderboardTest.php` và fixture `MonthlyDashboardTest.php`. Không sửa test để bỏ bảo vệ nghiệp vụ/privacy/query budget; ghi đủ căn cứ và lần FAIL ở trên.
- QA/docs mới: `qa/leaderboard-baseline.mjs`, `leaderboard-state-fixtures.php`, `leaderboard-visual.mjs`, `leaderboard-interactions.mjs`, `leaderboard-design-evidence.json`, bộ ảnh `qa/screenshots/competition-leaderboard/` và tracking này. Build assets/manifest do Vite sinh; không chỉnh thủ công. Không chỉnh tracking lịch sử của trang chủ.
- Dữ liệu local live hiện chỉ có1đại lý có điểm ở kỳ đang xem; không tạo thêm dữ liệu để giống Figma. Dữ liệu10đại lý reference,23đại lý tên dài, empty/page2/one/two chỉ trong private DB-disabled fixtures. HTTP live và fixture được phân biệt trong evidence.json. Tests nghiệp vụ dùng toyota_testing, không migrate/seed DB thật.
- Đối chiếu trực quan đã xem Desktop1440/Mobile390 với screenshot Figma và các trạng thái dài/rỗng/ít dữ liệu; typography/spacing/order/assets/overflow trong phạm vi đã xác minh. Home390/1440 screenshot byte-identical baseline, regression home và registration/lookup PASS.
- **Giới hạn còn lại:** Figma Mobile frame151:303 clip sau844px; list151:377 cao640px nhưng render chỉ166px, kể cả contentsOnly. Không có ảnh raster cho toàn bộ phần cuộn dưới để so pixel; đã đối chiếu context/metadata đầy đủ và xem screenshot live toàn trang. Không có empty frame riêng để tuyên bố empty pixel-perfect; giữ copy và chức năng empty cũ với tokens Website Thi đua. Footer Mobile giữ component hiện hữu vì source cần footer; root B02 Mobile không có footer riêng.
- Không còn lỗi triển khai được xác nhận trong phạm vi kiểm thử. Nếu cần nghiệm thu pixel từng phần Mobile dưới fold, bước tiếp theo là cung cấp reference Figma không bị ancestor clip; không sửa file thiết kế để vượt giới hạn trong task này. Các thay đổi chính sách giờ/kỳ hoặc quy tắc công bố không thuộc task UI.
- Check cuối độc lập `git diff --check`: PASS exit0, chỉ cảnh báo CRLF ở dealer-registration.css/welcome.blade.php có sẵn trước task. Đã dừng dev server8771 do task khởi động sau khi hoàn tất QA.

## QA cuối bổ sung — 2026-11-04 (kết quả mới sau DONE triển khai)
- Chi tiết riêng tại `website-competition-leaderboard-final-qa.md`; giữ nguyên kết luận/bằng chứng lịch sử triển khai ở trên, không coi105tests cũ là kết quả của lượt mới.
- **PASS:** ma trận0/1/2/3/4/20/21/40/46đại lý ×month/all,18tests/1240assertions,26pages; không trùng trong trang, không mất đại lý xuyên trang, rank/total/pages/filter đúng; service before/after giữ nguyên. Top3 ghim trên mỗi trang có chủ đích, không cộng nhiều lần vào tổng.
- **PASS:**93lượt UI mới, gồm78matrix page/viewports320/390/1440,12reference/stress320–1920,3live navigation/font. Đã mở xem Figma mới215:322/151:303 và screenshot mới; không phát hiện lỗi runtime leaderboard cần sửa. Source ứng dụng và hai test đã chỉnh trước đó giữ nguyên.
- **FAIL:** regression mở rộng16class168tests/3588assertions có164PASS/4FAIL, đã tái hiện4FAIL riêng: service aggregation budget cũ2vs4 (2aggregate+2lookupdemo, diagnostic8/16participants PASS1test/27assertions), DealerProfile đòi phonefixture chưa approved, placeholderảnh cũ, DealerHistory dùng lại29D-428.12 bị uniquenessguard chặn. Không sửa runtime/test để che các failures này; xem căn cứ trong tracking QA.
- Chưa đủ điều kiện xác nhận regression DONE toàn bộ: tracking QA giữIN PROGRESS vì4test chưa giải quyết. DONE triển khai bên trên là trạng thái lịch sử, không có nghĩa regression mở rộng hiện xanh. Không thay businesslogic/schema/privacy; không migrate/seed/reset DB thật. Tất cả featurefixture ởtoyota_testing đã preflight/transactionguard.
- **Chưa kiểm chứng:** pixel-perfect Mobile toàn bộ phần cuộn dưới844px do reference raster mới vẫn clip; không chạy full suite/concurrency/load/browser khácChrome. Không bỏ giới hạn này chỉ vì UI checks PASS.
- Bằng chứng mới `qa/screenshots/competition-leaderboard/final-qa/`: matrix.json/visual.json/service-query-diagnostic.json/regression-summary.json, ảnhFigma mới, screenshotmatrix/design/stress/live. Không đè ảnh triển khai cũ.

## Đóng4regression bằng task riêng — 2026-11-04
- `leaderboard-regression-contract-refinement.md` xác nhận contract từ source/tracking, tái hiện lỗi mới rồi chỉ sửa3file test lỗi thời. Không thay logic điểm/privacy/uniqueness hoặc giao diện.
- Chạy lại **đầy đủ16class PASS168tests/3675assertions,0FAIL/ERROR/SKIP** trên toyota_testing; focused4test và Pint/diffcheck PASS. Kết quả này thay thế trạng thái regression4FAIL ở QA bổ sung phía trên; giữ lịch sử/ảnh và các giới hạn visual nguyên vẹn.
- `website-competition-leaderboard-final-qa.md` cập nhậtDONE theo kết quả regression mới, không suy ra pixel-perfect Mobile dưới fold hoặc fullsuitePASS.
