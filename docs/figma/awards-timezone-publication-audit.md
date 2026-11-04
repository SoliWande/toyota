# Rà soát thời gian và công bố Vinh danh

## Mục tiêu
Xác minh timezone lưu trữ/nghiệp vụ/trình bày và quy trình draft, preview, phê duyệt, publish; đề xuất phương án an toàn, không thay đổi runtime hoặc dữ liệu.

## Trạng thái
DONE — 2026-11-04, hoàn tất phạm vi **audit và đề xuất**, không có triển khai nghiệp vụ. Tài liệu được tạo IN PROGRESS trước khi hoàn thiện kết luận. DONE không có nghĩa hệ thống đã có workflow publish.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, website-competition-awards-sync.md và source Awards hiện tại. Working tree chứa thay đổi Awards, regression và footer từ các task trước; không ghi đè hoặc nhận là thay đổi của task audit này.

Đọc thêm SPEC.md mục6/7/11, MONTHLY_DASHBOARD_REPORT.md, PUBLIC_PRIVACY_REPORT.md, QA_V1_REPORT.md, PERFORMANCE_AUDIT.md và các references integration. Kết quả kiểm thử 197 tests / 4.004 assertions trong awards-sync là lịch sử của task UI, không phải kết quả chạy mới ở đây.

## Các file source code liên quan
config/app.php, config/database.php; routes/web.php; AwardHistoryController, Admin/AwardController, AwardDraftRequest; CreateAwardDraft; LeaderboardService, AwardResults, PublicAwardResults; Award, AwardWinner, AwardPolicy; migration Awards/Winners; các Blade public/Admin/Dealer; tests AwardWorkflow/PublicPrivacy/CompetitionAwards. Chỉ đọc.

Các nguồn bổ sung đã kiểm tra: `app/Console/Kernel.php`, `routes/console.php`, `app/Console/Commands/`, `app/Providers/{AuthServiceProvider,AppServiceProvider}.php`, `app/Models/CustomerSubmission.php`, `app/Services/{SalesPublicProfile,DealerDashboardData}.php`, `database/seeders/DevelopmentDemoSeeder.php`, `tests/Feature/{DatabaseFoundationTest,SecurityAuditTest,MonthlyDashboardTest,LeaderboardServiceTest}.php`. Đọc implementation framework local `LoadConfiguration.php` và `MySqlConnector.php` để xác minh timezone; không dựa vào phỏng đoán phiên bản.

## Những thay đổi đã thực hiện
Chỉ tạo tài liệu này. Không sửa source, UI, cấu hình, endpoint, schema hoặc trạng thái database.

## Quyết định kỹ thuật quan trọng
Phân biệt contract hiện có, rủi ro và đề xuất chưa triển khai. Không tự quyết định timezone nghiệp vụ, lịch chốt kỳ hoặc quy trình phê duyệt.

## 1. Audit timezone

### Cấu hình và runtime local đã xác minh mới

Probe bootstrap Laravel console chỉ thực hiện SELECT cấu hình/metadata tại MySQL loopback, không đọc tên winner hoặc thông tin cá nhân. Kết quả ngày04/11/2026:

| Lớp | Kết quả | Ý nghĩa |
|---|---|---|
| Laravel config/app.php | `timezone => UTC` viết trực tiếp, không lấy APP_TIMEZONE | Chỉ thêm APP_TIMEZONE vào .env hiện không đổi timezone này |
| Runtime config và PHP sau bootstrap | `UTC` / `UTC`, config cache=false | Framework đặt date_default_timezone_set theo config |
| MySQL connection config | Không có timezone, runtime null | Connector chỉ SET time_zone khi có config timezone; hiện không đồng bộ tự động với app |
| MySQL global/session | `SYSTEM` / `SYSTEM` | Kế thừa giờ hệ điều hành của MySQL local |
| MySQL system timezone | `SE Asia Standard Time` | Tại probe: NOW=2026-11-04 16:26:07, UTC_TIMESTAMP=09:26:07, lệch+420 phút |
| Database đã kiểm tra | `toyota`, MySQL local | Đây là SELECT read-only, không chạy test ghi dữ liệu vào DB này |
| Schema thực: awards.period_start/period_end/published_at, customer_submissions.submitted_at | `DATETIME` | Không chứa offset/timezone, không tự chuyển theo session MySQL |
| Schema thực: awards.created_at | `TIMESTAMP` | Khác DATETIME: cần xét chuyển đổi session khi đọc/ghi, không dùng cột này để xác định kỳ/điểm |

**Không được suy ra database đang lưu mọi DATETIME theo UTC+7 chỉ vì NOW() là UTC+7.** Source tạo submitted_at bằng now() và serialize theo app; contract hiện tại diễn giải DATETIME nghiệp vụ theo app.timezone, tức UTC ở môi trường này. Record cũ/import/SQL thủ công có thể có provenance khác; chưa xác minh từng record. Không có timezone gắn trong DATETIME để tự khôi phục sự thật đó.

Phân biệt ba khái niệm:

1. **Lưu trữ:** DATETIME không có múi giờ, hiện được ứng dụng đọc/ghi theo quy ước timezone app. UTC chưa được ép thành session timezone của MySQL. Không tự convert dữ liệu cũ hoặc đổi session/global timezone trong task này.
2. **Nghiệp vụ:** `LeaderboardService::bounds` lấy thời điểm hiện tại theo config và chuyển input về config; tuần thứ Hai00:00, tháng ngày1 00:00, đầu kỳ inclusive/cuối kỳ exclusive. Query dựa `submitted_at`, approved-only; tie-break score DESC, MAX(submitted_at) ASC, id ASC. Vì vậy tháng/tuần hiện là lịch UTC, không phải lịch Việt Nam. Draft và preview dùng chính service này.
3. **Trình bày:** Blade hiện gọi format trực tiếp từ immutable_datetime, không có lớp display timezone độc lập. Có thể đổi cách trình bày một instant sang Asia/Ho_Chi_Minh mà không đổi instant lưu trữ hoặc ranh giới truy vấn. Điều đó không biến kỳ UTC thành kỳ Việt Nam.

### Màn hình và mức ảnh hưởng

| Màn hình/source | Hiện tại | Nếu chỉ đổi presentation | Nếu đổi app.timezone toàn cục |
|---|---|---|---|
| `/awards`, `/awards/{id}`; award-panel, awards-page, award-period | Ngày/giờ công bố format trực tiếp; nhãn timezone theo config; tên kỳ từ period_start, tuần có khoảng ngày | Ngày công bố/tooltip/time ISO có thể đổi ngày; giữ nguyên ID, tên và biên kỳ lịch sử | Có thể diễn giải lại DATETIME, làm lệch instant và lịch tính kỳ |
| B01 home; competition/home.blade.php | Widget dùng latest theo published_at/id, format ngày công bố và khoảng kỳ; ranking tháng theo bounds | Dùng cùng formatter công bố với Awards để tránh lệch ngày; không đổi sort/latest | Có thể đổi tháng đang xếp hạng và điểm gần biên |
| B02 `/leaderboard` | Tháng/all; range và timezone label theo config | Hiển thị range UTC hoặc đầy đủ instant đã quy đổi với nhãn rõ; không thay filter/query | Có thể đổi khoảng xét submitted_at và kết quả live |
| Admin Awards index/show | Input mặc định hôm nay theo config; parse period_date theo config; preview; published timestamp theo config | Có thể trình bày giờ công bố VN, vẫn chỉ rõ timezone xác định kỳ | Có thể đổi draft canonical bounds, default date, tính hợp lệ preview kỳ cũ |
| `/dealer/awards` và widget Dealer dashboard | Snapshot published, khoảng kỳ format trực tiếp qua dealer-awards | Giữ cùng period identity/labels; không trộn timezone lịch sử | Diễn giải thời gian và thống kê tháng thay đổi |
| Admin/Dealer/Sales dashboard, public Sales profile, Admin submission filter | Dùng bounds hoặc startOfMonth/startOfDay; SalesPublicProfile còn group DATE_FORMAT theo DATETIME | Không cần đổi aggregate khi chỉ format thời điểm công bố | Phải audit cả SQL DATE_FORMAT, date filter, thống kê tháng, năm đăng ký hợp lệ |
| Registration/Lookup và receipts/verification | Không xác định kỳ Awards, nhưng có now(), expiry, timestamp xét duyệt | Không liên quan nếu formatter chỉ áp dụng công bố Awards | Có rủi ro đọc lại thời điểm hết hạn và so sánh dữ liệu cũ; cần regression riêng |

Không tìm thấy JavaScript tự chuyển timezone cho Awards; thời gian render server-side. Múi giờ Asia/Saigon chỉ được nhận diện làm nhãn ở một số Blade; home nhận Asia/Ho_Chi_Minh. Không coi alias label là lớp chuyển timezone đã có.

### Ví dụ kiểm chứng và phương án an toàn

- Probe Carbon thật: `2026-10-31T18:30:00+00:00` → `2026-11-01T01:30:00+07:00`. Ngày công bố có thể đổi sang01/11 khi chỉ đổi presentation.
- Cùng instant này, service hiện trả kỳ tháng `[2026-10-01T00:00:00Z, 2026-11-01T00:00:00Z)`. Tại Việt Nam đã là01/11, nhưng vẫn thuộc kỳ tháng10 theo contract UTC. Không được đổi nhãn thành “tháng11” bằng cách lấy tháng của thời điểm hiển thị.
- Biên tháng10 UTC tương ứng01/10 07:00 →01/11 07:00 ở Việt Nam. Dải “01/10–31/10” là **ngày lịch của kỳ UTC**, không nên gắn nhãn “kỳ theo giờ Việt Nam” sau một thao tác format. Giữ exclusive end; không lấy subDay của một instant đã convert rồi quảng bá là biên kết thúc chính xác.

**Đề xuất ưu tiên, chưa triển khai:** thêm một formatter/presenter chung cho các instant công bố, cấu hình display timezone độc lập Asia/Ho_Chi_Minh sau khi được xác nhận. Với giá trị đã được xác minh là UTC, dùng immutable `setTimezone` để giữ nguyên instant; không cộng7giờ thủ công, không dùng shiftTimezone để diễn giải lại wall-clock. Đồng bộ ngày/giờ/tooltip/ISO và nhãn trên Awards + home + Admin, giữ tên kỳ và query/bounds nguyên trạng. Nêu riêng timezone của kỳ nếu trình bày giờ công bố khác timezone nghiệp vụ. Không đổi config app/database hoặc cập nhật bản ghi.

**Nếu yêu cầu thực sự là kỳ tháng/tuần theo lịch Việt Nam:** đây là task nghiệp vụ riêng. Cần xác nhận provenance DATETIME lịch sử và mốc chuyển đổi, phân tách business/storage timezone rõ ràng, mô phỏng dữ liệu quanh biên, kế hoạch lưu trữ/đọc tương thích và rollback. Không chỉ đổi app.timezone vì SQL đang so sánh chuỗi DATETIME theo timezone app. Không tính lại hoặc chỉnh period_start/end của award đã published. Việc chuẩn hóa session MySQL về UTC cũng cần audit riêng cho TIMESTAMP/NOW/defaults; không gộp vào sửa nhãn UI.

## 2. Audit quy trình công bố

### Chức năng đã có thực tế

| Bước | Source/bằng chứng | Kết luận |
|---|---|---|
| Admin quản lý lịch sử | GET admin/awards, GET admin/awards/{award} | Có, filter loại kỳ/pagination20 và trạng thái draft/published |
| Tạo/mở draft | POST admin/awards; AwardDraftRequest, CreateAwardDraft | Có, active Admin; chỉ nhận period_type/date/title, tính bounds server-side, firstOrCreate và unique period, không nhận winners/published_by/at từ form |
| Preview | AwardResults::preview từ Admin show | Có, draft-only, xác minh canonical bounds; transaction đọc Top3 Sales và Dealer từ LeaderboardService; không ghi winners |
| Preview fingerprint | AwardResults::fingerprint, controller truyền previewHash | Có SHA256 của kỳ và dữ liệu preview; Blade hiện không gửi hash để xác nhận, không có action đối chiếu hash |
| Duyệt khai báo/tài khoản | Các Admin routes submissions approve/reject/verification và Sales review | Có; là phê duyệt dữ liệu đầu vào, **không phải phê duyệt award** |
| Phê duyệt kỳ/kết quả award | Không có route/action/status approved hoặc pending-review cho award | Chưa có; status chỉ suy ra draft/published từ published_at |
| Quyền publish | AwardPolicy::publish | Có phương thức kiểm tra role Admin + status Active; chưa có workflow HTTP gọi quyền này để publish |
| Publish từ Admin UI | Admin show draft chỉ preview, không nút/form publish; không có publish controller/action/request | Chưa có |
| Snapshot/history/public | Award/AwardWinner, PublicAwardResults, DealerDashboardData, Blade | Có khả năng đọc snapshot published đã tồn tại và giữ tên/điểm lịch sử |

Runtime route collection đã xác minh đúng6 routes Awards: public index/show; Dealer index; Admin index/store/show. Không có PUT/PATCH/POST publish, approve-award, unpublish hoặc republish. Không có command/job/scheduler công bố award trong app/Console hoặc routes/console.php. Scheduler hiện có tác vụ cleanup receipt opt-in, không liên quan Awards.

`DevelopmentDemoSeeder::awards` có tạo winner snapshots rồi forceFill published_by/at cho dataset demo local/testing; tests cũng dựng published fixtures tương tự. Đây **không phải workflow Admin/production**, không được dùng seeder để công bố thật; task này không chạy chúng.

### Snapshot bất biến và giới hạn bảo vệ

- Award và period/published_at cast immutable_datetime. Award updating/deleting chặn khi bản ghi gốc đã published; winner saving/deleting chặn nếu award cũ hoặc mới đã published. Vì vậy thêm/sửa/xóa/chuyển winner bằng đường model thông thường sau công bố bị chặn.
- Winner guarded toàn bộ; snapshot gán tường minh. Award fillable không gồm published_by/at; model kiểm tra publisher có role Admin. **Kiểm tra model này không thay thế active Admin authorization**: trạng thái Active nằm ở policy/middleware.
- Database: period unique, end>start, published_by và published_at cùng null hoặc cùng có; FK; rank1–3; subject Sales/Dealer hợp lệ; snapshot name/code không trống; unique award/type/rank và award/subject. Có FK snapshot Dealer bổ sung. Không bảo đảm đủ đúng3 người mỗi nhóm hoặc bảo đảm danh sách đúng thuật toán leaderboard chỉ bằng constraints.
- Published reads dùng winner snapshots, không query lại leaderboard để dựng kết quả lịch sử. Admin published branch không gọi preview.
- **Giới hạn:** immutability hiện ở model events; bulk Query Builder update/delete, raw SQL hoặc code bỏ events có thể bypass. SELECT information_schema.TRIGGERS tại DB local trả rỗng cho awards/award_winners; CHECK/FK không đóng băng toàn bộ snapshot. Không khẳng định có bảo vệ bất biến tuyệt đối ở tầng database. Không thử bypass bằng ghi dữ liệu trong task này.
- Preview transaction chỉ phục vụ tính nhất quán lần xem; không phải transaction ghi publish, không phải khóa chốt kỳ, và không ngăn hai Admin công bố đồng thời. Chưa có xử lý rollback/idempotency/concurrency publish để kiểm thử.

### Authorization và public projection

- Admin routes có web/auth/auth.session/role:admin/account.active/throttle:admin-writes; POST hiện có CSRF từ web group. Controller/FormRequest/Action dùng authorize/Gate. Probe xác nhận AwardPolicy được auto-discover dù AuthServiceProvider không map tường minh.
- Public không authorize bằng AwardPolicy (policy dành Admin): PublicAwardResults tự gate published_at NOT NULL + tồn tại Dealer winner. Draft hoặc Sales-only published không vào public, find trả404 kể cả người xem là Admin. Chỉ select ID/loại/biên kỳ/ngày công bố và Dealer rank/score/name/code snapshots, không publisher/title nội bộ/Sales/customer details.
- Home dùng cùng PublicAwardResults::latest theo published_at DESC/id DESC; lịch sử Awards theo period_start DESC/id DESC. Hai tiêu chí sort khác nhau là hiện hữu, không phải trộn kỳ. Dealer private chỉ xem Dealer winner đúng dealer_id, award published.
- **Giới hạn cần lưu ý trước khi có publish:** gate chỉ NOT NULL, chưa có `published_at <= now`, approved flag, completeness flag hay embargo. Gán published_at trong tương lai không tạo lịch công bố tự động; có Dealer winner là đủ qua gate ngay. Không tự sửa gate hoặc gán timestamp tương lai trong task này.

### Những phần còn thiếu để Admin công bố thật

1. Contract chốt kỳ/deadline duyệt, có cho kỳ đang diễn ra hay không, kỳ thiếu0/1/2 winners, cutoff và xử lý dữ liệu được duyệt sau lần preview.
2. Quyết định một Admin xác nhận trực tiếp hay có bước người duyệt độc lập. Hiện chưa có award approval workflow hoặc trạng thái trung gian.
3. Publish action và request/controller/route có authorization active Admin, CSRF/throttle, input whitelist, publisher/time do server gán. Không nhận points/rank/winners tự khai từ client.
4. Transaction atomic: khóa award, kiểm tra lại trạng thái/canonical bounds và quyền; lấy candidate preview nhất quán theo cutoff đã chốt, đối chiếu fingerprint với lần Admin xác nhận; nếu thay đổi yêu cầu xem lại. Ghi snapshots trước rồi published_by/at trong cùng transaction; lỗi phải rollback cả hai. Khóa award để tránh double-publish; không coi khóa này là đã khóa mọi thay đổi submissions. Chính sách cutoff/duyệt đồng thời phải được quyết định rõ.
5. UI xác nhận kết quả, thời gian/kỳ/timezone, cảnh báo preview thay đổi, xử lý đã-published/retry và liên kết snapshot sau thành công. Fingerprint không phải quyền truy cập hay chữ ký xác thực.
6. Kiểm thử publish end-to-end cách ly: active/blocked/wrong role, stale preview, kỳ chưa kết thúc, ít winners theo contract, double submit/concurrent publish, rollback, public gate, bất biến sau duyệt muộn/đổi tên/chuyển Dealer, timezone boundary. Test fixtures published hiện tại không thay thế các test này.
7. Quyết định audit log/approval history nếu cần và cách xử lý yêu cầu sửa/hủy/republish. Hiện không có audit event công bố riêng ngoài published_by/at; không đề xuất tự mở khóa snapshot hoặc thêm schema trước khi chốt yêu cầu.

## 3. Quyết định cần xác nhận trước task triển khai tiếp

| Quyết định | Phương án đề xuất để xem xét, chưa áp dụng |
|---|---|
| Chỉ hiển thị giờ VN hay tính kỳ theo lịch VN? | Tách presentation trước; giữ lịch UTC hiện hữu. Nếu muốn lịch VN, mở task chuyển đổi riêng |
| Dữ liệu DATETIME cũ thực sự theo timezone nào? | Xác minh nguồn ghi/import và cấu hình deployment; không tự cộng/trừ giờ hàng loạt |
| Phạm vi đổi presentation | Awards, home widget và Admin công bố phải đồng bộ; period identity/timezone nghiệp vụ vẫn rõ |
| Chốt kỳ và duyệt muộn | Xác nhận thời điểm cho publish, cutoff, hành vi preview thay đổi; published snapshot không tính lại |
| Số người được vinh danh | Xác nhận0/1/2 thay vì3, Sales-only/Dealer-only và công bố kỳ không dữ liệu; không lấy điều kiện của demo seeder làm nghiệp vụ |
| Phê duyệt và publish | Một Admin hay nhiều bước/khác người; publish ngay hay hẹn giờ. Hẹn giờ hiện chưa được hỗ trợ bởi public gate |
| Sửa/hủy/công bố lại | Mặc định giữ bất biến hiện tại; cần chính sách version/correction riêng nếu yêu cầu |

Không xin xác nhận để làm thay đổi trong task audit này. Đây là các đầu vào cần chốt trước khi giao task triển khai nghiệp vụ.

### Đối chiếu tài liệu lịch sử có mâu thuẫn

- awards-sync kết luận UTC và chưa có publish: được xác nhận lại bằng runtime/source mới. Trạng thái DONE của task UI không phải nghiệm thu publish.
- MONTHLY_DASHBOARD_REPORT nói schedule:list không có task tại thời điểm lịch sử; hiện Kernel có cleanup receipt opt-in. Không có scheduler Awards ở cả kết luận mới; không sửa/xóa lịch sử.
- SPEC mục6 đã mô tả tie-break score/time/id rõ và source preview dùng đúng; mục7/11 vẫn ghi hòa điểm còn mở. Giữ thuật toán hiện có, ghi đây là điểm tài liệu cần làm rõ, không tự đổi ranking khi làm publish.
- SPEC còn mô tả public Sales/tuần ở một số đoạn cũ; source + MONTHLY_DASHBOARD_REPORT + PUBLIC_PRIVACY_REPORT/awards-sync hiện là Dealer-only, month/all cho public leaderboard, Awards vẫn weekly/monthly. Không mở public Sales hoặc khôi phục tab tuần theo đoạn lịch sử đó.

## Kết quả kiểm thử
| Kiểm chứng mới trong task này | Kết quả |
|---|---|
| Đọc config/source/routes/Blade/model/policy/migration và tài liệu liên quan | PASS, contract và gaps ghi ở trên |
| Laravel/PHP timezone, MySQL session/global/system/offset, schema data types/triggers | PASS SELECT read-only trên loopback toyota; không đọc dữ liệu cá nhân hoặc thay record |
| Probe thuần PHP/Carbon bounds và VN conversion | PASS, UTC bounds giữ nguyên, conversion đổi ngày/tháng như ví dụ |
| Policy auto-discovery và publish role check trên User/Award chưa lưu | PASS active Admin được policy cho phép, blocked Admin bị từ chối; đây không phải HTTP publish đã tồn tại |
| Kiểm tra cấu trúc/nội dung Markdown và diff whitespace | PASS sau khi hoàn thiện tài liệu |
| Feature suite/build/screenshot | Không chạy mới: task chỉ audit/tài liệu, không sửa runtime/UI; không ghi fixture/test database chỉ để có kết quả PASS |
| Publish qua HTTP/concurrency/rollback và timezone production | Chưa kiểm chứng; chưa có endpoint và không truy cập môi trường production |

Các SQL của probe: `SELECT DATABASE(), @@global.time_zone, @@session.time_zone, @@system_time_zone, NOW(), UTC_TIMESTAMP(), TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())`; SELECT information_schema.COLUMNS cho các cột thời gian đã liệt kê; SELECT information_schema.TRIGGERS cho awards/award_winners. Lấy route collection trong bootstrap console, không gửi request POST. Guard probe chỉ cho local MySQL. Không chạy SET timezone, migrate, seed, reset, save/update/delete model, config:cache hoặc thay .env.

Probe policy dùng User/Award mới chưa lưu và gọi trực tiếp policy; probe thời gian chỉ Carbon/LeaderboardService::bounds, không gọi aggregate query hoặc đổi config. Không suy diễn việc formatter hiển thị VN đã được triển khai từ probe chuyển đổi này.

## Những lỗi còn tồn tại
**Thiếu chức năng đã xác nhận:** Admin publish/award approval, atomic write/concurrency/stale-preview flow và UI xác nhận chưa tồn tại. Đây là gap backend đã có từ trước, không phải regression của giao diện Awards.

**Rủi ro đã xác nhận bằng source:** timezone app/DB session khác nhau; DATETIME không có timezone provenance; bất biến phụ thuộc model events; NOT NULL publication gate không phải hẹn giờ; policy publish chưa ràng buộc state hoặc đóng kỳ. Không tự sửa các điểm này.

**Chưa xác minh:** config/data provenance trên production; độ đúng của từng timestamp lịch sử/import; khả năng chống bypass qua raw SQL bằng kiểm thử ghi; đầy đủ quy tắc chốt kỳ và người duyệt. Không có lỗi UI thực tế được phát hiện qua audit source này; không thực hiện lại visual QA để khẳng định UI mới.

## Task hoặc bước tiếp theo
Chốt các quyết định trong mục3; sau đó giao task presentation timezone độc lập hoặc task publish có contract cụ thể. Giữ nguyên snapshot/công thức/privacy trong cả hai. Task hiện tại chỉ thay đổi `docs/figma/awards-timezone-publication-audit.md`; không cập nhật source hoặc ghi đè tracking Awards UI.
