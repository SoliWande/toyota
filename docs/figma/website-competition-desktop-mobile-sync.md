# Đồng bộ Website Thi đua Desktop và Mobile

## Cập nhật font đã hoàn tất — 2026-11-04
- Theo `hero-heading-vietnamese-clipping-refinement.md`: đã tải Montserrat Latin/Vietnamese600/700 và license gốc vào `public/competition/fonts`, cập nhật font-face local. DNS PASS; HTTPS sandbox curl7 Bad access, ngoài sandbox sau phê duyệt HTTP200; không sửa policy.
- Build PASS64 modules; CDP77 glyph không fallback; Hero700 và label600 thật,5viewport PASS, ảnh1440/390 đã xem không cắt dấu/overflow. Evidence mới `qa/screenshots/hero-heading/font-restored/`.
- Mục này supersede các kết luận còn thiếu font/fallback bên dưới, giữ nguyên lịch sử. Task font DONE; trạng thái nghiệm thu toàn trang vẫn theo các giới hạn Figma/icons/mobile-reference đã ghi, không suy ra pixel-perfect toàn trang.

## Cập nhật liên quan — sửa dấu Hero 2026-11-04
- Task riêng được theo dõi tại `hero-heading-vietnamese-clipping-refinement.md`; không ghi đè kết quả Final QA cũ.
- Đã xác minh `background-clip:text` cắt ink của dấu trên dòng1, sửa vùng paint bằng padding-block bù margin âm, giữ baseline/CTA/Hero/line-height. Có ảnh before/after và assertions PASS5viewport.
- Yêu cầu font không fallback vẫn chưa đạt: h1 cònỘ/Ớ dùng Inter do font thiếu glyph; tải bộ Vietnamese600/700 bị OS/sandbox chặn. Không coi clipping PASS là đóng toàn bộ typography.

## Tiếp tục Final QA — 2026-11-04 (bắt đầu)
- Yêu cầu hiện tại: kiểm tra độc lập source/evidence, đọc lại B01 hai viewport, rà mobile dưới fold, font/assets, auth/navigation và phân loại hai regression. Tiếp tục file này theo yêu cầu, giữ nguyên toàn bộ lịch sử bên dưới.
- Trạng thái vẫn IN PROGRESS; chưa sửa source tại thời điểm ghi mục này. Figma design context Desktop214:233/Mobile150:233 đã đọc lại thành công. Git status tiếp tục bị chặn dubious ownership.
- Tải font Google qua shell bị chặn `Failed to connect ... Bad access`; Python không có fontTools. Sẽ dùng Chrome CDP để xác định font thực sự render từng glyph thay vì suy ra từ CSS family.

## Mục tiêu
Thay giao diện demo của route `/` bằng B01 chính thức trên hai page Website Thi đua (B), giữ nghiệp vụ Laravel và dữ liệu hiện có.

## Trạng thái
IN PROGRESS

## Hiện trạng trước khi chỉnh sửa
- `/` → `LandingPageController` → `welcome.blade.php`, dùng `public.layout` và app.css/app.js chung.
- Demo cũ màu kem/xanh, illustration SVG tự dựng, hai số liệu, ba bước, top/award tách dọc, bốn accordion. Khác bố cục/màu/asset chính thức.
- Controller cung cấp stats dealers/points, topDealers tháng và latestAward đã công bố; không thay controller/service/routes/validation.
- Đã đọc AGENTS.md, context-recovery-2026-11-03.md, analysis-report.md, phase-1-implementation.md, landing-fidelity-refinement.md, DEALER_LANDING_PAGE_REPORT.md và source/test liên quan. Các phase D/M khách hàng không phải chuẩn cho trang `/`; không áp thiết kế đó cho B01.
- Git status bị chặn dubious ownership; không ghi cấu hình Git toàn cục. Các thay đổi dealer-call và result-panel có sẵn nằm ngoài task này.

## Danh sách frame Figma đã đọc
- File `yKmWQI6BYJ6dponEnidlsq`; metadata toàn page Desktop `207:333`, Mobile `148:252` đã đọc thành công qua MCP. Đã rà toàn danh sách B01–B17, toasts, component tương tác; chỉ B01 và menu B07 liên quan phạm vi home, không triển khai màn khác.
- Design context B01 Desktop `214:233` (1440×2834), B01 Mobile `150:233` (390×844, nội dung children kéo tới y=3591), menu khách `205:659` (390×844).
- Desktop hero `223:1584`; các section `214:277/309/331/379/457`; Mobile `150:260/294/322/359/367/438/443`.
- B01 Mobile có viewport clip 844px nhưng các section dưới tồn tại trong metadata/context; phải triển khai trang cuộn đủ nội dung.

## Bảng đối chiếu Desktop/Mobile
| Phần | Desktop Figma | Mobile Figma | Source trước sửa | Cần chỉnh |
|---|---|---|---|---|
| Header/menu | 72px, container 1200, brand/nav/login | 60px, brand/menu 40px; B07c overlay | Header 80px, nav và dropdown demo | Layout riêng home, giữ liên kết thật |
| Hero | 680px, copy 600px, banner và xe thật | 780px, artwork riêng, copy trên, CTA cạnh nhau | Hai cột, journey-art tự dựng | Artwork Figma, font Montserrat, CTA đúng từng viewport |
| Số liệu | 4 card 288px, gap16, top48 | 2×2, gap10, padding16/top24 | 2 số liệu | Thêm slot giải/thời gian; không giả số mẫu |
| Cùng bắt đầu | 4 card, gap16, top80 | 1 card chứa 4 hàng có divider, top40 | 3 cột bước | Thay cấu trúc Blade theo hai layout |
| Top/award | Cột 736/440, gap24 | Top trước, award sau, gap40 | Hai section dọc | Dùng dữ liệu thật/empty/published, giữ link lịch sử |
| Thể lệ | 2 cột 6+5 câu, top80 | 11 câu một cột, top40 | 4 câu | Native details/summary, giữ quy tắc nghiệp vụ |
| CTA cuối | Gradient đỏ, chữ 32px, nút trắng bên phải | Card dọc, chữ24, nút full | Nền kem, thêm copy demo | Thay HTML/CSS |
| Footer | 101px, logo/copy/liên kết | Copy căn giữa,104px | Brand/copy demo | Footer home riêng, không link rỗng |

## Các file source code liên quan
- resources/views/welcome.blade.php
- resources/views/components/competition/* (mới, chỉ home)
- resources/css/competition.css, resources/js/competition.js (mới)
- vite.config.js và public/build (entry mới)
- public/competition/* (asset Figma)
- docs/figma/qa/competition-* (kiểm tra/evidence mới)
- Đọc nhưng giữ nguyên: LandingPageController, LeaderboardService, PublicAwardResults, routes/web.php, public.layout/navigation và các trang khác.

## Những thay đổi đã thực hiện
Tạo tracking trước khi sửa source. MCP đọc được thiết kế; shell curl tải asset thất bại `Failed to connect ... Bad access`. Đang kiểm tra đường xuất asset qua MCP hỗ trợ sandbox, không dùng URL tạm ở runtime.

### Cập nhật 2026-11-04 — đã triển khai trên source
- `welcome.blade.php` trở thành entry include `competition.home`. Controller vẫn trả view welcome và các biến cũ; route `/` vẫn tên home.
- Thêm `resources/views/competition/home.blade.php` với hero dùng picture, 4 card số liệu, 4 bước, cột top/award, 11 accordion, CTA gradient và footer chính thức.
- Thêm component trong `resources/views/components/competition/`: `layout`, `brand`, `navigation`, `heading`, `icon`, `rules`. Component mới chỉ áp dụng trang chủ; không sửa public.layout/navigation vốn dùng chung các trang khác.
- `resources/css/competition.css`: token màu tập trung theo B01, container1200/358, desktop hero680/mobile780, type Inter và Montserrat, các layout mobile riêng, nội suy tablet. CSS có scope competition-site.
- `resources/js/competition.js`: đồng bộ aria-expanded của details menu; đóng khi chọn link/Escape/chuyển desktop; giữ focus và vòng Tab trong menu. Accordion dùng details/summary native, không JavaScript giả nghiệp vụ. Menu có fallback native khi JS tắt.
- `resources/content/competition-faq.php`: 11 tiêu đề đúng B01; nội dung mở dùng quy tắc hiện có, không suy ra nghiệp vụ mới từ prototype. Thời gian thay bằng cấu hình thật khi có. Giữ quy tắc approved, điểm theo ngày khai báo, snapshot Dealer, xử lý ảnh riêng và quyền chỉnh sửa.
- Thêm hai entry vào `vite.config.js`; build tạo competition CSS/JS và cập nhật `public/build/manifest.json`. Các bundle app/dealer-registration cũng được Vite tái sinh do Tailwind quét source đã thay, không sửa source CSS/JS của chúng.
- `public/competition/`: artwork desktop xuất riêng từ node `223:1584`, logo và icon xuất trực tiếp từ node thiết kế; mobile background tái sử dụng artwork local đã có sau khi đối chiếu trực quan B01 (không lấy layout khách hàng làm chuẩn). Không screenshot toàn trang làm nền UI; text, CTA và số liệu là HTML/Blade thật.
- Giữ stats dealers/points từ controller, topDealers, kỳ vinh danh đã publish và snapshot. Trường hợp có kỳ thật vẫn có khoảng kỳ/ngày công bố, tên/mã/điểm snapshot, link Dealer và link kỳ vinh danh. Dữ liệu demo hiện hữu do service trả về vẫn được giữ, không sửa dataset/manifest hay tạo thêm sample để đủ top3.
- Giữ CTA guest → register; auth → user.homeRoute theo role/status. Menu giữ POST logout + CSRF. Các liên kết leaderboard/awards/dealers/registration/lookup không bị đổi route.
- Footer không đưa hotline 1900 xxxx thành tel giả. Privacy thiếu URL hợp lệ là text không khả dụng; hotline chỉ render khi config hợp lệ. Không thêm phone/PII vào trang chủ.

### File mới hoặc sửa trong task này
| Nhóm | File |
|---|---|
| Entry/view | resources/views/welcome.blade.php; resources/views/competition/home.blade.php |
| Components mới | resources/views/components/competition/{layout,brand,navigation,heading,icon,rules}.blade.php |
| Presentation content | resources/content/competition-faq.php |
| Frontend | resources/css/competition.css; resources/js/competition.js; vite.config.js |
| Assets | public/competition/*.png (banner, artwork, logo, icon; provenance trong competition-design-evidence.json) |
| Test mới | tests/Feature/CompetitionHomepageTest.php |
| QA | docs/figma/qa/competition-db-check.php; competition-test-bootstrap.php; competition-visual.mjs; competition-design-evidence.json |
| Evidence | docs/figma/qa/screenshots/website-competition/*, gồm ảnh live home 6 viewport, hero/menu, ảnh tham chiếu Figma và evidence.json |
| Build | public/build/manifest.json và assets do npm run build sinh |
| Tracking | docs/figma/website-competition-desktop-mobile-sync.md |

File debug/backup cục bộ nằm trong `.laravel-tools/competition/`, không phải runtime hoặc dữ liệu production. Không thay routes/controller/service/model/schema, không sửa tracking lịch sử.

## Quyết định kỹ thuật quan trọng
- Entry/layout có scope riêng `competition-site` để không thay giao diện shared của leaderboard/awards/dealers hoặc D01 khách hàng.
- Giữ nguồn stats/top/award, CTA đăng ký hoặc homeRoute theo auth. Không tạo số liệu giả, giải thưởng hay lịch chương trình giả.
- Số 87/1126/2 trong Figma không được tự thành dữ liệu production. Hai slot đầu lấy stats; slot giải/thời gian dùng cấu hình hiện có nếu có, còn thiếu ghi rõ.
- Không đổi timezone nghiệp vụ sang Việt Nam chỉ để khớp nhãn Figma; hiển thị timezone cấu hình thực tế.

### Cập nhật quyết định và khác biệt có chủ đích
- Không có nguồn cơ cấu giải chính thức trong controller/config, nên card giải ghi `[Chờ TMV]`, không hardcode số 2. Card thời gian dùng campaign_start/end hiện có và fallback `[Chờ TMV]`. Backend cần nguồn chính thức nếu muốn hiện số giải.
- B16 Website Thi đua có nội dung khác D09 khách hàng; link thể lệ đầy đủ hiện nối `campaign.rules` đang có, chưa tạo route hay thay trang thể lệ khác phạm vi. B16 cần task riêng nếu phải thay toàn bộ đích thể lệ. Trang chủ và menu vẫn có anchor thể lệ tại home.
- B01 chỉ có thiết kế award empty; state published có thật được giữ trong card theo dữ liệu đã công bố, chưa có frame B01 published riêng để chứng nhận khớp thị giác.
- Figma MCP use_figma đọc image fill bị chặn `MCP tool call requires approval, but approval policy is never`. Không yêu cầu nâng quyền, không sửa Figma. Dùng get_screenshot với enableBase64Response/contentsOnly cho asset node vì shell không tải được URL. Logo/icons là PNG native theo thiết kế, chưa có SVG gốc trong task này.
- Đã dùng hướng dẫn skill Sites (nhánh source local), figma-design-to-code và figma-use (chỉ cho lần đọc bị chặn). Không tạo/publish Site hay deploy app.

## Kết quả kiểm thử
Chưa chạy. Không migrate/seed hay chạy test ghi dữ liệu trước khi xác minh database test cách ly.

### Kết quả mới 2026-11-04
Môi trường: PHP8.5.11, Laravel theo lock hiện có, Node18.8/Vite6.4.3, Chrome headless cài sẵn. Laravel dev server `php artisan serve --host=127.0.0.1 --port=8771`; HTTP GET `/` trả200, dùng database local hiện có để render read-only. Không POST form vào local/production.

| Kiểm tra | Kết quả |
|---|---|
| `php docs/figma/qa/competition-db-check.php` | PASS: APP_ENV testing, không cache config, loopback MySQL, config và SELECT DATABASE() đều toyota_testing; schema có sẵn15 bảng |
| Test mới qua `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result tests/Feature/CompetitionHomepageTest.php` | PASS6 tests/35 assertions: section, 11 câu/4 bước, CTA thật, không sample số liệu, dates/fallback, timezone, hotline/privacy, auth/logout+CSRF |
| Chạy đủ CompetitionHomepageTest + LandingPageTest + PublicPrivacyTest | 22 tests/1761 assertions, 20 PASS/2 FAIL; chi tiết lỗi cũ bên dưới |
| Cùng3 file, lọc riêng2 assertion lỗi cũ đã xác định | PASS20 tests/1756 assertions; không coi đây là full suite pass |
| `php docs/figma/qa/render-check.php` | PASS toàn bộ63 contract hiện có cho Dealer landing, bước1/2, success, lookup/supplement/rules/preview |
| `npm run build` | PASS64 modules; không warning ở lần build cuối |
| `node docs/figma/qa/competition-visual.mjs` | PASS6 viewport320/390/768/1024/1440/1920; không overflow/lỗi JS/request404; ảnh local tải được; menu/keyboard/Escape/anchors/accordion hoạt động; 8 đường GET liên quan trả200 |
| PHP syntax + Node syntax | PASS competition-faq.php và competition.js |
| Pint `--test` các file PHP mới | PASS |

Bootstrap QA chỉ đặt RefreshDatabaseState::$migrated=true để các regression dùng schema test có sẵn và transaction; không migrate/reset/seed database. Kiểm tra cách ly đã chạy trước. Feature test tạo fixture trên toyota_testing và rollback, không dùng toyota.

### Đối chiếu thị giác và kích thước
- Đã xem ảnh tham chiếu Figma Desktop toàn trang và Mobile hero/menu; đã xem screenshot live Desktop1440, Mobile390 hero/menu/toàn trang.
- Desktop: container1200, header72, hero680. Tọa độ section bắt đầu `72/752/996/1375/1859/2403` trùng metadata Figma; kết thúc content+spacer2733, footer101.
- Mobile390: container358, header60, hero780. Section bắt đầu `60/840/1216/1765/2475/3265` trùng metadata; steps549, results710 (top446 + award264), rules790, CTA222, footer104.
- Chrome xác nhận loaded Inter400–800 và Montserrat700 từ local font assets; không có font request failure. Điều này chưa chứng minh đủ glyph Montserrat tiếng Việt (giới hạn dưới).
- Nội dung và số lượng top khác Figma có chủ đích do dữ liệu thật. Không thêm hai Dealer giả khi local chỉ có một Dealer trong top.
- Sửa sau đối chiếu: border accordion từng hàng62px (trước đó dư5px desktop/10px mobile), xuống dòng mô tả mobile, padding/menu login y328, gap icon/menu, bảo toàn link và metadata kỳ vinh danh.

### Lỗi test đã phân loại
1. LandingPageTest query count kỳ vọng5 nhưng thực tế7. Đã chạy lại với Blade demo cũ được backup và view finder trỏ bản cũ: vẫn FAIL7vs5. SQL bổ sung là hai query đọc user để xác minh ownership demo qua DemoSubmissionScope/ToyotaRealisticDemo; không do view mới và không sửa service trong task UI.
2. PublicPrivacyTest whitelist public GET routes chưa gồm campaign.rules, các preview và lookup/step-two/success vốn đã có trước task. Task không sửa routes/web.php; assertion lỗi thời. Chưa sửa test lịch sử để mở rộng phạm vi.
3. QA test đầu tiên đếm CTA bằng chuỗi có sát tag bị lệch whitespace/icon; sửa thành đếm href register thật. QA browser ban đầu chọn container header362 thay vì content358; đổi sang wc-stats. Hai lỗi harness đã sửa và chạy lại PASS.
4. Hai request screenshot section Mobile dưới fold trả PNG1×1 vì parent B01 clip844; không dùng làm ảnh đối chiếu và đã chuyển vào `.laravel-tools/competition/failed-*`. Metadata và high-fidelity context toàn nội dung vẫn có, live screenshot trang cuộn đã chụp đủ.

## Những lỗi còn tồn tại
Đang triển khai; chưa có nghiệm thu desktop/mobile, chưa tải đủ assets.

### Cập nhật sau kiểm tra
- Source đã triển khai và các kiểm tra mới pass; tracking giữ IN PROGRESS cho nghiệm thu hình ảnh tuyệt đối. Không tuyên bố pixel-perfect hoặc toàn bộ regression suite pass.
- Chưa xuất được SVG gốc; dùng PNG Figma1× ở kích thước native. Cần SVG/2× nếu nghiệm thu độ nét trên màn hình mật độ cao.
- Montserrat local chỉ có subset Latin700; glyph tiếng Việt có thể fallback Inter và label600 dùng font matching của browser. Chưa có bộ font Montserrat Vietnamese600/700 để bảo đảm typography hoàn toàn giống thiết kế. Không đổi font giả thành chuẩn Figma.
- Mobile B01 bị clip trong Figma, screenshot phần dưới fold trực tiếp trả1×1; đã đối chiếu theo context/metadata, chưa có ảnh reference mobile cuộn toàn bộ để so pixel các section dưới.
- Hai regression assertion cũ còn FAIL như phân loại trên; chưa đổi backend hay test cũ ngoài phạm vi để làm xanh.
- Chưa có số giải/thời gian chính thức hoặc privacy/hotline được cấu hình đầy đủ; UI xử lý thiếu an toàn, không dữ liệu mock runtime.
- Link thể lệ đầy đủ dùng route hiện hữu, chưa đồng bộ trang đích theo B16 Website Thi đua. Nội dung expanded FAQ giữ nghiệp vụ đã triển khai, không tuyên bố là nội dung pháp lý mới được phê duyệt.
- Chưa chạy toàn bộ suite đăng ký/lookup ghi dữ liệu hoặc nghiệm thu B01 published bằng browser fixture; các contract hồi quy và test có scope được ghi riêng bên trên.

## Task hoặc bước tiếp theo
Lấy assets, thay Blade/layout/CSS, kiểm tra menu/accordion/links/dữ liệu, build và chụp so sánh Figma desktop/mobile/tablet; cập nhật tracking theo bằng chứng mới.

### Phần còn lại sau cập nhật
1. Bổ sung SVG và bộ Montserrat Vietnamese600/700 khi kênh tải được phép; so lại typography và độ nét.
2. Lấy reference toàn chiều dài Mobile B01 hoặc các section không bị parent clip để nghiệm thu phần dưới fold. Giữ nguyên file thiết kế hiện có trong task này.
3. Chốt nguồn cấu hình cơ cấu giải và các thông tin chiến dịch; không thay số mẫu vào production.
4. Xử lý riêng query-count/route-whitelist test cũ; thiết kế B16 và state B01 published nếu cần phạm vi đồng bộ tiếp theo.

## Final QA — cập nhật kết quả 2026-11-04

### Trạng thái nghiệm thu
IN PROGRESS. Phần triển khai, sửa lỗi có đủ căn cứ và kiểm thử đã hoàn thành; chưa đánh dấu DONE do font Montserrat còn thiếu glyph và thiếu ảnh reference Figma toàn bộ mobile dưới fold. Không tuyên bố pixel-perfect. Mục này cập nhật/supersede các kết luận tương ứng của lần triển khai trước; giữ nguyên lịch sử ở trên.

### Kiểm tra độc lập và các sai lệch đã sửa
- Đọc lại high-fidelity design context B01 Desktop214:233, Mobile150:233 và riêng CTA Mobile150:438. Đã xem lại screenshot trước sửa và ảnh live sau sửa, gồm từng section dưới fold thay vì chỉ so tọa độ metadata.
- CTA Mobile: Figma dùng Montserrat22/28px, tracking−0.22px; bản trước24/28px. Sửa22px và ngắt dòng tường minh sau “Cộng đồng lớn hơn.” theo hai paragraph Figma. Sửa gradient mobile153.052°/stop71.429% thay vì dùng gradient desktop164.291°; nút chữ đen, border1.5px #c7c7cc theo ButtonSecondary, không kế thừa chữ đỏ desktop.
- Footer Mobile: sửa11px thành12/16px và màu #8e8e93 đúng context150:444; vẫn giữ copy và max-width300.
- Footer top/ranking Desktop: metadata caption13px và CTA14px theo214:368/370; bản trước12/13px. Sửa font và border #e3e3e8 đúng thiết kế.
- Mô tả card vinh danh: sửa opacity trắng50% thành60% ở cả Desktop/Mobile theo214:378/150:366.
- Thay logo PNG tại brand/footer/menu home bằng SVG Toyota chính thức đã có `public/dealer-registration/lookup-logo.svg`; giữ kích thước native49.8315×36. Đã đối chiếu hình logo khi xem ảnh hero/menu/desktop. Không đổi hay tự vẽ logo mới; các icon PNG khác vẫn là asset Figma xuất trực tiếp ở lần trước.

### Font — bằng chứng theo từng glyph
- Thêm `competition-font-audit.mjs`, dùng Chrome CDP `CSS.getPlatformFontsForNode` trên từng ký tự thực tế của hero, label, award, CTA; không chỉ kiểm tra computed font-family hoặc trạng thái loaded.
- Trước sửa:23 vị trí fallback trên77 vị trí glyph riêng theo selector. Hero `Ỗ Ế Ố Ộ Ớ` dùng Inter; label `Ộ Đ Ồ`; award `ế ố ạ ấ`; CTA `ộ đ ồ ớ ơ ắ ầ ừ ờ ủ ạ`.
- Phát hiện `unicode-range` của stylesheet font dùng chung hẹp hơn coverage thực của WOFF2. Thêm @font-face Montserrat700 trong **entry competition riêng**, dùng nguyên file font có sẵn, bỏ giới hạn range trong declaration này. Không sửa stylesheet/font dùng chung hay glyph font.
- Sau sửa: còn11 vị trí fallback,10 ký tự khác nhau: hero `Ộ (U+1ED8), Ớ (U+1EDA)`; label `Ộ, Đ (U+0110)`; CTA `ộ (U+1ED9), đ (U+0111), ớ (U+1EDB), ơ (U+01A1), ắ (U+1EAF), ừ (U+1EEB), ờ (U+1EDD)`. Award heading không còn glyph fallback trong chuỗi đang dùng. Chưa có Montserrat600 thật cho label.
- Báo cáo máy: `final-qa/font-audit.json`; `font-audit-probe.json` ghi phép thử bỏ range trong DOM trước khi sửa source. Không dùng việc giảm fallback để tuyên bố font đã đầy đủ.
- Curl fonts.googleapis.com bị sandbox chặn Bad access. Không đổi font khác hoặc vẽ/sửa glyph giả. Cần WOFF2 Montserrat Vietnamese600/700 đúng bộ để đóng typography.
- Đối chiếu [notice/license Montserrat của Google Fonts](https://raw.githubusercontent.com/google/fonts/main/ofl/montserrat/OFL.txt): SIL OFL1.1. Thêm `public/competition/FONT-NOTICE.md`, chỉ tái sử dụng font có sẵn không sửa binary; tham chiếu full OFL text đã có trong dự án. Không áp license font lên logo/ảnh Toyota.

### Phân tích và xử lý hai lỗi regression
**LandingPageTest/query count — lỗi kỳ vọng cũ, không phải UI mới.** Trước sửa test, chạy đủ3 file lại nhận7vs5; chạy lại Blade demo cũ qua view finder trong `.laravel-tools/competition/BaselineQueryDiagnosticTest.php` cũng7vs5. Hai query bổ sung là read ownership manifest demo qua email ToyotaRealisticDemo::EMAIL, do service đã tồn tại.

Test sửa giữ nguyên ngân sách **5 query nghiệp vụ**, chỉ tách đúng SQL+binding của lookup ownership ra kiểm tra **tối đa2**; sau đó tăng workload từ8 lên16 submissions và kiểm tra lại top3/budget. Không đổi thẳng5→7, không bỏ assertion hoặc thay service để pass. Vẫn phát hiện query thừa/N+1 và ownership lookup không bị tăng theo Dealer.

**PublicPrivacyTest/public routes — whitelist cũ chưa theo các phase đăng ký/lookup.** Đã rà routes/web.php: campaign.rules; lookup/supplemented; step-two/success; hai preview local/testing đều tồn tại trước B01. Cập nhật whitelist bằng danh sách **tường minh** các URI đã review, preview chỉ thêm khi environment local/testing. Không tự lấy expected từ router, không thêm wildcard hoặc miễn kiểm tra route mới. Không sửa routes/controller/auth/throttle. Các route lookup/draft/success được kiểm tra thêm bằng66 regression tests bên dưới.

### Kiểm thử mới trong Final QA
| Kiểm tra | Kết quả mới |
|---|---|
| `php docs/figma/qa/competition-db-check.php` | PASS: SELECT DATABASE() và config đều toyota_testing, loopback, không cache config |
| `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result tests/Feature/CompetitionHomepageTest.php tests/Feature/LandingPageTest.php tests/Feature/PublicPrivacyTest.php` | **PASS22 tests/1786 assertions, không lọc bỏ test** |
| Cùng bootstrap, DealerRegistrationDraftTest + RegistrationLookupTest + RegistrationLookupStatesTest + CampaignRulesTest | **PASS66 tests/850 assertions** |
| Tổng hai nhóm PHPUnit | **PASS88 tests/2636 assertions**; không phải full suite toàn ứng dụng |
| `php docs/figma/qa/render-check.php` | PASS63 contracts hồi quy |
| `npm run build` | PASS64 modules; entry competition và Montserrat local được Vite đóng gói |
| `COMPETITION_QA_OUTPUT=docs/figma/qa/screenshots/website-competition/final-qa node docs/figma/qa/competition-visual.mjs` | PASS6 viewport320/390/768/1024/1440/1920: overflow, ảnh/font requests, container/section alignment, menu/Escape/anchor và **cả11 accordion**,8 GET routes trả200 |
| `node docs/figma/qa/competition-state-visual.mjs` | PASS36 tổ hợp6 viewport × empty/published/Sales/Dealer/Admin/pending; tên dài, điểm lớn, không clip/tràn, focus trap và markup logout |
| PHP Pint + Node syntax cho các file sửa/mới | PASS |

Feature auth test mở rộng từ Sales sang cả Dealer/Admin/pending, xác nhận CTA đi homeRoute đúng trạng thái, không lộ register cho user đã login, POST logout có CSRF. Opt-in `COMPETITION_EXPORT_QA_HTML=1` xuất HTML test riêng trong `.laravel-tools/competition/final-qa-states/` để browser kiểm tra UI. Không đăng nhập bằng tài khoản thật hoặc gửi logout vào database local.

`competition-state-fixtures.php` tạo empty/published bằng model chưa lưu, database driver bị vô hiệu hóa; browser intercept HTML **chỉ trong test**, assets và JS thực từ build. Không có QA/mock route trong app. Lần đầu fixture Award datetime thử lấy date format từ connection vô hiệu hóa nên bị chặn; đặt dateFormat tường minh riêng model fixture và chạy lại PASS. Không bật DB để né guard.

Tất cả DB feature tests chạy transaction trên schema toyota_testing có sẵn; bootstrap chặn RefreshDatabase migrate. Không migrate/seed/reset DB. Ảnh `home-*` là GET Laravel với dữ liệu local thật; ảnh `published/empty/sales/dealer/admin/pending-*` là **fixture cô lập**, không được coi là luồng auth/publish trên production.

### Screenshot và kiểm tra dưới fold
- Bằng chứng cũ không bị ghi đè; toàn bộ lần này ở `docs/figma/qa/screenshots/website-competition/final-qa/`.
- `home-1440.png`, `home-390.png`, `home-320/768/1024/1920.png`: trang live đầy đủ.
- `below-fold-steps/results/rules/cta/footer-390.png`: ảnh riêng các phần dưới fold, đã xem steps/results qua ảnh toàn trang và xem riêng rules/CTA. `hero-390.png`, `menu-390.png` có ảnh riêng.
- `published-390/1440.png` và các state auth: đã xem published390 để kiểm tra tên dài, ba Dealer/điểm nhiều chữ số và card snapshot. Không dùng dữ liệu giả này ở runtime.
- `evidence.json` và `state-evidence.json` phân biệt rõ live/fixture. Đã xác minh toàn bộ asset visible tải local, không URL MCP tạm runtime.
- Figma Mobile `150:298` thử xuất lại vẫn **1×1** dù original node358×411. High-fidelity context CTA150:438 đọc được đầy đủ nhưng render cũng1×1. Không sửa/cloning/resize file Figma để vượt clipping. **Chưa có đủ ảnh Figma phần dưới fold để so pixel toàn bộ**, nên các kết luận dưới fold dựa trên xem live + context/style/content Figma, không giả rằng reference ảnh đã đủ.
- Assertion border ban đầu mong computed1.5px đã FAIL vì Chrome DPR1 snap CSS1.5px thành1px. Source vẫn border1.5px đúng thiết kế; harness ghi rõ expected raster/computed ở DPR1, không sửa CSS làm sai thiết kế chỉ để pass.

### Các file thay đổi trong Final QA
- `resources/css/competition.css`
- `resources/views/competition/home.blade.php`
- `resources/views/components/competition/brand.blade.php`
- `resources/views/components/competition/layout.blade.php`
- `tests/Feature/LandingPageTest.php`
- `tests/Feature/PublicPrivacyTest.php`
- `tests/Feature/CompetitionHomepageTest.php`
- `docs/figma/qa/competition-visual.mjs`
- Mới: `docs/figma/qa/competition-font-audit.mjs`, `competition-state-fixtures.php`, `competition-state-visual.mjs`
- Mới: `public/competition/FONT-NOTICE.md`
- Build: `public/build/manifest.json`, competition CSS và bản copy Montserrat WOFF2 do Vite tạo.
- Tracking này và `docs/figma/qa/screenshots/website-competition/final-qa/*`.
- Không sửa controller/service/model/routes/config/database hay các trang ngoài home.

### Những điểm khớp và giới hạn còn lại
- Khớp cấu trúc/order Desktop và Mobile, artwork, container/grid/card, section spacing, các kích thước/nhãn đã đối chiếu; mọi CTA/navigation/accordion trong phạm vi có hành vi thật. Các viewport yêu cầu không overflow, có screenshot và test hành vi.
- **Chưa khớp đủ**: glyph Montserrat còn fallback và weight600; icons PNG chưa có SVG/2×; chưa có reference ảnh mobile cuộn hết; B01 published chưa có frame thiết kế riêng. Giữ nguyên giới hạn, không đánh dấu DONE.
- **Cần quyết định nghiệp vụ**: số giải/lịch chính thức, URL privacy/hotline; UTC hay chuyển chuẩn kỳ sang Việt Nam (không tự đổi backend); nội dung/route thể lệ B16 Website Thi đua thay cho đích campaign.rules hiện đang dùng D09; chuẩn visual cho B01 đã có kỳ vinh danh.
- Hai lỗi regression cũ đã xử lý bằng test đúng phạm vi sau khi có bằng chứng, thay thế kết luận “hai test còn FAIL” ở phần lịch sử. Không còn FAIL trong88 test đã chạy lần này.

### Bước tiếp theo để đóng nghiệm thu
1. Cung cấp/tải được font Montserrat Vietnamese600/700 được cấp phép; chạy lại glyph audit và screenshot nếu typography thay.
2. Có ảnh Figma mobile dưới fold không bị clip để nghiệm thu thị giác đầy đủ; không cần thay nghiệp vụ.
3. Chốt các mục nghiệp vụ trên và phạm vi B16/published state. Sau đó cập nhật cùng tracking và chỉ chuyển DONE khi đủ bằng chứng.
