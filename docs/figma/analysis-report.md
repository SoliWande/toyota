# Báo cáo phân tích Figma MCP và Laravel — Toyota Community

- Ngày tổng hợp: 02/11/2026.
- Workspace: `F:\laragon\www\toyota`.
- Figma: [Toyota Community — D01](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=209-233&m=dev).
- File key: `yKmWQI6BYJ6dponEnidlsq`.
- Phạm vi: D01–D06, các bản mobile tương ứng, luồng đăng ký trực tiếp qua Dealer và tích hợp với Admin approval hiện tại.
- Trạng thái: báo cáo phân tích, chưa triển khai.

## 1. Phạm vi và nguyên tắc bảo toàn

Yêu cầu nghiệp vụ đã được xác định:

1. D01 là landing page Dealer, chứa form đăng ký bước 1.
2. D02 là trang riêng cho bước 2; D03 là trạng thái validation của cùng trang này.
3. D05 là lỗi số điện thoại trùng trong form bước 1.
4. D06 là trạng thái QR không hợp lệ trong panel đăng ký bước 1.
5. D04 là trang đã nhận đăng ký thành công, không đồng nghĩa Admin đã duyệt.
6. Khách đăng ký trực tiếp qua Dealer, không gán Sales.
7. Chỉ sau bước 2 mới tạo bản ghi `pending` để Admin xác minh.
8. Chỉ bản ghi `approved` được tính điểm cho Dealer.
9. Giữ nguyên dữ liệu, lịch sử và logic Admin approval hiện tại.

Trong giai đoạn phân tích không sửa source code, không tạo file, không tải assets vào dự án, không chạy migration và không thay đổi database. Theo yêu cầu tổng hợp tiếp theo, chỉ tạo file Markdown báo cáo này. Không chạy test/build vì chúng có thể phát sinh artifacts và chưa thuộc phạm vi được yêu cầu.

Các đề xuất dưới đây chưa phải thay đổi đã thực hiện. Những đường dẫn file mới và routes mới đều là phương án triển khai, không phải thành phần đang tồn tại.

## 2. Kết nối Figma MCP và phương pháp khảo sát

### 2.1. Kết quả kết nối và quyền truy cập

- Figma MCP phản hồi thành công; `whoami` xác thực tài khoản Ngoc Phuc Nguyen, plan Starter, seat Full.
- `create_session` thành công cho file Toyota Community.
- Đọc thành công metadata, design context và screenshot của các frame được nêu bên dưới. Đây là bằng chứng quyền đọc file thực tế, không chỉ là phát hiện công cụ.
- Không thử quyền chỉnh sửa file Figma, không tạo hoặc thay đổi thiết kế.
- Đã đọc hướng dẫn `skill://figma/figma-design-to-code/SKILL.md` và truyền `skillNames: resource:figma-design-to-code` khi gọi `get_design_context`.
- Mã React/Tailwind do MCP trả về chỉ là tham chiếu thiết kế; triển khai cần chuyển về Blade, CSS và JavaScript của dự án.

### 2.2. Công cụ đã sử dụng

| Công cụ | Mục đích |
|---|---|
| `whoami` | Xác thực kết nối và tài khoản |
| `create_session` | Mở session khảo sát file |
| `get_metadata` | Xác định pages, frame, node, kích thước và cấu trúc |
| `get_design_context` | Đọc layout, typography, màu, component và nguồn assets |
| `get_screenshot` | Đối chiếu trực quan thiết kế |
| MCP resources | Đọc hướng dẫn skill và khám phá tài nguyên |

Danh sách 43 công cụ Figma đã phát hiện trong phiên kiểm tra:

```text
add_code_connect_map, create_generative_plugin, create_new_file,
create_session, create_shader, download_assets, export_video,
generate_diagram, generate_figma_design, generate_image,
get_code_connect_map, get_code_connect_suggestions,
get_context_for_code_connect, get_design_context, get_figjam,
get_generative_plugin, get_libraries, get_metadata, get_motion_context,
get_screenshot, get_shader, get_variable_defs, list_file_shaders,
list_generative_plugins, list_shaders, open_figma_mcp_app_in_thread,
search_design_system, send_code_connect_mappings, send_code_to_figma,
update_generative_plugin, update_shader, upload_assets, use_figma,
weave_cancel_tool_run, weave_find_model, weave_get_model_run_output,
weave_get_tool_inputs, weave_get_tool_run_output, weave_list_tools,
weave_run_model, weave_run_tool, weave_upload_asset, whoami
```

Danh sách công cụ không đồng nghĩa đã kiểm tra hoạt động hoặc quyền sử dụng của từng công cụ.

### 2.3. Pages xác định được

| Page ID | Tên |
|---|---|
| `7:2` | Hi-fi v1 |
| `14:2` | Audit & Giải pháp |
| `56:233` | Components |
| `57:801` | UI · Khách hàng – Mobile |
| `148:252` | UI · Website Thi đua – Mobile (B) |
| `159:316` | UI · Admin – Desktop (C) |
| `207:332` | UI · Khách hàng – Desktop |
| `207:333` | UI · Website Thi đua – Desktop (B) |

Thử metadata node `0:1` trả về không tìm thấy. Sau đó dùng `get_metadata` không truyền node để lấy danh sách pages và đọc đúng page `207:332`, `57:801`. Lỗi thử node này không phải lỗi quyền truy cập file.

## 3. Danh sách frame và mức độ đã kiểm tra

### 3.1. Desktop D01–D06

Tất cả sáu frame dưới đây đều đã đọc metadata trong cây page, design context và screenshot trực tiếp.

| Frame | Node ID | Kích thước | Vai trò |
|---|---|---|---|
| D01 · Trang đại lý | `209:233` | 1440 × 3075 | Landing và form bước 1 |
| D02 · Bước 2 – Thông tin xe | `211:287` | 1440 × 1337 | Trang bước 2 |
| D03 · Bước 2 – Lỗi nhập liệu | `211:450` | 1440 × 1489 | Validation của bước 2 |
| D04 · Đăng ký thành công | `212:377` | 1440 × 900 | Xác nhận đã nhận đăng ký và hướng dẫn vào group |
| D05 · SĐT đã đăng ký | `212:501` | 1440 × 847 | Lỗi trùng điện thoại bước 1 |
| D06 · QR không hợp lệ | `212:587` | 1440 × 765 | Trạng thái QR không dùng được |

Các node con D01 được đọc thêm để lấy thông số chi tiết: `209:278` — Form · Bước 1; `209:262` — Copy hero.

**Ưu tiên nghiệp vụ so với cách trình bày frame:** Figma tách D05, D06 thành màn minh họa độc lập. Khi triển khai theo yêu cầu hiện tại, hai trạng thái này nằm trong panel bước 1 của landing D01, không biến thành hai landing độc lập.

### 3.2. Mobile tương ứng

| Frame | Node ID | Viewport | Mức độ kiểm tra |
|---|---|---|---|
| M01 · Trang đại lý | `99:376` | 390 × 844 | Metadata, design context, screenshot |
| M02 · Bước 2 – Thông tin xe | `57:452` | 390 × 844 | Metadata, design context, screenshot |
| M03 · Bước 2 – Lỗi nhập liệu | `129:346` | 390 × 844 | Metadata và design context |
| M04 · Đăng ký thành công | `57:571` | 390 × 844 | Metadata và design context |
| M05 · SĐT đã đăng ký | `57:664` | 390 × 844 | Metadata và design context |
| M06 · QR không hợp lệ | `57:714` | 390 × 844 | Metadata và design context |

390 × 844 là viewport frame; nội dung có thể dài hơn và cần cuộn. Screenshot M01 chỉ thể hiện vùng hero/CTA đầu trang; các section phía dưới được xác định qua metadata và design context.

### 3.3. Frame liên quan mới xác định qua metadata

| Desktop | Node ID | Nội dung |
|---|---|---|
| D00 | `213:1650` | Mục lục prototype |
| D07 | `213:533` | Tra cứu: cần bổ sung |
| D08 | `212:647` | Đã gửi bổ sung |
| D09 | `213:1447` | Thể lệ đầy đủ |
| D10 | `213:636` | Tra cứu: không tìm thấy |
| D11 | `213:702` | Tra cứu: chưa hoàn tất |
| D12 | `213:797` | Tra cứu: chờ vào group |
| D13 | `213:894` | Tra cứu: chờ duyệt |
| D14 | `213:989` | Tra cứu: đã là thành viên |
| D15 | `213:1086` | Tra cứu: chưa thể xác nhận |
| Tương tác chọn xe D01 | `222:950` | Frame minh họa tương tác |
| Tương tác chọn xe D05 | `222:1016` | Frame minh họa tương tác |

Mobile còn có M00 `57:1000`, M07 `57:733`, M08 `57:941`, M09 `57:963`, M10 `204:366`, M11 `204:446`, M12 `204:541`, M13 `204:638`, M14 `204:736`, M15 `204:833`.

Banner mobile có frame riêng `123:342`, tên “Banner mobile · ảnh (Export PNG @2x)”, kích thước 390 × 780. D01 desktop sử dụng instance “Banner desktop” `223:854`.

Chưa phân tích sâu D07–D15, chưa xác minh toàn bộ liên kết prototype hoặc mọi trạng thái tương tác.

## 4. Phân tích từng màn

### 4.1. D01 — Landing Dealer và bước 1

Thứ tự các phần desktop:

1. Header khách, logo Toyota, thương hiệu Veloz & Hilux, tra cứu, chip Dealer, CTA đăng ký.
2. Hero: ảnh xe trên đường núi, tiêu đề “KẾT NỐI HÀNH TRÌNH”, mô tả, CTA và số chủ xe.
3. Panel bước 1: giới thiệu Dealer, tiến trình, cam kết riêng tư, họ tên, điện thoại, dòng xe, liên kết tra cứu và nút Tiếp tục.
4. Ba quyền lợi: ưu đãi dịch vụ, sự kiện/chuyến đi, hỏi đáp kỹ thuật viên.
5. Panel nền tối “3 bước là xong”.
6. Thể lệ với khoảng thời gian áp dụng, accordion; FAQ dạng hội thoại.
7. Thông tin Dealer, địa chỉ, giờ mở cửa và gọi điện.
8. Footer chương trình, thể lệ, bảo mật, hotline.

Lựa chọn xe: Veloz, Hilux, Fortuner, FJ. Khi chọn dùng nền hồng nhạt và border đỏ. Nên triển khai bằng radio/nhãn có ngữ nghĩa truy cập, không chỉ div đổi màu.

Tên Toyota An Sương, địa chỉ, giờ mở cửa, số `1.126` và các ngày 01.11.2026–31.01.2027 là dữ liệu thiết kế, chưa được xác nhận là dữ liệu production.

### 4.2. D02 và D03 — Trang bước 2

Desktop có liên kết quay lại, heading, tiến trình hai bước, form chính bên trái và sidebar bên phải.

| Nhóm | Trường/thành phần |
|---|---|
| Địa chỉ | Tỉnh/Thành phố bắt buộc; Phường/Xã bắt buộc |
| Xe | Dòng xe từ bước 1 và nút Đổi; biển số bắt buộc; năm đăng ký lần đầu bắt buộc; ODO tùy chọn |
| Facebook | Link trang cá nhân bắt buộc; hướng dẫn sao chép liên kết |
| Xác nhận | Checkbox consent, liên kết điều khoản, nút Hoàn tất đăng ký |
| Sidebar | Tóm tắt họ tên/điện thoại/dòng xe, nút Sửa, hướng dẫn sau khi gửi, Dealer |

D03 dùng lại cấu trúc D02 và bổ sung:

- Tổng hợp “Còn 3 chỗ cần sửa: biển số xe, link Facebook và ô đồng ý điều khoản”. Khi triển khai phải tính từ lỗi thực tế.
- Biển số đã có người đăng ký, hướng dẫn gọi Dealer.
- Link Facebook chưa đúng định dạng trang cá nhân.
- Chưa đồng ý điều khoản.
- Input lỗi có nền hồng, border đỏ, icon và mô tả dưới trường.

Form cần giữ dữ liệu của chính draft khi validation thất bại, không lấy nhầm `old input` từ luồng Sales/Admin.

### 4.3. D04 — Đã nhận đăng ký

- Biểu tượng thành công; thông báo “Đăng ký thành công!”.
- Hướng dẫn bước cuối vào Facebook group.
- Ảnh group, tên cộng đồng, số thành viên, tính riêng tư.
- Nhắc khách trả lời điện thoại và biển số giống thông tin đã đăng ký.
- CTA Facebook và nhắc dùng đúng tài khoản đã khai báo.
- Timeline: đã nhận thông tin → vào group → thành viên chính thức.
- Tra cứu trạng thái và thông tin Dealer.

Trang này chỉ xác nhận intake đã thành công. Trạng thái DB vẫn là `pending`; không tự duyệt, không cộng điểm và không xác nhận thành viên chỉ vì khách bấm link Facebook.

### 4.4. D05 — Điện thoại đã đăng ký

- Lỗi đặt dưới trường điện thoại.
- Panel hướng dẫn khách đã đăng ký vào group hoặc tra cứu.
- Có hướng dẫn liên hệ Dealer nếu khách chưa từng đăng ký.
- Giữ form bước 1 và lựa chọn xe.

Cần xác định quy tắc chặn và hành vi nút Tiếp tục; không suy ra quy tắc server chỉ từ việc nút vẫn hiển thị trong frame.

### 4.5. D06 — QR không hợp lệ

Thiết kế có minh họa QR/đồng hồ, thông báo “Mã QR không còn hiệu lực”, hướng dẫn lấy mã mới và CTA về trang cộng đồng. Theo yêu cầu hiện tại, đưa nội dung này vào panel đăng ký bước 1, giữ phần landing phù hợp.

Không tự tạo cơ chế hết hạn QR vì QR hiện tại là URL Dealer ổn định. Trường hợp Dealer không hoạt động, slug không tồn tại và QR có token sai cần phân biệt theo cơ chế thực sự được chọn.

## 5. Layout, design tokens và responsive

### 5.1. Kích thước và spacing desktop

| Hạng mục | Giá trị đọc được |
|---|---|
| Canvas / container | 1440px / 1200px; lề trái phải 120px |
| Header / footer | 72px / 101px |
| Hero D01 | 1440 × 680px |
| Copy hero | Bắt đầu x=120px, y=168px trong hero; rộng 660px |
| Panel bước 1 | 1200 × 352px ở trạng thái mặc định; radius 28px |
| Padding bước 1 | 48px ngang, 40px dọc |
| Hai phần bước 1 | 340px giới thiệu + gap 56px + 708px trường |
| Họ tên / điện thoại | Mỗi trường 344px; gap 20px |
| Field spacing | Label/input thường 8px; các nhóm 24px |
| Input desktop | Cao 56px, padding ngang 14px, radius 16px |
| Form D02/D03 | 816px chính + gap 24px + sidebar 360px |
| Card quyền lợi | 384 × 386px; gap 24px; ảnh 384 × 240px |
| Thể lệ / FAQ | Hai cột 588px, gap 24px |
| Khoảng section | Nhiều section dùng padding top 80px; trước panel đăng ký 48px |
| Hệ spacing thường gặp | 8, 12, 16, 20, 24, 28, 40, 48, 56, 80px |

Không cố định chiều cao trang/form theo frame khi nội dung thực hoặc lỗi dài hơn. Dùng max-width, Grid/Flex và flow tự nhiên.

### 5.2. Màu và hiệu ứng

| Token/vai trò | Giá trị |
|---|---|
| Đỏ thương hiệu | `#EB0A1E` |
| Nền trang | `#F5F5F7` |
| Card | `#FFFFFF` |
| Surface input/muted | `#F2F2F5` |
| Chữ chính | `#111111` |
| Chữ phụ | `#5E5E66` |
| Chữ tertiary | `#8E8E93` |
| Border mặc định | `#E3E3E8` |
| Border/đường phân cách nhẹ | `#EDEDF0` |
| Selected xe / nền hồng | `#FFF1F2` |
| Input lỗi | Nền `#FEF1F2`; border token `#B91C1C`, dày 1.5px |
| Chữ lỗi một số node | `#C4101F` |
| Summary lỗi mobile | `#FEF2F2` |
| Panel tối | `#111111`, một số button/bubble `#2F2E2C` |
| Radius | Input 16px; card 20–24px; panel 28px; button/chip 999px |
| Shadow form desktop | Tham chiếu `0 12px 20px rgba(0,0,0,0.06)` |
| Shadow form mobile D01 | `0 8px 24px rgba(18,18,26,0.06)` |

Thiết kế có chênh lệch nhỏ giữa token component và màu gán trực tiếp. Khi triển khai cần đối chiếu từng trạng thái, không đồng nhất mọi sắc đỏ tùy tiện. CTA hero có nền trắng trong suốt, border trong suốt và backdrop blur; không thay bằng nền trắng đặc.

### 5.3. Typography

| Vị trí | Font / thông số |
|---|---|
| Phần lớn UI | Inter Regular, Medium, SemiBold, Bold, ExtraBold |
| Hero heading | Montserrat Bold, desktop 72/78px, tracking -1.44px; gradient trắng → `#BFC4D1` |
| Hero mobile | Montserrat Bold, 38/42px; gradient và text shadow |
| Hero eyebrow desktop | Montserrat SemiBold, 14/18px, tracking 4.48px |
| Hero mô tả desktop | Inter Regular 20/30px, trắng 80% |
| Heading form D01 | Inter ExtraBold 32/38px, tracking -0.48px |
| Heading D02 | Inter ExtraBold, line-height 46px theo context |
| Label | Inter Medium 14/20px |
| Input | Inter Regular 16px |
| CTA chính | Inter SemiBold 16/22px |
| Nội dung phụ | Thường 13/18px hoặc 15/22px |

Một số chữ nhỏ ở sidebar/ngày tháng cũng dùng Montserrat. Cần font có đủ glyph tiếng Việt; chưa kiểm tra file font thực tế hoặc cấu hình font runtime.

### 5.4. Responsive

- Mobile M01 có hero riêng cao 780px, bố cục ảnh xe khác desktop; không chỉ crop banner desktop.
- Có Sticky CTA ở đáy viewport. Cần khoảng đệm tránh che nội dung, xử lý safe-area và bàn phím điện thoại.
- Form D01 một cột, margin ngang 16px, padding card 24px.
- Quyền lợi mobile là carousel cuộn ngang, gap 12px, ảnh cao 176px.
- Các section mobile thường cách 48px.
- Bước 2 mobile chia thành các card, margin 16px, padding 20px; input cao 48px.
- Sidebar desktop cần chuyển thứ tự phù hợp trên mobile; không giữ cột rộng cố định 360px.
- Chưa xác minh thiết kế tablet hoặc breakpoint chính thức. Các breakpoint giữa 390px và 1440px phải được đề xuất và kiểm chứng khi dựng UI.

## 6. Khảo sát codebase Laravel

### 6.1. Stack và cấu trúc hiện tại

Theo `composer.json`, `package.json`:

- PHP `^8.5`, Laravel framework `^13.0`.
- Blade server rendering.
- Tailwind CSS `^4.3.3`, `@tailwindcss/postcss`, Vite `^6.4.3`, Laravel Vite plugin.
- JavaScript ES modules; Axios có dependency, luồng địa chỉ hiện dùng fetch.
- Bacon QR Code `^3.0` để tạo QR.

Đây là khai báo dependency, chưa chạy kiểm tra phiên bản runtime/installed hoặc build.

### 6.2. Routes hiện tại có liên quan

Tất cả nằm trong `routes/web.php`.

| Method / URL | Route name | Controller / ý nghĩa |
|---|---|---|
| GET `/` | `home` | `LandingPageController`; trang chung hiện tại |
| GET `/dealers` | `dealers.index` | `PublicDealerController@index` |
| GET `/dealers/{dealer}` | `dealers.show` | `PublicDealerController@legacy`, redirect 301 về URL slug |
| GET `/dealers/{dealer}/qr` | `dealers.qr` | `DealerProfileMediaController@qr` |
| GET `/dealers/{dealer}/photo` | `dealers.photo` | `DealerProfileMediaController@photo` |
| GET `/{slug}` | `dealers.profile` | `PublicDealerController@show` |
| POST `/{slug}/registrations` | `dealers.registrations.store` | `DealerRegistrationController@store`; throttle dealer-registrations |
| GET `/{slug}/address/wards/{province}` | `dealers.address.wards` | `DealerRegistrationController@wards`; throttle dealer-address |
| GET `/register`, POST `/register` | `register` / POST cùng URL | Đăng ký tài khoản qua `Auth\RegisteredUserController`; không phải intake khách Dealer |
| GET `/leaderboard` | `leaderboard` | `LeaderboardController` |
| GET `/awards`, `/awards/{award}` | `awards.index`, `awards.show` | `AwardHistoryController` |
| GET `/dealer/customers`, `/dealer/customers/{submission}` | `dealer.customers.index/show` | Xem khách theo quyền Dealer |
| GET `/admin/submissions` | `admin.submissions.index` | `Admin\SubmissionReviewController@index` |
| GET `/admin/submissions/{submission}` | `admin.submissions.show` | Xem chi tiết Admin |
| PATCH `/admin/submissions/{submission}` | `admin.submissions.update` | Sửa thông tin qua Admin |
| POST `/admin/submissions/{submission}/approve` | `admin.submissions.approve` | Duyệt |
| POST `/admin/submissions/{submission}/reject` | `admin.submissions.reject` | Từ chối |
| GET `/admin/submissions/address/wards/{province}` | `admin.submissions.address.wards` | Địa chỉ trong Admin |

Route Dealer slug được đặt sau các application routes; có reserved slugs qua `DealerSlugs`. Routes Sales công khai dùng dạng `s-[a-f0-9]{32}` để tránh bắt nhầm private routes.

Chưa có routes riêng cho bước 1/draft, trang bước 2, trang thành công hoặc public tra cứu khách hàng.

### 6.3. Controllers, requests và services

| File | Hành vi đã đọc / khả năng tái sử dụng |
|---|---|
| `app/Http/Controllers/PublicDealerController.php` | Projection public giới hạn field; lookup theo slug; đọc standing; response `no-store, private`; Dealer không hoạt động vẫn có trang lịch sử |
| `app/Http/Controllers/DealerRegistrationController.php` | Gửi một bước; lookup active Dealer; cooldown atomic cache 600 giây; transaction, khóa Dealer; tạo pending; trả JSON 201 hoặc redirect về `#registration` |
| `app/Http/Requests/DealerRegistrationRequest.php` | Chuẩn hóa phone; validate địa chỉ/xe/consent; cấm giả mạo system fields; thông báo lỗi công khai chung; không flash PII |
| `app/Services/RegistrationPhone.php` | Bỏ khoảng trắng/dấu phân cách; đổi +84, 0084 sang 0; HMAC key |
| `app/Services/AdministrativeDivisions.php` | Có service và được gọi để lấy provinces/wards; chưa audit toàn bộ dữ liệu hành chính |
| `app/Services/DealerProfile.php` | URL từ canonical origin cấu hình; QR PNG trỏ URL slug ổn định; kiểm tra base URL |
| `app/Services/LeaderboardService.php` | Chỉ đếm approved; Dealer theo snapshot dealer_id; Sales theo quan hệ Sales hợp lệ |
| `app/Actions/ApproveCustomerSubmission.php` | Gate, transaction, lockForUpdate, pending-only, kiểm tra integrity, Facebook xác minh, chống trùng, moderation log |
| `app/Actions/UpdateCustomerSubmission.php` | Sửa Admin, HMAC version chống stale update, khóa số điện thoại bằng MySQL GET_LOCK, audit đã che PII |
| `app/Http/Requests/Admin/UpdateSubmissionRequest.php` | QR bắt buộc address_line/vehicle_description, cấm vehicle_model trong luồng sửa QR; cấm đổi Facebook qua form sửa thông thường |
| `app/Http/Requests/Admin/ReviewSubmissionRequest.php` | Nhận verified_facebook_url cho thao tác duyệt |
| `app/Exceptions/Handler.php` | Che lỗi đăng ký/SQL, response không lộ PII; nhánh đặc biệt hiện chỉ áp dụng `dealers.registrations.store` |
| `app/Providers/RouteServiceProvider.php` | Rate limit đăng ký Dealer 5/phút và 20/giờ theo IP đã HMAC; địa chỉ 60/phút |
| `config/dealer.php` | Public base URL, consent version và consent notice |
| `config/vehicles.php` | Mapping xe Toyota hiện có Veloz/Hilux/Fortuner, chưa có key FJ |

**Bổ sung khi tổng hợp:** Luồng Admin sửa số điện thoại đã có kiểm tra trùng trên submission `pending`/`approved` toàn hệ thống khi đổi sang số khác, bao gồm một số biến thể định dạng cũ. Điều này không có nghĩa public intake đã chống trùng tương tự. Cần đối chiếu và thống nhất chính sách giữa intake và Admin; không tự sửa logic Admin đang được yêu cầu giữ nguyên.

### 6.4. Models và invariants

| Model / enum | Vai trò và nhận xét |
|---|---|
| `CustomerSubmission` | Model chính đã đọc đầy đủ; quan hệ Dealer, Sales, reviewer, moderationReviews; cast status/datetime/year |
| `Dealer` | Entity Dealer được các controller/service sử dụng; có slug, active, contact và media; chưa audit đầy đủ model |
| `User` | Sales/Dealer/Admin; được dùng kiểm tra vai trò và quan hệ; chưa audit đầy đủ model |
| `ModerationReview` | Lưu lịch sử chuyển trạng thái duyệt; hành vi tạo được đọc trong action approval |
| `SubmissionEdit` | Lưu lịch sử sửa Admin; action che phone/name/free text trước khi ghi audit |
| `Award`, `AwardWinner` | Liên quan lịch sử vinh danh; chưa audit đầy đủ model trong đợt này |
| `SubmissionStatus` | Chỉ `pending`, `approved`, `rejected` |

Invariants từ `CustomerSubmission`:

- Source mặc định sales, status mặc định pending; intake Dealer phải gán rõ `dealer_qr`.
- Source không được đổi sau khi tạo.
- QR phải có Dealer và `sales_id = null`.
- Sales source cần Sales hợp lệ; Dealer snapshot lấy khi tạo.
- Attribution thông thường không đổi; Admin có cơ chế sửa có kiểm soát cho bản ghi pending.
- Facebook được chuẩn hóa; QR có thể null trước khi Admin xác minh.
- Reviewer phải là Admin.
- Một số field nhạy cảm được hidden; không được suy luận rằng serialize model là an toàn cho public vì phone/name không nằm toàn bộ trong hidden.

### 6.5. Luồng intake hiện tại

```text
GET /{slug}
  → dealers.show + dealer-registration-form (tất cả trường một bước)
POST /{slug}/registrations
  → validate + chuẩn hóa phone
  → active Dealer + cooldown Dealer/phone 600 giây
  → transaction, khóa Dealer và kiểm tra active lại
  → CustomerSubmission(source=dealer_qr, sales_id=null, status=pending)
  → JSON 201 hoặc redirect /{slug}#registration
  → Admin xác minh Facebook và duyệt/từ chối
```

Đang lưu họ tên, phone/phone_normalized, địa chỉ, vehicle_description và consent. Facebook ban đầu null; biển số, năm đăng ký và ODO chưa được controller public này ghi.

## 7. Migrations và ràng buộc dữ liệu

Chỉ đọc migration source, không chạy `migrate`, không query dữ liệu production và chưa xác minh schema thực tế đã áp dụng đầy đủ.

| Migration | Ý nghĩa |
|---|---|
| `2026_10_03_000001_create_dealers_table.php` | Bảng Dealer, code unique |
| `2026_10_03_000002_add_business_fields_to_users_table.php` | Vai trò/quan hệ nghiệp vụ; check Sales cần Dealer |
| `2026_10_03_000003_create_customer_submissions_table.php` | Submission, status, thời điểm gửi/duyệt; generated approved_facebook_identity và unique index |
| `2026_10_03_000004_create_awards_table.php` | Kỳ vinh danh và constraints |
| `2026_10_03_000005_create_award_winners_table.php` | Kết quả/snapshot vinh danh |
| `2026_10_03_000006_create_moderation_reviews_table.php` | Lịch sử duyệt |
| `2026_10_03_000007_add_is_active_to_dealers_table.php` | Trạng thái Dealer |
| `2026_10_03_000009_add_dealer_contact_fields.php` | Contact Dealer |
| `2026_10_04_000010_add_vehicle_evidence_to_customer_submissions.php` | vehicle_model, first_registration_year, vehicle_color, license_plate, evidence_image_path; nullable để giữ lịch sử |
| `2026_10_04_000011_add_submission_listing_indexes.php` | Index danh sách submission |
| `2026_10_04_000012_add_toyota_directory_fields_to_dealers.php` | Thông tin directory Toyota |
| `2026_10_04_000013_add_dealer_snapshot_to_customer_submissions.php` | Snapshot attribution Dealer |
| `2026_10_19_000015_add_customer_address_to_customer_submissions.php` | address_line, province/ward codes/names nullable cho dữ liệu lịch sử |
| `2026_10_19_000017_add_photo_path_to_dealers.php` | Ảnh Dealer |
| `2026_10_20_000018_support_unique_dealer_accounts_on_users.php` | Tài khoản quản lý Dealer |
| `2026_10_20_000019_add_stable_slug_to_dealers.php` | Slug Dealer ổn định, unique |
| `2026_10_20_000020_support_dealer_qr_registrations.php` | Source QR, phone_normalized, vehicle_description, consent; nullable Sales/Facebook; checks QR |
| `2026_10_20_000022_create_submission_edits_table.php` | Nhật ký chỉnh sửa Admin |

Mức độ khảo sát khác nhau: các migration submission gốc, vehicle evidence, địa chỉ và QR đã đọc nội dung; các migration còn lại được xác định qua danh sách/search và code sử dụng, chưa audit toàn bộ.

Các ràng buộc quan trọng:

- `approved_facebook_identity` là generated column chỉ có giá trị khi approved.
- Unique index `submissions_approved_profile_unique` ngăn cùng Facebook approved nhiều lần toàn hệ thống.
- `submissions_review_check` ràng buộc cặp trạng thái/thông tin reviewer.
- `submissions_source_check` yêu cầu QR có Dealer, phone_normalized, vehicle_description, address_line, province_code, ward_code, consented_at, consent_version và không Sales.
- `submissions_profile_check` yêu cầu Facebook gốc/chuẩn hóa cùng null hoặc cùng hợp lệ theo điều kiện DB.
- `submissions_approved_identity_check` cấm approved khi chưa có Facebook identity.
- phone_normalized chỉ có index thường trong migration QR, không phải unique.
- Chưa thấy cột ODO trong migration source đã khảo sát.
- Chưa thấy unique/rule chống trùng biển số tương ứng D03 trong phần code đã đọc.

Không sửa migration cũ đã áp dụng. Nếu sau này cần ODO hoặc index bổ sung, thiết kế migration mới mang tính bổ sung, giữ nullable cho dữ liệu lịch sử, đánh giá dữ liệu trước mọi constraint unique. Đây chỉ là đề xuất, chưa tạo hoặc chạy migration.

## 8. Views, CSS, JavaScript có thể tái sử dụng

| File | Hiện trạng / hướng tái sử dụng |
|---|---|
| `resources/views/dealers/show.blade.php` | Trang hồ sơ Dealer hiện tại; có media, details, approved score, leaderboard và form |
| `resources/views/components/dealer-registration-form.blade.php` | Form một bước; CSRF, honeypot, consent, lỗi; cần tách thành bước 1 |
| `resources/views/errors/dealer-registration.blade.php` | Trang lỗi chung khi gửi; chưa phải panel D05/D06 |
| `resources/views/components/customer-address-fields.blade.php` | Address fields dùng chung; province/ward, address_line, error aria; có restoreInput=false |
| `resources/views/components/public/layout.blade.php` | Layout chung hiện màu kem, header 80px, CTA tới tài khoản/register; khác Figma khách Dealer |
| `resources/views/components/public/navigation.blade.php` | Nav trang chung; không khớp header khách đăng ký |
| `resources/views/components/public/brand.blade.php` | Chữ TOYOTA, chưa dùng logo vector Figma |
| `resources/views/components/public/arrow.blade.php` | Có arrow dùng chung; cần kiểm tra hình học trước khi tái sử dụng |
| `resources/views/components/dealer-details.blade.php` | Có thể dùng dữ liệu Dealer; cần kiểm tra markup phù hợp |
| `resources/views/components/dealer-profile-media.blade.php` | Media Dealer hiện có, không phải banner chiến dịch |
| `resources/views/components/submission-vehicle-details.blade.php` | Hiển thị vehicle_description/model, năm, màu, biển số; QR không bắt tải ảnh bằng chứng |
| `resources/views/components/submission-address-details.blade.php` | Hiển thị địa chỉ nội bộ; giữ phạm vi phân quyền |
| `resources/views/components/submission-inline-edit.blade.php` | Admin sửa; QR hiện dùng vehicle_description |
| `resources/views/components/submission-review-form.blade.php` | Form review hiện tại; không thay logic xác minh |
| `resources/views/admin/submissions/index.blade.php`, `show.blade.php` | Danh sách/chi tiết Admin; ưu tiên bảo toàn |
| `resources/views/dealer/customers/index.blade.php`, `show.blade.php` | Khách trong phạm vi Dealer; ưu tiên bảo toàn |
| `resources/views/welcome.blade.php` | Landing chung thiên về Sales/thi đua, không phải D01 Dealer |
| `resources/css/app.css` | Tailwind import và class form/button/public dùng chung |
| `resources/js/app.js` | Import bootstrap, customer-address, submission-inline; xử lý fallback ảnh |
| `resources/js/customer-address.js` | Fetch wards, cache, AbortController, chống response cũ, retry; tái sử dụng logic |
| `resources/js/submission-inline.js` | JS Admin inline; tránh ảnh hưởng khi thêm module public |

Không đổi toàn cục `.form-input`, `.button-primary`, `.public-container` để ép Figma vì ảnh hưởng Sales/Admin và các public pages. Container hiện tại max-w-6xl cộng padding cũng khác target 1200px. Nên dùng layout và scope CSS riêng cho luồng đăng ký Dealer.

Không đổi `/register` thành đăng ký khách: route đó đang phục vụ tài khoản hệ thống.

## 9. Assets và nội dung còn thiếu

### 9.1. Kết quả kiểm kê local

Trong `public` và `resources`, tìm thấy ba assets ảnh liên quan Hilux:

```text
public/hilux-brothers/so-do-duong-trai-nghiem.webp
public/hilux-brothers/kv-hilux-brothers.webp
public/hilux-brothers/anh-cung-duong.webp
```

Chưa xác minh chúng là ảnh trùng khớp Figma; không tự dùng thay thế. Chưa tìm thấy font local Inter/Montserrat và bộ assets đúng thiết kế trong phạm vi tìm kiếm này. Không kiểm kê toàn bộ storage/media database hoặc nội dung package vendor.

### 9.2. Assets Figma cần đưa về local khi triển khai

| Nhóm | Thông tin |
|---|---|
| Logo Toyota | Component `57:851`, vector chính thức; kích thước logo desktop tham chiếu khoảng 49.831 × 36px |
| Banner desktop | Instance `223:854`, dùng trong hero 1440 × 680px |
| Banner mobile | Frame `123:342`, 390 × 780px, ghi chú export PNG @2x |
| Xe và hiệu ứng | Context trả ảnh Veloz/Hilux, bóng gầm, đèn viền và các layer hiệu ứng; cần xác định đúng nhóm export |
| Quyền lợi | Ba ảnh layer “Ảnh (mẫu AI)”: dịch vụ Dealer, caravan, kỹ thuật viên |
| D04 | Ảnh group desktop 300 × 240px; icon thành công, timeline và Facebook |
| D06 | Minh họa QR hết hiệu lực/đồng hồ |
| Icons khác | Tra cứu, Dealer, khóa, vị trí, thời gian, gọi điện, arrow, lỗi, FAQ/quyền lợi |
| Fonts | Inter nhiều weight; Montserrat SemiBold/Bold, hỗ trợ tiếng Việt |

MCP đã trả nguồn PNG/SVG nhưng chưa tải assets xuống dự án. URL MCP là nguồn tạm thời, phản hồi ghi thời hạn 7 ngày; cần lấy lại khi triển khai nếu hết hạn. Không lưu URL tạm làm runtime image source và không đưa screenshot toàn trang vào UI thay các thành phần thật.

Banner có nhiều layer; tránh xuất cả banner rồi lại chồng xe/bóng từ cùng composition. Cần manifest ghi asset → node/slot → kích thước → callsite để kiểm chứng.

### 9.3. Nội dung cần xác minh

- URL Facebook group thật; mẫu hiện có link chung `https://www.facebook.com/groups/` ở một số CTA.
- Hotline `1900 xxxx` còn là placeholder.
- Link điều khoản, chính sách bảo mật và nội dung thể lệ đầy đủ.
- Tên/địa chỉ/giờ mở cửa/điện thoại lấy từ Dealer thay mẫu Toyota An Sương.
- Nguồn số `1.126`: số group, số đăng ký hay số approved chưa được xác định; không dùng thay điểm Dealer.
- Thời gian chiến dịch 01/11/2026–31/01/2027 và cam kết 48 giờ chưa xác nhận là cấu hình nghiệp vụ thực.
- Các ảnh ghi mẫu AI đã có trong Figma nhưng chưa xác nhận là assets cuối cùng cho production.

## 10. Khoảng cách nghiệp vụ và rủi ro kỹ thuật

### 10.1. Hai bước và draft

Hiện intake tạo pending ngay sau một POST đầy đủ. Phương án đề xuất:

```text
D01 → validate bước 1 → draft theo session/Dealer, có TTL
    → GET trang D02
    → POST hoàn tất → validate lại draft + dữ liệu bước 2 + Dealer active
    → transaction tạo một submission pending, source dealer_qr, không Sales
    → consume draft / idempotency → redirect D04
    → Admin xác minh như hiện tại → approved mới có điểm
```

Không tạo customer_submissions ở bước 1; tránh bản ghi thiếu dữ liệu và sai yêu cầu pending sau bước 2. Draft không có điểm, không đổi enum status hiện tại.

Cần xử lý quay lại sửa, refresh, nhiều tab, hết TTL, session mất, đổi Dealer, duplicate submit và response mạng bị mất. Khóa cache 600 giây hiện là cooldown, không thay thế idempotency hoặc uniqueness nghiệp vụ.

Draft chứa PII: đề xuất server-side session/cache với token opaque, namespace riêng và TTL; kiểm tra session driver thực trước khi chọn. Không đưa PII vào URL hoặc localStorage và không flash toàn bộ vào old input dùng chung.

### 10.2. Địa chỉ chi tiết là điểm lệch bắt buộc xử lý

Figma chỉ có Tỉnh/Thành và Phường/Xã. Request, DB check và Admin edit QR hiện bắt buộc address_line. Khuyến nghị bảo toàn nghiệp vụ: thêm trường địa chỉ chi tiết trong card địa chỉ bước 2, ghi nhận đây là sai khác có chủ đích với Figma.

Không điền dấu gạch, chuỗi rỗng hoặc địa chỉ giả để vượt validation. Nếu bỏ yêu cầu address_line sẽ phải thay đổi nhiều tầng và logic Admin, ngoài phương án bảo toàn hiện tại.

### 10.3. Facebook public và Admin xác minh

Public request hiện cấm facebook_url; Figma D02 lại bắt buộc. Có thể nhận Facebook do khách khai báo và chuẩn hóa bằng service hiện có. Tuy nhiên approval QR vẫn bắt buộc Admin xác minh `verified_facebook_url` như cũ; khách không được gửi approved identity, reviewed fields hoặc status.

Không tự xem URL khách nhập là đã xác minh. Unique Facebook approved và moderation history phải giữ nguyên. Không đổi approval action chỉ để giảm thao tác.

### 10.4. Xe, biển số, năm và ODO

- Các cột vehicle_model, license_plate, first_registration_year đã có nhưng public intake chưa ghi.
- QR hiện bắt buộc vehicle_description; cần giữ mapping chuỗi mô tả tương thích khi bổ sung model có cấu trúc.
- FJ có trong Figma nhưng chưa có trong mapping config; không giới hạn toàn bộ luồng Sales về bốn dòng xe chiến dịch.
- ODO chưa thấy cột; lưu đầy đủ cần schema bổ sung sau này, validation số/km và hiển thị tương ứng.
- Thiết kế không yêu cầu ảnh bằng chứng cho QR; không mang yêu cầu ảnh của Sales sang luồng này.

### 10.5. Trùng điện thoại D05

Public intake hiện chỉ chặn lặp cùng Dealer/phone trong 600 giây, không kiểm tra tồn tại bền vững. Admin edit có kiểm tra pending/approved toàn hệ thống khi thay số, nhưng intake chưa áp dụng.

Chưa chốt phạm vi global/per Dealer; pending/approved/rejected nào chặn; có cho một số điện thoại nhiều xe hay không. Cần thống nhất với Admin hiện tại và dữ liệu lịch sử, không tự áp unique phone toàn bảng.

Kiểm tra tại bước 1 cần kiểm tra lại khi hoàn tất; tránh race condition với intake và Admin edit. Không biến 429 rate limit thành “đã đăng ký”. Response lỗi không trả PII/record của khách khác; thông báo tồn tại số điện thoại cũng cần kiểm soát lạm dụng bằng throttle.

### 10.6. Trùng biển số D03

Chưa có rule tương ứng được xác minh. Cần xác định chuẩn hóa dấu chấm/gạch/khoảng trắng/chữ hoa, phạm vi và status chặn; hỗ trợ biển số cũ/định dạng thực tế trước khi chọn regex. Không thêm unique constraint khi chưa đánh giá dữ liệu.

Không chỉ kiểm tra frontend hoặc query exists trước INSERT; cần giải pháp đồng thời phù hợp nếu nghiệp vụ yêu cầu chặn cứng.

### 10.7. QR và Dealer không hoạt động

QR hiện trỏ canonical slug ổn định, không có expiry token. Dealer inactive vẫn xem được profile lịch sử nhưng form bị ẩn và POST không được nhận. Cần đưa D06 vào panel mà không phá QR đã in hoặc thay attribution cũ.

Slug lạ hiện trả 404; chưa có cơ chế khôi phục tên Dealer hợp lệ từ QR sai. Không hiển thị tên Toyota An Sương mặc định khi không biết Dealer. Không tự thêm token hết hạn hoặc chuyển mọi 404 thành trang hợp lệ.

### 10.8. Thành công, group và tra cứu

D04 cần dữ liệu thuộc session/draft vừa gửi, không cho xem PII bằng ID tuần tự hoặc số điện thoại đơn thuần. Dùng redirect sau POST, no-store/private, tránh gửi lại khi refresh.

“Chờ vào group”, “thành viên chính thức” là tiến trình UX, chưa phải status DB. Không thêm status trung gian hoặc tự đổi approved qua nút Facebook. Thời điểm cộng điểm hiện theo submitted_at cho kỳ xếp hạng, không theo ngày Admin duyệt; cần bảo toàn.

Tra cứu public chưa tồn tại. Link tra cứu trong D01/D04/D05 phụ thuộc phạm vi bổ sung: cần cơ chế chứng minh quyền xem như token bí mật hoặc phương án xác thực được chốt, không chỉ nhận phone và trả hồ sơ. D07–D15 chưa được phân tích sâu nên chưa đủ cơ sở triển khai trọn luồng bổ sung/tra cứu.

### 10.9. Bảo vệ các chức năng hiện có

- Không thay toàn bộ layout public để làm D01 vì trang chung và các pages khác đang dùng chung.
- Không thay rules/status/score của Sales và Admin.
- Không gán Sales cho khách QR, kể cả khi tài khoản Sales đang đăng nhập trình duyệt.
- Giữ consent version/time do server cấp; cập nhật nội dung consent có version nếu phạm vi thu thập mở rộng.
- Giữ xác thực CSRF, honeypot, throttles và kiểm tra active Dealer ở thời điểm ghi cuối.
- Handler đang chỉ nhận diện route store hiện tại; route mới cần cùng chính sách che PII/no-store.
- Giữ audit edits/reviews và attribution lịch sử; không chạy backfill hoặc sửa dữ liệu cũ để khớp màn hình.

## 11. Danh sách thay đổi dự kiến

### 11.1. File hiện có cần chỉnh trong phương án hai bước

| File | Thay đổi dự kiến |
|---|---|
| `resources/views/dealers/show.blade.php` | Dựng landing D01, dữ liệu Dealer và panel bước 1 |
| `resources/views/components/dealer-registration-form.blade.php` | Form bước 1, car picker, D05/D06 |
| `resources/css/app.css` | Import stylesheet scope riêng; không đổi global UI nội bộ |
| `resources/js/app.js` | Import module registration, khởi tạo có điều kiện |
| `routes/web.php` | Thêm endpoints draft, trang bước 2, success; giữ route QR và thứ tự slug |
| `app/Http/Controllers/PublicDealerController.php` | Chuẩn bị dữ liệu landing, active/invalid state |
| `app/Http/Controllers/DealerRegistrationController.php` | Điều phối hai bước, final validation, pending, redirect success, idempotency |
| `app/Http/Requests/DealerRegistrationRequest.php` | Validation bước cuối, field errors, Facebook/xe và system fields |
| `app/Exceptions/Handler.php` | Bao phủ route mới, phân biệt lỗi và không lộ PII |
| `app/Providers/RouteServiceProvider.php` | Rate limit từng thao tác, không tiêu hao hạn mức submit cho tải trang |
| `config/dealer.php` | Group URL, nội dung chiến dịch, consent/version và cấu hình phù hợp |
| `config/vehicles.php` | Mapping FJ khi nghiệp vụ xác nhận |
| `tests/Feature/DealerLandingRegistrationTest.php` | Chuyển assertions form một bước sang flow mới, giữ invariants nghiệp vụ |

### 11.2. File mới đề xuất, chưa tạo

Tên dưới đây có thể điều chỉnh theo convention khi triển khai:

```text
resources/views/components/dealer-registration/layout.blade.php
resources/views/components/dealer-registration/phone-duplicate.blade.php
resources/views/components/dealer-registration/invalid-qr.blade.php
resources/views/dealers/registration/step-two.blade.php
resources/views/dealers/registration/success.blade.php
resources/css/dealer-registration.css
resources/js/dealer-registration.js
app/Http/Requests/DealerRegistrationStepOneRequest.php
app/Services/DealerRegistrationDraft.php
public/... hoặc resources/... cho assets và fonts đã export
```

Nếu lưu ODO: migration bổ sung cột nullable, model cast/validation và display liên quan cần được thiết kế thêm. Chưa quyết định tên migration/cột cuối cùng.

### 11.3. File chỉ chỉnh khi có nhu cầu xác nhận thêm

- `resources/views/components/customer-address-fields.blade.php`: thêm variant hoặc wrapper riêng; giữ mặc định cho Sales/Admin.
- `resources/js/customer-address.js`: chỉ sửa nếu cần autocomplete/search hoặc markup mới; ưu tiên giữ fetch/cache/retry hiện có.
- `resources/views/components/submission-vehicle-details.blade.php`: hiển thị ODO nếu được lưu.
- `app/Models/CustomerSubmission.php`: cast ODO hoặc điều chỉnh field phù hợp nếu schema mở rộng; không nới invariants.
- `resources/views/errors/dealer-registration.blade.php`: fallback cho lỗi ngoài validation; không thay thế panel D05/D06.

### 11.4. Thành phần ưu tiên giữ nguyên

- `app/Actions/ApproveCustomerSubmission.php`, `RejectCustomerSubmission.php`.
- `app/Actions/UpdateCustomerSubmission.php` và workflow Admin edit.
- `app/Services/LeaderboardService.php`, logic vinh danh/snapshot.
- `app/Http/Controllers/Admin/SubmissionReviewController.php` và authorization.
- Enum status, unique approved Facebook, review/audit history.
- Các migrations đã áp dụng và dữ liệu lịch sử.
- Luồng Sales, auth `/register`, public home, leaderboard và awards.

Giữ nguyên logic không có nghĩa bỏ qua kiểm thử hồi quy. Bất kỳ yêu cầu mở rộng Admin field phải được tách riêng và đánh giá, không gộp âm thầm vào việc dựng Figma.

## 12. Routes đề xuất, chưa tồn tại

| Method / URL gợi ý | Vai trò |
|---|---|
| GET `/{slug}` | Giữ landing hiện tại, nâng UI D01 |
| POST `/{slug}/registrations/step-one` | Validate bước 1, lưu draft, D05 nếu trùng |
| GET `/{slug}/registrations/step-two` | Render D02 hoặc trở về bước 1 nếu draft không hợp lệ |
| POST `/{slug}/registrations` | Giữ URL final hiện có nếu khả thi, validate cả hai bước và tạo pending |
| GET `/{slug}/registrations/success` | D04, giới hạn bằng session/token phù hợp |
| GET `/{slug}/address/wards/{province}` | Tái sử dụng endpoint địa chỉ |

Không đề xuất route riêng cho D03/D05/D06 chỉ để tương ứng tên frame: chúng là trạng thái của màn hình. Public lookup sẽ cần thiết kế riêng nếu được đưa vào phạm vi; chưa chốt URL/API.

## 13. Kế hoạch triển khai theo giai đoạn

### Giai đoạn 1 — Chốt hợp đồng dữ liệu/nghiệp vụ

- Chốt bổ sung address_line để giữ ràng buộc hiện tại.
- Chốt chính sách duplicate phone/plate, đối chiếu Admin edit hiện có.
- Chốt ODO, FJ, Facebook khách khai báo và consent version.
- Chốt tình huống D06, group URL, hotline, số thành viên, ngày chiến dịch.
- Chốt phạm vi tra cứu và cơ chế quyền xem.
- Kiểm tra schema/runtime thật trong phạm vi được cho phép ở đợt triển khai.

Đầu ra: mapping field/validation/storage, state diagram, API contracts và những khác biệt UI có chủ đích. Chưa tự thay dữ liệu lịch sử.

### Giai đoạn 2 — Assets và nền giao diện

- Export đúng logo/banner/ảnh/icon; lưu local và lập manifest.
- Chuẩn bị fonts hỗ trợ tiếng Việt, tokens và layout riêng.
- Xây UI primitives có trạng thái focus/error/disabled/loading.
- Đối chiếu desktop 1440px và mobile 390px, chọn breakpoint trung gian.

Đầu ra: nền giao diện không tác động Admin/Sales/public pages khác.

### Giai đoạn 3 — D01, D05, D06 và draft bước 1

- Dựng hero, sections, FAQ, Dealer data và form bước 1.
- Car picker, điện thoại chuẩn hóa, duplicate response theo chính sách đã chốt.
- Lưu draft có TTL; không tạo submission tại bước 1.
- Hiển thị D05/D06 trong panel; giữ QR/slug ổn định.
- Sticky CTA mobile, keyboard/focus/aria và reduced-motion.

### Giai đoạn 4 — D02/D03 và final intake

- Trang riêng bước 2, đọc draft đúng Dealer/session.
- Tái sử dụng address data, fetch wards/retry.
- Validate biển số/năm/Facebook/consent/ODO, lỗi tổng hợp và từng trường.
- Thêm address_line theo quyết định bảo toàn nghiệp vụ.
- Final transaction, kiểm tra Dealer active, chống gửi lặp/race; pending không Sales.
- Nếu cần schema ODO/index, xử lý bằng thay đổi bổ sung được review riêng; không sửa migration cũ.

### Giai đoạn 5 — D04 và tích hợp duyệt

- Redirect sau POST tới success, no-store/private.
- Dữ liệu success chỉ thuộc session hiện tại; group CTA đúng cấu hình.
- Giữ Admin xác minh Facebook, unique approved identity và moderation log.
- Không ghi nhận vào group/approved qua thao tác bấm link.
- Tra cứu chỉ triển khai khi có phạm vi và cơ chế quyền xem rõ ràng.

### Giai đoạn 6 — Kiểm chứng và nghiệm thu

- Functional: bước 1 không tạo DB row; final hợp lệ tạo đúng một pending; không Sales và không điểm trước approval.
- Validation: D03/D05/D06, back/edit, draft hết hạn, session mất, multiple tabs, duplicate submit, Dealer inactive giữa hai bước.
- Race: phone/plate cùng lúc, idempotency và sự tương tác với Admin edit/approval.
- Privacy/security: CSRF, throttles, honeypot, system-field spoofing, không PII trong URL/log/old input hoặc response khách khác.
- Approval: vẫn yêu cầu verified Facebook, chống trùng, reject không điểm; Dealer có điểm sau approved, Sales không có điểm QR.
- Regression: QR đã in, canonical URL, attribution cũ, leaderboard theo submitted_at, awards history, Sales/Admin/auth routes.
- Visual: D01–D06 desktop và M01–M06; font tiếng Việt, asset geometry/crop, spacing, lỗi dài, tablet, không tràn ngang ngoài carousel chủ đích.

Chỉ chạy test trên database test cách ly khi bước triển khai được yêu cầu. Không chạy toàn bộ test suite mù quáng: `DealerLandingRegistrationTest::test_browser_fixture_uses_test_database_only` chủ động tạo artifacts trong `.laravel-tools/dealer-landing/qa`.

## 14. Kiểm thử hiện có và giới hạn xác minh

### 14.1. Kiểm thử đã đọc/xác định

`tests/Feature/DealerLandingRegistrationTest.php` đã đọc nội dung, bao gồm các tình huống:

- URL/QR Dealer ổn định sau thay đổi thông tin.
- Intake tạo pending, null Sales/Facebook/evidence, chưa có điểm.
- Từ chối dữ liệu sai/system fields và không echo PII.
- Quan hệ province/ward hợp lệ, tên địa chỉ cấp từ server.
- Dealer inactive/không tồn tại không nhận đăng ký.
- Chuẩn hóa phone và cooldown.
- Rate limit IP.
- Admin phải xác minh Facebook trước approval; approved QR chỉ cộng Dealer.
- Chống trùng Facebook giữa QR và Sales.
- Reject không điểm; phân quyền và public không lộ khách.
- Không flash/restore PII sang public page.
- DB check/unique guard, attribution không đổi tùy tiện.
- Lỗi database/debug không lộ PII, CSRF thật.
- QR được tính Dealer awards, không Sales awards.

Các test liên quan đã thấy qua danh sách, chưa chạy hoặc audit đầy đủ: `SubmissionApprovalRaceTest`, `DealerProfileTest`, `DealerSlugTest`, `DealerSlugBackfillTest`, `DealerRegistrationMigrationTest`, `PublicDealerDirectoryTest`, `DealerDashboardTest`, `DealerAuthorizationTest`, `DealerHistoryTest`.

### 14.2. Những điểm chưa xác minh

- Chưa render ứng dụng Laravel trong trình duyệt, chưa visual diff với Figma.
- Chưa chạy test/build, chưa xác nhận kết quả runtime.
- Chưa truy vấn database, chưa kiểm tra migration status/schema thực tế hoặc dữ liệu trùng lịch sử.
- Chưa kiểm tra cấu hình session/cache, origin production, hệ điều hành/runtime PHP/Node thực.
- Chưa audit toàn bộ Models/Policies/Actions ngoài phần nêu rõ đã đọc.
- Chưa kiểm tra đầy đủ D07–D15, prototype transitions, tablet hoặc toàn bộ interaction states.
- Chưa export/download/kiểm tra bytes, kích thước gốc và độ khớp của từng asset local.
- Chưa kiểm tra Code Connect mapping riêng hoặc thư viện thiết kế toàn file; không kết luận không có mapping.
- Không phát hiện AGENTS.md qua các lần tìm kiếm trong phạm vi khảo sát; không có chỉ dẫn delegation được áp dụng.
- `git status` thất bại do Git báo dubious ownership. Thử cấu hình safe.directory chỉ trong lệnh cũng chưa thành công. Không sửa Git config, không thay ownership; chưa có bằng chứng git diff/status để xác nhận toàn bộ working tree sạch. Không quy kết các thay đổi có sẵn cho đợt phân tích.

## 15. Kết luận cho việc triển khai

Đủ thiết kế D01–D06 và mobile tương ứng đã được truy cập. Laravel hiện có nền nghiệp vụ phù hợp cho khách đăng ký qua Dealer: pending, không Sales, Admin xác minh và approved-only scoring. Phần cần bổ sung chính là giao diện Figma, draft hai bước, trang D02/D04, state validation và hợp đồng dữ liệu cho những trường mới.

Các điểm cần giải quyết trước khi code là address_line bắt buộc nhưng không có trong Figma, ODO chưa có storage, Facebook public hiện bị cấm, duplicate phone/plate chưa thống nhất giữa intake và nghiệp vụ, QR ổn định không có expiry, cùng phạm vi public tra cứu chưa xác định. Những điểm này không được che bằng UI giả, dữ liệu giả hoặc thay đổi âm thầm logic Admin.

File báo cáo này là thay đổi tài liệu duy nhất được yêu cầu; source code, migrations và database chưa được thay đổi trong công việc tổng hợp.
