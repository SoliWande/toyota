# Backup, restore verification và import Toyota Dealer local

## Mục tiêu
Import đủ 87 Dealer từ snapshot đã duyệt vào local toyota sau backup/restore PASS; giữ hai Dealer và toàn bộ dữ liệu nghiệp vụ cũ; QA B14/B15 và regression cách ly.

## Trạng thái
IN PROGRESS — 2026-11-05, tracking mới tạo trước mọi thao tác ghi database của task này.

Cập nhật cuối 2026-11-05: **DONE phạm vi được phê duyệt**. Backup/restore verification PASS; local đã commit đủ87 Dealer và hậu kiểm dữ liệu, B14/B15 live,36screenshot/6viewport và regression292tests PASS. Không deploy hoặc ghi production.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, plan/dry-run, audit province, dealer-sync và filters-refinement; đối chiếu Dealer/model/migrations/Seeder/Slugs/CallPhone/controllers/QR/Leaderboard. Người dùng đã duyệt source SHA256 4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205, đủ 87 Dealer, giữ nguyên nhãn province kể cả 5 dòng từng CONFLICT và active=true cho 87 mới. Chỉ được ghi local sau mọi guard/backup/restore PASS.

Preflight mới: APP_ENV local, mysql 8.0.30, 127.0.0.1:3306, actual toyota, config cached; 2 Dealer, schema/FK/count và Dealer hash trùng plan trước. Không đổi env/key/cache. Git có thay đổi header Thể lệ và tài liệu plan từ task trước, giữ nguyên.

## Các file source code liên quan
Dealer, DealerSlugs, DealerCallPhone, PublicDealerController, LeaderboardService và migration Dealer chỉ đọc; sẽ bổ sung importer/command/tests và QA phù hợp. Không dùng ToyotaDealerSeeder/DatabaseSeeder.

## Những thay đổi đã thực hiện
Tạo tracking; đang xác minh cache/instance/schema và chuẩn bị backup/restore. Chưa import.

### Cập nhật thực thi 2026-11-05

- Thêm `app/Services/LocalToyotaDealerImport.php`, command `app/Console/Commands/ImportLocalToyotaDealers.php`, `tests/Feature/LocalToyotaDealerImportTest.php` và QA `docs/figma/qa/local-dealer-import-visual.mjs`.
- Không sửa Dealer/model/slug/scoring/approval/controller/UI/migration, `.env`, app key, config cache, phone approval hoặc snapshot nguồn. Thay đổi header Thể lệ và tài liệu plan/dry-run có sẵn từ task trước được giữ riêng, không tính là source import mới.
- Helpers/evidence/dump/clone manifests ở `.laravel-tools` ignored ngoài public; screenshot mới ở `docs/figma/qa/artifacts/local-dealer-import-20261105/`, không đè baseline cũ.

### Môi trường và dry-run cuối

Đã so kết nối **cached** và đọc cấu hình **uncached** bằng APP_CONFIG_CACHE riêng cho process chỉ đọc, không ghi/cache-clear cấu hình: cả hai APP_ENV=local, mysql, 127.0.0.1:3306, actual toyota, server UUID `6ae1e427-7569-11ef-8969-e0be03927d3d`, host OUSB8MG-PC. Không dùng toyota_testing để chứa dữ liệu UI. Không kết nối production.

Dealer hash trước import vẫn `03a2ec4e7a43ca3fd1ec651483b96f38be673b74a35feb181d5546656205d900`; 2 Dealer (1 active/1 inactive), 3 users, 7 customer_submissions, 4 moderation_reviews, 1 dealer_registration_receipts; awards/award_winners/submission_edits rỗng. 13 bảng InnoDB, migrations count27; schema Dealer/FK/count trùng plan đọc trước. Toàn bộ schema/dữ liệu hiện tại được chụp hash riêng cho backup.

Source SHA256 đúng `4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205`, 87 dòng hợp lệ, mã/source/slug dự kiến không trùng. Sau chính sách province/raw và active đã duyệt, dry-run mới **INSERT87 / UPDATE0 / SKIP0 / CONFLICT0**, lưu đủ từng giá trị dự kiến trong manifest backup. Không merge theo tên hoặc đổi các tỉnh cũ. `php artisan toyota:import-local-dealers` mặc định chỉ đọc.

### Backup/restore thực tế

Thư mục thành công (đường dẫn tương đối repository):

`.laravel-tools/backups/toyota-local/20261105-072148-3a57bf8d8b33/`

- Backup `toyota-before-import.sql`, **35.883 byte**, SHA256 **4bc6a6c30eec1d16f7ad43c028c5f44c412a2661c39bf13dbc05bdb2297301ed**.
- Manifest `manifest.json`: binding/source/plan hashes, table counts/data/schema hashes, object summary, dung lượng/checksum/thời điểm và restore evidence. Có 13 bảng, 3 trigger, 0 routine, 0 event; không bỏ object tồn tại.
- Dùng mysqldump/mysql8.0.30 với single-transaction/quick/routines/events/triggers/hex-blob/no-tablespaces/GTID OFF/column-statistics0. Credentials chỉ ở defaults-extra-file private tạm, không in console/Git, được gỡ sau khi các client kết thúc. Backup/receipts được Git ignore; HTTP kiểm tra đường dẫn dump qua public root trả404.
- Khóa READ toàn bộ bảng gốc trong quá trình baseline/dump/restore; không có connection toyota đang hoạt động khác lúc kiểm tra. Tạo clone qua connection riêng đã kiểm tra cùng instance, vì MySQL không cho CREATE DATABASE trên connection giữ table locks. Kiểm tra dump ở cấp SQL token: chặn USE/CREATE-DROP-ALTER DATABASE/tham chiếu schema toyota/LOAD DATA/SOURCE/CONNECT, không nhận nhầm URL trong string thành tên database.
- Clone thành công **`toyota_restore_20261105_072148_e49aeb51`**, giữ nguyên để đối chiếu. Restore bằng mysql client chỉ đích clone, không ghi toyota hoặc toyota_testing. Các SET phục vụ dump/restore chỉ ở connection clone; importer/original luôn kiểm tra foreign_key_checks=1, không tắt FK để insert.
- **Restore PASS**: cùng counts và hash từng row-set của cả13 bảng; cùng schema canonical, metadata cột (type/default/null/charset/collation/generated), indexes, FK/actions, engine/table collation/AUTO_INCREMENT và hashes 3 trigger/0 routine/0 event. Không chỉ dựa exit0 hoặc kích thước dump.

#### Các lần dừng an toàn trước bản PASS (giữ lịch sử)

1. Helper chưa đọc được JSON evidence PowerShell có UTF-8 BOM; dừng trước tạo backup/ghi DB, sửa parser chỉ bỏ BOM.
2. Dump bị scanner cũ nhận nhầm URL toyota.test là `toyota.` schema. Dừng trước clone/import; sửa scanner phân biệt literals/comments và SQL tokens, vẫn chặn SQL cross-schema. Giữ dump cũ, không overwrite.
3. MySQL1192 từ chối CREATE DATABASE trên connection giữ READ locks. Không có clone hoặc import ở lần này; chuyển thao tác tạo clone sang connection riêng cùng binding, giữ khóa gốc.
4. Clone `toyota_restore_20261105_071955_76e1ea00` có đủ counts/data hashes và objects, nhưng hash SHOW CREATE thô khác vì MySQL in thêm CHARACTER SET utf8mb4 trước COLLATE utf8mb4_unicode_ci sau restore. Dừng import, giữ clone và `restore-failed.json` trong thư mục `20261105-071955-7c4b7518d17c`. Chỉ canonicalize charset dư thừa khi collation xác định chính charset đó; không bỏ khác biệt type/collation/index/FK. Thêm test chứng minh type/collation/engine khác vẫn không khớp, đồng thời đối chiếu metadata độc lập gồm AUTO_INCREMENT. Bản backup/clone mới ở trên chỉ được PASS sau mọi kiểm tra đó.

Không xóa dump/clone thất bại hoặc sửa dữ liệu gốc để làm restore PASS. Không dùng lần thất bại làm chứng cứ cho execute.

### Importer và transaction thực tế

Command mặc định dry-run; execute yêu cầu chuỗi confirm đúng toyota:87:source-hash, file evidence thực trong private backup root, backup SHA256, binding/schema/data/plan hashes và restore PASS. Kiểm tra lại clone hiện tại trước execute. Không có schedule/deploy hook hoặc quyền execute production/testing của command.

GET_LOCK chống hai importer đồng thời; transaction InnoDB REPEATABLE READ khóa mọi rows/range gaps trên các bảng baseline bằng FOR UPDATE, kể cả bảng rỗng, để chống ghi cạnh tranh. Recheck baseline/plan sau lock; kiểm tra code/source/slug ngay trước từng insert và để unique indexes chặn race bổ sung. Tạo Dealer bằng model/DealerSlugs, gán source ID chủ đích; không UPDATE/DELETE, không tạo tài khoản hoặc submission. Không bỏ qua lỗi giữa batch.

Đã chạy **đúng một execute trên local**:

```powershell
php artisan toyota:import-local-dealers --execute --evidence=F:/laragon/www/toyota/.laravel-tools/backups/toyota-local/20261105-072148-3a57bf8d8b33/manifest.json --confirm=toyota:87:4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205
```

Đường dẫn ở lệnh là evidence thực của máy hiện tại, không được copy làm lệnh thực thi ở máy khác. Không chạy execute lần hai.

Kết quả **COMMITTED 87 INSERT / 0 UPDATE / 0 SKIP / 0 CONFLICT / 0 DELETE**; Dealer ID mới **3–89**. Receipt cuối `import-receipt-75d1ca8f8223.json` trong thư mục backup thành công: source/binding/backup/restore/time/duration, 87 id/source/code và before=NULL/new raw-row hash, toàn bộ table hashes trước/sau. File pending `import-receipt-pending-c7757a8c9dd9.json` là chứng cứ precommit, receipt COMMITTED mới là kết quả cuối; giữ cả hai. Nếu receipt sau commit lỗi thì không tự rerun/rollback phá hủy.

Precommit so sánh Dealer cũ và toàn bộ bảng nghiệp vụ/objects cùng projection AllTime Leaderboard bằng nhau; nếu không thì command rollback. Sau import chỉ chạy lại **dry-run**, kết quả INSERT0 / UPDATE0 / SKIP87 / CONFLICT0, không tạo thêm row. Hành vi rerun/collision/rollback đã test cách ly.

### Hậu kiểm dữ liệu thật

Evidence `postcheck.json` cùng thư mục backup, helper chỉ đọc `.laravel-tools/local-import-postcheck.php` (READ ONLY transaction):

| Kiểm tra | Kết quả |
|---|---|
| Dealer trước/thêm/sau | 2 / 87 / **89** |
| Active/inactive | **88 / 1** |
| 87 source ID/code/slug | Đúng định danh, không trùng |
| Giá trị nguồn của cả87 | So sánh từng field raw bằng snapshot, province giữ nguyên100%, active=true, photo=NULL |
| Hai demo cũ | Hash raw rows không đổi |
| Users/submissions/reviews/registration receipts/awards và các bảng khác | Hash dữ liệu/schema và count không đổi |
| FK orphan | 0 ở users.dealer_id, submissions.dealer_id, cả2 FK award_winners |
| Dealer mới | Không submission, score/members0, rankNULL, không tạo điểm/hạng giả |
| Approved phone Dealer mới | 0; không sửa allowlist, B15 không render phone/tel/call |

Demo active cũ vẫn có điểm2/hạng1 từ dữ liệu trước import, không bị reset hoặc thay bằng dữ liệu mới. Đây không phải lỗi import và không xóa/sửa các hồ sơ đó.

### B14/B15 và responsive — dữ liệu local đã import

QA dùng HTTP Laravel thật tại127.0.0.1:8771 với config local toyota. **Không route interception, không DTO/fixture HTML**.

- B14: đủ88 active IDs trên5trang20/page (trang5 có8), không trùng/mất, inactive ID2 không xuất hiện. Options lấy đủ34 nhãn province raw từ nguồn, có5dòng tỉnh cũ đã duyệt. Search Toyota + Hồ Chí Minh trả16 dòng; native select/toggle/search giữ state; pagination giữ query/sort/direction. Sáu sort directions không đổi rank toàn kỳ.
- B15: kiểm tra GET của cả87 Dealer mới HTTP200, đúng tên/mã/địa chỉ, score0/chưa hạng, photo fallback, không call/tel chưa approved. CTA mở D01, QR endpoint200, ID sai404. Kiểm tra thêm Home/Leaderboard/Awards/landing demo cũ/lookup demo cũ HTTP200; Mobile menu mở trang Thể lệ đúng.
- **36 screenshot**: B14 mặc định/trang cuối/filter tỉnh cũ/empty, B15 tên dài nhất và D01 ×320/390/768/1024/1440/1920. Không overflow/clipping, footer sau main, không lỗi console/broken image/asset HTTP errors. Inter dùng cho Dealer; probe riêng Montserrat Vietnamese600/700 để phân biệt font chưa được dùng nên chưa tải với lỗi font. Không sửa CSS/font để xử lý dữ liệu.
- Đã xem trực quan B14 Desktop1440/Mobile390 và B15 tên dài Desktop1440/Mobile320: tên/địa chỉ xuống dòng, CTA/footer sau nội dung, không cắt dấu tiếng Việt. Không sửa UI hoặc tuyên bố pixel-perfect Figma mới trong task dữ liệu.
- Evidence: `docs/figma/qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/evidence.json`; smoke khác `.laravel-tools/local-import-public-smoke.json`.

Ảnh: [B14 Desktop](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/directory-1440.png), [B14 Mobile](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/directory-390.png), [B14 trang5](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/directory-last-390.png), [tỉnh giữ nhãn cũ](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/legacy-province-390.png), [B15 Desktop](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/profile-long-1440.png), [B15 Mobile320](qa/artifacts/local-dealer-import-20261105/screenshots/local-dealer-import/profile-long-320.png).

## Quyết định kỹ thuật quan trọng
Tỉnh giữ 100% nguồn và active=true đã được duyệt, không còn conflict chính sách đó. Command execute chỉ local toyota; backup/clone/receipt ngoài public và ignored; transaction, lock, hash, không update/delete cũ hoặc ghi bảng nghiệp vụ khác. Không tự xóa clone hoặc restore đè dữ liệu sau commit.

## Kết quả kiểm thử
Preflight chỉ đọc PASS; backup/restore/import/visual/PHPUnit chưa chạy.

### Kết quả mới của task này

- Isolation preflight **PASS actual toyota_testing,15 tables, không config cache local**. APP_CONFIG_CACHE chỉ trỏ file nonexistent riêng trong process tests; không thay env/config của ứng dụng. Bootstrap hiện có đặt RefreshDatabaseState migrated để không gọi migrate:fresh; fixture transactions rollback.
- Test importer mới: **8 tests /1.165 assertions PASS**, gồm command từ chối testing, source/province/raw fields, giữ Dealer/users/approval, rerun SKIP87, collision code/tên và manual changes không UPDATE, mid-batch rollback, schema canonicalization không bỏ qua type/collation/engine khác.
- Lượt đầu hai assertion fixture baseline so object chưa refresh (thiếu cột DB/default và bool-vs-int), sửa baseline lấy raw row đã refresh; giữ so sánh exact toàn bộ cột. Không nới guard hoặc xóa assertion để PASS.
- Full regression **292 tests /5.924 assertions,25 classes PASS,0 failure/error/skipped**, PHP8.5.11/PHPUnit12.5.40,1m38.371s. `.laravel-tools/local-import-regression.xml` và `.txt`. Đây là lần chạy mới, không dùng kết quả284 lịch sử.
- 25classes: LocalToyotaDealerImportTest, DealerDirectoryFiltersTest, CompetitionDealerTest, PublicDealerDirectoryTest, DealerProfileTest, DealerSlugTest, DealerLandingRegistrationTest, DealerAuthorizationTest, DealerDashboardTest, DealerHistoryTest, LeaderboardServiceTest, CompetitionLeaderboardTest, PublicLeaderboardTest, LandingPageTest, CompetitionHomepageTest, MonthlyDashboardTest, PublicPrivacyTest, CompetitionAwardsTest, AwardWorkflowTest, DealerRegistrationDraftTest, DealerRegistrationFinalTest, RegistrationLookupTest, RegistrationLookupStatesTest, RegistrationLookupResultPanelTest, RegistrationBusinessIntegrationTest.
- Pint explicit3PHP files PASS; node --check QA PASS; git diff --check PASS (lệnh chỉ đọc được duyệt khi sandbox báo ownership; không đổi policy/safe.directory). Không build lại vì không sửa asset/CSS/JS runtime; browser kiểm tra build hiện hữu không lỗi assets.
- Browser live pagination/sort/filters/87profiles/D01/QR/404 +36screenshots PASS. Lượt cuối probe Montserrat Vietnamese600/700 tải rõ ràng đều PASS; font chưa dùng được browser lazy-load nên không dùng check trước yêu cầu tải làm kết luận lỗi. Inter heading của Dealer không bị đổi. Kiểm tra cấu trúc tracking,87 mapping rows và Git cuối PASS; không chạy lại execute.

## Những lỗi còn tồn tại
Chưa hoàn tất các điều kiện bắt buộc trước import.

Cập nhật: import/data/restore/regression đã PASS. Cả hai demo vẫn giữ nguyên theo yêu cầu; active demo thiếu province và điểm lịch sử vẫn có mặt B14. Nhãn province cũ giữ nguyên có chủ đích; không có quyền tự chuyển sang danh mục34tỉnh mới. Snapshot là nguồn local đã duyệt, không chứng minh production/địa chỉ còn hiện hành online. Chưa kiểm tra trình duyệt khác Chrome/cuộc gọi thật/luồng POST mới trên local; luồng ghi được bảo vệ qua regression testing cách ly.

Đã giữ clone PASS và clone phục vụ điều tra, cùng mọi dump/receipt; không tự xóa hoặc dọn QA artifacts. Không còn quyền tự execute importer lần nữa hoặc sửa dữ liệu lịch sử trong task này.

## Task hoặc bước tiếp theo
Xác minh backup/restore, importer và tests; chỉ execute nếu toàn bộ PASS, rồi QA với DB thật và cập nhật evidence.

Rollback nếu có yêu cầu sau commit: dừng ghi local, đối chiếu receipt/baseline/dữ liệu mới; không tự xóa Dealer đã phát sinh quan hệ hoặc restore đè toyota. Chỉ task rollback được duyệt riêng mới xử lý đúng row/hash hoặc phục hồi backup đã kiểm chứng; không tắt FK/reset database. Hiện không có lý do cần rollback và chưa thực hiện rollback sau commit.

Không còn bước bắt buộc trong phạm vi task này. Bàn giao local toyota có dữ liệu phát triển; giữ backup/clone/receipt và không tự dọn. Server PHP8771 tạm do task khởi tạo được dừng sau QA. Dùng Laragon theo cấu hình hiện hữu để tiếp tục phát triển; không thay DNS/port/.env.

## Danh sách 87 Dealer mới

Đối chiếu receipt COMMITTED; không bao gồm hai Dealer demo ID1/2. Raw row hashes nằm trong receipt private, không xuất dữ liệu tài khoản/khách hàng.

| Dealer ID | Toyota source ID | Mã | Tên | Province giữ nguyên |
|---:|---:|---|---|---|
| 3 | 574 | TMV-574 | Toyota An Sương | Hồ Chí Minh |
| 4 | 968 | TMV-968 | Toyota An Suong- Chi nhánh Trường Chinh | Hồ Chí Minh |
| 5 | 404 | TMV-404 | Toyota An Thành Fukushima | Hồ Chí Minh |
| 6 | 984 | TMV-984 | Toyota Bắc Giang | Bắc Ninh |
| 7 | 845 | TMV-845 | Toyota Bắc Ninh | Bắc Ninh |
| 8 | 408 | TMV-408 | Toyota Bến Thành | Hồ Chí Minh |
| 9 | 1007 | TMV-1007 | Toyota Bến Tre | Vĩnh Long |
| 10 | 406 | TMV-406 | Toyota Biên Hòa | Đồng Nai |
| 11 | 665 | TMV-665 | Toyota Biên Hòa - CN Cầu Đồng Nai | Đồng Nai |
| 12 | 563 | TMV-563 | Toyota Bình Định | Gia Lai |
| 13 | 889 | TMV-889 | Toyota Bình Dương | Bình Dương |
| 14 | 1030 | TMV-1030 | Toyota Bình Dương – CN Mỹ Phước | Hồ Chí Minh |
| 15 | 1011 | TMV-1011 | Toyota Bình Phước | Bình Phước |
| 16 | 966 | TMV-966 | Toyota Bình Thuận | Lâm Đồng |
| 17 | 426 | TMV-426 | Toyota Buôn Ma Thuột | Đắk Lắk |
| 18 | 1009 | TMV-1009 | Toyota Cà Mau | Cà Mau |
| 19 | 564 | TMV-564 | Toyota Cần Thơ | Cần Thơ |
| 20 | 986 | TMV-986 | TOYOTA CẦN THƠ - CHI NHÁNH AN GIANG | An Giang |
| 21 | 989 | TMV-989 | Toyota Đắk Lắk | Đắk Lắk |
| 22 | 410 | TMV-410 | Toyota Đà Nẵng | Đà Nẵng |
| 23 | 785 | TMV-785 | Toyota Đà Nẵng chi nhánh Lê Đình Lý | Đà Nẵng |
| 24 | 976 | TMV-976 | Toyota Doanh Thu - Thanh Hóa | Thanh Hóa |
| 25 | 412 | TMV-412 | Toyota Đông Sài Gòn | Hồ Chí Minh |
| 26 | 573 | TMV-573 | Toyota Đông Sài Gòn - Chi nhánh Gò Vấp | Hồ Chí Minh |
| 27 | 965 | TMV-965 | Toyota Đông Sài Gòn - Chi nhánh Nguyễn Văn Lượng | Hồ Chí Minh |
| 28 | 969 | TMV-969 | Toyota Đông Sài Gòn - Chi nhánh Quận 9 | Hồ Chí Minh |
| 29 | 995 | TMV-995 | Toyota Đông Sài Gòn - Chi nhánh Thủ Đức | Hồ Chí Minh |
| 30 | 975 | TMV-975 | Toyota Giải Phóng - Chi Nhánh Nam Định | Ninh Bình |
| 31 | 865 | TMV-865 | Toyota Gia Lai | Gia Lai |
| 32 | 565 | TMV-565 | Toyota Hà Đông | Hà Nội |
| 33 | 826 | TMV-826 | Toyota Hải Dương | Hải Phòng |
| 34 | 1028 | TMV-1028 | Toyota Hải Dương - Chí Linh | Hải Phòng |
| 35 | 424 | TMV-424 | Toyota Hải Phòng | Hải Phòng |
| 36 | 979 | TMV-979 | Toyota Hà Tĩnh | Hà Tĩnh |
| 37 | 994 | TMV-994 | Toyota Hiroshima Long Phước | Hồ Chí Minh |
| 38 | 432 | TMV-432 | Toyota Hiroshima Tân Cảng | Hồ Chí Minh |
| 39 | 1002 | TMV-1002 | Toyota Hòa Bình | Phú Thọ |
| 40 | 1019 | TMV-1019 | Toyota Hòa Bình Minh Yên Bái | Lào Cai |
| 41 | 416 | TMV-416 | Toyota Hoàn Kiếm | Hà Nội |
| 42 | 987 | TMV-987 | Toyota Huế | Thừa Thiên - Huế |
| 43 | 1020 | TMV-1020 | Toyota Hùng Hải Hà Nam | Ninh Bình |
| 44 | 422 | TMV-422 | Toyota Hùng Vương | Hồ Chí Minh |
| 45 | 1006 | TMV-1006 | Toyota Hưng Yên | Hưng Yên |
| 46 | 1024 | TMV-1024 | Toyota Hưng Yên - Chi nhánh Văn Lâm | Hưng Yên |
| 47 | 993 | TMV-993 | Toyota IDMC Hoài Đức | Hà Nội |
| 48 | 1016 | TMV-1016 | Toyota Kiên Giang | An Giang |
| 49 | 1015 | TMV-1015 | Toyota Lạng Sơn | Lạng Sơn |
| 50 | 998 | TMV-998 | Toyota Lào Cai | Lào Cai |
| 51 | 988 | TMV-988 | Toyota Long An | Tây Ninh |
| 52 | 663 | TMV-663 | Toyota Long Biên | Hà Nội |
| 53 | 1014 | TMV-1014 | Toyota Long Thành | Đồng Nai |
| 54 | 420 | TMV-420 | Toyota Lý Thường Kiệt | Hồ Chí Minh |
| 55 | 684 | TMV-684 | Toyota Lý Thường Kiệt - Chi nhánh Tân Phú | Hồ Chí Minh |
| 56 | 430 | TMV-430 | Toyota Mỹ Đình | Hà Nội |
| 57 | 972 | TMV-972 | Toyota Nankai Hải Phòng | Hải Phòng |
| 58 | 745 | TMV-745 | Toyota Nankai Vĩnh Phúc | Phú Thọ |
| 59 | 1027 | TMV-1027 | Toyota Ngọc Anh Lâm Đồng | Lâm Đồng |
| 60 | 765 | TMV-765 | TOYOTA Nha Trang | Khánh Hòa |
| 61 | 1017 | TMV-1017 | Toyota Nhung Hồng Tuyên Quang | Tuyên Quang |
| 62 | 1018 | TMV-1018 | Toyota Ninh Bình | Ninh Bình |
| 63 | 805 | TMV-805 | Toyota Ninh Kiều | Cần Thơ |
| 64 | 996 | TMV-996 | Toyota Okayama Đà Nẵng | Đà Nẵng |
| 65 | 1013 | TMV-1013 | Toyota PGS Quảng Trị | Quảng Trị |
| 66 | 643 | TMV-643 | Toyota Pháp Vân | Hà Nội |
| 67 | 990 | TMV-990 | Toyota Phú Mỹ Hưng | Hồ Chí Minh |
| 68 | 992 | TMV-992 | Toyota Phú Thọ | Phú Thọ |
| 69 | 997 | TMV-997 | Toyota Phú Yên | Đắk Lắk |
| 70 | 945 | TMV-945 | Toyota Quảng Bình | Quảng Trị |
| 71 | 602 | TMV-602 | Toyota Quảng Ninh | Quảng Ninh |
| 72 | 974 | TMV-974 | Toyota Quảng Ninh - Cẩm Phả | Quảng Ninh |
| 73 | 973 | TMV-973 | Toyota Sông Lam | Nghệ An |
| 74 | 991 | TMV-991 | Toyota Tây Bắc Sơn La | Sơn La |
| 75 | 971 | TMV-971 | Toyota Tây Ninh | Tây Ninh |
| 76 | 1008 | TMV-1008 | Toyota Thái Bình | Hưng Yên |
| 77 | 978 | TMV-978 | Toyota Thái Hòa Từ Liêm | Hà Nội |
| 78 | 724 | TMV-724 | Toyota Thái Nguyên | Thái Nguyên |
| 79 | 566 | TMV-566 | Toyota Thăng Long | Hà Nội |
| 80 | 685 | TMV-685 | Toyota Thanh Hóa | Thanh Hóa |
| 81 | 725 | TMV-725 | Toyota Thanh Xuân | Hà Nội |
| 82 | 1001 | TMV-1001 | Toyota Thập Nhất Phong Vĩnh Long | Vĩnh Long |
| 83 | 567 | TMV-567 | TOYOTA THUẬN AN | Bình Dương |
| 84 | 977 | TMV-977 | Toyota Tiền Giang | Đồng Tháp |
| 85 | 1012 | TMV-1012 | Toyota Tiến Thu Quảng Ngãi | Quảng Ngãi |
| 86 | 1021 | TMV-1021 | Toyota Trang Lê Quảng Nam | Đà Nẵng |
| 87 | 999 | TMV-999 | Toyota Từ Sơn | Bắc Ninh |
| 88 | 434 | TMV-434 | Toyota Vinh | Nghệ An |
| 89 | 623 | TMV-623 | Toyota Vũng Tàu | Bà Rịa - Vũng Tàu |
