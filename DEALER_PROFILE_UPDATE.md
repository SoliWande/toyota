# Dealer history, immutable email và profile — báo cáo kiểm tra

Ngày kiểm tra: 04/10/2026.

## Implementation

- Submission mới lưu Dealer tại thời điểm tạo. Creation và admin transfer dùng transaction khóa cùng user row; request Sales không quyết định sales_id/dealer_id. Model bảo vệ sales_id/dealer_id sau khi tạo.
- Admin chỉnh Sales name/phone/Dealer hiện tại; status vẫn qua moderation workflow. Không sửa email/role/password ở Sales edit form.
- Dealer leaderboard và award preview lấy attribution từ customer_submissions.dealer_id. Sales leaderboard giữ sales_id. Điểm vẫn approved-only, period theo submitted_at và quy tắc hòa điểm hiện có.
- Queue, Dealer filter, dashboard và duplicate warning đọc Dealer snapshot của submission; lịch sử chưa xác định hiển thị rõ thay vì dùng Dealer hiện tại.
- Profile chỉ thao tác authenticated user. Sales active sửa name/phone/password; Admin active sửa name/password. Không có endpoint profile nhận ID người khác.
- Email không có editable field; request cố gửi email trong profile/Sales edit bị từ chối, kể cả null. Model và MySQL trigger bảo vệ email sau creation, bao gồm bulk/raw UPDATE.
- Change password kiểm tra current password dưới user row lock, xác nhận password mới, Laravel hashing, xoay remember token/session ID. AuthenticateSession yêu cầu các session có password hash cũ đăng nhập lại; session thực hiện đổi password tiếp tục hoạt động.
- Forgot/reset dùng Laravel password broker và notification. Token được hash trong database, hết hạn sau 60 phút, dùng một lần. Reset không sửa email/Dealer/role/status hoặc bỏ qua approval. Response forgot không công bố account tồn tại.
- Rate limiter riêng cho profile writes, change password, forgot và reset; reset pages có no-store. Password fields không flash vào session khi validation thất bại.
- Published award snapshots giữ nguyên sau rename, transfer hoặc block account. Không thêm account merge hoặc workflow ngoài phạm vi.

## Migrations và dữ liệu cũ

1. `2026_10_04_000013_add_dealer_snapshot_to_customer_submissions.php`: nullable dealer_id, FK RESTRICT tới dealers, index (dealer_id, status, submitted_at).
2. `2026_10_04_000014_protect_account_email_updates.php`: MySQL BEFORE UPDATE trigger ngăn đổi users.email, kể cả đổi casing.

Cả hai đã chạy thành công trên database `toyota`. Migration từ database trống được kiểm tra riêng trên `toyota_testing`: 18 migrations, 10 tables, 13 foreign keys. Schema thực tế có FK/index snapshot và email trigger.

**Backfill đã dừng.** Trước thay đổi có 500 submissions, trong đó 300 approved. Không có Dealer snapshot hoặc lịch sử transfer đủ để chứng minh Dealer lúc tạo; moderation_reviews chỉ lưu review status. Không lấy users.dealer_id hiện tại để suy đoán.

Sau migration, 500 record này vẫn có dealer_id NULL, không thay đổi nội dung/trạng thái hoặc awards đã published. Approved legacy records tiếp tục tính Sales, nhưng chưa tính Dealer leaderboard/Dealer award preview. Dealer leaderboard trên dataset hiện tại chưa có điểm cho đến khi có submissions mới hoặc mapping lịch sử đã xác minh. Admin moderation hiển thị “Chưa xác minh đại lý lịch sử”. Cần dữ liệu đối chiếu chính xác trước khi lập migration/backfill riêng; không chạy lại demo seeder để thay thế lịch sử.

## Files chính của thay đổi này

- Nghiệp vụ: SPEC.md.
- Database: hai migrations trên; app/Models/User.php, CustomerSubmission.php, Dealer.php.
- Query/duplicate: app/Services/LeaderboardService.php, ApprovedSubmissionFinder.php.
- HTTP: app/Http/Controllers/ProfileController.php; Auth/PasswordResetController.php, Auth/AuthenticatedSessionController.php; Admin/SalesController.php, Admin/SubmissionReviewController.php, Admin/DashboardController.php; Sales/SubmissionController.php.
- Validation/authorization: app/Http/Requests/ProfileRequest.php; Admin/UpdateSalesRequest.php, Admin/ReviewSalesRequest.php; app/Policies/UserPolicy.php.
- Routing/security: routes/web.php, app/Providers/RouteServiceProvider.php, app/Http/Middleware/SecurityHeaders.php.
- UI: resources/views/profile/edit.blade.php; auth/forgot-password.blade.php, reset-password.blade.php, login.blade.php; admin/sales/edit.blade.php, show.blade.php; admin/submissions/index.blade.php, show.blade.php; admin/dashboard.blade.php; components/layout.blade.php.
- Tests mới: tests/Feature/ProfileManagementTest.php, DealerHistoryTest.php, PasswordRecoveryTest.php.
- Tests điều chỉnh: PerformanceQueryTest.php kiểm tra eager loading Dealer snapshot; DevelopmentDemoSeederTest.php và V1WorkflowTest.php logout trước khi chuyển identity để tương thích AuthenticateSession.

Factory submissions hiện có tự nhận snapshot qua model creation event; không seed hoặc ghi đè dataset development trong task này.

## Kết quả kiểm tra cuối

- Targeted suite (profile, Dealer history, password recovery, demo và V1 workflow): **47 tests, 468 assertions, 0 failures**.
- Full PHPUnit suite trên MySQL toyota_testing: **486 tests, 3.156 assertions, 0 failures/errors/skips**. Bao gồm test duplicate approval với hai process/transactions MySQL thật và các kiểm tra query count.
- `npm.cmd run build`: pass.
- Laravel Pint: pass, 156 PHP files.
- `git diff --check`: pass.
- Chrome headless trên Apache thật: **90 page loads** tại 320/390/1440px, login Admin/Sales thật, profile/form chuyển Dealer/forgot/reset, pagination, moderation và private evidence; không lỗi JavaScript, không tràn ngang trong kiểm tra tự động. Đã xem ảnh profile mobile.

## Việc còn cần xử lý

- Cung cấp mapping/audit tin cậy để xác định Dealer lịch sử của 500 record cũ. Phần này chưa thực hiện theo yêu cầu không suy đoán.
- Kiểm thử broker/notification thành công; chưa gửi email thực tế qua SMTP. Trước production cần xác nhận mail transport, sender, APP_URL và HTTPS, kiểm tra nhận email reset thật. Database account chạy migration email trigger cần quyền TRIGGER.

Không push Git trong task này.
