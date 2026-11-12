# P09N — E1 Admin Call Audit Finding Review

## Mục tiêu

Backend read-only truy xuất finding_pending đáng tin cậy từ Wrong C1, đúng batch/item/Dealer/attempt/actor, không tác động điểm/verification/Awards.

## Trạng thái

DONE — 2026-11-12, phạm vi backend E1 read-only. Khởi đầu IN PROGRESS; nghiệm thu mới bên dưới thay kết luận chưa kiểm thử ở bước khởi tạo, giữ lịch sử C1/C2/P09M.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09J-B/P09K/P09M và riêng C07/C08 inventory, đối chiếu source/models/policy/routes/readers/actions/tests. C1 lưu finding trong attempt/event/projection, chưa có bảng hoặc review action riêng. C2 kiểm consistency nhưng không verify chữ ký result receipt. C07/C08 chỉ có trạng thái gọi/tỷ lệ, không frame review finding riêng; E1 không tạo UI. Working tree chứa nhiều phase trước chưa commit, giữ nguyên.

## Các file source code liên quan

Các file mới thuộc P09N:

- `app/Http/Controllers/Admin/CallAuditFindingController.php`.
- `app/Http/Requests/Admin/CallAuditFindingReadRequest.php`.
- `app/Services/CallAudit/FindingReadModel.php`.
- `app/Services/CallAudit/FindingEvidence.php`.
- `tests/Feature/AdminCallAuditFindingTest.php`.
- `tests/Fixtures/call_audit_findings_snapshot.php`.
- Tracking này.

`routes/web.php` chỉ thêm import và hai GET finding; các edits trước task được giữ nguyên. Dùng lại C1 `ClaimCoordinator`/`CallResultPolicy`, C2 `WorkflowReadModel`, models và `CallAuditBatchPolicy`; không sửa chúng trong P09N.

## Những thay đổi đã thực hiện

Khởi tạo: tạo tracking trước sửa source, chưa thao tác database. Cập nhật nghiệm thu 2026-11-12:

- GET `/admin/call-audits/{batch}/findings` và `/admin/call-audits/{batch}/findings/{item}`. Query bắt buộc `purpose=general_audit|award_top3`; tùy chọn `month=YYYY-MM`. Danh sách nhận `page`/`per_page` (mặc định20, tối đa50); detail không nhận pagination. Không thêm mutation, UI, navigation hoặc metric/ngưỡng xử phạt.
- Trả finding hiện hành có bằng chứng Wrong, gắn batch/item/attempt/event/Dealer snapshot/actor ID/thời điểm. ID finding bằng item ID, không tạo finding mới hoặc ghi dữ liệu khi GET. Một lần no-answer rồi Wrong vẫn chỉ một finding.
- Kiểm consistency toàn batch bằng C2, sau đó verify receipt có chữ ký C1 cho từng attempt của item pending, liên kết digest tiền nhiệm và fingerprint snapshot. Kiểm mọi finding trước pagination, không để evidence hỏng ngoài trang làm tăng total. Projection giả, thiếu/duplicate attempt/event, receipt sửa, snapshot hoặc version/key không khớp đều fail closed `409 audit_findings_unavailable`; không sửa tự động.
- Mỗi request kiểm Admin active ở middleware/request/policy và service (đọc lại quyền hiện hành). Child item phải thuộc đúng batch/purpose/month; sai scope hoặc item không có finding trả404. Admin active khác người tạo/ghi nhận vẫn được đọc.
- Response dùng allowlist, phone masked, `Cache-Control: no-store, private`. Không serialize encrypted model; không trả full phone, Facebook, biển số, note, answers, source snapshot, receipt, seed, manifest, digest hoặc key. Không query nguồn live/Dealer/Award/caller để dựng evidence; không ghi exception riêng tư ra log. Query lạ/không hợp lệ trả422 generic, không echo hoặc tạo pagination URL chứa input.
- Đã xem lại screenshot Figma C07 `164:867`, C08 `165:832`, file `yKmWQI6BYJ6dponEnidlsq`, session `edf27b5e-4417-4366-8601-fb657b86d73e`. Chưa có frame review finding, đúng giới hạn user: chỉ backend, không thêm màn hình/modal/nút.

## Quyết định kỹ thuật quan trọng

Finding ID logic là item_id, không tạo record khi đọc; mỗi item chỉ trả latest Wrong hợp lệ sau khi kiểm cả lịch sử. C07/C08 overlap cùng submission vẫn là hai item khác scope, không hợp nhất. Snapshot là evidence lịch sử, không tuyên bố source live hiện tại vẫn Sai; không auto resolve khi nguồn thay đổi. Actor ID giữ từ attempt/event, không giả tên lịch sử khi C1 chưa lưu actor-name snapshot.

Read transaction không dùng locking read hoặc gọi lại sampling/claim/record. Chữ ký được dựng đúng envelope version C1 hiện hành, không sửa format/history cũ. Key/version không còn hỗ trợ trả lỗi an toàn; rotation/migration key là quy trình riêng. Xác thực commit không đồng nghĩa chứng minh lời khai khách hàng đúng/sai ngoài đời, không phải bộ verifier toàn ledger và không phát sinh quyền xử phạt. Không decrypt note hoặc claim-token receipt để dựng intent gốc; chỉ đọc result receipts cần thiết.

## Kết quả kiểm thử

Khởi tạo: NOT_RUN, chờ preflight. Nghiệm thu mới 2026-11-12:

| Kiểm tra | Lệnh/kết quả mới |
|---|---|
| Preflight | `php tests/Fixtures/call_audit_findings_snapshot.php before`: APP_ENV=testing, mysql TCP `127.0.0.1:3306`, database thực `toyota_testing`, MySQL8.0.30, FK checks1, InnoDB; bốn bảng audit ban đầu0 rows. Từ chối config URL/socket/read-write override trước PDO |
| Tests chọn lọc | `php vendor/bin/phpunit tests/Feature/AdminCallAuditFindingTest.php tests/Feature/AdminCallAuditReadIntegrationTest.php tests/Feature/AdminCallAuditReadTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped --log-junit storage/app/private/p09n-call-audit-claim/tests.xml`: **93 tests / 1.066 assertions PASS**, không skipped |
| Coverage mới | 37 ca finding: Wrong thật/giả; preceding no-answer; duplicate attempt/event; stale/tampered receipts và snapshot; off-page corruption; authorization/IDOR/fresh blocked Admin; privacy/no logs; month/purpose/C07-C08 overlap; pagination/zero/underfill; lịch sử giữ nguyên khi nguồn/Dealer/caller đổi; GET chỉ SELECT và không mutation |
| Syntax/Pint | `php -l` cả6 PHP mới và `routes/web.php`: PASS. `php vendor/bin/pint --test` đúng7 file: PASS |
| Postflight | `php tests/Fixtures/call_audit_findings_snapshot.php after`: audit rows `[0,0,0,0]`; `changed_tables=[]` trên19 bảng (rows/checksum/schema); `source_changed=false` trên215 file app/migration có trước task. Tất cả1.211 fixture journals đã cleaned, không còn fixture |
| Diff/tài liệu | `git diff --check` và whitespace check riêng các file mới; tracking có đủ9 mục. Không sửa các thay đổi phase trước |

Các lệnh tests đặt biến môi trường test rõ ràng: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ file cache test không tồn tại. Fixtures dùng harness A3 có ID/tag journal và cleanup theo dependency; không có fixture runtime. Snapshot/JUnit private ở `storage/app/private/p09n-call-audit-claim/`. Schema hash bỏ AUTO_INCREMENT vì insert/cleanup không khôi phục bộ đếm; không reset counter.

Không migrate/seed/reset/full regression hoặc kết nối production. Không sửa schema/source scoring/moderation/verification/Award/published snapshots; postflight xác nhận dữ liệu nghiệp vụ không đổi. Không build/browser vì E1 không thay UI/assets.

## Những lỗi còn tồn tại

Bước đầu chưa nghiệm thu đã được thay bằng kết quả mới phía trên; không còn FAIL/BLOCKED trong E1. Review UI chưa có frame; correction, approve/reject, penalty, void, publication và xử lý finding E2 chưa được mở. Đây là giới hạn phạm vi, không chặn backend E1. Actor chỉ có ID lịch sử; nếu cần tên lịch sử phải có contract/snapshot riêng, không lấy tên hiện tại giả làm lịch sử.

## Task hoặc bước tiếp theo

E1 hoàn tất để review. E2 cần task/quyết định nghiệp vụ riêng trước bất kỳ mutation finding, trừ điểm, hủy xác minh hoặc kết nối công bố C07; UI review cần frame Figma phù hợp. Tracking này không cấp quyền deploy production.
