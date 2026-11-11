# P09I — B Admin Call Audit Read API

## Mục tiêu

GET Admin đọc batch/items đã freeze, lọc purpose/month, counts theo evidence thật, snapshots/phone masking và phân quyền; không mutation/claim/resampling/UI.

## Trạng thái

DONE phạm vi read API — 2026-11-11: **34 tests/444 assertions PASS**, không SKIPPED/FAIL (21 HTTP read API +13 policy). Baseline V1 được người dùng được TMV ủy quyền phê duyệt; A1/A2/A3 giữ nguyên. Không nghiệm thu UI hoặc workflow ghi cuộc gọi.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS, P09B mục9/P09G/P09H và riêng C08 inventory. C08 queue có tên/Dealer/SĐT dạng 09****321, tabs Cần gọi/Gọi lại/Đã xong, metric Đã gọi. Chưa có contract/action ghi attempt/retry/terminal; không suy completed hay tỷ lệ Sai từ mock. Working tree có task trước chưa commit, không ghi đè.

## Các file source code liên quan

- Mới `app/Http/Controllers/Admin/CallAuditController.php`, `app/Http/Requests/Admin/CallAuditReadRequest.php`.
- Mới `app/Services/CallAudit/BatchReadModel.php`, `ReadUnavailable.php` (cùng thư mục).
- Sửa `routes/web.php`: import controller và đúng4 GET routes trong group Admin hiện hữu.
- Mới `tests/Feature/AdminCallAuditReadTest.php`, `tests/Fixtures/call_audit_read_snapshot.php` và tracking này.
- A1 policy/fixtures cleanup dùng lại, không sửa schema/A2/A3/approval/verification/scoring/AwardResults/public snapshots hoặc views/sidebar. Entry C08 sidebar vẫn null/disabled, không tự mở UI bằng API route.

## Những thay đổi đã thực hiện

Đã triển khai index/detail/items/scoped-item read endpoints, explicit projection từ persisted records, pagination và counts/Dealer summaries. Controller trả JSON allowlist; không serialize Eloquent paginator/models, không tạo pagination links chứa request URL/query thừa. Chỉ decrypt những snapshot cần để lấy tên/masked phone/Dealer cho phép, không query lại CustomerSubmission/Dealer/Award live để dựng nội dung.

Request/service kiểm active Admin và policy mỗi request, requery role/status từ DB thay vì tin user object cached; purpose bắt buộc, child item scope qua batch đã authorize. Thêm safe409 khi snapshot không đọc được/không khớp item hoặc khi filter terminal/retry chưa có contract; không log private exception/ciphertext.

## Quyết định kỹ thuật quan trọng

Purpose là scope bắt buộc; month optional để lọc lịch sử, mismatch scope/child ID trả404. Payload allowlist, không model serialization hoặc pagination URLs chứa query thừa. Names/Dealer/masked phone từ frozen snapshots. Called=count distinct items có attempt; chưa có attempt không fake completion/recall/ratio. Counts của recall/completed chưa xác định nếu có attempts chưa có contract C, không đoán theo projection.

### GET contract V1

| Route/name | Query hợp lệ | Nội dung |
|---|---|---|
| `/admin/call-audits` — admin.call-audits.index | purpose bắt buộc; month/page/per_page optional | Batch frozen theo purpose, tháng giảm dần → generation giảm dần → ID giảm dần; counts/progress mỗi batch riêng |
| `/admin/call-audits/{batch}` — .show | purpose bắt buộc, month optional | Metadata allowlist + progress + Dealer summaries; không khách/seed/manifest thô |
| `/admin/call-audits/{batch}/items` — .items | purpose bắt buộc; month/page/per_page/tab optional | Selected items theo selection_order → ID; default tất cả items, tab scoped cùng batch; labels Cần gọi/Gọi lại/Đã xong đúng inventory |
| `/admin/call-audits/{batch}/items/{item}` — .item | purpose bắt buộc, month optional | Cùng projection an toàn của selected item, luôn query từ batch relationship |

Purpose chỉ general_audit/award_top3; month YYYY-MM; per_page mặc định20/max50, page min1/max100000. Unknown query keys bị từ chối mà không echo key/value. Không hỗ trợ phone/name/search/export qua query; URL chỉ có ID audit/purpose/month/tab/page. Mismatch purpose/month/non-frozen batch/child thuộc batch khác →404. Không có mutation routes (Laravel tự có HEAD tương ứng GET).

Mọi active Admin đọc được batch của creator khác đúng policy V1. Guest401, Sales/Dealer và account quản lý Dealer/Manager theo role dealer403, Admin pending/rejected/blocked403. Source enum chỉ có admin/dealer/sales, không tự thêm role Manager hoặc capability mới. Middleware auth/auth.session/role:admin/account.active/throttle hiện hữu giữ nguyên; FormRequest và service còn recheck fresh user/policy server-side.

### Projection, counts và phần chờ workflow C

- Batch: id/purpose/month/generation/frozen_at/requested_count/actual_count + progress, không cộng tổng hai purpose. Pagination meta chỉ current_page/per_page/total/last_page, không URL/links. Chỉ đọc lifecycle frozen có frozen_at; lifecycle tương lai ngoài V1 cần contract riêng.
- Item: audit item ID, selection_order, customer name, Dealer ID/name và masked phone từ frozen snapshots. Mask đúng dạng inventory C08 `09****321`:2 chữ số đầu +4 dấu* +3 số cuối sau QR/C03 normalization; không valid/alias-conflict thì null, không phát minh số/label. Không trả submission ID, full phone, Facebook, biển số, địa chỉ, source snapshot, raw key/seed/manifest/fingerprint/digest hoặc private Sales baseline.
- Item snapshot phải khớp submission/dealer ID nội bộ trước projection; snapshot bị hoán đổi giữa items hoặc ciphertext hỏng →409 `audit_read_unavailable`, không leak private data/stack exception. Header cache private/no-store. Controller không logging input, source snapshots hoặc arbitrary query.
- called_count = **số selected items có ít nhất1 attempt persist**, không số attempts, queue projection, claim hay bằng chứng nghe máy. needs_call_count = số items chưa có attempt. Kiểm tổng items khớp actual_count trước trả counts; mismatch409, không dùng target60/15 làm số khách hiện có.
- Không attempts: tabs needs_call=N, recall=0, completed=0; item state needs_call, is_complete=false kể cả N=0. Strings queue_state/attempt_count/latest_outcome dù mang completed/wrong cũng không tạo kết quả khi chưa có attempt thật.
- Nếu có attempt nhưng C chưa cung cấp terminal/retry mapping: called/needs_call vẫn có định nghĩa thật; recall_count/completed_count=null, item có attempt state=null và is_complete=false (chưa có chứng cứ hoàn tất). Default list vẫn đọc tất cả selected; needs_call lọc đúng no-attempt; filter recall/completed trả409 thay vì bịa trạng thái. API này **không** suy outcome từ strings tự do trong schema, không biến một attempt thành completion.
- Dealer actual_count/progress từ persisted items/attempt existence của chính batch. C08 requested_count theo Dealer=null vì không Dealer quota. C07 requested_count từ frozen stratum (5), kể cả stratum0 selected; Dealer name từ baseline snapshot cho các stratum0, không lấy directory live hoặc private Sales data. Wrong ratio luôn null khi chưa có contract kết quả; không hiển thị0% như đã kiểm.
- Giải mã server-side chỉ để tạo projection được phép theo yêu cầu read/Figma queue, không cấp raw viewSensitiveData/reproduce hoặc full-phone/script disclosure. Không thêm text/badge/section UI ngoài thiết kế; error token và field keys là contract máy, không wording UI mới.

Counts read trong DB transaction, Dealer tổng hợp một lượt từ item metadata/attempt existence, tránh gọi progress queries lặp theo từng Dealer. Index không select ciphertext baseline/strata; không endpoint nào select seed/manifest hoặc resolve sampler/freeze action. Không calls/claim/retry/mutation hoặc auto-demo records.

## Kết quả kiểm thử

| Kiểm tra vừa chạy | Kết quả |
|---|---|
| `php tests/Fixtures/call_audit_read_snapshot.php before` | PASS local127.0.0.1:3306/toyota_testing, MySQL8.0.30, fk_checks1, audit0 rows; snapshot19 bảng và199 source/migrations được bảo vệ |
| `php vendor/bin/phpunit tests/Feature/AdminCallAuditReadTest.php tests/Unit/CallAuditBatchPolicyTest.php --no-progress --display-skipped` | **34 PASS/444 assertions**, không SKIPPED/FAIL; không full regression |
| `php -l` và `php vendor/bin/pint --test` đúng7 PHP mới/sửa, gồm routes/web.php | PASS; không formatter toàn working tree cũ |
| `php tests/Fixtures/call_audit_read_snapshot.php after` | PASS audit_rows=[0,0,0,0], changed_tables=[], source_changed=false |
| Diff/whitespace và tracking | PASS đúng8 file mới/sửa, đủ9 mục tracking; Git status chỉ thêm7 file mới, không mất/tăng trạng thái file cũ; routes/web.php đã có thay đổi phase trước được giữ và chỉ thêm import/4 GET cho P09I. Mọi fixture journal dùng lại đều cleaned=true |

Coverage:4 GET endpoints với guest/Sales/Dealer/Manager account/inactive Admin; active Admin khác creator; stale active Auth object nhưng DB đã blocked; scope purpose/month và child IDOR; batch/item stable pagination; no pagination URLs; masked phone/frozen name/Dealer khi live data đổi; no private Sales/seed/manifest/snapshots; empty/underfill/C07 stratum0; fake queue projections; attempt thật không trộn sang purpose khác; chưa có mapping không fake done/ratio; unknown queries không echo/log PII; swapped/unreadable snapshots fail closed; routes chỉ GET/HEAD.

Case GET-no-mutation bật SQL guard chỉ cho read, bind sampler/freeze action để throw nếu resolve, kiểm query log không đọc live source và không fetch seed/manifest; checksum/row count19 bảng trước/sau các GET khớp. Không chỉ dựa rollback để gọi GET read-only.

Tests dùng bootstrap/guard và CallAuditFreezeFixtures/cleanup ID-tag của P09H. Dữ liệu test tạo/freeze bằng backend thật trong giai đoạn chuẩn bị fixture, không phải GET. Một attempt chỉ được insert trực tiếp như fixture để chứng minh không bịa mapping; không thêm action record hoặc mutation route. Test xóa đúng records fixture theo dependency; không đụng data khác.

Process env: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE/TOYOTA_TEST_DATABASE=toyota_testing; DATABASE_URL/DB_URL/DB_SOCKET rỗng; APP_CONFIG_CACHE absolute `tests/Fixtures/.p09i-disabled-config-cache.php` không tồn tại. Không sửa .env/cache, không production/migrate/seed/reset/build/UI.

Evidence private/ignored: `storage/app/private/p09i-call-audit-read/{before,after}.json` và fixture journals dùng lại. Data/count/checksum/schema19 bảng khớp trước/sau, chỉ normalize AUTO_INCREMENT counters vì fixture commit/rollback; không reset counters. Hash199 source/migrations có sẵn giữ nguyên; chỉ routes GET và file read API mới thay đổi source trong phạm vi này.

## Những lỗi còn tồn tại

Không có test/read API FAIL còn tồn tại. Recall/completed semantics khi có attempts, full phone/script disclosure và UI/call actions thuộc task C/D sau; không coi read API đã nghiệm thu workflow gọi điện. Chưa benchmark index pagination qua nhiều năm batch; GET vẫn có bounded pagination. Không sửa schema hoặc source nghiệp vụ cũ.

## Task hoặc bước tiếp theo

Review contract rồi giao C ghi attempt/retry/terminal transition với quyền, evidence và definitions được chốt; sau đó UI C08 task riêng. Khi C có contract thật mới thay null/409 tương ứng và cập nhật tests; không tự mở freeze/claim/create UI hoặc mutation từ task P09I.
