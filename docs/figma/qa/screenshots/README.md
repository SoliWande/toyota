# Screenshot evidence — Phase 2C.4C

**NOT VERIFIED: chưa có screenshot render ứng dụng.** Không dùng ảnh Figma làm bằng
chứng browser. Không có ảnh 390/768/1440px được tạo trong lượt QA này.

Chrome headless được launch với profile QA riêng, loopback CDP và timeout 15 giây;
không kết nối được `/json/version`. Process do QA tạo đã được dừng. Không điều khiển
profile/browser đang mở của người dùng. Figma MCP trả design context cho đủ 12 frame;
get_screenshot D04/M04 trả URLs nhưng tải PNG bị lỗi mạng. Không có PNG tham chiếu
được lưu, cũng không có ảnh giả thay thế.

Xem `../browser-manual-2c-4c.md` để hoàn tất QA trên staging bằng fixtures synthetic.
Tên ảnh đề xuất: `{D01..D06}-{390|768|1440}-{default|invalid|focus}.png`.
Mỗi ảnh cần ghi browser/version, viewport CSS px, device scale, URL không chứa PII,
commit + checksum artifact, trạng thái và ngày kiểm tra. Không chụp dữ liệu thật,
cookies, receipt token hoặc DevTools đang chứa secrets. Chỉ commit ảnh synthetic đã
được review, không đưa ảnh vào release production bundle.
