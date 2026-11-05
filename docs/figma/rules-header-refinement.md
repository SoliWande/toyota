# Đồng bộ header Thể lệ với Website Thi đua

## Mục tiêu
Thay header đăng ký tại campaign.rules bằng header/navigation chung Website Thi đua; kiểm tra nhanh1440/390, giữ nội dung/footer/route/nghiệp vụ và header các trang khác.

## Trạng thái
DONE — 2026-11-05; tracking tạo trước sửa code, kiểm tra nhanh1440/390 PASS.

## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS.md, phase-3b-full-rules và header-rules-link-refinement; route GET /chuong-trinh/the-le, CampaignRulesController, campaign/rules dùng dealer-registration.layout với rules=true. Header cũ dr-header Desktop chỉ logo/Đăng ký ngay; Mobile dr-inner giấu logo/actions, chỉ backbar. Trang chủ/BXH/Vinh danh/Đại lý dùng competition.layout, shared navigation chưa có active campaign.rules. Git có thay đổi importer/header-link từ task trước; giữ nguyên.

## Các file source code liên quan
competition/layout/navigation/brand/icon, dealer-registration/layout, campaign/rules, CSS/JS competition và dealer-registration (chỉ đọc nếu không cần sửa).

## Những thay đổi đã thực hiện
Đọc metadata/context/screenshot Figma header Desktop214:234 và Mobile150:234, thuộc B01 Desktop214:233/Mobile150:233 trong đúng page Website Thi đua207:333/148:252, session d9ac1170-277f-42a7-84bf-de2990b4e347. Desktop72/container1200/logo49.831×36/gap14, Mobile60/logo/menu40; tái sử dụng assets hiện hữu. Chuẩn bị tách header hiện có để dùng chung.

Thực hiện: tách nguyên markup/account handling từ competition/layout sang **competition/header.blade.php**, layout gọi component này với dealerProfile như cũ. dealer-registration/layout chỉ ở rules=true tải CSS/JS competition hiện hữu, thêm scope competition-site để menu/scroll lock hoạt động, thay dr-header bằng component chung. navigation thêm aria-current cho campaign.rules. Không viết CSS/JS/asset mới, không sửa campaign/rules/content/controller/route hoặc footer. Tổng4file Blade (1mới), tracking này; helper kiểm tra nằm ignored.

## Quyết định kỹ thuật quan trọng
Không chuyển toàn bộ trang sang layout khác vì sẽ đổi footer/content styles. Chỉ nhánh rules=true nhận header competition và assets tương ứng; không duplicate header/CSS. Active Thể lệ chỉ khi campaign.rules.

## Kết quả kiểm thử
`node .laravel-tools/rules-header-quick.mjs before` và `after` PASS: HTTP200, header72px Desktop1440/header60px Mobile390, logo đúng slot; không tràn ngang/lỗi JS. Mục Thể lệ active ở cả2nav, menu mở/đóng bằng Escape và click BXH tới đúng route. Đã xem screenshot1440/390 và đối chiếu Figma header. So trước/sau Header HTML+geometry của Home/BXH/Vinh danh/Dealer giống hệt; content/footer HTML của Thể lệ giống hệt, text/font/color/line-height và kích thước nội dung không đổi ngoài rounding0.016px (giới hạn đo0.25px). Không full regression hoặc build lại vì không đổi assets, không ghi DB. Git diff --check PASS.

Evidence ignored `qa/artifacts/rules-header-20261105/{before,after}.json`; [Desktop](qa/artifacts/rules-header-20261105/after-1440.png), [Mobile](qa/artifacts/rules-header-20261105/after-390.png), [menu](qa/artifacts/rules-header-20261105/menu-390.png). Chỉ kiểm tra nhanh, không thêm QA matrix/test source dài.

## Những lỗi còn tồn tại
Không còn lỗi trong phạm vi header đã kiểm tra. Chưa kiểm thử tài khoản đăng nhập hoặc các browser khác Chrome; giữ nguyên account handling dùng chung. Không chứng nhận lại toàn trang Thể lệ/Figma body, vì nội dung/footer nằm ngoài phạm vi.

## Task hoặc bước tiếp theo
Bàn giao thay đổi; dừng server PHP8771 tạm do task khởi tạo. Không commit/push/deploy hoặc chỉnh dữ liệu local.
