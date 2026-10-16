# Final QA Dealer-only — 2026-10-16

**NOT READY** để triển khai production.

Phạm vi: branch `develop_new`, PHP CLI 8.1.9, Laravel installed/locked v10.50.3, MySQL 8.0.30 / REPEATABLE-READ. Đã kiểm tra workspace và database local; chưa có môi trường production riêng để kiểm tra HTTPS, web server, mail delivery hoặc backup ngoài máy.

Không sửa code nghiệp vụ, dependencies hoặc `.env` trong lần QA này. Chỉ tạo công cụ/cấu hình/log QA dưới `.laravel-tools/` được Git ignore và báo cáo này. Không migrate database gốc, không xóa dữ liệu/schema hiện có, không chạy `migrate:fresh` hoặc `db:wipe`. Các schema QA mới được giữ lại để kiểm tra.

## BLOCKERS

1. **Full automated suite chưa đạt.** PHPUnit cấu hình gốc vẫn trỏ `toyota_testing`; 295 cases trong 15 classes dùng `RefreshDatabase`, nhiều cases vẫn mô tả Sales, chuyển Sales giữa đại lý hoặc tạo Sales Awards. Chạy trực tiếp cấu hình đó có thể làm mới database hiện có. Lần QA dùng cấu hình tạm trỏ vào một bản khôi phục mới đã xác minh; các guard cũ từ chối tên database này trước thao tác destructive. Kết quả: 468 tests, 173 pass, 295 setup errors, 0 assertion failures, 0 skipped, 9.842 assertions. Không thể coi 295 cases bị chặn là đã xác minh. Cần chuyển các cases còn phù hợp sang helper khôi phục database mới và cập nhật kỳ vọng Dealer-only; thay thế cases Sales lỗi thời bằng coverage nghiệp vụ tương ứng, rồi chạy lại toàn bộ suite an toàn.

2. **Composer audit không đạt.** Exit 1, bốn advisory records trên `laravel/framework`, tương ứng ba GHSA khác nhau. Hai records là cùng lỗi CRLF từ hai nguồn. Không tự cập nhật dependencies trong QA.

   | Advisory | Nội dung | Mức độ theo nguồn hiện tại |
   | --- | --- | --- |
   | [GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq) / CVE-2026-48019 | CRLF injection trong email validation | High |
   | [GHSA-crmm-hgp2-wgrp](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) | Temporary signed URL path confusion | Moderate |
   | [GHSA-jh5r-qr3c-85q8](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) / CVE-2026-102279 | Debug page XSS | Low |

   Code đã có control-character rejection ở registration/admin account creation/password reset và ép debug=false khi production; evidence dùng endpoint policy thay vì temporary URLs. Đây là giảm phạm vi tiếp xúc, không làm Composer audit trở thành PASS. PHP 8.1 đã EOL ngày 31/12/2025 ([PHP](https://www.php.net/eol.php)); Laravel 10 hết security support ngày 04/02/2025 ([Laravel](https://laravel.com/docs/10.x/releases)). Cần nâng runtime/framework lên phiên bản còn được hỗ trợ và không bị advisories, kiểm tra tương thích và chạy lại QA.

3. **Schema thực tế chưa triển khai Dealer-only.** `artisan migrate:status` exit 0 nhưng migration `2026_10_16_000015_prepare_dealer_only_database` vẫn **Pending**; 18 migrations trước đã Ran. Live schema vẫn là mô hình cũ. Các tests Dealer-only chạy trên bản khôi phục mới được migrate, không chứng minh database gốc đã chuyển đổi. Không phục vụ release mới trên live schema cũ.

4. **Chưa xác minh production deployment configuration.** Cấu hình local hiện tại: APP_ENV=local, APP_DEBUG=false, URL scheme=http, session secure=null, SMTP mailpit, DB user=root, chưa config cache và chưa cấu hình backup manifest cho migration. Không đưa nguyên cấu hình này lên production. Mô phỏng APP_ENV=production với APP_DEBUG=true xác nhận code vẫn ép debug=false và secure cookie=true; mô phỏng không thay thế kiểm tra HTTPS/mail/web server thực tế.

## WARNINGS

- **500/501 submissions chưa có historical dealer_id**, gồm 300 approved. Vẫn được bảo toàn; không tính vào Dealer leaderboard/awards cho đến khi có bằng chứng mapping lịch sử được duyệt. Không suy diễn từ Dealer hiện tại của Sales. Cần xác nhận nghiệp vụ về số điểm lịch sử đang được loại trừ trước go-live.
- `DevelopmentDemoSeeder` vẫn là công cụ tạo tài khoản/dữ liệu theo Sales, không tương thích một tài khoản/Dealer; không chạy nó trong triển khai. Nó đã chặn production, nhưng cần retire hoặc refactor riêng. Các tests cũ và phần mô tả trong `SPEC.md` vẫn còn mô hình Sales.
- Migration forward-only, MySQL DDL có implicit commit. Backup script hiện giới hạn source tên `toyota`; môi trường tên khác cần quy trình backup đã review, không bỏ guard để chạy cho được.
- Múi giờ ứng dụng hiện là **UTC**. Không tự đổi sang Asia/Ho_Chi_Minh khi triển khai vì có thể đổi ranh giới kỳ của dữ liệu đã lưu. Cần thống nhất múi giờ nghiệp vụ và kế hoạch chuyển đổi nếu có yêu cầu đổi.
- Composer development build cũ hơn 60 ngày; nên cập nhật công cụ ở công việc khắc phục riêng. npm audit không phát hiện advisory ở thời điểm QA; kết quả này không thay thế đánh giá source hoặc kiểm tra runtime production.
- Chưa xác minh mail reset password được giao thực tế, TLS/proxy, web root chỉ trỏ `public/`, filesystem permissions, scheduled jobs hoặc quy trình khôi phục production ngoài máy local.

## TEST RESULTS

| Hạng mục | Kết quả | Bằng chứng / giới hạn |
| --- | --- | --- |
| Full PHPUnit | **NOT PASS** | 468 tests; 173 pass; 295 guard errors; 9.842 assertions; exit 2; 94,738 giây |
| Dealer-only regression trong full run | **PASS** | Toàn bộ 172 cases đã refactor; thêm 1 Feature Example pass. Không phải toàn bộ legacy suite đã đạt |
| Composer audit --locked --format=json | **FAIL** | 4 advisory records / 3 GHSA trên Laravel; exit 1; đã thử lại ngoài sandbox sau lỗi kết nối Packagist |
| npm audit --json | **PASS** | 0 vulnerabilities; exit 0 |
| npm run build | **PASS** | Vite 6.4.3; 58 modules; assets và manifest production đã tạo; exit 0 |
| artisan migrate:status | **PENDING** | 18 Ran, Dealer-only migration Pending; lệnh exit 0 |
| Source data/evidence/trigger checksum | **PASS** | 10 bảng và toàn bộ 501 evidence files khớp verified backup, cả sau full tests |
| git diff --check | **PASS** | Không phát hiện whitespace errors |

Full test command dùng tất cả Unit/Feature directories của `phpunit.xml`, chỉ thay bootstrap/directory thành đường dẫn tuyệt đối và database mặc định thành bản khôi phục mới `toyota_final_qa_9dad238c5bb0d658`. Cấp `DEALER_INTEGRITY_MANIFEST` để các tests mới tự verify/restore/migrate schema riêng. Không loại trừ tests để làm xanh kết quả.

Artifacts private: `.laravel-tools/final-qa-20261016_132243/phpunit.xml`, `full-tests.xml`, `full-tests.log`. Tất cả 295 errors là `LogicException` từ `beforeRefreshingDatabase`; 0 assertions của các cases đó đã chạy.

### Kiểm tra theo yêu cầu

| Yêu cầu | Kết luận trên code / bản khôi phục đã migrate |
| --- | --- |
| Không còn Sales workflow hiện hành | Web/API không có Sales dashboard/management/submission routes hoặc Sales ranking mới. Public landing không có Sales sections. Sales chỉ còn trong audit/historical award details; dev seeder/tests/spec còn dư như cảnh báo |
| Historical submissions | 501 rows và mọi giá trị gốc khớp backup; migration tests kiểm tra preservation, archived FK và không chuyển Sales thành Dealer |
| Evidence | 501/501 files khớp SHA-256; paths trong submission rows nguyên vẹn; private endpoint có policy |
| Published awards | 4 published awards, 24 winners (12 Sales) nguyên vẹn; tests kiểm tra snapshot bất biến ở model và raw SQL |
| Dealer isolation | Tests chặn chéo Dealer ở list/show/edit/update/delete/evidence; dealer_id lấy từ auth, không từ frontend; NULL snapshot không cấp quyền |
| Account uniqueness | Backend lock + generated UNIQUE key; kiểm tra mọi status và hai tiến trình tạo account đồng thời |
| Email immutable | FormRequest, model và DB trigger; không cho Dealer/Admin sửa email qua account/profile endpoints |
| Authentication / authorization | 2 login roles; legacy password/session/remember/reset/token bị chặn; active status, CSRF, throttles và policy được kiểm tra trong suite mới |
| Duplicate submission protection | Pending duplicates được phép theo rule cũ; cùng normalized profile chỉ được approved một lần toàn hệ thống. Source có 0 approved duplicate groups; race test cho đúng 1 approval và 1 review |
| Leaderboard | Approved-only; GROUP BY snapshot dealer_id; submitted_at; [start,end); week/month/all; tie score DESC, MAX(submitted_at) ASC, dealer ID ASC; không join current Sales/Dealer account để suy ownership |
| Awards | Dealer-only Top 3; admin preview/session fingerprint; ended period; không còn pending trong kỳ; atomic immutable snapshots; stale preview và duplicate/concurrent publish bị chặn |
| Migration integrity | 3 integrity tests pass: preservation/constraints, token preflight, missing/stale backup refusal. Chưa apply trên source; cần rehearsal actual deploy command trên staging |
| Production safeguards | Debug false/secure cookie true trong production simulation; actual production infrastructure chưa được kiểm tra |

Baseline source sau QA: 87 dealers, 52 users, 501 submissions, 397 moderation reviews, 4 awards, 24 winners, 18 migration records. Hash toàn bộ 10 bảng, evidence và trigger definitions khớp manifest đã xác minh. Không đăng/approve/reject/sửa/xóa dữ liệu nghiệp vụ trên source trong QA.

## DEPLOYMENT STEPS

1. Khắc phục blockers trước release: chuyển full tests sang cơ chế database clone an toàn, cập nhật kỳ vọng Dealer-only; nâng PHP/framework/dependencies; chạy lại toàn bộ tests, Composer audit và frontend audit/build. Retire dev Sales seeder khỏi quy trình triển khai.
2. Rehearse release trên staging cô lập từ backup production đã restore và verify, bao gồm thực thi migration bằng Artisan và smoke tests qua HTTP. Giữ cùng tên source/schema hợp lệ với manifest hoặc chuẩn bị cơ chế backup được review; không bypass preflight. Kiểm tra counts/hashes của submissions/reviews/awards/evidence và archive chính xác trước/sau.
3. Chuẩn bị production: APP_ENV=production, APP_DEBUG=false, APP_URL HTTPS đúng host, secure cookies, trusted proxies đúng hạ tầng, SMTP thật, DB account ứng dụng ít quyền, private evidence storage. Giữ APP_KEY hiện có; không đổi timezone tùy tiện. Đảm bảo web root là `public/`, không công khai `.env`, backups hoặc private evidence.
4. Vào maintenance, dừng workers/scheduler và mọi writers. Tạo backup DB + evidence mới, restore thử và verify; đặt đường dẫn tuyệt đối `DEALER_MIGRATION_BACKUP_MANIFEST`. Cập nhật/clear config cache để preflight đọc đúng manifest, không dùng cấu hình cũ.
5. Deploy code/dependencies/build artifacts đã kiểm thử. Chạy `php artisan migrate --force` đúng database trong cửa sổ maintenance, **không** `migrate:fresh`, `db:wipe`, seed Sales hoặc tự động chuyển Sales accounts thành Dealer. Kiểm tra `migrate:status` không còn Pending.
6. Đối chiếu mọi submission/evidence/review/published award với backup; kiểm tra archived Sales IDs/FKs và NULL dealer_id vẫn nguyên. Admin tạo/kích hoạt từng Dealer account theo identity đã xác minh; uniqueness tính cả account bị khóa/chờ duyệt.
7. Cache config/routes/views sau cấu hình cuối. Smoke test admin/dealer login, chặn Sales, account lock, reset password email thật, cross-Dealer IDOR, tạo/sửa pending/evidence, approve duplicate, leaderboard boundaries và award preview/publish guards. Chỉ mở writes khi kiểm tra đạt; restart workers theo quy trình vận hành.
8. Nếu DDL lỗi, giữ maintenance và restore verified backup theo runbook; không chạy rollback phá dữ liệu hoặc retry mù migration đã chạy một phần. Sau khi đã nhận writes mới, recovery phải có kế hoạch reconciliation riêng.

Chưa thực hiện deployment hoặc tự sửa các blockers. Dừng sau QA.
