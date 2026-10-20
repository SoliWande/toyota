# Sales Public Personal Profile và phạm vi consent

Ngày 20/10/2026. Laravel 13, PHP Laragon 8.5.11. Giữ nguyên working tree và các chức năng đã có; không reset Git/database, không thay đổi production.

## Kết quả audit consent — chưa đủ điều kiện mở danh sách

`config/dealer.php` hiện có consent `dealer-qr-2026-10-20`, cho phép tiếp nhận/xử lý thông tin nhằm liên hệ, xác minh và ghi nhận khách hàng của chiến dịch. Nội dung kết thúc bằng **“Thông tin này không được hiển thị công khai.”** Checkbox ở `resources/views/components/dealer-registration-form.blade.php` sử dụng đúng thông báo đó. `DealerRegistrationController` chỉ lưu `consented_at` và `consent_version` cho mục đích này.

Migration `000020` và model CustomerSubmission chưa có trường hoặc quan hệ thể hiện sự đồng ý **công khai**. Luồng Sales khai báo cũng chưa có bằng chứng đồng ý công khai riêng. Không thể coi approved, việc tham gia chiến dịch hoặc chỉ có consented_at là đồng ý công khai.

Vì yêu cầu lần này quy định phải báo cáo và chờ xác nhận trước khi tạo migration hoặc mở danh sách nếu thiếu cơ chế consent, **chưa tạo migration, chưa mở từng khách hàng, kể cả dữ liệu đã che**. Đã gửi câu hỏi lựa chọn giữa giữ thống kê tổng hợp và triển khai consent công khai riêng; phần sau đang chờ xác nhận.

## Phần đã triển khai được

- Top 3 Sales trên Dashboard hiện có: bấm tên Sales active mở `/sales/{public_slug}`. Giữ link **Chi tiết nội bộ** riêng tới hồ sơ Admin. Sales inactive vẫn có link quản trị, không sinh link public gây 404. Không thêm bảng xếp hạng Sales trên public, không đổi phạm vi công khai của Dealer Landing Page.
- Tận dụng Sales Public Profile hiện có: avatar/placeholder, tên, điện thoại được che ở backend, đại lý hiện tại, tổng approved chiến dịch, tháng hiện tại và hai tháng trước, kể cả tháng bằng 0.
- Bổ sung nhãn “Đại lý hiện tại” và khu vực “Khách hàng của Sales” giải thích chỉ hiển thị thống kê tổng hợp. Không hiển thị tên, điện thoại hoặc địa lý từng khách; không tạo dữ liệu giả hay hàng placeholder mô phỏng khách thật.
- `SalesPublicProfile` tiếp tục chỉ truy vấn số đếm và nhóm tháng, không select các trường cá nhân của CustomerSubmission, không đưa model khách hàng hoặc dữ liệu gốc vào view/JSON/JavaScript.
- Chỉ approved có source sales và sales_id tương ứng được tính. QR không Sales không tăng điểm cá nhân. Phân tháng tiếp tục theo submitted_at, timezone ứng dụng; không thay quy tắc sang reviewed_at.
- Sales chuyển Dealer vẫn giữ thành tích cá nhân; điểm Dealer tiếp tục dựa trên dealer_id lịch sử của khai báo. Không thay trạng thái duyệt, dữ liệu lịch sử, ảnh hoặc slug.
- Chi tiết khách hàng vẫn ở các route riêng sau đăng nhập: `admin.submissions.show`, `dealer.customers.show`, `sales.submissions.show`; giữ policy theo quyền và snapshot Dealer. Public profile không được nâng thành response nội bộ khi người xem đăng nhập.

## Đề xuất consent công khai riêng — chưa triển khai

Phương án mặc định hiện tại là **chỉ thống kê tổng hợp**. Nếu xác nhận triển khai danh sách có consent:

1. Tách consent công khai khỏi consent thu thập/xử lý. Không bắt buộc đồng ý công khai để tham gia chiến dịch hoặc được tính điểm. Không tự backfill consent cho dữ liệu cũ.
2. Đề xuất bảng mới `customer_publication_consents`: khóa id, FK customer_submission_id, trạng thái requested/granted/revoked, notice_version, notice_hash, scopes, granted_at, revoked_at, recorded_by nullable và evidence_reference nội bộ cho bằng chứng xác nhận. Cần chốt cách xác minh đúng khách hàng và lưu bằng chứng; thao tác Sales/Admin đơn thuần không thay cho sự đồng ý của khách.
3. Scopes là allowlist cho tên đã che, điện thoại đã che, địa lý tổng quát; mặc định không công khai trường nào. Địa lý mặc định tối đa tỉnh/thành khi được cho phép, không mặc định cho phép phường/xã, số nhà hoặc đường.
4. Backend chỉ chọn approved, thuộc Sales đúng nghiệp vụ và consent hợp lệ, chưa rút lại, đúng phiên bản/phạm vi. Rút lại phải có hiệu lực trên mọi trang phân trang; lịch sử consent phải truy vết được, không dùng consent granted cũ để vượt trạng thái revoked mới.
5. Chuyển thành DTO chỉ chứa dữ liệu đã che ở backend trước khi render: tên giữ rất ít ký tự Unicode, phần còn lại `*`; điện thoại tối đa hai số đầu/ba số cuối; địa lý theo scope. Không gửi model gốc, số điện thoại đầy đủ, Facebook, email, ghi chú, xác minh, evidence hoặc ID/URL truy cập dữ liệu gốc qua public.
6. Phân trang và mọi endpoint public phải dùng cùng điều kiện consent; không nhận cờ `public_consent=1` hay dealer_id/sales_id từ browser làm căn cứ. Public không có trang chi tiết khách hàng gốc. Bổ sung test cấp/rút consent, consent giả mạo/hết hiệu lực, scope từng trường, phân trang và vô hiệu hóa cache khi rút lại.

Đây là phương án cần xác nhận, chưa phải schema/migration đã được áp dụng. Không sửa migration lịch sử hoặc tái sử dụng consent QR hiện có để công khai.

## File thay đổi trong lượt này

| File | Nội dung |
|---|---|
| `app/Http/Controllers/Admin/DashboardController.php` | Lấy trạng thái/public_slug trong query Top Sales hiện có; chỉ truyền slug active |
| `resources/views/admin/dashboard.blade.php` | Truyền slug cho Top Sales |
| `resources/views/components/public/ranking-preview.blade.php` | Link tên tới public profile khi hợp lệ; giữ link nội bộ riêng |
| `resources/views/sales/public-profile.blade.php` | Nhãn đại lý hiện tại và thông báo thống kê tổng hợp/bảo vệ khách hàng |
| `tests/Feature/SalesProfilePrivacyTest.php` | 4 tests mới: link profile, consent không đủ điều kiện, chống yêu cầu lộ dữ liệu qua query/phân trang, SQL chỉ tổng hợp, Sales blocked |
| `SALES_PROFILE_PRIVACY_REPORT.md`, `WORKING_TREE_CHANGES.md` | Báo cáo và inventory |

Không thay `SalesPublicProfile` service, schema, routes, policies hoặc logic điểm. File QA/build nằm trong `.laravel-tools`/`public/build` ignored.

## Kết quả kiểm thử

- **69 PASS, 0 FAIL, 0 ERROR; 2.331 assertions** trong nhóm tests liên quan: SalesProfilePrivacyTest, SalesPublicProfileTest, ProfileNavigationTest, DashboardNavigationTest, DealerAuthorizationTest, AdminDashboardTest, PublicPrivacyTest, DealerHistoryTest. Đây là kết quả kiểm thử lượt này, không gộp số tests của các lượt trước.
- Bao gồm guest HTML/Accept JSON, placeholder/ảnh, phone mask, approved/pending/rejected, QR không Sales, ba tháng và biên năm, chuyển Dealer, chi tiết Admin/Dealer, IDOR, collision, phân trang và query cố yêu cầu dữ liệu khách hàng.
- Test mới xác nhận hai truy vấn CustomerSubmission trên public chỉ đếm/nhóm tháng, không select customer_name/phone/address/Facebook/notes/evidence/consent; processing consent có timestamp vẫn không công khai khách hàng.
- Browser Chrome với HTML render từ Laravel test và assets build: **320px, 390px, 768px, 1440px**, avatar và placeholder đều không tràn ngang, không lộ email/điện thoại đầy đủ/evidence; ảnh lỗi quay về placeholder; không lỗi JavaScript. Đã xem ảnh profile 390px.
- Vite build, Pint PHP file trong phạm vi và `git diff --check`: PASS.
- Dùng PHP 8.5 Laragon, database test riêng `toyota_testing_upgrade_20261019` với guard kiểm tra DB; không chạy migration/seed trên DB ứng dụng local.
- Đối chiếu hash 7 bảng nguồn trước/sau: **không đổi**, gồm users/dealers/customer_submissions/moderation_reviews/awards/award_winners/migrations. Giữ nguyên 140 tài khoản, 88 Dealer, 500 khai báo. Không có code ghi/xóa ảnh trong thay đổi này.

Log: `.laravel-tools/sales-profile-privacy/related.log`, `related.xml`, `audit-before.json`, `audit-after.json`. Browser: `.laravel-tools/sales-public-profile/qa/results.json` và ảnh chụp mới.

## Rủi ro và trạng thái còn lại

- **Danh sách khách hàng công khai chưa được mở**, đang chờ quyết định về consent riêng theo yêu cầu người dùng. Khu vực khách hàng hiện chỉ có thống kê tổng hợp.
- Che tên/điện thoại vẫn có nguy cơ tái nhận diện khi kết hợp địa lý, đại lý hoặc thông tin ngoài hệ thống; che dữ liệu không thay cho consent hợp lệ.
- Tổng số và thống kê tháng vẫn là dữ liệu công khai theo yêu cầu; số lượng nhỏ có thể giúp suy luận khi kết hợp thông tin bên ngoài. Không giảm/ẩn số liệu ngoài yêu cầu trong lượt này.
- Tên Sales, đại lý hiện tại, avatar được upload và ba số cuối điện thoại vẫn công khai theo phạm vi đã duyệt; không có email hoặc số điện thoại Sales đầy đủ trong response public.

## Kiểm tra trên Laragon

Admin đăng nhập `/admin/dashboard`, bấm tên Sales active trong Top 3 để mở public profile; link “Chi tiết nội bộ” vẫn mở hồ sơ quản trị. Đăng xuất và mở lại URL profile: chỉ thông tin Sales được phép, tổng approved và ba tháng, không có từng khách hàng. Thử thêm `?include=customers&customers_page=2&public_consent=1` để xác nhận không mở dữ liệu. Dùng màn hình 320/390/1440px để kiểm tra responsive. Không cần migrate hoặc seed cho phần đã triển khai.
