# P07 — Tích hợp thao tác C02

## Mục tiêu
Nối commands Laravel hiện có vào C02; modal sửa/inline bổ sung theo Figma, giữ lock/audit/version/CSRF/quyền và context hàng chờ. Không Undo/scoring/schema mới.
## Trạng thái
2026-11-10: bắt đầu IN PROGRESS; cập nhật BLOCKED nghiệm thu toàn bộ thiết kế vì các contract dưới đây. Phần tích hợp được backend hỗ trợ đã hoàn thành và kiểm tra.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, C02/P07, kết quả P06, source requests/controllers/actions/verification và UI cũ. P02/P03/P05/P06 có thay đổi sẵn, giữ nguyên. P06 chỉ GET/disabled; deep link cũ có form confirm/reason/edit đầy đủ. Figma session9e0238ac-5866-413f-8956-c13f290b5a79, đọc main/inline/modal206:1169.
## Các file source code liên quan
Đã sửa:
- `app/Http/Controllers/Admin/{SubmissionReviewController,SubmissionVerificationController}.php`, `app/Http/Requests/Admin/UpdateSubmissionRequest.php` (chỉ redirect).
- `resources/views/admin/submissions/{index,queue-detail,edit-modal,show}.blade.php`, `resources/views/components/submission-review-form.blade.php`.
- `resources/css/admin-submissions.css`, `resources/js/admin-submissions.js`, `public/admin/icons/c02-modal-close.png`.
- `tests/Feature/{AdminSubmissionActionsTest,AdminSubmissionQueueTest,AdminSubmissionInlineTest,AdminSubmissionModerationTest}.php`.
Đã đọc, không sửa: actions Approve/Reject/UpdateCustomerSubmission, SubmissionVerification, policy, validation rules, leaderboard, P01 components, routes/schema. Các thay đổi P02/P03/P05/P06 có sẵn vẫn giữ nguyên.
## Những thay đổi đã thực hiện
Ban đầu tạo tracking, đối chiếu field/reason/toast. Cập nhật:
- Sales Xác minh POST action cũ; QR chuyển deep link cũ để nhập Facebook đã xác minh. Từ chối mở confirm/reason cũ, không tạo modal mới.
- Inline bổ sung dùng reason enum/version/audit thật; ghi chú chưa lưu được giữ disabled. Không toast gửi tin.
- Modal P01 720×530: sửa tên/SĐT/xe/biển số; QR pending có thể đổi Dealer. Giữ địa chỉ QR bắt buộc bằng giá trị hiện tại, không bỏ validation. Field chưa hỗ trợ disabled và không gửi; không lưu biển số placeholder đã mask.
- Adapter JSON chỉ cho `c02=1`; trả lỗi 422, giữ input, chống submit lặp; Huỷ không ghi. GET lại dữ liệu/count sau thành công, giữ filter/workflow/page/selected còn hợp lệ; fallback khách đầu trang và clamp trang cuối khi khách rời tab. Cập nhật badge từ HTML backend, không sửa shell.
- Toast sửa chỉ khi có thay đổi; toast +1 chỉ khi submission thật sự đóng góp vào query leaderboard hiện hành. Không Undo.
## Quyết định kỹ thuật quan trọng
Giữ nguyên actions/policies/schema và request validation. Lock, audit, duplicate, edit_version và CSRF vẫn qua pipeline cũ; thêm kiểm tra đúng target form C02 trước review. Reject/QR approval cần input ngoài C02 giữ luồng cũ có context quay lại queue. Checkbox không có typed evidence không được suy thành membership hoặc approval. P06 mô tả read-only là lịch sử, được P07 thay thế ở controls/adapter; không sửa báo cáo P06.
## Kết quả kiểm thử
Preflight `php docs/figma/qa/competition-db-check.php`: PASS actual loopback `toyota_testing`, 15 tables, config cache absent. Mọi PHPUnit dùng `APP_CONFIG_CACHE=storage/framework/testing/p07-config-unused.php`, bootstrap `docs/figma/qa/competition-test-bootstrap.php`, `--process-isolation --do-not-cache-result`; transaction rollback, không migrate/seed/reset.
- `npm run build`: PASS. Pint 4 file PHP liên quan và `git diff --check`: PASS.
- PHPUnit AdminSubmissionActions/Queue/Inline/Moderation + SubmissionIntegrityUpdate: **60 tests / 461 assertions PASS**.
- RegistrationLookupStates filter `test_(admin_authorization|structured_blocker|real_csrf|profile_change|approved_metrics|manual_progress|rejected_member)`: **10 / 134 PASS**.
- SubmissionDuplicate bỏ riêng `test_same_name_and_phone_with_different_facebook_can_both_be_approved`: **13 / 151 PASS**. Test bị bỏ đã chạy lượt đầu và ERROR ngay khi tạo fixture trùng SĐT: kỳ vọng cũ trái `CustomerSubmission::performInsert` hiện hành. Không sửa test/nghiệp vụ để che lỗi. Các lỗi adapter/fixture/assertion của lượt đầu đã sửa và kiểm tra lại như trên.
- Fixture QA cách ly `.laravel-tools/P07VisualFixtureTest.php`: **1 / 19 PASS**. Playwright `.laravel-tools/p07-qa.mjs`: PASS cancel/reset, 422 giữ input, edit/supplement/approve, count/selected/filter, không Undo/toast delivery/overflow/broken image/pageerror. Phát hiện và sửa `input[name=action]` che thuộc tính `form.action`; dùng `getAttribute('action')`.
- Đã xem trực tiếp ảnh Figma C02/inline/modal và ảnh Chromium **1440×1405**. Modal đo **720×530**, list500/detail612, inline562×246. Artifacts cục bộ (ignored): `docs/figma/qa/artifacts/p07-admin-actions-20261110/` gồm ảnh Figma, C02, modal, sau approve và checks.json. Browser replay HTML/JSON do Laravel thật xuất trong transaction test; đây không phải phiên browser CRUD trực tiếp. Mutation thật được kiểm thử HTTP bằng PHPUnit.
- Không chạy SubmissionApprovalRaceTest vì dùng DatabaseMigrations, trái giới hạn không migrate/reset; không full regression hoặc production.
## Những lỗi còn tồn tại
BLOCKED riêng: Undo24h; delivery khách/Sales và toast “Đã gửi”; reason Figma “Biển số không khớp câu trả lời trong group”/ghi chú chưa có contract writer; Facebook/năm chưa được writer hỗ trợ; đổi province cần ward ngoài thiết kế; Sales reassignment thiếu Sales field. Select chỉ gửi hai reason enum backend thật; option Figma chưa hỗ trợ disabled. Không bỏ validation hoặc thêm field/UI ngoài frame. Checklist SĐT/biển số chưa có typed evidence vẫn không check. Các giới hạn flag/source từ P06 giữ nguyên. Vì vậy chưa tuyên bố giống tuyệt đối Figma hoặc DONE toàn bộ. Test duplicate cũ nêu trên còn ERROR; race chưa chạy.
## Task hoặc bước tiếp theo
Chốt contract cho các phần BLOCKED trước khi mở chỉnh sửa/reason/delivery/Undo. Đối chiếu lại test duplicate lịch sử với nghiệp vụ hiện hành trong task riêng; nếu cần concurrency test phải có cách chạy không migrate và quyền phù hợp. Không còn sửa source nào trong phạm vi tích hợp đã được hỗ trợ.
