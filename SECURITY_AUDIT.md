# Security & data integrity audit — 04/10/2026

## Phạm vi và kết luận

Đã đọc SPEC.md, routes, controllers, FormRequests, middleware, policies, actions, models, services, migrations, seeders, Blade và cấu hình auth/session/logging. Kiểm tra bổ sung bằng PHPUnit trên `toyota_testing`, HTTP thực tế trên Open Server, Composer audit và npm audit. Không thêm feature, không thay đổi nghiệp vụ hoặc nội dung SPEC.md trong đợt audit này.

Các vấn đề có thể sửa trong ứng dụng và cấu hình Apache hiện tại đã được sửa. **Chưa thể xác nhận sẵn sàng production:** Laravel 10.50.3 và PHP 8.1.9 đã hết hỗ trợ bảo mật; Composer vẫn báo advisories của framework. Các biện pháp giảm thiểu bên dưới không thay thế nâng cấp nền tảng.

## Phát hiện và sửa

| Mức độ | Phát hiện | Khắc phục và bằng chứng |
| --- | --- | --- |
| High | Apache phục vụ trực tiếp `composer.json` và `storage/logs/laravel.log` qua `/toyota/...`, HTTP 200. | `.htaccess` tại gốc project chặn truy cập; `public/.htaccess` cho phép public. Tám đường dẫn source/secrets/log/test/git/preview đều trả 403 sau sửa; landing vẫn 200. Không xóa log. |
| Medium | `/api/user` serialize toàn bộ User, có thể lộ ghi chú review nội bộ. | Chỉ trả id/name/email/role/status/dealer_id của người đã xác thực; thêm hidden cho admin_note/rejection_reason. Regression test kiểm tra exact JSON và serialization. |
| Medium | `/api/user` không kiểm tra trạng thái tài khoản. | Thêm `account.active`; pending/rejected/blocked đều 403, guest 401. |
| Medium | Exception database có thể ghi SQL bindings và dữ liệu khách/password vào log, kể cả khi được exception khác bọc lại. | Handler chỉ ghi thông báo cố định, loại exception, SQLSTATE/mã lỗi và vị trí code. Không ghi SQL, bindings, error message gốc hoặc exception/trace chứa arguments. Test cả exception trực tiếp và được bọc. |
| Hardening | Response chứa khách hàng/tài khoản chưa có yêu cầu no-store rõ ràng. | Middleware đặt `Cache-Control: private, no-store` cho khu vực tài khoản, admin, Sales, API user và auth forms; thêm nosniff, SAMEORIGIN và referrer policy. Đã kiểm tra feature test và HTTP headers thực tế. |
| Hardening | Admin mutation endpoints chưa có rate limit; TrustHosts chưa bật. | Admin writes dùng chung 60 request/phút/admin; không giới hạn GET/HEAD trong limiter này. Bật TrustHosts theo APP_URL ngoài local/testing. Tests xác nhận 429/Retry-After và từ chối host không tin cậy. |
| Hardening | APP_DEBUG=true ở môi trường local; production có thể kế thừa debug/cookie settings không an toàn. | Đổi local `.env` và `.env.example` sang debug=false; production provider buộc debug=false và secure session cookie. Local HTTP vẫn hoạt động; production bắt buộc HTTPS. Test cookie Secure/HttpOnly/SameSite và response lỗi không lộ chi tiết. |
| Mitigation | Framework audit có advisory email CRLF và debug XSS. | Login/register từ chối control characters trong email; debug đã tắt. Test CRLF/NUL/tab và debug solution endpoints không hoạt động ở production. Chưa nâng cấp framework; advisory vẫn tồn tại ở dependency. |

## Các kiểm tra không phát hiện bypass trong luồng hiện có

| Hạng mục | Cơ chế/bằng chứng |
| --- | --- |
| Authentication | Password hashing, login lỗi chung, session ID rotation, logout invalidate session + đổi CSRF token; tests hiện có đều pass. |
| Authorization/account status | Admin routes yêu cầu auth + admin + active; Sales routes yêu cầu auth + sales + active; policies/actions tiếp tục authorize backend. Không có route cập nhật quyền tài khoản của Sales. Session bị block bị từ chối ở request tiếp theo. |
| IDOR/SQL injection | Danh sách Sales scope theo authenticated owner; detail/edit/update/delete kiểm tra owner. Search có nhóm điều kiện và binding, escape LIKE wildcards; SQL payload và forged owner filter không lấy được dữ liệu Sales khác. |
| Mass assignment/role escalation | User fillable chỉ name/email/password; registration gán sales/pending và Dealer active phía backend, recheck Dealer dưới lock. Submission chỉ nhận bốn trường khách hàng; owner/status/time/reviewer được gán backend. Tests forged fields và role/status đều pass. |
| CSRF | Không có CSRF exception tùy chỉnh. Test thật ngoài testing bypass: auth mutations và mọi nhóm Sales/admin mutation đều bị 419 khi thiếu token. Không mutation nghiệp vụ qua GET. |
| XSS/validation | Blade escape tên/notes/reasons/snapshots; không thấy raw Blade output cho dữ liệu người dùng. URL Facebook normalize/allowlist host và scheme, link dùng noopener noreferrer. FormRequests giới hạn kiểu/độ dài/enum/filter; không fetch Facebook URL ở backend. |
| Approved submissions | Update/delete chỉ pending; row lock và authorize lại trước mutation. Approved/rejected chỉ đọc qua Sales endpoints. HTTP thử sửa/xóa approved không làm thay đổi record. |
| Duplicate approval/concurrency | Approve recheck profile, lock submission, transaction bao gồm review history; generated identity + unique index là chốt cuối. Test hai PHP processes/MySQL transactions đồng thời: một approved, một pending, chỉ một history. |
| Public/customer exposure | Landing/leaderboard/awards chỉ hiển thị thống kê, ranking và snapshot; draft không public. Tests kiểm tra không có customer name/URL/phone/notes và account email/phone. |
| Audit/history integrity | FK/CHECK/unique hiện có, moderation append-only và published award/winners immutable qua model; snapshot không đọc tên/điểm live. Dealer không có delete endpoint. FK của sales_dealer_id_snapshot đã có trong migration 000009; xác minh metadata và bổ sung tests raw SQL chặn xóa Dealer lịch sử/ghi orphan. |
| Auth rate limiting | Login 5/phút/email+IP và 30/phút/IP; register 5/giờ/IP; Sales writes 20/phút/user, không thay đổi các ngưỡng nghiệp vụ hiện có. |
| Secrets/logs | .env/auth.json/vendor/node_modules/generated files không được Git track. Kiểm tra log local hiện có không tìm thấy các dấu hiệu SQL bindings/password key/customer fields đã dò; đây không phải khẳng định mọi log đều sạch. Log đã bị chặn truy cập HTTP. |

## Dependencies và giới hạn production

- `npm audit --json`: 0 vulnerabilities.
- `composer audit --locked --format=json`: 4 advisory entries của Laravel, tương ứng 3 vấn đề riêng; advisory email có hai nguồn trùng nhau. Không ignore advisory và không sửa vendor để che kết quả.
- [Debug XSS GHSA-jh5r-qr3c-85q8](https://github.com/laravel/framework/security/advisories/GHSA-jh5r-qr3c-85q8): mitigated khi debug=false, dependency chưa patched.
- [Email CRLF GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq): application validation đã chặn control characters ở cả hai auth endpoints. Project hiện chưa triển khai gửi email nghiệp vụ tới khách hàng.
- [Temporary signed URL GHSA-crmm-hgp2-wgrp](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp): không thấy sử dụng temporary filesystem signed URLs hoặc routes phục vụ chúng trong project hiện tại; chưa coi dependency đã sửa.
- [Laravel 10 kết thúc security support 04/02/2025](https://laravel.com/docs/10.x/releases#support-policy), [PHP 8.1 kết thúc support 31/12/2025](https://www.php.net/eol.php). Cần nâng PHP/Open Server runtime và Laravel sang các phiên bản còn được hỗ trợ, có bản vá tương ứng, trước production. Không tự thay runtime toàn Open Server hoặc nâng major framework trong đợt audit này.
- Production DocumentRoot phải trỏ `public/`, HTTPS, APP_ENV=production, APP_URL đúng hostname. Apache guard đã kiểm tra trên local; chưa kiểm tra hạ tầng production, reverse proxy, Nginx hoặc hệ thống log bên ngoài.
- Raw SQL/bulk Eloquent updates có thể bỏ qua model events bảo vệ lịch sử. Không có endpoint hiện tại cho người dùng thực hiện các thao tác đó; DB maintenance vẫn phải kiểm soát quyền riêng. Facebook username/numeric ID alias vẫn là giới hạn đã nêu trong SPEC, không tự đổi rule nhận diện.

## Validation

- 24 regression cases mới trong `tests/Feature/SecurityAuditTest.php`, bao gồm dữ liệu API/status, CSRF thật, owner scope, immutable approved qua HTTP, log redaction, rate limit, production settings, debug endpoints và FK snapshot.
- PHPUnit full suite trên MySQL `toyota_testing`: **398 tests, 2.271 assertions — PASS**, gồm 24 regression cases mới (259 assertions) và race test với hai transaction MySQL thực tế.
- `tests/Deployment/apache-security.ps1`: 8 đường dẫn private bị chặn, landing HTTP 200; chạy thực tế trên Open Server.
- Laravel Pint, `git diff --check`, `artisan migrate:status`; xác minh FK hiện có `award_winners_sales_dealer_id_snapshot_foreign` có DELETE_RULE/UPDATE_RULE = NO ACTION (MySQL chặn xóa/cập nhật parent đang được tham chiếu).
- Không thay SPEC.md, không thay điểm/period/status transitions; không xóa dữ liệu development. Không có thay đổi schema cuối cùng: migration FK bổ sung thử nghiệm đã rollback và loại bỏ sau khi xác minh constraint tương ứng đã tồn tại ở 000009.
