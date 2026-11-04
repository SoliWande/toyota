# Bổ sung và bảo vệ nút Gọi đại lý

## Mục tiêu
Nút gọi nền xám, icon điện thoại, href lấy từ database, chỉ công khai số hợp lệ đã được cho phép; an toàn cho demo và responsive.

## Trạng thái
IN PROGRESS

## Hiện trạng trước khi chỉnh sửa
- Card `dealer-registration/dealer-card.blade.php` đã có nút nhưng chỉ kiểm tra truthy phone rồi xóa ký tự; có thể sinh `tel:` rỗng hoặc số sai.
- PublicDealerController lấy `dealers.phone` trực tiếp qua PUBLIC_FIELDS. Admin DealerRequest chỉ giới hạn string/max:30; schema không có cờ xác minh/quyền công khai số.
- Demo Phase 2A không cấu hình phone của Dealer; không được tự điền số để làm xuất hiện nút.
- Đã đọc AGENTS.md, context-recovery-2026-11-03.md, phase-1-implementation.md, phase-2a-demo.md, landing-fidelity-refinement.md và source tương ứng.
- Git status bị chặn dubious ownership kể cả override theo lệnh; không sửa cấu hình Git toàn cục. Chỉ chỉnh file trong phạm vi task.
- Figma D01 209:233, file yKmWQI6BYJ6dponEnidlsq: mở trực tiếp thất bại, phiên không có Figma MCP. Tham khảo asset phone 209:485 (18×18) và CSS hiện có; chưa xác nhận thông số trực tiếp.

## Các file source code liên quan
- resources/views/components/dealer-registration/dealer-card.blade.php
- resources/css/dealer-registration.css
- app/Services/DealerCallPhone.php (mới)
- config/dealer-registration.php
- .env.example
- docs/figma/qa/dealer-call-check.php và dealer-call-visual.mjs (mới)

## Những thay đổi đã thực hiện
Chưa sửa source tại thời điểm tạo tracking; sẽ cập nhật kết quả bên dưới.

### Cập nhật 2026-11-04 — hoàn tất source và kiểm tra cô lập
- Thêm `DealerCallPhone`: chỉ nhận Dealer đã lưu/active, chuẩn hóa khoảng trắng/dấu phân cách và +84/0084; chấp nhận mobile VN, landline 02 và hotline 1800/1900 đủ độ dài. Từ chối ký tự lạ, số ngắn và href rỗng.
- Card gọi service và chỉ xuất `<a href="tel:…">` khi đạt kiểm tra. Giữ icon phone 18px bên trái, thêm aria-label cho biến thể compact vốn ẩn text theo convention hiện có. Không thêm JavaScript runtime.
- CSS giữ pill xám `#f2f2f5`, text `#111`, gap 8px, font 16/600, padding 16px 28px của component hiện có. Desktop flex căn giữa; mobile nút full-width theo CSS hiện hữu. Chống co nút; compact tối thiểu 44px. Sửa hover kế thừa màu đỏ thành xám `#e3e3e8` riêng nút này.
- Thêm cấu hình `approved_call_phones` và biến `.env.example` `TOYOTA_DEALER_APPROVED_CALL_PHONES='{}'`. Sau khi người vận hành xác minh số và quyền công khai, giá trị JSON là object ánh xạ slug đại lý sang số chuẩn hóa tương ứng trong database (chuỗi giữ số 0 đầu). Không điền số thật/mẫu vào cấu hình triển khai. Nếu đang cache config, áp dụng cấu hình bằng quy trình triển khai hiện có.
- Cấu hình chỉ là bằng chứng cho phép, không thay nguồn số database; số thay đổi sẽ không khớp approval cũ. Không tuyên bố có workflow OTP xác minh: dự án chưa có workflow đó cho Dealer. Approval là xác nhận tường minh của người vận hành.
- Phase 2A không có số nên tiếp tục ẩn nút; demo có số nhưng không được duyệt riêng đúng slug/số cũng ẩn. Không seed, ghi DB hoặc dùng production phone fallback. Không đổi cơ chế manifest/metrics demo.
- File thay đổi: Blade, CSS, service mới, config, `.env.example`, hai QA script được liệt kê ở trên; tracking này; Vite output `public/build/manifest.json` và bundle CSS `public/build/assets/dealer-registration-DaiR-0jd.css`; screenshot/evidence trong `docs/figma/qa/screenshots/dealer-call/`.

## Quyết định kỹ thuật quan trọng
- Không thay schema/API hay tự coi phone hiện hữu là đã xác minh. Cấu hình allowlist theo slug và chính số chuẩn hóa là xác nhận của người vận hành về xác minh/quyền công khai; mặc định rỗng.
- Số href luôn lấy từ Dealer trong database, config chỉ đối chiếu. Số database thay đổi thì phải duyệt lại. Demo cũng cần được cho phép tường minh từng slug/số; không dùng số production làm fallback.
- Không ghi database hay chạy migrate/seed. Test render cô lập với database driver vô hiệu hóa; fixture chỉ trong QA.

## Kết quả kiểm thử
Chưa chạy; cập nhật sau triển khai.

### Kết quả mới 2026-11-04
- `php docs/figma/qa/dealer-call-check.php`: PASS 25 kiểm tra — chuẩn hóa và href chính xác, null/rỗng/undefined/ký tự nguy hiểm/số sai bị ẩn, approval thiếu/sai/khác đại lý bị từ chối, demo không kế thừa approval production, demo chỉ được gọi khi duyệt riêng, preview unsaved/inactive bị ẩn. DB default bị vô hiệu hóa trước render; không ghi database.
- `php docs/figma/qa/render-check.php`: PASS toàn bộ contract hồi quy landing, step one/two, lookup, supplement, rules và preview. Không chạy feature suite ghi dữ liệu.
- `npm run build`: PASS, 62 modules; manifest và bundle CSS mới đã tạo.
- `node docs/figma/qa/dealer-call-visual.mjs`: PASS Chrome headless, viewport 320/390/768/1024/1440, cả full/compact: không overflow/chồng lấn, nút trong card, hit area >=44px, xám/đen/pill đúng CSS, desktop căn giữa dọc, hover xám. Đã chụp 5 screenshot và evidence.json; đã xem ảnh 390/1440. Asset icon lấy từ public local, không gọi network hay tel.
- `php -l app/Services/DealerCallPhone.php`, `php -l config/dealer-registration.php`: PASS. Pint `--test` cho service/config/QA PHP: PASS.
- Diễn tiến: QA visual ban đầu đọc màu trong lúc transition nên trả transparent; tắt transition riêng harness và dùng bundle đã build. Sau đó phát hiện rule compact 40px ghi đè rule mới; tăng specificity riêng nút, chạy lại PASS. Giữ lịch sử, không coi lần FAIL là PASS.

## Những lỗi còn tồn tại
Chưa truy cập được Figma trực tiếp; không thể nghiệm thu khớp hoàn toàn thiết kế.
- Screenshot là fixture Blade, không phải luồng database thật. Chưa kiểm tra font thật hoặc gọi trên thiết bị hỗ trợ tel; không phát sinh cuộc gọi trong QA.
- Chưa đọc dữ liệu/số thật trong database và chưa cấu hình approval cho đại lý cụ thể; mặc định nút ẩn để đáp ứng quyền công khai/demo. Không tự coi dữ liệu hiện có là đã được xác minh.
- Trạng thái vẫn IN PROGRESS cho phần nghiệm thu thiết kế, không đánh dấu DONE khi chưa đối chiếu frame gốc. Không thay kết luận lịch sử của phase khác.

## Task hoặc bước tiếp theo
Triển khai, kiểm thử render, build và screenshot desktop/mobile nếu runtime hỗ trợ.

### Bước còn lại sau cập nhật
- Khi truy cập được Figma D01/M01, đối chiếu chính xác kích thước/font/spacing/màu và nghiệm thu screenshot; số liệu CSS hiện có chưa được chứng nhận là thông số Figma hiện hành.
- Người vận hành xác minh quyền công khai và cấu hình approval riêng cho số database của đại lý cần bật nút; giữ demo rỗng nếu không có số được phép.
- Kiểm tra luồng thật với database test cách ly và thiết bị hỗ trợ gọi khi có môi trường phù hợp; không dùng số fixture để gọi.
