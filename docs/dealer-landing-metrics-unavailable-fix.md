# D01: render khi demo ownership không xác minh được

## Mục tiêu
Chặn production500 từ phần metrics của trang Dealer, không nới demo verification hoặc coi dữ liệu chưa kiểm chứng là thật; giữ CTA/thông tin/đăng ký khi số liệu unavailable.

## Trạng thái
DONE — 13/11/2026. Sửa source và kiểm thử local hoàn tất; không triển khai hoặc kiểm tra dữ liệu production.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, tracking d01-cta-dealer-count-refinement và source controller/LeaderboardService/DemoSubmissionScope/ToyotaRealisticDemo/Blade/tests. ownedSubmissionIds() không bỏ kiểm tra theo enabled flag: nếu admin demo không tồn tại trả[]; nếu có admin thì manifest phải đúng tag/role/users/submissions1500 unique positive IDs. Ownership envelope phải decrypt được và khớp dataset/database/admin/submission IDs, hoặc legacy fingerprint phải khớp khi không có envelope. Dòng257 là final throw khi không có proof khớp. APP_KEY/ciphertext/binding database/dataset/ID thay đổi hoặc fingerprint thiếu/sai có thể dẫn đến lỗi; chưa truy cập production nên không kết luận trường nào sai trên production.

## Các file source code liên quan
Sửa riêng PublicDealerController::show() phần metrics; thêm tests/Feature/DealerLandingMetricsUnavailableTest.php. Blade hiện có isset(dealerOwnerCount) đã hỗ trợ null để ẩn số, không cần đổi UI/CSS. ToyotaRealisticDemo/LeaderboardService/DemoSubmissionScope chỉ đọc, lưu hash baseline ignored .laravel-tools/dealer-metrics-unavailable/before/.

## Những thay đổi đã thực hiện
- `app/Http/Controllers/PublicDealerController.php`, riêng show(): khởi tạo standing/dealerOwnerCount=null, gọi verifier trước mọi query metrics, rồi giữ nguyên query standings/approved raw count all-time theo đúng dealer_id và loại owned demo IDs. Bọc riêng vùng này bằng catch LogicException; lỗi ở count sau khi standing đã tính cũng xóa cả hai kết quả, không lộ partial metrics. Lỗi query/DB thông thường không bị catch-all che khuất.
- Khi verifier từ chối, ghi warning cố định `Dealer landing metrics unavailable; demo verification requires operator review.` với đúng dealer_id và reason=demo_verification_failed. Không log exception object/message/stack, dữ liệu khách, admin email, ciphertext hoặc metadata. Dealer lookup/404, draft đọc dữ liệu, thông tin Dealer/CTA/registration vẫn chạy theo logic cũ.
- Không sửa Blade: isset(dealerOwnerCount) hiện có ẩn phần count khi null; count0 chỉ được tính từ query đã qua verification và vẫn hiển thị khi thật sự không có approved profiles. Không thêm text/badge/UI hay hardcode số thay thế.
- Thêm `tests/Feature/DealerLandingMetricsUnavailableTest.php`. Hash sau sửa của ToyotaRealisticDemo, DemoSubmissionScope, LeaderboardService và landing-content.blade.php khớp baseline; không sửa ownership/cleanup/scoring/approval/C07/C08 hoặc database schema/dữ liệu production.

## Quyết định kỹ thuật quan trọng
Luôn xác minh ownership trước khi lấy metrics dù demo enabled/disabled/include. Catch LogicException chỉ tại vùng đọc metrics D01; không sửa service guard, scoring/approval/C07/C08 hoặc manifest. Khi fail đặt standing/count=null (không thay0), ẩn count theo Blade sẵn có, log warning cố định với dealer_id public/reason code, không log exception message/trace/manifest/phone/email/ciphertext. DB/query lỗi ngoài contract này không bị che bằng catch-all.

## Kết quả kiểm thử
- Preflight `php .laravel-tools/dealer-profile/preflight.php`: PASS actual MySQL loopback toyota_testing/hostname local. Tests mới lặp guard trước transaction. Dùng bootstrap `tests/Fixtures/p09_final_bootstrap.php` chặn SQL DDL/reset. Không migrate/seed/reset, không truy cập production.
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/Fixtures/p09_final_bootstrap.php tests/Feature/DealerLandingMetricsUnavailableTest.php tests/Feature/DealerLandingOwnerCountTest.php`: **17 tests/240 assertions PASS**. 13 ca mới và4 count contracts cũ, không cộng reruns.
- Ca thực với ToyotaRealisticDemo thật: demo enabled/disabled/include; manifest thiếu, không có ownership proof, ciphertext không giải mã được, authenticated envelope bind database khác, duplicate ownership IDs, legacy fingerprint sai. Trang trả200, count/standing=null, không hiển thị0/1126/exception text, Dealer/address/hours/CTA/form/action đúng. Gọi verifier trực tiếp sau đó vẫn throw, chứng minh không vô hiệu hóa guard.
- Proof hợp lệ được tạo bằng Crypt trong transaction test, manifest1500 positive unique IDs theo contract (không seed1500 hồ sơ), gồm1 approved demo thuộc Dealer và các IDs metadata không tồn tại; count chỉ1 approved thật của đúng Dealer, loại pending/rejected/Dealer khác. Demo-inclusive có ownership hợp lệ vẫn loại demo khỏi count D01. Test env giả lập production chỉ sau preflight/fixtures và luôn dùng connection toyota_testing; có case binding mismatch để mô phỏng import/chuyển môi trường, không cần secrets hoặc DB production.
- Test double mô phỏng exception chứa email/phone/envelope: Log chỉ nhận message/context cố định an toàn; không phát query COUNT(*) AS score/ROW_NUMBER() sau proof fail. Ca proof+standing thành công nhưng verifier từ chối khi chuẩn bị count xác nhận partial standing bị xóa.
- `node .laravel-tools/dealer-metrics-unavailable/browser.mjs`: **3 cases PASS** tại1440/390/320, HTML response từ controller thật trong transaction production-like test. Count ẩn, CTA vẫn Tham gia ngay/#registration, đúng Dealer card, POST form/action và field nhập được; không overflow/JS error/asset thiếu/request ghi. Browser chỉ replay fixture/static assets, không POST/production network; không tuyên bố E2E submit production. Đã xem ảnh hero desktop/mobile trong `.laravel-tools/dealer-metrics-unavailable/after/`; checks.json lưu kết quả.
- Harness browser lần đầu đoán nhầm URL `/register/`; đã đổi assertion theo route hiện hữu `/registrations/step-one` và chạy lại PASS, không đổi route ứng dụng.
- Pint --test và php -l hai file controller/test: PASS. Git diff --check PASS. Hash các file guard/scoring/Blade được bảo vệ: PASS. Không build lại assets vì không sửa Blade/CSS/JS/dependencies.

## Những lỗi còn tồn tại
Không còn lỗi liên quan được phát hiện trong phạm vi kiểm thử. Không xác định chính xác metadata/binding/key nào sai trên production chỉ từ stack trace; operator cần review riêng. Khi proof còn sai, count sẽ tiếp tục unavailable, không tự bật lại số liệu bằng demo flag hoặc bỏ ownership check. Các trang ngoài D01 giữ nguyên fail-closed của service; task không tuyên bố sửa lỗi metrics của nơi khác.

## Task hoặc bước tiếp theo
Bàn giao thay đổi controller/test để người dùng review và quyết định deploy riêng. Sau deploy được xác nhận, operator kiểm tra D01 render200/count ẩn khi chưa có proof và warning reason cố định; xử lý ownership proof theo quy trình hiện hữu, không tự sửa manifest/APP_KEY/binding hoặc coi unknown profiles là thật. Không tự deploy.
