# Phase 2C.3 — Final QA & release readiness

Ngày 03/11/2026. **NO-GO hiện tại: chưa đủ điều kiện deploy production.**
Đã hoàn thành kiểm tra local và chuẩn bị runbook, không deploy trong lượt này.
Các lệnh Linux bên dưới là hướng dẫn cho operator sau khi đóng blockers, chưa chạy.

## 1. Bằng chứng và kết quả

Đã đọc analysis-report.md, phase-2b-implementation.md, d04-success-ui-refinement.md,
phase-2c-1-integration.md và phase-2c-2-data-integrity.md; đối chiếu source thực tế.
Những mô tả cũ “chưa có final/ODO/draft”, “thiếu toyota_testing”, heading chờ xác
minh không còn đúng. Phase 2C.2 đã có HTTP concurrency thật; giới hạn browser visual,
demo isolation và status-only reactivation vẫn còn.

Git ban đầu báo dubious ownership; đọc thành công bằng explicit git-dir/work-tree,
không sửa global config/ownership. HEAD nền: `64c3fc5`; working tree có thay đổi
chưa commit từ nhiều phase. Đã xem diff source, status và diff --check. Không dùng
HEAD đơn lẻ làm release artifact vì sẽ thiếu nhiều file mới/untracked.

| Hạng mục | Kết quả | Phạm vi / giới hạn |
| --- | --- | --- |
| PHP regression | PASS | **198 tests / 1.579 assertions**, 48,398 giây: 9 feature files Phase 2A/B/C + toàn bộ tests/Unit |
| HTTP concurrency | PASS | Trong suite trên: 7 cases / 220 assertions, hai HTTP server/DB connections, CSRF thật |
| Admin/Sales/Dealer/leaderboard | PASS trong phạm vi integration | Queue/detail/edit/approve/reject, authorization, Sales transfer, snapshot Dealer và tháng theo submitted_at; không phải toàn bộ legacy feature suite |
| Production build | PASS | Vite 6.4.3, 62 modules; manifest và CSS/JS Dealer tồn tại |
| Pint toàn repo | PASS | `php vendor/bin/pint --test`; đã sửa các lỗi formatting có sẵn, không đổi business logic |
| Git whitespace check | PASS | Có warning CRLF→LF, không có diff --check errors |
| Assets | PASS | 22 PNG không rỗng, dimensions đúng manifest; không URL Figma tạm trong views |
| Render/validation scripts | PASS | 33 render checks, frontend-check.mjs, step-two-validation-check.mjs |
| Production boot/route cache | PASS local | 9 checks bằng release-preflight.php, database driver bị vô hiệu hóa; không dùng database ứng dụng |
| Composer platform requirements | PASS local | PHP 8.5.11 đáp ứng composer.json; vẫn cần bản vá security mới cho production; Composer local có deprecation notices |
| Composer security audit | CHƯA KIỂM CHỨNG | `composer audit --locked --no-dev` lỗi curl 7 khi kết nối Packagist; không phải kết quả “không có lỗ hổng” |
| Browser responsive/visual | CHƯA KIỂM CHỨNG | Chrome headless không hoàn tất about:blank; chưa có screenshot app ở 390/768/1440px |
| Ubuntu/Nginx/TLS/restore | CHƯA KIỂM CHỨNG | Không SSH/deploy, không kiểm tra VM thật hoặc restore backup |
| Schema | PASS về phạm vi source | Chỉ migration Phase 2B 000023 mới; không thêm/sửa/chạy migration trong lượt này |

DB tests chỉ toyota_testing: test guard và SELECT DATABASE() kiểm tra database thực
tế; không migrate:fresh/RefreshDatabase. HTTP workers cần fixtures commit, chỉ dọn
đúng các row fixture mới do test tạo; các tests khác dùng transaction/rollback.
Không đọc hoặc thay đổi dữ liệu local toyota/production. Chưa chạy toàn bộ legacy
feature suite vì các file cũ dùng RefreshDatabase/DatabaseMigrations; không tuyên bố
toàn bộ repository feature tests đã PASS.

## 2. Figma QA

Figma MCP session `3c58e121-e91d-49bb-83fc-ea0fc65dd10c`, file
`yKmWQI6BYJ6dponEnidlsq`. Đã dùng skill figma-design-to-code, đọc design context
và screenshot trả về cho đủ 12 frame, không chỉnh Figma.

| Frame | Desktop / mobile node | Đối chiếu source |
| --- | --- | --- |
| D01/M01 | 209:233 / 99:376 | Hero Montserrat 72/78 desktop, 38/42 mobile; phần lớn UI Inter; ảnh desktop/mobile riêng, car picker 4 xe |
| D02/M02 | 211:287 / 57:452 | Form/sidebar, provinces/wards/xe/Facebook; thêm address_line để giữ business/schema hiện tại |
| D03/M03 | 211:450 / 129:346 | Lỗi inline, aria-invalid/describedby/live regions; bỏ summary theo yêu cầu UX mới, có chủ đích khác Figma cũ |
| D04/M04 | 212:377 / 57:571 | Heading thành công, card cộng đồng, CTA đỏ, progress check xanh/current đỏ/upcoming xám; badge Chờ vào group |
| D05/M05 | 212:501 / 57:664 | Duplicate phone inline và panel riêng, giữ dữ liệu đã nhập và xe; không bịa record chủ sở hữu |
| D06/M06 | 212:587 / 57:714 | Dealer inactive không có form POST; trạng thái nằm trong panel D01 theo scope đã chốt, khác frame standalone |

Typography source gán Inter/Arial/sans-serif rõ cho headings và controls, Montserrat
cho hero/những vị trí tương ứng; không fallback Times. Fonts hiện tải Google Fonts
qua HTTPS, **chưa self-host WOFF2**, chưa xác minh tải font/runtime glyph bằng browser.
Không đánh dấu việc font tự tải thành công là PASS từ CSS declaration.

CSS có mobile <=767px và tablet <=1100px, minmax(0,1fr), wrap nội dung, carousel
cuộn riêng, focus-visible/skip link/reduced motion. 390px/768px/1440px hiện chỉ được
đối chiếu source và Figma; chưa đo scrollWidth/clientWidth hoặc kiểm tra sticky CTA,
keyboard, crop ảnh, overflow ngang, focus order thực tế và screen reader. **Không
tuyên bố pixel-perfect hoặc WCAG compliance.** 768px cần QA riêng tại ranh breakpoint.

Những khác biệt có chủ đích: không số thành viên/ngày chiến dịch demo, không cam
kết 48h, không xác nhận Facebook membership; caption D04 nằm dưới CTA; lookup còn
unavailable và hướng dẫn liên hệ Dealer, không triển khai lookup ngoài scope.
Các link terms/privacy cần cấu hình thật trước nghiệm thu nội dung production.

## 3. Security QA

- PASS regression: CSRF thiếu/sai token 419 và token hợp lệ được nhận, draft TTL
  30 phút, receipt owner session/Dealer/TTL 24h, no-store/private, honeypot,
  throttling, system-field allowlist, idempotency và duplicate protection đã có.
- PASS local production boot: AppServiceProvider ép APP_DEBUG=false và secure
  cookie; session HTTP-only/SameSite=lax. Routes preview không đăng ký khi production,
  compiled production route cache cũng không chứa preview; controller còn guard runtime.
- Public registration không đưa PII vào URL, flash old input chung; draft/receipt
  mã hóa server-side và HMAC keys. Handler che SQL bindings và lỗi registration.
  Đây là kiểm tra source/fixtures, không phải audit mọi log production/infra.
- Group canonical là https://www.facebook.com/groups/376631239337942; D04 anchor
  thật, target=_blank, rel=noopener noreferrer, không phụ thuộc approved.
- Single-node production dùng SESSION_DRIVER=file, CACHE_DRIVER=file; draft service
  gọi store('file') trực tiếp. Đổi default sang Redis không tự chuyển draft sang Redis.
  Shared storage phải tồn tại qua mọi release; APP_KEY giữ nguyên. Multi-node chưa sẵn sàng.
- HTTPS, trust proxy, Nginx/FPM logs, session cookies thực tế qua domain production,
  quyền filesystem, SMTP/secret management chưa được kiểm chứng trên VM.
- Demo approved vẫn tính điểm khi trộn DB; reactivation rejected → active và bulk
  SQL identity writes chưa có guard đầy đủ. Các quyết định Phase 2C.2 chưa được chốt.

## 4. Blockers trước deploy

1. Nghiệm thu browser 390/768/1440px cho đủ trạng thái, font thực tải, không overflow,
   accessibility/focus và validation. Chụp ảnh screenshot, đặc biệt tablet và D04.
2. Chốt chính sách dữ liệu lịch sử/rejected reactivation và cách kiểm soát import/bulk
   writes; kiểm kê collision lịch sử thực bằng quy trình read-only đã được cho phép.
   Không âm thầm đổi rule Admin để đóng blocker.
3. Chứng minh DB production không có demo ảnh hưởng leaderboard; không chạy demo
   seeders trong release. Không coi TOYOTA_DEMO_ENABLED=false là xóa/loại demo đã có.
4. Kiểm tra runtime Ubuntu thực: PHP 8.5 security-patched. PHP 8.5.11 đang test local
   không phải baseline production; PHP công bố 8.5.12 là security release và khuyến
   nghị nâng cấp. Cần kiểm tra patch mới nhất/vendor backport và chạy lại CI trên đúng
   runtime được chọn. [PHP release announcement](https://www.php.net/releases/8_5_12.php).
5. Hoàn tất dependency audit từ builder có mạng; xác nhận Ubuntu, MySQL, Nginx/FPM,
   extensions, TLS/domain, secrets, disk, backup/restore và quyền storage.
6. Chốt các pending migrations thật qua migration ledger, review SQL/DDL impact trên
   clone/staging; chuẩn bị artifact có đủ file mới, checksum và bản rollback tương thích.
7. Terms/privacy URL, public origin QR, SMTP và production config được người phụ
   trách xác minh. Cleanup retention vẫn tắt đến khi duyệt dry-run và policy.

## 5. VM budget và artifact

2 vCPU / 2GB RAM / 20GB disk: build/test trên builder, không trên VM. Không cài
Node/node_modules hoặc dev vendor vào production. Builder hiện test Node 18.8.0;
cần pipeline dùng Node/PHP còn được duy trì, tương thích lockfiles, verify lại build.
Composer cũ local có notices; dùng Composer 2 được cập nhật trên builder.

Đề xuất ban đầu để đo trên staging, **chưa phải cấu hình đã load-test**:
FPM ondemand, max_children=2, process_idle_timeout=10s, max_requests=200,
memory_limit=256M, OPcache khoảng 128MB; MySQL buffer pool khởi đầu khoảng 256MB
nếu DB cùng VM. Đo RSS/CPU/latency/disk rồi điều chỉnh, tránh tăng worker theo cảm tính.
Global mutex và scan O(N) vẫn giới hạn throughput; số đo Phase 2C.2 không phải SLA.
[PHP FPM directives](https://www.php.net/manual/en/install.fpm.configuration.php).

Giữ đủ chỗ cho release hiện tại + release rollback + private uploads/logs/DB; đặt
cảnh báo disk sớm (ví dụ 75%), log rotation, backup ra off-host. Không giữ node_modules,
dump DB lớn hoặc nhiều bản release cũ trên disk 20GB. Chưa đo disk/traffic thật.

Builder Linux tương thích target, từ source bundle đã review (không chỉ HEAD):

```bash
composer validate --no-check-publish
composer install --prefer-dist --no-interaction
composer audit --locked
npm ci
npm run build
# Chạy suite QA chọn lọc trên toyota_testing đã có schema; không migration trong test.
php vendor/bin/pint --test
# Sau tests, trong một artifact directory riêng, không làm mất dev tools của builder:
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
```

Đóng gói app/bootstrap/config/routes/resources, toàn bộ migrations hiện có, vendor
no-dev, public/index.php và assets bao gồm public/build/manifest.json, public/build/assets,
public/dealer-registration. Không kèm .env local, .git, node_modules, tests/Fixtures,
storage testing/logs/sessions/cache, credentials demo hoặc bootstrap config/route cache
của local. Không xóa private uploads thật. Tạo checksum artifact và manifest file.

## 6. Migrations và release order

Migration mới duy nhất trong diff release:
`database/migrations/2026_11_03_000023_support_final_dealer_registration.php`:
odo nullable unsigned integer, submission_intake_mutex + row id=1,
dealer_registration_receipts + FK restrict. 000023 phải chạy **trước khi code mới
nhận bất kỳ intake/identity edit**, kể cả legacy/Sales, vì model guard cần mutex.

Prerequisites phải đã áp dụng: submission base/checks, vehicle evidence 000010,
Dealer snapshot 000013, address 000015, stable slug 000019, Dealer QR 000020,
submission edit audit 000022 và các migration tài khoản phụ thuộc tương ứng.
Chưa đọc migration ledger production; không thể khẳng định production chỉ thiếu
000023. Nếu còn pending cũ, dừng và review thứ tự riêng; không chạy migrate --force
toàn bộ mù quáng. Không sửa migration cũ hoặc thêm unique phone/plate.

Thứ tự: đóng blockers → artifact/rehearsal → xác minh config/DB → maintenance/drain
→ backup DB + private storage + production secrets bảo mật → migration được duyệt
→ caches production → switch release → reload FPM → health checks → mở traffic.

## 7. Backup và storage

Layout gợi ý `/srv/toyota/releases/<release-id>`, `/srv/toyota/current` là symlink,
`/srv/toyota/shared/storage`, `/srv/toyota/shared/.env` do operator quản lý.
Giữ nguyên APP_KEY; không chạy key:generate trên app đã có ciphertext.
Bootstrap/cache thuộc riêng từng release; storage/cache/session/private data phải shared.

Backup trước migration trong maintenance, bằng credential file/login-path bảo vệ:

```bash
set -euo pipefail
umask 077
: "${DB_NAME:?Set and verify the production database name}"
: "${BACKUP_DIR:?Use a verified mounted off-host backup destination}"
mysqldump --login-path=toyota-backup --single-transaction --quick \
  --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF \
  "$DB_NAME" | gzip -1 > "$BACKUP_DIR/pre-release.sql.gz"
```

Xác nhận dump hoàn tất/checksum, backup storage/app/private và production secret
material vào kho mã hóa có quyền hạn chế, rồi diễn tập restore ngoài VM production.
Không đưa password vào command line/log/chat. Không lấy .env local làm production.

FPM/Nginx đọc code, chỉ runtime user được ghi storage và bootstrap/cache. Chuẩn bị
storage/framework/{cache/data,sessions,views}, storage/logs và storage/app/private/
{submission-evidence,dealer-photos,sales-avatars}. Không chmod 777, không symlink
private storage vào public. public/storage nếu dùng chỉ trỏ storage/app/public.
Kiểm tra resolved paths trước mọi thao tác quyền/di chuyển/retention.

## 8. Production config và deploy runbook (chưa thực thi)

Ví dụ cấu hình, thay domain/credentials bằng production values được xác minh:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://toyota.example.invalid
DEALER_PUBLIC_BASE_URL=https://toyota.example.invalid
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=daily
LOG_LEVEL=warning
TOYOTA_COMMUNITY_GROUP_URL=https://www.facebook.com/groups/376631239337942
TOYOTA_RECEIPT_CLEANUP_ENABLED=false
TOYOTA_DEMO_ENABLED=false
TOYOTA_DEMO_ALLOW_PRODUCTION=false
```

SESSION_DOMAIN chỉ đặt nếu cần và đúng host; giữ consent version hiện có. Cấu hình
DB/SMTP/terms/privacy trong secret/config management, không lưu secret vào artifact.
DB database/cache/session drivers không có migrations tables tương ứng thì không
tự chọn. Source hiện dùng CACHE_DRIVER (không chỉ CACHE_STORE).

Sau khi đã chốt blockers, operator thực hiện từng bước, không copy chạy nguyên khối:

1. Xác minh artifact checksum, no-dev vendor, PHP CLI/FPM cùng phiên bản/extension,
   pdo_mysql và gd cho QR, timezone đúng nghiệp vụ; composer platform check trên VM
   nhẹ nếu cần. Không composer update, npm build hoặc tests nặng trên VM.
2. Stage release, gắn production .env/shared storage, runtime permissions; kiểm tra
   actual DB host/database bằng thông tin không lộ credential và migrate:status.
3. Đưa current app maintenance, chờ request đang chạy hoàn tất; snapshot/backup và
   xác minh phục hồi. Không in maintenance bypass secret vào log.
4. Chạy migration path được review từ staged release nếu ledger còn pending:

```bash
php artisan migrate --force --path=database/migrations/2026_11_03_000023_support_final_dealer_registration.php
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

5. Xác minh odo nullable, mutex row=1, receipt PK/FK; production routes không có
   _preview, debug=false, group URL đúng. Không xuất toàn bộ config vì chứa secrets.
6. Switch current bằng symlink tạm rồi rename atomic trong cùng filesystem; giữ
   previous target. `nginx -t` trước reload nếu đổi config; reload PHP-FPM để worker/
   OPcache theo release mới. Nginx/FPM service/socket name phải xác minh trên VM.
7. Health checks trong maintenance/bypass được bảo vệ, sau đó `php artisan up` từ
   release active và giám sát. Không thay business rows để thử approved production.

**Không chạy optimize:clear hoặc cache:clear trong runbook này.** Source Laravel
OptimizeClearCommand gọi cache:clear; default file cache dùng chung với draft store,
có thể làm mất draft đang dùng và quota. Các cache commands riêng bên trên không
flush application cache. Không đổi APP_KEY hoặc xóa storage/framework/cache/data.
[Laravel deployment guidance](https://laravel.com/docs/13.x/deployment).

## 9. Nginx/HTTPS checklist và template cần operator review

Template chưa nginx -t trên Ubuntu; thay domain, cert paths và FPM socket thật.
Không expose project root, chỉ public. Không FastCGI cache D01/D02/D04, auth hoặc
response có session. Nếu có reverse proxy/CDN, cấu hình trusted proxies cụ thể và
verify scheme/client IP/rate limits; không tự trust toàn bộ X-Forwarded headers.

```nginx
# http {} context: no URL/query/body/cookie/referrer in this access log format.
log_format toyota_min '$time_iso8601 $request_method $status $body_bytes_sent $request_time';

server {
    listen 80;
    server_name toyota.example.invalid;
    return 308 https://toyota.example.invalid$request_uri;
}
server {
    listen 443 ssl;
    server_name toyota.example.invalid;
    root /srv/toyota/current/public;
    index index.php;
    ssl_certificate /etc/letsencrypt/live/toyota.example.invalid/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/toyota.example.invalid/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    client_max_body_size 8m;
    access_log /var/log/nginx/toyota-access.log toyota_min;
    error_log /var/log/nginx/toyota-error.log warn;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ^~ /build/ { try_files $uri =404; expires 1y; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
    location ~ ^/(vendor|bootstrap|tests|docs)(/|$) { return 404; }
}
```

PHP upload_max_filesize>=5M, post_max_size>=8M cho Sales evidence, request timeout
có giới hạn phù hợp; verify body 413/validation UX. Không mở FPM TCP public.
Kiểm tra renewal TLS, certificate chain, HTTP redirect, secure cookies và không
mixed content. Chỉ bật HSTS sau khi domain/subdomain policy được duyệt.
Nginx error logs và upstream/CDN logs vẫn có thể chứa request URL khi lỗi; hạn chế
quyền/retention và kiểm tra sanitization. Admin GET search hiện hữu có thể chứa q;
không tuyên bố toàn bộ ứng dụng không bao giờ có PII trong query string.
[Nginx log format documentation](https://nginx.org/en/docs/http/ngx_http_log_module.html).

## 10. Queue, cron, scheduler

Không tìm thấy application queued jobs/ShouldQueue/dispatch trong app hiện tại;
QUEUE_CONNECTION=sync là lựa chọn tương thích, không tạo queue worker/database jobs
table ngoài scope. Nếu deployment dùng async mail/jobs riêng, kiểm kê và cấu hình
Supervisor/systemd, số worker và restart riêng trước release.

Cron dưới runtime user có quyền shared storage (đường dẫn PHP kiểm tra trên VM):

```cron
* * * * * cd /srv/toyota/current && /usr/bin/php artisan schedule:run >> /srv/toyota/shared/scheduler.log 2>&1
```

Log scheduler private và rotate. Receipt cleanup hourly/withoutOverlapping hiện
disabled; sau khi có phê duyệt retention mới chạy dry-run đúng DB, xem count, rồi
bật TOYOTA_RECEIPT_CLEANUP_ENABLED và config:cache qua quy trình release. Không chạy
cleanup thật hoặc bật flag trong lượt QA này. Giữ session/draft cache server-side.

## 11. Health checks và rollback runbook

Sau deploy operator kiểm tra: `/`, `/dealers`, `/leaderboard`, `/login`, Dealer slug
active/inactive (D06), QR PNG, JS/CSS/PNG 200 và MIME; preview 404; .env/vendor/tests/
private paths không truy cập được; HTTP→HTTPS/cookies; cache headers private/no-store.
Source không có `/up` route riêng, không coi `/up` là health endpoint đã triển khai.
Kiểm tra DB migration ledger/mutex, FPM error rate, 419/429, CPU/RSS/disk/latency.
Đăng ký→D04/refresh/retry/approve/reject nên diễn tập ở staging; mọi canary có ghi
production cần được owner phê duyệt và không tạo approved/demo làm sai leaderboard.

Rollback:

1. Maintenance/drain, lưu lỗi/counts không PII; tắt intake trước khi quay về bản cũ
   nếu bản đó không tương thích guard/schema; không tự mở luồng cũ không an toàn.
2. Giữ nguyên DB/storage/APP_KEY; atomic switch về previous **compatible** release,
   rebuild config/route/view caches của release đó với production env, reload FPM.
3. Health checks/ownership/QR/header/schema, rồi chỉ up khi đã chấp thuận. Nếu code
   cũ không tương thích dữ liệu mới, giữ maintenance và fix-forward.
4. **Không mặc định migrate:rollback.** down() 000023 từ chối khi có receipt hoặc
   ODO; xóa cột/bảng sẽ mất dữ liệu/idempotency. Additive schema thường được giữ
   nguyên khi code rollback, phải verify bản rollback đã rehearsed trên schema mới.
5. Không restore dump cũ đè DB sau khi đã nhận submissions mới: sẽ mất dữ liệu. Nếu
   cần disaster recovery, restore off-host vào DB phục hồi, đối soát/reconcile và
   xin chấp thuận trước cutover; không tự xóa/rewrite lịch sử.

## 12. Files thay đổi và release manifest

**Riêng lượt 2C.3:** tạo báo cáo này và docs/figma/qa/release-preflight.php.
Pint-only fixes: app/Http/Kernel.php, app/Http/Middleware/RedirectIfAuthenticated.php,
app/Http/Requests/Admin/SalesIndexRequest.php, bootstrap/app.php, config/app.php,
config/auth.php, database/factories/AwardFactory.php, docs/figma/qa/asset-check.php,
docs/figma/qa/render-check.php, docs/figma/qa/router.php. Chỉ import/docblock/braces/
formatting; không migration hoặc thay business rules. public/build được rebuild.

**Working tree release gồm các phase trước**, ngoài Pint files nêu trên:

- app/Actions/UpdateCustomerSubmission.php; app/Console/Kernel.php;
  app/Exceptions/Handler.php; config/dealer-registration.php; routes/web.php.
- app/Http/Controllers/{DealerRegistrationController,DealerRegistrationDraftController,
  DealerRegistrationFinalController}.php; app/Http/Controllers/Sales/SubmissionController.php.
- app/Models/CustomerSubmission.php;
  app/Services/{DealerDashboardData,DealerRegistrationDraft,DealerRegistrationReceipt,SubmissionIntake}.php;
  app/Console/Commands/PruneDealerRegistrationReceipts.php.
- database/migrations/2026_11_03_000023_support_final_dealer_registration.php.
- resources/css/dealer-registration.css; resources/js/dealer-registration.js;
  resources/views/components/dealer-registration/step-one.blade.php;
  resources/views/components/submission-vehicle-details.blade.php;
  resources/views/dealers/registration/{step-two-content,success}.blade.php.
- tests/Feature/{DealerRegistrationDraftTest,DealerRegistrationFinalTest,
  DealerRegistrationSuccessPresentationTest,RegistrationBusinessIntegrationTest,
  RegistrationHttpConcurrencyTest,RegistrationReceiptCleanupTest,
  SubmissionIntakeRetryTest,SubmissionIntegrityUpdateTest}.php;
  tests/Fixtures/registration_http_router.php; tests/Unit/SubmissionIntakeNormalizationTest.php.
- docs/figma/{d04-success-ui-refinement,phase-2b-implementation,phase-2c-1-integration,
  phase-2c-2-data-integrity}.md; docs/figma/qa/step-two-validation-check.mjs.

Untracked PHP/service/migration files phải có trong artifact. Không copy test router
ra public, không tự commit/push để giải quyết release manifest. Source/lockfiles/
assets cần review và tạo immutable artifact sau khi blockers đóng.

Không deploy, commit, push, chạy migration hoặc cleanup dữ liệu production trong lượt này.
