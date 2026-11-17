# Chuẩn hóa số điện thoại và nút Gọi đại lý

## Mục tiêu
Parse duy nhất dealers.phone để hiển thị từng nút gọi tại D02–D06, landing, Dealer Detail và component liên hệ chung; giữ users.phone riêng trong /profile. Không tạo phase, sửa dữ liệu/schema, C07/C08 hoặc deploy.

## Trạng thái
DONE — 2026-11-17, hoàn tất phạm vi parser/nút gọi và kiểm tra local. Hai lỗi hồi quy cũ ngoài phạm vi ghi riêng bên dưới; không tuyên bố toàn suite PASS hoặc khớp pixel tuyệt đối Figma.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, context-recovery, phase-1-implementation, dealer-call-button-refinement, referring-dealer-manager-phone-sync, registration-success-sidebar-refinement và website-competition-dealer-sync; đối chiếu source/controller/profile/tests/CSS hiện tại.
DealerCallPhone chỉ trả một số, yêu cầu approved_call_phones và có fallback users.phone qua manager_contacts. Lookup-next tự xóa ký tự, có thể ghép nhiều số thành tel sai. D02 sidebar riêng không có nút; success chỉ có icon. ProfileController hiện chỉ ghi users.phone, không ghi dealers.phone.
Quy tắc người dùng xác nhận trong task này thay thế quyết định approval/fallback phone trong các tracking cũ (bao gồm dealer-call-button-refinement, referring-dealer-manager-phone-sync, dealer-manager-introduction-sync và website-competition-dealer-sync); giữ nguyên lịch sử, mapping ảnh không đổi. Git status bị dubious ownership cả khi override theo lệnh; không đổi Git global. Sẽ lưu baseline riêng trước sửa.

## Các file source code liên quan
app/Services/DealerCallPhone.php; app/Http/Controllers/PublicDealerController.php; Blade dealer-card, success, step-two-content, lookup-next, competition/dealer-profile và dealers/competition; resources/css/dealer-registration.css, competition.css; tests tương ứng. ProfileController/ProfileRequest/profile/dealer chỉ đối chiếu và kiểm thử hồi quy.

## Những thay đổi đã thực hiện
Chưa sửa source tại thời điểm tạo. Figma session 8e1fe8f7-2514-413f-ab0d-c252264bd039; đã đọc context và ảnh 209:464 (nút nền #f2f2f5, radius999, padding14×24, gap8, icon18, font15/600), 212:437 (success) và 216:562 (Dealer Detail). Dùng lại assets/component hiện có. Bổ sung text số và nhiều nút là yêu cầu hiện tại, Figma chỉ có biến thể một nút/icon; không tuyên bố pixel-perfect cho biến thể mới.

### Cập nhật triển khai 2026-11-17
- `app/Services/DealerCallPhone.php`: thêm `parse(?string): array`, `allForDealer(Dealer): array`; bỏ fallback manager và allowlist. Giữ `forDealer()` lấy số đầu để tương thích các consumer một số cũ; tất cả UI hiện dùng danh sách. Không gọi managerFor để tìm số.
- `app/Http/Controllers/PublicDealerController.php`, `resources/views/dealers/competition.blade.php`, `resources/views/components/competition/dealer-profile.blade.php`: chuyển danh sách số đã parse tới Detail. Mỗi số có một nút; contact row hiển thị riêng từng số, không tạo thêm tel trùng trong row.
- `resources/views/components/dealer-registration/dealer-card.blade.php`, `resources/views/dealers/registration/step-two-content.blade.php`, `resources/views/dealers/registration/success.blade.php`: render từng nút có số và aria-label riêng. D02 chuyển sang component chung nhưng giữ nhãn “Đăng ký qua đại lý”. D03 dùng compact; D05 giữ card landing; D06 vẫn không có nút cho đại lý inactive.
- `resources/views/components/dealer-registration/lookup-next.blade.php`: dùng parser chung cả nội dung liên hệ và nút, không ghép các số thành một href.
- `resources/css/dealer-registration.css`, `resources/css/competition.css`: nhóm nút flex/wrap gap8, giữ nền xám/chữ đen/bo999/icon hiện hữu; sidebar xuống hàng, mobile full width. Success chuyển icon tròn sang pill có số theo yêu cầu mới. Không modal/dropdown.
- `config/dealer-registration.php`: chỉ cập nhật chú thích approval cũ không còn dùng cho nút; manager_contacts chỉ cho fallback ảnh. Giữ key cũ để không phá tooling, không sửa env/data.
- Tests sửa contract: `tests/Feature/{CompetitionDealerTest,DealerLandingRegistrationTest,DealerManagerIntroductionTest,DealerProfileTest,DealerRegistrationSuccessPresentationTest,ReferringDealerManagerPhoneTest,RegistrationLookupResultPanelTest}.php`. Thêm `tests/Unit/DealerCallPhoneTest.php`, `tests/Feature/DealerPhonePresentationTest.php`.
- Thêm QA tái chạy `docs/figma/qa/dealer-phone-multiple-render.php`, `docs/figma/qa/dealer-phone-multiple-browser.mjs`; fixture chỉ trong process/database-disabled và `.laravel-tools/dealer-phone-multiple/`, không thêm route runtime.
- Build cập nhật `public/build` (đã ignored). Tài liệu duy nhất sửa là tracking này, không tạo phase và không đổi lịch sử cũ.
- Đã đọc thêm `d02-d06-registration-flow-sync.md`, `d01-cta-dealer-count-refinement.md`; đọc Figma D02 211:287 và mobile card99:545. Mobile context xác nhận nền#f5f5f7/font16/600/h52; ảnh card99:545 bị clip1px như lịch sử, nên chỉ đối chiếu mobile bằng context và ảnh app thực tế.
- Cuối task đã dùng được Git với `--git-dir=F:/laragon/www/toyota/.git --work-tree=F:/laragon/www/toyota`, không sửa config global. So baseline lưu trước chỉnh với HEAD: **16/16 file trùng** (bỏ khác biệt line ending). Git diff cho các file còn lại chỉ chứa thay đổi được mô tả tại đây; không có diff C07/C08, ProfileController, ProfileRequest, profile/dealer, models, schema hoặc JS runtime.

## Quyết định kỹ thuật quan trọng
Chỉ parse khi hiển thị, không lưu lại chuỗi chuẩn hóa. Tách theo phẩy/chấm phẩy/slash/xuống dòng; khoảng trắng bên trong một số là format. Chuẩn hóa +84/0084 về đầu 0 bằng RegistrationPhone hiện có; kiểm tra mobile/landline/hotline VN và bỏ trùng sau chuẩn hóa. Giữ guard dealer tồn tại/active. Approval cũ không còn chi phối việc hiển thị theo yêu cầu hiện tại.

## Kết quả kiểm thử
Chưa chạy. Sẽ xác nhận cấu hình và danh tính MySQL toyota_testing trước mọi test transaction; không migrate/seed/reset. UI QA dùng fixture cách ly và asset build thật.

### Kết quả mới 2026-11-17
- Preflight: `php .laravel-tools/dealer-profile/preflight.php` **PASS**, SELECT-only; APP_ENV testing, MySQL loopback, actual DB toyota_testing/hostname local. Test harness hiện có kiểm tra isolation trước transaction. Không migrate/seed/reset/deploy hay ghi fixtures vào DB ứng dụng.
- Lệnh tập trung: `php vendor/phpunit/phpunit/phpunit tests/Unit/DealerCallPhoneTest.php tests/Feature/DealerPhonePresentationTest.php tests/Feature/DealerProfileTest.php tests/Feature/CompetitionDealerTest.php tests/Feature/DealerManagerIntroductionTest.php tests/Feature/ReferringDealerManagerPhoneTest.php tests/Feature/DealerRegistrationSuccessPresentationTest.php tests/Feature/RegistrationLookupResultPanelTest.php tests/Feature/DealerPersonalProfileTest.php --log-junit .laravel-tools/dealer-phone-multiple/focused.xml`: **85 tests / 1010 assertions PASS**. Bao gồm delimiter/format/leading0/+84/0084/loại trùng/số sai/null; render đúng số nút/href/text ở5 presentation; route thật landing/Detail hai số và không fallback khi có manager mapping. PATCH profile thay/omit/xóa users.phone đều giữ nguyên chuỗi dealers.phone hai số.
- Landing test chọn đúng phạm vi: `php vendor/phpunit/phpunit/phpunit tests/Feature/DealerLandingRegistrationTest.php --filter 'test_landing_form_uses_correct_dealer_and_stable_existing_qr|test_inactive'`: **2 tests / 21 assertions PASS**.
- Hồi quy rộng cùng9 class tập trung cộng DealerLandingRegistrationTest, JUnit `related.xml`, log `related-final.log` tại `.laravel-tools/dealer-phone-multiple/`: **127 tests / 1324 assertions; 125 PASS, 2 FAIL, 0 ERROR**. Hai FAIL là `test_admin_approval_requires_verified_facebook_then_scores_dealer_only` (dòng163) và `test_internal_access_is_scoped_and_public_never_returns_customer_records` (dòng219), thiếu nhãn “Khách tự đăng ký qua QR” tại Admin/Dealer customers. Đúng hai lỗi đã ghi trong `d01-cta-dealer-count-refinement.md`; các source trang đó không đổi. Không sửa assertion/nghiệp vụ ngoài phạm vi để làm xanh suite.
- Lần đầu test mới lỗi vì fixture chart thiếu months/period; đã bổ sung fixture đúng shape. Contract manager fallback và fixture lookup unsaved cũ được cập nhật theo nghiệp vụ mới. Các lần lỗi ban đầu giữ trong `tests-initial.log`; kết quả cuối ở trên không gộp thành PASS toàn suite.
- `npm run build` **PASS**,90 modules; kiểm tra manifest **58 asset local tồn tại**. Output log `build.log`.
- `php docs/figma/qa/dealer-phone-multiple-render.php` **PASS**,25 HTML fixture dùng Blade/build thật, database default vô hiệu hóa. D05 có duplicatePhone + lỗi phone thật trong input view; D06 inactive có hai số hợp lệ để kiểm tra guard.
- `node docs/figma/qa/dealer-phone-multiple-browser.mjs` **PASS**, Chrome1440×1000/390×1000, **50 page/viewport checks** (landing,D02,D03,success,D05,Detail ×1/2/none/invalid, cộng D06). Mỗi số đúng href/text, pill radius999/text#111, hit area>=44px, nút trong card, không horizontal overflow/JS error/asset thiếu/request ghi. Lần đầu harness đếm cả nút trong preview step-two ẩn; đã chuyển fixture sang render trang runtime preview=false. Không thay runtime để làm QA qua.
- Screenshot/metrics: `.laravel-tools/dealer-phone-multiple/screenshots/`; đã xem card landing1440/390, D02 sidebar1440/390, success1440 và Detail390; ảnh full page giữ cùng thư mục. Đây là fixture UI, không tuyên bố browser submit luồng thật hoặc thực hiện cuộc gọi. Browser chặn mọi request ghi và ngoài origin.
- Pint `--test` **PASS** cho service/controller/config,9 test PHP thay đổi/thêm và QA render. PHP syntax/JS syntax **PASS**, Blade đã render thật. `git diff --check` **PASS**, chỉ cảnh báo chuyển CRLF/LF. Chỉ chạy Pint trên file trong task.

## Những lỗi còn tồn tại
Không phát hiện lỗi parser/nút gọi trong phạm vi kiểm tra. Hai lỗi assertion nhãn cũ ngoài phạm vi ở trên vẫn tồn tại; không gọi hồi quy rộng PASS. Ảnh Figma mobile99:545 clip1px, đã đối chiếu context và screenshot app nhưng chưa thể so pixel mobile trực tiếp. Các biến thể hai số và success pill có text là mở rộng theo yêu cầu người dùng, không có frame Figma tương ứng riêng. Chưa kiểm tra gọi điện thật trên thiết bị hoặc dữ liệu production. QA cũ `dealer-call-check.php` phản ánh policy approval lịch sử, được thay bằng tests/parser và QA mới cho task này, không chạy nó như contract hiện hành.

## Task hoặc bước tiếp theo
Không còn bước triển khai trong phạm vi. Hai lỗi nhãn Admin/Dealer customers cần task riêng nếu muốn sửa; muốn nghiệm thu pixel mobile cần export Figma không clip. Không tự deploy hoặc chỉnh dữ liệu đại lý.
