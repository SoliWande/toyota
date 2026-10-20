/**
 * Nhận đăng ký từ landing page Mini Off HILUX & BROTHERS
 * và ghi thành một dòng trong Google Sheet.
 *
 * CÁCH CÀI (làm 1 lần, khoảng 3 phút):
 *  1. Mở Google Sheet "Đăng ký Mini Off HILUX & BROTHERS — Đồng Mô 30.10.2026"
 *  2. Menu Tiện ích mở rộng (Extensions) > Apps Script
 *  3. Xoá hết code mẫu, dán toàn bộ file này vào
 *  4. Bấm Triển khai (Deploy) > Tạo bản triển khai mới (New deployment)
 *  5. Chọn loại: Ứng dụng web (Web app)
 *       - Thực thi với tư cách (Execute as): Tôi (Me)
 *       - Ai có quyền truy cập (Who has access): Bất kỳ ai (Anyone)
 *  6. Bấm Triển khai, cấp quyền khi Google hỏi
 *  7. Copy đường link Web app (dạng https://script.google.com/macros/s/..../exec)
 *  8. Mở file index.html, tìm dòng  var SHEET_ENDPOINT = '';
 *     dán link vào giữa hai dấu nháy, lưu lại và up lên hosting
 *
 * LƯU Ý: mỗi lần sửa code phải Deploy lại (chọn "Quản lý bản triển khai"
 * > sửa bản hiện tại > Phiên bản mới), nếu không link cũ vẫn chạy code cũ.
 */

var SHEET_NAME = 'Trang tính1'; // đổi nếu bạn đã đặt tên khác cho tab

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(20000); // tránh hai người đăng ký cùng lúc ghi đè nhau

  try {
    var d = JSON.parse(e.postData.contents);
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME)
             || SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];

    sheet.appendRow([
      d.thoiDiem || new Date(),
      d.hoTen || '',
      "'" + (d.dienThoai || ''),  // dấu nháy giữ số 0 ở đầu
      d.xe || '',
      d.soNguoi || '',
      d.laiThu || '',
      d.cauHoi || '',
      d.suKien || '',
      '',                          // Đã gọi xác nhận — BTC tự điền
      ''                           // Ghi chú
    ]);

    return json({ ok: true });
  } catch (err) {
    return json({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

function doGet() {
  return json({ ok: true, message: 'Endpoint dang ky Mini Off HILUX & BROTHERS' });
}

function json(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
