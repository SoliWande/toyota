# Kiểm kê UI Admin C01–C08

## Phạm vi, trạng thái và bằng chứng
- Ngày audit: 2026-11-06. File `yKmWQI6BYJ6dponEnidlsq`, page `159:316` — **UI · Admin – Desktop (C)**. Figma MCP session `d1b549c8-3ede-4e39-9d7f-e322bb4e6783`.
- **Đọc được 8/8 màn chính + toàn bộ 28 frame phụ cấp page = 36/36 frame** bằng metadata và get_design_context riêng từng frame. Đã xem ảnh Figma 8 màn, 4 modal, 6 nhóm tab; không tạo screenshot ứng dụng hoặc file asset/QA.
- 4 modal độc lập, 18 toast, 6 nhóm tab với **20 named variants**; thêm 2 panel chi tiết và 3 vùng inline xác nhận/form. Các nhóm này được đếm riêng, không gọi mọi panel là modal.
- **BLOCKED xác minh toàn bộ prototype graph**: lệnh use_figma chỉ đọc `reactions`/destination/componentProperties của page159:316 bị từ chối: “MCP tool call requires approval, but approval policy is never”. Không có node render nào trong36 frame bị lỗi; nhưng reactions của page và descendants chưa đọc được. Metadata/context không xuất đầy đủ click triggers, overlay options, timing, refresh hoặc navigation destinations. Không suy ra hành vi chắc chắn chỉ từ tên button.
- Quy ước: **FOUND** = thấy trong metadata/context/ảnh; **NOT FOUND** = không tìm thấy thiết kế trong phạm vi đã quét, không đồng nghĩa backend không có; **BLOCKED** = chưa đọc/xác minh được; **INTENT** = liên hệ theo vị trí/câu chữ thiết kế, chưa phải prototype edge đã kiểm chứng.
- Mobile Admin: **NOT FOUND**. File có page Website/Khách hàng Mobile, không có page Admin Mobile trong danh sách page. Không dùng B08/B09/B10 làm thiết kế Admin.
- Dữ liệu tên người, email, điện thoại, tháng, counts, ranks, mã Dealer trong Figma là mẫu. Production phải bind dữ liệu Laravel; không chép mẫu, placeholder `[SĐT]`, `[DEMO]` hoặc con số vào runtime.
- Source thực tế đã đọc: `routes/web.php`; Admin controllers/requests; policies User/Dealer/CustomerSubmission/Award; actions review/edit/draft; services verification/leaderboard/award; Admin Blade/shared components/JS. Đây là audit tĩnh; không kiểm tra schema database đang chạy, SMTP delivery hoặc endpoint bằng runtime.
- Chỉ tạo tài liệu này và [kế hoạch](admin-ui-implementation-plan.md). Các thay đổi Dealer Manager có sẵn trong working tree được giữ nguyên, không thuộc task Admin.

## A. Screen inventory

| Screen ID | Frame ID — Desktop | Tên chính xác | Route Laravel hiện có | UI hiện có | Mức độ cần sửa |
|---|---|---|---|---|---|
| C01 | 160:233 — 1440×1384 | C01 · Tổng quan | GET /admin/dashboard — admin.dashboard | admin/dashboard; x-layout, KPI/link/filter, recent pending, topSales/topDealers | Cao: shell mới, bố cục KPI, task cards, counts bổ sung |
| C02 | 161:322 — 1440×1405 | C02 · Duyệt khách hàng | GET /admin/submissions; GET /admin/submissions/{submission}; PATCH cùng path; POST /approve,/reject,/verification dưới submission | admin/submissions/index,show; list cards + details edit/review, verification component | Rất cao: master/detail, 5 workflow tabs, flags, modal edit, inline supplement |
| C03 | 162:403 — 1440×900 | C03 · Tra SĐT vào group | **NOT FOUND route riêng**; /admin/submissions?q= chỉ là search LIKE | Không có màn tra cứu chuyên biệt | Mới UI và backend lookup có quyền; không dùng public lookup thay thế |
| C04 | 163:488 — 1440×1054 | C04 · Đại lý | admin.dealers.index/create/store/show/edit/update/status; /admin/dealers và /{dealer} | admin/dealers/index,show,form,account | Cao: split list/detail, 4tabs, 3modals, thống kê tháng và exportQR |
| C05 | 163:763 — 1440×900 | C05 · Sales | GET /admin/sales; GET /{sales}, /{sales}/edit; PATCH /{sales}; POST /{sales}/{approve,reject,block,reactivate} | admin/sales/index,show,edit; list chỉ role Sales | Cao: table, 3tabs, inline lock, metrics, export; role Manager trong Figma xung đột scope |
| C06 | 164:658 — 1440×1145 | C06 · Duyệt tài khoản | GET /admin/dealer-registrations; POST /admin/dealer-registrations/{account}/{approve,reject} | admin/dealer-registrations: pending Manager + approve/reject details | Cao: cards/checks, tab processed/overdue, inline reason; reminder/override thiếu |
| C07 | 164:867 — 1440×956 | C07 · Vinh danh | GET/POST /admin/awards; GET /admin/awards/{award} | admin/awards/index,show; draft/preview/snapshot đã published | Rất cao: pipeline top3 audit/call/exclusion/publish chưa có |
| C08 | 165:832 — 1440×1046 | C08 · Kiểm tra ngẫu nhiên | **NOT FOUND** | Không có list gọi/kết quả/lịch gọi lại | Mới; ảnh hưởng điểm/phê duyệt, phải có contract riêng |

Tất cả Mobile tương ứng: **NOT FOUND**, chưa có breakpoint/sidebar mobile được phê duyệt.

## Shell và component dùng chung
| Component | ID nguồn / instance | Thông số và wording có căn cứ | Giới hạn |
|---|---|---|---|
| Admin/Sidebar | 159:235; instance C01 160:234, C02 161:323 | rộng248, white, border #ededf0, px14/py20; nav icon20, text14/20, gap12, row40, radius12; active #fff1f2/#eb0a1e | Các màn có active riêng qua instance; không thấy component set sidebar responsive |
| Brand/User | sidebar children159:236,159:300 | “Quản trị”; “Cộng đồng Veloz & Hilux”; avatar36; “Development Admin”; “Admin Ô tô Sài Gòn” | Tên/avatar mẫu bind User; menu User mở rộng/logout popup **NOT FOUND**; không xóa logout backend |
| Admin/Topbar | 159:308 | cao64, content1192, left32/right24; “Quản trị / …”; “Giờ Việt Nam (UTC+7)”; notification circle36 | Badge/count/menu thông báo mở **NOT FOUND** |
| Content | C01 160:318; C02 161:405; C03 162:486; C04 163:571; C05 163:846; C06 164:741; C07 164:950; C08 165:915 | nền #f5f5f7; content1128 sau padding32; top28, bottom48, gap24; Inter; title28/34 Extra Bold, body14/20, small12/18 | C05 frame900 nhưng Main824, C03 frame900/Main897: giữ nhận diện, không suy ra sticky/scroll behavior |
| Badge | 7:44 Pending; 7:47 Approved; 7:50 Rejected; 7:53 Brand; 20:107 Info; 20:110 Neutral | dot6, px10/py4 radius999, text12/1.3 Semi Bold; success #f0fdf4/#15803d; info #eff6ff/#1d4ed8; danger #fef2f2/#b91c1c; neutral #f5f5f4/#44403c | Label phụ thuộc màn và evidence thực, không đổi enum chỉ để giống ảnh |
| Button/input | global7:26/7:28/7:30; Admin controls là frame con | button pill thường h36/38/42/44; input modal44 radius12 border#e3e3e8; tab bg#e9e9ee radius12 activewhite radius9 | Không lấy button global h52 áp lên mọi Admin variant |
| Modal | 206:1029/1086/1169 rộng720; 206:1143 rộng520 | radius24, white, shadow0 24 64 rgba(0,0,0,.18); close36; footer#fafafb; Huỷ outline; submit dark, destructive #c4101f | Backdrop/escape/click-outside/focus trap/prototype duration **BLOCKED**, không bịa option |
| Toast | 18node ở bảng B | h52, radius16, bg#1c1c1e; px16/22 py14 gap10; icon24 green; shadow0 8 24 rgba(0,0,0,.25) | Vị trí runtime/auto-dismiss/timing **BLOCKED**; chỉ toast173:2006 có action “Hoàn tác (24 giờ)” |
| Shared Laravel hiện tại | components/layout,admin-feedback,submission-inline-edit,submission-review-form,submission-verification,award-results | generic layout dùng app.css/app.js; admin-feedback hiển thị flash success/errors; submission-inline.js có reset/cancel/double-submit/pageshow | Chưa có Admin shell/toast manager/modal theo Figma; không sửa shared layout làm ảnh hưởng Sales/Dealer |

Sidebar nav theo thứ tự nguyên văn: “Tổng quan”, “Duyệt khách”, “Tra SĐT vào group”, “Duyệt tài khoản”, “Đại lý”, “Sales”, “Vinh danh”, “Kiểm tra ngẫu nhiên”; divider; “Bảng xếp hạng”. Badge mẫu222 tại Duyệt khách,1 tại Duyệt tài khoản. Không hard-code.

## B. Modal/Toast inventory
Cột trigger/kết quả bên dưới là **INTENT từ màn nguồn + wording**, trừ href cụ thể ở mục navigation. Toàn bộ prototype edge/timing vẫn BLOCKED. “Không có” nghĩa không có button trong frame, không suy ra toast tự đóng.

| ID | Màn nguồn | Tên modal/toast, nội dung chính xác | Trigger INTENT | Buttons | Kết quả INTENT / backend |
|---|---|---|---|---|---|
|206:1029|C04|Modal · Thêm đại lý; “Đại lý mới sẽ có mã QR và standee ngay sau khi tạo.”|Thêm đại lý163:575|Đóng; Huỷ; Tạo đại lý|POST admin.dealers.store; thành công toast206:1227; modal payload có field chưa được request nhận|
|206:1086|C04|Modal · Sửa thông tin đại lý; “Đổi mã đại lý không làm đổi link QR đã in.”|Sửa163:720|Đóng; Huỷ; Lưu thay đổi|PUT/PATCH admin.dealers.update; toast206:1233; slug immutable đã có|
|206:1143|C04|Modal · Ngừng tham gia chương trình?; “QR và link của đại lý sẽ dẫn tới trang báo lỗi.”; “Điểm và lịch sử đã có vẫn giữ.”; “Đại lý không còn trên bảng xếp hạng kỳ mới.”|Ngừng tham gia163:762|Đóng; Huỷ; Ngừng tham gia|PATCH admin.dealers.status is_active=0 có; tác động leaderboard/landing phải chốt|
|206:1169|C02|Modal · Sửa thông tin khách; “Lý Minh Quân · mọi thay đổi được ghi vào lịch sử”|Sửa161:591 hoặc161:611|Đóng; Huỷ; Lưu thay đổi|PATCH admin.submissions.update có version/audit; FB/năm/attribution có giới hạn|
|173:2006|C02|Đã xác minh Lý Minh Quân · đại lý +1 điểm|Xác minh161:629|Hoàn tác (24 giờ)205:1278|Approve có; undo24 giờ **NOT FOUND backend**|
|173:2013|C02/C03 liên hệ bổ sung|Đã gửi yêu cầu bổ sung cho khách và sales|Gửi yêu cầu161:651; C03CTA bổ sung liên hệ chưa có edge|Không có|Supplement state có, thông báo khách/Sales chưa có delivery|
|173:2020|C02|Đã từ chối khai báo · sales sẽ thấy lý do|Từ chối161:633|Không có|Reject action có; rejection form riêng **NOT FOUND Figma**|
|173:2028|C03|Đã xác minh Nguyễn Văn An · bấm [Duyệt] bên Facebook|Xác minh thành viên162:559|Không có|Group evidence có; không tự approve/cộng điểm; tra cứu riêng thiếu|
|173:2037|C04|Đang tải QR Toyota An Sương (PNG)|QR (PNG)163:752|Không có|QR public thật có; cần adapter tải từ Admin|
|173:2044|C04|Đang tải standee Toyota An Sương (PDF)|Standee (PDF)163:756|Không có|DealerStandee service có; route hiện chỉ Manager own Dealer, Admin không dùng được|
|173:2052|C05|Đang xuất danh sách sales (Excel)|Xuất danh sách163:850|Không có|Export Excel endpoint/service **NOT FOUND**|
|173:2059|C05|Đã khoá [DEMO] Võ Quốc Bảo · link mời hết hiệu lực|Khoá tài khoản163:1033|Không có|Sales block có; Manager trong table chưa nằm trong scope|
|173:2066|C06|Đã duyệt Lâm Văn Tài làm quản lý Toyota Cà Mau|Duyệt164:786|Không có|ReviewAccountRegistration Manager có, name/dealer bind thật|
|173:2073|C06|Đã gửi từ chối · người đăng ký nhận email|Gửi từ chối164:801|Không có|Reject state/history có, action/controller không gửi mail|
|173:2080|C06|Đã gửi email nhắc quản lý đại lý|Nhắc quản lý164:844/864|Không có|Reminder endpoint/delivery **NOT FOUND**|
|173:2088|C06|Đã duyệt thay quản lý đại lý|Duyệt thay164:846/866|Không có|**Xung đột policy**: Admin không được reviewRegistration Sales|
|173:2097|C08|Đã lưu: Đúng thông tin · chuyển sang khách tiếp theo|Lưu kết quả165:1081 + Đúng thông tin|Không có|Sampling/call persistence/next **NOT FOUND**|
|206:1227|C04|Đã tạo đại lý · QR và standee đã sẵn sàng|Tạo đại lý206:1085|Không có|Create có; không báo PDF ready khi chưa có tải Admin có quyền|
|206:1233|C04|Đã lưu thông tin đại lý|Lưu thay đổi206:1142|Không có|Update có; request hiện thiếu opening_hours/facebook_url/zalo_url|
|206:1239|C04|Đã ngừng tham gia Toyota An Sương|Ngừng tham gia206:1168|Không có|is_active update có; hậu quả nghiệp vụ cần chốt|
|206:1245|C02|Đã lưu thông tin khách · ghi vào lịch sử|Lưu thay đổi206:1226|Không có|UpdateCustomerSubmission có masked audit + stale guard; no-op không tạo audit|
|206:1274|C04|Đã sao chép link đại lý|Copy163:744/linkbox icon|Không có|Canonical DealerProfile URL có; clipboard UI/toast chưa có|

### Bổ sung: panel và confirmation inline không được bỏ sót
| ID | Màn | Loại | Nội dung/CTA đã tìm thấy | Backend / giới hạn |
|---|---|---|---|---|
|161:536|C02|Panel chi tiết 612×1011, nằm trong content, không xác nhận là overlay|Tên/badge, “1 / 222”, previous/next; flags; đối chiếu group; LIÊN HỆ & XE; GHI NHẬN CHO; Xác minh/Yêu cầu bổ sung/Từ chối|Existing show riêng; cần adapter master/detail nhưng giữ GET detail deep link|
|161:634|C02|Inline form “Yêu cầu bổ sung” 562×246|Lý do; “Biển số không khớp câu trả lời trong group”; “Ghi chú thêm cho khách / sales (tuỳ chọn)”; “Khách xem được lý do này ở trang tra cứu.”; Huỷ; Gửi yêu cầu|Hiện chỉ reason whitelist facebook_unavailable/missing_information, không public free note; không tái dùng admin_note|
|163:695|C04|Panel chi tiết Dealer420×712|KPI tháng/rank/Sales; Sửa; MÃ QR VÀ STANDEE; copy, PNG, PDF; Ngừng tham gia|Existing show trang riêng, all-time stats chưa đúng tháng|
|163:1022|C05|Inline “Xác nhận khoá”1126×68|“Khoá [DEMO] Võ Quốc Bảo?”; “Link mời hết hiệu lực ngay. 47 khách đã mời vẫn tính cho Toyota Kiên Giang. Người này cũng không đăng nhập được nữa.”; Huỷ; Khoá tài khoản|State/history có; count/name/Dealer thật; không mở modal mới thay inline|
|164:789|C06|Inline “Từ chối · mở rộng”490×238|“Lý do từ chối (người đăng ký sẽ thấy)”; selected “Không phải nhân viên của đại lý”; “Ghi thêm (tuỳ chọn)”; Huỷ; Gửi từ chối|Rejection reason text nullable≤2000 có; cách combine selected reason+note chưa có contract|
|165:1059|C08|Kết quả trong workspace, không modal|Đúng thông tin / Sai thông tin / Không nghe máy; Ghi chú (tuỳ chọn); Lưu kết quả|Chưa có backend; confirm khi sai được text nhắc nhưng **modal xác nhận sai NOT FOUND**|

### Các modal/form field nguyên văn
- Subtitle mẫu của modal sửa Dealer206:1090 và modal ngừng tham gia206:1147: “Toyota An Sương · TMV-405”. Khi triển khai phải bind tên/mã Dealer thật, không dùng giá trị mẫu này.
- **206:1029**: Tên đại lý — “VD: Toyota Cần Thơ”; Mã đại lý — “VD: TMV-1040”; Tỉnh / Thành phố — “Chọn tỉnh/thành”; Điện thoại — “Số tổng đài”; Địa chỉ — “Số nhà, đường, phường”; Giờ mở cửa — “VD: 8:00 – 17:00”; Link Facebook — “facebook.com/…”; Link Zalo — “zalo.me/…”. Ghi chú: “Quản lý đại lý tự đăng ký tài khoản, admin duyệt ở mục Duyệt tài khoản.”
- **206:1086**: cùng8label; dữ liệu mẫu Toyota An Sương,TMV-405,TP. Hồ Chí Minh,[SĐT],[Địa chỉ đại lý],[Giờ mở cửa],[Link Facebook],[Link Zalo]. Đây là value mẫu, không phải default production.
- **206:1169**: Họ và tên; Điện thoại; Dòng xe; Năm đăng ký; Biển số; Tỉnh / Thành phố; Facebook; Đại lý ghi nhận. Sample tương ứng: Lý Minh Quân;0000700105;Toyota Hilux;2024;68A-xxx.xx;Hà Nội;facebook.com/toyota.demo…0105;Toyota Kiên Giang · TMV-1016. Modal không có input Sales, ward/address_line đầy đủ hoặc edit_version; các yêu cầu backend không được bỏ vì vậy.
- **Close controls**: 206:1034,206:1091,206:1148,206:1174 đều “Đóng” theo node name/icon×; không có chữĐóng hiển thị như CTA footer. Escape/backdrop cancel/reset chưa xác minh.

## Variants, dropdown, tooltip và các trạng thái

| Set ID | Màn | Named variants (ID chính xác) | Ghi chú |
|---|---|---|---|
|173:1465|C01|`173:1458` Active=Đại lý<br>`173:1464` Active=Sales (nội bộ)|Đã đọc context/ảnh mọi hàng; kết nối CHANGE_TO chưa xác minh.|
|173:1558|C02|`173:1489` Active=Chờ xác minh<br>`173:1506` Active=Cần bổ sung<br>`173:1523` Active=Chờ vào group<br>`173:1540` Active=Đã xác minh<br>`173:1557` Active=Từ chối|Đã đọc context/ảnh mọi hàng; kết nối CHANGE_TO chưa xác minh.|
|173:1651|C04|`173:1608` Active=Tất cả<br>`173:1622` Active=Chưa có khách tháng này<br>`173:1636` Active=Chưa có quản lý<br>`173:1650` Active=Ngừng tham gia|Đã đọc context/ảnh mọi hàng; kết nối CHANGE_TO chưa xác minh.|
|173:1710|C05|`173:1687` Active=Đang hoạt động<br>`173:1698` Active=Tỉ lệ bị từ chối cao<br>`173:1709` Active=Đã khoá|Đã đọc context/ảnh mọi hàng; kết nối CHANGE_TO chưa xác minh.|
|173:1760|C06|`173:1736` Active=Quản lý đại lý chờ duyệt<br>`173:1746` Active=Sales chờ quá 3 ngày<br>`173:1759` Active=Đã xử lý|**Mâu thuẫn**: variant173:1759 tên Active=Đã xử lý nhưng ảnh/context hiển thị nhãn active “Quản lý đại lý chờ duyệt 1”, lặp tab đầu. Cần designer xác nhận.|
|173:1809|C08|`173:1786` Active=Cần gọi<br>`173:1797` Active=Gọi lại<br>`173:1808` Active=Đã xong|Đã đọc context/ảnh mọi hàng; kết nối CHANGE_TO chưa xác minh.|

20 variants trên là trạng thái active của tab, không phải20 empty/error/loading variants. Counts mẫu: C02 222/12/36/1.126/105; C04 87/23/4/2; C05 120/3/9; C06 1/2/không count; C08 36/6/18. Tất cả phải lấy backend đúng định nghĩa.

| Loại trạng thái/thành phần | Kết quả audit |
|---|---|
| Default | FOUND: cả8 màn có dữ liệu mẫu, C02/C04 đang chọn một row/panel |
| Confirmation | FOUND:4 modal gồm deactivate; inline lockC05 và rejection mở rộngC06; supplementC02 là form mở rộng. Không tạo thêm modal approve/reject nếu chưa có thiết kế |
| Disabled | FOUND: C07 “Công bố kỳ”164:1113 + “Mở khi cả 3 đại lý đã kiểm tra xong”164:1111; step3gray. C03 [Duyệt]/[Từ chối] nằm trong **Facebook minh họa**, không phải control ứng dụng có quyền thao tác Facebook |
| Checked/unchecked | FOUND: C02 checklist3dòng, 2checked/1unchecked trong ảnh; C03 phone/platechecked, nameunchecked; C08 câu1/2Đúng active, câu3chưa chọn; kết quả Đúng thông tin active. Persist/gating cụ thể BLOCKED |
| Success/progress | FOUND:18 toast; C04activebadge; C07 đang kiểm tra/đã công bố/đạt/đang gọi/vượt ngưỡng/đôn lên; C08 statuses và progress18/60 |
| Loading | NOT FOUND màn/skeleton/spinner/button-loading riêng. Toast “Đang tải…” và “Đang xuất…” là feedback có chữ đang, không đủ chứng minh loading variant điều khiển |
| Empty | NOT FOUND Admin list/detail/lookup-no-match/không có kỳ/call-empty designs hoặc copy |
| Error | NOT FOUND Admin validation/submission-error/network/403/409/429/toast-error designs/copy. Global Input/Error7:38 có trong Components nhưng không thấy Admin instance sử dụng; không tự coi là đặc tả lỗi đầy đủ |
| Dropdown | FOUND control đóng: C02 Dealer/source/date và reason; C04province/sort; C05Dealer/role; C06reason; modal Dealer province; modal khách model/year/province/dealer. Menu mở/options đầy đủ/search state **NOT FOUND** |
| Tooltip | NOT FOUND tooltip frame/nội dung. Iconinfo/flag/bell/ellipsis không chứng minh cótooltip |
| User menu / ellipsis | FOUND affordance sidebar user chevron, C05rowellipsis; popup/menu items khi mở **NOT FOUND** |
| Modal đóng/cancel | FOUND icon× và Huỷ trên4 modal; Huỷ inline3vùng; reset/dirty prompt/Escape/backdrop/focus/animation/auto-close **BLOCKED reactions** |
| Reject riêng C02 | Button Từ chối FOUND; modal/expanded form riêng và danh sách lý do **NOT FOUND**, không mượn rejection form C06 vì khác đối tượng |
| Sai thông tin C08 | Warning165:1078 nói “sẽ hỏi xác nhận trước”; **dialog xác nhận và wording/buttons NOT FOUND** |
| Publish C07 | Nút disabled FOUND; modal confirm/publish-success-toast/undo publish **NOT FOUND** |
| Refresh/redirect | Figma runtime rules **BLOCKED**. Laravel hiện dùng POST-redirect-GET/back, inline.js pageshow refresh; không coi đó là hành vi prototype đã audit |

### Navigation và href đọc được, không suy đoán edge
- Sidebar link “Bảng xếp hạng” node159:292 trong mọi instance; C01shortcut160:386; C04rank163:707: context có href Figma proto đến **151:303 (B02 Mobile)**, target_blank. Runtime phải map route leaderboard thật theo quyết định thiết kế, không nhúng URL prototype.
- C07 “Xem trước trang vinh danh”164:978: href proto đến **151:441 (B03 Mobile)**, target_blank; chưa chứng minh preview draft có quyền riêng hoặc public preview an toàn.
- C02 “Mở Facebook”161:565: href `https://www.facebook.com/groups/`, target_blank; C03 “Mở FB trong hồ sơ”162:551: href `https://www.facebook.com/`, target_blank. Đây là generic link thiết kế, không phải URL group/profile khách thật. Backend có normalized profile URL; group URL chính thức cần cấu hình/xác nhận.
- Nav còn lại và click đổi tab cóaffordance/câu chữ nhưng không cóhref đích trong context. Không gán NAVIGATE/CHANGE_TO/OPEN_OVERLAY/AFTER_TIMEOUT cụ thể khi chưa đọc reactions.

## C. User flow
**17nhóm luồng nghiệp vụ INTENT**, không phải17prototype edge đã xác minh. Tên/action lấy từ Figma, kết quả backend ghi rõ hiện có/chưa có.

| Flow | Điểm bắt đầu | Hành động | Modal/Toast/Trang tiếp theo | Điều kiện | Kết quả |
|---|---|---|---|---|---|
|F01|Sidebar/C01|Nav, Bắt đầu duyệt, Mở danh sách, KPI, Lối tắt|C01–C08/leaderboard theo label; internal destinations BLOCKED|Admin active; count/query thật|Read-only route/filter mapping, không tạo page giả cho C03/C08 |
|F02|C02|Chọn tab, q, Dealer/source/date/flag; chọn row; previous/next|Panel161:536; pagination|5workflowtab khác moderation3status; giữfilter khi đổirow|Backend có query cơ bản; thiếu source/plate/flags/workflowtab |
|F03|C02|Xác minh; Hoàn tác (24 giờ)|Toast173:2006|Backend approval pending/identity guard; ý nghĩa3 checkbox cần chốt|Approve có; undo backend/design confirmation thiếu |
|F04|C02|Yêu cầu bổ sung → Lý do/note → Huỷ/Gửi yêu cầu|161:634 →173:2013|Public reason whitelist khác reasonFigma; không lộ admin_note|State/audit có; mail/Salesnotification và typedreason mới thiếu |
|F05|C02|Từ chối|173:2020|Pending, reason theo contract; UIformconfirmNOT FOUND|Reject action có; không bỏ confirmation backend cũ mà chưa giải quyết thiết kế |
|F06|C02|Sửa liên hệ/ghi nhận → Huỷ/Lưu|206:1169 →206:1245|edit_version + request fields; reassignment chỉ pending|Edit/audit có; FB/year/ward mismatch cần quyết định |
|F07|C03|Nhập SĐT/Enter → đối chiếu → Xác minh thành viên hoặc yêu cầu bổ sung|Kết quả162:528,173:2028 hoặc liên hệC02supplement|Admin, exactnormalizedlookup; Facebook mở riêng, không autoduyệt Facebook|Lookup đặc thù thiếu; group evidence action tách moderation/score |
|F08|C04|Thêm đại lý → Huỷ/Tạo|206:1029 →206:1227|code unique, slug server, validation request|Create có; fields mới cần request/UI integration |
|F09|C04|Sửa Dealer → Huỷ/Lưu|206:1086 →206:1233|Stable slug/QR, không mất fields cũ|Update có; giờ/link contact requestchưa nhận |
|F10|C04|Ngừng tham gia → Huỷ/Ngừng tham gia|206:1143 →206:1239|Khôngdelete, giữhistory; ruleeligibility cần chốt|is_active có; Figma futureperiodexclude chưađượcLeaderboard enforce |
|F11|C04|Copylink/QRPNG/StandeePDF|206:1274/173:2037/173:2044|Canonical URL thật; authorized PDF Admin|PNG có, PDF service có nhưng Adminroute thiếu |
|F12|C05|Tab/search/Dealer/role; Xuất danh sách|Table163:961; toast173:2052|Scope includeManager và rate cao cần chốt|List Sales có; export/roleunion/metrics thiếu |
|F13|C05|Ellipsis → inline khoá → Huỷ/Khoá tài khoản|163:1022 →173:2059|OnlySales active→blocked hiện có; repeated guards|Lock/reopenbackend giữ; menuitems/unlockFigmaNOT FOUND |
|F14|C06|Manager Duyệt hoặc Từ chối → reason/note → Huỷ/Gửi từ chối|164:789;173:2066/2073|Admin canreviewManager; pending + Dealeractive + uniqueness|State/history có; emaildelivery và systemchecks thiếu |
|F15|C06|Sales chờ>3 ngày → Nhắc quản lý/Duyệt thay|173:2080/2088|Rule3 ngày + recipient + cooldown/overridepermission cần chốt|Reminder/override thiếu; AdminSalesreview hiệnbịpolicycấm |
|F16|C07|Chọn kỳ → preview → Mở danh sách gọi → Công bố kỳ|C08 theo INTENT; public B03href; publishconfirmationNOT FOUND|ThángcloseVN, top3complete, threshold[x]%TMV chưa chốt|Draft/preview có; callgate/publish/exclusion thiếu |
|F17|C08|Chọn khách → gọi/Đúng/Sai → result/note → Lưu|173:2097 khi đúng; nextcustomer; sai confirmationNOT FOUND|Sampling/persistence; retry1ngày,max2noanswer; deduction phải chốt|Chưa có call workflow, không tái dùng reject để trừđiểm |

## D. Gap analysis
P0 = chốt trước bất kỳ thay đổi nghiệp vụ; P1 = UI/read integration ưu tiên; P2 = phase sau dependency.

| Screen | UI thiếu | Backend thiếu/khác | Rủi ro | Phụ thuộc | Ưu tiên |
|---|---|---|---|---|---|
|All|Admin shell, modal/toast đúng bộ, mobile/error/empty/menu thiết kế chưa có|Không có common Admin nav/shell; generic x-layout hiện dùng public-styleheader|Ảnh hưởng Sales/Dealer nếu sửa shared globally; tự tạo UI sai|Prototype/thiết kế thiếu; shell riêng|P0/P1|
|C01|Task cards,2KPIchính/phụ, layout5stats/hàng, overdue/date/tab internal|Chưa có oldest pending/48 giờ/pending Managers/>3daySales/approved tháng/Dealers ≥5 aggregator; top/month đã có; recent pending hiện DESC, còn ưu tiên flag/oldest là yêu cầu riêng của C02|Sai cohort/clock/timezone; hardcode mẫu|LeaderboardService/DemoSubmissionScope + quyết định KPI|P1|
|C02|Master/detail,workflowtabs,flag sorting/checklist, editmodal, toastundo|Statusfilter chỉpending/approved/rejected; qchưa plate, filter nguồn/flag địa lý/tần suất thiếu; 3 checkbox không có payload tương đương; approve không undo; verification riêng|Gộpgroup→score, trùng identities, stale, publicnote leak|Typedworkflowprojection, contract duyệt/sửa|P0/P1|
|C03|Toàn trang lookup + result/history|Không có route/controller exactphone/plate/namecheck/adminrecentlookup; group evidence có sẵn|LộPII, ambiguoustelephone, tựduyệt Facebook hoặc cộngđiểm sai|C02verification; API đọc mới được duyệt|P0/P2|
|C04|4tabs/list-detail/KPImonth/3modals/share|Adminindex chỉq/status, chưaprovince/sort/no-manager/no-customer tabs; showtoàn kỳ; Request không nhậnopening_hours/FB/Zalo dù model có; route PDF Admin thiếu|Slug/QR đổi; deactivate khácfutureperiodexclude; chức năngphoto/accountbackend ngoàiFigma|C04schema hiện tại cần kiểm chứng khi triển khai; stableDealerSlugs|P0/P1|
|C05|3tabs,role/metrics/activity/export/inline lock|Query chỉrole Sales, tableFigma cóManager; highrejectthreshold/window chưa chốt; export/montlymetrics chưa có; last_login_at chỉSales update khilogin|Nới quyềnManager, giảactivity/threshold, bỏ pending/rejected/editflow cũ|UserPolicy+ReviewSalesAccount+scorequeries|P0/P1|
|C06|3tabs/card/systemchecks/reasonselector/reminder|PendingManager queue có; processedhistorytab/overdue query thiếu; no-mailactions; domainofficial/phoneuniquenesscheck thiếu; RegisterRequest không uniquephone; Admin overrideSales bịcấm|Email thànhcông giả; bypasspolicy; biến suy đoán thành verifiedcheck|Quyết địnhauthority/domain/maildelivery|P0/P1|
|C07|Monthrail/3steps/auditstatus/disqualified/promoted/publishdisabled|Award draftweek/month+previewhash+publishedmodel có; không có publishroute/action, callgate, exclusion/replacement, snapshotpublishtransaction|Phá immutability/history/score, mất weekly/Salesinternalresults|C08auditcontract; thresholdTMV + newpublish approvedspec|P0/P2|
|C08|Toàn bộcallworkspace/dialogs còn thiếu|Không tìm thấy routes/models/actions/migration sampling/callattempt/outcome/schedule/deduction|Thu hồiđiểm/gọi lại/cancel/dup-submit khôngreversible đơn giản|Businessapproval + isolation/schema đề xuất task riêng, C07|P0/P2|

### Backend/policy/validation phải giữ nguyên
- Admin routes trong auth + auth.session; admin prefix thêm role:admin/account.active/throttle:admin-writes; GET/HEAD không hạn mức viết, write60/phút/user. /admin/dashboard córole/active riêng. CSRF Laravel giữ nguyên. Policy đều kiểm tra Admin active khi đúng ability.
- CustomerSubmissionPolicy dùng **review** cho Admin, không dùng view (view chỉ Sales/Manager scope). Evidence private endpoint và authorization không đổi.
- ApproveCustomerSubmission: mutex/row lock, pending-only, phone/plate/Facebook duplicate checks, QR bắt buộc verified_facebook_url cá nhân; review/audit; không có undo. Reject pending-only, reason/admin_note tách biệt; không dùngreject cho revoke approved.
- SubmissionVerification: group/blocked/supplement độc lập moderation; reason whitelist chỉ2 code; HMAC/version + profileevidence; supplement response có ownershipreceipt. Controller trả “Trạng thái duyệt và thành tích được giữ nguyên.” Không gọi “+1 điểm” cho group-onlyupdate.
- UpdateCustomerSubmission: HMAC edit_version/edit_submission_id, phone mutex/duplicate guard, audit masked; reassignment chỉpending, Dealer/Saleskhớp, dealer_qr bắtbuộcsales_idnull; cấm facebook_url/status/points/... trong editrequest. Modal Figma Facebook editable và Năm đăng ký khác request; QR còn requiredaddress_line/province/ward/vehicle_description, Sales requiredvehicle_model. Không bỏ validationfields để làmformđẹp.
- DealerRequest: name/code required, code unique(ignorecurrent), is_active requiredboolean; province/phone/address optional; photo5MB/4096px allowed; slug/photo_path forbidden. Model cóopening_hours/facebook_url/zalo_url nhưng Request chưa nhận; đây là write-contract gap, không mặc định cần addcolumns. Slug immutable nên đổicode không đổiQR.
- C04deactivate: is_active giữhistory, publicdirectorylọcactive; LeaderboardService scorequeries **không lọc Dealer is_active**; landing inactive còn phục vụ lịch sử/luồnginactive, không thể hứaHTTPerror hoặc futureperiodexclude nếu chưa chốt. Public snapshot giữ nguyên.
- UserPolicy::reviewRegistration cho Sales chỉ cùngDealer Manager active; Admin **không được**. Admin cómoderateSales block/reactivate; Managerregistration cóAdmin reviewquaDealerPolicy. ReviewSalesAccount approve/reject delegate cùngpolicy, không là đườngvòng.
- ReviewAccountRegistration pending→active/rejected và audit, rejectedresubmit riêng, Dealer lock/active/unique Manager; không gửi email. Không tự hardcode green “email đúng tên miền” hoặc “phone chưa dùng” vì chưa có evaluator tương ứng.
- C05 last_login_at hiện auth chỉ ghi khi **Sales** đăng nhập thành công; Manager activity không có cùng bảo đảm. Điểm do approved submissions theo submitted_at, Dealer snapshot, rank ROW_NUMBER scoreDESC/achieved_atASC/idASC; không đổi rule.
- Awards: AwardPeriod hỗ trợweekly vàmonthly; CreateAwardDraft firstOrCreate; AwardResults previewtop3Sales+Dealers/fingerprint; published_at/winners immutable. PublicAwardResults chỉpublishedDealer snapshots, không publicSales/PII/draft. AwardPolicy::publish tồn tại **không có nghĩa** đã có action/route công bố.
- Existing admin extra features không xuất hiện Figma (photoDealer, Manageraccountcreate/edit, Salesedit/reopen, weeklydrafts, moderationnotes/evidence/fulladdress): **không xóa**; cần quyết định entry point/design khi di chuyển, không nhét section/modal tự viết.
- SMTP runtime/delivery, actualDBschema và tests hiện hành chưa chạy ở task này; source-level “có” không đồng nghĩa luồng đã PASS.

## Hồ sơ từng màn: bố cục, wording và node tra cứu
Các bảng dưới lưu **toàn bộ text layer trong metadata**, controls có tên và instance labels đọc từ context. Các giá trị mẫu là reference, không payload production. Text node mà layer name khác render được ghi chú; header “Text”163:976/164:1063 thực tế **rỗng/U+200B**, không render chữText.

### C01 · Tổng quan — 160:233

C01: content1128; task cards552/276/276 gap12; 2KPI558/558; 5stats216 mỗi hàng; recentpending756 + rankings348 gap24. KPI số chính tháng, overdue48 giờ và counts cần backend thật; tháng/ngày mẫu không hard-code.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|160:324|Page head|32,28|1128×82|
|160:319|Button · Thứ Hai, 26/10/2026|945,44|183×38|
|160:329|Việc cần làm|32,134|1128×270|
|160:330|Duyệt khách hàng|0,0|552×270|
|205:1273|Quá hạn|28,152|183×28|
|160:341|Button · Bắt đầu duyệt|28,200|156×42|
|160:345|Duyệt tài khoản|564,0|276×270|
|160:362|Link · Duyệt tài khoản|25,227|106×18|
|160:366|Lối tắt|852,0|276×270|
|160:369|Lối tắt · Đại lý|13,53|250×52|
|160:378|Lối tắt · Tạo kỳ vinh danh|13,109|250×52|
|160:386|Lối tắt · Xem bảng xếp hạng|13,165|250×52|
|160:460|Số liệu|32,484|1128×370|
|205:1252|Chỉ số chính|0,0|1128×132|
|205:1253|KPI · Khách đã xác minh tháng này|0,0|558×132|
|205:1263|KPI · Đại lý có từ 5 khách đã xác minh|570,0|558×132|
|160:396|Stats 1|0,144|1128×96|
|160:397|Stat · Tổng đại lý|0,0|216×96|
|160:403|Stat · Đại lý hoạt động|228,0|216×96|
|160:409|Stat · Tổng sales|456,0|216×96|
|160:415|Stat · Sales hoạt động|684,0|216×96|
|160:421|Stat · Sales chờ duyệt|912,0|216×96|
|160:427|Stats 2|0,252|1128×118|
|160:428|Stat · Tổng khách hàng|0,0|216×118|
|160:434|Stat · Khách đã duyệt|228,0|216×118|
|160:440|Stat · Chờ xác minh|456,0|216×118|
|160:446|Stat · Khai báo tuần này|684,0|216×118|
|160:453|Stat · Khai báo tháng này|912,0|216×118|
|160:461|Hàng chờ & dẫn đầu|32,878|1128×394|
|160:462|Chờ xác minh gần đây|0,0|756×394|
|160:465|Link · Xem hàng chờ|575,20|159×18|
|160:470|Hàng · Vũ Minh Quân|1,56|754×66|
|160:480|src|443,21|120×24|
|160:482|Button · Duyệt|668,17|66×32|
|160:485|Hàng · Đặng Quốc Bảo|1,123|754×66|
|160:495|src|441,21|120×24|
|160:497|Button · Duyệt|668,17|66×32|
|160:500|Hàng · Nguyễn Ngọc Mai|1,190|754×66|
|160:510|src|443,21|120×24|
|160:512|Button · Duyệt|668,17|66×32|
|160:515|Hàng · Hoàng Minh Quân|1,257|754×66|
|160:525|src|445,21|120×24|
|160:527|Button · Duyệt|668,17|66×32|
|160:529|Dẫn đầu tháng này|768,0|360×394|
|160:537|Hạng 1|25,147|310×58|
|160:546|Hạng 2|25,219|310×78|
|160:553|Hạng 3|25,311|310×58|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|160:328|KHÔNG GIAN QUẢN TRỊ|
|160:326|Xin chào, Development Admin|
|160:327|Theo dõi chương trình và xử lý các khai báo đang chờ duyệt.|
|160:323|Thứ Hai, 26/10/2026|
|160:334|Duyệt khách hàng|
|160:336|222|
|160:338|khách chờ xác minh|
|160:339|Cũ nhất: 01/05/2026 · 178 ngày|
|205:1277|37 khai báo quá 48 giờ|
|160:344|Bắt đầu duyệt|
|160:352|Duyệt tài khoản|
|160:354|1|
|160:355|quản lý đại lý chờ duyệt|
|160:360|Thêm 2 sales chờ quá 3 ngày|
|160:363|Mở danh sách|
|160:368|Lối tắt|
|160:375|Đại lý|
|160:383|Tạo kỳ vinh danh|
|160:390|Xem bảng xếp hạng|
|160:394|Tổng quan chương trình|
|160:395|Bấm vào số để mở danh sách đã lọc|
|205:1256|CHỈ SỐ CHÍNH|
|205:1257|Khách đã xác minh tháng này|
|205:1259|184|
|205:1260|khách · tháng 10/2026|
|205:1266|CHỈ SỐ PHỤ|
|205:1267|Đại lý có từ 5 khách đã xác minh|
|205:1269|21|
|205:1270|/ 87 đại lý tham gia|
|160:399|Tổng đại lý|
|160:402|87|
|160:405|Đại lý hoạt động|
|160:408|87|
|160:411|Tổng sales|
|160:414|120|
|160:417|Sales hoạt động|
|160:420|120|
|160:423|Sales chờ duyệt|
|160:426|0|
|160:430|Tổng khách hàng|
|160:433|1.501|
|160:436|Khách đã duyệt|
|160:439|1.126|
|160:442|Chờ xác minh|
|160:445|222|
|160:448|Khai báo tuần này|
|160:451|0|
|160:452|Mọi trạng thái|
|160:455|Khai báo tháng này|
|160:458|251|
|160:459|Mọi trạng thái|
|160:464|Chờ xác minh gần đây|
|160:466|Xem toàn bộ hàng chờ|
|160:472|Vũ Minh Quân|
|160:473|00****0123|
|160:475|Toyota Thái Nguyên|
|160:476|[DEMO] Phạm Quốc Bảo|
|160:481|20/10 08:13|
|160:483|Duyệt|
|160:487|Đặng Quốc Bảo|
|160:488|00****0456|
|160:490|Toyota Quảng Ninh – Cẩm Phả|
|160:491|Khách tự đăng ký|
|160:496|20/10 03:08|
|160:498|Duyệt|
|160:502|Nguyễn Ngọc Mai|
|160:503|00****0789|
|160:505|Toyota Đông Sài Gòn – CN Q9|
|160:506|[DEMO] Lê Ngọc Hân|
|160:511|19/10 20:59|
|160:513|Duyệt|
|160:517|Hoàng Minh Quân|
|160:518|00****0321|
|160:520|Toyota Thái Nguyên|
|160:521|[DEMO] Phạm Quốc Bảo|
|160:526|19/10 10:23|
|160:528|Duyệt|
|160:530|Dẫn đầu tháng này|
|160:531|01/10 – 31/10/2026 · Chỉ tính khai báo đã duyệt|
|160:543|Toyota Thái Nguyên|
|160:544|TMV-724|
|160:545|67|
|160:547|2|
|160:550|Toyota Lý Thường Kiệt – CN Tân Phú|
|160:551|TMV-684|
|160:552|9|
|160:554|3|
|160:557|Toyota Nankai Hải Phòng|
|160:558|TMV-972|
|160:559|8|

Label từ component instance (metadata thường không bung text con): “Sales khai báo”; “QR đại lý”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C02 · Duyệt khách hàng — 161:322

C02: list500 + detail612 gap16; title+tabs; hàngfilter; detail có warning geo/burst, duplicatecheck, checklist,2cột liên hệ/attribution và actions; inline supplement đang mở. Không có riêng rejectionmodal hoặc undoconfirm.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|161:406|Page head|32,28|1128×60|
|161:410|Toolbar|32,108|1128×154|
|161:427|Lọc|0,54|1128×100|
|161:428|Field · Tên, SĐT, biển số, Facebook|0,30|300×40|
|161:433|Field · Tất cả đại lý|308,30|180×40|
|161:437|Field · Tất cả nguồn|496,30|160×40|
|161:441|Field · 01/05 – 26/10/2026|664,30|190×40|
|161:446|Button · Chỉ ca bị gắn cờ · 18|953,31|175×38|
|161:450|Split|32,282|1128×1011|
|161:451|Danh sách|0,0|500×557|
|161:452|Header|1,1|498×34|
|161:457|Khách · Lý Minh Quân|1,36|498×82|
|161:471|Khách · Huỳnh Mỹ Duyên|1,119|498×82|
|161:484|Khách · Bùi Hoài Nam|1,202|498×66|
|161:495|Khách · Bùi Hải Yến|1,269|498×82|
|161:506|Khách · Lý Đức Long|1,352|498×66|
|161:517|Khách · Phạm Hồng Nhung|1,419|498×82|
|161:528|Phân trang|1,502|498×54|
|161:536|Chi tiết · Lý Minh Quân|516,0|612×1011|
|161:550|Gắn cờ|25,77|562×98|
|161:556|Không trùng|25,195|562×20|
|161:562|Đối chiếu|25,256|562×174|
|161:565|Link · Mở Facebook|459,0|103×18|
|161:569|Tick|0,30|562×40|
|161:574|Tick|0,82|562×40|
|161:579|Tick|0,134|562×40|
|161:583|Thông tin|25,471|562×166|
|161:584|LIÊN HỆ & XE|0,0|269×166|
|161:587|Sửa|227,0|42×18|
|161:592|Điện thoại|0,28|269×18|
|161:595|Địa chỉ|0,56|269×18|
|161:601|Facebook|0,130|269×36|
|161:604|GHI NHẬN CHO|293,0|269×166|
|161:607|Sửa|227,0|42×18|
|161:612|Đại lý|0,28|269×36|
|161:615|Sales|0,74|269×18|
|161:618|Nguồn|0,102|269×18|
|161:621|Thời điểm|0,130|269×36|
|161:625|Quyết định|25,678|562×42|
|161:626|Button · Xác minh|0,0|121×40|
|161:630|Button · Yêu cầu bổ sung|129,0|150×42|
|161:632|Button · Từ chối|287,0|89×42|
|161:634|Yêu cầu bổ sung|25,740|562×246|
|161:636|Field · Biển số không khớp câu trả lời trong group|17,47|528×40|
|161:640|Ghi chú|17,99|528×80|
|161:648|Button · Huỷ|361,0|56×38|
|161:650|Button · Gửi yêu cầu|425,1|103×36|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|161:408|Duyệt khách hàng|
|161:409|Ca bị gắn cờ xếp trước, sau đó tới khai báo cũ nhất.|
|161:432|Tên, SĐT, biển số, Facebook|
|161:434|Tất cả đại lý|
|161:438|Tất cả nguồn|
|161:442|01/05 – 26/10/2026|
|161:449|Chỉ ca bị gắn cờ · 18|
|161:453|KHÁCH HÀNG|
|161:454|ĐẠI LÝ / SALES|
|161:455|THỜI ĐIỂM|
|161:464|Lý Minh Quân|
|161:465|00****0105|
|161:467|Toyota Kiên Giang|
|161:468|[DEMO] Võ Quốc Bảo · Khai báo tay|
|161:469|03/05 22:00|
|161:477|Huỳnh Mỹ Duyên|
|161:478|00****0197|
|161:480|Toyota Lào Cai|
|161:481|[DEMO] Hoàng Ngọc Hân · Link sales|
|161:482|01/05 11:50|
|161:488|Bùi Hoài Nam|
|161:489|00****0032|
|161:491|Toyota Thanh Xuân|
|161:492|Khách tự đăng ký · QR đại lý|
|161:493|01/05 19:06|
|161:499|Bùi Hải Yến|
|161:500|00****0151|
|161:502|Toyota Hà Tĩnh|
|161:503|[DEMO] Dương Quốc Bảo · Khai báo tay|
|161:504|02/05 16:55|
|161:510|Lý Đức Long|
|161:511|00****0224|
|161:513|Toyota Long Thành|
|161:514|Khách tự đăng ký · QR đại lý|
|161:515|04/05 19:48|
|161:521|Phạm Hồng Nhung|
|161:522|00****0059|
|161:524|Toyota Thanh Xuân|
|161:525|[DEMO] Huỳnh Minh Anh · Link sales|
|161:526|05/05 03:05|
|161:529|1–20 / 222|
|161:539|Lý Minh Quân|
|161:543|1 / 222|
|161:554|Bị gắn cờ: cần xem kỹ|
|161:555|• Tỉnh khách (Hà Nội) cách xa tỉnh đại lý (Kiên Giang) • Sales này có 6 khai báo trong 15 phút|
|161:560|Không trùng SĐT, biển số, Facebook.|
|161:564|ĐỐI CHIẾU GROUP FACEBOOK|
|161:566|Mở Facebook|
|161:573|Đã thấy trong yêu cầu vào group / thành viên group|
|161:578|Câu trả lời SĐT khớp: 00****0105|
|161:581|Câu trả lời biển số khớp: 68A-xxx.xx|
|161:586|LIÊN HỆ & XE|
|161:591|Sửa|
|161:593|Điện thoại|
|161:594|0000700105|
|161:596|Địa chỉ|
|161:597|Hà Nội · [Phường/Xã]|
|161:599|Xe|
|161:600|Toyota Hilux · 2024 · biển 68A-xxx.xx|
|161:602|Facebook|
|161:603|facebook.com/toyota.demo…0105|
|161:606|GHI NHẬN CHO|
|161:611|Sửa|
|161:613|Đại lý|
|161:614|Toyota Kiên Giang · TMV-1016|
|161:616|Sales|
|161:617|[DEMO] Võ Quốc Bảo|
|161:619|Nguồn|
|161:620|Khai báo tay|
|161:622|Thời điểm|
|161:623|03/05/2026 22:00 (giờ VN)|
|161:629|Xác minh|
|161:631|Yêu cầu bổ sung|
|161:633|Từ chối|
|161:635|Lý do|
|161:637|Biển số không khớp câu trả lời trong group|
|161:641|Ghi chú thêm cho khách / sales (tuỳ chọn)|
|161:647|Khách xem được lý do này ở trang tra cứu.|
|161:649|Huỷ|
|161:651|Gửi yêu cầu|

Label từ component instance (metadata thường không bung text con): “Chờ xác minh”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C03 · Tra SĐT vào group — 162:403

C03: Facebookillustration420 + lookup/detail692 gap16; lookup/result642; minh họa Facebook không phải iframe/FBSDK hay control ứng dụng; recentprocessed card riêng.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|162:487|Page head|32,28|1128×60|
|162:491|Split|32,108|1128×677|
|162:492|Facebook (minh hoạ)|0,0|420×392|
|162:494|Yêu cầu tham gia nhóm|0,26|420×322|
|162:496|Người|21,57|378×40|
|162:500|Câu|21,113|378×60|
|162:503|Câu|21,189|378×60|
|162:506|Nút|21,265|378×36|
|162:507|Button · [Duyệt]|0,0|185×36|
|162:509|Button · [Từ chối]|193,0|185×36|
|162:511|cap|0,360|420×32|
|162:516|Công cụ|436,0|692×677|
|162:517|Tra SĐT|0,0|692×514|
|162:519|Ô tra cứu|25,63|642×60|
|162:524|Enter|557.5,14|75×32|
|162:528|Kết quả|25,139|642×298|
|162:538|Tick|19,73|604×40|
|162:543|Tick|19,127|604×40|
|162:548|Tick|19,181|604×40|
|162:551|Link · Mở FB trong hồ sơ|461,11|131×18|
|162:555|Quyết định|19,235|604×44|
|162:556|Button · Xác minh thành viên|0,0|297×42|
|162:560|Button · Không khớp · Yêu cầu bổ sung|305,0|299×44|
|162:562|note|25,453|642×36|
|162:567|Vừa xử lý|0,530|692×147|
|162:570|Trần Minh Đức|25,41|642×48|
|162:581|Lê Thị Hoa|25,90|642×48|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|162:489|Tra SĐT vào group|
|162:490|Dành cho admin group Facebook: mỗi yêu cầu vào group, dán SĐT khách trả lời để đối chiếu.|
|162:493|CỬA SỔ FACEBOOK · MINH HOẠ|
|162:495|Yêu cầu tham gia nhóm|
|162:497|A|
|162:499|[Tên Facebook của khách]|
|162:501|Câu 1 · Số điện thoại đã đăng ký?|
|162:502|0901 234 567|
|162:504|Câu 2 · Biển số xe?|
|162:505|51K-123.45|
|162:508|[Duyệt]|
|162:510|[Từ chối]|
|162:515|Admin group mở song song: Facebook bên trái, công cụ tra cứu bên phải.|
|162:518|Tra SĐT khi duyệt vào group|
|162:523|0901 234 567|
|162:527|Enter|
|162:530|NA|
|162:533|Nguyễn Văn An|
|162:534|Toyota An Sương · [DEMO] Trần Thu Hà · Link sales|
|162:542|SĐT khớp hồ sơ|
|162:547|Biển số khớp: 51K-123.45|
|162:550|Tên Facebook giống hồ sơ?|
|162:552|Mở FB trong hồ sơ|
|162:559|Xác minh thành viên|
|162:561|Không khớp · Yêu cầu bổ sung|
|162:566|Xác minh xong thì bấm [Duyệt] bên Facebook. Chưa tìm thấy SĐT thì giữ yêu cầu chờ, khách có thể đang điền form; quá 48 giờ vẫn chưa có hồ sơ thì nhắn khách đăng ký.|
|162:569|VỪA XỬ LÝ|
|162:571|Trần Minh Đức|
|162:579|10:42|
|162:582|Lê Thị Hoa|
|162:590|10:39|

Label từ component instance (metadata thường không bung text con): “Chờ vào group”; “Đã xác minh”; “Cần bổ sung”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C04 · Đại lý — 163:488

C04: list692 + Dealerpanel420 gap16;4tabs,filters,statcards3ô; QR/link/download/deactivategroup; list5cols+pagination. ModalCRUD tách ngoài main.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|163:576|Page head|32,28|1128×60|
|163:572|Button · Thêm đại lý|992,20|136×40|
|163:580|Toolbar|32,108|1128×42|
|163:594|Lọc|32,170|1128×40|
|163:595|Field · Tên hoặc mã đại lý|0,0|280×40|
|163:600|Field · Tất cả tỉnh/thành|288,0|180×40|
|163:604|Field · Sắp xếp: tháng này|476,0|180×40|
|163:608|Split|32,230|1128×712|
|163:644|Danh sách đại lý|0,0|692×414|
|163:645|Header|1,1|690×34|
|163:657|Row 1|1,36|690×66|
|163:664|Row 2|1,103|690×66|
|163:671|Row 3|1,170|690×66|
|163:678|Row 4|1,237|690×66|
|163:685|Row 5|1,304|690×66|
|163:692|Phân trang|1,371|690×42|
|163:695|Chi tiết · Toyota An Sương|708,0|420×712|
|163:704|Đã xác minh tháng này|0,0|118×80|
|163:707|Hạng tháng này|126,0|118×80|
|163:710|Sales đang làm|252,0|118×80|
|163:716|Sửa|328,0|42×18|
|163:722|Địa chỉ|0,0|370×18|
|163:725|Điện thoại|0,28|370×18|
|163:728|Giờ mở cửa|0,56|370×18|
|163:731|Facebook, Zalo|0,84|370×18|
|163:734|Quản lý|0,112|370×18|
|163:740|Link|25,461|370×38|
|163:748|Tải|25,519|370×40|
|163:749|Button · QR (PNG)|0,0|181×40|
|163:753|Button · Standee (PDF)|189,0|181×40|
|163:757|Ngừng tham gia|25,579|370×108|
|163:761|Button · Ngừng tham gia|222,35|131×38|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|163:578|Đại lý|
|163:579|87 đại lý · 85 đang tham gia|
|163:575|Thêm đại lý|
|163:599|Tên hoặc mã đại lý|
|163:601|Tất cả tỉnh/thành|
|163:605|Sắp xếp: tháng này|
|163:647|ĐẠI LÝ|
|163:649|TỈNH|
|163:651|QUẢN LÝ|
|163:653|THÁNG NÀY|
|163:655|TỔNG|
|163:610|Toyota An Sương|
|163:611|TMV-405|
|163:612|TP. HCM|
|163:613|[DEMO] Trần Thu Hà|
|163:614|5|
|163:615|5|
|163:617|Toyota Thái Nguyên|
|163:618|TMV-724|
|163:619|Thái Nguyên|
|163:620|[DEMO] Lý Văn Đức|
|163:621|67|
|163:622|87|
|163:624|Toyota Bến Thành|
|163:625|TMV-408|
|163:626|TP. HCM|
|163:627|[DEMO] Vũ Thị Lan|
|163:628|8|
|163:629|41|
|163:631|Toyota Cà Mau|
|163:632|TMV-1031|
|163:633|Cà Mau|
|163:634|Chưa có|
|163:635|0|
|163:636|0|
|163:638|Toyota Lạng Sơn|
|163:639|TMV-1002|
|163:640|Lạng Sơn|
|163:641|[DEMO] Hồ Gia Bảo|
|163:642|0|
|163:643|3|
|163:693|1–20 / 87|
|163:694|Trang sau ›|
|163:698|Toyota An Sương|
|163:702|TMV-405 · TP. Hồ Chí Minh|
|163:705|5|
|163:706|Đã xác minh tháng này|
|163:708|#12|
|163:709|Hạng tháng này|
|163:711|6|
|163:712|Sales đang làm|
|163:715|THÔNG TIN ĐẠI LÝ|
|163:720|Sửa|
|163:723|Địa chỉ|
|163:724|[Địa chỉ đại lý]|
|163:726|Điện thoại|
|163:727|[SĐT]|
|163:729|Giờ mở cửa|
|163:730|[Giờ mở cửa]|
|163:732|Facebook, Zalo|
|163:733|[Link Facebook] · [Link Zalo]|
|163:735|Quản lý|
|163:736|[DEMO] Trần Thu Hà · thuha@…|
|163:739|MÃ QR VÀ STANDEE|
|163:744|…/toyota-an-suong/|
|163:752|QR (PNG)|
|163:756|Standee (PDF)|
|163:759|Ngừng tham gia chương trình|
|163:760|QR sẽ dẫn tới trang báo lỗi. Điểm cũ vẫn giữ.|
|163:762|Ngừng tham gia|

Label từ component instance (metadata thường không bung text con): “Đang tham gia”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C05 · Sales — 163:763

C05: table1128, columnsNGƯỜI/VAI TRÒ/ĐẠI LÝ/THÁNG NÀY/BỊ TỪ CHỐI/HOẠT ĐỘNG + ellipsisblank; inline lockfullwidth1126; Managerrow tồn tại trong bảnSales.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|163:851|Page head|32,28|1128×60|
|163:847|Button · Xuất danh sách|963,18|165×42|
|163:855|Toolbar|32,108|1128×42|
|163:866|Lọc|32,170|1128×40|
|163:867|Field · Tên, email, số điện thoại|0,0|300×40|
|163:872|Field · Tất cả đại lý|308,0|180×40|
|163:876|Field · Tất cả vai trò|496,0|160×40|
|163:961|Danh sách sales|32,230|1128×482|
|163:962|Header|1,1|1126×34|
|163:978|Row 1|1,36|1126×66|
|163:880|who|0,0|210×38|
|163:987|Row 2|1,103|1126×66|
|163:896|who|0,0|190×38|
|163:1022|Xác nhận khoá|1,169|1126×68|
|163:1030|Button · Huỷ|912,15|56×38|
|163:1032|Button · Khoá tài khoản|984,16|122×36|
|163:996|Row 3|1,238|1126×66|
|163:913|who|0,0|185×38|
|163:1005|Row 4|1,305|1126×66|
|163:929|who|0,0|217×38|
|163:1014|Row 5|1,372|1126×66|
|163:945|who|0,0|194×38|
|163:1035|Phân trang|1,439|1126×42|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|163:853|Sales|
|163:854|120 tài khoản đang hoạt động|
|163:850|Xuất danh sách|
|163:871|Tên, email, số điện thoại|
|163:873|Tất cả đại lý|
|163:877|Tất cả vai trò|
|163:964|NGƯỜI|
|163:966|VAI TRÒ|
|163:968|ĐẠI LÝ|
|163:970|THÁNG NÀY|
|163:972|BỊ TỪ CHỐI|
|163:974|HOẠT ĐỘNG|
|163:976|[rỗng/U+200B; layer name là Text]|
|163:881|B|
|163:884|[DEMO] Phạm Quốc Bảo|
|163:885|bao.pham@…|
|163:890|Toyota Thái Nguyên|
|163:891|55|
|163:889|2%|
|163:892|2 giờ trước|
|163:897|B|
|163:900|[DEMO] Võ Quốc Bảo|
|163:901|quocbao.vo@…|
|163:907|Toyota Kiên Giang|
|163:908|6|
|163:906|38%|
|163:909|Hôm qua|
|163:1028|Khoá [DEMO] Võ Quốc Bảo?|
|163:1029|Link mời hết hiệu lực ngay. 47 khách đã mời vẫn tính cho Toyota Kiên Giang. Người này cũng không đăng nhập được nữa.|
|163:1031|Huỷ|
|163:1033|Khoá tài khoản|
|163:914|H|
|163:917|[DEMO] Trần Thu Hà|
|163:918|thuha@…|
|163:923|Toyota An Sương|
|163:924|3|
|163:922|0%|
|163:925|5 phút trước|
|163:930|H|
|163:933|[DEMO] Dương Quốc Bảo|
|163:934|quocbao.duong@…|
|163:939|Toyota Hà Tĩnh|
|163:940|4|
|163:938|10%|
|163:941|3 ngày trước|
|163:946|P|
|163:949|[DEMO] Phan Gia Huy|
|163:950|giahuy@…|
|163:955|Toyota An Sương|
|163:956|0|
|163:954|—|
|163:957|Chưa đăng nhập|
|163:1036|1–20 / 120|
|163:1037|Trang sau ›|

Label từ component instance (metadata thường không bung text con): “Sales”; “Quản lý đại lý”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C06 · Duyệt tài khoản — 164:658

C06: Managerapprovalcard+systemcheckcard2cột; belowoverdueSales fullwidth. Rejection form inline mở;3tabs, riêngvariantĐãxửlý bị sai nhãn.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|164:742|Page head|32,28|1128×60|
|164:746|Toolbar|32,108|1128×42|
|164:756|Split|32,170|1128×633|
|164:757|Người đăng ký · Lâm Văn Tài|0,0|540×633|
|164:766|Thông tin|25,101|490×187|
|164:767|Đại lý|16,4|458×44|
|164:771|Email|16,49|458×44|
|164:775|Điện thoại|16,94|458×44|
|164:779|Đăng ký|16,139|458×44|
|164:782|Quyết định|25,308|490×42|
|164:783|Button · Duyệt|0,0|240×40|
|164:787|Button · Từ chối|248,0|242×42|
|164:789|Từ chối · mở rộng|25,370|490×238|
|164:791|Field · Không phải nhân viên của đại lý|17,47|456×40|
|164:795|Ghi thêm|17,99|456×72|
|164:798|Button · Huỷ|294,0|56×38|
|164:800|Button · Gửi từ chối|358,0|98×36|
|164:802|Hệ thống kiểm tra giúp|556,0|572×314|
|164:804|Đạt|25,53|522×46|
|164:809|Đạt|25,111|522×46|
|164:814|Thông tin|25,169|522×64|
|164:819|Lưu ý|25,245|522×44|
|164:823|Sales chờ quá 3 ngày|32,823|1128×210|
|164:828|[DEMO] Đỗ Thu Hà|1,76|1126×66|
|164:840|Button · Nhắc quản lý|859,15|134×36|
|164:845|Button · Duyệt thay|1009,16|97×34|
|164:848|[DEMO] Hồ Minh Anh|1,143|1126×66|
|164:860|Button · Nhắc quản lý|859,15|134×36|
|164:865|Button · Duyệt thay|1009,16|97×34|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|164:744|Duyệt tài khoản|
|164:745|Admin duyệt quản lý đại lý. Sales do quản lý đại lý của họ duyệt.|
|164:759|L|
|164:762|[DEMO] Lâm Văn Tài|
|164:768|Đại lý|
|164:769|Toyota Cà Mau|
|164:772|Email|
|164:773|tai.lam@toyotacamau.vn|
|164:776|Điện thoại|
|164:777|0939 xxx 214|
|164:780|Đăng ký|
|164:781|25/10/2026, 15:40|
|164:786|Duyệt|
|164:788|Từ chối|
|164:790|Lý do từ chối (người đăng ký sẽ thấy)|
|164:792|Không phải nhân viên của đại lý|
|164:796|Ghi thêm (tuỳ chọn)|
|164:799|Huỷ|
|164:801|Gửi từ chối|
|164:803|HỆ THỐNG KIỂM TRA GIÚP|
|164:808|Email đúng tên miền của đại lý (@toyotacamau.vn)|
|164:813|Số điện thoại chưa dùng cho tài khoản khác|
|164:818|Toyota Cà Mau chưa có quản lý. Duyệt xong, người này sẽ duyệt sales của đại lý.|
|164:822|Nếu còn nghi ngờ, gọi tổng đài của đại lý để xác nhận trước khi duyệt.|
|164:825|Sales chờ quá 3 ngày|
|164:826|Quản lý đại lý chưa xử lý, admin có thể nhắc hoặc duyệt thay|
|164:830|[DEMO] Đỗ Thu Hà|
|164:831|Sales · Toyota Vinh|
|164:833|[DEMO] Ngô Trung Hiếu|
|164:834|Người duyệt|
|164:839|Chờ 5 ngày|
|164:844|Nhắc quản lý|
|164:846|Duyệt thay|
|164:850|[DEMO] Hồ Minh Anh|
|164:851|Sales · Toyota Long Thành|
|164:853|[DEMO] Vũ Gia Huy|
|164:854|Người duyệt|
|164:859|Chờ 4 ngày|
|164:864|Nhắc quản lý|
|164:866|Duyệt thay|

Label từ component instance (metadata thường không bung text con): “Muốn làm Quản lý đại lý”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C07 · Vinh danh — 164:867

C07: kỳrail~300 + main804;3steps; topDealerstable có disqualified/replacement; warningthreshold;publishdisabled. Không có create-draftmodal hay confirmPublish.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|164:951|Page head|32,28|1128×60|
|164:955|Split|32,108|1128×736|
|164:956|Các kỳ|0,0|300×340|
|164:957|Kỳ · Tháng 10/2026|13,13|274×102|
|164:963|Kỳ · Tháng 9/2026|13,121|274×100|
|164:969|Kỳ · Tháng 8/2026|13,227|274×100|
|164:975|Kỳ tháng 10/2026|316,0|812×736|
|164:978|Button · Xem trước trang vinh danh|542,0|220×36|
|164:983|Các bước|25,81|762×68|
|164:984|Bước 1|0,0|248×66|
|164:991|Bước 2|256,0|250×68|
|164:997|Bước 3|514,0|248×66|
|164:1050|Kiểm tra top 3|25,169|762×360|
|164:1051|Header|1,1|760×34|
|164:1065|Row 1|1,36|760×68|
|164:1073|Row 2|1,105|760×68|
|164:1081|Row 3|1,174|760×102|
|164:1089|Row 4|1,277|760×82|
|164:1096|Ngưỡng|25,549|762×42|
|164:1101|Hành động|25,611|762×100|
|164:1102|Button · Mở danh sách gọi|0,29|180×42|
|164:1107|cap|418,42|217×16|
|164:1112|Button · Công bố kỳ|645,30|117×40|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|164:953|Vinh danh|
|164:954|Mỗi tháng một kỳ, chốt theo giờ Việt Nam. Chỉ công bố sau khi kiểm tra top 3.|
|164:958|Tháng 10/2026|
|164:959|Chốt số liệu 01/11, 00:00|
|164:964|Tháng 9/2026|
|164:965|Công bố 03/10|
|164:970|Tháng 8/2026|
|164:971|Công bố 04/09|
|164:977|Kỳ tháng 10/2026|
|164:982|Xem trước trang vinh danh|
|164:989|Chốt số liệu|
|164:990|01/11, 00:00 giờ VN|
|164:992|2|
|164:995|Kiểm tra top 3|
|164:996|Gọi 5 khách mỗi đại lý|
|164:998|3|
|164:1001|Công bố|
|164:1002|Hiện trên trang Vinh danh|
|164:1053|HẠNG|
|164:1055|ĐẠI LÝ|
|164:1057|ĐIỂM|
|164:1059|MẪU GỌI|
|164:1061|KẾT QUẢ|
|164:1063|[rỗng/U+200B; layer name là Text]|
|164:1008|Toyota Thái Nguyên|
|164:1009|67|
|164:1010|5 khách|
|164:1011|5/5 đúng|
|164:1020|Toyota Lý Thường Kiệt – CN Tân Phú|
|164:1021|9|
|164:1022|5 khách|
|164:1023|3/5 đã gọi, 3 đúng|
|164:1027|—|
|164:1029|Toyota Nankai Hải Phòng|
|164:1030|Bị loại khỏi kỳ, thu hồi 2 điểm|
|164:1031|8|
|164:1032|5 khách|
|164:1033|5/5 đã gọi, 2 sai|
|164:1042|Toyota Bến Thành|
|164:1043|Thay cho Nankai Hải Phòng|
|164:1044|8|
|164:1045|5 khách|
|164:1046|Chưa gọi|
|164:1100|Ngưỡng loại: sai quá [x]% số khách được gọi (chờ TMV chốt). Không nghe máy 2 lần thì không tính là sai.|
|164:1105|Mở danh sách gọi|
|164:1111|Mở khi cả 3 đại lý đã kiểm tra xong|
|164:1113|Công bố kỳ|

Label từ component instance (metadata thường không bung text con): “Đang kiểm tra”; “Đã công bố”; “Đạt”; “Đang gọi”; “Vượt ngưỡng sai”; “Được đôn lên”. Sidebar/topbar/tab dùng inventory chung ở trên.

### C08 · Kiểm tra ngẫu nhiên — 165:832

C08: calllist420 + workspace692;wrongratiocard ởleft;3câuĐúng/Sai;3outcome options; notes/warning/save; wronginfo confirmation chỉ được nhắc, không cóframe.

Controls/sections (tọa độ tương đối parent, không phải viewport):

| Node | Tên layer | x,y | w×h |
|---|---|---|---|
|165:922|Page head|32,28|1128×62|
|165:916|Tiến độ|876,0|252×62|
|165:926|Toolbar|32,110|1128×42|
|165:937|Split|32,172|1128×762|
|165:938|Trái|0,0|420×548|
|165:939|Danh sách gọi|0,0|420×336|
|165:940|Khách · Nguyễn Văn An|1,1|418×66|
|165:949|Khách · Trần Minh Đức|1,68|418×66|
|165:957|Khách · Lê Văn Hải|1,135|418×66|
|165:965|Khách · Phạm Thu Trang|1,202|418×66|
|165:973|Khách · Võ Thanh Tùng|1,269|418×66|
|165:980|Tỉ lệ sai theo đại lý|0,352|420×196|
|165:982|Toyota Kiên Giang|21,51|378×32|
|165:989|Toyota An Sương|21,97|378×32|
|165:996|Toyota Thái Nguyên|21,143|378×32|
|165:1003|Cuộc gọi · Nguyễn Văn An|436,0|692×762|
|165:1008|Gọi|467,1|175×48|
|165:1013|Kịch bản|25,95|642×102|
|165:1016|Câu hỏi|25,217|642×194|
|165:1017|Câu 1|0,0|642×64|
|165:1021|Đúng/Sai|494,14|148×36|
|165:1022|Đúng|3,3|76×30|
|165:1026|Sai|83,3|62×30|
|165:1031|Câu 2|0,65|642×64|
|165:1035|Đúng/Sai|494,14|148×36|
|165:1036|Đúng|3,3|76×30|
|165:1040|Sai|83,3|62×30|
|165:1045|Câu 3|0,130|642×64|
|165:1049|Đúng/Sai|494,14|148×36|
|165:1050|Đúng|3,3|76×30|
|165:1054|Sai|83,3|62×30|
|165:1059|Kết quả|25,452|642×225|
|165:1061|Lựa chọn|0,28|642×47|
|165:1062|Đúng thông tin|0,0|209.3333282470703×47|
|165:1066|Sai thông tin|217.3333282470703,0|208.33334350585938×46|
|165:1069|Không nghe máy|433.66668701171875,0|208.33334350585938×46|
|165:1072|Ghi chú|0,87|642×64|
|165:1074|Lưu ý|0,163|642×62|
|165:1079|Lưu|25,697|642×40|
|165:1080|Button · Lưu kết quả|518,0|124×40|

Text nguyên văn / value mẫu:

| Text node | Nội dung |
|---|---|
|165:924|Kiểm tra ngẫu nhiên|
|165:925|Tháng 10/2026 · 60 khách được chọn ngẫu nhiên từ các đại lý|
|165:918|Đã gọi|
|165:919|18 / 60|
|165:943|Nguyễn Văn An|
|165:944|Toyota An Sương · 09****321|
|165:951|Trần Minh Đức|
|165:952|Toyota An Sương · 09****789|
|165:959|Lê Văn Hải|
|165:960|Toyota Kiên Giang · 08****112|
|165:967|Phạm Thu Trang|
|165:968|Toyota Kiên Giang · 09****540|
|165:975|Võ Thanh Tùng|
|165:976|Toyota Thái Nguyên · 09****008|
|165:981|TỈ LỆ SAI THEO ĐẠI LÝ|
|165:984|Toyota Kiên Giang|
|165:985|5/6 đã gọi|
|165:986|2 sai · 40%|
|165:991|Toyota An Sương|
|165:992|2/4 đã gọi|
|165:993|0 sai|
|165:998|Toyota Thái Nguyên|
|165:999|6/8 đã gọi|
|165:1000|0 sai|
|165:1006|Nguyễn Văn An|
|165:1007|Toyota An Sương · Hilux 2024 · 51K-123.45 · Đã xác minh 22/10|
|165:1012|0901 234 567|
|165:1014|KỊCH BẢN GỌI, KHOẢNG 30 GIÂY|
|165:1015|“Chào anh An, em gọi từ ban quản trị Cộng đồng Toyota Veloz & Hilux. Em xin phép hỏi nhanh ba câu để xác nhận đăng ký của anh ạ.”|
|165:1018|1|
|165:1020|Anh có đăng ký tham gia cộng đồng không ạ?|
|165:1025|Đúng|
|165:1029|Sai|
|165:1032|2|
|165:1034|Anh đang đi Toyota Hilux, biển số 51K-123.45 phải không ạ?|
|165:1039|Đúng|
|165:1043|Sai|
|165:1046|3|
|165:1048|Anh đăng ký qua đại lý Toyota An Sương đúng không ạ?|
|165:1053|Đúng|
|165:1057|Sai|
|165:1060|KẾT QUẢ|
|165:1065|Đúng thông tin|
|165:1068|Sai thông tin|
|165:1071|Không nghe máy|
|165:1073|Ghi chú (tuỳ chọn)|
|165:1078|Sai thông tin: khách bị huỷ xác minh và đại lý bị trừ 1 điểm, sẽ hỏi xác nhận trước. Không nghe máy: tự hẹn gọi lại sau 1 ngày; 2 lần không nghe thì bỏ qua, không tính là sai.|
|165:1081|Lưu kết quả|

Label từ component instance (metadata thường không bung text con): “Đang gọi”; “Chưa gọi”; “Gọi lại lần 2”; “Sai thông tin”; “Đúng”. Sidebar/topbar/tab dùng inventory chung ở trên.

## Danh mục36 frame cấp page, không bỏ frame phụ

| ID | Tên chính xác | Kích thước |
|---|---|---|
|160:233|C01 · Tổng quan|1440×1384|
|161:322|C02 · Duyệt khách hàng|1440×1405|
|162:403|C03 · Tra SĐT vào group|1440×900|
|163:488|C04 · Đại lý|1440×1054|
|163:763|C05 · Sales|1440×900|
|164:658|C06 · Duyệt tài khoản|1440×1145|
|164:867|C07 · Vinh danh|1440×956|
|165:832|C08 · Kiểm tra ngẫu nhiên|1440×1046|
|173:1465|Tương tác · Tabs · C01|213×120|
|173:1558|Tương tác · Tabs · C02|710×282|
|173:1651|Tương tác · Tabs · C04|634×228|
|173:1710|Tương tác · Tabs · C05|452×174|
|173:1760|Tương tác · Tabs · C06|600×174|
|173:1809|Tương tác · Tabs · C08|325×174|
|173:2006|Toast · Đã xác minh Lý Minh Quân · đại lý +1 điểm|480×52|
|173:2013|Toast · Đã gửi yêu cầu bổ sung cho khách và sales|359×52|
|173:2020|Toast · Đã từ chối khai báo · sales sẽ thấy lý do|335×52|
|173:2028|Toast · Đã xác minh Nguyễn Văn An · bấm [Duyệt] bên Facebook|453×52|
|173:2037|Toast · Đang tải QR Toyota An Sương (PNG)|314×52|
|173:2044|Toast · Đang tải standee Toyota An Sương (PDF)|346×52|
|173:2052|Toast · Đang xuất danh sách sales (Excel)|301×52|
|173:2059|Toast · Đã khoá [DEMO] Võ Quốc Bảo · link mời hết hiệu lực|417×52|
|173:2066|Toast · Đã duyệt Lâm Văn Tài làm quản lý Toyota Cà Mau|400×52|
|173:2073|Toast · Đã gửi từ chối · người đăng ký nhận email|348×52|
|173:2080|Toast · Đã gửi email nhắc quản lý đại lý|282×52|
|173:2088|Toast · Đã duyệt thay quản lý đại lý|257×52|
|173:2097|Toast · Đã lưu: Đúng thông tin · chuyển sang khách tiếp theo|426×52|
|206:1029|Modal · Thêm đại lý|720×588|
|206:1086|Modal · Sửa thông tin đại lý|720×588|
|206:1143|Modal · Ngừng tham gia chương trình?|520×294|
|206:1169|Modal · Sửa thông tin khách|720×530|
|206:1227|Toast · Đã tạo đại lý · QR và standee đã sẵn sàng|348×52|
|206:1233|Toast · Đã lưu thông tin đại lý|218×52|
|206:1239|Toast · Đã ngừng tham gia Toyota An Sương|317×52|
|206:1245|Toast · Đã lưu thông tin khách · ghi vào lịch sử|330×52|
|206:1274|Toast · Đã sao chép link đại lý|221×52|

## Chưa xác minh và điều kiện tiếp tục
1. **BLOCKED reactions page159:316 và descendants**: toolapproval bịcấm. Toàn bộ36node render đọc được; không kết luận không córeaction hay không cóoverlay chỉ vì context không trảreaction. Khi có khả năng read-only prototype, chỉ cần kiểm chứng edges/close/timing đã liệt kê, không quét lại toàn bộ nội dung tĩnh.
2. **NOT FOUND**: Mobile Admin; Adminloading/empty/error/full dropdown/menu/tooltip; confirmwrongcall; confirm/undo/publishdialogs. NOT FOUND là kết quả tìm trong page/component liên quan, không thiết kế giải pháp thay thế.
3. C06variant173:1759: tênActive=Đã xử lý khác nội dung hiển thị; cần chốt wording. C05/C07 headerlayerText là rỗng trongrender, không dùng nodename làmcopy.
4. Các quan hệ modal/toast→màn nguồn là INTENT, cần xác nhận prototype, đặc biệt C03supplement đi cùng formC02 hay formriêng; C02Từchối; luồngHoàntác; copy/download toast timing; C08wrongconfirmation.
5. Không sửa source/dữ liệu. Không chạy PHPunit/build/migrate/seed hoặc screenshotQA. Tài liệu này đủ tra cứu staticframe/text/node cho từng phase, nhưng chưa gọi auditprototype hoàn chỉnh.

