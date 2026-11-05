# Cleanup demo và chuẩn bị nhận dữ liệu thật

## Mục tiêu
Audit ownership/FK/storage; xây command dry-run mặc định, backup/confirm/transaction; kiểm thử trên toyota_testing cách ly. Không cleanup thật local business DB hoặc production, không đổi Dealer/dữ liệu thật, không vô hiệu hóa security.

## Trạng thái
IN PROGRESS — 2026-11-05, tạo trước khi sửa source. Production chưa được truy cập; số liệu production và quyết định thực thi phải chờ operator dry-run và phê duyệt riêng.

Cập nhật cuối 2026-11-05: **DONE trong phạm vi audit source, xây command và kiểm thử local/testing cách ly được phép**. Cleanup production **CHƯA THỰC HIỆN**, chưa xác nhận khắc phục HTTP500 tại production. Ownership production hiện báo không hợp lệ vẫn là blocker phải operator review; không có quyền tự bypass hoặc execute.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, TOYOTA_REALISTIC_DEMO.md, service/commands/config/tests, migrations và models liên quan. Git status có4 tài liệu QA untracked từ task trước, giữ nguyên. Preflight MySQL toyota_testing PASS15 tables, config cache absent. Không migrate/seed/reset database thật.

## Các file source code liên quan
ToyotaRealisticDemo, DemoSubmissionScope, LeaderboardService, các dashboard/home controllers; migrations users/submissions/reviews/awards/edits/receipts/verification/tokens; config realistic_demo; sẽ thêm cleanup service/backup/command và tests riêng.

## Những thay đổi đã thực hiện
Ghi chép ban đầu: chỉ audit và tạo tracking. Các cập nhật triển khai/kiểm thử dưới đây chỉ trên source/local/testing; không kết nối production hoặc cleanup DB business local.

## Nguyên nhân HTTP500 và contract ownership

- LandingPageController gọi LeaderboardService; approvedSubmissions dùng DemoSubmissionScope. Khi include_in_metrics=false, scope vẫn gọi ToyotaRealisticDemo::ownedSubmissionIds, không phụ thuộc việc seeder đã bị tắt. Vì vậy TOYOTA_DEMO_ENABLED=false không loại bỏ lỗi ownership trên trang chủ/dashboard.
- ownedSubmissionIds tìm admin namespace cố định, đọc manifest trong admin_note. Nó yêu cầu list ID nguyên hợp lệ và kiểm tra encrypted ownership envelope khớp dataset/database/admin/submission IDs; manifest legacy không có envelope chỉ được dùng cho metrics khi fingerprint toàn bộ dataset còn khớp. Lỗi decrypt, đổi APP_KEY, đổi database binding, ID bị chuyển thành chuỗi/trùng hoặc legacy fingerprint drift đều có thể dẫn đến exception đã báo. Không có evidence production ở workspace để kết luận trường hợp cụ thể nào đang xảy ra.
- Fingerprint hiện dùng SELECT* của users/submissions/reviews. Migration thêm cột, thay data/duyệt/đổi tên đều có thể làm fingerprint thay đổi; migration000023 thêm odo. Migration000024 tạo bảng mới có FK tới hồ sơ/users. Migrations đã đủ không đồng nghĩa manifest/fingerprint cũ tự hợp lệ.
- config/app.php hiện không map APP_PREVIOUS_KEYS; framework có hỗ trợ previous_keys nhưng chỉ đặt biến môi trường đó không tự kích hoạt trên source này. Không đổi APP_KEY hoặc tự re-sign/rebuild manifest để ép cleanup. Đổi key ngược có thể làm hỏng encrypted receipt/draft đã tạo bằng key mới.
- Proof xác thực dùng để **lọc metrics** không đồng nghĩa permission xóa. Command mới đòi envelope authenticated, các list ID đầy đủ/không trùng, fingerprint hiện tại khớp, các identity namespace/tag nguyên vẹn và không có lịch sử ngoài manifest. Số1500 là cấu trúc protocol v1, không phải predicate tìm/xóa record; không query DELETE theo số lượng, customer/Dealer name, tag hoặc prefix.

## Bảng, FK, history và file storage

| Bảng/nguồn | Quan hệ và cách xử lý |
|---|---|
| dealers | Chỉ đọc/lock/hash toàn bộ row, không update/delete. Giữ ID/code/name/address/province/contact/slug/status/photo/source config và mọi field khác |
| users | Chỉ ID có trong manifest đã xác minh. Admin thật/manager/Sales ngoài manifest giữ nguyên. Nếu không còn active Admin ngoài manifest thì block để tránh mất quyền quản trị |
| customer_submissions | sales_id/reviewed_by→users, dealer_id snapshot→Dealer. Chỉ xóa list ID đã chứng minh; giữ toàn bộ non-demo submissions và attribution |
| moderation_reviews | FK submission/Sales/reviewer, append-only. Chỉ list review ID trong proof, đúng subject/reviewer; review ngoài manifest là blocker, không tự xóa bổ sung |
| awards/award_winners | FK publisher/Sales/Dealer; published snapshots bất biến. Realistic v1 không tạo awards hoặc ownership list cho chúng. Không tự xóa winner/award theo tên [DEMO] hoặc suy từ điểm; tham chiếu tới demo user hay legacy demo marker đều yêu cầu review |
| submission_edits | FK customer_submission_id có SET NULL, edited_by restrict và submission_id_snapshot không FK. Kiểm tra cả FK lẫn soft snapshot; block, không để deletion âm thầm null history |
| dealer_registration_receipts (000023) | FK submission restrict; receipt chứa encrypted confirmation. Ngoài manifest v1 nên block nếu liên quan, không tự xóa |
| submission_verification_states/audits (000024) | FK submission và Admin actor, history/evidence phát sinh. Block nếu liên quan demo; không suy actor/row là demo chỉ vì đang tham chiếu demo |
| personal_access_tokens/sessions/password_reset_tokens | Có soft/morph refs hoặc email, không đủ chỉ xem FK. Block các phụ thuộc liên quan, không xóa token/session/reset thật |
| jobs/failed_jobs | Serialized payload không chứng minh được ownership bằng text/ID. Nếu có rows khi cleanup dataset thì block để operator review, không tự prune queue |
| migrations/mutex/các bảng khác | Không xóa, giữ toàn bộ snapshots. Không reset auto-increment hoặc schema. Không có bảng điểm cần reset: điểm vẫn COUNT approved theo submitted_at và tie-break hiện hữu |

Incoming foreign keys được đọc từ information_schema, kể cả bảng mới và SET NULL; dependency ngoài ba tập users/submissions/reviews luôn block. Cross-schema reference yêu cầu review. Operator cần account đủ visibility toàn schema và incoming refs; không chạy bằng account che metadata hoặc qua read replica. Command từ chối read/write split, database thực khác cấu hình và bảng không InnoDB. Không hứa tự suy luận mọi soft reference do custom code/schema ngoài bản đã audit.

Các field fingerprint cũ bỏ qua cũng được xét riêng: remember_token hoặc admin_note của demo account ngoài chủ manifest là dấu hiệu hoạt động/operator review; không coi fingerprint khớp là đã đủ. Upload avatar/evidence có reference cũng block. Seeder realistic v1 vốn không tạo ảnh; DevelopmentDemoSeeder cũ tạo evidence và awards nhưng không có proof tương đương, nên chỉ báo candidate và giữ lại.

Không xóa bất kỳ upload/storage/session file nào. File không có provenance hoặc orphan vẫn được giữ; không dùng tên file để suy sở hữu. Ngoại lệ duy nhất là client.cnf **tạm mới do chính backup helper tạo bằng exclusive-create trong thư mục private mới**, phải xóa sau dump/lỗi để không lưu credential. Dump/receipt không tự xóa.

## Dry-run local mới — không phải số liệu production

Database business local hiện tên `toyota`, không phải production được mô tả trong yêu cầu. Không có demo candidates theo các marker được rà soát; không có manifest xác thực. Không được kết luận mọi record bên ngoài manifest là “thật”.

| Bảng | Tổng | Demo được xác minh | Giữ nguyên/chưa phân loại |
|---|---:|---:|---:|
| dealers | 2 | 0 | 2 |
| users | 3 | 0 | 3 |
| customer_submissions | 7 | 0 | 7 |
| moderation_reviews | 4 | 0 | 4 |
| dealer_registration_receipts | 1 | 0 | 1 |
| awards / award_winners | 0 / 0 | 0 / 0 | 0 / 0 |
| submission_edits / personal_access_tokens / password_reset_tokens / failed_jobs | 0 mỗi bảng | 0 | 0 |
| migrations / submission_intake_mutex | 27 / 1 | 0 | 27 / 1 |
| submission_verification_states/audits | Chưa có ở local business DB | Chưa kiểm chứng | Không sửa schema local |

Dry-run local trả exit1 do thiếu hai bảng verification. Người dùng cho biết **production đã có000024**; không áp dụng lỗi schema local này sang production. toyota_testing đã có cả000023/000024,15 tables và migrations28; không cần chạy migration trong task.

Đã so sánh SHA256 của mọi bảng business local trước/sau toàn bộ kiểm thử: **không đổi**. Không DELETE/UPDATE hoặc migrate DB toyota. Output đầy đủ private ở `.laravel-tools/demo-cleanup-dry-run-local*.json`.

**Production:** số lượng demo/thật/phụ thuộc/file chưa kiểm chứng vì không có quyền truy cập/backup/dry-run production trong task. Phải nhận output operator trước khi phê duyệt execute. Với ownership=false, dependencies=[] nghĩa chưa xác định được tập root để kiểm tra phụ thuộc, không phải chứng nhận không có phụ thuộc.

## Source PHP đã thêm/chỉnh

- Mới `app/Console/Commands/CleanupToyotaDemo.php`: default dry-run, modes tách biệt, confirmation/plan/backup/restore acknowledgement; production thêm allow-production + maintenance.
- Mới `app/Services/DemoCleanup.php`: audit, hash toàn bộ row/bảng, FK/soft refs, plan binding, range locks, delete đúng ID và kiểm tra dữ liệu giữ lại trước commit.
- Mới `app/Services/DemoCleanupBackup.php`: mysqldump đầy đủ, private credentials tạm, private dump/checksum, encrypted/authenticated receipt gắn database+plan, kiểm tra tamper/stale trước xóa.
- Mới `app/Services/DemoDataGuard.php`: candidate alarm chỉ cho audit, không dùng làm bằng chứng xóa; chế độ retired xác thực biên nhận cleanup private gắn database, không đọc/decrypt demo ownership và từ chối cấu hình bật lại demo hoặc Admin demo xuất hiện lại.
- `ToyotaRealisticDemo.php`: strict read-only cleanup proof; retired mode khóa seed/legacy cleanup. Không bỏ guard cũ hoặc sửa thuật toán fingerprint để hợp thức hóa dữ liệu đã thay đổi.
- `DemoSubmissionScope.php`: nhánh retired có guard; luồng metrics cũ giữ nguyên khi retired=false. Không catch rồi tính tất cả data chưa xác minh.
- `PublicAwardResults.php`, `DealerDashboardData.php`: guard retired trên public/private award reads; giữ nguyên publication gates/projection/snapshot queries khi không retired. Không expose draft/Sales/customer data.
- `config/realistic_demo.php`: thêm TOYOTA_DEMO_RETIRED=false mặc định.
- Mới `tests/Feature/DemoCleanupTest.php`: fixtures/proofs/transport giả chỉ trên database test, không đưa demo mới vào production.

Không thay LeaderboardService formula/bounds/tie-break, controllers, routes HTTP, auth/privacy/verification/uniqueness guards, Dealer, migrations/schema, Blade/CSS/production assets hoặc DatabaseSeeder. Seeder/fixtures cũ vẫn phục vụ testing, không gọi từ production startup; retirement chặn opt-in demo cũ.

## Hành vi command và transaction

```bash
php artisan toyota:cleanup-demo
php artisan toyota:cleanup-demo --dry-run
```

Hai lệnh trên chỉ SELECT, báo counts/candidates/blockers và plan_hash, không in customer rows/password/key/admin_note/envelope/ID list. `verified_demo` là proof thành công; `retained_unclassified` không đồng nghĩa certified-real. Các marker chỉ là alarm, false positive vẫn được giữ để review.

`--backup` chỉ khi audit eligible: tạo private SQL toàn database và receipt.enc. Trước/sau dump phải cùng plan hash. Password không nằm trong command arguments/log; backup account cần đủ quyền đọc schema/routines/triggers/events và visibility để không thiếu dữ liệu. Không tự cấp privilege.

`--execute` đòi đồng thời plan, receipt, đúng SHA256 đã được operator xác nhận restore và `--confirm=cleanup:<database>`. Không có --force bỏ ownership. Production còn đòi `--allow-production` và maintenance mode. Approval nghiệp vụ vẫn là ticket riêng; có đủ flags không thay thế phê duyệt của chủ dữ liệu.

Trong transaction: khóa mutex → range-lock các bảng → audit lại → kiểm tra plan/receipt/hash → snapshot tất cả non-owned rows → delete moderation_reviews → customer_submissions → users → hash lại toàn bộ retained data/Dealer. Sai count, FK, metadata, backup hoặc hash đều rollback. Chỉ repeatable-read/serializable, không tự hạ isolation. Snapshot/predicate locks và maintenance/stop writers bảo vệ interval; metadata/schema không được thay trong cửa sổ này. Không có DELETE Dealer/awards/verification/uploads. Không reset ID counters.

Nếu crypto/fingerprint/dataset sai, không có bước tự “repair” manifest. Giữ fail-closed, backup thủ công để review. Việc metrics vẫn bị chặn trước cleanup/giải quyết proof không được báo thành đã sửa HTTP500 production.

### Bổ sung 2026-11-05: retirement receipt và dữ liệu nhập mới
Sau SQL commit thành công, service audit lại: không còn tập owned, không còn candidate và không có blocker mới, rồi ghi biên nhận mã hóa vào `storage/app/private/demo-retirement/<database-binding>.enc`. Receipt chứa binding/plan đã hoàn tất, không chứa PII. Thư mục/file private0700/0600; file tạm exclusive-create rồi rename. Runtime retired phải có receipt xác thực đúng binding và không có Admin demo gốc tái xuất hiện; bật biến retired một mình không bỏ qua proof.

Điều chỉnh guard ban đầu trong task: **không scan marker do người dùng nhập ở runtime**. Nếu dùng marker Facebook/biển số/email để làm lỗi toàn site sau retirement, một đăng ký mới có thể gây denial of service. Sau biên nhận cleanup, dữ liệu nhập mới đi qua approval/privacy/uniqueness và cách tính điểm bình thường; chuỗi giống demo không phải provenance. Candidate scan vẫn bảo thủ trong CLI audit: input như vậy sẽ yêu cầu review nếu muốn chạy cleanup lần nữa, không xóa nó. Seed cũ bị chặn bởi retired flag; DBA không được import lại dump demo sau retirement hoặc tái sử dụng receipt cho database/key khác.

Receipt xác nhận chuyển trạng thái tại thời điểm cleanup, không phải cơ chế phát hiện mọi import thủ công về sau. Bảo vệ private storage như secret, không commit/đưa vào public disk; khi restore một backup trước cleanup phải vô hiệu hóa receipt bằng quy trình rollback và giữ retired=false. Khi chuyển host/database/key phải có kế hoạch xác thực lại riêng. Môi trường mới không có demo cũng phải dry-run/backup/restore-ack/execute no-op hợp lệ để có receipt, không tự tạo nó bằng tay.

Nếu SQL đã commit nhưng audit/ghi receipt lỗi: giữ maintenance, kiểm tra counts/hash và outcome, không tuyên bố toàn bộ SQL đã rollback. Sau xử lý nguyên nhân, audit/backup/restore rồi execute no-op tạo lại receipt. Không retry mù khi đã mở traffic hoặc đã xuất hiện candidate mới.

## Runbook production — chỉ operator sau phê duyệt riêng

### 1. Audit và backup phục vụ review, chưa execute
1. Xác nhận host/database/APP_ENV, release commit, schema000023/000024, InnoDB và đủ quyền metadata. Giữ danh sách/hash Dealer và active Admin thật ngoài manifest. Không dùng tên Dealer/khách hoặc số1500 để gắn nhãn demo.
2. Lấy backup DB và storage hiện tại bằng công cụ DBA được duyệt, giữ APP_KEY và cấu hình tương ứng trong secret vault riêng. Backup chứa PII, phải private/encrypted theo policy, không commit/upload public.
3. Khi ownership hiện đang sai, tạo backup DBA thủ công trước khi review; command --backup tự động sẽ từ chối audit blocked. Ví dụ trên máy operator đã cấu hình options file private (không đưa password lên argv):

```bash
umask 077
mysqldump --defaults-extra-file=/secure/backup-client.cnf --single-transaction --quick --skip-lock-tables --routines --triggers --events --hex-blob --no-tablespaces ACTUAL_DATABASE > /secure/backups/toyota-before-review.sql
sha256sum /secure/backups/toyota-before-review.sql
php artisan toyota:cleanup-demo --dry-run
```

4. Nếu blocked: giữ maintenance phù hợp sự cố, dừng tại đây, đối chiếu manifest/authenticated envelope với backup và nguồn key/database cũ trong môi trường cách ly. Không sửa manifest/fingerprint, bỏ envelope, xóa admin demo trước, xóa theo prefix/tag hoặc bật include_in_metrics để làm website lên bằng data chưa xác minh. Khôi phục/reconcile proof cần phương án và phê duyệt riêng; command hiện không nhận danh sách ID tự nhập hay --force.
5. Legacy DevelopmentDemo/admin do cấu hình tùy ý và award snapshots không có ownership v1 phải phân loại riêng, không được quy tất cả account example.* hoặc award [DEMO] là được xóa. Có thể xem IDs từ `app(DemoCleanup::class)->audit()['ids']` trong console private để operator đối chiếu từng account; không đưa key/admin_note/customer data vào ticket/chat.

### 2. Chốt kế hoạch và kiểm tra restore
6. Sau khi proof hợp lệ và kế hoạch được duyệt, bật maintenance bằng quy trình deploy, dừng queue/scheduler/import/cron và mọi writer khác. Không thay schema/config giữa final dry-run và execute; đảm bảo còn active Admin ngoài manifest. Không bật seeder opt-in.
7. Final dry-run lấy PLAN_HASH, rồi tạo backup chính thức:

```bash
php artisan toyota:cleanup-demo --dry-run
php artisan toyota:cleanup-demo --backup
```

8. Ghi lại receipt path, dump SHA256, plan hash, DB binding và commit. Xác nhận storage private không được web server phục vụ; Linux permissions0700/0600, Windows cần kiểm tra NTFS ACL riêng. Client.cnf đã bị xóa; không xóa SQL/receipt.
9. Copy dump vào host restore cách ly **không có credentials/connectivity production**. Chuẩn bị database clone rỗng được duyệt; dump không dùng --databases nhưng vẫn phải review USE/CREATE DATABASE/DEFINER/qualified SQL trước khi import, đặc biệt nếu có routines. Không import vào DB có dữ liệu thật.

```bash
sha256sum /secure/restore/database.sql
mysql --defaults-extra-file=/secure/restore-isolated-client.cnf --database=ISOLATED_RESTORE_DATABASE < /secure/restore/database.sql
```

10. Trên clone, kiểm tra schema/FK/row counts/Dealer/Admin và app bằng cấu hình/key cách ly đúng nguồn. Ghi bằng chứng restore. `--restore-verified` là acknowledgement của operator với hash chính xác, **không phải chứng nhận máy đã tự restore**. Không đưa SQL/receipt giả của unit tests vào quy trình này.

### 3. Execute chỉ sau khi phê duyệt và restore PASS

```bash
php artisan toyota:cleanup-demo --execute --allow-production --plan=PLAN_HASH --receipt=/PRIVATE/PATH/receipt.enc --restore-verified=DUMP_SHA256 --confirm=cleanup:ACTUAL_DATABASE
```

11. Nếu stale plan/backup/dependencies: dừng, dry-run + backup/restore lại; không bỏ guard. Nếu thành công, lưu stdout kết quả và post-check counts/hashes trong log private. HTTP vẫn maintenance trong lúc hậu kiểm.
12. Sau verified cleanup + operator review, đặt cấu hình real-only retirement:

```dotenv
TOYOTA_DEMO_ENABLED=false
TOYOTA_DEMO_ALLOW_PRODUCTION=false
TOYOTA_DEMO_INCLUDE_IN_METRICS=false
TOYOTA_DEMO_RETIRED=true
```

13. Giữ file retirement_receipt do command trả về trong private storage của đúng deployment/database, cập nhật config cache/restart worker theo quy trình deploy, không đổi APP_KEY để chữa envelope. Retired mode không gọi ownedSubmissionIds; nếu receipt thiếu/hỏng/sai binding, Admin demo tái xuất hiện hoặc flag demo=true thì dừng review. Không dùng marker của input mới để gây lỗi toàn site. Mở traffic chỉ sau HTTP200 home/leaderboard/awards/Dealer, đăng ký/lookup/verification/duyệt và privacy PASS. Không cần chạy seed/migrate để reset số liệu.
14. Nếu không có approved real submission thì points0/leaderboard empty. Nếu còn real approved hoặc real published awards thì giữ kết quả thật, không ép về0/trống. Demo manager được xác minh có thể bị xóa nhưng Dealer row và manager thật ngoài manifest giữ nguyên; tạo/provision manager thật qua workflow hiện có nếu cần, không seed manager demo trở lại.

## Rollback
- Trước commit SQL: lỗi giữa chừng rollback toàn transaction; files/user uploads không bị xóa. Kiểm tra fresh dry-run/counts/hash trước operator retry. Nếu mất kết nối gần COMMIT, phải xác minh outcome, không đoán đã rollback.
- Sau commit nhưng trước mở traffic: giữ maintenance/writers stopped, xác minh backup checksum và restore clone đã PASS, khôi phục full dump đúng database/schema theo change ticket; khôi phục app config tương ứng (retired=false nếu rollback về dataset demo), cất riêng retirement receipt vào backup private và vô hiệu hóa receipt đang active. Không rollback migration hoặc đổi Dealer IDs. Không tự bật lại retired với receipt cũ sau restore.
- Nếu đã nhận dữ liệu thật mới sau mở traffic: **không restore full dump đè production**. Backup hiện trạng mới, dựng clone, đối chiếu và lập kế hoạch reconcile giữ dữ liệu mới, phê duyệt riêng.
- Giữ backup SQL/receipt và storage backup theo retention được duyệt. Không tự xóa backup, không DROP/TRUNCATE/wipe hoặc cascade để ép cleanup thành công.

## Quyết định kỹ thuật quan trọng
Không suy ownership từ tên, số lượng1500 hoặc nhãn. Chỉ manifest có bằng chứng nguyên vẹn mới được tự xóa; fingerprint/schema drift hoặc ownership không xác thực thì block. Award/history phát sinh không có ownership trong manifest không được xóa. Không xóa storage files. Tách production-retired metrics bằng biên nhận cleanup xác thực, không bật include_in_metrics để che lỗi.

## Kết quả kiểm thử
### Môi trường và bằng chứng mới
- `php docs/figma/qa/competition-db-check.php`: PASS, actual loopback MySQL toyota_testing,15 tables, migrations000023/000024 có sẵn; users/submissions0 trước test. Test guard kiểm tra actual SELECT DATABASE() trước transaction. Không migrate/seed DB business; fixture seeder chỉ trong transaction của test cách ly và rollback.
- Các lượt trước: PASS241 tests /4.187 assertions rồi242 tests /4.193 assertions (22 classes), trước thay guard retirement receipt. Test riêng bản có receipt: PASS24 tests /165 assertions. Đang chạy lại toàn bộ22 class trên bản cuối; kết quả chính thức ghi cập nhật bên dưới. Không cộng các lần rerun thành coverage.
- **Cập nhật cuối:** toàn bộ22 class trên bản có receipt **PASS244 tests /4.206 assertions**, 0 failures/errors/skipped, PHP8.5.11 / PHPUnit12.5.40 / MySQL toyota_testing. Runtime2m22.872s. JUnit mới `.laravel-tools/demo-cleanup-regression-final.xml`. Pint --dirty --test, git diff --check và artisan help đều PASS trên bản cuối. Dry-run read-only business local chạy lại sau regression:13 bảng,0 hash thay đổi,0 demo verified; eligible=false đúng vì thiếu hai bảng verification. Không sử dụng kết quả lịch sử để tuyên bố lượt này PASS.
- DemoCleanupTest dùng cả fixture protocol lẫn chính ToyotaRealisticDemoSeeder trong testing: source seeder tạo1500 submissions/1230 reviews và manager demo, command mới xóa đúng các tập này, giữ87 Dealer và Admin ngoài manifest. Mixed real-data case giữ nguyên row/points thật; retired flow xác nhận HTTP200/empty0 rồi QR2-step→verification member→approve→Dealer score1. Mock explicitly cấm ownedSubmissionIds sau retirement.
- Negative cases: missing/invalid crypto, unsigned manifest, fingerprint edit, new verification/receipt/edit, ngoài-manifest customer, award snapshot, token, upload, remember login/operator note, stale plan, tampered dump, wrong restore acknowledgement, injected failure sau DELETE submissions, production chưa maintenance/approval, label-only không proof, retired opt-in và mất Admin cuối. Bổ sung receipt rỗng/tampered/sai binding phải từ chối; dữ liệu nhập mới chứa marker và Sales dùng email của Admin demo đã xóa vẫn HTTP200, điểm theo approval thật. Không sửa/nới assertion test cũ.
- Backup transport trong unit/integration được thay bằng SQL synthetic nhỏ để không claim restore database. Crypt receipts, binding/hash/plan, transaction và FK guards là thật. **Luồng mysqldump thật** đã chạy read-only chỉ trên toyota_testing rỗng: export34.077 bytes,15 CREATE TABLE, footer completed, SHA256 `18a1ccc9f60f4a6c39f4be6a356b54b37ba2a6cdb8cf2e49ad1eb25720478cc1`; credential file không còn. Không chạy backup/restore/cleanup production hoặc restore thật vào DB business. Restore end-to-end production vẫn là điều kiện operator phải kiểm chứng.
- Pint và PHP lint các source mới PASS; command help/discovery PASS. Kết quả lịch sử trong TOYOTA_REALISTIC_DEMO.md “feature chưa kiểm chứng” không còn được dùng thay cho test mới, nhưng không xóa lịch sử đó.

Lệnh regression mới (22 test class, bootstrap không migrate:fresh, process-isolation tránh state TrustedHosts của test production lan sang test khác):

```bash
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/demo-cleanup-regression-final.xml tests/Feature/DemoCleanupTest.php tests/Feature/DemoIsolationTest.php tests/Unit/ToyotaRealisticDemoSafetyTest.php tests/Feature/ToyotaRealisticDemoSeederTest.php tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/CompetitionAwardsTest.php tests/Feature/DealerHistoryTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/RegistrationLookupResultPanelTest.php
php vendor/bin/pint --dirty --test
git diff --check
php artisan help toyota:cleanup-demo
```

## Những lỗi còn tồn tại
Production chưa truy cập, chưa có counts/backup/restore evidence; không tuyên bố đã hết HTTP500 trên production. Manifest invalid được thiết kế dừng, không tự biến candidate thành owned. Dữ liệu legacy/award/history/uploads có thể khiến command bị block và cần plan riêng. Account full metadata visibility, maintenance/stop writer và restore test là điều kiện operator, không giả định đã có.

Không chạy mọi browser viewport hoặc deploy production vì không sửa UI. Local business schema thiếu000024 được giữ nguyên; không suy ra production thiếu migration khi user đã xác nhận đầy đủ. Không xóa file storage hoặc force dashboard thành0 khi còn data thật.

## Task hoặc bước tiếp theo
Bàn giao command/source/runbook đã kiểm thử. Operator chạy dry-run production và cung cấp báo cáo đã loại secrets/PII; chỉ sau ownership/dependency review, backup/restore PASS và phê duyệt riêng mới được execute. Agent không thực hiện bước đó trong task này. Không dùng lệnh cleanup legacy để vượt guard mới.

Git status cuối task:5 file PHP tracked được chỉnh (`DealerDashboardData`, `DemoSubmissionScope`, `PublicAwardResults`, `ToyotaRealisticDemo`, config realistic_demo);5 file PHP mới (command,3 services,test);1 Markdown tracking mới. Bốn file QA audit/manifest/plan/result untracked có sẵn được giữ nguyên, không thuộc thay đổi task. Không commit/stage/deploy, không đổi .env/config production, không sửa giao diện/migration/assets.
