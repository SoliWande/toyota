# Quy tắc làm việc cho Codex

Áp dụng cho toàn bộ dự án Laravel tại thư mục chứa file này, xuyên suốt các
phiên làm việc và tài khoản. Dùng đường dẫn tương đối theo repository; không
mặc định đường dẫn máy cũ trong tài liệu còn đúng.

## Trước mỗi task

1. Đọc yêu cầu hiện tại và các Markdown tracking liên quan trước khi chỉnh sửa.
   Có thể bắt đầu từ `docs/context-recovery-2026-11-03.md` để tìm lịch sử,
   sau đó đọc báo cáo của đúng tính năng/phase trong `docs/figma/` hoặc root.
2. Kiểm tra source thực tế tương ứng: routes, controllers, requests, models,
   actions/services, Blade, CSS/JS và tests tùy phạm vi. Không mặc định tài liệu
   hoặc kết quả test lịch sử phản ánh trạng thái hiện tại.
3. Kiểm tra thay đổi có sẵn khi có thể; không ghi đè công việc của người dùng
   hoặc quy các thay đổi có sẵn cho task hiện tại.
4. Khi tài liệu mâu thuẫn, đối chiếu source và bằng chứng kiểm thử hiện tại.
   Ghi rõ tài liệu nào bị thay thế, kết luận, căn cứ và phần chưa xác minh trong
   tracking mới. Không sửa lịch sử để làm mất mâu thuẫn.

## Tracking cho từng task triển khai

- Mỗi task triển khai phải có một Markdown tracking riêng, tạo trước khi sửa
  và cập nhật trong quá trình làm. Khi tiếp tục cùng task, dùng file của task đó;
  không dùng file phase cũ để ghi đè lịch sử của một task mới.
- Ưu tiên quy tắc đặt tên hiện có: `docs/figma/phase-<...>-<noi-dung>.md` cho
  phase Figma, `<noi-dung>-refinement.md` hoặc `<noi-dung>-sync.md` khi phù hợp.
  Task quy trình/tổng hợp có thể dùng `docs/<ten-task>.md`. Kiểm tra file đã
  tồn tại trước khi tạo; chọn tên riêng nếu bị trùng với lịch sử.
- Không xóa hoặc ghi đè lịch sử triển khai cũ. Nếu cần đổi kết luận của cùng
  task, thêm mục cập nhật có ngày và căn cứ, giữ diễn tiến trước đó.
- Mỗi file tracking mới phải có đầy đủ các mục sau:

```markdown
# Tên task

## Mục tiêu
## Trạng thái
TODO / IN PROGRESS / BLOCKED / DONE
## Hiện trạng trước khi chỉnh sửa
## Các file source code liên quan
## Những thay đổi đã thực hiện
## Quyết định kỹ thuật quan trọng
## Kết quả kiểm thử
## Những lỗi còn tồn tại
## Task hoặc bước tiếp theo
```

Nếu task chỉ thay đổi tài liệu, ghi rõ không có source được sửa và liệt kê các
file tài liệu thay đổi. Mục không áp dụng phải ghi lý do, không bỏ trống.

Trạng thái: TODO khi chưa bắt đầu; IN PROGRESS khi đang thực hiện; BLOCKED khi
có trở ngại ngăn hoàn thành (ghi nguyên nhân và điều kiện tiếp tục); DONE chỉ
khi hoàn thành phạm vi và các kiểm thử cần thiết. Nếu chưa kiểm thử đủ hoặc
kiểm thử còn lỗi liên quan chưa giải quyết, không đánh dấu DONE.

## Giới hạn thay đổi và dữ liệu

- Chỉ thay đổi trong phạm vi được yêu cầu. Task giao diện không được tự ý đổi
  business logic, database schema hoặc API. Nếu thiết kế xung đột với nghiệp vụ
  hay privacy, ghi rõ và yêu cầu quyết định cho phần vượt phạm vi.
- Không tạo dữ liệu giả trong production để mô phỏng trạng thái. Fixture/mock
  chỉ dùng trong môi trường kiểm thử/demo cách ly phù hợp, không thay dữ liệu
  thật hoặc trở thành hành vi runtime production.
- Trước kiểm thử có ghi dữ liệu, xác nhận database test cách ly và guard an toàn;
  không chạy migrate/seed/feature test vào database thật theo suy đoán. Transaction
  hoặc rollback không thay thế yêu cầu cách ly và quyền thực hiện.
- Giữ convention dự án; không gộp refactor hoặc thay đổi không liên quan.

## Giao diện theo Figma

- Figma là nguồn chuẩn của thiết kế. Đọc frame/component liên quan trước khi
  sửa; lấy thông số và assets trực tiếp khi truy cập được, không tự suy đoán.
- Đối chiếu kích thước, bố cục, spacing, typography, màu, border/radius/shadow,
  icon, nội dung tiếng Việt và các trạng thái; ưu tiên component hiện có.
- Chạy giao diện và chụp screenshot đối chiếu frame ở viewport tương ứng nếu
  có thể; kiểm tra lại sau khi sửa. Phân biệt ảnh fixture với kiểm thử luồng thật.
- Nếu không truy cập được Figma, không chạy được giao diện hoặc chưa kiểm tra
  trực quan, ghi rõ giới hạn và phần chưa xác minh. Build/test pass không đủ
  để tuyên bố giao diện khớp hoàn toàn hay nghiệm thu thị giác DONE.

## Kiểm chứng và báo cáo kết thúc

- Chọn kiểm tra phù hợp với phạm vi; ghi lệnh, môi trường cần thiết, kết quả
  PASS/FAIL, lỗi và giới hạn. Phân biệt test vừa chạy với kết quả lịch sử.
- Task chỉ sửa tài liệu dùng kiểm tra nội dung/cấu trúc phù hợp; không cần
  chạy bộ test ứng dụng hoặc ghi database chỉ để có kết quả test.
- Sau mỗi task, báo cáo bằng tiếng Việt: đã sửa gì, file nào thay đổi, test nào
  đã chạy, kết quả ra sao, vấn đề còn lại và bước tiếp theo nếu có.
