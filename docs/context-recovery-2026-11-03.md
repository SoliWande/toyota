# Khôi phục ngữ cảnh dự án — 2026-11-03

## Mục tiêu và phạm vi

Khôi phục lịch sử triển khai, đối chiếu tài liệu với source hiện tại và xác định
công việc tiếp theo. Root Laravel: `F:\laragon\www\toyota`.

Đây là đánh giá, không phải một đợt triển khai. Không sửa source, database,
migration hoặc tracking cũ; không chạy build, migrate, seed hay feature test ghi
database. File báo cáo mới này là thay đổi duy nhất của tác vụ.

## Tài liệu đã đọc

Đã đọc đủ 18 tài liệu người dùng yêu cầu trong `docs/figma/`:

- `phase-1-implementation.md`.
- `phase-2a-demo.md`, `phase-2a-implementation.md`, `phase-2b-implementation.md`.
- `phase-2c-1-integration.md`, `phase-2c-2-data-integrity.md`,
  `phase-2c-3-release-readiness.md`.
- `phase-2c-4a-controlled-approval.md`, `phase-2c-4b-demo-isolation.md`,
  `phase-2c-4c-final-readiness.md`.
- `phase-3a-registration-lookup.md`, `phase-3a-2-lookup-states.md`,
  `phase-3a-3-lookup-submit-fix.md`, `phase-3a-4-d12-ui-fix.md`.
- `phase-3b-full-rules.md`, `phase-3b-ui-refinement.md`.
- `lookup-d7-d15-sync.md`, `landing-fidelity-refinement.md`.

Đã đọc thêm `SPEC.md`, README và các báo cáo root về QA, bảo mật, nâng cấp
Laravel, tích hợp, hiệu năng, Dealer/profile/dashboard, Admin inline edit,
địa chỉ, điều hướng tháng, public privacy, public Sales, thiết lập Dealer local
và demo thực tế. Đọc thêm phân tích Figma ban đầu, typography, D04, validation
bước hai, checklist browser, README screenshot và tài liệu dữ liệu/demo/avatar.

Tài liệu mô tả từng thời điểm; không xem tất cả kết luận lịch sử là trạng thái
hiện tại. Các đường dẫn `D:\laragon\...` và số lượng dữ liệu local cũ không xác
nhận trạng thái môi trường đang ở ổ F.

## Kiến trúc đối chiếu từ source

- Composer yêu cầu PHP 8.5 và Laravel 13; lock chứa Laravel 13.37.0,
  Sanctum 4.3.3, PHPUnit 12. Runtime CLI hiện tại PHP 8.5.11, Node 18.8.0.
- MVC Blade, Eloquent, FormRequest, Policy/middleware; Actions xử lý nghiệp vụ,
  Services phụ trách intake, tra cứu, xác minh, leaderboard và privacy.
- Frontend Blade + Tailwind 4 + Vite 6 + JavaScript modules. Có bundle riêng
  `dealer-registration.css/js`, không phải React/Vue SPA.
- Ba vai trò Admin, Dealer, Sales; có trạng thái tài khoản và trạng thái hồ sơ
  riêng. Evidence/avatar/ảnh Dealer dùng private disk và controller kiểm soát.
- Routes public, Admin, Dealer, Sales ở `routes/web.php`; Sanctum user endpoint
  ở `routes/api.php`. Route Dealer slug đứng sau các route hệ thống.
- Schema và protocol intake phụ thuộc MySQL/InnoDB, transaction/row lock và một
  số advisory lock; không coi SQLite là môi trường tương đương cho concurrency.
- `config/app.php` hiện dùng UTC; kỳ thống kê không mặc định theo Asia/Saigon.

## Tiến độ và trạng thái triển khai

| Nhóm | Kết quả có trong source |
| --- | --- |
| Nền tảng V1 | Auth, quản lý Dealer/Sales, khai báo khách, ảnh bằng chứng, duyệt/từ chối, leaderboard, tạo bản nháp vinh danh và preview |
| Nâng cấp và mở rộng | Laravel 13; tài khoản Dealer/dashboard; QR/slug ổn định; hồ sơ Sales public giới hạn; địa chỉ hành chính; Admin inline edit và audit |
| Phase 1 | Giao diện D01–D06 và component đăng ký riêng |
| Phase 2A | Draft hai bước mã hóa, session/Dealer scope, TTL; demo local riêng |
| Phase 2B–2C2 | Final submission, ODO, receipt/idempotency, mutex phone/plate, guard cập nhật và lịch cleanup |
| Phase 2C3–2C4C | Runbook readiness, chặn approval xung đột, audit dữ liệu, cách ly demo trong thống kê; nghiệm thu production vẫn còn điều kiện |
| Phase 3A | Tra cứu phone + plate; trạng thái có bằng chứng; Admin xác minh group/blocker và yêu cầu bổ sung; khách bổ sung Facebook có quyền |
| Phase 3A3–3A4 | Sửa submit/exception view, fallback khi thiếu bảng workflow, phân biệt unknown với D12, giao diện D12 |
| Phase 3B | D9 thể lệ đầy đủ, mục lục/anchor, refinement theo Figma; bản sau bỏ banner và production gate của bản trước |
| Refinement gần nhất | Landing/D04/validation; đồng bộ D7–D15, local font/icon, bộ ảnh QA |

Đã đọc routes/controllers/models/actions/services/views liên quan và migrations
000023/000024. Chưa kiểm tra trạng thái migration trên database thật. Có code
không đồng nghĩa tính năng đã được kích hoạt hoặc nghiệm thu trên môi trường này.

## Quy tắc phải giữ

1. Chỉ hồ sơ approved có điểm; một hồ sơ một điểm. Kỳ theo `submitted_at`,
   không phải ngày duyệt. Điểm Dealer theo snapshot `submission.dealer_id`.
2. QR tạo nguồn `dealer_qr`, không gán Sales. Chuyển đại lý của Sales không
   chuyển điểm lịch sử. Admin chỉ sửa attribution có kiểm soát khi pending.
3. Phân biệt account status, moderation status và group progress. Bấm link
   Facebook không chứng minh membership hay approval. Không có bằng chứng là
   unknown; không tự gán D12 hoặc coi tra cứu thất bại.
4. Pending/approved chiếm phone/plate đã chuẩn hóa; rejected không chiếm.
   Writer phải đi qua protocol intake/mutex. Raw SQL/bulk update có thể vượt
   guard instance Eloquent, không được dùng tùy tiện.
5. Approval cần kiểm tra lại xung đột và Facebook đã xác minh; giữ uniqueness
   approved Facebook và audit. Facebook do khách khai chưa phải đã xác minh.
6. Phone + plate chỉ cấp kết quả tra cứu tối thiểu đã che dữ liệu; quyền bổ sung
   còn phụ thuộc receipt/session, request/version và trạng thái hợp lệ.
7. Draft TTL 30 phút dùng file cache chỉ định rõ; receipt TTL 24 giờ. Không tùy
   tiện đổi APP_KEY, xóa cache hoặc thay storage làm mất draft/receipt.
8. Giữ Dealer slug/QR và public Sales slug ổn định; giữ private upload, CSRF,
   throttle, honeypot, no-store và audit. Consent xử lý không phải consent công bố.
9. Demo chỉ được loại khỏi thống kê bằng ownership manifest đã xác minh; không
   đoán qua email/tên rồi xóa. Không backfill dữ liệu lịch sử để khớp giao diện.

## Chênh lệch tài liệu và vấn đề còn lại

- `SPEC.md` chưa phản ánh đầy đủ ba vai trò, public Sales và luồng mới. Báo cáo
  phân tích Figma ban đầu còn mô tả nhiều chức năng hiện đã được triển khai.
- Nhận định cũ "chưa có screenshot" đã được bổ sung bằng đợt D7–D15. Hiện có
  27 ảnh final (9 màn × 1440/390/768). JSON final có 9 bản ghi desktop: không
  có overflow, lỗi JS/request local hay font failure được ghi nhận. Đây là
  evidence lịch sử của router QA dùng Blade/build thật với fixture, không phải
  kiểm thử khách hàng thật xuyên suốt. Không chạy browser/Figma lại trong đợt này.
- D9 có trong hồ sơ Figma và là trang thể lệ; không phải màn tra cứu bị thiếu.
- D7/D11–D15 có khác biệt privacy có chủ đích; D7 không có quyền chỉ liên hệ.
  D8/D13 không hứa 48 giờ; D9 thiếu Dealer context; hotline/privacy link tùy
  cấu hình; D15 dùng số/lý do thật. Không khôi phục dữ liệu mẫu để giống ảnh.
- Chưa nghiệm thu toàn bộ M7–M15; D01–D06 còn cần browser/font/crop/focus và
  screenshot đối chiếu ở phiên bản hiện tại. Không kết luận khớp tuyệt đối Figma.
- Lookup có fallback thiếu bảng verification, nhưng Admin detail vẫn query
  bảng audit trực tiếp. Thiếu migration 000024 vẫn có thể làm hỏng Admin workflow.
  Đây là rủi ro có điều kiện; chưa xác nhận database hiện tại đang thiếu bảng.
- Cleanup receipt mặc định tắt; chưa xác nhận scheduler/retention/infra hiện tại.
  Cột expires_at chưa có index trong migration 000023. FK restrict giữ audit có
  thể xung đột UX hard-delete hồ sơ pending; cần quyết định retention trước sửa.
- Mutex toàn cục và scan chuẩn hóa cần đo tải. Alias Facebook username/numeric
  chưa được giải quyết đầy đủ; không tự thêm scraping/API suy đoán identity.
- Nội dung thể lệ đang render ở mọi môi trường theo refinement sau cùng, nhưng
  nội dung/đợt chiến dịch/hotline/quyền lợi cần chủ sở hữu xác nhận trước phát hành.
  Copy về hủy hồ sơ không có nghĩa đã có chức năng đảo điểm hồ sơ approved.
- Chưa có endpoint/workflow publish award; hiện có tạo draft và preview.
- Manifest demo cũ và award snapshot demo cần đánh giá riêng. Gói candidate
  Phase 2C4C không đại diện source/schema mới sau Phase 3A.
- Chưa có xác minh mới về full suite DB, Linux/no-dev deployment, backup,
  session/cache shared, load và dependency advisories. Không xác nhận production ready.

## Kiểm chứng thực hiện trong lần khôi phục

- PHPUnit thuần, không bootstrap Laravel, không cache kết quả:
  `php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/FacebookProfileUrlTest.php tests/Unit/SubmissionIntakeNormalizationTest.php`
  → PASS, 28 tests / 41 assertions.
- Bốn DOM harness `frontend-check.mjs`, `step-two-validation-check.mjs`,
  `lookup-validation-check.mjs`, `supplement-validation-check.mjs` → PASS,
  chạy Node với `--experimental-specifier-resolution=node`.
- `node docs/figma/qa/rules-css-check.mjs` → FAIL, exit 1, dòng 50: vẫn đòi
  `min-width: 2ch`. Đồng bộ Figma sau đó đã bỏ min-width này. Cần cập nhật
  assertion theo thiết kế, không thêm lại CSS chỉ để qua test.
- Đọc manifest Vite và evidence có sẵn. Không build/chụp browser mới, không
  chạy feature test vì có thể ghi database dù sử dụng transaction/rollback.

Các số 235 tests / 1989 assertions của Phase 2C4C và 49 tests / 599 assertions
của lookup-sync là kết quả ghi trong lịch sử, không phải đã chạy lại hôm nay.

## Công việc tiếp theo đề xuất

1. Kiểm tra read-only migration/schema/runtime/config không lộ secrets; xác định
   môi trường test riêng trước mọi feature/concurrency test.
2. Đồng bộ harness CSS thể lệ với Figma hiện hành; giữ kiểm tra nowrap, số mục,
   responsive và không overflow có ý nghĩa.
3. Kiểm thử luồng thật trong database test cách ly: draft → final → receipt →
   tra cứu → yêu cầu bổ sung → bổ sung → xác minh/duyệt; thêm tình huống session
   khác, stale version, bảng thiếu, xung đột và Dealer inactive.
4. Nghiệm thu browser D01–D15/mobile theo frame Figma; phân biệt sai lệch cần
   sửa với khác biệt privacy/nghiệp vụ đã được ghi nhận.
5. Chốt nội dung/cấu hình phát hành; scheduler/retention, demo isolation và
   publish award thành các task riêng, không gộp âm thầm vào sửa UI.
6. Chuẩn bị candidate mới, dependency audit và rehearsal Linux/no-dev; chỉ xác
   nhận readiness sau bằng chứng kiểm thử/backup/rollback thực tế.

## Quy trình tracking tiếp tục áp dụng

Mỗi task/phase có Markdown riêng, đọc tài liệu liên quan trước khi sửa; ghi mục
tiêu, phạm vi, file thay đổi, cách triển khai, lệnh/kết quả kiểm thử, giới hạn và
công việc còn lại. Phân biệt bằng chứng mới với kết quả lịch sử. Không ghi hoàn
thành nếu chưa kiểm chứng; không ghi đè/xóa tracking cũ. Kết luận mới supersede
kết luận cũ bằng liên kết và giải thích, không sửa lịch sử để che khác biệt.
