# SPEC — Chương trình cộng đồng Toyota Veloz & Hilux

## 1. Phạm vi và nguồn sự thật

Tài liệu này là source of truth về nghiệp vụ của project. Các yêu cầu đã chốt ở đây phải được tuân thủ; thay đổi nghiệp vụ phải được chủ project xác nhận trước khi cập nhật tài liệu và implementation.

V1 là website thi đua dành cho nhân viên kinh doanh (sales) của các đại lý Toyota, ghi nhận thành viên do sales mời tham gia cộng đồng Facebook Toyota Veloz và Hilux. Theo yêu cầu cập nhật đã xác nhận, khai báo mới phải có thông tin xe Toyota và 1 ảnh bằng chứng; không giới hạn xe chỉ là Veloz/Hilux.

Stack: Laravel, Blade, Tailwind CSS, MySQL; Alpine.js khi cần. Không dùng React/Vue nếu chưa có nhu cầu được xác nhận. UI hiện đại, tối giản, responsive, ưu tiên mobile và phù hợp chương trình cộng đồng Toyota; moderation của admin cần ít thao tác.

### Public landing page

- Landing page có header/navigation, hero, CTA tham gia, thống kê Sales active/Dealer active/tổng submissions approved, Top 3 Sales/Dealer theo tuần hoặc tháng, kỳ vinh danh được công bố gần nhất và CTA cuối trang.
- Dữ liệu leaderboard lấy từ LeaderboardService, giữ nguyên quy tắc tính điểm/ranking. Vinh danh lấy kỳ có published_at mới nhất và chỉ hiển thị snapshot; không public draft hoặc dữ liệu cá nhân khách hàng.
- Thể lệ trên landing tóm tắt các quy tắc đã chốt ở SPEC; không tự thêm lịch chương trình, giải thưởng hoặc quy định chưa xác nhận.
- Giao diện public riêng, Blade components, mobile first; có skip link, menu mobile dùng native details, focus rõ, heading hierarchy và hỗ trợ reduced motion. CTA của người đã đăng nhập về trang tài khoản theo role/status hiện có.

## 2. Vai trò và quyền

V1 chỉ có hai role: `admin`, `sales`. Dealer là entity, không phải role. Một dealer có nhiều sales; mỗi sales thuộc một dealer. Admin không bắt buộc thuộc dealer.

| Đối tượng | Quyền |
| --- | --- |
| Public | Landing page, leaderboard, lịch sử vinh danh đã công bố, thể lệ, đăng ký, đăng nhập |
| Sales pending/rejected/blocked | Không được sử dụng các chức năng nghiệp vụ; chỉ được xem thông báo tình trạng tài khoản khi được phép xác thực |
| Sales active | Dashboard, thêm khách hàng, xem submissions của mình, sửa/xóa submission pending, xem trạng thái, thành tích và ranking cá nhân; tự sửa name/phone/password |
| Admin active | Dashboard, duyệt tài khoản, quản lý dealers/sales, moderation submissions, leaderboard, công bố awards và xem lịch sử duyệt |

Sales không được đọc/sửa/xóa submissions của sales khác; không được tự đổi role, status, reviewer, điểm hay kết quả awards. Mọi quyền phải được kiểm tra ở backend.

## 3. Tài khoản và state transitions

- Sales tự đăng ký và phải chọn dealer Toyota hợp lệ.
- Form đăng ký tối giản gồm họ tên, email, dealer, mật khẩu và xác nhận mật khẩu. Dealer phải đang active và được kiểm tra lại ở backend; dealer ngừng hoạt động không xuất hiện trong form.
- Đăng ký thành công xác thực session và chuyển tới trang trạng thái chờ duyệt. Pending/rejected/blocked có thể đăng nhập để xem trạng thái và đăng xuất, nhưng không được truy cập dashboard nghiệp vụ.
- Login chuyển admin active tới Admin dashboard, sales active tới Sales dashboard; tài khoản chưa active tới trang trạng thái. Logout dùng POST + CSRF, hủy session và tạo lại CSRF token.
- Login giới hạn 5 request/phút cho email + IP và 30 request/phút cho IP; đăng ký giới hạn 5 request/giờ cho IP. Client không được gửi role/status để chọn quyền tài khoản.
- Role của tài khoản tự đăng ký luôn là `sales`; status ban đầu là `pending`.
- Admin duyệt: `pending → active`; từ chối: `pending → rejected`.
- Admin có thể block tài khoản: `active → blocked`.
- Admin có thể reactivate `blocked → active` và `rejected → active` (đã được chủ project xác nhận). Không dùng approve thay cho reactivate; approve chỉ dành cho pending.
- Các đường chuyển khác (đăng ký lại sau rejected, block tài khoản chưa active) chưa được chốt; không tự implement.
- Admin active quản lý danh sách Sales với pagination, filter trạng thái/dealer và search tên/email/phone; xem chi tiết, duyệt, từ chối, khóa, kích hoạt lại và lịch sử duyệt. Không áp dụng các thao tác này lên tài khoản admin.
- Lý do từ chối và ghi chú nội bộ tùy chọn, tối đa 2000 ký tự. Khi chuyển trạng thái khác, lý do từ chối hiện hành được xóa nhưng lịch sử cũ vẫn giữ nguyên.
- Mỗi thao tác review kiểm tra trạng thái hiện tại dưới row lock và lưu user + moderation history trong cùng transaction. Thao tác lặp lại hoặc dùng trạng thái đã cũ bị từ chối, không tạo thêm lịch sử.
- `users.phone` tùy chọn, tối đa 30 ký tự; Sales sửa trong hồ sơ cá nhân và Admin sửa trong form Sales. Form đăng ký vẫn tối giản, chưa có ô phone.
- Chỉ sales active được dùng nghiệp vụ. Admin cũng phải có tài khoản active khi thực hiện chức năng quản trị.
- Lưu admin review, thời điểm, ghi chú/lý do và lịch sử duyệt. Không xóa lịch sử bằng việc ghi đè reviewer cuối cùng.

### Quản lý Dealer dành cho admin

- Chỉ admin active được xem, tìm kiếm, tạo, sửa và active/inactive Dealer; kiểm tra quyền ở backend.
- Dealer gồm `name`, `code`, `province`, `phone`, `address`, `is_active`.
- Dữ liệu danh bạ Toyota bổ sung `toyota_source_id` duy nhất (nullable cho đại lý nhập tay), `website`, `facebook_url`, `zalo_url`, `opening_hours`. Seeder đọc snapshot danh bạ chính thức có ngày/URL nguồn trong `database/data/toyota_dealers.json`, gồm cả chi nhánh. Mã `TMV-{source_id}` là mã nội bộ của app, không tuyên bố là mã doanh nghiệp chính thức. Import giữ ID/code/status của dealer đã tồn tại, không xóa dealer ngoài danh sách hoặc đổi liên kết Sales/lịch sử; trường nguồn không công bố để null.
- Name bắt buộc tối đa 255 ký tự; code bắt buộc tối đa 50 ký tự và duy nhất. Province/phone/address tùy chọn, giới hạn lần lượt 255/30/2000 ký tự; is_active là boolean.
- Danh sách có pagination và tìm kiếm theo tên, mã, tỉnh/thành phố, điện thoại, địa chỉ.
- Dealer inactive không xuất hiện trong form đăng ký và bị backend từ chối nếu gửi ID trực tiếp. Inactive không tự đổi trạng thái Sales hiện có.
- Không có chức năng xóa Dealer trong UI/API V1. Nếu có tài khoản hoặc lịch sử vinh danh liên quan, model và foreign keys ngăn xóa cứng, bao gồm dealer được tham chiếu trong snapshot lịch sử Sales.

## 4. Customer submissions

Mỗi submission thuộc một sales, có:

| Dữ liệu | Yêu cầu |
| --- | --- |
| Họ tên khách hàng | Bắt buộc |
| Facebook profile URL gốc | Bắt buộc |
| Facebook URL normalized | Do backend tính để duplicate detection |
| Số điện thoại | Không bắt buộc |
| Ghi chú sales | Không bắt buộc |
| `vehicle_model` | Bắt buộc chọn dòng Toyota trong danh sách cấu hình; gồm Veloz, Hilux, Camry, Vios và các dòng Toyota khác |
| `first_registration_year` | Bắt buộc, năm đăng ký lần đầu gồm 4 chữ số, từ 1900 đến năm hiện tại theo timezone app |
| `vehicle_color` | Bắt buộc, tối đa 50 ký tự |
| `license_plate` | Bắt buộc, tối đa 20 ký tự; uppercase, bỏ khoảng trắng, chỉ chữ/số/dấu chấm/gạch ngang; không dùng làm duplicate key |
| `evidence_image_path` | Đường dẫn do backend sinh tới 1 ảnh bằng chứng riêng tư; không nhận đường dẫn từ request |
| `submitted_at` | Thời điểm khai báo, tách riêng khỏi created/reviewed timestamps |
| `status` | `pending`, `approved`, `rejected`; mặc định `pending` |
| `reviewed_by`, `reviewed_at` | Admin và thời điểm review |
| `rejection_reason`, `admin_note` | Lý do từ chối và ghi chú admin |

Transitions đã chốt: `pending → approved` hoặc `pending → rejected`. Sales chỉ được sửa/xóa khi pending; sau approved không được sửa/xóa. Chưa chốt quy trình sửa/nộp lại rejected hoặc thu hồi approved; không tự thêm transitions đó.

Backend đặt `submitted_at` khi nhận khai báo; sales không tự đổi thời điểm để chuyển kỳ thành tích. Sửa thông tin pending không được làm thay đổi `submitted_at`.

- Form Sales gồm `customer_name`, `facebook_url`, `customer_phone` (tùy chọn, tối đa 30 ký tự), `note` (tùy chọn, tối đa 2000 ký tự). `customer_phone`/`note` ánh xạ vào cột `phone`/`notes` hiện có.
- Năm trường bổ sung về dòng xe/năm đăng ký lần đầu/màu/biển số/ảnh bằng chứng đều bắt buộc khi tạo và khi bổ sung/sửa khai báo pending. Khi sửa, được giữ ảnh hiện có hoặc thay bằng đúng 1 ảnh mới; không cho xóa ảnh rồi để trống.
- Dòng xe chỉ nhận giá trị allowlist Toyota trong `config/vehicles.php`; admin xác minh tính xác thực thông tin/bằng chứng khi moderation. Nhập đủ field không đồng nghĩa backend tự xác minh quyền sở hữu hay ảnh là thật.
- Ảnh JPG/JPEG/PNG/WebP hợp lệ, tối đa 5 MB, chiều rộng/cao tối đa 8000 pixel; không SVG/GIF hoặc nhiều file. Tên file ngẫu nhiên; lưu trên disk private ngoài public, database lưu đường dẫn, không lưu binary. Không expose ảnh/biển số/thông tin xe trên public landing, leaderboard hay awards.
- Chỉ admin active hoặc Sales active sở hữu khai báo được truy cập ảnh qua endpoint có auth/policy; không dùng public storage link. Thay/xóa ảnh chỉ gắn với thao tác sửa/xóa pending; lỗi lưu database phải dọn ảnh mới, ảnh cũ chỉ xóa sau lưu thành công. Cleanup filesystem lỗi được log không kèm dữ liệu khách/đường dẫn.
- Dữ liệu lịch sử giữ nguyên: năm cột mới nullable cho record cũ; không tự backfill dữ liệu giả và không sửa approved/rejected. UI hiển thị chưa cung cấp khi thiếu. Quy tắc approval, duplicate, điểm và thời gian thành tích giữ nguyên; không tự thêm điều kiện chặn approval của record lịch sử.
- Backend lấy `sales_id` từ session, đặt pending và thời điểm submit; bỏ qua mọi trường hệ thống giả mạo từ request, bao gồm normalized URL và review fields.
- Sales active chỉ xem submissions của mình, kể cả trang chi tiết. Approved/rejected chỉ xem; pending được sửa/xóa. Backend kiểm tra lại quyền và trạng thái dưới row lock khi sửa/xóa.
- Danh sách tìm kiếm theo tên, URL Facebook, điện thoại; filter pending/approved/rejected và pagination 20 bản ghi, mới nhất theo submitted_at/ID.
- Giới hạn thao tác tạo/sửa/xóa tổng cộng 20 request/phút mỗi tài khoản. Ghi chú admin nội bộ không hiển thị cho Sales.
- Chuẩn hóa bằng `FacebookUrlNormalizer`, không gọi mạng. Pending vẫn có thể trùng profile; constraint approved toàn hệ thống tiếp tục được giữ.

## 5. Xác minh Facebook và duplicate

- Admin xác minh thủ công bằng tìm khách trong Facebook Group và có nút mở profile nhanh.
- V1 không phụ thuộc Facebook API, scraping, access token hay metadata tự động. Metadata chỉ là hướng nghiên cứu sau nếu có cách hợp lệ và ổn định.
- **Một Facebook profile chỉ được approved một lần toàn hệ thống**, bao gồm trường hợp submission mới thuộc cùng sales hoặc sales khác. Đây là quy tắc đã được chủ project xác nhận.
- Pending/rejected có thể trùng profile; phải hỗ trợ cảnh báo và truy xuất approved record hiện có.
- Khi approve, backend phải kiểm tra lại normalized identity, không chỉ dựa vào kiểm tra frontend hoặc lúc khai báo.
- Nếu đã có approved record: không approve record mới, hiển thị cảnh báo và cho admin xem record được duyệt trước đó.
- Database phải bảo đảm uniqueness của approved identity, kể cả hai admin duyệt đồng thời. Thao tác duyệt và ghi lịch sử phải atomic.
- Không dùng phone/name làm khóa duy nhất thay thế profile Facebook.

### Kiểm tra duplicate và Approve

- Admin active có danh sách submissions (mặc định pending, pagination 20), màn hình kiểm tra và nút mở Facebook profile. Khi pending trùng approved normalized profile, hiển thị cảnh báo, Sales/Dealer của record trước và link chi tiết chỉ dành cho admin.
- Approve chỉ chuyển pending sang approved. Backend đọc lại record dưới row lock, normalize lại URL gốc và tìm approved profile trước khi ghi; không tin cảnh báo frontend hoặc dữ liệu URL/status từ request.
- Approve và lưu moderation history trong cùng transaction; giữ nguyên sales_id và submitted_at. Có ghi chú admin tùy chọn, tối đa 2000 ký tự.
- Unique index `submissions_approved_profile_unique` là bảo vệ cuối khi hai admin duyệt hai record trùng cùng lúc. Unique violation được xử lý sau rollback thành lỗi duplicate dễ hiểu; record thua vẫn pending, không có review fields/history mới. Transaction thử lại tối đa 3 lần nếu deadlock; nếu vẫn tranh chấp, yêu cầu tải lại và thử lại.
- Approved/rejected không được approve lại. Pending/rejected trùng profile không tự bị từ chối; không block approval chỉ vì trùng tên hoặc điện thoại.
- Admin Moderation có search tên/phone/Facebook, filter Dealer/Sales/status và khoảng ngày submitted_at, pagination 20. Mặc định pending, ưu tiên khai báo cũ nhất. Khoảng ngày bao gồm cả ngày đầu/cuối theo múi giờ đang cấu hình trong app; không thay đổi timezone nghiệp vụ.
- Admin có thể Approve hoặc Reject pending, nhập lý do từ chối và ghi chú admin tùy chọn (tối đa 2000 ký tự). Lý do từ chối được Sales xem; admin note là nội bộ. Review lưu reviewed_by/reviewed_at và history trong cùng transaction, không đổi submitted_at/owner/dữ liệu khách.
- Danh sách có form duyệt nhanh cho từng pending record, nút mở Facebook và cảnh báo duplicate. Sau review tại danh sách giữ bộ lọc/page; chi tiết có cùng form. Có confirmation trước Approve/Reject; không có bulk approval hoặc auto-reject duplicate.
- Approved/rejected chỉ xem, không đổi trạng thái hoặc thêm note sau review trong workflow V1. Toàn bộ màn hình và endpoints moderation chỉ dành cho admin active, không public dữ liệu khách.

Normalization trong foundation: chuẩn hóa HTTP/HTTPS và các host Facebook phổ biến về `https://www.facebook.com`; bỏ query tracking/fragment khỏi URL username; lowercase username; giữ `id` của `/profile.php?id=...`; loại URL không phải profile, host giả Facebook, credentials, port và query identity mơ hồ. Lưu cả URL gốc và normalized. Không gọi mạng để normalize.

Giới hạn: URL username và URL numeric ID của cùng người không thể tự đối chiếu chắc chắn nếu không có mapping xác minh. Đổi username cũng có thể làm thay đổi normalized identity. Admin cần xử lý thủ công các trường hợp này; V1 không tuyên bố nhận diện tuyệt đối mọi alias Facebook.

### Admin Dashboard

- Chỉ admin active truy cập. Ưu tiên hàng chờ với tổng số Sales pending và submissions pending, liên kết đến bộ lọc pending của từng màn hình moderation.
- Hiển thị tổng Dealer (kể cả inactive), Sales active/pending (không tính admin), tổng submissions approved/pending, số submissions tuần này/tháng này.
- Số khai báo tuần/tháng gồm mọi trạng thái, theo submitted_at với ranh giới kỳ và timezone của LeaderboardService; không dùng created_at/reviewed_at.
- Hiển thị tối đa 5 submissions pending gần nhất theo submitted_at giảm dần, ID giảm dần khi trùng thời điểm; có tên khách, Sales/Dealer và link xem/duyệt chỉ dành cho admin.
- Tóm tắt Top 3 Sales/Dealer tuần này dùng LeaderboardService, giữ nguyên quy tắc approved và hòa điểm. Không có chart hoặc thao tác approval mới trên Dashboard.
- Quick actions: Duyệt Sales, Duyệt khách hàng, Quản lý Dealer, Xem Leaderboard, Tạo vinh danh (mở trang tạo/preview draft hiện có).

## 6. Leaderboard

### Sales Dashboard

- Chỉ Sales active được truy cập; dữ liệu luôn thuộc tài khoản đang đăng nhập.
- Hiển thị tên Sales, Dealer, tổng submissions và số approved/pending/rejected trong toàn bộ thời gian.
- Hiển thị 5 submissions gần nhất theo `submitted_at` giảm dần, dùng ID giảm dần khi trùng thời điểm; gồm tên khách, thời điểm khai báo và trạng thái.
- Thứ hạng cá nhân toàn chương trình lấy từ LeaderboardService; Sales chưa có approved submission hiển thị chưa có dữ liệu xếp hạng.
- CTA “Thêm khách hàng” mở form khai báo; Dashboard có link tới danh sách và chi tiết submissions. Giao diện responsive, ưu tiên mobile.

### Quy tắc xếp hạng

- Chỉ submission `approved` được tính, mỗi submission hợp lệ đóng góp một thành tích.
- Thời gian tính theo **`submitted_at`**, không theo `reviewed_at`/`approved_at`.
- Khai báo 31/10, duyệt 02/11: thành tích thuộc tháng 10.
- Top Sales tuần, tháng, toàn chương trình.
- Top Dealer tuần, tháng, toàn chương trình.
- Điểm Dealer là tổng approved submissions có dealer_id snapshot của dealer đó.
- Dùng thống nhất `config('app.timezone')` cho thời điểm hiện tại và ranh giới kỳ. Giữ timezone application hiện có; không tự đổi timezone hoặc chuyển dữ liệu DATETIME cũ.
- Tuần bắt đầu thứ Hai 00:00, kết thúc trước thứ Hai tiếp theo 00:00. Tháng bắt đầu ngày 1 00:00, kết thúc trước ngày 1 tháng tiếp theo. Đầu kỳ inclusive, đầu kỳ tiếp theo exclusive.
- Khi bằng điểm, ưu tiên đối tượng đạt điểm sớm hơn theo yêu cầu đã xác nhận: dùng MAX(submitted_at) của các approved submissions trong kỳ làm thời điểm đạt tổng điểm hiện tại; không dùng reviewed_at. Nếu điểm và thời điểm đều trùng, ID tăng dần giữ thứ tự ổn định. Hạng liên tiếp 1, 2, 3... cho cả Sales và Dealer.
- All time tính toàn bộ approved submissions đang có, chưa áp dụng ngày bắt đầu/kết thúc chương trình chưa được chốt. Chỉ đối tượng có ít nhất một approved submission xuất hiện trên ranking; inactive Sales/Dealer không tự mất thành tích đã duyệt.
- Dealer score chỉ dựa trên `customer_submissions.dealer_id` snapshot tại thời điểm tạo, tuyệt đối không dựa trên `users.dealer_id` hiện tại. Khi Sales chuyển Dealer, submissions cũ giữ nguyên Dealer; submissions mới thuộc Dealer mới. Sales score vẫn theo sales_id.
- LeaderboardService tập trung bounds/query/score/rank; SQL aggregate + joins + window function MySQL, không load customers hoặc query riêng từng Sales/Dealer. Dùng index status/submitted_at hiện có.
- Public `/leaderboard` có tab Tuần này / Tháng này / Toàn chương trình, Top 3 Sales/Dealer nổi bật, bảng ranking đầy đủ có pagination riêng 20 dòng mỗi loại. Top 3 vẫn hiển thị ở trang sau; rank không reset khi pagination.
- Public chỉ xuất tên Sales, tên/mã Dealer, điểm và hạng; không email/phone tài khoản, credential hoặc dữ liệu khách. Các quy tắc ranking live này chưa công bố hay sửa award snapshots.
- Public leaderboard không được hiển thị họ tên khách, URL Facebook, phone, ghi chú hoặc thông tin riêng của submissions.
- Leaderboard hiện tại có thể thay đổi khi submissions cũ được duyệt muộn; lịch sử awards đã công bố được giữ nguyên.

## 7. Awards và snapshots

- Có awards weekly và monthly, mỗi kỳ vinh danh Top 3 Sales và Top 3 Dealer.
- Trước công bố là bản nháp; khi công bố lưu snapshot cùng thời điểm và admin công bố.
- Snapshot lưu loại người thắng, hạng, điểm, tên sales/dealer hiển thị, dealer của sales khi công bố và ID tham chiếu để truy vết; không chỉ lưu FK rồi đọc tên/điểm live.
- Snapshot không chứa dữ liệu cá nhân khách hàng hay credential.
- Thay đổi tên sales/dealer hoặc các submission tương lai không làm thay đổi kết quả lịch sử.
- Mỗi award có tối đa một winner ở mỗi hạng 1–3 cho từng loại Sales/Dealer; một đối tượng không xuất hiện hai lần trong cùng loại của một award.
- Admin active tạo/mở draft bằng loại weekly/monthly và một ngày thuộc kỳ; backend tính period_start/period_end theo cùng lịch và timezone của LeaderboardService. Các ngày cùng kỳ mở cùng record, không tạo thêm award hoặc ghi đè title hiện có.
- Trạng thái `draft`/`published` được suy ra từ published_at; không lưu thêm status có thể mâu thuẫn với timestamp. Preview chưa lưu winners và không public. Preview dùng cùng điểm/hòa điểm leaderboard, theo submitted_at; toàn bộ query Sales/Dealer chạy trong transaction đọc.
- Đã có trang admin quản lý/preview và public `/awards` chỉ liệt kê published. Trang chi tiết public chỉ đọc winner_name_snapshot, dealer_name_snapshot, dealer_code_snapshot, rank và score; không đọc tên/điểm live hoặc dữ liệu khách.
- Chưa chốt quy tắc hòa điểm, kỳ thiếu ba đối tượng, deadline duyệt, công bố lại/hủy/sửa kết quả. Phải xác nhận trước khi implement quy trình công bố.

## 8. Database foundation và quan hệ

| Entity | Dữ liệu/quan hệ chính |
| --- | --- |
| `users` | Role, status, dealer nullable (sales bắt buộc có), phone tùy chọn, thông tin đăng nhập, thông tin review cuối; belongsTo dealer/reviewer, hasMany submissions/reviews |
| `dealers` | Mã dealer duy nhất, tên, province/phone/address tùy chọn, `is_active` (mặc định true); hasMany sales |
| `customer_submissions` | Sales, Dealer snapshot lúc tạo (nullable cho lịch sử chưa xác định), thông tin khách, URL gốc/normalized, submitted/reviewed timestamps, trạng thái và ghi chú; belongsTo sales/dealer/reviewer |
| `awards` | Weekly/monthly, khoảng kỳ, tên, published timestamp/publisher; hasMany winners |
| `award_winners` | Sales hoặc dealer, hạng, điểm và snapshot tên/dealer; belongsTo award và đối tượng tham chiếu |
| `moderation_reviews` | Lịch sử review tài khoản hoặc submission: đối tượng, reviewer, trạng thái trước/sau, lý do và ghi chú, thời điểm |

Foundation dùng MySQL 8.0.16 trở lên để CHECK constraints được enforce; môi trường đã kiểm tra là MySQL 8.0.30. Migrations này không dùng SQLite. Index phục vụ filter status, sales/dealer, submitted_at và khoảng awards. FK hạn chế xóa dữ liệu đã được tham chiếu; không cascade xóa lịch sử. Approved profile có unique constraint có điều kiện thông qua generated column. Review history cần bảng riêng vì trường review cuối không đáp ứng lịch sử duyệt.

Đã có database foundation, authentication bằng session Laravel, Blade/Tailwind, Admin Sales/Dealer Management, Sales Dashboard và Customer Submissions cho Sales. Admin có Submission Moderation với bộ lọc, duyệt nhanh, Approve/Reject, cảnh báo duplicate và transaction/history. Đã có LeaderboardService và public leaderboard theo tuần/tháng/toàn chương trình; chưa implement award publication.

Tests chạy trên database MySQL riêng `toyota_testing`; không chạy RefreshDatabase vào database `toyota`. Development seeder đọc `DEV_ADMIN_NAME`, `DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD` từ environment; `.env.example` không chứa email/mật khẩu mặc định. Dealer demo được đánh dấu rõ, không đại diện danh sách đại lý Toyota chính thức. Snapshot và moderation history được bảo vệ khỏi sửa/xóa qua model events; các thao tác bulk query/raw SQL bỏ qua model events, nên workflows sau này phải dùng model/actions có authorization và transaction phù hợp.

DevelopmentDemoSeeder là seeder tùy chọn, chỉ local/testing, không nằm trong DatabaseSeeder mặc định. Tạo 50 Sales/500 submissions giả có `[DEMO]`, ảnh placeholder private, lịch sử review và bốn snapshots weekly/monthly đã published để kiểm tra giao diện. Mật khẩu lấy `DEV_DEMO_PASSWORD` trong environment; không thay mật khẩu admin cũ. Seeder không xóa dữ liệu, không chuyển Dealer, không ghi đè awards có sẵn; chạy lại giữ các thay đổi moderation. Các snapshot giả lập không bổ sung workflow publication production hoặc chốt các quyết định nghiệp vụ còn mở. Chi tiết tại `database/data/DEMO.md`.

## 9. Validation

- Validate phía server bằng convention Laravel; frontend chỉ hỗ trợ UX.
- Tên sales/khách/dealer: chuỗi không rỗng sau trim, tối đa 255 ký tự.
- Email tài khoản: email hợp lệ, duy nhất; mật khẩu được hash bằng Laravel, không lưu plaintext.
- Dealer cho sales: phải tồn tại; admin có thể không có dealer.
- Role/status/award period/winner type chỉ nhận giá trị enum hợp lệ.
- Facebook URL: profile URL hợp lệ, tối đa 2048 ký tự gốc, normalized tối đa 255; backend tính lại khi URL đổi.
- Phone: tùy chọn, chuỗi tối đa 30 ký tự; định dạng/số quốc gia chi tiết cần xác nhận trước khi siết validation nghiệp vụ.
- Ghi chú/lý do: text tùy chọn; giới hạn form cụ thể chốt khi implement moderation.
- Approved/rejected phải có reviewer và reviewed_at; pending không mang dữ liệu review hiện hành.
- Award: khoảng thời gian hợp lệ; publisher và published_at phải cùng có hoặc cùng không có.
- Winner: hạng 1–3, điểm nguyên không âm, đúng một đối tượng theo loại sales/dealer, snapshot đủ tên/điểm/dealer tương ứng.

## 10. Security

- Session authentication, CSRF, Laravel policies/gates và middleware kiểm tra role/status trên mỗi request nghiệp vụ; không chỉ ẩn nút UI.
- Chống mass assignment role/status/dealer attribution, review và điểm từ request sales. Các trường hệ thống phải được gán rõ bởi backend.
- Chỉ owner và admin có quyền phù hợp mới truy cập thông tin khách.
- Blade escape dữ liệu; link profile chỉ từ URL được validate, mở tab mới với `noopener noreferrer`.
- Không fetch URL người dùng ở backend để tránh SSRF; không phụ thuộc Facebook API.
- Transactions và database constraints cho approval/snapshot; xử lý unique violation thành thông báo nghiệp vụ ở bước implement approval.
- Rate limit đăng ký/đăng nhập/thao tác khai báo; không log mật khẩu hoặc dữ liệu khách không cần thiết.
- Secrets trong environment; không hard-code credential production. Admin development seeder chỉ chạy local/testing, nhận credential qua environment, không ghi đè mật khẩu tài khoản đã tồn tại.
- Test database phải tách khỏi database development/production.

## 11. Các quyết định cần xác nhận trước khi implement phần liên quan

1. Lịch bắt đầu/kết thúc chương trình; nếu đổi timezone application sau khi có dữ liệu DATETIME, cần kế hoạch chuyển đổi dữ liệu. Leaderboard hiện dùng timezone application và tuần bắt đầu thứ Hai.
2. Một hay hai Facebook Groups; có cần phân loại Veloz/Hilux trong submission không.
3. Hòa điểm, chốt kỳ, duyệt muộn sau công bố, sửa/hủy/công bố lại awards.
4. Dealer history đã chốt: Admin được chuyển Dealer hiện tại; attribution lịch sử là snapshot bất biến tại thời điểm tạo submission.
5. Reopen rejected/revoke approved đối với submission và quy trình chống gian lận thủ công/alias Facebook.
6. Dữ liệu public của sales/dealer, chính sách lưu trữ/xóa dữ liệu khách.

Các mục chưa chốt không được tự biến thành requirement mới. Schema foundation không đồng nghĩa đã implement workflows hoặc đã xác nhận các quyết định này.


## 12. Quy tắc chính thức: Dealer history và quản lý hồ sơ

- Submission mới bắt buộc có dealer_id do backend lấy từ Dealer hiện tại của Sales trong transaction khóa user. Không tin dealer_id/sales_id do Sales gửi. Snapshot dealer_id và sales_id không thay đổi khi sửa submission hoặc chuyển Dealer.
- FK dealer_id hạn chế xóa Dealer có lịch sử; index (dealer_id, status, submitted_at) hỗ trợ leaderboard và moderation. Dealer filter và thông tin Dealer của submission/duplicate dùng snapshot.
- Dữ liệu cũ chưa lưu snapshot chỉ được backfill nếu xác minh chính xác Dealer tại thời điểm tạo. Nếu không đủ dữ liệu, dừng backfill, giữ NULL để thể hiện chưa xác định; không fallback sang Dealer hiện tại. Record chưa xác định vẫn tính Sales, chưa tính Dealer.
- Email là định danh bất biến của mọi account kể từ khi tạo. Sales và Admin không được sửa email của mình hoặc của Sales; UI chỉ hiển thị email dưới dạng văn bản. Backend từ chối request cố gửi email trong profile/Sales edit, model từ chối cập nhật email và MySQL trigger ngăn bulk/raw UPDATE thay email.
- Email mới cần account mới qua registration/approval; thành tích riêng biệt, không chuyển hoặc merge account. Không triển khai account merge.
- Sales active tự sửa name, phone, password. Không tự sửa email, dealer_id, role, status hoặc hồ sơ Sales khác. Profile endpoint chỉ dùng authenticated user, không nhận ID mục tiêu.
- Admin active tự sửa name, password; email bất biến. Admin sửa Sales name/phone/dealer_id; role không sửa ở form Sales edit, status chỉ theo moderation workflow hiện có. Admin có thể chọn Dealer tồn tại; active/inactive hiển thị rõ để admin quyết định. Chuyển Dealer chỉ cập nhật users.dealer_id.
- Đổi mật khẩu yêu cầu current password chính xác, password mới tối thiểu 8 ký tự và confirmation; dùng Laravel hashing, xoay remember token và session ID, không log password.
- Forgot/reset password dùng Laravel password broker, token hết hạn sau 60 phút, dùng một lần, gửi link tới email account. Response quên mật khẩu không tiết lộ account tồn tại. Rate limit forgot/reset/change-password và profile writes. Reset không sửa email/role/status/dealer và không bỏ qua approval. Mail delivery cần cấu hình môi trường phù hợp.
- Published award là immutable snapshot: đổi tên, chuyển Dealer, thay status không thay đổi winners/rank/score/tên Dealer đã lưu. Preview Dealer dùng submission snapshot; snapshot Dealer hiển thị của Sales winner được lấy lúc công bố như quy tắc awards hiện có.
