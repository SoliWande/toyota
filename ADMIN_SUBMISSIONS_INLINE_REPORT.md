# Admin quản lý và duyệt khai báo inline

Ngày 20/10/2026. Laravel 13, PHP Laragon 8.5.11. Phạm vi `/admin/submissions` và bộ lọc hiện có. Không reset Git/database, không chạy migration trên database ứng dụng local, không thay đổi production.

## Audit và cách giữ nghiệp vụ

Source đã có danh sách, bộ lọc, duyệt/từ chối, CustomerSubmissionPolicy và action duyệt khóa giao dịch. Tận dụng những thành phần này. Hai thiếu sót chính trước sửa: form review dùng JavaScript `confirm()`, và chưa có audit cho chỉnh sửa thông tin. Dealer/Sales trước đây bất biến với mọi thao tác; chỉ bổ sung ngoại lệ Admin cho liên kết của bản ghi pending.

Không đổi quy tắc approved mới có điểm, định danh chống trùng Facebook, thời điểm thống kê, nguồn đăng ký hoặc consent. Khách QR tiếp tục không có Sales, không cộng điểm Sales. Không hỗ trợ gán Sales cho QR bằng cách đổi source hoặc tạo tài khoản giả.

## Frontend

- Mỗi dòng hiện tên khách, số điện thoại, Dealer lịch sử, Sales nếu có, nguồn đăng ký, thời điểm, trạng thái và thao tác.
- Dealer lấy trực tiếp từ submission.dealer_id. Sales hiện thuộc Dealer khác chỉ được chú thích, không dùng để suy diễn lại Dealer lịch sử.
- QR hiện “Khách tự đăng ký” ở mục Sales và “Khách tự đăng ký qua QR Dealer” ở nguồn. Thiếu Dealer/quan hệ Sales sai role có cảnh báo; không sinh link Sales không hợp lệ hoặc tự gán để che lỗi.
- “Chỉnh sửa” mở native `<details>` ngay dưới dòng. Form có tên, điện thoại, địa chỉ hành chính tỉnh/phường và địa chỉ chi tiết, loại xe hoặc mô tả xe QR, Dealer và Sales theo quyền. Không có trang edit riêng, modal hoặc popup.
- Nút Lưu/Hủy, validation ngay trong form tương ứng, khôi phục dữ liệu nhập của đúng bản ghi. Hủy đóng form và khôi phục trường địa chỉ; hủy request tải phường đang chạy để tránh kết quả cũ ghi đè.
- Khai báo approved/rejected khóa Dealer/Sales; QR không có lựa chọn Sales. Backend kiểm tra lại, không dựa vào disabled ở giao diện.
- Form chỉnh sửa submit PATCH rồi redirect về danh sách giữ tìm kiếm, trạng thái, Dealer/Sales, khoảng ngày và trang. Bản ghi không còn khớp bộ lọc sau sửa/duyệt có thể rời danh sách; đây là kết quả của cùng bộ lọc.
- Review dùng khung xác nhận native `<details>` trong dòng: mở “Duyệt khai báo” chưa gửi request; chỉ “Xác nhận duyệt” mới POST. Từ chối cũng xác nhận inline. Không còn alert/confirm/modal trong review form, kể cả trang chi tiết dùng chung component.
- JavaScript chỉ quản lý Hủy và khóa submit, trạng thái aria-busy/“Đang xử lý”; backend vẫn chống gửi lặp. Ghi chú và Facebook xác minh QR được giữ lại khi validation lỗi.

## Backend

- Route PATCH mới dùng cùng middleware Admin active và policy review hiện có; Dealer/Sales/guest bị chặn.
- UpdateSubmissionRequest chỉ validate PATCH body cho trường chỉnh sửa; query filters được validate riêng, không nhầm `status=pending` trong URL với yêu cầu sửa trạng thái. Chặn source, status, thời điểm, người duyệt, Facebook/định danh, evidence, consent, điểm và tên địa chỉ do browser tự gửi.
- Tên/điện thoại/địa chỉ/xe được validate server; tên tỉnh/phường tra từ directory sẵn có. QR vẫn bắt buộc điện thoại, địa chỉ, mô tả xe và Sales null. Không tự xóa địa chỉ nếu toàn bộ trường địa chỉ được bỏ khỏi payload.
- Đổi Dealer/Sales chỉ khi pending, phải tồn tại và Sales thuộc Dealer được chọn. Khóa User giống luồng chuyển Sales khi kiểm tra phân công. Giữ source bất biến. Nếu không đổi liên kết, Sales đã chuyển đại lý không làm thay đổi snapshot.
- Approved/rejected chỉ sửa thông tin được cho phép; không đổi Dealer/Sales, trạng thái, review metadata, submitted_at hoặc điểm. Hiệu chỉnh liên kết approved **chưa triển khai**.
- Thay số điện thoại kiểm tra các khai báo pending/approved khác, bao gồm số legacy có khoảng trắng/dấu phân cách/+84. Khóa advisory MySQL theo HMAC số điện thoại và database để hai chỉnh sửa Admin không cùng giành một số. Không thêm unique index điện thoại hoặc thay định danh Facebook của nghiệp vụ duyệt.
- Row lock và HMAC edit_version phát hiện phiên bản cũ, tránh Admin ghi đè chỉnh sửa/duyệt của người khác. Submit lặp cùng version bị từ chối khi dữ liệu đã thay đổi.
- Thay đổi khách hàng và audit insert nằm cùng transaction; audit hỏng thì rollback. Audit ghi actor, thời gian, tên trường và before/after an toàn: ID Dealer/Sales, mã tỉnh/phường, mã xe giữ giá trị; tên giữ một ký tự, điện thoại giữ ba số cuối; địa chỉ/free text ẩn. Không ghi payload hoặc PII đầy đủ vào application log.
- Action duyệt kiểm tra thêm Dealer và Sales/source hợp lệ sau row lock. Giữ unique index Facebook và retry giao dịch hiện có. Hai Admin duyệt cùng bản ghi chỉ một thành công; hai bản ghi cùng Facebook chỉ một approved. Không có lần cộng điểm thứ hai.
- Query danh sách là **7 truy vấn cố định/trang có dữ liệu**: count/page, eager Sales/Dealer, hai danh sách bộ lọc và kiểm tra bảng audit. Không truy vấn quan hệ theo từng dòng.

## Migration mới và trạng thái áp dụng

`database/migrations/2026_10_20_000022_create_submission_edits_table.php` tạo bảng riêng:

| Trường | Mục đích |
|---|---|
| id | Khóa audit |
| customer_submission_id nullable FK | Liên kết khai báo, nullOnDelete |
| submission_id_snapshot | Giữ mã khai báo sau khi bản ghi pending bị xóa hợp lệ |
| edited_by FK users | Admin thực hiện |
| changes JSON | Before/after đã giảm thiểu/che dữ liệu |
| edited_at | Thời điểm chỉnh sửa |

Model audit append-only. FK không cản chức năng Sales xóa khai báo pending vốn có; log vẫn giữ snapshot ID và nội dung đã che. Không đổi bảng CustomerSubmission hoặc sửa migration lịch sử.

**Database local `toyota`: Pending, chưa chạy.** Chỉ chạy migration trong database test riêng qua test framework. Khi local chưa có bảng, giao diện thông báo chờ migration và không mở form chỉnh sửa; endpoint cũng từ chối lưu trước khi thay đổi dữ liệu. Duyệt/từ chối vẫn hoạt động theo quyền, kèm guard Dealer mới. Đây là trạng thái có chủ đích để tuân thủ yêu cầu không chạy migration local khi chưa được phê duyệt.

Nếu sau này cần sửa Dealer/Sales của approved: cần quy trình hiệu chỉnh riêng với lý do, audit và cách điều chỉnh/đối soát điểm, bảo toàn award snapshots đã công bố. Chưa tạo ledger, migration hoặc cơ chế sửa approved trong lượt này; cần thống nhất và phê duyệt trước.

## File thay đổi trong lượt này

- `app/Actions/ApproveCustomerSubmission.php`
- `app/Actions/UpdateCustomerSubmission.php` (mới)
- `app/Http/Controllers/Admin/SubmissionReviewController.php`
- `app/Http/Requests/Admin/UpdateSubmissionRequest.php` (mới)
- `app/Models/CustomerSubmission.php`
- `app/Models/SubmissionEdit.php` (mới)
- `app/Services/SubmissionIntegrity.php` (mới)
- `database/migrations/2026_10_20_000022_create_submission_edits_table.php` (mới, local Pending)
- `resources/views/admin/submissions/index.blade.php`
- `resources/views/components/submission-inline-edit.blade.php` (mới)
- `resources/views/components/submission-review-form.blade.php`
- `resources/js/app.js`
- `resources/js/customer-address.js`
- `resources/js/submission-inline.js` (mới)
- `routes/web.php`
- `tests/Feature/AdminSubmissionInlineTest.php` (mới)
- `tests/Feature/AdminSubmissionModerationTest.php`
- `tests/Feature/PerformanceQueryTest.php`
- `tests/Feature/SubmissionApprovalRaceTest.php`
- `tests/Fixtures/approve_submission.php`
- `ADMIN_SUBMISSIONS_INLINE_REPORT.md`, `WORKING_TREE_CHANGES.md`

Route mới: PATCH `/admin/submissions/{submission}` (`admin.submissions.update`) và GET `/admin/submissions/address/wards/{province}` (`admin.submissions.address.wards`). Cả hai chỉ Admin active; wards chỉ trả directory hành chính, không có dữ liệu khách hàng. Route ID/province có regex, không xung đột Dealer/Sales slug. Không có endpoint khách hàng public mới.

## Kiểm thử

- Nhóm liên quan trước rà soát cuối: **58 PASS, 0 FAIL; 484 assertions**. Có giao dịch MySQL thật trong hai subprocess cho cả race cùng bản ghi và race cùng Facebook.
- Full suite phiên bản cuối: **699 PASS, 0 FAIL, 0 ERROR, 0 SKIP; 13.688 assertions**, thời gian 3 phút 54 giây. Gồm 112 Unit Tests và 587 Feature Tests, có fail-on-warning/deprecation. Đã bao gồm quan hệ Sales sai role, bảo toàn quyền xóa pending có audit, rollback khi audit lỗi và race MySQL thật.
- Browser Chrome với HTML test của Laravel và JS build: 320px, 390px, 1440px không tràn ngang, không trùng ID; mở/Hủy form đúng dòng; mở/Hủy xác nhận không gửi request; đúng một request edit và một request approve sau nút cuối, chặn requestSubmit lặp, không có native dialog hoặc lỗi JavaScript. DB persistence/authorization kiểm tra bằng Feature Tests; browser fixture không ghi dữ liệu local.
- Đã xem ảnh edit 390px. Vite build thành công, không thêm dependency hoặc framework UI.
- Tests dùng PHP Laragon 8.5 trên database `toyota_testing_upgrade_20261019` với guard; không gọi migrate:fresh/db:wipe/reset trên database ứng dụng.
- Vite build, Pint các PHP file trong phạm vi và `git diff --check`: PASS. Kiểm tra lại route và migration-status xác nhận migration 000022 vẫn Pending trên local.
- Đối chiếu hash 7 bảng nguồn trước/sau: không đổi; giữ nguyên 140 tài khoản, 88 Dealer và 500 khai báo. Kiểm chứng baseline ảnh và lịch sử cũng PASS: 1.001 file evidence, 50 avatar và điểm Dealer được bảo toàn.

Log: `.laravel-tools/submission-inline/related.log`, `related.xml`, `full-suite-initial.log/xml`, `full-suite.log/xml`, `qa/results.json`, `qa/edit-*.png`, `qa/confirm-*.png`, `audit-before.json`, `audit-after.json`, `migration-status.log`.

## Kiểm thử trên Laragon

1. Với trạng thái hiện tại: Admin vào `/admin/submissions?status=pending`, kiểm tra thông tin Dealer/Sales/nguồn/cảnh báo và xác nhận duyệt inline. Chỉnh sửa hiện thông báo chờ migration.
2. **Sau khi phê duyệt áp dụng migration local**, dùng PHP 8.5 Laragon chạy đúng migration mới (không dùng fresh/wipe/seed):

```powershell
& 'D:\laragon\bin\php\php-8.5.11-nts-Win32-vs17-x64\php.exe' artisan migrate --path=database/migrations/2026_10_20_000022_create_submission_edits_table.php
```

3. Tải lại danh sách, mở Chỉnh sửa, sửa tên/điện thoại/địa chỉ/xe, Lưu/Hủy; thử số điện thoại trùng và Phường/Xã không đúng Tỉnh/Thành. Lỗi phải ở form tương ứng. Lưu hợp lệ không đổi trạng thái duyệt.
4. Với pending: thử cặp Dealer/Sales đúng/sai; QR không được gán Sales. Với approved/rejected: Dealer/Sales khóa và backend từ chối giả mạo thay đổi.
5. Giữ filter/tìm kiếm/trang 2 khi lưu/duyệt; mở xác nhận duyệt không thay đổi gì, Xác nhận duyệt mới gửi. QR vẫn yêu cầu Facebook đã xác minh trước duyệt. Thử bấm gửi nhanh hai lần và hai phiên Admin.
6. Sales/Dealer thử PATCH Admin phải bị chặn; Dealer chỉ xem khách hàng theo quyền cũ. Public không xuất hiện dữ liệu/audit/endpoint nội bộ.

Không deploy production. Chỉ sau khi kiểm thử và phê duyệt riêng mới xem xét áp dụng migration ở môi trường khác.

Trạng thái bàn giao: code và kiểm thử đã hoàn tất; không phát hiện lỗi còn tồn tại trong phạm vi đã kiểm tra. **Chưa bật lưu chỉnh sửa trên database local** vì migration audit chưa được phê duyệt áp dụng. Hiệu chỉnh Dealer/Sales của approved nằm ngoài phần đã triển khai.
