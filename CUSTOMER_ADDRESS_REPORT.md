# Địa chỉ khách hàng — Toyota Community

## Thay đổi

- Form thêm/sửa khai báo có Tỉnh/Thành, Phường/Xã phụ thuộc Tỉnh/Thành và địa chỉ chi tiết (số nhà, đường hoặc thôn/ấp).
- Địa chỉ là tùy chọn để bảo toàn luồng hiện có. Khi nhập bất kỳ phần địa chỉ nào, cần chọn đủ Tỉnh/Thành và Phường/Xã; địa chỉ chi tiết có thể để trống, tối đa 500 ký tự.
- Backend kiểm tra mã hợp lệ và quan hệ Phường/Xã–Tỉnh/Thành; không tin tên tỉnh/xã do client gửi. Mã có số 0 đầu và tên từ danh mục được lưu riêng.
- Snapshot local có 34 Tỉnh/Thành và 3.321 Phường/Xã/Đặc khu, nguồn/ngày tải được ghi tại `database/data/ADMINISTRATIVE_UNITS.md`. Không phụ thuộc API bên ngoài khi sử dụng form.
- Dropdown Phường/Xã reset khi đổi tỉnh; hủy/bỏ qua phản hồi cũ khi người dùng chọn nhanh; có trạng thái đang tải/lỗi và nút thử lại. Giữ lựa chọn khi sửa hoặc khi validation thất bại.
- Sales chỉ sửa địa chỉ của khai báo thuộc mình khi đang chờ duyệt. Admin xem địa chỉ trong chi tiết duyệt theo quyền hiện có. Không nới quyền sửa bản ghi đã duyệt/từ chối.
- Địa chỉ chỉ có trong màn hình nội bộ. Các trường địa chỉ bị ẩn khỏi serialization model; public không truy vấn/hiển thị địa chỉ khách hàng. Endpoint danh mục cần Sales active, không trả dữ liệu khách hàng.
- Client cũ không gửi nhóm địa chỉ sẽ không vô tình xóa địa chỉ đã lưu. Form có thể xóa địa chỉ bằng cách để trống toàn bộ nhóm.

## Database và bảo toàn dữ liệu

Đã sao lưu MySQL local trước migration tại `.laravel-tools/customer-address/toyota-before-address.sql` (không commit). Chạy riêng migration `2026_10_19_000015_add_customer_address_to_customer_submissions` sau khi kiểm tra SQL qua `--pretend`.

Migration chỉ thêm 5 cột nullable vào `customer_submissions`: `address_line`, `province_code`, `province_name`, `ward_code`, `ward_name`. Không tạo bảng danh mục, không sửa migration cũ, không backfill, seed, reset hoặc xóa dữ liệu.

Đối soát các cột/bản ghi cũ trên cả 10 bảng và SHA-256 của 1.001 file evidence: giữ nguyên. 500 khai báo hiện có vẫn đầy đủ, các cột địa chỉ mới đều NULL. Bảng migrations chỉ thêm bản ghi của migration mới. Không thay đổi production VM.

## Kiểm thử

- Nhóm địa chỉ đầu tiên: 21 tests / 6.851 assertions đạt. Sau đó bổ sung kiểm tra form sau validation và dữ liệu sai kiểu.
- Toàn bộ suite cuối: **522 tests / 11.250 assertions**, exit 0; không lỗi, warning hoặc deprecated. Chạy với `--fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-warning`.
- Frontend build thành công bằng Node 20.17.0 và Vite hiện có; không thêm/cập nhật dependency.
- Playwright/Chrome headless kiểm tra chiều rộng 320/390/1440px: dropdown đúng, không tràn ngang, giữ dữ liệu edit, reset khi đổi/xóa tỉnh, tải lại sau lỗi, phản hồi chậm không ghi đè tỉnh mới, không lỗi JavaScript. Response danh mục được giả lập từ cùng snapshot local; authorization/JSON thực tế được kiểm tra bằng Feature tests.
- Đã xem ảnh chụp mobile/desktop của nhóm địa chỉ. Ảnh và kết quả nằm trong `.laravel-tools/customer-address/`.
- Test chạy trên database cách ly `toyota_testing_upgrade_20261019`, không dùng dữ liệu local ứng dụng.

## File thay đổi trong lượt này

Backend:

- `app/Services/AdministrativeDivisions.php` — đọc danh mục local.
- `app/Http/Controllers/Sales/AddressController.php` — trả danh sách Phường/Xã theo tỉnh.
- `app/Http/Requests/Sales/SubmissionRequest.php` — validate/lưu nhóm địa chỉ và tên snapshot.
- `app/Models/CustomerSubmission.php` — các trường địa chỉ và bảo vệ serialization.
- `app/Providers/AppServiceProvider.php` — singleton danh mục.
- `routes/web.php` — route nội bộ dưới auth/role Sales/account active.

Database/dữ liệu tham chiếu:

- `database/migrations/2026_10_19_000015_add_customer_address_to_customer_submissions.php`
- `database/data/vietnam_administrative_units.json`
- `database/data/ADMINISTRATIVE_UNITS.md`

Giao diện:

- `resources/views/components/customer-address-fields.blade.php`
- `resources/views/components/submission-address-details.blade.php`
- `resources/views/sales/submissions/form.blade.php`
- `resources/views/sales/submissions/show.blade.php`
- `resources/views/admin/submissions/show.blade.php`
- `resources/js/customer-address.js`
- `resources/js/app.js`

Tests/báo cáo:

- `tests/Feature/CustomerAddressTest.php`
- `CUSTOMER_ADDRESS_REPORT.md`

Các thay đổi nâng Laravel/public từ các lượt trước vẫn được giữ nguyên; chưa commit/push. Assets đã build ở `public/build` theo cấu hình gitignore hiện có.
