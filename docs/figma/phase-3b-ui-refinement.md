# Phase 3B UI final refinement — Strict Figma fidelity

## Kết quả hiện hành — 03/11/2026

Phần này thay thế các kết luận cũ bên dưới về banner mẫu, production gate và CSS
hover. Theo chỉ đạo mới, Figma là nguồn copy/UI trong giai đoạn build; mọi lưu ý
phê duyệt chỉ ghi trong Markdown. Không deploy, commit, push hoặc migration.

### Đối chiếu Figma

File yKmWQI6BYJ6dponEnidlsq, session
3b799439-1104-40fb-957e-442bc2b76dcb. Đã lấy lại design context kèm screenshot:
D09 **213:1447**, M09 **57:963**, sidebar **213:1478**. Sidebar selected row
**213:1480**, label **213:1482**, numbering **213:1481**; các hàng khác bắt đầu
213:1489. Nội dung tám mục giữ nguyên resources/content/campaign-rules.php.

Frame cho thấy hàng đầu có fill **#fff1f2**, label Inter600 **#111**, số Montserrat700
**#eb0a1e**. Hàng mặc định không có fill, label Inter500, số **#8e8e93**; radius12,
padding10×12, gap12. Không nhận được hover/focus variant riêng từ design context.
Do đó dùng hover tối giản theo token, không coi nền selected là hover.

### Thành phần đã xóa khỏi render

- Toàn bộ aside dr-rules-notice “Bản tham khảo thiết kế — chưa được phê duyệt”,
  cả đoạn giải thích phía dưới và CSS của nó.
- Hậu tố “(nội dung mẫu)” trong dòng thời gian.
- Nhãn aria chứa “mẫu chưa phê duyệt”; thay bằng tên nội dung từ Figma.
- Nhánh Blade tạo panel “Thể lệ chính thức đang được cập nhật”, đoạn hướng dẫn
  liên hệ và CTA “Xem các đại lý Toyota”; xóa CSS dr-rules-pending.
- Mô tả meta có lời giải thích cập nhật/phê duyệt; dùng tiêu đề tài liệu.
- Status hint “chưa khả dụng” tại footer **riêng D09** nếu privacy URL chưa có:
  hiển thị đúng label Figma, không thêm note hoặc tạo URL giả.

Không ẩn các notices bằng CSS; markup/conditional render đã bị xóa. Blade đọc
static document khi controller không truyền dữ liệu, nên mọi environment có cùng
HTML Figma, không có production placeholder page. Không sửa controller ngoài
phạm vi: biến sample cũ không còn được view dùng để quyết định UI. Điều này không
đánh dấu metadata nội dung là approved hoặc thay đổi consent/version.

Header D09 đổi caption CTA về “Đăng ký ngay” theo Figma; footer D09 dùng đúng
copy “Chương trình của Toyota Việt Nam · Vận hành bởi Ô tô Sài Gòn” và text
“Hotline 1900 xxxx” có trong thiết kế, không biến placeholder thành số tel giả.
Các nhánh này chỉ áp dụng rules=true, không đổi D01–D08/D10–D15. M09 giữ header
64px, title16/24, back hit area44px và gap4; footer desktop không xuất hiện trên
mobile như M09. Không thêm nút quay lại mới: back control đã có trong M09.

### Hover, selected và focus trước/sau

Trước:

```css
.dr-rules-toc ol a:hover,
.dr-rules-toc ol a:focus-visible { background:#fff1f2; }
```

Sau (selectors thực tế vẫn scope dưới dr-site):

```css
.dr-rules-toc ol a:hover { color:var(--dr-red); }
/* Selected row only: background #fff1f2; label #111/600; number Toyota red. */
```

Selected dùng native anchor hash + CSS :target/:has: không có hash section thì
chọn mục01 như frame; vào #rules-... thì chỉ hàng tương ứng được chọn. Không dùng
chung selector hover/selected, không thêm animation/transition hoặc JS để giả
selected. Không gán aria-current tĩnh sai cho mục01 khi hash khác. Keyboard focus
giữ outline3px Toyota red/offset4px của design system, không đổi fill chỉ vì focus.
Cursor là hành vi link native. Background hồng còn lại chỉ thuộc selected như
Figma, không còn là hiệu ứng hover của mọi hàng.

Các sửa numbering/CTA từ lượt trước giữ nguyên: number nowrap/flex:none/min2ch,
body flex:1/min-width:0, một content CTA duy nhất trong aside, desktop sidebar và
mobile cuối bài. Không có bản mobile CTA được render thêm hoặc che bằng CSS.

### Kiểm thử cuối

- Actual DB được xác minh **toyota_testing**; không migration hoặc dữ liệu thật.
- CampaignRulesTest **7 tests /155 assertions PASS**: absence của notices, full
  Figma copy ở mọi environment, đủ8 mục/số/anchors, một content CTA, no SQL GET,
  draft/TTL, historical consent và validation consent giữ nguyên.
- Regression chọn lọc **261 tests /2.027 assertions PASS**, 7,089 giây, 88MB:
  CampaignRulesTest, RegistrationLookupStatesTest, RegistrationLookupTest,
  DealerRegistrationFinalTest, DealerRegistrationDraftTest,
  DealerRegistrationSuccessPresentationTest, DealerRegistrationStepTwoValidationTest,
  RegistrationBusinessIntegrationTest, ControlledDuplicateApprovalTest,
  DemoIsolationTest, SubmissionIntegrityUpdateTest và tests/Unit.
- **61 Blade render checks PASS**, gồm không còn panel/suffix, không có alternate
  approval CTA, đúng8 sections và một CTA; các states khác tiếp tục render đúng.
- Production CSS cascade checks PASS tại **390/768/1024/1440px**; thêm kiểm tra
  hover không có fill/transition, selected đủ8 anchor targets có fill/font/number
  color riêng. Script là bộ xét selectors hữu hạn, không thay browser engine.
- npm run build PASS, Vite6.4.3 /62 modules; Dealer CSS **BbzpWk2s**, JS CRiDIgFV
  không đổi. Pint toàn repo PASS; diff --check trong scope sửa PASS.

### Khác biệt và điều chưa xác minh

- **Browser QA NOT VERIFIED** tại390/768/1440 (và1024): không có browser connector;
  Chrome headless đã thử ở lượt UI trước, lỗi GPU và không tạo screenshot kể cả
  about:blank. Không có ảnh app mới hoặc tuyên bố pixel-perfect/không overflow
  dựa trên đo thực. CSS/render checks không xác nhận font load/glyph, crop,
  scrollWidth, CTA occlusion hoặc native anchor/focus geometry.
- D09 truy cập trực tiếp không có Dealer context. Header không giả tên Toyota An
  Sương/chip/lookup link của Dealer demo trong Figma; CTA đi Dealer directory.
  Chưa có cơ sở để thêm context đó trong phạm vi UI-only, không đọc DB từ view.
- Back icon mobile vẫn dùng control/glyph từ layout hiện hữu, chưa nghiệm thu
  đúng SVG variant và kích thước render; fonts vẫn Google Fonts, chưa self-host.
- Default selected/anchor CSS dùng :has; chưa browser-test target compatibility
  hoặc screen-reader traversal. Focus outline giữ source cũ, chưa đo trên browser.
- D00 gọi copy là nội dung mẫu; ngày/benefits/reversal/privacy/hotline vẫn cần
  owner xác nhận trước release. Theo yêu cầu hiện tại, chỉ ghi tại Markdown này,
  không hiển thị warning, suffix hoặc approval notice trên trang. Không thay
  backend để thực hiện thêm quy định huỷ/trừ điểm từ copy Figma.

### Files thay đổi lượt final

- resources/views/campaign/rules.blade.php.
- resources/views/components/dealer-registration/layout.blade.php: chỉ nhánh D09.
- resources/views/components/dealer-registration/link.blade.php: statusHint
  mặc định true, D09 truyền false; behavior các trang khác giữ nguyên.
- resources/css/dealer-registration.css: selectors D09, không đổi base buttons.
- tests/Feature/CampaignRulesTest.php.
- docs/figma/qa/render-check.php; docs/figma/qa/rules-css-check.mjs.
- docs/figma/phase-3b-ui-refinement.md; public/build rebuild (ignored).

Không sửa resources/content/campaign-rules.php, controller, routes, database,
migrations, consent/version, Admin/Sales/Dealer, lookup/Group hoặc leaderboard.

---

## Lịch sử lượt sửa trước — Layout, numbering và CTA

Ngày 03/11/2026. Chỉ sửa giao diện D09/M09. Không đổi nội dung mẫu/production
content gate, consent/version, routes, registration JS hoặc backend nghiệp vụ.
Không migration, commit, push hoặc deploy.

## Khảo sát và Figma

Đã đọc Blade campaign.rules, layout Dealer registration cha, rules-link/component
liên quan, CSS chung và CSS rules, controller/route/JS và tests. D09 không load
registration JS; không có JS sinh CTA hoặc thay layout.

Figma file yKmWQI6BYJ6dponEnidlsq; session
43afd470-ee0c-4b05-899e-e1a9d70c6e75. Đã đọc lại get_design_context có ảnh và
get_screenshot cho D09 **213:1447**, M09 **57:963**. D09 desktop1440:
container1200, sidebar320, gap24, main856, padding main8×48, section28/gap24;
number Montserrat700 20/28, title Inter700 20/28. M09 mobile390:
margin20, section padding20, số đơn nằm cùng heading, CTA cuối bài full-width52.
Giữ typography/nội dung/notice đã có; không dùng ảnh nguyên trang làm UI.

Đối chiếu source với mô tả lỗi thực tế người dùng và ảnh thiết kế. Không có ảnh
render ứng dụng mới được tạo thành công; không tuyên bố đã so ảnh browser thực
tế hoặc pixel-perfect. Ảnh Figma không thay cho evidence browser.

## Nguyên nhân và cách sửa

### 1. Số 04–07 bị tách dòng

`.dr-full-rules` áp dụng overflow-wrap:anywhere xuống toàn bài. Section dùng flex;
span số không có flex-shrink/width/white-space guard, nên mặc định flex-shrink=1.
Cột body cũng không có flex:1/min-basis phân bổ phần còn lại. Khi nội dung cạnh
bên có intrinsic width lớn, span số có thể co tới một chữ số; inherited anywhere
cho phép ngắt giữa 0 và 4/5/6/7. Main grid bên ngoài đã có minmax(0,1fr), nhưng
không bảo vệ được span flex ở bên trong.

Trước:

```css
.dr-rules-document section { display:flex; gap:24px; }
.dr-rules-document section>div { min-width:0; }
.dr-rules-number { /* chỉ typography; không có shrink/wrap guard */ }
```

Sau, chỉnh ngay selector gốc, scope Dealer rules:

```css
.dr-rules-document section>div { flex:1; min-width:0; }
.dr-rules-number {
    flex:none;
    min-width:2ch;
    white-space:nowrap;
    overflow-wrap:normal;
    word-break:normal;
}
```

Sidebar numbering có cùng guard. Mobile inline-number cũng nowrap/normal;
mobile vẫn hiển thị 1.–8. trong heading như M09, không thêm số 01 bên cạnh.
Không hack bằng khoảng trắng/ký tự đặc biệt, không cắt clip/ẩn overflow để che số.
Giữ overflow-wrap:anywhere cho nội dung dài, chỉ loại nó khỏi numbering.

### 2. CTA thừa dưới góc trái

Blade trước đây sinh hai content links: dr-rules-join trong sidebar và
dr-rules-mobile-join sau split. CSS ẩn mobile link bằng selector
`.dr-site .dr-rules-mobile-join`, nhưng base `.dr-site .dr-button` nằm phía sau,
cùng specificity và đặt display:inline-flex; do đó nó thắng ở desktop. Kết quả
link mobile hiện sau split ở góc trái, thêm chiều cao/khoảng trắng cuối bài.
Không phải footer, include lặp, registration sticky CTA hoặc JavaScript.

Đã **xóa hẳn anchor dr-rules-mobile-join khỏi Blade và CSS của nó**. Chỉ có một
CTA trong main, thuộc sidebar aside chứa nav. Article đứng trước aside trong
DOM; desktop grid-template-areas đặt aside bên trái/article bên phải, mobile
block flow tự đặt chính CTA đó sau bài. Mobile chỉ ẩn mục lục, không ẩn CTA;
aside bỏ card padding/background và CTA full-width52px. Không cần display:contents,
không có hai bản CTA để toggle bằng CSS/JS. Reading order mobile phù hợp vị trí
cuối bài; anchors desktop vẫn trỏ đúng section.

Header CTA chung vẫn giữ nguyên, như header trong Figma. Đây là CTA điều hướng
chung, không phải bản content bị lặp. Không chỉnh shared layout hoặc CTA D01–D06.

### 3. Layout và khoảng trắng

Outer desktop split320 + gap24 + minmax(0,1fr) vốn đúng D09, không cần redesign
hoặc thêm wrapper. Vấn đề phát sinh bên trong: span co, body chưa nhận phần rộng
còn lại, và mobile link thừa được render thành element ngoài split. Đã sửa cả
DOM và flex allocation thay vì thêm override ở cuối stylesheet.

Grid areas định danh vị trí sidebar/document, align-items:start giữ top alignment;
giữ desktop main padding8×48 và section28. Tablet768 dùng sidebar240/gap20/main
padding24;1024 trở lại desktop sizing. Mobile main padding0 và section20.
Không có bằng chứng sticky trong design context; bỏ position:sticky/top do bản
triển khai trước tự thêm, giữ sidebar normal-flow top aligned theo frame tĩnh.
Giữ bottom spacing100 desktop/48 mobile, không thêm margin bù cho CTA đã xóa.
Banner mẫu chưa phê duyệt vẫn giữ, nên chiều cao không bằng nguyên frame Figma.

## Responsive checks

| Width | Bố cục theo stylesheet | Content width tính từ CSS, không phải đo browser |
| --- | --- | --- |
| 390 | Mobile block, nav mục lục ẩn, một CTA cuối bài | 350 |
| 768 | Sidebar240, gap20, main co giãn | Container704 / main444 |
| 1024 | Sidebar320, gap24 | Container960 / main616 |
| 1440 | Sidebar320, gap24 | Container1200 / main856 |

`rules-css-check.mjs` đọc **production bundle qua manifest**, parse cascade và
media query, xét specificity/source order cùng base button rule. PASS ở cả bốn
widths: number nowrap/non-shrinking/normal word-break, đúng split, CTA không bị
hidden, mobile CTA100%/52px/margin0, desktop đúng grid areas và nav còn hiển thị.
Đây là kiểm tra cascade hữu hạn cho các selectors liên quan, **không phải computed
style hoặc layout engine đầy đủ**; không dùng nó để tuyên bố không overflow thực.

Chrome headless được thử lại với profile riêng dưới
storage/framework/testing/rules-refinement-browser, about:blank và screenshot
390×844. Tiến trình kết thúc nhưng không tạo probe.png; stderr có GPU process
exit−1073741790 và lỗi encryption/network của môi trường. Không có browser
automation connector hoặc Playwright/Puppeteer đã cài. Không đổi sandbox/profile
người dùng để vượt lỗi. Không có ảnh ứng dụng390/1440 mới.

**NOT VERIFIED:** hình học thực tế, scrollWidth/overflow, font load/glyph,
CTA occlusion, native anchor focus/scroll và screenshot tại390/768/1024/1440.
Manual acceptance cần mở từng width, kiểm tra đủ8 số, một content CTA đúng vị trí,
Tab/Enter mục lục và không scroll ngang; mobile kéo đến cuối bài để kiểm tra CTA.

## Tests/build

- Xác minh actual DB toyota_testing trước test; fixtures DatabaseTransactions và
  rollback, không migration/RefreshDatabase hoặc dữ liệu toyota/production.
- CampaignRulesTest + DealerRegistrationDraftTest +
  DealerRegistrationSuccessPresentationTest: **20 tests / 322 assertions PASS**.
  Regression mới kiểm tra đúng một content CTA trong HTML, không mobile clone,
  article đứng trước sidebar trong DOM, đủ số01–08 và anchors/consent/draft cũ.
- Blade render checks: **61 PASS**, gồm hai assertions mới CTA duy nhất và reading
  order/sidebar ownership. Content gate và D01/D02/D04/lookup render vẫn PASS.
- `node docs/figma/qa/rules-css-check.mjs`: PASS390/768/1024/1440 như giới hạn trên.
- `npm run build`: PASS, Vite6.4.3 /62 modules. CSS
  dealer-registration-BRKNOsva.css; JS CRiDIgFV giữ nguyên.
- Pint toàn repo PASS. diff --check trên files scope sửa PASS.

## Files sửa/tạo

- resources/views/campaign/rules.blade.php — xóa CTA clone, một sidebar aside/nav,
  article trước sidebar trong reading order.
- resources/css/dealer-registration.css — sửa selectors rules gốc, numbering,
  grid areas và responsive CTA; không chỉnh base button hoặc shared layout.
- tests/Feature/CampaignRulesTest.php — DOM regression cho CTA và numbering.
- docs/figma/qa/render-check.php — kiểm tra CTA/render order.
- docs/figma/qa/rules-css-check.mjs — kiểm tra cascade production bundle.
- docs/figma/phase-3b-ui-refinement.md — báo cáo này.
- public/build rebuild (ignored); browser diagnostic dưới storage (ignored).

Không chỉnh resources/content/campaign-rules.php, consent/version, controller,
route, CustomerSubmission, Admin/Sales/Dealer, lookup hoặc Group workflow.
Working tree có các thay đổi từ lượt khác; không gán toàn bộ diff cho lượt sửa UI.
