# Phase 3B — Full campaign rules D09/M09

Ngày 03/11/2026. Đã triển khai trang HTML, mục lục/anchors, link đăng ký/tra cứu và
tests. **Nội dung chính thức chưa thể công bố**: D00 xác nhận đây là nội dung mẫu.
Local/testing hiển thị đủ bản tham khảo có cảnh báo; production chỉ hiển thị
thông báo chờ thể lệ được phê duyệt. Không deploy, commit, push hoặc migration.

## Khảo sát và Figma

Đã đọc Phase 3A.2, Phase 3A, Phase 2B, Phase 2C.4C và đối chiếu source routes,
Dealer controllers, draft/final/receipt, layout, D01/D02/D04, lookup states,
CSS/JS, consent và cấu hình terms/privacy. Source là căn cứ chính.

File `yKmWQI6BYJ6dponEnidlsq`, MCP session
`f1d82e8f-9ddf-4e80-a34e-5b4777240c60`. Metadata desktop page `207:332`, mobile
page `57:801` xác nhận chính xác các node trước khi lấy design context:

| Frame | Node | Kích thước |
| --- | --- | --- |
| D09 — Thể lệ đầy đủ | 213:1447 | 1440 × 1578 |
| M09 — Thể lệ đầy đủ | 57:963 | 390 × 844, nội dung dài hơn viewport |
| D00 — Mục lục prototype | 213:1650 | 1440 × 632 |
| Link đầy đủ trên D01 | 209:422, text 209:423 | 151 × 20 |
| Link đầy đủ trên M01 | 99:521, text 99:522 | 159 × 21 |

Đã gọi get_design_context và get_screenshot D09/M09/D00; đọc context hai link.
D00 text node **213:1652** ghi: “Bấm vào một màn để mở. Nội dung là nội dung mẫu.”
[Evidence JSON](qa/phase-3b-figma-evidence.json),
[D00 reference](qa/screenshots/phase-3b/figma-D00.png),
[D09 reference](qa/screenshots/phase-3a/figma-D09.png),
[M09 reference](qa/screenshots/phase-3a/figma-M09.png).
Đây là ảnh thiết kế, không phải browser screenshots; không dùng ảnh nguyên trang
thay HTML hoặc temporary Figma URL làm runtime asset.

## Nội dung trích xuất và cần phê duyệt

Toàn bộ nội dung body, đúng thứ tự và nguyên văn D09/M09, nằm trong
`resources/content/campaign-rules.php`. Tiêu đề: “Cộng đồng Toyota Veloz & Hilux”.
Phần giới thiệu chỉ có dòng “Áp dụng từ 01/11/2026 đến 31/01/2027”, không có đoạn
giới thiệu khác. Có đúng 8 mục sau, không tự thêm mục giải thưởng/thành tích:

| Mục | Desktop body node | Mobile body node | Nội dung / điểm cần xác nhận |
| --- | --- | --- | --- |
| 01 Thời gian | 213:1487 | 57:975 | 01/11/2026–31/01/2027 là ngày mẫu; không dùng làm campaign dates chính thức |
| 02 Đối tượng tham gia | 213:1496 | 57:978 | Veloz/Hilux/Fortuner/FJ tại Việt Nam; mỗi chủ xe một lần. Allowlist Dealer phù hợp source, nhưng không suy ra copy này đã được phê duyệt hoặc Sales cũng chỉ có bốn dòng xe |
| 03 Cách tham gia | 213:1505 | 57:981 | QR/link Dealer → thông tin → Group đúng tài khoản → trả lời câu hỏi; copy vẫn thuộc mẫu D00 |
| 04 Điều kiện để được xác nhận | 213:1514 | 57:984 | Phone/plate khớp câu trả lời; mỗi phone/plate/Facebook tính một lần; gọi xác nhận ngẫu nhiên. Cần chốt cách diễn đạt: phone/plate chiếm identity khi pending/approved, Facebook approved identity là trục khác |
| 05 Quyền lợi thành viên | 213:1523 | 57:987 | Ưu đãi dịch vụ, sự kiện/chuyến đi, hỏi kỹ thuật viên: chưa có phê duyệt phạm vi/quyền lợi |
| 06 Kiểm tra và xử lý | 213:1532 | 204:1250 | Gọi xác nhận, huỷ đăng ký sai và trừ điểm Dealer. **Không tương đương source hiện tại**: không tự bổ sung huỷ approved/reversal hoặc trừ thành tích |
| 07 Bảo mật thông tin | 213:1541 | 57:990 | Xác nhận chủ xe/chăm sóc khách hàng, không chia sẻ bên thứ ba: là tuyên bố cần đơn vị phụ trách phê duyệt, không coi là privacy policy đã có hiệu lực |
| 08 Liên hệ | 213:1550 | 57:993 | `1900 xxxx` là placeholder; giờ 8:00–17:00 thứ 2–thứ 7 cũng chưa được xác nhận |

Không có giải thưởng cụ thể, công thức leaderboard/tháng hoặc SLA duyệt trong
D09/M09. Không tự bổ sung. CTA: “Đăng ký tham gia”, desktop dưới mục lục, mobile
cuối bài. Không dùng hotline placeholder làm tel link. Không đưa thông tin Dealer
An Sương mẫu hoặc tuyên bố đơn vị vận hành mẫu của footer vào trang runtime mới.

Mọi đoạn body trên đều được coi là **unapproved sample**, kể cả phần phù hợp với
source. Local/testing có banner rõ “không phải thể lệ chính thức”; production và
mọi environment khác không nhận body/date/hotline mẫu. Query `sample/preview`
không mở được bản mẫu ở production. Không có env switch tự biến mẫu thành chính
thức. Cần copy được khách hàng phê duyệt và source review riêng trước công bố.

## Route và layout

```text
GET /chuong-trinh/the-le   campaign.rules
```

Không chọn `/the-le`: route Dealer `/{slug}` hiện tại có thể sở hữu chính slug
này; Dealer slug immutable cả model và DB trigger. Chưa đọc database thật để
chứng minh slug chưa tồn tại. URL hai segment không chiếm canonical URL/QR nào,
không cần đổi reserved slugs hoặc dữ liệu. Test tạo Dealer tên The Le sinh slug
the-le và xác nhận profile + redirect QR/legacy destination vẫn đúng.

Controller không đọc Dealer/submission, không nhận return URL, không đọc/ghi draft
hoặc xác nhận consent. Không yêu cầu auth. Dùng chung Dealer registration layout
với props rules/description và dealer nullable cho trang độc lập. Header dùng logo
và CTA chọn đại lý thật; không bịa Dealer context hoặc generic lookup endpoint
không tồn tại. Mobile có back link về trang chủ; CTA đến Dealer directory.

Desktop: container 1200, padding top48/bottom100, sidebar320/gap24, document856
padding8×48/radius28. Heading Inter800 40/46 tracking−0.8; body Inter400 16/26,
section heading700 20/28; numbering Montserrat700. Mobile: margin20, top24,
heading Inter700 24/1.3, sections20, body15/1.55, title16/1.4, divider và CTA52.
Sidebar ẩn mobile; tại 768 dùng tablet grid240 + cột nội dung co giãn. Dùng minmax/
min-width và wrap, không hardcode chiều cao bài hoặc cắt nội dung tại844px.

HTML semantic h1/h2/article/section/nav/ol, native anchors và tabindex−1 tại đích,
skip link/focus styles hiện có; không thêm JavaScript. D09 chỉ load Dealer CSS,
không load JS đăng ký. Shared footer và privacy link giữ nguyên chức năng.
Banner sample và production pending view khác Figma có chủ đích để không công bố
thể lệ chưa phê duyệt. Không có thay đổi typography Admin/Sales.

## Link audit

| Vị trí | Xử lý |
| --- | --- |
| D01/M01, Public Dealer landing “Xem thể lệ đầy đủ” | rules-link dùng route campaign.rules |
| Footer Dealer dùng chung D01–D06/D07–D15 | “Thể lệ” dùng cùng named route |
| D02/D03 bên cạnh vùng consent | Thêm link thể lệ riêng **ngoài label consent**, không sửa checkbox hoặc link pháp lý cũ |
| D04, các lookup states | Nhận link footer qua layout; không thay status resolver/workflow |
| Consent “Xem điều khoản” | Giữ terms_url hiện có: đó là data-processing notice, chưa có căn cứ đồng nhất với D09 |
| Footer “Bảo mật thông tin” | Giữ privacy_url, không gán sang thể lệ |
| Public navigation “Thể lệ” → home#the-le | Giữ: section hiện hữu giải thích nghiệp vụ Sales/điểm/xếp hạng, không phải copy thể lệ khách D09; là anchor hợp lệ, không phải href="#" placeholder |

Tất cả link thể lệ mới từ registration/lookup mở tab mới, rel noopener noreferrer
và có screen-reader hint. D09 footer tự liên kết same-tab. Không submit form,
không reset nhập liệu hoặc ghi PII/return URL lên URL. Khi đóng tab thể lệ,
form cũ vẫn ở tab ban đầu; draft/server TTL không đổi. Điều này không tạo cơ chế
lưu mới cho dữ liệu DOM chưa submit; không tuyên bố reload form sẽ giữ mọi dữ
liệu chưa từng được lưu. Các privacy/consent URL chưa cấu hình vẫn dùng trạng
thái unavailable hiện có, không tự sáng tác trang pháp lý để thay thế.

## Consent/version audit

- `config('dealer.consent_version')` vẫn **dealer-qr-2026-10-20**.
- Notice vẫn là chấp thuận xử lý họ tên/phone/địa chỉ/thông tin xe cho đăng ký,
  xác minh và ghi nhận khách hợp lệ; không được tự đổi thành đồng ý mọi mục D09.
- Legacy DealerRegistrationRequest và final controller vẫn required + accepted
  cho consent. Chỉ final/legacy insert thành công ghi consented_at và version
  hiện hành. GET rules không có quyền ghi các fields này.
- Không sửa validation, request/controller intake, historical consent hoặc
  version config. Không thêm checkbox, migration, backfill hoặc auto-consent.
- File nội dung mẫu có version=NULL; đây không phải registration consent_version.

Đề xuất sau khi có copy chính thức: quản lý tài liệu tĩnh được duyệt bằng version
bất biến, effective date, checksum và bằng chứng phê duyệt riêng. Archive phiên
bản cũ; xác định trước consent hiện tại đang chấp thuận document nào. Nếu muốn
consent bao gồm campaign rules, phải duyệt lại wording/document mapping và
version tương lai; không sửa giá trị của hồ sơ lịch sử. Chưa triển khai thay đổi đó.

## Tests và build

- Preflight xác nhận actual connection/database **toyota_testing**. Không chạy
  migrations, migrate:fresh, RefreshDatabase hoặc đọc/ghi toyota/production.
- CampaignRulesTest: **7 tests / 130 assertions PASS**: public200, HTML8 mục,
  named route/anchors, production content gate, the-le slug compatibility,
  D01/D02/D04/lookup links, legal link distinction, no SQL GET, draft values/id/
  TTL, historical consent bất biến và xem trang không thay đồng ý consent.
  Chạy lại cuối sau cập nhật meta/QA vẫn PASS 7/130. Post-check read-only xác nhận
  submissions, verification states/audits, receipts, moderation reviews, users,
  dealers trên toyota_testing đều 0 rows; không còn fixture do lượt test tạo.
- Regression chọn lọc **261 tests / 2.002 assertions PASS**, 8,616 giây, 88 MB.
  Targets: CampaignRulesTest, RegistrationLookupStatesTest, RegistrationLookupTest,
  DealerRegistrationFinalTest, DealerRegistrationDraftTest,
  DealerRegistrationSuccessPresentationTest, DealerRegistrationStepTwoValidationTest,
  RegistrationBusinessIntegrationTest, ControlledDuplicateApprovalTest,
  DemoIsolationTest, SubmissionIntegrityUpdateTest và tests/Unit. Không tuyên bố
  chạy toàn bộ legacy Feature suite có destructive database traits.
- 59 Blade render checks PASS, gồm 5 checks mới D09/sample/production gate/link.
- Bốn frontend harness Step One/Two/lookup/supplement PASS; không sửa JS.
- npm run build PASS: Vite6.4.3, 62 modules. Dealer CSS BX6FM2xk, JS CRiDIgFV;
  CSS chứa rules layout; D09 không yêu cầu script runtime.
- Pint toàn repo PASS; 9 production preflight checks PASS với DB disabled;
  22 PNG assets PASS và không temporary Figma URLs trong views.
- diff --check trên files Phase3B PASS. Global diff --check phát hiện trailing
  whitespace ở public/hilux-brothers/index.html:888, là file ngoài phạm vi không
  chỉnh sửa trong lượt này; giữ nguyên thay đổi đó của workspace.

Browser automation connector không có. **390/768/1440px, computed font loading,
horizontal overflow, focus/keyboard/scroll geometry và screenshot comparison:
NOT VERIFIED**. Không tái dùng ảnh Figma làm bằng chứng browser. Manual QA: mở
trang ở ba widths, đi Tab/Enter qua mục lục/CTA, kiểm tra không scroll ngang,
Inter/Montserrat thực tải, tab đăng ký giữ dữ liệu khi mở/đóng thể lệ; kiểm tra
production-mode chỉ có pending copy. Fonts vẫn kế thừa Google Fonts như trước,
chưa self-host hoặc đóng blocker font/browser của Phase2C.4C.

## Files tạo/chỉnh lượt này

Tạo:

- app/Http/Controllers/CampaignRulesController.php.
- resources/content/campaign-rules.php.
- resources/views/campaign/rules.blade.php.
- resources/views/components/dealer-registration/rules-link.blade.php.
- tests/Feature/CampaignRulesTest.php.
- docs/figma/qa/phase-3b-figma-evidence.json.
- docs/figma/qa/screenshots/phase-3b/figma-D00.png.
- docs/figma/phase-3b-full-rules.md.

Chỉnh: routes/web.php; Dealer registration layout, landing-content,
step-two-content; resources/css/dealer-registration.css;
docs/figma/qa/render-check.php. public/build rebuild (ignored).
Working tree còn nhiều thay đổi từ các phase khác; không gán toàn bộ diff cho 3B.

## Cần khách hàng/operator xác nhận

1. Phê duyệt copy toàn bộ 8 mục, đặc biệt dates/benefits/cancellation & points/
   privacy/hotline. Chưa coi việc hoàn thiện HTML là đủ quyền publish chính thức.
2. Tách rõ document thể lệ và data-processing consent; duyệt mapping/version nếu
   muốn đổi nghĩa consent cho đăng ký tương lai.
3. Chấp nhận URL an toàn hai segment. Nếu bắt buộc /the-le, phải audit slug trên
   target được xác nhận và có phương án canonical QR compatibility trước khi đổi.
4. Browser/font/responsive acceptance. Không tự đóng release blockers trước đó.

Không sửa Admin/Sales/Dealer business rules, approval, Group progress, leaderboard,
APP_KEY, .env, lịch sử hoặc schema. Không commit, push hay deploy.
