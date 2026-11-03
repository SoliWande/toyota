# Phase 2C.4C — Browser QA & production blocker closure

Ngày 03/11/2026. **Quyết định: NO-GO production. Không deploy.**

Đã đọc đầy đủ Phase 2C.1, 2C.2, 2C.3, 2C.4A, 2C.4B; đối chiếu source, git diff,
routes/middleware, draft/receipt, metrics/demo scope, migration, lockfiles và bundle.
Không sửa business rules hoặc application source trong lượt này.

## Kết quả nghiệm thu

| Mục | Kết quả | Bằng chứng / giới hạn |
| --- | --- | --- |
| PHP regression Phase 2A/B/C + Admin/Sales/Dealer/leaderboard | PASS | 235 tests / 1.989 assertions; 1 phút 42,449 giây; peak 98 MB |
| HTTP concurrency | PASS trong phạm vi test | 10 cases, hai server/session/connection, CSRF thực; gồm intake/approval races |
| Demo isolation | PASS có giới hạn | Chỉ verified realistic manifest; không xác nhận demo lịch sử khác hoặc published awards |
| npm run build | PASS | Vite 6.4.3; 62 modules; 6 generated assets + manifest |
| Pint toàn repo / git diff --check | PASS | Không lỗi; Git có warning CRLF→LF của files cũ |
| Render checks | PASS | 33 checks, gồm inline errors, CSRF markup, production final POST và preview guard |
| Frontend JS harness | PASS | Step One/Two messages, correction, retention, focus/scroll/aria qua harness; không phải browser |
| Dealer assets | PASS | 22 PNG không rỗng, dimensions khớp; views không có temporary Figma URLs |
| Production boot/route cache | PASS local | 9 preflight checks, DB driver disabled, không kết nối DB ứng dụng |
| Composer validate / platform --no-dev | PASS local | composer.json hợp lệ; PHP 8.5.11 và dependencies hiện cài đáp ứng requirements |
| composer audit --locked --no-dev | NOT VERIFIED | Exit 1, curl error 7 tới Packagist; không có kết quả advisory, không được coi là zero vulnerabilities |
| Browser 390/768/1440px | NOT VERIFIED | Chrome không mở CDP sau 15s, không render/screenshot app |
| Font Inter/Montserrat thực tải | NOT VERIFIED | CSS/source đúng families; fonts còn phụ thuộc Google Fonts HTTPS, chưa self-host WOFF2 |
| Runtime vendor no-dev | NOT VERIFIED | Workspace chứa 37 dev packages; không đưa vendor đó vào artifact |
| Deployable artifact | FAIL readiness gate | Chỉ tạo source + built-assets candidate; thiếu vendor no-dev đã nghiệm thu và Linux rehearsal |
| Production DB/VM/TLS/backup/restore | NOT VERIFIED | Không SSH, không kết nối production, không chạy production commands |

PASS của test DB/production-mode boot không chứng minh production DB/VM sạch hoặc
đã cấu hình đúng. Không có kết luận pixel-perfect, WCAG compliance hoặc browser E2E PASS.
Suite chọn lọc dùng đúng lệnh 13 test targets trong Phase 2C.4B; không chạy toàn bộ
legacy feature suite có RefreshDatabase/DatabaseMigrations, không tuyên bố mọi test
trong repository PASS. Sau regression, read-only check trên toyota_testing xác nhận
submissions, receipts, moderation_reviews, users, dealers đều 0 rows. Local CLI có
pdo_mysql, gd, openssl và mbstring; PHP-FPM production vẫn chưa kiểm chứng.

## Browser và Figma evidence

Đã gọi Figma MCP get_design_context đủ 12 frame trong file
`yKmWQI6BYJ6dponEnidlsq`; lưu các fonts/context receipt tại
[figma-reference-2c-4c.json](qa/figma-reference-2c-4c.json).

| State | Desktop node | Mobile node | Browser tại cả 3 widths |
| --- | --- | --- | --- |
| D01/M01 | 209:233 | 99:376 | NOT VERIFIED |
| D02/M02 | 211:287 | 57:452 | NOT VERIFIED |
| D03/M03 | 211:450 | 129:346 | NOT VERIFIED |
| D04/M04 | 212:377 | 57:571 | NOT VERIFIED |
| D05/M05 | 212:501 | 57:664 | NOT VERIFIED |
| D06/M06 | 212:587 | 57:714 | NOT VERIFIED |

Figma context xác nhận Inter chủ đạo, Montserrat cho hero/vị trí tương ứng. CSS
dr-site gán Inter/Arial/sans-serif cho headings và controls, hero Montserrat/Arial/
sans-serif. Chưa thể kiểm chứng Rendered Fonts/glyph hoặc chặn fallback serif trong
browser thực. Font requests Google còn là external dependency; không có WOFF2 trong
bundle. Không tự đánh dấu self-host requirement của phase trước đã hoàn thành.

Source có mobile <=767px, tablet <=1100px; 768px không dùng mobile layout. Chưa đo
document scrollWidth, CTA occlusion/safe-area/keyboard, focus order, crop ảnh hoặc
accessibility thực. 768px và hai biên 767/769 cần manual acceptance riêng.

D04 source/render tests giữ heading thành công, Group link đúng URL, progress hướng
dẫn 3 bước và badge Chờ vào group; không auto approve/Facebook membership. Các khác
biệt đã chốt so Figma cũ gồm bỏ validation summary, không cam kết 48h, inactive trong
panel D01. Không sửa lại theo nội dung cũ của Figma.

Chrome **156.0.8078.76** được launch headless=new bằng profile QA riêng ở workspace,
loopback CDP, about:blank; không kết nối được `/json/version` trong 15s. Chỉ dừng
process QA đã tạo, không điều khiển browser/profile người dùng. Không có browser
automation connector khả dụng. Figma get_screenshot D04/M04 trả short-lived URLs,
nhưng download PNG bị URLError. Không lưu URLs tạm làm runtime asset.

- [Browser evidence](qa/browser-evidence-2c-4c.json).
- [Screenshot directory/evidence status](qa/screenshots/README.md): **0 screenshots app**.
- [Manual QA matrix và DevTools checklist](qa/browser-manual-2c-4c.md): đầy đủ 18
  state/width combinations, fonts, inline validation, keyboard, D04 và cross-session.

## Security và dependencies

Đã kiểm tra source/test cho CSRF thiếu/sai token 419 và valid token được nhận,
honeypot, system-field allowlist, IP throttle 5/phút + 20/giờ, draft TTL 30 phút,
ownership session/Dealer, receipt TTL 24h, idempotency và private/no-store.
Không disable CSRF, không thêm exceptions hoặc đổi APP_KEY.

Production preflight cố ý boot APP_ENV=production, APP_DEBUG=true và secure cookie
false rồi xác nhận provider ép debug=false/secure=true, HTTP-only cookie, compiled
route cache không chứa preview. Driver DB bị disabled nên đây là test configuration,
không kiểm tra domain/TLS/FPM production. Không chạy optimize:clear/cache:clear.

Draft luôn Cache::store('file'), ciphertext server-side, HMAC session/Dealer keys.
Receipt encrypted DTO trong DB, HMAC owner/draft; success URL không chứa submission
ID hoặc PII. Test session khác/Dealer khác/expired không đọc confirmation PASS;
browser incognito check vẫn NOT VERIFIED. APP_KEY phải giữ nguyên qua release.
Đổi default cache sang Redis không tự chuyển draft; cần shared file storage bền
vững trên single node, chưa nghiệm thu multi-node.

SecurityHeaders và controller headers có private/no-store; storage evidence/photo/
avatar nằm dưới storage/app/private và qua controller authorization/projection.
Không có public/storage symlink hiện tại; candidate không có storage hoặc runtime
uploads. Chưa kiểm tra ACL Ubuntu/Nginx public/private thực tế; Windows ACL không
thay thế Linux owner/mode. Không chmod hoặc sửa filesystem permissions.

Artifact allowlist chặn .env/credentials/tests/docs QA/cache/symlinks; literal scan
private key/AWS key/APP_KEY token PASS trong files đóng gói. Đây là scan giới hạn,
không chứng minh không có mọi loại secret hoặc PII trong infra logs. Không đọc
credential demo hoặc in .env. Access/error/CDN/FPM logs, forwarded IP/scheme và
rate limiting qua proxy cần operator kiểm tra; không trust forwarded headers tùy ý.

[Dependency evidence](qa/dependency-evidence-2c-4c.json) có exit codes/output đã bỏ
deprecation noise. Composer local cũ phát deprecation notices và không ghi được
cache ngoài sandbox; audit không tới Packagist. Cần chạy audit lại trên builder có
mạng và Composer phù hợp PHP 8.5. Không sửa lockfiles hoặc tự update dependencies.

Node local 18.8.0 build được nhưng Node 18 đã EOL; phải rebuild/retest trên supported
LTS trước release. [Node release schedule](https://nodejs.org/en/about/previous-releases).
PHP 8.5.11 local đạt Composer constraints, không phải chứng nhận bản vá security
hoặc PHP-FPM compatibility trên Ubuntu. Operator chọn patch/backport còn được hỗ
trợ và chạy lại platform/tests trên target builder; không suy ra từ báo cáo cũ rằng
một patch là mới nhất. [PHP support policy](https://www.php.net/supported-versions.php),
[Laravel deployment requirements](https://laravel.com/framework/docs/13.x/deployment).

## Release artifact và checksums

Đã tạo gói **source + built assets, NOT DEPLOYABLE**:

```text
storage/app/private/release-qa/phase-2c-4c-source-NOT-DEPLOYABLE.zip
SHA-256: b5809cb0c5092cbd11d496f0582f9aa26137b693bfbc60b7449e87d117ba5619
291 files; 3.762.408 bytes
```

[Release manifest](qa/release-manifest-2c-4c.json) ghi từng path/size/SHA-256,
27 migrations, 80 locked production packages, working-tree modified/untracked inventory
và exclusions. [Checksum sidecar](qa/release-artifact-2c-4c.sha256).
Base HEAD `64c3fc5`; candidate lấy **filesystem hiện tại**, bao gồm services/controllers/
migration untracked từ các phase trước, không chỉ git HEAD. Mọi member trong ZIP
đã được đọc lại, kiểm tra CRC, so đúng file set và SHA-256 từng file.

Allowlist gồm app/config/routes/resources, bootstrap source, artisan, composer/npm
manifests + locks, Vite config, public entry/assets/build, static administrative
units và Toyota directory JSON. AdministrativeDivisions cần database/data JSON
khi runtime; đã kiểm tra có trong ZIP. Bao gồm Dealer assets và public/hilux-brothers.

Không kèm .env local, credentials/.laravel-tools, .git, vendor hiện tại, node_modules,
tests/Fixtures, docs QA routers, storage/log/session/cache/uploads, bootstrap/cache,
public/hot/public/storage, demo seeders/factories/portraits. App preview controller
vẫn là source; production preview route không được đăng ký (đã preflight cached
routes), không đóng gói router debug trong public.

**Không upload/sử dụng ZIP này làm production release.** Vendor no-dev chưa được
build/verify. Trong staging directory riêng trên Linux builder có mạng, từ reviewed
source và nguyên lockfiles: npm ci → build → isolated tests → composer install
--no-dev --prefer-dist --no-interaction --optimize-autoloader → platform check →
package discover/production boot với config an toàn → kiểm tra không dev packages →
checksum toàn artifact cuối. Không prune vendor của workspace đang dùng để test.
Rebuild thay đổi bytes phải tạo manifest/checksum mới; ZIP Windows không chứng minh
Linux executable bits/permissions hoặc reproducibility của clean npm/composer install.

Tool tái tạo inventory/source candidate: `python docs/figma/qa/release-manifest.py`.
Không chạy DB hoặc Laravel bootstrap; output giữ ở private storage, không trong public.

## Database readiness — operator read-only checklist, CHƯA CHẠY

Chỉ operator sau khi xác minh target và authorization; không dùng .env local cho
production. Không copy chạy nguyên khối khi chưa kiểm tra connection:

```sql
SELECT DATABASE(), VERSION();
SELECT migration, batch FROM migrations ORDER BY migration;
SELECT table_name, table_rows, data_length, index_length
FROM information_schema.tables WHERE table_schema = DATABASE();
SHOW INDEX FROM customer_submissions;
SHOW INDEX FROM dealer_registration_receipts;
```

- Đối chiếu ledger với 27 migration filenames/checksums trong manifest. Production
  ledger chưa đọc nên không khẳng định chỉ thiếu 000023.
- Migration mới của Phase 2B: `2026_11_03_000023_support_final_dealer_registration.php`:
  nullable unsigned ODO, mutex table/row id=1, receipt PK/owner/submission indexes/FK
  restrict. Phải có trước mọi intake/edit/approval dùng model guard mới. Không thêm
  migration ở 2C.4A/B/C; marker demo và expires_at index chỉ là đề xuất riêng.
- Trên clone/staging: review ALTER locking/time/disk/rollback, estimate cardinality,
  concurrency scan O(N)/global mutex và cleanup EXPLAIN. Chưa có expires_at index.
  Không thêm unique phone/plate trên historical data chưa audit.
- Collision audit: sau khi operator xác nhận config/actual DB, dùng
  `php artisan submissions:audit-collisions --confirm-database=DB_OPERATOR_CONFIRMED --json`.
  Chỉ counts, không PII; kiểm tra áp lực snapshot/memory và chạy off-peak.
- Demo audit: xác minh provenance/manifest toàn vẹn, dataset không manifest và
  published awards riêng. TAG/email không chứng minh classification. Trạng thái
  TOYOTA_DEMO_ENABLED=false không tự xóa/loại mọi demo. Không chạy seed/cleanup demo.
- Receipt dry-run sau target/retention approval:
  `php artisan dealer-registration:prune-receipts --dry-run --batch=500 --max-batches=20`.
  Không chạy cleanup thật; scheduler cleanup tiếp tục default disabled. TTL truy cập
  không tương đương xóa ciphertext vật lý; cần chốt retention và lịch dọn được duyệt.
- Backup DB + private storage off-host, checksums và restore rehearsal trên DB cách
  ly trước migration. Không đưa dumps/secrets vào git hoặc candidate ZIP.

Không chạy bất kỳ checklist production nào trong lượt này. Test DB schema đã tồn
tại; không chạy migration/migrate:fresh/RefreshDatabase.

## VM readiness — tất cả CHƯA KIỂM CHỨNG trên VM

Budget 2 vCPU / 2GB RAM / 20GB disk. Không SSH trong lượt này.

- Ubuntu release/support, PHP CLI/FPM cùng patched version/extensions (pdo_mysql,
  gd, openssl, mbstring hoặc requirements tương đương), MySQL version/backup access.
- Nginx document root chỉ public, `nginx -t`, đúng FPM socket, không expose PHP khác
  index.php/.env/tests/docs/vendor/private storage; giới hạn body/upload hợp lệ.
- TLS chain/expiry/renewal, HTTP→HTTPS, secure/HTTP-only/SameSite cookies, proxy/client
  IP/scheme, no mixed content; không cache auth/draft/receipt pages tại CDN/FastCGI.
- Domain APP_URL/DEALER_PUBLIC_BASE_URL đúng canonical QR; official Group URL;
  terms/privacy URLs được duyệt; SMTP qua test inbox, không gửi khách thật để QA.
- RAM/RSS/CPU/swap/disk/inodes và chỗ cho DB + private uploads + release rollback;
  không npm build/test nặng trên VM; không giữ node_modules/dev vendor/dump lớn tại VM.
- Shared storage xuyên release: sessions, file draft cache, private assets; owner/
  mode giới hạn runtime writes vào storage/bootstrap/cache, không chmod 777.
- Cron schedule:run dưới runtime user, private log rotation, receipt dry-run/retention
  được duyệt trước bật cleanup; queue hiện sync, không tự thêm worker/jobs table.
- Off-host encrypted backup, recovery credentials, restore rehearsal, rollback
  compatible release, monitoring 419/429/5xx/latency/disk và contact owner.

## Release order và rollback — chưa thực thi

Đóng blockers → Linux no-dev artifact + audit + browser/staging acceptance → operator
kiểm tra target/ledger/data/VM → maintenance/drain → backup/restore evidence → chỉ
migration path đã duyệt → caches riêng config/event/route/view → atomic switch/reload
FPM → health checks/cookies/private headers/QR/preview 404 → mở traffic và giám sát.
Không chạy optimize:clear/cache:clear vì file application cache chứa draft/rate limit.
Không đổi APP_KEY hoặc xóa shared storage; không chạy demo seeders.

Rollback: maintenance/drain, switch về **compatible** prior release giữ DB/storage/
APP_KEY, rebuild caches riêng và reload FPM; nếu không tương thích thì fix-forward
trong maintenance. Không migrate:rollback tự động: down() 000023 từ chối có receipt/
ODO để tránh mất dữ liệu/idempotency. Không restore dump cũ đè các đăng ký mới.
Chi tiết Nginx/backup/health runbook tại Phase 2C.3 vẫn cần operator review/rehearsal.

## Blockers đã đóng và còn mở

Đã đóng ở mức local evidence: final/draft security regression; intake/edit/approval
mutex và concurrency; controlled duplicate approval/reactivation model; normalized
phone alias insert guard; verified realistic demo metrics isolation; production route
cache không preview; build/render/assets; allowlisted source candidate/inventory/checksum.

Còn mở trước GO:

1. Browser/font/responsive/accessibility acceptance cho đủ 18 combinations và
   cross-session browser evidence; chưa có screenshots, chưa self-host WOFF2.
2. Dependency audit có mạng, supported Node/Linux builder, patched target PHP/FPM,
   full no-dev artifact và boot rehearsal.
3. Production read-only collision/demo audit, unknown demo/published award handling;
   manifest unsigned đã sửa có thể làm metrics HTTP 500, không dùng demo mode để bypass.
4. External import/bulk/direct SQL writers chưa có guard DB tự động: operator phải
   xác nhận không dùng hoặc chuyển sang guarded protocol/tests; không suy ra an toàn
   cho writer ngoài repo. Không tự thay import policy/unique constraint.
5. Production ledger/schema/DDL, TLS/domain/proxy, storage persistence/permissions,
   SMTP/terms/privacy, retention dry-run/scheduler, backup/restore/rollback và VM budget.

Vì các mục trên chưa có evidence, NO-GO là quyết định readiness hiện tại, không phải
kết luận có lỗi business mới hoặc production đang hỏng. Không tự đóng blocker bằng
giả định hoặc kết quả từ database testing trống.

## Files tạo/thay đổi lượt này

- docs/figma/phase-2c-4c-final-readiness.md.
- docs/figma/qa/{release-manifest.py,release-manifest-2c-4c.json,
  release-artifact-2c-4c.sha256,dependency-evidence-2c-4c.json,
  figma-reference-2c-4c.json,browser-evidence-2c-4c.json,browser-manual-2c-4c.md}.
- docs/figma/qa/screenshots/README.md (trạng thái thiếu screenshot).
- public/build được rebuild (ignored); source candidate ZIP nằm private storage
  (ignored), browser profile diagnostics nằm storage/framework/testing (ignored).

Không sửa application source, migrations, lockfiles, .env hoặc APP_KEY. Không commit,
push, upload, deploy, SSH, production migration hoặc cleanup dữ liệu thật.
