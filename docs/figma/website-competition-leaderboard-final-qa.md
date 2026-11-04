# QA cuối Bảng xếp hạng Website Thi đua

## Mục tiêu
Rà soát Top3/phân trang với0/1/2/3/4/20/21/40/46 đại lý, UI Desktop/Mobile, nghiệp vụ/query/privacy, review diff hai test và regression mới. Giữ nguyên runtime nếu không có lỗi xác minh.

## Trạng thái
DONE — cập nhật2026-11-04 sau task riêng `leaderboard-regression-contract-refinement.md`:4FAIL legacy đã được giải quyết có căn cứ; đủ16class PASS168tests/3675assertions. Lịch sử QA ban đầu và các giới hạn visual giữ nguyên bên dưới; không tuyên bố pixel-perfect toàn trang.

## Hiện trạng trước khi chỉnh sửa
- Đã đọc AGENTS.md và toàn bộ website-competition-leaderboard-sync.md, Blade/controller/service/tests thực tế. Source hiện paginate20 gồm Top3 trong20 bản ghi đầu, card Top3 ghim trên mọi trang, list chỉ render rank>3.
- Top3 ghim lặp ở các trang là chức năng hiện hữu theo PublicLeaderboardTest; kiểm tra không trùng trong cùng trang và độ phủ xuyên trang bằng Top3 một lần + toàn bộ list, không cộng Top3 ghim nhiều lần vào tổng.
- Git status đầu lượt trả rỗng; Git diff/log trong sandbox có lỗi ownership/repository. Đang đọc history đúng tài khoản, không đổi safe.directory.
- Preflight mới `php docs/figma/qa/competition-db-check.php`: PASS MySQL loopback toyota_testing,15tables, không config cache. Đã đọc bootstrap chặn migrate và TestDatabase guard. Không migrate/seed/reset database thật.

## Các file source code liên quan
- Chỉ đọc: LeaderboardController/LeaderboardService/DemoSubmissionScope, views leaderboard/competition, competition.css/js, routes/web.php.
- Review tests/Feature/PublicLeaderboardTest.php, MonthlyDashboardTest.php; regression leaderboard/home/Dealer/registration/lookup/privacy.
- QA riêng trong docs/figma/qa và artifact private `.laravel-tools/competition/leaderboard-final-qa`; chưa sửa runtime hoặc tests hiện có.

## Những thay đổi đã thực hiện
Tạo tracking QA riêng trước khi thêm harness. Chưa thay đổi source ứng dụng. Sẽ lưu bằng chứng mới vào `qa/screenshots/competition-leaderboard/final-qa/`, không đè ảnh cũ.
- Kết thúc lượt: **không sửa runtime/source ứng dụng hoặc tests hiện có**. Thêm riêng QA `LeaderboardFinalMatrixTest.php`, `LeaderboardFinalDiagnosticTest.php`, `leaderboard-final-visual.mjs`, ảnh/JSON mới và tracking này; thêm liên kết kết quả QA vào tracking sync hiện tại. Không build lại vì không đổi asset/source, không đổi Figma, không đổi Git config.

## Quyết định kỹ thuật quan trọng
- Fixture DB tests chỉ toyota_testing đã xác nhận, transaction rollback; fixture UI private không lưu vào public/không thêm route runtime.
- Ma trận phải đọc HTML thực render bởi Laravel, follow links thật trong feature tests và đối chiếu toàn bộ service trước/sau HTTP; browser intercept fixture chỉ để bố cục, ghi rõ giới hạn.
- Tách query nghiệp vụ với lookup ownership bằng SQL/binding xác định, không nới budget tùy ý.
- Figma đọc mới session af9f6f50-ee4d-4e5c-8675-fdad0b18ce0a; frame215:322 Desktop/151:303 Mobile. Không sửa thiết kế.

## Kết quả kiểm thử
Chưa hoàn tất; preflight PASS như trên. Không dùng kết quả105tests cũ làm kết quả mới.

### Kết quả mới bước đầu
- Ma trận thật Laravel trên toyota_testing: `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result docs/figma/qa/LeaderboardFinalMatrixTest.php` → PASS18tests/1240assertions,0/1/2/3/4/20/21/40/46 × month/all,26trang. Không trùng trong trang, không mất bản ghi xuyên trang, rank toàn cục/total/pages/activeperiod đúng; query budget cố định; service before/after giống hệt. HTML mới private, evidence `final-qa/matrix.json`.
- Regression mở rộng16class →168tests/3588assertions,4FAIL. Đang xác minh nguyên nhân: LeaderboardServiceTest budget2query thực4;2assertions DealerProfileTest; DealerHistoryTest biển số đã đăng ký. Không coi168tests là PASS, không sửa source/test để che lỗi.
- Diff commit `a191cb1^..a191cb1`: LeaderboardController và LeaderboardService không thay đổi. PublicLeaderboardTest chỉ thêm phân loại/đếm chính xác1/2lookup ownership, giữ2/3businessquery; không xóa assertion privacy/rank/period. MonthlyDashboardTest chỉ đổi phone fixture pending sang0901234568, giữ assertions Sales rỗng/Dealer score1. Review không thấy yếu đi các bảo vệ ban đầu.

## Những lỗi còn tồn tại
Chưa kết luận; cần kiểm tra ma trận và regression. Reference Mobile cũ bị ancestor clip dưới844px, sẽ đối chiếu ảnh mới và ghi giới hạn.

### Kết luận hiện tại — giữ nguyên source
Không phát hiện lỗi runtime leaderboard trong phạm vi đã kiểm tra. Có4test regression FAIL đã tái hiện chạy riêng, không được coi là PASS hoặc bỏ qua để nghiệm thu:

| Test | FAIL mới và căn cứ | Phân loại/xử lý |
|---|---|---|
| LeaderboardServiceTest:115 | Mong2 query, thực4. Diagnostic SQL xác minh2aggregate COUNT/GROUP BY +2lookup chính xác `select * from users where email = ? limit 1`, binding ToyotaRealisticDemo::EMAIL. 8→16participants vẫn4query. | Assertion budget cũ chưa tính DemoSubmissionScope; không phải N+1. Không đổi service/test cũ. |
| DealerProfileTest:34 | Mong `DEALER_PHONE` xuất hiện. Fixture là chuỗi không hợp lệ, không có approval; DealerCallPhone và card hiện tại chỉ render số đã normalize/allowlist đúng slug/số. | Test lệch contract công khai số đã phê duyệt (xem dealer-call-button-refinement.md). Không làm lộ phone để PASS. |
| DealerProfileTest:164 | Mong text “Ảnh đại lý đang được cập nhật”. Card hiện dùng icon Dealer khi thiếu photo, không có text đó. | Assertion UI cũ, không thêm lại text ngoài thiết kế chỉ để PASS. |
| DealerHistoryTest:53 | helper data(first/second) đều gán29D-428.12. POST thứ hai sau transfer bị occupied-license guard từ chối trước assertion điểm. | Fixture trùng biển số, không phải bằng chứng sai attribution/rank. Không tắt uniqueness hoặc sửa fixture lịch sử trong lượt review này. |

Các failures thuộc service-test expectation/Dealer fixtures/UI cũ, không xuất phát từ thay đổi source trong lượt QA (không có source change). Các phép kiểm tra đúng hiện tại về snapshot transfer/privacy/QR vẫn chạy qua MonthlyDashboardTest/PublicPrivacyTest; không dùng điều đó để xóa4FAIL.

## Task hoặc bước tiếp theo
Đo ma trận dữ liệu/HTML, review commit diff, regression cách ly, chụp và xem ảnh mới, phân loại PASS/FAIL/chưa kiểm chứng. Chỉ sửa nếu xác minh lỗi.

### Các bước kiểm tra đã hoàn tất — kết quả mới, không phải lịch sử
1. **Ma trận PASS:** counts1/2/3/4/20/21/40/46 có pages tương ứng1/1/1/1/1/2/2/3, kiểm tra cả month/all; thêm0/empty. Tổng18scenarios/26pages. Từng trang cócardTop3 đúng IDs, list rank>3, không trùng ID trong cùng trang. Top3 pinned lặp trên trang2/3 đúng chức năng hiện có; Top3 tính một lần + toàn list qua26pages phủ đúng tổng và thứ tựservice, không thiếu/duplicate, rank không reset. Filter, next/prev URL chỉ period/dealers_page hợp lệ. Total của paginator bao gồm Top3 (không cộng Top3 mỗi trang).
2. **Nghiệp vụ PASS:** assert độc lập điểm1 mỗi approved fixture và ID/time order; projection chỉ id/name/code/score/rank; sentinel Sales/customer/admin-note không lọt HTML. Service trước/sau mọi GET giống nhau, số submissions không đổi trong transaction. Existing PublicLeaderboardTest/MonthlyDashboardTest diff không bỏ assertion privacy, đồng điểm, month/week, snapshot hay QR. Budget2/3businessquery giữ nguyên + ownership1/2 được đếm riêng. Git commit review a191cb1^..a191cb1 không có diff service/controller.
3. **Regression FAIL4:** lệnh mới bên dưới chạy nguyên16class, không filter bỏ test:168tests/3588assertions,164PASS/4FAIL, exit1. Chạy riêng đúng4test bằng `--filter` chỉ để chẩn đoán/tái hiện, kết quả vẫn4FAIL; XML/log private `.laravel-tools/competition/leaderboard-final-qa/failures.xml|log`. Không cộng các lượt rerun vào tổng test để phóng đại coverage.
4. **Service diagnostic PASS:** `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result docs/figma/qa/LeaderboardFinalDiagnosticTest.php` →1test/27assertions, SQL/constant query8/16participant ở `final-qa/service-query-diagnostic.json`. Không sửa test aggregation cũ cho xanh.
5. **UI PASS93 lượt:** `node docs/figma/qa/leaderboard-final-visual.mjs` →78matrix pages×viewport320/390/1440 (follow actual rendered next/prev href),12design/stress ở320/390/768/1024/1440/1920,3live GET interaction/font ở320/390/1440. Không overflow, text clipping, ảnh thiếu hoặc lỗi JS trong matrix; Top1center/Top2left/Top3right Desktop và1→2→3theo yMobile. Periodactive đúng cả next/prev. Tên dài/điểm123.456.788 hiển thị đủ và xuống hàng theo CSS hiện có. Không có runtime mock route; browser matrix intercept fresh HTML được xuất từ HTTP test trên toyota_testing, assets/JS thật.
6. **Live interaction/font PASS:** GET /leaderboard200; keyboardEnter sang all, mobile menu/aria-current/Escape, linkHome tới Hero; clickDealer thật qua redirect trả200. Footer tồn tại, không href rỗng/#giả. CDP chuỗi tiếng Việt trên leaderboard ở600/700 chỉ Montserrat custom SemiBold/Bold, không fallback. Ảnh thực `live-320/390/1440.png`; đây là GET local thật, không seed.
7. **Figma/visual đối chiếu:** screenshot mới từ MCP session af9f6f50-ee4d-4e5c-8675-fdad0b18ce0a:215:322Desktop1440×1399,151:303Mobile390×844. Download sandbox lần đầu curl7Badaccess; sau phê duyệt ngoài sandbox PASS. Đã mở xem reference mới + design1440/390, long-podium390, count21/month/page2/320 và count2/all/390; không phát hiện sai lệch mới cần sửa. Giữ UTC,20/page, disablednext/prev và footer/phone/privacy theo dữ liệu/config thật, không chép minh họa Figma.
8. **Pint/Node PASS:** `php vendor/bin/pint docs/figma/qa/LeaderboardFinalMatrixTest.php docs/figma/qa/LeaderboardFinalDiagnosticTest.php --test`; `node --check docs/figma/qa/leaderboard-final-visual.mjs`. Không chạy build vì không sửa runtime/assets.

### Lệnh regression đã chạy mới
```powershell
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerHistoryTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/CampaignRulesTest.php
```

### Bằng chứng và giới hạn
- `qa/screenshots/competition-leaderboard/final-qa/matrix.json`:18scenarios/26pages; `visual.json`:93checks, phân biệt fresh testHTML, DB-disabled stress và GET live.
- `regression-summary.json`: kết quả168tests+4FAIL và hai QA suite mới, không gộp thành PASS. Tổng lượt test riêng không rerun:187tests,183PASS/4FAIL; assertions3588+1240+27=4855. Không phải full suite.
- Reference `figma-desktop.png`, `figma-mobile.png`; ảnh `count-<n>-<period>-page-<p>-<width>.png`, `design-*`, `long-*`, `long-podium-390.png`, `live-*` mới, không đè evidence triển khai cũ.
- **Chưa kiểm chứng:** pixel-perfect phần Mobile cuộn dưới844px vì raster reference mới vẫn bị clip; các phần đó đã đọc context ở task triển khai và chụp/xem UI mới, không giả có full reference. Không chạy full suite/concurrency/load hoặc browser khác Chrome. Không thay điều kiện công khai/score/rank.
- **Bước tiếp theo:** xử lý riêng các legacy test assertions/fixtures sau khi đối chiếu contract đã được phê duyệt; giữ nguyên guard/public-phone approval. Chỉ xác nhận regression DONE khi4test đó đã được giải quyết có căn cứ và chạy lại. Không có sửa runtime leaderboard cần thực hiện từ kết quả QA này.

## Cập nhật từ task sửa regression riêng — 2026-11-04
- Trước task sửa, báo cáo này giữIN PROGRESS vì4FAIL; các phép đo168tests/4FAIL và rerun4FAIL phía trên là lịch sử QA, không bị xóa.
- Task `leaderboard-regression-contract-refinement.md` đã tái hiện4FAIL mới, xác nhận contract và chỉ sửa LeaderboardServiceTest/DealerProfileTest/DealerHistoryTest. Giữ2aggregate+đếm riêng2ownership, thử8→16participants; phonefixture hợp lệ chỉ công khai sauapproval đúngslug/số; iconfallback thaycopy cũ nhưng vẫn endpoint404/diskempty; khách thứ hai dùng plate khác, giữ snapshot/score/awardassertions. Không thay source runtime/privacy/uniqueness/điểm hoặc UI.
- Kết quả mới sau sửa: focused4tests/120assertions PASS; **đủ16class khôngfilter PASS168tests/3675assertions,0FAIL/ERROR/SKIP**; Pint/diffcheck PASS. MySQL toyota_testing đãpreflight, không migrate/seed/reset DBthật. XML `regression.xml` trong `.laravel-tools/competition/regression-contract-refinement/`.
- Kết quả này supersede nhận định “còn4FAIL/chưa đủ điều kiện regression DONE” và bước xử lýlegacy phía trên. Không thay đổi bằng chứng93UIchecks hay giới hạnraster Mobile844px/fullsuite/concurrency; không chạy lại UI vì task này chỉ sửa tests.
