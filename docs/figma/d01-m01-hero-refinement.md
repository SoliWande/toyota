# D01/M01 — Hoàn thiện hero theo Figma mới

## Mục tiêu
Bổ sung5 quyền lợi/icon, kiểm tra toàn hero desktop/mobile theo frame209:233/99:376. Giữ CTA/count dealer thật và backend; không C07/C08, migration hoặc deploy.
## Trạng thái
IN PROGRESS — 13/11/2026, tracking trước sửa source; không mở phase mới.

Cập nhật 13/11/2026: **DONE phạm vi hero D01/M01** sau build, browser và đối chiếu ảnh. Không nghiệm thu section khác ngoài hero hoặc luồng DB/backend trong lượt UI này.
## Hiện trạng trước khi chỉnh sửa
Đã đọc AGENTS/tracking CTA trước, Blade/CSS/layout/browser harness. Working tree có source/backend/tests C07 và CTA từ task trước, giữ nguyên. Hero thiếu5 quyền lợi và dealer badge trong hero; font trong browser offline fallback do layout dùng Google Fonts. Figma full-frame context/screenshot đã đọc lại, session a9e7c3f5-4f93-450d-aafd-5b536c6dff30. Task mới mở rộng scope hero, thay thế giới hạn chỉ CTA của tracking trước, không xóa lịch sử.
## Các file source code liên quan
resources/views/dealers/registration/landing-content.blade.php; resources/css/dealer-registration.css; public/dealer-registration/hero-*.svg; harness D01 chỉ phục vụ kiểm UI. Backend/PublicDealerController và tests count đọc tham chiếu, không sửa.

File thực sửa: `resources/views/dealers/registration/landing-content.blade.php`, `resources/css/dealer-registration.css`, tracking này và12 PNG icon mới `public/dealer-registration/hero-{dealer,gift,service,chat,expert,event}-{desktop,mobile}.png`. Không tạo SVG: giới hạn export và fallback được ghi dưới. Không sửa source/backend/test PHP/JS có sẵn. Build assets/QA harness/screenshot là output ignored.
## Những thay đổi đã thực hiện
Chưa sửa source tại thời điểm khởi tạo. Sẽ ghi kết quả bên dưới.

### Kết quả triển khai
- Thêm “Quyền lợi khi tham gia” sau mô tả, trước CTA; đúng5 mục theo thứ tự: Logo & quà tặng; Ưu đãi bảo dưỡng; Chia sẻ kinh nghiệm; Chuyên gia khám xe; Sự kiện & chuyến đi. Markup section/h2/ul/li, icon trang trí alt rỗng; không thêm copy ngoài Figma.
- Desktop660px: nền trắng10%, border trắng18%, blur10px, radius24, padding18px20px, gap14; grid3 cột gap14/12, circle34/icon18, Inter50014.5/20, heading60015/20. Mobile342px: radius20, padding14px10px, gap12; grid2 cột gap10/8, circle24/icon14, Inter50013/17, heading60014/19. Tái dùng nguyên asset xuất đúng node, không vẽ lại hoặc thay icon chung gần giống.
- Kiểm cả hero phát hiện dealer badge mới thiếu: thêm badge “Đăng ký qua đại lý” với **`$dealer->name` thật**, style/phone không thay backend. Desktop circle36/icon18, mobile circle30/icon16, nền trắng12%/border22%/blur8px. Badge mobile top23/right24, logo top24/left24; tên dài có ellipsis, không hardcode Toyota An Sương runtime.
- Copy desktop gap24; mobile top88/left24/width342/gap12, heading38/42, mô tả15/22; chỉnh accent mobile22px/gap10 đúng context. Bổ sung font local Inter/Montserrat Vietnamese đã có bằng alias **D01Inter/D01Montserrat chỉ dùng trong hero**; không đổi font trang khác. Browser CDP nhận Inter-Medium custom font, đo các weight400/500/600/700 có metrics khác nhau, không font giả/fallback ở hero.
- Mobile hero theo context `120:336` cao937; artwork780 đặt top157 theo `124:500`, không sửa file xe/background cũ. Gradient “Nối nền trời” `345:443` cao337, mốc46.588% được bổ sung sau ảnh kiểm tra thấy seam ở157px. Hero screenshot sau sửa đã không còn seam.
- CTA/count đã triển khai giữ nguyên biểu thức/DTO, không dùng1126 mẫu. Giữ cả sticky CTA hiện hữu và điều kiện active, không thêm control hoặc đổi flow. Full-page ở viewport390×844 vẫn có sticky CTA như trước; ảnh so trọn hero dùng viewport390×1040 để sticky nằm ngoài vùng hero, không sửa DOM/CSS để giấu control trong screenshot.
## Quyết định kỹ thuật quan trọng
Dùng SVG Figma desktop/mobile đúng kích thước, không tự vẽ icon. Nội dung dealer động và CTA/count giữ nguyên. Chỉ kiểm/sửa hero, không phát triển section/flow ngoài hero.

Cập nhật định dạng asset: tải trực tiếp URL SVG từ design context thất bại do mạng; `use_figma` export SVG **bị công cụ từ chối vì cần approval trong khi approval policy=never**. Không yêu cầu sửa quyền/global config hoặc bỏ qua hạn chế. Fallback bằng `get_screenshot` từng node icon với `contentsOnly=true` để có PNG trong suốt đúng artwork Figma. Lượt export đầu mặc định có nền ancestor; đã thay bằng bản isolated và kiểm lại ảnh. Không gọi tải SVG là thành công, không dùng URL tạm runtime. PNG native18/14px là giới hạn độ nét trên màn hình mật độ cao, không nhận là vector.

Mapping asset/node desktop→mobile: dealer349:1020→345:403; gift346:1013→345:415; service346:1022→345:424; chat346:1027→345:429; expert346:1032→345:434; event346:1038→345:440.
## Kết quả kiểm thử
Chưa chạy. Browser render fixture cách ly không ghi DB; build/check source và ảnh mới1440/390 sau sửa. Không chạy bộ test ghi DB để kiểm UI.

### Kiểm chứng mới thực chạy

| Kiểm tra | Kết quả |
|---|---|
| Figma | Đọc lại **toàn bộ** design context + screenshot209:233/99:376, không chỉ CTA. Đối chiếu thêm screenshot riêng hero209:260/120:336; reference PNG lưu trong thư mục QA dưới. |
| `npm run build` | **PASS89 modules**, log `.laravel-tools/d01-hero-build.log`; manifest51 entries, không thiếu file/CSS/assets tham chiếu, không public/hot. Build lại sau sửa gradient/accent, không sửa source UI sau build cuối. |
| `php .laravel-tools/d01-hero-render.php` | **PASS** render Blade hiện tại với real Vite assets; DB_CONNECTION vô hiệu hóa, session/cache array, Dealer/count0/2 in-memory fixture. Không gọi controller/query hoặc ghi DB; dữ liệu được gắn nhãn fixture, không coi là test backend mới. |
| `node .laravel-tools/d01-hero-browser.mjs` | **PASS6 ca**, Chrome headless1440/390/320 × count2/0. Assert đúng5 text/icon/order, grid3/2, radius24/20, blur10/background10%, loaded icons18/14, font local, CTA href/label/count, không pageerror/asset missing/overflow hoặc POST. 390px đo card342×153 tại y270, button204×46/gap14; desktop gap16/button54 giữ nguyên. Kiểm320 bổ sung không tràn. |
| Asset/visual | Đã mở ảnh Figma hero1440/mobile và ảnh app1440/390 sau sửa. Kiểm ảnh icon isolated, rendered slot/kích thước bằng browser. Artwork/font có thể khác rasterization nhỏ giữa Figma và Chrome; không gọi là pixel-perfect tuyệt đối hoặc nghiệm thu toàn trang. |
| Scope/hash/diff | **PASS** `git diff --check` (chỉ notice CRLF→LF); backend PublicDealerController SHA256 vẫn `145e04ae90dd418dd36e1507b23c0b6e522ebee60c2956be1c8617d525707e78`, khớp trước task. C07 CSS/Blade/JS và C08 CSS/JS khớp inventory trước task. Không sửa các edits/tracking/test của task trước. |
| PHPUnit/Pint/DB regression | **NOT_RUN**: lượt chỉ Blade/CSS/assets, không sửa PHP backend; Blade đã compile/render và browser mới. Không dùng19tests/98assertions lịch sử làm kết quả mới. Không migration/seed/reset/production network/deploy. |

Ảnh đối chiếu mới trong `.laravel-tools/d01-hero-browser/`:
- Desktop: `hero-1440-owners-2.png` ↔ `figma-hero-1440.png`.
- Mobile trọn hero: `hero-390-comparison.png` ↔ `figma-hero-390.png`. Figma frame screenshot có thể crop ở844px; context hero thực cao937px. Ảnh app comparison viewport390×1040 để chụp trọn hero937 không bị sticky cũ che.
- Ảnh app viewport390×844 nguyên trạng: `hero-390-owners-2.png`; full pages/count0/320 và `checks.json` cùng thư mục. Ảnh fixture không phải dữ liệu hoặc session production.
## Những lỗi còn tồn tại
Chưa có implementation/bằng chứng browser mới; chưa nghiệm thu hero.

Cập nhật kết thúc:5 quyền lợi và các thiếu sót liên quan trong hero đã hoàn thiện/kiểm chứng. Giới hạn còn lại: icon PNG native thay SVG do policy export, sticky CTA cũ vẫn hiện theo viewport, rasterization có thể khác nhẹ. Không mở rộng chỉnh các section ngoài hero theo full frame; các lỗi test Admin/Dealer ghi ở task trước không thuộc lượt UI này.
## Task hoặc bước tiếp theo
Lấy asset, triển khai/UI review, build/browser/screenshots và ghi rõ giới hạn. Không deploy.

Cập nhật kết thúc: bàn giao2 source UI,12 assets và ảnh mới để review; không cần migration hay thay backend. Có thể thay PNG bằng SVG đúng node khi môi trường export được phép; không phải điều kiện để thay artwork/chức năng hiện tại. Không tự commit/push/deploy.
