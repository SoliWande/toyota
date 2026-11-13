# D01 — CTA và số chủ xe hợp lệ theo đại lý

## Mục tiêu
Cập nhật riêng cụm CTA D01/M01 theo Figma mới; số chủ xe lấy từ hồ sơ approved hợp lệ của đại lý đang giới thiệu, không lấy config/toàn hệ thống/demo. Giữ nguyên hero/artwork/badge/nội dung ngoài CTA, C07/C08 và nghiệp vụ hiện hành.

## Trạng thái
IN PROGRESS — 13/11/2026. Đã đọc AGENTS, landing-fidelity-refinement, phase-1-implementation, website-competition-dealer-sync và source thực tế trước sửa.

Cập nhật 13/11/2026: **DONE phạm vi CTA/count D01**, các kiểm tra trực tiếp đạt như dưới. Không nghiệm thu toàn repository: suite đăng ký rộng hơn có2 FAIL ở trang Admin/Dealer ngoài D01, giữ nguyên và báo riêng; không coi suite đó PASS.

## Hiện trạng trước khi chỉnh sửa
- Git có sẵn 5 edits C07/release: phase-p09-final.md, admin-awards.css, admin/awards/index.blade.php, p09-publication.mjs, AwardPublicationFinalTest.php; giữ nguyên, không quy cho task D01.
- Hero hiện dùng “Đăng ký ngay”, số từ config dealer-registration.member_count và ẩn khi không có/0; không phản ánh dealer context.
- Route dealers.profile qua slug chọn Dealer thật trong PublicDealerController::show. LeaderboardService::dealerScores/all-time đã dùng approved submissions, source hợp lệ, Dealer snapshot và demo scope. B14 đã có contract members=approved profiles; không đồng nghĩa Facebook group members. Query join theo PK users/dealers không nhân bản hồ sơ; intake/approval quản lý trùng identity, không thêm quy tắc distinct phone/Facebook mới.
- Figma file yKmWQI6BYJ6dponEnidlsq, session898c36de-b102-4998-9b90-1385ba2f7182: đã đọc context/screenshot D01 209:233, M01 99:376. Chỉ triển khai CTA209:268/M01127:348; các section mới ngoài CTA không thuộc task. Không thấy ảnh tham chiếu mới đính kèm trong lượt này; dùng Figma trực tiếp, không suy đoán phần còn lại.

## Các file source code liên quan
PublicDealerController.php; dealers/registration/landing-content.blade.php; dealer-registration.css; DealerLandingRegistrationTest.php. Đọc LeaderboardService/DemoSubmissionScope/ToyotaRealisticDemo/CustomerSubmission và tests/harness liên quan. Sẽ liệt kê file thực sửa sau kiểm chứng.

File thực sửa/tạo của task:
- `app/Http/Controllers/PublicDealerController.php` — chỉ action show D01 và import demo ownership service.
- `resources/views/dealers/registration/landing-content.blade.php` — hero CTA/count và text sticky CTA hiện có.
- `resources/css/dealer-registration.css` — chỉ selector CTA hero, breakpoint CTA mobile.
- `tests/Feature/DealerLandingOwnerCountTest.php`, `tests/Browser/d01-cta.mjs` — mới, chỉ D01.
- Tracking này; generated Vite assets/manifest trong `public/build` ignored. Không sửa test đăng ký cũ, LeaderboardService, config hoặc migration.

## Những thay đổi đã thực hiện
Khởi tạo tracking trước sửa source. Chưa có kết quả triển khai/kiểm thử mới.

Cập nhật sau triển khai:
- Controller thêm DTO integer `dealerOwnerCount`, query `dealerScores()` all-time lọc `dealers.id` của slug đang mở, loại `ToyotaRealisticDemo::ownedSubmissionIds()` cả khi demo metrics bật. Không dùng tổng đứng bảng, config member_count hoặc monthly net score; standings cũ không đổi.
- View hiện “Tham gia ngay”, `X chủ xe`/`đã tham gia` theo hai dòng Figma, format số Việt Nam và hiện số0 thật. Preview không có DTO thật thì ẩn count, không giả số. Không xuất customer records/PII.
- Desktop CTA padding6/20, gap16, button54px/count16px; Mobile390 capsule342px, button204×46/gap14/count14px và caption12px/blur7. Breakpoint320 chỉ co CTA để tránh overflow; không đổi bố cục hero, background/artwork/xe/logo/badge/header/form hoặc section ngoài CTA. Tái dùng arrow PNG hiện hữu18px desktop/16px mobile.
- Sticky CTA hiện có dùng cùng text, vẫn giữ điều kiện Dealer active; inactive vẫn panel QR không hiệu lực và không form nhận đăng ký mới. Không thêm component/nút mới.

## Quyết định kỹ thuật quan trọng
Tái sử dụng approved raw count all-time theo Dealer snapshot từ logic có sẵn; không dùng điểm Dealer tháng sau penalty, không dùng số Facebook group. D01 loại owned demo IDs ngay cả khi cấu hình demo metrics bật; không đổi scope dùng chung. Nếu ownership demo không xác minh được, giữ fail-closed của service, không tự đoán dữ liệu.

Bằng chứng đếm: approvedSubmissions hiện join users theo PK rồi dealers theo PK, mỗi submission chỉ có1 row, không join review/history; filter status approved, source sales hợp lệ hoặc dealer_qr không Sales. Attribution từ submissions.dealer_id không chuyển theo Sales. Intake/approval và UNIQUE hiện hữu chặn trùng identity; không tự thêm định nghĩa unique chủ xe theo phone/tên/Facebook, không đếm lại cùng hồ sơ qua nhiều lượt duyệt. B14 đã xác nhận members=approved profiles; phân tích Phase1 nói count chưa có nguồn được thay thế **riêng D01** bởi contract existing này và yêu cầu hiện tại. Không coi nhãn “chủ xe” là đo membership Facebook.

## Kết quả kiểm thử
Chưa chạy. Trước tests ghi fixture phải kiểm exact isolated toyota_testing và dùng bootstrap no-DDL; không migrate/seed/reset. Browser có thể dùng Blade DTO fixture cách ly, sẽ phân biệt với HTTP/database test thật.

### Kết quả mới — 13/11/2026

Preflight read-only: actual MySQL8.0.30, TCP127.0.0.1:3306/toyota_testing/FK1/InnoDB; snapshot schema/count/checksum23 bảng tại `storage/app/private/d01-cta-20261113/before.json`, không ghi đè baseline task trước. Tất cả PHPUnit pin APP_ENV=testing, mysql/127.0.0.1/3306/toyota_testing, TOYOTA_TEST_DATABASE=toyota_testing, DB_URL/DATABASE_URL/DB_SOCKET rỗng, APP_CONFIG_CACHE trỏ test path không tồn tại. Bootstrap `tests/Fixtures/p09_final_bootstrap.php` chặn DDL/reset, fixtures transaction trên DB đã xác nhận cách ly; không migrate/seed.

| Lệnh/kiểm tra | Kết quả thực tế |
|---|---|
| `php vendor/bin/phpunit --bootstrap tests/Fixtures/p09_final_bootstrap.php tests/Feature/DealerLandingOwnerCountTest.php tests/Feature/LeaderboardServiceTest.php --no-progress --display-skipped --do-not-cache-result --log-junit storage/app/private/d01-cta-20261113/targeted-final.xml` | **16 tests/80 assertions PASS**,0 fail/error/skipped; gồm4 ca D01 thật về đúng dealer/source/approved/historical attribution, demo exclusion dù include metrics, zero/404, inactive/privacy và query count kế thừa. |
| `php vendor/bin/phpunit --bootstrap tests/Fixtures/p09_final_bootstrap.php tests/Feature/SubmissionDuplicateTest.php --filter 'test_different_facebook_cannot_bypass_duplicate_phone_at_intake\|test_repeated_approval_or_rejected_record_does_not_create_more_history\|test_database_unique_constraint_rejects_duplicate_even_when_application_check_is_bypassed' --no-progress --display-skipped --do-not-cache-result --log-junit storage/app/private/d01-cta-20261113/duplicates.xml` | **3 tests/18 assertions PASS**,0 fail/error/skipped. Tổng hai lệnh chọn lọc **19/98**, không cộng reruns. |
| `node tests/Browser/d01-cta.mjs` | **6 ca PASS**, Chrome headless 1440/390/320 × real fixture count2/0. HTML do controller D01 thật render trong HTTP test; browser replay HTML và Vite/static assets local, mọi request ngoài fixture/assets bị abort, không POST/production network. Không phải live session DB/browser production. Kiểm CTA label/href/anchor, count,0 overflow, geometry/loaded images/pageerror, không tự đăng ký. |
| Screenshot/visual | Đã mở ảnh `hero-{1440,390}-owners-2.png` và full-page1440/390/320 tại `.laravel-tools/d01-cta-browser/`, đối chiếu Figma CTA209:268 và127:348. Count0/2 là fixture từ query thật; không phải1126 giả. CTA390 đo342px, button204×46/gap14; desktop button54px/gap16. Khác biệt các vùng khác của Figma mới giữ nguyên theo scope, không tuyên bố toàn trang pixel-perfect. |
| `npm run build` | **PASS89 modules**, log `.laravel-tools/d01-cta-build.log`; D01 CSS `assets/dealer-registration-Bv80P50S.css`. Manifest49 entries, không thiếu file/CSS/assets tham chiếu, không public/hot. Không thay dependencies/locks; build từ dependency lock đã được cài sạch ở lượt release trước. |
| Syntax/Pint | **PASS** `php -l app/Http/Controllers/PublicDealerController.php`, `php -l tests/Feature/DealerLandingOwnerCountTest.php`, `php vendor/bin/pint --test` đúng2 file này; `node --check tests/Browser/d01-cta.mjs`, `git diff --check`. Blade đã compile/render trong HTTP suite/browser. |
| Postflight | **PASS**23 bảng giữ counts/checksums/schema; changed_business_tables=[], source_changes=[] so baseline app PHP sau sửa trước tests, migration_rows_added=0, audit4/penalty3/publication đều0. Không reset AUTO_INCREMENT. Hash C07 CSS/Blade/JS và C08 CSS/JS khớp inventory trước task. |

### Lỗi đã gặp và giới hạn test

- Fixture QR mới ban đầu thiếu mandatory fields của CHECK submissions_source_check, sau đó thiếu phone_normalized; đã sửa **chỉ fixture** cho đúng schema hiện hữu và chạy lại tới PASS16/80. Không sửa business model, constraint hay migration để làm test qua.
- Lượt rộng ban đầu chạy thêm `DealerLandingRegistrationTest`: riêng lớp này **42 tests/314 assertions,40 PASS/2 FAIL/0 ERROR**. Hai FAIL tại `test_admin_approval_requires_verified_facebook_then_scores_dealer_only` dòng163 (GET admin.submissions.index) và `test_internal_access_is_scoped_and_public_never_returns_customer_records` dòng219 (GET dealer.customers.index), đều đòi text “Khách tự đăng ký qua QR” không có trong response. Các views/controllers tương ứng và test cũ không đổi; lỗi xảy ra ở trang ngoài D01, không phải query count/CTA. Không sửa Admin/Dealer UI/assertion ngoài phạm vi hoặc gọi bộ này PASS; JUnit `tests.xml` lưu nguyên cả lỗi fixture ban đầu. Không chạy lại toàn suite để che lỗi; không chạy full regression.

## Những lỗi còn tồn tại
CTA/backend hiện tại chưa đáp ứng yêu cầu. Chưa nghiệm thu browser mới, chưa build assets mới. Không chứng nhận toàn trang khớp Figma mới vì task giữ layout/nội dung ngoài CTA.

Cập nhật kết thúc: CTA/count đã được thực hiện và kiểm chứng như trên, thay thế đoạn hiện trạng ban đầu. Còn2 FAIL test rộng ngoài D01 cần xử lý riêng nếu nghiệm thu luồng Admin/Dealer đầy đủ. Không nhận được ảnh tham chiếu đính kèm mới; Figma trực tiếp là căn cứ. Không thay đổi hero để thêm quyền lợi/badge mới từ Figma ngoài yêu cầu CTA. Preview không có dữ liệu thật không hiển thị bộ đếm.

## Task hoặc bước tiếp theo
Sửa DTO count/CTA/CSS, kiểm backend filtering/privacy, browser desktop/mobile, syntax/Pint/build; cập nhật kết quả trong file này. Không deploy.

Cập nhật kết thúc: bàn giao các file D01 đã test và tracking này để review; xử lý riêng2 assertion cũ ở suite rộng khi được yêu cầu. Không commit/push/deploy, không truy cập production hoặc lấy secrets; không thay đổi5 edits C07/release có sẵn.
