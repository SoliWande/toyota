# Sửa dấu tiếng Việt trong Hero Heading

## Mục tiêu
Xác định và sửa clipping/glyph của heading B01, giữ nội dung, Montserrat700, kích thước/line-height Figma và chiều cao Hero; kiểm tra390/768/1024/1440/1920.

## Trạng thái
DONE (cập nhật bổ sung font 2026-11-04 bên dưới)

## Hiện trạng trước khi chỉnh sửa
- Đã đọc AGENTS.md, website-competition-desktop-mobile-sync.md và các QA trước; kiểm tra Blade/CSS thực tế. Git status bị chặn dubious ownership, không đổi config Git.
- Heading dùng gradient background-clip:text trên h1, 48/56px desktop và26/32px mobile; không có height/max-height/overflow clip trên h1/copy. Artwork có overflow hidden nhưng là sibling riêng.
- Figma214:266 và150:252 đọc lại xác nhận Montserrat Bold700,48/56 và26/32; không đổi các thông số này khi chưa có bằng chứng.
- Font Montserrat hiện có chưa đủ Vietnamese; final QA trước còn fallback. Curl tải font đầy đủ từ upstream Google Fonts/GitHub bị sandbox chặn Bad access trong phiên này.
- `public/dealer-registration/montserrat-OFL.txt` có sẵn trước task; không quy file này cho thay đổi hiện tại.

## Các file source code liên quan
- resources/css/competition.css
- resources/views/competition/home.blade.php
- public/dealer-registration/lookup-fonts.css và montserrat-latin.woff2 (đọc)
- docs/figma/qa/competition-font-audit.mjs (đọc)
- docs/figma/qa/hero-heading-check.mjs (QA mới)

## Những thay đổi đã thực hiện
Tạo tracking trước khi sửa source. Đang đo computed styles, baseline/ink bounds và so ảnh tắt gradient clipping trong Chrome.

### Cập nhật 2026-11-04 — đã sửa clipping
- Nguyên nhân thực tế là vùng paint của `background-clip:text` trên h1 cắt ink phía trên line box. Canvas TextMetrics đo nét chữ đầu vượt8.648px ở48px và4.226px ở26px. Đây không phải container cha overflow hidden: h1/copy/Hero đều overflow visible, không max-height/transform; artwork overflow hidden chỉ là sibling.
- Chụp phép thử đối chứng: chỉ đổi `background:none;color:white` trong DOM thì dấu hiện đầy đủ trong khi font/line-height không đổi. Ảnh `before/solid-color-*` cho thấy rõ dấu ngã của Ỗ và dấu sắc trên Ế/Ố bị gradient cắt trước sửa.
- Sửa duy nhất CSS runtime tại `resources/css/competition.css`: mở rộng hộp vẽ gradient bằng `padding-block:.25em` và bù đúng `margin-block:-.25em`. Bleed12px desktop/6.5px mobile lớn hơn ink overhang đã đo; không thêm khoảng trống layout, không tăng line-height hoặc chiều cao Hero.
- CSS trước: `font:700 48px/56px Montserrat,Inter,sans-serif; ... background-clip:text;` (mobile26/32). CSS sau giữ nguyên các giá trị đó, thêm `padding-block:.25em; margin-block:-.25em;` cùng comment giải thích số đo.
- Không sửa Blade/text, không thay font, không bỏ dấu. h1 border box tăng để chứa nét chữ; baseline và đóng góp vào flex layout không đổi vì margin bù.
- Tạo `docs/figma/qa/hero-heading-check.mjs`, before/after screenshots và evidence JSON cho5viewport. Build Vite cập nhật manifest/CSS bundles; không sửa source của trang khác.

## Quyết định kỹ thuật quan trọng
Giữ text và thông số Figma; không bỏ dấu, không tự vẽ glyph hoặc thay font khác. Chỉ thay phạm vi typography cần thiết sau khi đo nguyên nhân thực tế.

## Kết quả kiểm thử
Chưa hoàn tất. Chưa chạy test ghi database; kiểm tra browser dùng GET.

### Kết quả mới 2026-11-04
- `npm run build`: PASS64 modules.
- `node --check docs/figma/qa/hero-heading-check.mjs`: PASS.
- `node docs/figma/qa/hero-heading-check.mjs before`: lưu computed styles, chuỗi ancestor, số đo ink/baseline và ảnh gốc/đối chứng.
- `node docs/figma/qa/hero-heading-check.mjs after`: PASS assertions ở390/768/1024/1440/1920. Ink phía trên và dưới đều nằm trong paint box; không horizontal overflow; font-weight700,48/56 hoặc26/32 giữ nguyên. Baseline và CTA x/y/height sai khác<=0.5px so trước sửa; thực tế CTA không đổi.
- Hero390 vẫn780px; các viewport còn lại680px. CTA y390=276px, y768/1024=480px, y1440/1920=492px, giống trước sửa.
- Đã xem screenshot heading1440 và390: dấu Ỗ/Ế/Ố hiện đầy đủ, dấu chấm dưới ởỘ vẫn hiển thị. Ảnh Hero đầy đủ đã chụp cả5viewport.
- CDP `CSS.getPlatformFontsForNode` trên h1 thật:32glyph Montserrat và2glyph Inter. Hai ký tự fallback là `Ộ (U+1ED8)` và `Ớ (U+1EDA)` ở dòng2, theo audit từng ký tự; đây là vấn đề font còn riêng biệt, không được che bằng kết quả clipping PASS.
- Đã kiểm tra khai báo600/700: file local chỉ đăng kýMontserrat700. Label yêu cầu600 nhưng browser dùng face Montserrat700 gần nhất và Inter cho glyph thiếu; chưa có face600 thật. Không dùng document.fonts.check để kết luận đủ glyph.
- Không chạy test DB: thay đổi chỉ là CSS paint box, không đổi route/controller/form/JS. Không migrate/seed hoặc ghi dữ liệu local.
- Lần đầu QA after lỗi syntax do dư ngoặc trong script; đã sửa, Node syntax và cả5viewport chạy lại PASS. Không coi lần lỗi là PASS.

## Những lỗi còn tồn tại
Đang xác minh clipping và tìm nguồn font đủ Vietnamese được phép truy xuất.

### Kết luận sau sửa
- **Lỗi cắt dấu đã sửa và có ảnh xác nhận.** Trạng thái tổng task giữ IN PROGRESS vì yêu cầu bổ sung Montserrat Vietnamese600/700 và loại bỏ fallback chưa đạt.
- Các nguồn hợp lệ đã kiểm tra: upstream Google Fonts/GitHub; Fontsource được maintainer cung cấp qua CDN/npm (https://fontsource.org/docs/getting-started/cdn). Curl GitHub/CDN trả Bad access; npm pack (không cài dependency/không chạy scripts) bị OS từ chối connect EACCES tới registry.npmjs.org. Không có package/font mới được tải thành công; không đưa URL font remote không kiểm chứng vào runtime.
- Không tạo glyph giả, không thay bằng font khác hoặc dùng ảnh thay text. Cần bộ Montserrat đủ glyph và weight600/700 từ nguồn được phép khi kênh tải hoạt động hoặc asset có sẵn được bổ sung.

## Task hoặc bước tiếp theo
Chụp trước/sau, sửa nguyên nhân đã xác nhận, kiểm tra5viewport/font/CTA/Hero và cập nhật kết luận.

### Bước còn lại
1. Bổ sung bộ Montserrat Vietnamese600/700 đúng nguồn; chạy lại CDP glyph audit và ảnh nếu có font mới.
2. Chỉ chuyển DONE khi font không còn fallback theo yêu cầu. Nghiệm thu clipping độc lập đã PASS.

## Danh sách bằng chứng

### Kết quả bổ sung font — 2026-11-04
- Đã tải nguyên bản Latin WOFF2 37,956 bytes, Vietnamese WOFF2 12,928 bytes và OFL 4,400 bytes vào `public/competition/fonts`. Nguồn Google Fonts v31 và Google Fonts GitHub, URL ghi ở `public/competition/FONT-NOTICE.md` đã cập nhật.
- Phân loại mạng: DNS A/AAAA PASS; HTTPS GitHub trong sandbox curl exit7 `Bad access`, chưa tới HTTP/TLS thành công. HEAD ngoài sandbox sau phê duyệt trả HTTP/2 200. Google CSS API truy cập được bằng curl cho phép, nhưng tải gstatic/GitHub trong sandbox vẫn exit7. Ba file tải thành công exit0 sau phê duyệt riêng; không tắt sandbox hay sửa policy.
- Source sửa: `resources/css/competition.css`, bốn face Latin/Vietnamese ×600/700 với range chính thức và URL local. Không sửa binary hoặc font dùng chung. Lần đầu khai báo range600–700 còn fallback khi matching cùng face700 tĩnh từ stylesheet shared; đổi sang từng weight600/700 chính xác thì PASS. Giữ lịch sử lỗi đầu, không coi đó là PASS.
- QA mới: `docs/figma/qa/competition-font-restored.mjs`; `competition-font-audit.mjs` thêm output theo env để không đè evidence cũ.
- `npm run build` cuối: PASS64 modules, đóng gói hai WOFF2.
- `node docs/figma/qa/competition-font-restored.mjs`: PASS390/768/1024/1440/1920. HTTP200, không lỗi JS/request/HTTP>=400, không overflow, ink trên/dưới nằm trong paint box. Hero780px mobile/680px còn lại và line-height32/56px giữ nguyên.
- CDP trên node thật: h1 Montserrat Bold700, label SemiBold600, không Inter. Metadata family upstream là `Montserrat Thin`; PostScript instance là `Montserrat-Thin-Bold` và `Montserrat-Thin-SemiBold`, xác nhận không render Thin. Canvas chuỗi tiếng Việt48px cho600=1231.3917px,700=1239.6957px: metrics khác nhau.
- `$env:COMPETITION_QA_OUTPUT='docs/figma/qa/screenshots/hero-heading/font-restored'; node docs/figma/qa/competition-font-audit.mjs`: PASS77 glyph Hero/label/award/CTA, JSON fallback=[]. Không dựa riêng vào document.fonts.check hoặc script exit0.
- Đã chụp full-page/Hero/heading cả5viewport và mở xem Hero1440/390: dấu ngã/sắc/chấm dưới đầy đủ, không clipping. Không đọc lại Figma trực tiếp ở lượt font này; giữ thông số đã xác minh trong lịch sử, không kết luận pixel-perfect toàn trang.
- `git diff --check` cuối lượt bị môi trường trả `Not a git repository` dù git status đầu lượt đọc được; chưa xác minh bằng Git ở bước này. Không sửa Git config. Không feature test/migrate/seed hay ghi database; browser GET local.
- SHA256 Latin `06B16DB7A969135D48D38C49183BE7FB88D4452E2A3011957C7851941F4E4879`; Vietnamese `26B2403F995ED65FB49037A6498C074D3EC3C36031A69FC368C4C2180E2C01FA`.
- Bằng chứng mới: `docs/figma/qa/screenshots/hero-heading/font-restored/`, gồm font-audit.json, font-verification.json và home/hero/heading5viewport; giữ nguyên before/after cũ.
- Kết luận supersede các mục thiếu font600/700/fallback/chưa tải được và bước còn lại ở trên: task clipping + font Vietnamese DONE, không còn lỗi trong chuỗi đã audit. Không có bước tiếp theo bắt buộc cho task font; giới hạn nghiệm thu toàn trang theo tracking website. Ký tự ngoài Latin/Vietnamese không nằm trong phạm vi.
- Kiểm tra Git bổ sung cùng lượt với workdir tường minh: `git diff --check` PASS exit0; chỉ cảnh báo CRLF ở hai file thay đổi có sẵn dealer-registration.css/welcome.blade.php. Giới hạn Git tạm thời ở mục trước đã được giải quyết; không sửa config. Đã dừng dev server do lượt này khởi động sau khi chụp ảnh.

### Tiếp tục 2026-11-04 — kiểm tra mạng và bổ sung font
- IN PROGRESS. Tiếp tục đúng task font còn mở; giữ nguyên lịch sử trước đó. Git status hiện đọc được; các thay đổi có sẵn không thuộc lần tiếp tục này.
- DNS `Resolve-DnsName raw.githubusercontent.com`: PASS A/AAAA. HTTPS HEAD trong sandbox: curl exit 7, `Failed to connect ... port 443: Bad access`, chưa có HTTP response/TLS handshake thành công.
- Cùng HEAD sau yêu cầu và nhận phê duyệt chạy ngoài sandbox: HTTP/2 200, exit 0. Không tắt sandbox hay sửa chính sách bảo mật.
- Google Fonts CSS API qua `curl.exe -L --fail` được phép: PASS. User-Agent Chrome trả WOFF2 Latin/Vietnamese cho 600/700, cùng URL cho hai weight (sẽ xác minh trục variable trước khi khai báo).
- Sẽ tải assets vào `public/competition/fonts`, giữ license gốc; chỉ sửa font-face competition, build và kiểm tra glyph/ảnh mới. Không ghi database; browser chỉ GET trang local.
- `docs/figma/qa/screenshots/hero-heading/before/`: heading/hero/solid-color đối chứng,5viewport, evidence.json.
- `docs/figma/qa/screenshots/hero-heading/after/`: heading/hero sau sửa,5viewport, evidence.json có cả font thực, font faces, overflow và ink bounds.
- Screenshot chính: `after/heading-1440.png`, `after/heading-390.png`, `after/hero-1440.png`, `after/hero-390.png`.
