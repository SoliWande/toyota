# Browser QA thủ công còn phải thực hiện

Tất cả checkbox dưới đây **chưa được nghiệm thu**. Dùng staging cách ly với schema
đã chuẩn bị và fixture synthetic có ownership, không chạy seeder trên production.
Mở production bundle thực (không Vite dev server, không public/hot).
Preview chỉ hỗ trợ layout; không thay thế flow backend/receipt thật.

| Màn hình | Figma desktop / mobile node | 390px | 768px | 1440px |
| --- | --- | --- | --- | --- |
| D01/M01 | 209:233 / 99:376 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |
| D02/M02 | 211:287 / 57:452 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |
| D03/M03 | 211:450 / 129:346 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |
| D04/M04 | 212:377 / 57:571 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |
| D05/M05 | 212:501 / 57:664 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |
| D06/M06 | 212:587 / 57:714 | NOT VERIFIED | NOT VERIFIED | NOT VERIFIED |

File Figma: https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community
Thiết kế mobile 390px, desktop 1440px; 768px là kiểm tra interpolation/breakpoint,
không có frame tablet riêng. Kiểm tra thêm 767px và 769px quanh ranh 768px.

## Trình tự cho mỗi viewport

1. D01: load cold cache, đợi fonts/images, đối chiếu hero, section headings, form,
   car options, header/footer. Network fonts.googleapis.com/fonts.gstatic.com phải
   thành công; DevTools **Rendered Fonts** xác nhận Inter và Montserrat thực dùng,
   gồm chữ Việt có dấu. Computed font-family chỉ là danh sách CSS, không chứng minh
   font thực tải. Không Times New Roman; test chặn font network vẫn Arial/sans-serif.
2. Submit trống: đúng 3 lỗi cụ thể dưới fields, không summary/count/generic message,
   focus trường đầu. Sửa lần lượt, error/aria state cập nhật, dữ liệu khác không mất.
   Keyboard Tab/Shift+Tab, radio arrow/Space, skip link, focus ring không bị cắt/che.
3. Điền Step One hợp lệ → D02 qua POST/draft. Refresh, Back/Sửa giữ dữ liệu đúng
   session/Dealer. Check form hai cột/tablet, label wrapping, car disabled/read-only.
4. D03: submit D02 trống, sai từng field, nhiều field; error inline, border nhẹ/nền
   hồng nhạt, focus/scroll field đầu, aria-invalid/describedby/live regions đúng.
   Khi sửa đúng lỗi mất; không tạo lỗi duplicate plate giả. Mô phỏng backend errors
   qua submission thật trên fixtures, không chỉ sửa DOM để suy ra backend PASS.
5. D04: final thành công, GET PRG, heading “Đăng ký thành công!”, subheading bước
   cuối, card cộng đồng ngay dưới; không lặp tên/xe. Badge Chờ vào group; progress
   check xanh/current đỏ/upcoming xám, connector dọc, không tự coi đã approved/member.
   CTA đỏ mở URL `https://www.facebook.com/groups/376631239337942` tab mới với rel
   noopener noreferrer; không phụ thuộc approval. Không bấm approve để bật nút.
   Phone/plate trong hướng dẫn đúng fixture, không tự cập nhật membership.
6. D05: dùng phone fixture pending/approved → lỗi/trợ giúp duplicate riêng tại D01,
   giữ dữ liệu, không hiển thị chủ sở hữu khác. Rejected fixture không chặn.
7. D06: Dealer inactive không có form submit hợp lệ; truy cập draft/final bị chặn.
   Giữ khác biệt có chủ đích: trạng thái inactive trong panel D01.
8. Tại đầu/giữa/cuối trang: không horizontal document overflow, CTA không che trường,
   lỗi/footer; mobile keyboard mở/đóng và safe-area không che CTA. Ảnh không kéo méo,
   crop hero/mobile/group đúng chủ đích. Kiểm tra 200% zoom và text dài Dealer.
9. Session A hoàn tất, session B/incognito truy cập success cùng Dealer → không có
   phone/plate/DTO của A. Refresh/retry A không tạo thêm submission. Khác Dealer
   không đọc draft/receipt. Không đưa cookie/token/PII vào URL hoặc screenshot log.
10. Chụp default/error/focus states, ghi kết quả, so ảnh Figma. Kiểm tra screen reader
    riêng nếu yêu cầu accessibility acceptance; không suy ra WCAG PASS từ markup.

Snippet read-only để phát hiện overflow và CSS families (không đọc form values):

```javascript
await document.fonts.ready;
console.table({
  viewport: innerWidth,
  clientWidth: document.documentElement.clientWidth,
  scrollWidth: document.documentElement.scrollWidth,
  overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
});
console.table([...document.querySelectorAll('.dr-site h1,.dr-site h2,.dr-site h3')]
  .map(el => ({tag: el.tagName, family: getComputedStyle(el).fontFamily,
    weight: getComputedStyle(el).fontWeight, size: getComputedStyle(el).fontSize,
    lineHeight: getComputedStyle(el).lineHeight})));
```

Font load, rendered glyph, focus order, occlusion và screenshot comparison vẫn cần
DevTools/manual evidence. JS DOM harness PASS không thay browser evidence.
