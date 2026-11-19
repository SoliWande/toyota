/**
 * ============================================================================
 *  NHẬN ĐĂNG KÝ MINI OFF HILUX & BROTHERS — TP.HCM 29/11/2026
 *  Ghi dữ liệu từ form trên landing page vào Google Sheet
 * ============================================================================
 *
 *  HƯỚNG DẪN CÀI (làm 1 lần, khoảng 5 phút):
 *
 *  1. Tạo 1 Google Sheet mới, đặt tên:
 *        "Đăng ký Mini Off HILUX & BROTHERS — TP.HCM 29.11.2026"
 *
 *  2. Dòng 1 của sheet điền 8 tiêu đề cột theo đúng thứ tự này:
 *        A: Thời điểm đăng ký
 *        B: Họ và tên
 *        C: Số điện thoại
 *        D: Xe đang đi
 *        E: Biển số
 *        F: Lái thử
 *        G: Số người
 *        H: Sự kiện
 *
 *  3. Trong Sheet đó chọn:  Tiện ích mở rộng (Extensions) → Apps Script
 *
 *  4. Xoá hết code mẫu, dán TOÀN BỘ nội dung file này vào, bấm Lưu (Ctrl+S)
 *
 *  5. Bấm "Triển khai" (Deploy) → "Tuỳ chọn triển khai mới" (New deployment)
 *        - Loại (Select type)      : Ứng dụng web (Web app)
 *        - Thực thi với tư cách    : Tôi (Me)
 *        - Người có quyền truy cập : Bất kỳ ai (Anyone)     ← QUAN TRỌNG
 *        → Bấm Triển khai → Cấp quyền (Authorize access) → chọn tài khoản
 *          → "Nâng cao" → "Chuyển tới ... (không an toàn)" → Cho phép
 *
 *  6. Copy "URL ứng dụng web" (dạng https://script.google.com/macros/s/.../exec)
 *
 *  7. Mở file index.html, tìm dòng:
 *            var SHEET_ENDPOINT = '';
 *     dán URL vào giữa 2 dấu nháy:
 *            var SHEET_ENDPOINT = 'https://script.google.com/macros/s/AKfy.../exec';
 *
 *  8. Mở landing page, đăng ký thử 1 phiếu → kiểm tra Sheet đã có dòng mới.
 *
 *  LƯU Ý: mỗi lần sửa code này phải Triển khai lại bản mới (New deployment)
 *  hoặc "Quản lý triển khai" → sửa phiên bản thành "Mới nhất", nếu không
 *  Google vẫn chạy code cũ.
 * ============================================================================
 */

function doPost(e) {
  // Khoá để 2 người đăng ký cùng lúc không ghi đè nhau
  var lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    var d = JSON.parse(e.postData.contents);
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];

    sheet.appendRow([
      d.thoiDiem || new Date(),
      d.hoTen || '',
      "'" + (d.dienThoai || ''),   // dấu nháy đầu để Sheet giữ số 0 đầu SĐT
      d.xe || '',
      d.bienSo || '',
      d.laiThu || '',
      d.soNguoi || '',
      d.suKien || ''
    ]);

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: String(err) }))
      .setMimeType(ContentService.MimeType.JSON);

  } finally {
    lock.releaseLock();
  }
}

/** Mở URL web app bằng trình duyệt sẽ thấy dòng này — dùng để kiểm tra deploy ok */
function doGet() {
  return ContentService.createTextOutput(
    'Endpoint dang ky Mini Off Hilux & Brothers HCM 29/11/2026 — hoat dong binh thuong.'
  );
}
