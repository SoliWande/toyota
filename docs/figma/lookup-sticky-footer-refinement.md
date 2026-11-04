# Sửa sticky footer trang Tra cứu đăng ký

## Mục tiêu
Xác định nguyên nhân bằng computed styles, sửa footer theo luồng tự nhiên khi nội dung ngắn/dài, kiểm tra default/D7–D15/validation ở320/390/768/1024/1440/1920 và regression các layout liên quan. Không sửa nghiệp vụ hoặc chiều cao section để che lỗi.

## Trạng thái
DONE —2026-11-04, sửaCSS tối thiểu và hoàn tất browser/visual/regression trong phạm vi. Lỗi testQR phụ thuộc sharedprocess có sẵn được ghi riêng, không quy thành PASS; xem giới hạn bên dưới. Tracking tạo trước khi sửa source runtime.

## Hiện trạng trước khi chỉnh sửa
- Đọc AGENTS.md; lookup-d7-d15-sync.md, lookup-result-panel-sidebox-refinement.md, phase-3c-lookup-progress-refinement.md; tracking Website Thi đua/home/Leaderboard và task regression mới nhất.
- GET/POST `/{slug}/registrations/lookup` qua RegistrationLookupController, view `dealers/registration/lookup.blade.php`, layout `components/dealer-registration/layout.blade.php`; stylesheet entry `resources/css/dealer-registration.css`. Body trực tiếp chứa skiplink/header/main#dr-main/footer, không có wrapper khác.
- `.dr-site` hiện không flex/grid/min-height; `#dr-main` không flex-grow mặc định. Chỉ D8Mobile có bodyflex/min-height100svh/mainflex và chủ đíchẩn footer theo FigmaM08. Cần đo browser trước khi kết luận nguyên nhân; không quy lỗi cho competition.css khi chưa có bằng chứng.
- Working tree có3filetest và tracking/QA từ task trước; không đụng/ghi đè. Git status ban đầu bundled bị ownership, lệnh độc lập đọc được. Không sửa Gitconfig.

## Các file source code liên quan
- resources/css/dealer-registration.css; layout và lookup Blade chỉ đọc trừ khi xác minh cần sửa.
- routes/web.php, RegistrationLookupController, RegistrationLookupRequest, lookup/supplement services và components chỉ đọc.
- QA riêng mới: fixture private DBdisabled + Playwright đo before/after, screenshots dưới `qa/screenshots/lookup-sticky-footer/`; không thêm mockroute vào app.

## Những thay đổi đã thực hiện
Chưa sửa runtime; đang dựng evidence đo nguyên nhân và baseline.

### Xác định nguyên nhân —2026-11-04
- Probe browser với Blade/build thật, viewport900px: body/html displayblock,min-height0; main flex-grow0; footer static, không marginbottom hoặc positionfixed. Desktop1440 body/footerbottom564.984px, document900px →335.016px trống dưới footer. Mobile390 body/footerbottom746.5px →153.5px trống dưới footer.
- DOM trực tiếpbody>header/main/footer, không wrapper flex/grid. Chỉ D8Mobile có bodyflex; các lookup khác thiếu shell giãn main. Không phải footer bị đẩy lên do absolute/fixed, không thấy height cố định ở body/main.
- Stylesheets thực: GooglefontCSS, lookup-fonts.css và dealer-registration-Dwh6UaTx.css. Không tải competition.css trên lookup; không có bằng chứng CSS Website Thi đua/Leaderboard ghi đè. Rootcause là lookup shell block-flow thiếu min-height viewport/main growth.
- Không đổi mobilefooter padding110px vốn là style có sẵn; không dùng việc thay padding để che khoảng trống viewport. Số đo và ảnhprobe ở `qa/screenshots/lookup-sticky-footer/before/probe.json`.
- Đã hoàn tất baseline96số đo (13fixtures×6widths +12tall +6GETlookup thật) và42ảnhtrangshared/live; ảnh/tracking lịch sử giữ nguyên. Sau baseline mới thêm3rule lookup-only vào dealer-registration.css: bodyflexcolumn,min-height100vh fallback100dvh; mainflex1 0auto; header/footer không co. Không sửa HTML/Blade/spacing/section height hoặc positionfooter.

## Quyết định kỹ thuật quan trọng
- Ưu tiên flexcolumn/min-height viewport và mainflex, scope lookup-only để không đổi các trang khác. Không fixed/absolute footer, không margin-top/min-heightpx bù trừ header/footer; giữ spacing nội dung.
- Giữ D8Mobile ẩn footer có chủ đích theo FigmaM08 hiện hành, không tự thay thiết kế trong task stickyfooter. Kiểm tra hidden/CTAflow riêng; mọi footer hiển thị phải ở cuối nội dung/viewport.
- Fixture chỉ DBdisabled, không seed/migrate; browser GET live chỉ đọc. Featuretests nếu cần phải preflight toyota_testing như AGENTS.md.

## Kết quả kiểm thử
Chưa có kết quả mới; lịch sử lookup/168tests không phải kiểm thử task này.

### Kết quả mới —2026-11-04
- `php docs/figma/qa/lookup-sticky-footer-fixtures.php`: PASS13state fixtures, database driver disabled, không route fixture trong Laravel. Default,validation,D07,D08,D10,D11,D12,D13,D14,D15,long,found và rules(D09). Không tạo dữ liệu runtime/production.
- `node docs/figma/qa/lookup-sticky-footer-visual.mjs before`: baseline96số đo,120screenshots. `... after`: PASS96số đo,120screenshots tại320/390/768/1024/1440/1920 và900pxheight, thêmdefault/long ở1200pxheight. Mỗi lượt có78fixture cases +12tall measurements +6lookup liveGET; thêm36ảnh live regression ngoài lookup.
- Footer hiển thị: bottom bằngdocumentHeight (sai số<=1px), ít nhất bằngviewportHeight; footer.top>=main.bottom, mọi CTA nằm trênfooter; positionstatic, không horizontaloverflow, không lỗiJS/requestlocal/ảnhvisible. Main/bodyflexcolumn đúng, mainflexgrow>0; form/header/result/Sidebox/supplement block x/y/width/height giữ nguyên<=1px so baseline. D8Mobile320/390 tiếp tục ẩnfooter đúngFigmaM08; không biến hidden thànhposition hack.
- **Số đo default:**1440×900 trướcfooterTop464/bottom564.984; sautop799.016/bottom900, main727.016px.390×900 trướctop529/bottom746.5; sautop682.5/bottom900, main617.5px. Bodyminheight900, footer không còn khoảng trống viewport phía dưới. Không sửa padding/height của section.
- **Nội dung dài:** long reason35lần +header/form/result tự tăngdocumentHeight, footer sau main và cuộn tự nhiên cả900/1200pxheight. Validation servererrors được render đầy đủ ở6viewport, không chefooter/CTA. Không POST vào dữ liệu local thật.
- **Regression visual:** liveGET trangchủ/leaderboard/landing/preview step-two/success/inactive tại6viewport:36ảnh ngoàilookup byte-identical trước/sau. D09/rules6ảnh cũng byte-identical, tổng42ảnh unchanged. Lookup liveGET từ Dealer thật tại6viewport trả200 vàfooter đúng. Nguồn fixture/live phân biệt trong evidence.json.
- QA tra cứu/rules dùngfontlocal bundled; requestGoogleCSS trongfixture trảrỗng vì fontlocal đã có; regression landing/preview thay requestGoogleCSS bằnglookup-fonts.css local trongharness để loại network/font nondeterminism, không sửa app. Không dùng việc này để nghiệm thu font external hoặc toàn bộ pixelFigma.
- `npm run build`: PASS64modules, dealer-registration-C9ZtQ4JB.css; competition-BLpAqQAq.css không đổi. Manifest/bundle doVite sinh, không sửa tay.
- `php docs/figma/qa/render-check.php`: PASS63contracts Blade/route/preview/lookup/supplement/rules (DBdisabled).
- Node22 chạy nguyênflag cũ `--experimental-specifier-resolution=node` bị ERR_MODULE_NOT_FOUND ởimportextensionlesscustomer-address; không sửa JSproduction. Adapter loader test-only `.laravel-tools/lookup-sticky-footer/resolve-js.mjs` tự thêm.js cho relativeimportkhôngextension. `node --loader ./.laravel-tools/lookup-sticky-footer/resolve-js.mjs docs/figma/qa/lookup-validation-check.mjs`: PASSvalidation/focus/aria/normalization/retention/loading/duplicate-submit. Node phát ExperimentalWarning vềloaderAPI, không phải testfailure.
- Preflight `php docs/figma/qa/competition-db-check.php` mới PASSactualtoyota_testing/MySQLloopback/15tables/noconfigcache; bootstrap chặn migrate,guardreviewed. Featuretests dùngtransaction, không migrate/seed/reset DBthật.
- **Feature regression:** cùng11class112tests ởsharedprocess có1FAIL tạiDealerProfileTest:128 (QR dùnghostuntrusted, nhận400thay200), tái hiện cảtrước vàsauCSS; chạy riêngtestQR PASS1test/15assertions. Đây là hànhvi testphụthuộcthứtự/stateprocess, không được xem là CSSregression. Không sửa testcase/auth/TrustHosts.
- Chạy **toàn bộ cùng11class, khôngfilter/skip, với--process-isolation**: **PASS112tests/1483assertions**,39.340s, PHP8.5.11/PHPUnit12.5.40. Cáchlyprocess để loại globalstategiữgiữanguyênassertions. Không coi sharedprocessFAIL là đã sửa. Lệnh bên dưới.
- PintQAfixture `--test`, Node syntaxQA script và `git diff --check`: PASS. Không thay controllers/services/requests/models/API/schema/businesslogic hoặc test hiện có trong taskfooter.

```powershell
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result --process-isolation tests/Feature/CompetitionHomepageTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/CampaignRulesTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerProfileTest.php
```

## Screenshot trước/sau và cấu trúc cuối
- Không đổi DOM: `body.dr-site.dr-lookup-page > skiplink/header/main#dr-main/footer`. Skiplink giữfixed riêng choaccessibility, không can thiệpfooter. **Trước:**bodyblock/mainblock khônggrow, footer kếtthúcởcontentheight. **Sau:**bodyflexcolumnmin-height100vh/100dvh, maingrow1/shrink0/basisauto, header/footer shrink0. Nội dung giữnormalflow; khoảng trống viewport chuyển thành phầnmain giãn, footerởcuối.
- `qa/screenshots/lookup-sticky-footer/before/` và `after/`: default/validation/D07/D08/D10–D15/long/found/rules và home/leaderboard/lookup-live/landing/step-two/success/inactive tại6widths; evidence.json chứacomputedhtml/body/main/footer/stylesheets/geometries, probe.json chỉ đo lúcđầu.
- Đã mởxem default1440/390 before/after để xác nhận khoảngtrống dướifooter biếnmất; xemD07-390 (form bổsung dài) vàlong1440 (document cao, footer saucontent). Không tạoảnhfake để thay nội dungruntime.
- File runtime sửa duy nhất `resources/css/dealer-registration.css` (3rule); mới `docs/figma/qa/lookup-sticky-footer-fixtures.php`, `lookup-sticky-footer-visual.mjs`, trackingnày vàscreenshot/evidence. Buildoutputs sinh bởiVite. Loader ởfoldertoolignored. Các thay đổi3test/trackingregression cósẵn giữ nguyên, không thuộc tasknày.

## Những lỗi còn tồn tại
Footer ngắn chưa có shell kéo main đầyviewport; đang đo before. Chưa kết luận visual/regression.

### Kết luận sau sửa
- Lỗi footer vượt lênkhi nội dung ngắn đãsửa, cócomputed/ảnhxác nhận. Không còn lỗi footer đượcxácnhận trongstates/viewportsđãkiểmtra.
- Giới hạn cósẵn: D8Mobilefooterẩn theoM08; sharedprocessQRtestcó1FAIL phụthuộcthứtựnhưghiởtrên, isolated112PASS. Không sửangoàiphạmvi để làmxanh. Nguyên nhân globalstate cụthể củaQR chưađượctraceđầyđủ; không tựkếtluận lỗiTrustHosts runtime.
- Đây là tasklayoutbug, không đổi thiết kếpanel/Figmaassets/fonts; không đọc lạiFigmaMCP hoặc nghiệmthu pixel-perfect D7–D15toànmàn. Các thôngsố/ẩnD8mobile căn cứtrackingđãxácminh vàgiữnguyên. Không kiểmtra thiếtbịmobilethật/bànphímdynamicbars ngoàiChromeheadlessviewport, khôngchạyfullsuite.

## Task hoặc bước tiếp theo
Đo baseline realGET và fixtures; xác nhận matchedCSS; sửa tối thiểu, build, chụp before/after toàn bộ states/viewports/longvalidation và kiểm tra regression, cập nhật bằng chứng.

### Sau hoàn tất
Không còn bước bắtbuộc chofixstickyfooter. Nếu xửlýđộclập testQRorder-dependent thì tracestaticRequest/trustedhost quafixtureproduction trongtestgroup, không đổiauth/security để chiềuassertion. Giữ lịch sử/trackingregression tasktrước.
- Đã dừng devserver8771 do taskkhởiđộng sauQA; kiểmtra Gitdiff cuốiPASS (chỉCRLFwarning cósẵn). Không đểserverfixture hoặcrouteQA runtime.
