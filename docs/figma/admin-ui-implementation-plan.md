# Kế hoạch triển khai UI Admin C01–C08

## Mục tiêu
Dùng [inventory](admin-ui-inventory.md) làm đặc tả UI, câu chữ và node ID cho từng task nhỏ. Tích hợp Laravel hiện có, giữ dữ liệu, quyền và nghiệp vụ. Task này chỉ phân tích và tạo đúng hai file Markdown.

## Trạng thái
**BLOCKED phần xác minh prototype; đã hoàn tất kiểm kê tĩnh và lập roadmap.**
Đã đọc 36/36 frame trên page159:316: 8 màn chính, 4 modal, 18 toast và 6 nhóm tab gồm 20 variant có tên. Đọc thêm Admin/Sidebar159:235 và Admin/Topbar159:308. Lệnh đọc reactions bị môi trường chặn vì công cụ yêu cầu approval nhưng policy là never. Không tuyên bố audit đầy đủ interaction. Các thiết kế chưa tìm thấy được ghi NOT FOUND, không tự bổ sung.

## Hiện trạng trước khi chỉnh sửa
Hai tài liệu đầu ra chưa tồn tại. Đã đọc AGENTS và source Admin thực tế. Các thay đổi Dealer Manager có sẵn trong working tree được giữ nguyên và không thuộc task này. Không chạy ứng dụng, truy vấn database hoặc xác nhận runtime chỉ bằng đọc source.

## Các file source code liên quan
Chỉ đọc, chưa sửa:
- routes/web.php, app/Providers/RouteServiceProvider.php, middleware EnsureUserHasRole/EnsureAccountIsActive, AccountRegistrationReviewController.
- Admin controllers Dashboard, SubmissionReview, SubmissionVerification, Dealer, DealerAccount, Sales, DealerRegistration, Award và các request tương ứng.
- Policies User, Dealer, CustomerSubmission, Award; actions ApproveCustomerSubmission, RejectCustomerSubmission, UpdateCustomerSubmission, ReviewSalesAccount, ReviewAccountRegistration, CreateAwardDraft.
- Services SubmissionVerification, SubmissionCollisionAudit, LeaderboardService, AwardResults, PublicAwardResults, DealerProfile, DealerStandee; models Dealer/User/Award; RegisterRequest và cập nhật last_login_at khi đăng nhập.
- resources/views/admin và các component layout, admin-feedback, submission-inline-edit, submission-review-form, submission-verification, award-results; resources/js/app.js và submission-inline.js.
- Tests được đọc/tìm để lập test map: AdminDashboardTest, AdminSubmissionModerationTest, AdminSubmissionInlineTest, AdminSalesManagementTest, AccountRegistrationApprovalTest, DealerManagementTest, AwardWorkflowTest. Các tên test khác được nêu dưới đây đã tìm thấy trong repository; chưa đọc hoặc chạy mọi case.

## Những thay đổi đã thực hiện
Chỉ tạo hai tài liệu:
1. docs/figma/admin-ui-inventory.md: bốn bảng A/B/C/D, danh mục36frame, modal/toast/variant, 460 text layer của8màn, component geometry, 17 nhóm luồng INTENT, mapping Laravel và giới hạn.
2. docs/figma/admin-ui-implementation-plan.md: roadmap và tracking của task tài liệu này.

Không tạo source, raw dump, screenshot ứng dụng hoặc báo cáo QA bổ sung.

## Quyết định kỹ thuật quan trọng
- Figma chuẩn UI/câu chữ; Laravel chuẩn dữ liệu, validation và quyền. Lời hứa trong Figma chưa có backend phải BLOCKED, không thay bằng câu giải thích tự viết trong sản phẩm.
- Tên, email, điện thoại, con số, ngày tháng, mã Dealer và thứ hạng mẫu phải bind từ dữ liệu thật. Không mock production.
- Tạo shell, component và CSS/JS Admin riêng. Không sửa public header, x-layout chung hoặc B08 để xây sidebar Admin. Tái sử dụng primitive khi đúng variant, không ép mọi màn vào một table hoặc button.
- Phân biệt moderation với group membership, supplement và điểm. Không đổi enum hoặc gọi action cộng điểm chỉ vì label “Xác minh”.
- Giữ các tính năng backend chưa có thiết kế: ảnh Dealer, cấp/sửa tài khoản Manager, Sales edit/reactivate/pending/rejected, kỳ tuần, ghi chú nội bộ, evidence và địa chỉ đầy đủ. Cần quyết định vị trí truy cập thay vì xóa.
- Published snapshots và attribution lịch sử giữ nguyên. Undo, thu hồi điểm, Admin duyệt thay Sales và publication là task nghiệp vụ riêng nếu được chấp thuận.
- Mọi trigger/modal/toast mapping trong inventory là INTENT khi chưa có reaction được kiểm chứng. Không tự chọn thời gian toast, Escape, backdrop, animation hoặc menu items.

## Kế hoạch triển khai

Mỗi phase là một phạm vi riêng. Những phần được ghi “tách task” không được gom vào một lần triển khai lớn. Chưa bắt đầu phase triển khai nào.

### P00 — Chốt contract và khoảng trống thiết kế — P0
- **Màn:** C01–C08; đặc biệt C02 undo, C05 Manager, C06 override, C07/C08.
- **Modal/toast:** xác nhận danh mục4modal/18toast; bổ sung thiết kế còn thiếu cho lỗi, empty, dropdown mở, confirm sai thông tin và publication. C06variant173:1759 cần designer xác nhận.
- **Component:** chốt các variant shell, modal và toast; Mobile Admin hiện NOT FOUND.
- **Backend:** giải quyết Q01–Q16 trước khi phase liên quan thay đổi nghiệp vụ.
- **Test:** chỉ review tài liệu/contract; chưa cần PHPUnit hoặc build.
- **Nghiệm thu:**
  - [ ] Mỗi blocker có quyết định rõ và phạm vi thay đổi quyền/điểm được ghi riêng.
  - [ ] Đọc được reactions hoặc giữ BLOCKED phần interaction chưa xác minh.
  - [ ] Có frame/câu chữ cho trạng thái thiếu; không dùng B08/B10 làm Admin Mobile.

### P01 — Shell và component Admin dùng chung — P1, sau P00
- **Màn:** shell cho C01–C08; Admin/Sidebar159:235, Admin/Topbar159:308. Không triển khai nội dung8màn cùng task.
- **Modal/toast:** primitive cho4modal rộng720/520 và18toast; toast undo có action riêng. Chưa phát success cho thao tác chưa tích hợp.
- **Component:** Admin layout, nav, breadcrumb, card, table, filter, tabs, badge, modal, toast; tái sử dụng fonts/assets hiện có khi phù hợp.
- **Backend:** auth user, active route, quyền menu, số đếm thực. Giữ logout/profile; vị trí menu mở phải có thiết kế.
- **Test:** route guards theo phần shell chạm tới; rendering, keyboard/focus khi spec được chốt; build nếu đổi assets; screenshot1440. Viewport hẹp không được gọi là khớp Mobile Figma khi chưa có frame.
- **Nghiệm thu:**
  - [ ] Sidebar248, topbar64, content1128 tại1440; typography/spacing đúng inventory.
  - [ ] Active nav, tên dài và focus không làm vỡ layout.
  - [ ] Không có link giả cho C03/C08 chưa có route.
  - [ ] Modal/cancel không submit; close và toast timing có spec.
  - [ ] CSS/JS không ảnh hưởng public, Sales hoặc Dealer.

**Cập nhật P01 — 2026-11-06: DONE trong phạm vi shell/primitives.**
- Tạo `components/admin/{layout,sidebar,topbar,icon,badge,button,tabs,modal,toast}`, `admin.css`, `admin.js`,2entry Vite và12glyph PNG từ Figma MCP. Chỉ thay tag layout ở13view Admin; kiểm tra so HEAD xác nhận nội dung nguyên vẹn. Routes/controllers/quyền/dữ liệu và Website/Sales/Dealer không sửa.
- User đã chốt giữ nút Đăng xuất hiện có cạnh thẻ tài khoản và ghi nhận sai khác Figma; không dựng menu chưa có thiết kế. C03/C08 thiếu route và bell chỉ hiển thị tĩnh; modal/toast chưa tích hợp mặc định đóng/hidden, không success giả hoặc mutation tự động.
- PASS build73modules;18case AdminShell/AdminDashboard qua các lượt; fixture/QA riêng chỉ toyota_testing có guard. Case Dashboard cũ lỗi fixture trùng phone/query count đã sửa đúng dữ liệu và kiểm chứng, giữ privacy assertions. Không full regression.
- Đã đối chiếu ảnh Sidebar159:235/Topbar159:308 với [shell1440](qa/artifacts/p01-admin-shell-20261106/shell-1440.png). Kiểm tra390 chỉ dùng canvas Desktop có cuộn ngang cục bộ, không nghiệm thu Admin Mobile. Chi tiết file/test/giới hạn và ảnh tại [tracking P01](phase-p01-admin-shell.md).
- Kết quả này cập nhật trạng thái “chưa bắt đầu” của P01 trong kế hoạch gốc; các phase nội dung và blockers prototype/business P00 giữ nguyên. Chưa triển khai nội dung C01–C08.

### P02 — C01 Tổng quan, chỉ đọc — P1, sau P01
- **Màn:**160:233; tab set173:1465, hai variant.
- **Modal/toast:** NOT FOUND loại riêng cho C01; không tạo thêm.
- **Component:** KPI, task card, queue row, ranking và shell.
- **Backend:** DashboardController, LeaderboardService, DemoSubmissionScope. Bổ sung read aggregates đã chốt: hồ sơ cũ nhất, quá48giờ, pending Manager, Sales quá3ngày, approved tháng và Dealer có ít nhất5khách. Giữ submitted_at, múi giờ và tie-break.
- **Test:** AdminDashboardTest; chỉ case LeaderboardServiceTest bị ảnh hưởng. Kiểm tra count0, boundaries, quyền, query và link lọc.
- **Nghiệm thu:**
  - [ ] Số đếm và link dùng cùng tập dữ liệu.
  - [ ] Oldest/overdue dựa timestamp thật.
  - [ ] Hai tab xếp hạng đọc đúng query.
  - [ ] Luồng tới hàng chờ giữ tham số; không bỏ quick link đang dùng.
  - [ ] Empty chưa có thiết kế vẫn BLOCKED.

### P03 — C04 Đại lý — P1, sau P01
- **Màn:**163:488, tab set173:1651, panel163:695.
- **Modal/toast:**206:1029/1086/1143; toast173:2037/2044 và206:1227/1233/1239/1274.
- **Component:** master/detail, form hai cột, dangerous confirmation, copy link, badge, filter và pagination.
- **Backend:** DealerController/DealerRequest/DealerProfile/DealerSlugs; KPI tháng/rank/current staff. Model có opening_hours/facebook_url/zalo_url nhưng request chưa nhận: chốt write contract trước, không mặc định cần migration.
- **Tách task:** (a) list/panel/CRUD hiện có và các filter đã được duyệt; (b) adapter PDF có quyền Admin, gọi DealerStandee; (c) thay quy tắc eligibility khi ngừng tham gia nếu được chấp thuận.
- **Test:** DealerManagementTest và các case liên quan trong DealerSlugTest/DealerProfileTest/DealerStandeeTest. Kiểm tra code unique/race, slug immutable, lịch sử, quyền, headers file thật, clipboard và cancel.
- **Nghiệm thu:**
  - [ ] Bốn tab/count, tỉnh và sort dùng dữ liệu thật.
  - [ ] Các field giờ mở cửa/liên kết không bị silently drop.
  - [ ] Đổi code không đổi slug/QR đã in.
  - [ ] Admin tải PDF qua endpoint có quyền, không giả Manager own-Dealer route.
  - [ ] Ba modal/sáu toast đúng wording và chỉ phản hồi thành công thực tế.
  - [ ] Ảnh Dealer/account tools cũ có vị trí được duyệt hoặc ghi BLOCKED.

### P04 — C05 Sales — P1, sau P01/P03
- **Màn:**163:763, tab set173:1710, inline lock163:1022.
- **Modal/toast:**173:2059 cho khóa;173:2052 cho export ở task riêng. Giữ inline confirmation, không thay bằng modal tự dựng.
- **Component:** table, role badge, row ellipsis, inline confirm, search/filter/pagination.
- **Backend:** SalesController, ReviewSalesAccount, UserPolicy; read model cho điểm tháng, tỷ lệ từ chối và activity sau khi chốt định nghĩa.
- **Tách task:** UI/query/khóa Sales hiện có trước. Export Excel và gộp Manager vào scope là task backend riêng, cần quyết định quyền, PII, định dạng và bộ lọc.
- **Test:** AdminSalesManagementTest; các case SalesLastLoginTest/SalesPublicProfileTest thực sự bị ảnh hưởng. Kiểm tra khóa lặp, mở khóa, session/link invalidation, chuyển Dealer và export.
- **Nghiệm thu:**
  - [ ] Ba tab lấy số liệu thật; không suy ngưỡng từ38% mẫu.
  - [ ] Không giả activity từ updated_at hoặc gán last_login_at cho Manager khi chưa có bằng chứng.
  - [ ] Khóa không làm mất khách/điểm/history.
  - [ ] Pending/rejected/edit/reactivate backend không bị xóa.
  - [ ] Menu mở chưa có thiết kế không được tự thêm item.

### P05 — C06 Duyệt tài khoản — P1, sau P01/P03
- **Màn:**164:658, tab set173:1760, rejection inline164:789.
- **Modal/toast:**173:2066/2073 cho Manager;173:2080/2088 cho nhắc/duyệt thay ở task riêng.
- **Component:** account card, hệ thống kiểm tra, reason form; tách lý do công khai khỏi internal note.
- **Backend:** DealerRegistrationController, ReviewAccountRegistration/Request/UserPolicy; pending queue và lịch sử.
- **Tách task:** approve/reject Manager hiện có trước; query đã xử lý/quá hạn sau đó. Nhắc email/cooldown và Admin override Sales chỉ làm sau quyết định đổi quyền.
- **Test:** AccountRegistrationApprovalTest, AccountStatusPresentationTest theo phạm vi; pending/rejected/resubmit, unique Manager, race, quyền. Khi có gửi mail phải kiểm tra delivery failure và không báo “đã gửi” giả.
- **Nghiệm thu:**
  - [ ] Variant173:1759 đã chốt câu chữ.
  - [ ] Approve/reject giữ danh tính và audit.
  - [ ] Không hard-code green check tên miền email/phone unique.
  - [ ] Quá3ngày có clock/cohort rõ, không suy từ updated_at.
  - [ ] Không dùng legacy Sales route để vượt policy.

### P06 — C02 hàng chờ và master/detail chỉ đọc — P1, sau P01/P03/P04
- **Màn:**161:322, tab set173:1558, detail161:536.
- **Modal/toast:** chưa gắn mutation; edit và supplement ở P07.
- **Component:** filters, selected row, detail, check notice, pagination. List500/detail612/gap16.
- **Backend:** SubmissionReviewController/SubmissionIndexRequest, verification projection và duplicate guards. Chốt source filter, tìm biển số, flags và5nhóm workflow trước khi tích hợp.
- **Test:** các case read/filter/PII/order trong AdminSubmissionInlineTest/ModerationTest; selected row hết hiệu lực sau refresh, filter và pagination.
- **Nghiệm thu:**
  - [ ] List che PII, detail chỉ hiển thị dữ liệu Admin được phép xem.
  - [ ] Không giả flag địa lý/burst hoặc số đếm.
  - [ ] Deep link show cũ vẫn hoạt động.
  - [ ] Năm tab và số đếm cùng định nghĩa, không đổi enum moderation thành5trạng thái.
  - [ ] Giữ filter và selected ID theo contract khi đổi hàng.

### P07 — C02 actions, edit và supplement — P0/P1, sau P06
- **Màn:** modal206:1169, inline supplement161:634, actions161:629/631/633.
- **Toast:**173:2006/2013/2020,206:1245. Action undo24giờ chỉ tích hợp sau backend riêng, không để nút giả.
- **Component:** versioned form, modal hai cột, feedback/khôi phục filter, confirmation đã được chốt.
- **Backend:** Approve/Reject/UpdateCustomerSubmission và SubmissionVerification; giữ locks, duplicate checks, HMAC, audit, privacy.
- **Tách task:** tích hợp command hiện có; mở rộng typed reason/thông báo; undo24giờ. Không gộp ba phần thành task giao diện.
- **Test:** các case liên quan trong AdminSubmissionModerationTest/InlineTest, SubmissionDuplicateTest/ApprovalRaceTest/IntegrityUpdateTest; no-op, stale version, sai record, QR bắt buộc Facebook, lý do công khai, cancel/double-submit/back.
- **Nghiệm thu:**
  - [ ] Mutation chỉ thực hiện một lần và version đúng.
  - [ ] Bổ sung/group update không tự tăng điểm.
  - [ ] Modal đáp ứng required fields theo nguồn; không bỏ validation địa chỉ để giống ảnh.
  - [ ] Facebook/năm/attribution theo contract đã chốt, không tự nới quyền sửa.
  - [ ] Không lộ admin_note hoặc đổi published snapshots.
  - [ ] Reject/undo/error chưa có thiết kế phải ghi BLOCKED.

### P08 — C03 Tra SĐT vào group — P2, sau P06/P07
- **Màn:**162:403, result162:528 và recent processed.
- **Modal/toast:**173:2028; luồng bổ sung/toast173:2013 cần xác nhận đích. Không dựng Facebook control có quyền duyệt thật.
- **Component:** search, result, checklist, notice và normalized profile link.
- **Backend:** endpoint exact normalized-phone lookup có quyền Admin, throttle và xử lý đa kết quả; reuse group evidence/version. Public lookup hoặc LIKE search hiện có không thay thế contract này.
- **Test:** test mới cho lookup: exact/multiple/no match/malformed/roles/rate/stale version, không lộ PII ngoài quyền và không đổi điểm.
- **Nghiệm thu:**
  - [ ] Có thiết kế cho không tìm thấy và đa kết quả.
  - [ ] “Xác minh thành viên” tách moderation.
  - [ ] Không tự gọi API duyệt Facebook.
  - [ ] URL group/profile thật, không generic sample URL.
  - [ ] Recent processed phản ánh sự kiện đã lưu.

### P09 — C08 Kiểm tra ngẫu nhiên — P2, sau khi chốt chính sách lấy mẫu
- **Màn:**165:832, tab set173:1809, result165:1059.
- **Modal/toast:**173:2097 cho kết quả đúng; dialog xác nhận sai và toast cho outcome khác NOT FOUND.
- **Component:** queue/detail, ba câu Đúng/Sai, ba outcome, note/progress.
- **Backend mới:** tập dữ liệu lấy mẫu, freeze/reproducibility, phân công/concurrency, idempotency, attempts/outcomes/audit, gọi lại sau1ngày, hai lần không nghe máy, revoke/deduction nếu được duyệt.
- **Tách task:** (a) contract và backend/read model; (b) UI/gọi/lưu; (c) thao tác thu hồi điểm với confirmation đã có thiết kế. Không cấp quyền scoring chỉ bằng radio.
- **Test:** test mới cho sampling, nhận hồ sơ đồng thời, lưu lặp, gọi lại theo múi giờ, giới hạn attempts, cancel xác nhận, score idempotency và privacy.
- **Nghiệm thu:**
  - [ ] Không hard-code60/18 hoặc sample Dealer.
  - [ ] Lưu và chuyển khách nhất quán.
  - [ ] Cancel khi sai không mutation.
  - [ ] Hai lần không nghe không tính sai.
  - [ ] Không phá approval history/snapshot hoặc trừ điểm hai lần.
  - [ ] Dialog/outcome thiếu thiết kế vẫn BLOCKED.

### P10 — C07 Vinh danh và publication — P2
Phần chỉ đọc có thể làm sau P01; phần công bố bắt buộc sau P09 và quyết định TMV.
- **Màn:**164:867, kỳ tháng/steps/top3; preview164:978; disabled publish164:1113.
- **Modal/toast:** confirm/success publication NOT FOUND; không tự sáng tạo.
- **Component:** period rail, step progress, kết quả audit, table, warning và disabled button.
- **Backend:** reuse Award/AwardWinner/AwardResults/CreateAwardDraft/PublicAwardResults. Cần mới cycle close, audit gate, exclusion/replacement, publication transaction và fingerprint enforcement.
- **Tách task:** UI bản nháp/lịch sử/preview; action/route publication sau khi có contract; tích hợp call gate ở task cuối. Giữ kỳ tuần và Sales snapshots nội bộ dù Figma chỉ có tháng/Dealer.
- **Test:** các case liên quan trong AwardWorkflowTest/CompetitionAwardsTest/LeaderboardServiceTest; draft riêng tư, công bố một lần, stale hash, race, audit hoàn tất, immutability và projection public chỉ Dealer.
- **Nghiệm thu:**
  - [ ] Cutoff Việt Nam và exclusive bounds đúng.
  - [ ] Top3/replacement lấy từ contract thực.
  - [ ] Publish gate được enforce ở server, không chỉ disabled CSS.
  - [ ] Không đổi snapshot cũ, không lộ draft/PII qua public preview.
  - [ ] Ngưỡng “[x]% chờ TMV” được chốt trước khi phát hành nghiệp vụ.

## Thứ tự ưu tiên và dependencies
1. P00 → P01: chốt blocker, rồi làm shell.
2. P02/C01; P03/C04; P04/C05 và P05/C06 trong scope nghiệp vụ hiện có.
3. P06/C02 chỉ đọc → P07/C02 mutations.
4. P08/C03 lookup sau khi có contract và evidence.
5. P09/C08 audit → P10/C07 publication. C07 UI bản nháp có thể làm sớm, nhưng không giả dữ liệu kiểm tra hay công bố.

Mỗi lần chỉ triển khai một phase/subtask. Dừng ở gate nghiệp vụ chưa được chốt; không triển khai toàn bộC01–C08 trong một task.

## Rủi ro và câu hỏi nghiệp vụ cần chốt
| ID | Câu hỏi | Căn cứ/xung đột | Phụ thuộc |
|---|---|---|---|
|Q01|Đọc prototype thế nào? Ai cung cấp thiết kế Mobile, empty, lỗi, menu mở và confirmation còn thiếu?|Reactions page159:316 bị chặn; không có Admin Mobile|P00/P01, nghiệm thu mọi phase|
|Q02|C02 “Xác minh” là approval hay membership? Ba checkbox phải lưu/enforce thế nào?|Approve tăng điểm; group update không tăng điểm; ảnh còn checkbox chưa chọn nhưng button có style bình thường|P06/P07/P08|
|Q03|Undo24giờ đổi status/history/điểm/snapshot ra sao? Ai được dùng?|Toast có action, backend chưa có|P07 undo|
|Q04|Flags xa tỉnh và6khai báo/15phút dùng ngưỡng, clock và cohort nào?|Hiện chỉ có cảnh báo trùng identity, chưa có heuristics này|P02/P06|
|Q05|C05 gộp Manager không? Quyền khóa/sửa Manager? “Hoạt động” là login hay action?|Query/ability hiện chỉ Sales; last_login_at cũng chỉ ghi cho Sales|P04|
|Q06|Tỷ lệ từ chối cao dùng ngưỡng, mẫu số và khoảng thời gian nào? Export chứa PII gì?|38% là mẫu; chưa có Excel endpoint|P04 metrics/export|
|Q07|Admin được duyệt thay Sales sau3ngày không? 72giờ hay ngày lịch? Nhắc ai, cooldown bao lâu?|Policy hiện cấm Admin review Sales; chưa có override/reminder|P05|
|Q08|Nguồn đáng tin cho tên miền Dealer và kiểm tra phone duy nhất là gì?|RegisterRequest không unique phone; website không chứng minh domain chính thức|P05 checks|
|Q09|Thông báo cho khách/Sales/Manager qua kênh nào? Khi gửi thất bại, retry và toast xử lý ra sao?|Action hiện chỉ ghi DB; SMTP config không chứng minh đã gửi|P05/P07|
|Q10|Reason code và note nào được công khai? Khách sửa plate mismatch qua luồng nào?|Lý do Figma chưa nằm trong whitelist; receipt flow hiện chỉ hỗ trợ case Facebook|P07/P08|
|Q11|Edit Facebook/năm/địa chỉ/Dealer/Sales theo nguồn/status ra sao? Required field thiếu đặt ở đâu?|Facebook bị cấm trong edit request; QR required địa chỉ/ward; reassign chỉ pending|P07|
|Q12|Ngừng tham gia: HTTP error hay inactive landing? Loại khỏi kỳ mới hay mọi ranking?|Service score không lọc is_active; directory có lọc; history phải giữ|P03|
|Q13|C08 lấy60khách toàn hệ thống và C07 top3×5 là hai batch hay một? Chốt/seed thế nào?|Hai màn thể hiện tập dữ liệu khác nhau|P09/P10|
|Q14|Sai thông tin: thu hồi approval, trừ điểm, loại Dealer, đôn hạng và rollback thế nào?|Reject chỉ pending; chưa có deduction ledger; ngưỡng TMV chưa chốt|P09/P10|
|Q15|Hai lần không nghe là hai attempts tổng cộng? Gọi lại sau1ngày tính từ lúc nào? Có thay mẫu không?|Figma warning chưa định nghĩa đủ state machine và denominator|P09|
|Q16|Cutoff, audit gate, thay top3, kỳ tuần và public preview cần hoạt động ra sao?|Có draft tuần/tháng + snapshot immutable; chưa có route/action công bố|P10|

Các mẫu “[x]%”, domain/email hoặc số liệu trong Figma không thay thế quyết định nghiệp vụ. Không tự chọn ngưỡng hoặc đổi quyền.

## Kết quả kiểm thử
Task này **không chạy PHPUnit, build, migration, seed, reset, runtime GET/POST hoặc screenshot ứng dụng**.
Chỉ kiểm tra cấu trúc tài liệu, node IDs, bốn bảng, số lượng màn/modal/toast/variant, liên kết giữa hai tài liệu và phạm vi file thay đổi. Các tests/QA ở từng phase là việc tương lai, không phải kết quả PASS hiện tại.

Khi triển khai: xác nhận database test cách ly toyota_testing bằng actual connection/guard trước test ghi; không dùng config cache local. Chỉ chạy tests bị ảnh hưởng, không full regression mặc định. Build/test PASS không thay nghiệm thu thị giác. Nếu chưa có Mobile frame phải ghi rõ giới hạn.

## Những lỗi còn tồn tại
- BLOCKED reactions của page159:316 và descendants, nên chưa audit đầy đủ prototype.
- Các trạng thái NOT FOUND trong inventory không được tự thiết kế thay thế.
- Variant C06 node173:1759 mâu thuẫn giữa tên và nhãn hiển thị. C05/C07 có layer tênText nhưng render rỗng, không đưa chữText vào UI.
- Q01–Q16 cần chốt theo từng phase. Không triển khai quyền/score/schema trong task tài liệu.

## Task hoặc bước tiếp theo
Ưu tiên P00→P01. Các task sau chỉ đọc phần tương ứng trong inventory và roadmap; không cần quét lại toàn bộ static Figma. Chỉ đọc bổ sung reactions/node thiếu hoặc thiết kế mới khi có khả năng truy cập và quyết định liên quan. Chưa sửa PHP/Blade/CSS/JS hoặc dữ liệu.

