# P02 — C01 Tổng quan

## Mục tiêu
Đồng bộ riêng nội dung GET /admin/dashboard với C01 160:233 và tabs 173:1465; giữ nguyên shell P01, scoring, privacy và dữ liệu.
## Trạng thái
DONE trong phạm vi C01 Desktop và hai tab đã có thiết kế; giới hạn empty/track nêu dưới.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS, C01 inventory/P02 plan, source Dashboard/LeaderboardService/DemoSubmissionScope và tests. Có thay đổi P01 và Dealer Manager chưa commit; giữ nguyên. Dashboard hiện có 10 stats/top3 nhưng bố cục khác Figma, thiếu queue oldest/48h, pending Manager, Sales quá3ngày và2KPI.
## Các file source code liên quan
app/Http/Controllers/Admin/DashboardController.php; resources/views/admin/dashboard.blade.php; CSS riêng C01; tests/Feature/AdminDashboardTest.php. Shell P01 và LeaderboardService chỉ đọc.
## Những thay đổi đã thực hiện
Tạo tracking trước khi sửa source. Đọc context/ảnh C01 và hai tab; session59aa06cb-b54b-45f3-997f-41f78aea565d, không quét lại C02–C08.
## Quyết định kỹ thuật quan trọng
Chỉ thêm aggregate đọc; tháng/tuần theo submitted_at và timezone hiện có. Hàng chờ thực tế tách metrics demo theo contract DemoSubmissionScope. Tab GET có kiểm tra giá trị, giữ Admin middleware. Không thêm UI/copy ngoài Figma; không tạo dữ liệu mẫu runtime hoặc CTA giả.
## Kết quả kiểm thử
Chưa chạy; sẽ guard toyota_testing trước test AdminDashboard, build và screenshot1440.
## Những lỗi còn tồn tại
Empty state/prototype chi tiết chưa có thiết kế theo audit; không tự tạo câu chữ. Thanh KPI chính có kích thước Figma nhưng chưa có target nghiệp vụ, không suy ra mục tiêu giả.
## Task hoặc bước tiếp theo
Triển khai nội dung, kiểm tra dữ liệu/boundaries/privacy/links/tabs, screenshot so Figma và cập nhật kết quả ngắn tại đây.

## Kết quả cập nhật 2026-11-06
- **Đã sửa:** thay nội dung Dashboard cũ bằng đúng cấu trúc/copy C01: heading/date,3thẻ công việc,2KPI,10stats,4hàng pending gần đây, Top3. Shell P01 (kể cả logout đã chốt), Website/Dealer/Sales và nghiệp vụ giữ nguyên.
- **File P02:** `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`, mới `resources/css/admin-overview.css`,1entry Vite, `tests/Feature/AdminDashboardTest.php`;6PNG gốc MCP `public/admin/icons/c01-{calendar,arrow,chevron,clock,hero,winner}.png` từ160:320/342/376/357/331/538. Icon/component còn lại tái sử dụng P01; CSS chỉ tải C01, không URL Figma tạm runtime. Các thay đổi P01/Dealer có trước không thuộc task này.
- **Dữ liệu:** queue/oldest/quá48h theo submitted_at, strict `< now-48h`; pending Manager role dealer. Sales quá3ngày theo lần resubmit→pending gần nhất trong lịch sử account, fallback created_at cho lần đầu. Approved tháng theo submitted_at/timezone/bounds hiện có. Dealer≥5 dùng approved toàn thời gian theo snapshot dealer_id, gồm QR; mẫu số tổng Dealer đã lưu, gồm inactive (label không ghi tháng/active). Metrics vẫn DemoSubmissionScope; queue/list vẫn mọi bản ghi theo contract cũ, có thể khác metrics khi cấu hình loại demo. Không đổi scoring/tie-break LeaderboardService.
- **CTA/tabs/privacy:** validate GET `ranking=dealers|sales`; Top3 vào chi tiết đúng loại. Stats giữ status/submitted_from/to, tổng khách giữ `status=`. Queue Manager, hàng chờ pending, awards.index có form tạo kỳ và leaderboard đều là route thật; không CTA C01 giả. Phone mask, text escaped, eager loading, không notes/PII đầy đủ hoặc mutation. C03/C08 tĩnh thuộc P01 giữ nguyên.
- **PASS:** preflight actual loopback MySQL `toyota_testing`/15tables, bypass config cache bằng APP_CONFIG_CACHE process-local. Lượt cuối `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result tests/Feature/AdminDashboardTest.php`: **17tests/82assertions**. Kiểm tra clock48h/72h/resubmit, tháng, Dealer≥5/QR/snapshot, tabs/filter/privacy/quyền. Lượt đầu fixture QR thiếu field CHECK và assertion tab quá chặt whitespace đã sửa; không đổi constraint/backend để chiều test. Fixture privacy6phone riêng của P01 được giữ.
- **PASS:** `npm run build`74modules; Pint --test hai file PHP trên; git diff --check (chỉ cảnh báo CRLF). Không full regression, migrate/seed/reset hoặc ghi local toyota.
- **Ảnh:** fixture cách ly xuất HTML từ GET Dashboard có middleware, transaction rollback **1test/2assertions PASS**. Playwright click hai tab GET, geometry/counts/ảnh/font/JS/network PASS. Đã xem và so trực tiếp [Đại lý1440](qa/artifacts/p02-admin-overview-20261106/c01-dealers-1440.png), [Sales1440](qa/artifacts/p02-admin-overview-20261106/c01-sales-1440.png) với [Figma](qa/artifacts/p02-admin-overview-20261106/figma-c01.png). Cards552/276/276×270, KPI558×132, stats216×96/118, queue756/rank360×394 đúng metadata; content x280/y92. Đây là dữ liệu fixture kiểm thử, không ảnh tài khoản local thực.
- **Giới hạn/bước sau:** Empty frame NOT FOUND/BLOCKED phần thiết kế: giữ khung/count0, không thêm copy, bỏ Cũ nhất khi không có timestamp. Track KPI chưa có công thức/mục tiêu; đã hỏi tùy chọn, chưa có đặc tả nên tạm giữ hình học Figma như trang trí aria-hidden, không % tiến độ giả. Cần chốt mẫu số nếu muốn track dữ liệu. Sales variant chỉ có thiết kế tab, dùng chung cấu trúc hàng với dữ liệu Sales thật; không tự nhận có frame nguyên trang Sales riêng. Không nghiệm thu Admin Mobile. Kết quả này cập nhật ghi chú “chưa chạy/tiếp theo” ban đầu; không cần quét lại C01–C08. Server QA đã dừng.

## Tiếp tục cùng task — 2026-11-10
- **IN PROGRESS:** đọc lại source, C01/P02 và trực tiếp Figma 160:233/173:1465 (session d971b20e-2360-4989-8829-2d529c3aae11). Bản triển khai đã có sẵn; không quy các file cũ thành thay đổi mới.
- Preflight mới PASS: actual loopback MySQL `toyota_testing`, 15 bảng, bypass config cache riêng tiến trình; giữ bootstrap không migrate/seed/reset. Sẽ chỉ chạy AdminDashboard, build và ảnh 1440 của C01.
- Git status đang bị dubious ownership ngay cả với `-c safe.directory`; chưa xác minh được diff nền, không thay cấu hình Git toàn máy.

### Kết quả tiếp tục 2026-11-10
- **DONE phạm vi Desktop đã có thiết kế; BLOCKED phần empty frame/công thức thanh KPI như giới hạn 06/11.** Bản triển khai C01 đã có sẵn trong HEAD. Sửa mới đúng 2 file source `resources/views/admin/dashboard.blade.php`, `resources/css/admin-overview.css`: toàn bộ 10 ô thống kê là liên kết như Figma, thay vì chỉ con số; giữ href/filter, màu và hình học. Không sửa controller, tests, shell P01, scoring/attribution/moderation/privacy hoặc dữ liệu hiện có.
- **PASS mới:** `npm run build` (74 modules); `php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --process-isolation --do-not-cache-result tests/Feature/AdminDashboardTest.php .laravel-tools/P02VisualFixtureTest.php` (18 tests/84 assertions = AdminDashboard 17/82 + fixture ảnh 1/2). Guard/actual database `toyota_testing` đã xác nhận trước; APP_CONFIG_CACHE riêng tiến trình, transaction rollback fixture, không migrate/seed/reset/full regression.
- **PASS ảnh sau sửa:** `node .laravel-tools/p02-recheck.mjs`; đã xem [Đại lý 1440](qa/artifacts/p02-admin-overview-20261110/c01-dealers-1440.png) và [Sales 1440](qa/artifacts/p02-admin-overview-20261110/c01-sales-1440.png), đối chiếu trực tiếp context/ảnh Figma 160:233 và tabs 173:1465 mới đọc. Cards 552/276/276×270, KPI 558×132, 10 stats, queue 756/ranking 360×394; vùng bấm cả 10 cards, hai tab GET, ảnh/font, không overflow/JS/network errors. Ảnh dùng HTML GET có middleware từ fixture cách ly, không phải luồng đăng nhập trình duyệt với dữ liệu local thật. Inventory ghi ranking348/gap24 đã bị context hiện tại xác nhận thay thế bởi360/gap12; source hiện tại đúng context.
- Đã khắc phục việc đọc Git status bằng Git `C:/Program Files/Git/cmd/git.exe` và safe.directory chỉ trong tiến trình. Working tree ban đầu sạch; diff cuối chỉ 2 source C01 và tracking này. `git diff --check` PASS (cảnh báo CRLF→LF). Giữ nguyên giới hạn empty/progress/Mobile và sai khác logout P01 đã duyệt; không phát sinh CTA C01 thiếu route. Server ảnh đã dừng sau kiểm tra. Bước tiếp theo chỉ cần đặc tả empty/công thức KPI nếu mở rộng nghiệm thu các phần đó.
