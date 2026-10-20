# Danh mục Tỉnh/Thành và Phường/Xã

File `vietnam_administrative_units.json` là snapshot dùng cho địa chỉ khách hàng theo mô hình hai cấp, không có Quận/Huyện.

- Nguồn JSON: [Province Open API v2](https://provinces.open-api.vn/api/v2/?depth=2), tải ngày 19/10/2026. Đây là dịch vụ dữ liệu độc lập; không phải API chính thức của cơ quan nhà nước.
- Tham chiếu: [danh mục của Cục Thống kê](https://danhmuchanhchinh.nso.gov.vn/) và [Quyết định ban hành bảng danh mục/mã số](https://www.nso.gov.vn/default/2025/07/quyet-dinh-ban-hanh-bang-danh-muc-va-ma-so-cac-don-vi-hanh-chinh-viet-nam/).
- Snapshot có 34 Tỉnh/Thành và 3.321 đơn vị cấp xã, bao gồm Phường/Xã/Đặc khu. Đây là dữ liệu sau sắp xếp tháng 7/2025 theo [mô tả phiên bản của nhà cung cấp](https://provinces.open-api.vn/).
- Mã tỉnh lưu dạng chuỗi 2 chữ số; mã Phường/Xã là chuỗi 5 chữ số, giữ số 0 đầu. Quan hệ cha/con dựa trên danh sách wards của từng tỉnh trong nguồn.
- Chỉ lưu `code` và `name` cần cho ứng dụng; giữ metadata nguồn/ngày tải/số lượng ở đầu file. Quá trình import đã kiểm tra mã tỉnh/xã không trùng, số lượng và quan hệ tỉnh nếu nguồn cung cấp mã cha.

Ứng dụng đọc snapshot local. Khi nhập địa chỉ, trình duyệt chỉ gọi endpoint nội bộ có xác thực `sales.address.wards`, không gọi API bên ngoài và không gửi thông tin khách hàng cho nhà cung cấp dữ liệu.

Khi cập nhật danh mục, kiểm tra nguồn hành chính, thay đổi tên/mã và số lượng trước khi thay file; cập nhật test integrity tương ứng. Không dùng thay đổi danh mục để tự ghi đè `province_name`/`ward_name` đã lưu trong khai báo: đó là snapshot tên tại thời điểm nhập. Không suy đoán hoặc tự điền địa chỉ cho bản ghi cũ.
