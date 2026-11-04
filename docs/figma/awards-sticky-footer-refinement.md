# Sửa sticky footer trang Vinh danh

## Mục tiêu
Sửa footer /awards và chi tiết kỳ để nằm cuối viewport khi ít nội dung, sau nội dung khi trang dài. Giữ nguyên giao diện các section và nghiệp vụ.

## Trạng thái
DONE — 2026-11-04. Tạo IN PROGRESS trước khi chỉnh source; hoàn tất sửa, build và kiểm tra browser before/after.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, tracking awards-sync, awards-timezone-publication-audit và lookup-sticky-footer-refinement; kiểm tra competition layout, Awards Blade/CSS và QA hiện có. Git status ban đầu sạch. Body trực tiếp chứa header/main/footer, CSS competition-site chưa có flex/min-height viewport; cần xác nhận bằng browser.

## Các file source code liên quan
resources/views/components/competition/layout.blade.php, awards-page.blade.php; resources/css/competition.css. Routes/controllers/services/model chỉ đọc, không đổi publication/privacy/timezone.

## Những thay đổi đã thực hiện
Ghi chép ban đầu: tạo tracking và chuẩn bị baseline, chưa sửa runtime.

### Nguyên nhân và sửa — 2026-11-04
- Browser xác nhận body display:block/min-height:0, main flex-grow:0, footer position:static. Empty390×900 footer bottom696, còn204px dưới footer; empty1440×900 bottom745, còn155px. Không có fixed/absolute footer hoặc margin-bottom gây lỗi. Root cause là shell chưa giãn theo viewport, tương tự lookup nhưng thuộc stylesheet/layout khác.
- `resources/views/components/competition/layout.blade.php`: thêm prop stickyFooter mặc định false, chỉ thêm class wc-sticky-footer khi bật.
- `resources/views/components/competition/awards-page.blade.php`: bật stickyFooter cho cả index và detail vì hai route dùng chung component này.
- `resources/css/competition.css`: thêm đúng3 rule, scope body.competition-site.wc-sticky-footer: flex column/min-height100vh fallback100dvh; main flex1 0auto; header/footer không co. Không thay padding, height, typography của section hoặc footer, không pixel spacer, không position hack.
- Thêm `qa/awards-sticky-footer-visual.mjs` và evidence riêng `qa/screenshots/awards-sticky-footer/{before,after}/`, không ghi đè ảnh/tracking task trước.

## Quyết định kỹ thuật quan trọng
Ưu tiên flex column, main giãn và min-height viewport. Chỉ bật trên Awards để không thay geometry trang chủ/Leaderboard; không fixed/absolute footer hoặc pixel spacer. Không sửa nghiệp vụ, schema hoặc tạo dữ liệu production.

## Kết quả kiểm thử
Chưa có kết quả mới. Dùng GET read-only và private Blade fixtures DB-disabled, không migration/seed hoặc feature tests ghi dữ liệu cho thay đổi CSS này.

### Tiến độ kiểm chứng
- `php docs/figma/qa/awards-state-fixtures.php`: PASS11 private Blade states, DB disabled, không route QA runtime. Chạy trước/sau sửa, fixture chưa lưu vào database.
- `node docs/figma/qa/awards-sticky-footer-visual.mjs before`: PASS baseline96 cases (72 fixture +24 GET live). Sáu trạng thái empty/design/one/long/many/detail ×320/390/768/1024/1440/1920 ×height900/1200; home/leaderboard/lookup/awards GET thật ×6widths ởheight900.
- `npm run build`: PASS64 modules. JS và dealer-registration CSS giữ nguyên hash; chỉ competition CSS có hash mới.
- `node --check docs/figma/qa/awards-sticky-footer-visual.mjs` và `git diff --check`: PASS mới.
- Đã mở ảnh empty390 before/after: phần nội dung không đổi vị trí, footer được đẩy xuống đáy màn hình. Đang chạy ma trận after đầy đủ trước kết luận.

### Kết quả cuối mới — 2026-11-04
- `node docs/figma/qa/awards-sticky-footer-visual.mjs after`: **PASS96 cases**. 72 fixture cases của Awards và6 live Awards GET đều đúng sticky footer; 18 screenshot home/leaderboard/lookup **byte-identical** với baseline của task này.
- Sáu width320/390/768/1024/1440/1920; fixture chạy cảheight900 và1200. Empty, một winner, published Top3, tên rất dài/điểm lớn, nhiều kỳ và detail đều PASS. Footer.bottom bằng documentHeight (sai số<=1px), ít nhất bằng viewportHeight; footer sau main và mọi CTA, position static, không overflow ngang hoặc lỗi JavaScript/assets được phát hiện.
- Geometry header/intro/chips/panel/history/snapshot note x/y/width/height giữ nguyên<=1px so baseline, không tăng chiều cao section để che lỗi. Nội dung dài làm document cao tự nhiên, footer tiếp tục nằm sau nội dung.
- Empty390×900: footer top592/bottom696 → top796/bottom900; main532→736. Empty1440×900: top644/bottom745 → top799/bottom900; main572→727. Khoảng trống dưới footer204px/155px đã được chuyển thành phần main giãn, không thay padding footer.
- GET thật home/leaderboard/lookup/awards trả200 ở cả6widths, không lỗi JS. Các dữ liệu fixture chỉ phục vụ layout; không tuyên bố đã kiểm thử lại quy trình công bố bằng fixture ảnh.
- Đã xem trực quan ảnh empty390 trước/sau, empty1440 sau sửa và long1440 (cuộn bình thường). Không chạy lại feature suite/database tests vì không đổi nghiệp vụ; các test lịch sử không được ghi thành kết quả mới.

## Screenshot evidence và cấu trúc trước/sau
- Trước: body block, không min-height; main không grow; footer nằm sau độ cao nội dung nên có khoảng trống viewport phía dưới.
- Sau: Awards body flex column/min-height100vh/100dvh; main flex1 0auto; header/footer shrink0. Các trang không truyền stickyFooter vẫn giữ nguyên class/style cũ.
- Evidence computed styles/geometry: `qa/screenshots/awards-sticky-footer/before/evidence.json` và `after/evidence.json`. Mỗi lượt96 ảnh, không đè ảnh Awards/Lookup lịch sử.
- [Mobile trước](qa/screenshots/awards-sticky-footer/before/fixture-empty-390-900.png) / [Mobile sau](qa/screenshots/awards-sticky-footer/after/fixture-empty-390-900.png).
- [Desktop trước](qa/screenshots/awards-sticky-footer/before/fixture-empty-1440-900.png) / [Desktop sau](qa/screenshots/awards-sticky-footer/after/fixture-empty-1440-900.png), [Nội dung dài](qa/screenshots/awards-sticky-footer/after/fixture-long-1440-900.png).

## Những lỗi còn tồn tại
Không còn lỗi footer được phát hiện trong ma trận đã kiểm tra. Giới hạn: Chrome headless; chưa kiểm tra thiết bị thật/dynamic browser bars. Đây là fix shell, không thay thiết kế các section hoặc assets; dùng frame/reference đã lưu trong tracking Awards, không gọi lại Figma hoặc tuyên bố nghiệm thu pixel-perfect. Không sửa các vấn đề publish/timezone đã được ghi trong task audit riêng.

## Task hoặc bước tiếp theo
Không còn bước bắt buộc trong task này. Chỉ3 file runtime được sửa như trên, thêm tracking/QA/evidence riêng; không thay controller/service/routes/model/schema hoặc dữ liệu. Không cần migrate/seed để dùng bản sửa.
