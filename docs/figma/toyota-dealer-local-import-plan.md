# Kế hoạch import Dealer Toyota vào local

## Mục tiêu
Audit mới database local toyota và snapshot 87 Dealer, lập dry-run từng bản ghi cùng kế hoạch backup/import/rollback/QA. Không INSERT/UPDATE/DELETE, không chạy seeder hoặc restore trong task này.

## Trạng thái
IN PROGRESS — 2026-11-05. Tracking mới, không ghi đè các báo cáo lịch sử.

Cập nhật cuối 2026-11-05: **DONE phạm vi audit, dry-run và kế hoạch**. **CHƯA IMPORT, chưa đủ điều kiện execute**: chờ duyệt snapshot/default active/chính sách 5 dòng tỉnh không khớp; backup mới và restore kiểm chứng sẽ là điều kiện bắt buộc trước ghi dữ liệu ở task tiếp theo. Không có câu lệnh execute trong helper của task này.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, dealer-province-data-audit.md, website-competition-dealer-filters-refinement.md và website-competition-dealer-sync.md. Kết quả 2 Dealer trước đây cần xác minh lại. Git status mới trước khi tạo tracking **sạch**; không dùng trạng thái uncommitted của task trước làm kết luận hiện tại.

## Các file source code liên quan
Dealer model/migrations, ToyotaDealerSeeder/DatabaseSeeder, DealerSlugs, DealerCallPhone, PublicDealerController, DealerDirectoryProvinces, AdministrativeDivisions, Toyota snapshot và danh mục hành chính. Chỉ đọc.

Đã đọc các migration tạo Dealer (000001), active (000007), contact/province (000009), source ID/contact URLs (000012), photo (000017), slug/trigger (000019); đối chiếu cấu trúc database thực tế, không chạy migration. Đọc LeaderboardService/DealerProfile để lập kế hoạch bảo toàn điểm/QR và DealerRegistrationPhase2aDemoSeeder để đối chiếu hai Dealer local.

## Những thay đổi đã thực hiện
Tạo tracking này trước khi lập dry-run mới. Chưa sửa ứng dụng hoặc database.

Các file tài liệu thuộc task import:

- `docs/figma/toyota-dealer-local-import-plan.md` — tracking/kế hoạch này.
- [toyota-dealer-local-import-dry-run.md](toyota-dealer-local-import-dry-run.md) — danh sách đủ 87 dòng nguồn với action, mã, source ID, tên, province giữ nguyên, slug dự kiến và lý do; không ghi đè báo cáo cũ.
- Helper ignored `.laravel-tools/toyota-dealer-import-dry-run.php`, evidence ignored `toyota-dealer-import-dry-run-20261105.json` và `...-recheck.json` cùng thư mục. JSON có `plan[].proposed_values` đầy đủ các giá trị nguồn/address/contact/URLs/opening_hours, schema/index/FK/count và hash; không xuất thông tin tài khoản/password/customer rows.

Trong phiên có yêu cầu độc lập sửa link Thể lệ: chỉ thay href trong competition/navigation; có tracking riêng [header-rules-link-refinement.md](header-rules-link-refinement.md). Thay đổi đó không thuộc import và không ghi database. Không có source import/schema/scoring/privacy được sửa.

### 1. Database hiện tại — bằng chứng mới

Lần đầu 2026-11-05 06:54:10 UTC; kiểm tra lại sau gián đoạn lúc **06:59:16 UTC (13:59:16 giờ Việt Nam)**. App environment **local**, MySQL **8.0.30**, host **127.0.0.1:3306**, `SELECT DATABASE()` trả **toyota**, server hostname `OUSB8MG-PC`. Dùng config cache hiện có, không đổi `.env`, không clear cache và không kết nối production hoặc toyota_testing.

Helper từ chối chạy nếu không phải local/mysql/host loopback/database toyota hoặc có read/write split; kiểm tra actual database rồi mở **START TRANSACTION READ ONLY**, chỉ SELECT/SHOW và ROLLBACK. Không gọi model save/create, Seeder, command migrate/reset hoặc REST POST.

| ID | Mã | Tên | Active | Province | Toyota source ID |
|---:|---|---|---|---|---|
| 1 | DEMO-P2A-ACTIVE | Demo Phase 2A Active | Có | NULL | NULL |
| 2 | DEMO-P2A-INACTIVE | Demo Phase 2A Inactive | Không | NULL | NULL |

Tổng **2 Dealer**, 1 active, không có Dealer nào mang source ID Toyota hoặc mã trong snapshot. Cặp mã/tên/trạng thái khớp source DealerRegistrationPhase2aDemoSeeder; không dùng tên gần giống/địa chỉ để suy tỉnh hoặc ghép sang một Dealer Toyota. Giữ nguyên cả hai; không chạy lại demo seeder. Không coi đây là đủ bằng chứng để xóa dữ liệu phụ thuộc hoặc kết luận mọi khách hàng là demo.

Count hiện tại: users 3; customer_submissions 7; moderation_reviews 4; dealer_registration_receipts 1; awards 0; award_winners 0; submission_edits 0; submission_intake_mutex 1; migrations 27; failed_jobs/password_reset_tokens/personal_access_tokens đều 0. Có **13 bảng, đều InnoDB**. Chỉ kiểm kê count; không phân loại 7 hồ sơ là demo/thật và không xuất dữ liệu cá nhân. Giữ nguyên toàn bộ các bảng này. Snapshot có 87 Dealer là nguồn ngoài DB; không mặc định local hoặc production đang có 87.

**Unique/index thực tế:** PRIMARY(id), unique(code), unique(toyota_source_id), unique(slug), index(is_active). code/name/slug dùng utf8mb4_unicode_ci; kiểm tra mã/tên trùng với local bằng điều kiện bound theo collation DB, không nội suy request vào SQL. Slug NOT NULL, tạo theo DealerSlugs khi create, bất biến ở model và trigger `dealers_immutable_slug` BEFORE UPDATE. Không sửa slug hiện hữu.

| FK tham chiếu dealers.id | Delete rule | Orphan mới kiểm tra |
|---|---|---:|
| users.dealer_id | NO ACTION | 0 |
| customer_submissions.dealer_id | RESTRICT | 0 |
| award_winners.dealer_id | NO ACTION | 0 |
| award_winners.sales_dealer_id_snapshot | NO ACTION | 0 |

Dealer không có outgoing FK trong schema hiện tại. Không tắt FK, không TRUNCATE và không xóa/recreate Dealer để né quan hệ.

### 2. Nguồn dữ liệu — kiểm tra lại

- File `database/data/toyota_dealers.json`, metadata source_url `https://www.toyota.com.vn/lien-he-dai-ly`, retrieved_at **2026-10-04**. Đã đọc/validate snapshot local; chưa xác minh lại nguồn online hoặc trạng thái thực tế hiện hành của từng chi nhánh. Chỉ đề xuất dùng dữ liệu snapshot để phát triển local, không gọi đây là master production hiện tại.
- SHA256 **4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205**. Khai báo và thực tế đều **87 dòng**, 87 source ID và 87 mã khác nhau. Validation PASS cho source/date/count, ID số dương distinct, code/name distinct và độ dài, province/phone/address/opening_hours bắt buộc, các URL http/https nullable. Không có trường bắt buộc nguồn bị thiếu.
- Website thiếu 2, Facebook thiếu 2, Zalo thiếu 5: giữ NULL theo nguồn, không tự tìm/bù URL. Photo không có trong snapshot: không tạo ảnh hoặc đường dẫn giả. Bắt buộc database/model gồm code/name/slug, ID auto increment, active default 1; timestamps theo Eloquent. Source ID guarded phải được gán chủ đích trong importer sau này, không sửa fillable để nới quyền.
- **is_active không tồn tại ở cả 87 dòng nguồn.** Giá trị `true` trong proposed_values chỉ là đề xuất dùng default hiện có của model/schema, cần người dùng duyệt để hiển thị local; không phải thông tin hoạt động đã được Toyota xác minh. Không sửa active của hai Dealer local.
- AdministrativeDivisions đọc snapshot **2026-10-19** model two-tier-2025: 34 tỉnh/3.321 xã. Nguồn danh mục là Open API độc lập, dùng để đối chiếu tên, không chứng minh địa chỉ của từng Dealer. Không dùng Customer/Sales/address parsing để backfill.

### 3. Dry-run và phân loại

| Loại cuối | Số dòng nguồn | Ý nghĩa |
|---|---:|---|
| INSERT | **82** | Chưa có định danh ở local, dữ liệu hợp lệ, nhãn tỉnh có đối chiếu tên trong danh mục; chỉ là dự kiến, chưa được execute |
| UPDATE | **0** | Không có Dealer hiện hữu khớp source ID cần sửa |
| SKIP | **0** | Không có dòng nguồn đã tồn tại và giống dữ liệu |
| CONFLICT | **5** | Định danh là ứng viên INSERT nhưng chính sách nhãn tỉnh chưa được xác nhận |
| Tổng | **87** | Mỗi dòng nguồn thuộc đúng một loại cuối |

Về **định danh**, có 87 ứng viên INSERT và **0 collision mã/source ID/tên với DB**. Năm CONFLICT là review dữ liệu/chính sách tỉnh, không phải báo trùng định danh. Hai demo local là KEEP_UNTOUCHED ngoài nguồn, **không cộng thành 2 SKIP**. INSERT không đồng nghĩa đã duyệt: toàn bộ execute vẫn chờ xác nhận, backup và restore PASS.

| Dòng nguồn | Mã / source ID | Tên | Nhãn tỉnh chưa khớp danh mục |
|---:|---|---|---|
| 11 | TMV-889 / 889 | Toyota Bình Dương | Bình Dương |
| 13 | TMV-1011 / 1011 | Toyota Bình Phước | Bình Phước |
| 40 | TMV-987 / 987 | Toyota Huế | Thừa Thiên - Huế |
| 81 | TMV-567 / 567 | TOYOTA THUẬN AN | Bình Dương |
| 87 | TMV-623 / 623 | Toyota Vũng Tàu | Bà Rịa - Vũng Tàu |

So sánh danh mục chỉ bỏ tiền tố Tỉnh/Thành phố để phát hiện chênh lệch; không chuyển đổi dữ liệu nguồn. Các nhãn còn lại khớp tên không đồng nghĩa đã xác minh tính hiện hành của từng Dealer. Người dùng có thể duyệt **giữ nguyên các nhãn snapshot cho local**, hoặc cung cấp mapping riêng có bằng chứng; không tự đổi/gộp tỉnh. Khi được duyệt giữ nguyên năm dòng này, rerun dry-run có policy duyệt tương ứng mới có thể đổi phân loại sang 87 INSERT. Không tự import 82 rồi bỏ lại 5.

Từng dòng và slug dự kiến ở [báo cáo dry-run](toyota-dealer-local-import-dry-run.md); proposed_values đầy đủ trong JSON ignored tham chiếu source hash. Slug mô phỏng theo quy tắc hiện có, 87 slug khác nhau và không đụng hai slug local; ID DB/timestamps chưa cấp và không đoán trước. Không match gần giống tên; exact tên chỉ phát hiện khả năng xung đột để review, không cấp quyền merge. Nếu có exact mã nhưng thiếu source ID trong lần sau, cũng phải review định danh thay vì tự ghép.

### 4. Backup và xác nhận restore — kế hoạch chưa thực hiện

**Trạng thái hiện tại: chưa tạo backup mới, chưa đọc/restore thử một bản dump cho lần import này.** Không dùng backup hoặc kết quả test lịch sử để cho phép execute. Chỉ xác nhận binary `mysqldump.exe` và `mysql.exe` phiên bản 8.0.30 có trên máy; đó không phải bằng chứng backup thành công.

Trước khi ghi dữ liệu ở task tiếp theo:

1. Kiểm tra lại đúng local toyota, source/plan/row hashes và schema không đổi. Tạm ngừng ghi local trong cửa sổ backup/import để baseline và bản dump nhất quán. Không dùng database production hoặc toyota_testing làm nơi chứa snapshot giao diện.
2. Tạo thư mục backup **mới** ngoài public, ví dụ `.laravel-tools/backups/toyota-local/<UTC>-<uuid>/`, xác nhận Git ignore và quyền truy cập trước ghi; không ghi đè backup cũ. Credentials dùng defaults-extra-file riêng ngoài public, không in password vào command/log/Git. Đọc cấu hình hiện hành mà không đổi `.env` hoặc app key.
3. Dump toàn bộ `toyota` bằng mysqldump, bind host/port rõ ràng, `--single-transaction --quick --routines --events --triggers --hex-blob --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4`; dùng `--result-file` để tránh PowerShell biến đổi encoding. Không dùng option bỏ dữ liệu; kiểm tra exit code/stderr/quyền routines/events. Không dùng `--databases toyota` hoặc câu USE trỏ nhầm local khi dự định restore clone.
4. Kiểm tra file tồn tại, dung lượng, UTF-8/header/trailer và đủ CREATE/data tương ứng cả 13 bảng, trigger và các routines/events nếu có; tạo SHA256, count/schema manifest. Với bảng rỗng không yêu cầu INSERT giả. Không chỉ nhìn dung lượng rồi kết luận restore được.
5. **Restore thử vào instance/database cô lập được xác nhận riêng**, không ghi lại toyota, không dùng production/toyota_testing đang phục vụ PHPUnit. Kiểm tra không có câu schema-qualified/USE/event trỏ về DB gốc; restore không được kích hoạt event gây ghi ngoài clone. Thực hiện bước có ghi này chỉ sau quyền phù hợp của task tiếp theo, không chạy trong audit hiện tại.
6. So sánh đủ schema/index/FK/trigger, table counts và hash các row gốc (đặc biệt hai Dealer, users, submissions/reviews/receipt/awards) trên clone, kiểm tra orphan; lưu evidence restore PASS và checksum dump. Không kiểm chứng được restore thì **dừng import**. Không coi transaction rollback hoặc JSON Dealer export là backup toàn DB.

Không chạy dump/restore, không tạo database clone trong task này. Không cung cấp lệnh restore trực tiếp lên toyota ở báo cáo audit.

### 5. Kế hoạch import sau phê duyệt

1. Duyệt source hash, mục đích dữ liệu snapshot local, default active và cách xử lý năm tỉnh trên; rerun dry-run, review mọi delta và xử lý CONFLICT trước. Nếu DB/source/schema khác bản duyệt, lập lại plan; không force dùng plan cũ.
2. Xây dựng importer hẹp trong task được phép tiếp theo: mặc định dry-run, execute xác nhận rõ ràng đúng host/database/hash/bản duyệt, yêu cầu backup+restore evidence; không tự gắn vào deploy/migrate/boot. Helper audit hiện tại **không phải command execute**.
3. Import có transaction InnoDB, lock baseline Dealer và cơ chế ngăn hai importer chạy song song; recheck schema/unique/hash trước ghi. Match exact source ID trước, mã/slug là kiểm tra xung đột; không fallback theo tên để merge. Dùng placeholder/bound query, giữ FK checks, không TRUNCATE hoặc reset auto_increment. Unique constraints là lớp chặn race bổ sung; nếu collision/exception thì rollback toàn batch.
4. Chỉ tạo Dealer từ mapping đã duyệt; dùng Dealer creation để sinh slug ổn định theo model, gán source ID chủ đích, không tạo Manager/Sales/Admin hoặc submission mới. Phone/contact lưu đúng nguồn, **không sửa approved_call_phones** hoặc tự cấp quyền công khai; không dùng phone Sales. Photo/nullable URLs giữ thiếu theo nguồn. Không sửa hai demo, bất kỳ row users/submissions/reviews/receipt, số điểm, attribution hoặc award snapshot.
5. Rerun: cùng source ID và cùng các field đã duyệt thì SKIP, giữ nguyên ID/slug/timestamps. Nếu có khác biệt trên row đã tồn tại mà không có baseline được duyệt, chuyển CONFLICT; không ghi đè chỉnh tay. UPDATE chỉ cho phép với field diff được xác nhận riêng. Không gọi nguyên ToyotaDealerSeeder vì nó có fallback code/tên và refresh nhiều field, cũng không chạy DatabaseSeeder tổng/demo/reset.
6. Postcondition trước commit: các Dealer cũ hash không đổi; các bảng nghiệp vụ không thay đổi; mới đúng số dòng đã duyệt, unique source/code/slug và không orphan. Nếu duyệt đủ 87 mới, tổng dự kiến **89 Dealer, 88 active**, trong đó hai demo vẫn nguyên trạng. Đây là dự kiến có điều kiện, không phải số liệu sau import. Chưa duyệt tỉnh/active thì không tự import một phần.

LeaderboardService aggregate chỉ có Dealer có approved submission hợp lệ; Dealer mới không có submission sẽ score/members_count=0 và rank NULL ở B14/B15. Thêm Dealer không tự tạo thứ hạng hoặc sửa công thức; phải kiểm tra lại baseline sau import. Không dùng số lượng submissions hoặc tài khoản mới để mô phỏng điểm.

### 6. Rollback

- Exception trước commit: rollback transaction, không cập nhật dữ liệu ngoài transaction; auto_increment có thể có gap và không được reset chỉ để khôi phục dãy ID.
- Sau commit: lưu receipt/IDs/source hash/row hashes của các row mới. Trước bất kỳ thao tác hoàn tác nào, ngừng ghi local và đánh giá có account/submission/award hoặc chỉnh tay mới tham chiếu các Dealer vừa thêm hay không. Nếu có, dừng review, không xóa cascade hoặc tắt FK.
- Nếu vẫn không có phụ thuộc/mutation mới, task rollback được phê duyệt riêng mới được xóa đúng các row mới có hash khớp receipt; không đụng hai demo hoặc row sẵn có. Nếu cần khôi phục toàn DB từ backup đã test, restore clone để đối chiếu trước; restore đè toyota sẽ mất thay đổi sau backup nên phải được duyệt rõ ràng. Không thực hiện bất kỳ rollback dữ liệu nào trong audit này.

### 7. Kế hoạch kiểm thử khi import được phép

| Kiểm tra | Môi trường / điều kiện PASS |
|---|---|
| Counts/unique/FK | local toyota sau import: đủ các dòng được duyệt, dự kiến 89/88 active nếu đủ 87; source/code/slug không trùng, orphan 0 |
| B14 đầy đủ/pagination | local GET: 88 active dự kiến trên 5 trang 20/page, không mất/trùng; inactive demo vẫn ẩn; q/province/sort/direction giữ đầy đủ |
| Province/search | local GET: đúng nhãn **đã được duyệt**, các tỉnh cũ không bị âm thầm đổi; tìm tên/tỉnh, combined filter và empty hợp lệ |
| Ba sort/hai chiều | local GET: members chỉ approved, name/rank theo contract; global rank không reset theo sort/page |
| B15 | local GET cho Dealer mới/cũ: HTTP 200, tên/contact được phép, thiếu photo có fallback; ID sai 404 |
| Điểm/attribution/lịch sử | baseline hash và projection của Dealer/Sales cũ trước/sau không đổi, award snapshot không đổi; row mới score 0/rank NULL khi chưa approved |
| Phone/privacy | B14 không expose phone; B15 không tel/display số chưa approved exact slug+normalized phone, không tự sửa approval/config hoặc lộ customer/internal fields |
| D01/QR | local GET landing/QR của Dealer cũ giữ URL/slug; Dealer mới mở đúng D01 từ B15/QR, không POST hồ sơ vào local chỉ để test import |
| Responsive | local toyota: screenshot B14/B15 tại 320/390/768/1024/1440/1920; kiểm tra province/search/sort/pagination/navigation/footer và tên dài |
| Tests có ghi | Chỉ toyota_testing sau isolation preflight; test importer transaction/rerun/race/conflict/stale-plan/hash/rollback và bộ DealerDirectoryFilters/DealerProfile/DealerSlug/LandingRegistration/Leaderboard/Home/Awards/PublicPrivacy/Registration/Lookup liên quan |

Không chạy PHPUnit ghi dữ liệu vào toyota để làm QA UI. Không coi build PASS là visual QA. Không dùng test lịch sử hoặc fixture ảnh để thay cho browser local sau import.

## Quyết định kỹ thuật quan trọng
Chỉ local toyota trên localhost/127.0.0.1, không production/toyota_testing. Không match gần giống tên hoặc chuẩn hóa tỉnh chưa xác nhận. Snapshot là dữ liệu tham chiếu local, không chứng minh dữ liệu production hiện hành.

Audit này khác task backfill trước: mục tiêu tương lai là thêm 87 Dealer riêng, không tìm tỉnh cho hai demo. Giữ kết luận và lịch sử dealer-province-data-audit.md; bổ sung dry-run mới có metadata/schema và plan từng dòng, không sửa báo cáo cũ để biến fixture thành Dealer thật. Mọi trạng thái execute trong báo cáo là đề xuất có điều kiện, không phải quyền thực thi.

## Kết quả kiểm thử
- **PASS mới:** `php -l .laravel-tools/toyota-dealer-import-dry-run.php`.
- **PASS mới:** `php .laravel-tools/toyota-dealer-import-dry-run.php`; guard local/actual toyota, READ ONLY transaction; source validation, 87 dòng được phân loại, 87 mã/source/slug dự kiến duy nhất, constraints/FK/orphan và count kiểm tra mới.
- **PASS mới sau gián đoạn:** recheck 06:59:16 UTC, source hash/Dealer hash/toàn bộ plan giống lần đầu; table counts trước/sau không đổi. SHA256 toàn bộ rows Dealer trước/sau **03a2ec4e7a43ca3fd1ec651483b96f38be673b74a35feb181d5546656205d900**.
- **PASS mới:** Git diff --check và check-ignore helper/JSON; Git status đầu task sạch. Lệnh Git sandbox có lúc bị dubious ownership, chỉ chạy lại lệnh đọc được duyệt, không đổi safe.directory/policy. Artifact không có credentials, không ở public và không đưa vào Git.
- **PASS mới, chỉ kiểm tra công cụ:** mysqldump/mysql version 8.0.30. **Không phải** backup/restore PASS.
- **CHƯA KIỂM CHỨNG:** backup mới, restore, importer execute/rollback/idempotency trên DB, UI với 87 Dealer, nguồn online hiện hành, approved phone của Dealer sau import. Không chạy build/PHPUnit/browser cho task audit không đổi UI; kết quả 284 test và screenshot trong tracking trước không tính là lần chạy mới.

## Những lỗi còn tồn tại
Không có lỗi truy vấn/validation mới; còn 5 CONFLICT về chính sách tỉnh, thiếu active flag ở cả 87 dòng và chưa có backup/restore được kiểm chứng cho lần import. Không thể tuyên bố đã sẵn sàng execute hoặc UI đã dùng đủ Dealer. Hiện local vẫn chỉ có 2 Dealer, cả hai province NULL; không tự tạo dữ liệu bổ sung để làm filter đầy đủ.

## Task hoặc bước tiếp theo
Hoàn tất báo cáo; chờ phê duyệt dry-run trước mọi import trong task tiếp theo.

Đã hoàn tất audit/kế hoạch. Bước tiếp theo cần người dùng duyệt rõ snapshot/hash, chính sách giữ nguyên hoặc mapping có bằng chứng cho 5 dòng tỉnh và default active; sau đó thực hiện task mới gồm backup+restore verification, importer có guard, execute local được phép và QA. Chưa có quyền ghi dữ liệu; không tiếp tục import tự động trong phiên này.
