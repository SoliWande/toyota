# SPEC — Chương trình cộng đồng Toyota Veloz & Hilux

## 1. Phạm vi và nguồn sự thật

Tài liệu này là source of truth về nghiệp vụ của project. Các yêu cầu đã chốt ở đây phải được tuân thủ; thay đổi nghiệp vụ phải được chủ project xác nhận trước khi cập nhật tài liệu và implementation.

V1 là website thi đua dành cho nhân viên kinh doanh (sales) của các đại lý Toyota, ghi nhận thành viên do sales mời tham gia cộng đồng Facebook Toyota Veloz và Hilux. Khách hàng không bắt buộc sở hữu Toyota Veloz hoặc Hilux.

Stack: Laravel, Blade, Tailwind CSS, MySQL; Alpine.js khi cần. Không dùng React/Vue nếu chưa có nhu cầu được xác nhận. UI hiện đại, tối giản, responsive, ưu tiên mobile và phù hợp chương trình cộng đồng Toyota; moderation của admin cần ít thao tác.

## 2. Vai trò và quyền

V1 chỉ có hai role: `admin`, `sales`. Dealer là entity, không phải role. Một dealer có nhiều sales; mỗi sales thuộc một dealer. Admin không bắt buộc thuộc dealer.

| Đối tượng | Quyền |
| --- | --- |
| Public | Landing page, leaderboard, lịch sử vinh danh đã công bố, thể lệ, đăng ký, đăng nhập |
| Sales pending/rejected/blocked | Không được sử dụng các chức năng nghiệp vụ; chỉ được xem thông báo tình trạng tài khoản khi được phép xác thực |
| Sales active | Dashboard, thêm khách hàng, xem submissions của mình, sửa/xóa submission pending, xem trạng thái, thành tích và ranking cá nhân |
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
- Các đường chuyển khác (mở khóa, duyệt lại, đăng ký lại sau rejected, block tài khoản chưa active) chưa được chốt; không tự implement.
- Chỉ sales active được dùng nghiệp vụ. Admin cũng phải có tài khoản active khi thực hiện chức năng quản trị.
- Lưu admin review, thời điểm, ghi chú/lý do và lịch sử duyệt. Không xóa lịch sử bằng việc ghi đè reviewer cuối cùng.

## 4. Customer submissions

Mỗi submission thuộc một sales, có:

| Dữ liệu | Yêu cầu |
| --- | --- |
| Họ tên khách hàng | Bắt buộc |
| Facebook profile URL gốc | Bắt buộc |
| Facebook URL normalized | Do backend tính để duplicate detection |
| Số điện thoại | Không bắt buộc |
| Ghi chú sales | Không bắt buộc |
| `submitted_at` | Thời điểm khai báo, tách riêng khỏi created/reviewed timestamps |
| `status` | `pending`, `approved`, `rejected`; mặc định `pending` |
| `reviewed_by`, `reviewed_at` | Admin và thời điểm review |
| `rejection_reason`, `admin_note` | Lý do từ chối và ghi chú admin |

Transitions đã chốt: `pending → approved` hoặc `pending → rejected`. Sales chỉ được sửa/xóa khi pending; sau approved không được sửa/xóa. Chưa chốt quy trình sửa/nộp lại rejected hoặc thu hồi approved; không tự thêm transitions đó.

Backend đặt `submitted_at` khi nhận khai báo; sales không tự đổi thời điểm để chuyển kỳ thành tích. Sửa thông tin pending không được làm thay đổi `submitted_at`.

## 5. Xác minh Facebook và duplicate

- Admin xác minh thủ công bằng tìm khách trong Facebook Group và có nút mở profile nhanh.
- V1 không phụ thuộc Facebook API, scraping, access token hay metadata tự động. Metadata chỉ là hướng nghiên cứu sau nếu có cách hợp lệ và ổn định.
- **Một Facebook profile chỉ được approved một lần toàn hệ thống**, bao gồm trường hợp submission mới thuộc cùng sales hoặc sales khác. Đây là quy tắc đã được chủ project xác nhận.
- Pending/rejected có thể trùng profile; phải hỗ trợ cảnh báo và truy xuất approved record hiện có.
- Khi approve, backend phải kiểm tra lại normalized identity, không chỉ dựa vào kiểm tra frontend hoặc lúc khai báo.
- Nếu đã có approved record: không approve record mới, hiển thị cảnh báo và cho admin xem record được duyệt trước đó.
- Database phải bảo đảm uniqueness của approved identity, kể cả hai admin duyệt đồng thời. Thao tác duyệt và ghi lịch sử phải atomic.
- Không dùng phone/name làm khóa duy nhất thay thế profile Facebook.

Normalization trong foundation: chuẩn hóa HTTP/HTTPS và các host Facebook phổ biến về `https://www.facebook.com`; bỏ query tracking/fragment khỏi URL username; lowercase username; giữ `id` của `/profile.php?id=...`; loại URL không phải profile, host giả Facebook, credentials, port và query identity mơ hồ. Lưu cả URL gốc và normalized. Không gọi mạng để normalize.

Giới hạn: URL username và URL numeric ID của cùng người không thể tự đối chiếu chắc chắn nếu không có mapping xác minh. Đổi username cũng có thể làm thay đổi normalized identity. Admin cần xử lý thủ công các trường hợp này; V1 không tuyên bố nhận diện tuyệt đối mọi alias Facebook.

## 6. Leaderboard

- Chỉ submission `approved` được tính, mỗi submission hợp lệ đóng góp một thành tích.
- Thời gian tính theo **`submitted_at`**, không theo `reviewed_at`/`approved_at`.
- Khai báo 31/10, duyệt 02/11: thành tích thuộc tháng 10.
- Top Sales tuần, tháng, toàn chương trình.
- Top Dealer tuần, tháng, toàn chương trình.
- Điểm Dealer là tổng approved submissions của sales thuộc dealer đó.
- Query theo khoảng thời gian có đầu kỳ inclusive và đầu kỳ tiếp theo exclusive, sau khi xác nhận timezone/ranh giới kỳ.
- Public leaderboard không được hiển thị họ tên khách, URL Facebook, phone, ghi chú hoặc thông tin riêng của submissions.
- Leaderboard hiện tại có thể thay đổi khi submissions cũ được duyệt muộn; lịch sử awards đã công bố được giữ nguyên.

## 7. Awards và snapshots

- Có awards weekly và monthly, mỗi kỳ vinh danh Top 3 Sales và Top 3 Dealer.
- Trước công bố là bản nháp; khi công bố lưu snapshot cùng thời điểm và admin công bố.
- Snapshot lưu loại người thắng, hạng, điểm, tên sales/dealer hiển thị, dealer của sales khi công bố và ID tham chiếu để truy vết; không chỉ lưu FK rồi đọc tên/điểm live.
- Snapshot không chứa dữ liệu cá nhân khách hàng hay credential.
- Thay đổi tên sales/dealer hoặc các submission tương lai không làm thay đổi kết quả lịch sử.
- Mỗi award có tối đa một winner ở mỗi hạng 1–3 cho từng loại Sales/Dealer; một đối tượng không xuất hiện hai lần trong cùng loại của một award.
- Chưa chốt quy tắc hòa điểm, kỳ thiếu ba đối tượng, deadline duyệt, công bố lại/hủy/sửa kết quả. Phải xác nhận trước khi implement quy trình công bố.

## 8. Database foundation và quan hệ

| Entity | Dữ liệu/quan hệ chính |
| --- | --- |
| `users` | Role, status, dealer nullable (sales bắt buộc có), thông tin đăng nhập, thông tin review cuối; belongsTo dealer/reviewer, hasMany submissions/reviews |
| `dealers` | Mã dealer duy nhất, tên, `is_active` (mặc định true); hasMany sales |
| `customer_submissions` | Sales, thông tin khách, URL gốc/normalized, submitted/reviewed timestamps, trạng thái và ghi chú; belongsTo sales/reviewer |
| `awards` | Weekly/monthly, khoảng kỳ, tên, published timestamp/publisher; hasMany winners |
| `award_winners` | Sales hoặc dealer, hạng, điểm và snapshot tên/dealer; belongsTo award và đối tượng tham chiếu |
| `moderation_reviews` | Lịch sử review tài khoản hoặc submission: đối tượng, reviewer, trạng thái trước/sau, lý do và ghi chú, thời điểm |

Foundation dùng MySQL 8.0.16 trở lên để CHECK constraints được enforce; môi trường đã kiểm tra là MySQL 8.0.30. Migrations này không dùng SQLite. Index phục vụ filter status, sales/dealer, submitted_at và khoảng awards. FK hạn chế xóa dữ liệu đã được tham chiếu; không cascade xóa lịch sử. Approved profile có unique constraint có điều kiện thông qua generated column. Review history cần bảng riêng vì trường review cuối không đáp ứng lịch sử duyệt.

Đã có database foundation và authentication bằng session Laravel, Blade/Tailwind; có đăng ký sales, login/logout, trang trạng thái và dashboard cơ bản được bảo vệ theo role/status. Chưa implement approval actions, leaderboard queries hoặc award publication.

Tests chạy trên database MySQL riêng `toyota_testing`; không chạy RefreshDatabase vào database `toyota`. Development seeder đọc `DEV_ADMIN_NAME`, `DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD` từ environment; `.env.example` không chứa email/mật khẩu mặc định. Dealer demo được đánh dấu rõ, không đại diện danh sách đại lý Toyota chính thức. Snapshot và moderation history được bảo vệ khỏi sửa/xóa qua model events; các thao tác bulk query/raw SQL bỏ qua model events, nên workflows sau này phải dùng model/actions có authorization và transaction phù hợp.

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

1. Timezone nghiệp vụ, ngày bắt đầu tuần, lịch bắt đầu/kết thúc chương trình.
2. Một hay hai Facebook Groups; có cần phân loại Veloz/Hilux trong submission không.
3. Hòa điểm, chốt kỳ, duyệt muộn sau công bố, sửa/hủy/công bố lại awards.
4. Chuyển dealer của sales: có cho phép không và quy tắc tính điểm dealer quá khứ. Foundation chưa cung cấp thao tác chuyển dealer hoặc tự chốt attribution lịch sử.
5. Reopen rejected, unblock, revoke approved và quy trình chống gian lận thủ công/alias Facebook.
6. Dữ liệu public của sales/dealer, chính sách lưu trữ/xóa dữ liệu khách.

Các mục chưa chốt không được tự biến thành requirement mới. Schema foundation không đồng nghĩa đã implement workflows hoặc đã xác nhận các quyết định này.
