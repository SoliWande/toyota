# Reset dữ liệu nghiệp vụ trước go-live, giữ Dealer và tài khoản được duyệt

## Mục tiêu
Command riêng toyota:reset-demo-data theo quyết định khách hàng toàn bộ nghiệp vụ hiện tại là demo; không dùng ownership manifest để cấp quyền reset. Giữ87 Dealer, Dealer account hợp lệ và Admin do operator duyệt. Chỉ xây dựng/kiểm thử cách ly, không execute production/local nghiệp vụ.

## Trạng thái
IN PROGRESS — 2026-11-05. Tracking tạo trước chỉnh source.

Cập nhật cuối 2026-11-05: **DONE phạm vi audit source, command và regression local/testing được phép**. Production reset **CHƯA THỰC HIỆN/CHƯA ĐƯỢC PHÊ DUYỆT**; chưa có dry-run production hoặc danh sách Admin thật do operator duyệt. Không coi dry-run fixture là phê duyệt production.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, tracking cleanup trước, enum/User model, migrations000001–000024, FK, seeders, routes, backup/retirement guard và test isolation. Git sạch đầu task: source cleanup trước đã được lưu vào repository. Quyết định reset mới thay thế yêu cầu ownership cho **command mới**, không thay guard command cleanup cũ. Chưa kết nối production, chưa có danh sách Admin được duyệt hoặc số liệu production xác minh độc lập.

## Các file source code liên quan
UserRole/UserStatus/User, DemoCleanup/Backup/DataGuard, SubmissionIntake, migrations, login/registration/lookup, Dashboard/Leaderboard/PublicAwardResults. Sẽ thêm command/service/backup/reset retirement/test riêng; không đổi migrations, UI hoặc công thức điểm.

## Những thay đổi đã thực hiện
Ban đầu: audit/tracking, chưa có thao tác ghi database nghiệp vụ. Cập nhật triển khai và bằng chứng bên dưới.

### Source đã triển khai
- Mới `app/Console/Commands/ResetToyotaDemoData.php`: auto-discovery command, mặc định dry-run; database chỉ định phải đúng connection hiện tại, không chuyển connection; `--keep-admin` lặp, backup/execute tách biệt, confirm và maintenance production.
- Mới `app/Services/ResetDemoData.php`: bảng/scopes reset rõ ràng, danh sách giữ/xóa, hashes từng bảng và SHOW CREATE TABLE,22 FK ở schema test hiện tại, topology reviewer của users, orphan check, khóa range và đối chiếu retained data trước commit.
- Mới `app/Services/ResetDemoBackup.php`: chỉ kế thừa transport mysqldump từ helper cũ; receipt và audit riêng có purpose reset, binding database và plan. Không gọi/bypass ownership audit của cleanup. Receipt cleanup cũ không hợp lệ cho reset.
- Mới `app/Services/ResetDemoRetirement.php`: receipt private riêng, DB::afterCommit, xác minh hash hậu commit; không ghi receipt khi rollback kể cả transaction ứng dụng bao ngoài.
- `DemoDataGuard`, `DemoSubmissionScope`, `PublicAwardResults`, `DealerDashboardData`, `ToyotaRealisticDemo`: nhận receipt reset đã xác thực; ngừng đọc legacy ownership ở metrics sau reset, chặn cài demo lại dù chưa refresh retired flag. Toàn bộ demo flags phải false. Không đổi query tính điểm/publication/privacy. Command/service/proof/backup của `toyota:cleanup-demo` không bị sửa; assertEnabled chỉ được tăng bảo vệ sau reset.
- Mới `tests/Feature/ResetDemoDataTest.php`: fixture cách ly, backup transport synthetic, receipt test ở đường dẫn riêng theo từng test để không kích hoạt chế độ retired cho test khác.

Không sửa controller, HTTP routes, migrations, schema, Dealer/User business model, CSS/Blade/assets, .env hoặc deploy hooks. Không tự đăng ký command vào deploy/migrate/scheduler/boot.

### Ma trận schema và FK
| Bảng | Hành động | Căn cứ/thứ tự |
|---|---|---|
| submission_verification_audits | DELETE toàn bộ | Child submission/Admin, trước submissions/users |
| submission_verification_states | DELETE toàn bộ | PK submission_id, nhiều FK Admin, trước submissions/users |
| dealer_registration_receipts | DELETE toàn bộ | FK submission restrict, trước submissions |
| submission_edits | DELETE toàn bộ | Có FK SET NULL + soft snapshot; xóa chủ đích trước parent, không để FK tự sửa history |
| moderation_reviews | DELETE toàn bộ | Submission/Sales/reviewer, trước parent |
| award_winners | DELETE toàn bộ | Award/Sales/Dealer, trước awards/users |
| awards | DELETE toàn bộ | Bao gồm draft/published theo quyết định reset mới; trước publisher user |
| customer_submissions | DELETE toàn bộ | Bao gồm sales/QR, mọi status; trước users |
| personal_access_tokens | DELETE theo User morph + ID user bị xóa | Giữ token của Admin/Dealer được giữ; morph không rõ trùng ID thì block |
| sessions (nếu có) | DELETE theo user_id bị xóa | Không xóa session của retained account, không đụng file/Redis session tự động |
| password_reset_tokens/password_resets | DELETE theo email user bị xóa | Giữ token của retained account |
| users | DELETE theo list ID tính từ danh sách giữ | Sales và Admin ngoài allowlist bị xóa. Xóa user leaf trước reviewer; cycle/retained reviewer dependency thì block |
| dealers | Giữ toàn bộ87, hash mọi cột | Không update/delete; không reset IDs |
| migrations/submission_intake_mutex/configuration và bảng ngoài scope | Giữ nguyên | Hash trước/sau; incoming FK liên quan thì kiểm tra retained dependency, không tự thêm bảng vào reset |
| jobs/failed_jobs/job_batches (nếu có) | Không tự xóa | Có rows thì block vì payload/softrefs cần operator review; không tự prune queue |

Đọc metadata incoming/outgoing FK cả schema mới. Bảng tham chiếu nằm ngoài reset mà còn reference dữ liệu sẽ xóa, cross-schema, target/order bất thường hoặc orphan sẵn có đều block. Constraint SQL vẫn bật; không TRUNCATE, ALTER, reset auto-increment hoặc migration. Hash schema bắt thay cột/FK/index kể cả bảng rỗng. Locks/hashes không thay thế maintenance/stop writers và cửa sổ không DDL; DBA cần quyền visibility đầy đủ metadata/schema/incoming refs.

### Quy tắc tài khoản
UserRole có đúng `admin`, `dealer`, `sales`; UserStatus có pending/active/rejected/blocked. Admin phải nằm trong `--keep-admin=ID` operator duyệt, gồm cả Admin inactive nếu được duyệt; phải còn ít nhất một Admin active. Không giữ mọi Admin theo role; cũng không tự xóa/giữ theo email demo hoặc admin_note. ID không tồn tại/non-Admin/list trống bị block.

Dealer account được giữ ở mọi status hợp lệ khi role=dealer, dealer_id trỏ Dealer tồn tại và generated dealer_manager_key đúng invariant unique-manager. Dealer inactive hoặc account blocked không phải bằng chứng demo. Nếu quan hệ/status bất thường, block và giữ nguyên để review. Không buộc87 Dealer đều phải có account, không tạo manager mới.

Giữ nguyên mọi cột của retained users: ID/email/password hash/role/status/dealer_id/reviewed_by/timestamps/admin_note/remember_token/avatar/slug và generated key. Nếu Dealer/Admin giữ lại có reviewed_by là user sẽ xóa, block; không tự SET NULL hoặc sửa reviewer. Admin-note chứa manifest cũ trên một Admin được operator giữ vẫn nguyên vẹn, nhưng receipt reset khiến runtime không dùng nó để xác định metrics.

### Plan/hash, commit và chống reset lần hai
Dry-run chỉ SELECT/SHOW, xuất approved_admin_ids, admin_candidates kèm kept, kept_dealer_ids (đủ87), kept_dealer_accounts với id/dealer_id/status, deleted_user_ids, số giữ/xóa từng bảng, hashes, FK, blockers và plan_hash. Không in email/password/note/customer rows hoặc credential. Operator đối chiếu ID với account thật qua console bảo mật, không suy role là approval.

Execute: mutex → range-lock mọi bảng → audit lại toàn bộ và so plan → xác thực reset backup/restore SHA256 → snapshot các rows giữ → DELETE theo thứ tự FK/topology → kiểm tra count từng bảng → so hash mọi retained row và orphan check → commit. Chỉ cho REPEATABLE-READ/SERIALIZABLE; không tự hạ isolation.

Sau **outermost commit**, callback kiểm tra lại hash hậu commit/87 Dealer/business empty rồi ghi receipt encrypted, atomic rename, private0700/0600 ngoài public root, gắn purpose + database binding + plan + IDs giữ. File nằm `storage/app/private/demo-reset-retirement/<binding>.enc`. Receipt hợp lệ kích hoạt runtime retired cả khi TOYOTA_DEMO_RETIRED chưa refresh; các demo flags=true vẫn bị chặn. Receipt hỏng không fallback sang tính toàn bộ dữ liệu. Data nhập mới không bị nhận nhầm demo qua prefix/marker.

Một khi receipt reset tồn tại, command từ chối reset tiếp: dữ liệu sau go-live không nằm trong phê duyệt reset này. Không xóa receipt để chạy lại. Nếu callback ghi receipt lỗi sau SQL commit, giữ maintenance và kiểm chứng outcome; không tuyên bố SQL rollback. Nếu chưa có receipt và DB đã rỗng, sau review có thể final dry-run/backup/restore rồi execute no-op để hoàn tất receipt; không bỏ backup guard.

## Kế hoạch backup/restore và lệnh production
**Chỉ hướng dẫn cho operator sau phê duyệt riêng. Không lệnh execute/maintenance/restore nào dưới đây đã được chạy trên production trong task.** `ACTUAL_DATABASE`, `APPROVED_ADMIN_ID_1` và các hash/path là placeholder phải thay bằng kết quả xác minh, không dùng ID fixture/local.

### 1. Inventory và duyệt tài khoản, chưa execute
1. Xác minh host/database/release/schema000023/000024, đúng87 Dealer, các tài khoản Dealer; tài khoản DBA đủ metadata/routines/triggers/events và tables InnoDB. Command hỗ trợ duy nhất primary MySQL, từ chối read/write split và actual database khác cấu hình.
2. Chạy inventory không truyền keep-admin để lấy toàn bộ candidate ID (exit1/list chưa duyệt là đúng), lưu output private:

```bash
php artisan toyota:reset-demo-data --database=ACTUAL_DATABASE --dry-run
```

3. Operator đối chiếu từng Admin ID với tài khoản thật, duyệt allowlist bằng ticket. Dealer accounts hợp lệ giữ tự động theo relationship, danh sách ID xuất trong plan. Nếu bất thường hoặc retained reviewer đang trỏ Admin demo sẽ xóa: dừng, yêu cầu quyết định riêng; không sửa DB bằng suy đoán.
4. Đối chiếu quyết định khách hàng rằng **mọi dữ liệu nghiệp vụ hiện tại** đều được phép xóa (khác quyền cleanup manifest). Chưa mở nhận dữ liệu thật trong cửa sổ reset. Lấy backup DBA ban đầu và storage backup, giữ APP_KEY/cấu hình trong secret vault. SQL chứa PII/credentials hash, không commit/public/upload ticket.

### 2. Maintenance, final plan và full backup
5. Theo quy trình deploy bật maintenance, dừng toàn bộ queue/scheduler/cron/import và mọi writer khác. Không thay schema/release/config trong final window. Review pending/failed/batch queues; command không tự xóa. Không seed hoặc migrate để sửa counts.
6. Tắt TOYOTA_DEMO_ENABLED, TOYOTA_DEMO_ALLOW_PRODUCTION, TOYOTA_DEMO_INCLUDE_IN_METRICS; refresh config cache theo quy trình. Không đổi APP_KEY hoặc re-sign ownership cũ. Chưa bật retired=true khi chưa có receipt; site có thể vẫn ownership500 trong maintenance và không được báo đã sửa trước reset.
7. Final dry-run với mọi ID Admin thật đã duyệt (lặp option). Phải exit0/eligible=true/blockers=[]; lưu plan JSON và plan_hash:

```bash
php artisan toyota:reset-demo-data --database=ACTUAL_DATABASE --keep-admin=APPROVED_ADMIN_ID_1 --keep-admin=APPROVED_ADMIN_ID_2 --dry-run
php artisan toyota:reset-demo-data --database=ACTUAL_DATABASE --keep-admin=APPROVED_ADMIN_ID_1 --keep-admin=APPROVED_ADMIN_ID_2 --backup
```

Nếu chỉ giữ một Admin thì bỏ option thứ hai. Receipt backup trả plan_hash phải đúng final dry-run. Backup dùng mysqldump --single-transaction --routines --triggers --events --hex-blob --no-tablespaces, không password argv, options file private mới được xóa trong finally. Dump/receipt không tự xóa, chỉ thư mục private mới UUID. SHA/size và audit trước/sau phải khớp; schema/data đổi thì backup/plan cũ không dùng.

8. Trên Linux xác nhận0700/0600; trên Windows kiểm tra NTFS ACL riêng. Giữ storage/app/private và receipt giữa các release, không nằm symlink web-public, không đưa lên CDN. Backup cả uploads/cache/session cần thiết theo retention và chính sách hiện có; command không xóa file.

### 3. Restore test bắt buộc, chưa execute production
9. Copy dump vào **host restore cách ly không có production credentials/connectivity**. Dùng database clone rỗng được duyệt. Review SQL USE/CREATE DATABASE/DEFINER/routines/qualified references trước import; không chạy vào DB có dữ liệu thật.

```bash
sha256sum /secure/restore/database.sql
mysql --defaults-extra-file=/secure/restore-isolated-client.cnf --database=ISOLATED_RESTORE_DATABASE < /secure/restore/database.sql
```

10. Kiểm tra đủ tables/schema/FK/counts/87 Dealer, hash rows/IDs/password hashes/quan hệ của retained accounts theo plan, và không có orphan. Restore cấu hình/key đúng nguồn trong môi trường cách ly nếu cần đọc encrypted data, không sửa global APP_KEY production. Lưu bằng chứng restore và checksum. `--restore-verified` là operator acknowledgement đúng hash sau bước này, không phải program tự chứng minh đã restore. SQL synthetic của tests không được dùng làm backup thực tế.

### 4. Execute — CHỜ PHÊ DUYỆT, chưa được chạy trong phiên này

```bash
php artisan toyota:reset-demo-data --database=ACTUAL_DATABASE --keep-admin=APPROVED_ADMIN_ID_1 --keep-admin=APPROVED_ADMIN_ID_2 --execute --allow-production --plan=APPROVED_PLAN_SHA256 --receipt=/PRIVATE/RESET/BACKUP/receipt.enc --restore-verified=RESTORED_DUMP_SHA256 --confirm=reset-demo-data:ACTUAL_DATABASE
```

11. Kiểm tra output count deleted, retained_data_unchanged=true,87 Dealer và receipt path. Giữ stdout/dump/receipt/approval private. Nếu stale plan/mismatch/exception, kiểm tra outcome, không force/retry mù. Không TRUNCATE, tắt FK hoặc sửa migration để vượt guard.
12. Hậu kiểm Dealer hash và tài khoản giữ; business tables đều0; home/leaderboard/awards200/empty/points0, lookup not_found; login Admin/Dealer; đăng ký mới→verification→approve→điểm1 trên fixture/chạy nghiệm thu được khách hàng cho phép. Chỉ mở traffic khi đạt. Không đưa fixture test vào production.
13. Đặt TOYOTA_DEMO_RETIRED=true, giữ ba flags demo=false, refresh cache/restart workers theo quy trình rồi mở traffic. Receipt reset vẫn là bằng chứng; không tự tạo/chỉnh sửa hoặc copy sang binding khác. Không tự flush file/Redis sessions/cache/uploads. Operator review các draft/session/cache cũ trong runbook ứng dụng để không mở lại dữ liệu draft trước go-live; command chỉ xử lý database và token/session rows đã liệt kê. Không dùng APP_KEY rotation để dọn session.

### Rollback
- Lỗi trước SQL commit: DB rollback toàn bộ DELETE; receipt không được ghi; uploads/config/Dealers không thay đổi. Kiểm tra fresh counts/hash trước retry.
- Mất kết nối/callback I/O lỗi gần/sau COMMIT: giữ maintenance, xác minh outcome, không đoán đã rollback. Lỗi ghi receipt không hoàn tác commit SQL.
- Sau commit, chưa mở traffic/nhận dữ liệu thật: checksum + restore clone PASS, khôi phục full dump đúng database/schema theo change ticket. Backup/cất riêng receipt reset khỏi active path, khôi phục cấu hình cũ, không bật retired với receipt cũ. Giữ Dealer IDs và cấu hình/key tương ứng. Không sửa migration.
- Đã nhận dữ liệu thật: không restore full dump đè lên production. Backup hiện trạng mới, dựng clone và reconcile có phê duyệt riêng. Không dùng command reset lần hai.
- Backup/restore procedure độc lập có thể dùng mysqldump DBA khi audit blocked, với private options file và cùng flags trên; không dùng backup receipt cleanup thay cho reset. Không tự xóa backups/uploads/history ngoài phạm vi phê duyệt.

## Quyết định kỹ thuật quan trọng
Admin giữ theo allowlist ID bắt buộc, không theo role đơn thuần; Dealer giữ theo role + dealer_id tồn tại + manager key đúng invariant schema. Account Dealer bất thường phải block để review, không tự xóa. Thiếu87 Dealer/missing schema/unknown dependency đều block. Backup/plan/hash và maintenance bắt buộc theo môi trường; không TRUNCATE/tắt FK/reset sequence.

## Kết quả kiểm thử
Ghi nhận ban đầu chưa chạy bản mới. Kết quả mới ngày2026-11-05:

| Kiểm tra | Kết quả mới | Bằng chứng/giới hạn |
|---|---|---|
| Database isolation preflight | PASS | `php docs/figma/qa/competition-db-check.php`: loopback MySQL toyota_testing,15 bảng, không config cache; guard actual SELECT DATABASE trước fixture transaction |
| Reset tests lượt đầu | PASS25 tests /221 assertions | Trước bổ sung outer-commit/repeat-reset/allowlist reserved-email |
| Fixture bổ sung lượt đầu | FAIL1 error | Fixture mới dùng source=dealer_qr thiếu các trường bắt buộc của submissions_source_check. Không sửa constraint/guard. Đổi fixture bảo vệ dữ liệu mới sang một Sales mới + hồ sơ Sales hợp lệ; luồng QR đầy đủ vẫn được test riêng qua HTTP |
| Full regression sau sửa fixture | PASS315 tests /4.748 assertions,0 errors/failures/skipped |24 class:28 reset tests,244 tests cleanup/metrics/privacy/awards/registration cũ và43 Authentication tests. JUnit `.laravel-tools/reset-demo-regression.xml`. Không cộng các lượt rerun thành coverage |
| Reset suite bản cuối sau che selectors email ở stdout | PASS28 tests /245 assertions | `.laravel-tools/reset-demo-focused.xml`; đã chạy lại cả28 case sau thay đổi chỉ ở output dry-run, không sửa logic DELETE. Bổ sung assertions không xuất internal delete selector/email |
| Default dry-run không ghi | PASS | Query log không INSERT/UPDATE/DELETE/TRUNCATE/ALTER/DROP; hash trước/sau giống, không receipt mới |
|87 Dealer + retained account raw rows | PASS | So hash/raw mọi cột trước/sau, giữ cả Admin/Dealer inactive được phép; generated key và reviewed_by giữ nguyên |
| FK/topology, tokens và reset tables | PASS |8 bảng business đều0, xóa token/password reset của Sales bị xóa, giữ token của retained Admin/Dealer; orphan check[]; không tắt FK |
| Login và app empty | PASS | HTTP login Admin/Dealer; dashboards/home/dealers/leaderboard/awards200; leaderboard empty/points0; lookup result kind=not_found |
| Nhận hồ sơ mới | PASS | HTTP QR2-step→draft→submit→verification member→approve→điểm Dealer1; không dùng dữ liệu demo runtime |
| Production guards/stale/rollback | PASS | Sai database/confirm/allowlist/backup hash/purpose/restore acknowledgement và production chưa maintenance đều từ chối; thay Dealer data sau plan block; injected failure sau DELETE awards rollback mọi bảng; rollback outer application transaction không sinh receipt |
| Legacy integration | PASS | Manifest invalid vẫn bị cleanup cũ từ chối; explicit kept reserved email vẫn raw row không đổi/home200 qua receipt mới; seeder bị chặn dù bật demo flags; corrupt receipt fail-closed |
| Reset lặp sau go-live | PASS | Tạo dữ liệu mới sau reset, command từ chối reset lại và giữ hash mới |
| Pint/Git/command discovery | PASS | Pint explicit10 file, git diff --check, artisan help. Pint --dirty ban đầu không đọc được Git trong sandbox nên dùng explicit paths; Git diff đọc bằng quyền được duyệt, không đổi safe.directory/policy |

**Môi trường:** PHP8.5.11, PHPUnit12.5.40, MySQL, transactions fixture rollback; không migrate:fresh/migrate/seed DB nghiệp vụ. ToyotaDealerSeeder chỉ phục vụ fixture87 Dealer trong transaction testing; không chạy production. Backup transport trong tests là SQL synthetic; Crypt/receipt/plan/hash/FK/delete/transaction là thật. **Chưa chạy mysqldump/restore thực tế trong task này**; không dùng kết quả export ở task cũ để thay cho restore test mới. Operator phải thực hiện restore clone PASS trước execute production.

Lệnh regression đã chạy:

```bash
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/reset-demo-regression.xml tests/Feature/ResetDemoDataTest.php tests/Feature/DemoCleanupTest.php tests/Feature/DemoIsolationTest.php tests/Unit/ToyotaRealisticDemoSafetyTest.php tests/Feature/ToyotaRealisticDemoSeederTest.php tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/AwardWorkflowTest.php tests/Feature/CompetitionAwardsTest.php tests/Feature/DealerHistoryTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/RegistrationBusinessIntegrationTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/AuthenticationTest.php
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result --log-junit .laravel-tools/reset-demo-focused.xml tests/Feature/ResetDemoDataTest.php
php vendor/bin/pint --test app/Services/ResetDemoData.php app/Services/ResetDemoBackup.php app/Services/ResetDemoRetirement.php app/Console/Commands/ResetToyotaDemoData.php tests/Feature/ResetDemoDataTest.php app/Services/DemoDataGuard.php app/Services/DemoSubmissionScope.php app/Services/PublicAwardResults.php app/Services/DealerDashboardData.php app/Services/ToyotaRealisticDemo.php
git diff --check
php artisan help toyota:reset-demo-data
```

### Export ID và kế hoạch đã tạo — phân biệt môi trường
- `.laravel-tools/reset-demo-fixture-plan.json`: dry-run fixture mới **eligible=true**,22 FK, đầy đủ87 kept_dealer_ids, approved_admin_ids, kept_dealer_accounts và deleted_user_ids. Các ID tự tăng chỉ là fixture của lượt chạy, không phải production hoặc allowlist để copy sang production. Last focused fixture: Admin giữ22296/22297; Dealer accounts22299→Dealer26443(active),22300→Dealer26444(blocked); xóa demo Admin22298/Sales22301. Rerun sẽ đổi ID; dùng JSON của chính lượt đó để đối chiếu.
- `.laravel-tools/reset-demo-local-dry-run.json`: read-only business local `toyota`, **eligible=false** đúng thiết kế:2 Dealer thay vì87, thiếu000024 và chưa có approved Admin IDs. Local inventory Admin candidate ID1(active), Dealer account ID2→Dealer1(active); đây chỉ là inventory, không tự xác nhận là Admin thật hoặc danh sách giữ được duyệt. Không dùng nó để reset local.
- `.laravel-tools/reset-business-before.json` và `reset-business-after.json`: **13 bảng local,0 hash thay đổi**; Dealer2/users3/submissions7/reviews4 và mọi row khác được giữ nguyên. Không xóa dữ liệu local đang phục vụ kiểm thử.
- Production: chưa truy cập, chưa export được IDs/counts/hashes thật; operator phải chạy inventory/final dry-run theo runbook. Khách hàng xác nhận87 được dùng làm invariant của command, không phải bằng chứng đã đếm production.
- Artifacts nằm ignored/private; không commit SQL, receipt, credentials hoặc fixture JSON. Không chụp visual QA/build frontend vì task không đổi UI; HTTP assertions không được gọi là screenshot/browser nghiệm thu production.

## Những lỗi còn tồn tại
Chưa có dry-run/allowlist/restore evidence production. Không có quyền execute production trong task này.

Không có test FAIL còn lại trên bản bàn giao. Schema/hash change detection có trong code; không chạy DDL để mô phỏng đổi schema trong fixture transaction (MySQL implicit commit); đã test stale data/allowlist và hash SHOW CREATE trong plan. Optional session/jobs tables không có trong schema testing15 bảng; đã audit nhánh code và metadata nhưng chưa kiểm thử integration với các bảng tùy biến/Redis/file sessions. Có bảng mới/cross-schema/unknown refs cần operator review, không hứa tự xử lý mọi custom softref. Backup restore thật là bước operator bắt buộc, chưa kiểm chứng ở production.

Không tự xóa storage/uploads/session/cache files, không dọn queue hoặc provision Dealer managers. Receipt đánh dấu chuyển trạng thái theo plan đã duyệt, không phát hiện mọi DBA import thủ công về sau; phải giữ private, vô hiệu hóa đúng quy trình khi restore pre-reset backup. Không sửa business formula/privacy/authorization/uniqueness hoặc migrations.

## Task hoặc bước tiếp theo
Đã bàn giao source, test evidence và runbook. Operator export inventory production, duyệt từng Admin ID/Dealer relations, chạy final dry-run đến eligible=true, tạo backup và restore clone PASS; cung cấp plan hash/ID list/checksum và xin phê duyệt execute riêng. Không execute trong phiên Codex này. Git chưa stage/commit/deploy;5 tracked services sửa,5 PHP mới và1 tracking mới; không sửa dữ liệu nghiệp vụ local hoặc cấu hình production.
