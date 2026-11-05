# Đồng bộ giao diện Đăng nhập B04

## Mục tiêu
Đồng bộ login Desktop/Mobile theo đúng Website Thi đua, giữ authentication/CSRF/session/validation/role redirect/middleware; kiểm tra nhanh1440/390, build và test auth liên quan.

## Trạng thái
DONE — 2026-11-05; tracking tạo trước sửa source, build/QA và test auth liên quan PASS.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, routes/controller/LoginRequest, auth/login, layout/form-input/admin-feedback, CSS/JS và AuthenticationTest/DealerAccountTest. GET/POST /login, LoginRequest email/password; Auth::attempt(validated), regenerate/forget intended rồi homeRoute. Không có remember field hoặc remember backend; không thêm nghiệp vụ mới. Giao diện cũ x-layout Tailwind khác B04. Working tree có header/importer/tracking task trước, giữ nguyên.

## Các file source code liên quan
auth/login, competition/header/brand/navigation, assets/fonts hiện có, Vite entries; AuthenticatedSessionController/LoginRequest/routes/tests chỉ đọc nếu không phát hiện lỗi.

## Những thay đổi đã thực hiện
Figma session980f0cea-6bae-4a29-9a9b-f9d7cfa420bd, file yKmWQI6BYJ6dponEnidlsq: metadata xác minh B04 Desktop217:472 (1440×816) trên page207:333, Mobile152:323 (390×844) trên148:252; đọc high-fidelity context+screenshot cả2. Không dùng Admin/Dealer. Tái sử dụng competition/header/logo/Inter/Montserrat Vietnamese; lấy đúng ảnh node217:498 và icon car22/18 từ context, không dùng screenshot nguyên màn làm background.

File thuộc task: `resources/views/auth/login.blade.php`; mới `resources/css/login.css`, `resources/js/login.js`, `public/competition/login/{scene.png,car-22.svg,car-18.svg}`; thêm2entry trong `vite.config.js`; tracking này. Không sửa shared layout/header/CSS hoặc PHP auth/request/model/routes/tests. CSS chỉ .wc-login-site; form vẫn POST /login +CSRF, email/password/autocomplete/max255/required và liên kết forgot/register thật. old email được escape và chỉ render khi là string để malformed old input không crash; không repopulate password. Hiện/Ẩn có aria và loading/pageshow reset, không thêm API hoặc remember. Asset nguồn scene780×1560, icon giữ native22/18; tải bằng curl được duyệt sau lỗi sandbox curl7 Bad access, không đổi policy hoặc tham chiếu URL Figma tạm ở runtime.

## Quyết định kỹ thuật quan trọng
CSS/JS login scope riêng; Desktop 480px artwork +696px card, Mobile heading ngoài card/form dọc/note riêng. Giữ labels/old email/password không repopulate/errors thật; không hardcode tài khoản minh họa. Show/hide chỉ đổi input type, loading chỉ chống submit lặp sau native validation, không thay POST bằng API hoặc auth backend. Không tự thêm remember.

## Kết quả kiểm thử
- **PASS build** `npm run build`66modules; `node --check resources/js/login.js`; Git diff --check.
- **PASS isolation** `APP_CONFIG_CACHE` riêng process trỏ file nonexistent; `php docs/figma/qa/competition-db-check.php` xác nhận actual toyota_testing/15tables, không dùng config cache local. Chạy PHPUnit với bootstrap competition-test-bootstrap.php, process-isolation/do-not-cache-result: **AuthenticationTest + DealerAccountTest:49 tests/352 assertions PASS**, lần cuối23.249s. Bao gồm credentials/validation/CSRF/session rotation/rate limit/role-status redirect và Dealer login; không migrate/seed/reset toyota, không sửa test để PASS. XML/log `.laravel-tools/login-auth-tests.*`.
- **PASS quick1440/390**: live GET Laravel /login,2screenshot, không overflow/JS error/broken assets. Desktop art x120/y112/480×640, card x624/y112/696×640; Mobile form x16/y172/358×368, owner note x16/y560/358×90 đúng context. Đã xem trực quan cả2ảnh so Figma. Default để trống email/password thay tài khoản minh họa theo yêu cầu dữ liệu thật; focus ring chỉ khi focus.
- Show/hide không đổi giá trị password, valid submit loading/disabled và pageshow reset PASS bằng UI event đã prevent POST; Mobile menu/links đúng. Error/wrong-password/missing-fields/array old-input render Blade bằng helper DB-disabled, không crash/overflow và password rỗng; đây là UI fixtures, auth flow thật được test trong PHPUnit toyota_testing. Fixture đầu dùng hostname CLI khác gây asset/overflow; sửa root URL trong **helper QA** sang cùng local server, không chữa runtime để qua fixture. Không POST credential vào local toyota.
- Evidence ignored [Desktop](qa/artifacts/login-20261105/login-1440.png), [Mobile](qa/artifacts/login-20261105/login-390.png), `qa/artifacts/login-20261105/checks.json`. Helpers ngắn ở `.laravel-tools/login-*`, không thêm QA/test source dài hoặc full regression. Các bundle competition cũ giữ nguyên hash, không sửa trang khác.

## Những lỗi còn tồn tại
Không còn lỗi trong phạm vi đã kiểm tra. Figma B04 không có frame error/loading riêng nên các trạng thái này dùng tokens/Laravel messages và không tuyên bố pixel-perfect cho chúng. Chưa kiểm tra browser khác Chrome, autofill/password manager thật hoặc BFCache thực tế (đã test pageshow handler). Backend chưa hỗ trợ remember như audit ban đầu, giữ nguyên; không tạo checkbox không hoạt động.

## Task hoặc bước tiếp theo
Bàn giao; dừng server PHP8771 tạm của task sau QA. Không deploy, commit/push hoặc chỉnh dữ liệu local. Nội dung/logic các trang khác giữ nguyên.
