# Kiểm thử tích hợp Toyota Community

> Cập nhật sau khi được phép chạy local: cả bốn migration đã Ran, đã tạo 88 tài khoản Dealer; kiểm tra live Laragon đã đạt và suite mới đạt 663 PASS. **Đã sẵn sàng demo trên máy Laragon hiện tại.** Xem [LOCAL_DEALER_SETUP_REPORT.md](LOCAL_DEALER_SETUP_REPORT.md). Kết luận chưa sẵn sàng/pending bên dưới phản ánh thời điểm kiểm thử trước khi áp dụng local.

Ngày 20/10/2026. Laravel 13.37.0, PHP Laragon 8.5.11. Phạm vi: Dealer Landing Page, đăng ký QR, Dealer Dashboard, Sales Avatar và Public Personal Profile.

## Môi trường và giới hạn

- Mọi request có ghi dữ liệu chạy trên MySQL `toyota_testing_upgrade_20261019` qua config `.laravel-tools/composer/phpunit-upgrade.xml`. TestDatabase guard kiểm tra APP_ENV, connection và đúng tên schema trước khi test chạy.
- Test tích hợp mới dùng đăng nhập bằng email/password qua Laravel HTTP kernel, kiểm tra Auth/session/redirect thực; không dùng `actingAs` để bỏ qua đăng nhập. Tài khoản và dữ liệu được tạo trong DB test, mật khẩu tạo ngẫu nhiên cho riêng test.
- Browser QA dùng HTML/JSON/ảnh do các endpoint tạo từ DB test; network của browser được mô phỏng. Không gửi form vào toyota, không chạy demo live trên Laragon, không quét bằng camera điện thoại thật.
- Database `toyota` chỉ được SELECT/đọc metadata và đối chiếu hash. Không migrate, seed, reset/xóa dữ liệu, reset/revert Git, SSH hoặc deploy production.

## Kịch bản tích hợp mới

`tests/Feature/CommunityIntegrationTest.php`: **2 PASS, 0 FAIL, 243 assertions** khi chạy riêng.

1. Sales đăng nhập và khai báo khách vào tháng 11/2025; Admin đăng nhập và duyệt vào tháng 01/2026.
2. Guest lấy PNG QR từ endpoint, mở đúng Dealer Landing Page và gửi hai đăng ký QR với địa chỉ hành chính/xe/consent.
3. Kiểm chứng dealer_id chính xác, source dealer_qr, sales_id NULL, pending; chưa tăng điểm.
4. Dealer A đăng nhập xem được đăng ký. Dealer B không thấy trong danh sách và nhận 404 khi đổi ID; Dealer không có quyền duyệt/từ chối.
5. Admin đăng nhập kiểm tra; duyệt QR thiếu Facebook xác minh bị chặn. Sau xác minh, duyệt một đăng ký và từ chối đăng ký còn lại.
6. Dealer tăng đúng một điểm QR; Sales không tăng điểm QR.
7. Sales đăng nhập tiếp, upload WebP được chuẩn hóa JPEG, khai báo khách mới và xem evidence của mình. Sales không xem được đăng ký QR không thuộc mình.
8. Guest thấy profile Sales với số điện thoại đã che và avatar; private avatar theo ID/evidence vẫn cần đăng nhập.
9. Admin duyệt khách của Sales rồi chuyển Sales sang đại lý B. Tổng Sales vẫn 2; điểm đại lý A vẫn 3 (hai khách Sales + một QR approved), đại lý B vẫn 0.
10. Profile hiển thị tháng 01/2026 = 1, tháng 12/2025 = 0, tháng 11/2025 = 1. Khách tháng 11 được duyệt tháng 01 vẫn thuộc tháng 11, đúng quy tắc submitted_at đang áp dụng.
11. Dealer A giữ quyền xem khách/evidence lịch sử; Dealer B chỉ xem Sales hiện tại, không xem khách/evidence cũ.
12. Guest HTML và Accept: application/json không lộ tên, địa chỉ, xe, ghi chú, đường dẫn evidence của khách hoặc email/số đầy đủ của Sales.

Test route riêng xác nhận Dealer và Sales có cùng chuỗi slug vẫn đi đúng hai namespace; Dealer tên login/logout/sales/admin/dealer nhận slug tránh reserved. `/login` vẫn là form đăng nhập; GET `/logout` trả 405; dashboard/submissions/private avatar không bị public slug bắt nhầm.

## Ma trận yêu cầu

| Yêu cầu | Kiểm chứng |
|---|---|
| 1. QR đúng Dealer LDP | PNG từ endpoint trong flow tích hợp, mở đúng dealer_id; giải mã QR bằng browser |
| 2–3. Form và lưu pending đúng đại lý | POST qua HTTP kernel + kiểm tra MySQL dealer_id/source/status/address/vehicle/sales_id |
| 4–5. Dealer xem đúng phạm vi | Đăng nhập Dealer A/B, danh sách/chi tiết/IDOR/evidence sau chuyển đại lý |
| 6–8. Admin duyệt/từ chối, điểm Dealer/Sales | Chặn thiếu Facebook; approved cộng Dealer, rejected/pending không cộng, QR không cộng Sales |
| 9. Sales login/khai báo | Đăng nhập bằng mật khẩu, tạo khách qua endpoint Sales, evidence có quyền |
| 10–11. Avatar/public profile an toàn | Upload WebP→JPEG, public avatar, placeholder và mask; private endpoint vẫn bảo vệ |
| 12. Tổng và 3 tháng | Tổng 2; 01/2026:1, 12/2025:0, 11/2025:1; submitted_at khác reviewed_at |
| 13. Chuyển đại lý | Slug/tổng/tháng không đổi; dealer_id và điểm/evidence lịch sử giữ ở A |
| 14. Không rò rỉ khách/evidence | Guest HTML/JSON; route auth/policy; public projections và export không có dữ liệu nội bộ |
| 15. Route collision | Cùng chuỗi Dealer/Sales slug; reserved names; login/logout; các route hệ thống |
| 16. Responsive | Browser QA ở 320/390/1440px, thêm 768px cho LDP/Sales profile |
| 17. Toàn bộ suite | 657 PASS, 0 FAIL, 0 ERROR; chạy toàn bộ Unit + Feature với fail-on-warning/deprecation |

## Quyền truy cập

| Actor | Cho phép | Bị chặn |
|---|---|---|
| Guest | Dealer LDP/QR/form, Sales active public profile/avatar giới hạn | Dashboard, khách hàng, private avatar ID, evidence; không trả User/customer model qua public |
| Dealer A | Sales đang phân công; khách/evidence có dealer_id lịch sử A | Admin moderation, dữ liệu đại lý khác |
| Dealer B sau chuyển Sales | Sales hiện thuộc B | Khách/evidence cũ của Sales vẫn thuộc A, đổi ID không vượt quyền |
| Sales active | Đăng nhập, khai báo, avatar của mình, khách/evidence của mình | Đăng ký QR không gắn Sales, avatar của người khác, chức năng Admin |
| Admin active | Quản lý Sales/avatar, kiểm tra/duyệt/từ chối đăng ký | Duyệt QR thiếu danh tính xác minh hoặc trùng Facebook approved vẫn bị validation/unique index chặn |

Các test bảo mật riêng trong suite tiếp tục kiểm tra upload độc hại, chống trùng trong giao dịch đồng thời, CSRF thật, throttle và trạng thái tài khoản.

## Kết quả và bằng chứng

**657 PASS, 0 FAIL, 0 ERROR, 13.145 assertions**. Thời gian 3 phút 16,232 giây; PHP 8.5.11. Toàn bộ suite chạy với fail-on-deprecation, fail-on-phpunit-deprecation và fail-on-warning, exit code 0.

| Nhóm | PASS | FAIL / ERROR | Assertions |
|---|---:|---:|---:|
| Unit | 112 | 0 | 416 |
| Feature | 545 | 0 | 12.729 |
| Tổng | 657 | 0 | 13.145 |

Browser QA đã chạy lại sau khi suite kết thúc để tránh fixture bị ghi đồng thời:

- Dealer LDP: 320/390/768/1440px; không tràn ngang/lỗi JavaScript, chọn tỉnh/phường, validation, consent và POST payload đạt.
- Sales Public Profile: 320/390/768/1440px; cả avatar và placeholder, đủ ba tháng, không có email/số đầy đủ; ảnh 404 quay về placeholder, không lỗi JavaScript.
- Dealer Dashboard: 8 trang × 320/390/1440px; điều hướng, dữ liệu public giới hạn, link tải QR PNG và fixture evidence theo quyền đạt.
- PNG QR lấy trực tiếp từ endpoint trong flow tích hợp đã giải mã đúng URL của đại lý được kiểm tra. Điểm cuối: Dealer A = 3, Dealer B = 0; Sales = 2; 01/2026:1, 12/2025:0, 11/2025:1.
- Pint cho test mới và `git diff --check` đạt.
- Hash/schema của **10 bảng local không đổi**, **1.001 file evidence nguyên vẹn**; các migration 000018–000021 chưa chạy trên toyota.

- Log mới: `.laravel-tools/integration-qa/flow-tests.log`, `flow-tests.xml`, `full-suite.log`, `full-suite.xml`.
- QR flow: `.laravel-tools/integration-qa/flow/dealer-qr.png`, `qr-result.json`.
- Browser: `.laravel-tools/dealer-landing/qa/results.json`, `.laravel-tools/sales-public-profile/qa/results.json`, `.laravel-tools/dealer-dashboard/qa/results.json`.
- Đọc local: `.laravel-tools/integration-qa/local-readiness.json`, `local-migration-status.txt`. Đối chiếu dữ liệu: `.laravel-tools/sales-public-profile/local-preservation.json`.

## Lỗi và thay đổi trong lượt kiểm thử

- Hai test tích hợp mới đã đạt ngay lần chạy đầu; toàn bộ suite và browser đều đạt. Không phát hiện lỗi cần sửa trong controllers/models/views.
- GET /logout và role Dealer là các lỗi cũ đã xử lý từ trước; lần này chỉ kiểm tra hồi quy, không nhận là lỗi mới được sửa.
- Thêm `tests/Feature/CommunityIntegrationTest.php`, báo cáo này và cập nhật `WORKING_TREE_CHANGES.md`. Các script/log/ảnh QA nằm trong `.laravel-tools` ignored. Không sửa nghiệp vụ hoặc migration trong lượt này.

## Migration và vấn đề còn chờ

Không tạo migration mới trong lượt kiểm thử. Các migration tính năng đã tạo trước đó vẫn **Pending trên toyota**, chỉ thực thi trong DB test:

| Migration | Nội dung |
|---|---|
| `2026_10_20_000018_support_unique_dealer_accounts_on_users` | Role Dealer, tối đa một tài khoản quản lý mỗi đại lý |
| `2026_10_20_000019_add_stable_slug_to_dealers` | Slug Dealer duy nhất, ổn định |
| `2026_10_20_000020_support_dealer_qr_registrations` | QR không gắn Sales, consent, nguồn đăng ký và chốt định danh approved |
| `2026_10_20_000021_add_public_slug_to_sales_accounts` | Slug public Sales ngẫu nhiên, duy nhất, bất biến |

Local hiện có 1 Admin và 51 Sales, **0 tài khoản Dealer**; chưa có các cột dealer.slug, customer_submissions.source hoặc users.public_slug. Đây là trở ngại demo live, không phải lỗi test. Các migration address/avatar/photo 000015–000017 đã Ran từ trước.

## Kết luận demo

**Code đạt kiểm thử tích hợp trên database test riêng. Chưa sẵn sàng demo đầy đủ với database local hiện tại**, vì schema mới chưa được áp dụng và chưa có tài khoản Dealer. Không còn lỗi test hoặc lỗi source được ghi nhận trong phạm vi này. Để demo live cần:

1. Anh phê duyệt riêng chạy 000018–000021 trên toyota sau khi backup và kiểm tra dữ liệu.
2. Admin tạo/gán tài khoản Dealer bằng luồng quản trị hiện có, dùng mật khẩu riêng; không seed demo hoặc tạo mật khẩu mặc định.
3. Kiểm tra lại trên Laragon với tài khoản và dữ liệu local đã được phép dùng. Nếu demo bằng điện thoại, URL trong QR phải truy cập được từ thiết bị; chưa xác nhận quét camera thực trên hostname toyota.test.

Không chạy các bước cần phê duyệt ở trên trong lần kiểm thử này.
