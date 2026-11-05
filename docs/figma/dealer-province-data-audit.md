# Audit dữ liệu tỉnh/thành Dealer cho B14

## Mục tiêu
Kiểm kê Dealer thiếu province, xác định nguồn dữ liệu và đề xuất bổ sung có xác nhận. Chỉ đọc database local; không backfill, không sửa ứng dụng hoặc truy cập production.

## Trạng thái
DONE — phạm vi audit và đề xuất, ngày 2026-11-05. Tracking được tạo trước truy vấn kiểm kê mới. **Chưa thực hiện backfill**; dữ liệu tỉnh/thành của 2 Dealer local vẫn thiếu, cần mapping được xác nhận trước task cập nhật riêng.

## Hiện trạng trước khi chỉnh sửa
Tracking trước ghi nhận local có 2 Dealer đều province=NULL. Đây là kết quả lịch sử, cần truy vấn lại. Working tree có thay đổi B14/B15 và bộ lọc từ task trước; giữ nguyên, không quy thành thay đổi của audit này.

## Các file source code liên quan
Dealer model; PublicDealerController; DealerDirectoryProvinces; AdministrativeDivisions; Admin DealerController/DealerRequest/DealerPolicy; các migration Dealer; ToyotaDealerSeeder và hai snapshot trong database/data; tests ToyotaDealerSeederTest và DealerDirectoryFiltersTest. Chỉ đọc.

## Những thay đổi đã thực hiện
Chỉ thêm tài liệu `docs/figma/dealer-province-data-audit.md`. Không sửa source ứng dụng, migration, cấu hình, bộ lọc/tìm kiếm/phân trang/sắp xếp B14 hoặc dữ liệu.

Helper chỉ đọc và JSON evidence local được đặt trong thư mục ignored, ngoài public: `.laravel-tools/dealer-province-readonly-audit.php` và `.laravel-tools/dealer-province-readonly-audit-20261105.json`. Không thêm script này vào runtime, deploy hoặc Git tracking.

### Kiểm kê mới trên local

Truy vấn ngày 2026-11-05 lúc 06:28:37 UTC (13:28:37 Asia/Ho_Chi_Minh), MySQL `toyota` tại `127.0.0.1`, dùng config cache hiện hữu. Guard kiểm tra driver/host/database cấu hình và `SELECT DATABASE()` trước khi đọc bảng; thực hiện `START TRANSACTION READ ONLY`, kết thúc `ROLLBACK`. Không đọc users, customer_submissions hoặc dữ liệu khách hàng.

| ID | Mã Dealer | Tên | Trạng thái | province | toyota_source_id | Khớp snapshot theo source ID / mã |
|---|---|---|---|---|---|---|
| 1 | DEMO-P2A-ACTIVE | Demo Phase 2A Active | Active | NULL | NULL | Không / Không |
| 2 | DEMO-P2A-INACTIVE | Demo Phase 2A Inactive | Inactive | NULL | NULL | Không / Không |

Tổng 2 Dealer, 1 active, **2 thiếu province**, 0 province hợp lệ; `DealerDirectoryProvinces::groups()` trả rỗng. Chỉ Dealer active được đưa vào B14, nhưng audit vẫn liệt kê cả inactive để không bỏ sót dữ liệu. Không tìm ứng viên theo tên hoặc địa chỉ.

Source `DealerRegistrationPhase2aDemoSeeder` tạo đúng hai mã/tên/trạng thái này và không điền province; địa chỉ của active được ghi rõ là giả lập. Đây là căn cứ source giải thích dữ liệu local, không phải suy đoán tỉnh hoặc quyết định quyền xóa dữ liệu. Không chạy seeder, không mở file credentials và không dùng tỉnh của các hồ sơ khách hàng fixture trong seeder để gán cho Dealer.

**Không có Dealer local nào đủ bằng chứng để đề xuất một tỉnh cụ thể.** Không gán một đại lý Toyota thật trong snapshot cho hai fixture này. Nếu cần kiểm thử có tỉnh sau này, dùng fixture được cho phép trên toyota_testing; không thay dữ liệu local trong task audit.

### Nguồn dữ liệu Laravel và mức độ sử dụng

| Nguồn | Hiện trạng đã xác minh | Có thể dùng | Giới hạn |
|---|---|---|---|
| `dealers.province` | Chuỗi nullable, migration 000009; Dealer fillable có province | Nguồn runtime B14; các giá trị hợp lệ của Dealer active tạo options | Không có province_code/FK; hiện local đều NULL |
| `database/data/toyota_dealers.json` | Metadata source_url `https://www.toyota.com.vn/lien-he-dai-ly`, retrieved_at 2026-10-04; thực tế 87 dòng, 87 source ID và 87 mã khác nhau; 0 thiếu province | Ứng viên tham chiếu có định danh `toyota_source_id`; cần xác nhận đúng chi nhánh và nguồn hiện hành trước cập nhật | Đây là snapshot repository; chưa xác minh lại website bên ngoài hoặc production trong task này |
| `AdministrativeDivisions` / `vietnam_administrative_units.json` | Snapshot 2026-10-19, model two-tier-2025, thực tế 34 tỉnh với 34 mã khác nhau, 3.321 đơn vị cấp xã | Chuẩn hóa và kiểm tra code/name cho mapping đã được operator duyệt | Chỉ là danh mục địa giới, không có liên kết Dealer; không cung cấp bằng chứng vị trí của Dealer NULL |
| Admin Dealer form | Tỉnh/thành là input text tùy chọn, `nullable|string|max:255`; chưa bắt buộc khớp danh mục | Admin active có thể nhập dữ liệu đã xác nhận qua quy trình sẵn có | Không có trường xác nhận nguồn/mapping tỉnh; form gửi nhiều thuộc tính khác, phải đối chiếu toàn bộ giá trị khi lưu |
| `ToyotaDealerSeeder` | Dùng snapshot Toyota; match source ID, hoặc fallback code/tên; transaction và guard xung đột | Đọc để hiểu lịch sử import, không dùng làm backfill hẹp | Có thể tạo Dealer mới, cập nhật tên/contact/address/website/source ID và province; vượt phạm vi province-only; không chạy |
| Demo seeders, customer address, Sales, Figma | Không phải nguồn xác nhận địa chỉ Dealer | Không dùng cho mapping dữ liệu thật | Không suy từ tên, địa chỉ chưa xác minh, dữ liệu khách hàng, mã vùng điện thoại hoặc tỉnh người đăng ký |

`database/data/ADMINISTRATIVE_UNITS.md` ghi rõ nguồn danh mục là Province Open API v2 độc lập, không phải API nhà nước; có metadata tham chiếu danh mục nhà nước. Audit này đọc dữ liệu local và tài liệu nguồn, không xác nhận tình trạng pháp lý/địa giới hiện hành qua Internet.

#### Chênh lệch giữa hai snapshot

Snapshot Toyota có 34 nhãn province khác nhau; con số này **không có nghĩa** trùng danh mục hành chính 34 tỉnh. Khi so sánh tên sau khi chỉ bỏ tiền tố “Tỉnh”/“Thành phố” của danh mục để kiểm kê, còn 4 nhãn không khớp, liên quan 5 bản ghi:

| Nhãn trong snapshot Toyota | Số Dealer trong snapshot |
|---|---:|
| Bình Dương | 2 |
| Bình Phước | 1 |
| Bà Rịa - Vũng Tàu | 1 |
| Thừa Thiên - Huế | 1 |

Đây là chênh lệch dữ liệu giữa snapshot, không phải mapping được duyệt hoặc kết luận tỉnh mới cần ghi. Những nhãn khác cũng chỉ khớp tên, chưa đủ chứng minh dữ liệu còn hiện hành. Không tự gộp/đổi tên tỉnh, không sửa snapshot hoặc địa chỉ lịch sử khách hàng.

### Phương án bổ sung an toàn — chưa triển khai

1. **Chốt dataset và người xác nhận.** Lập kiểm kê chỉ đọc trên đúng database mục tiêu trong một bước được phép riêng; danh sách local 2 dòng này không đại diện 87 Dealer production. Người quản lý dữ liệu xác nhận dùng tên tỉnh theo danh mục nào và địa chỉ của đúng chi nhánh, không địa chỉ pháp nhân hoặc trụ sở khác.
2. **Lập mapping riêng theo từng Dealer**, ưu tiên exact `toyota_source_id` duy nhất có trong snapshot Toyota; mã Dealer chỉ là đối chiếu bổ sung và cần xác nhận nếu không có source ID. Không fallback tự động theo tên/địa chỉ, không tạo source ID hoặc liên kết mới khi thiếu bằng chứng. Thiếu ID, trùng/khác định danh, tỉnh cũ hoặc nguồn mâu thuẫn thì chuyển operator review và giữ nguyên NULL.
3. **Mẫu cột hồ sơ duyệt:** `dealer_id`, `dealer_code`, `toyota_source_id`, `expected_province`, `expected_updated_at` hoặc hash row, `confirmed_province_code`, `confirmed_province_name`, `evidence_reference`, `source_as_of`, `approved_by`, `approved_at`. Để trống giá trị chưa xác nhận, không điền tỉnh mẫu cho hai Dealer trên. Bằng chứng có thể là danh sách master do Toyota/chủ dữ liệu phê duyệt hoặc thông tin chi nhánh từ nguồn Toyota đã được người vận hành xác minh; lưu bản duyệt và checksum riêng ngoài public.
4. **Chọn cách cập nhật sau phê duyệt:** số ít có thể dùng màn hình Admin sẵn có `/admin/dealers/{dealer}/edit`, policy chỉ Admin active được sửa; ghi bằng chứng duyệt riêng và kiểm tra không đổi các input khác. Với nhiều Dealer, đề xuất task riêng xây dựng importer province-only mặc định dry-run, chỉ nhận mapping đã duyệt; không gọi DatabaseSeeder/ToyotaDealerSeeder và không thêm province vào runtime bằng fallback.
5. **Guard cho importer tương lai:** bind đúng host/database/manifest hash; backup và kiểm tra restore trước thực thi; validate ID/source identity, tên/code tỉnh theo phiên bản được duyệt; mặc định chỉ nhận province đang NULL/blank; nếu tỉnh đã có giá trị, record thay đổi từ lúc lập plan hoặc nguồn mâu thuẫn thì dừng/review. Transaction + row locks + so sánh expected row trước update; chỉ thay province và updated_at theo convention, giữ ID/code/name/slug/status/contact/ảnh/tài khoản và lịch sử. Không thay schema để ép mapping trong task này.
6. **Dry-run và QA cách ly trước execute:** hiển thị từng old/new/evidence và số apply/skip/conflict; test thiếu nguồn, trùng mapping, ID sai, stale row, province chưa duyệt, rollback và rerun idempotent. Sau cập nhật được phép, kiểm tra hash các cột khác không đổi, count Dealer/tài khoản không đổi, B14 active options đúng dữ liệu đã duyệt; search + province + ba sort/hai chiều + pagination giữ state, rank toàn kỳ và approved count không đổi. Không tự render inactive để làm đầy options.
7. **Rollback hẹp:** lưu trước/ sau từng row và plan; nếu cần hoàn tác thì chỉ restore province cũ cho đúng ID khi giá trị hiện tại vẫn bằng giá trị vừa áp dụng, khóa và kiểm tra không có cập nhật mới. Nếu đã thay đổi tiếp thì dừng review, không restore toàn bộ Dealer bằng dump cũ một cách mù. Backup đầy đủ là phương án phục hồi sự cố theo kế hoạch được operator duyệt riêng.

Không cung cấp/chạy câu lệnh UPDATE, seed hoặc backfill trong audit này. Không có mapping cụ thể nào được duyệt để thực thi.

## Quyết định kỹ thuật quan trọng
Không suy tỉnh từ tên/địa chỉ Dealer hoặc dữ liệu khách hàng. Snapshot Dealer có metadata nguồn là ứng viên tham chiếu, không tự động cấp quyền cập nhật. Danh mục hành chính không xác định liên kết giữa một Dealer và một tỉnh.

Giữ nguyên kết luận thiếu dữ liệu của `website-competition-dealer-filters-refinement.md`; bổ sung phát hiện snapshot 87 Dealer và chênh lệch tên tỉnh, không thay thế lịch sử bộ lọc. `LOCAL_DEALER_SETUP_REPORT.md` ngày 20/10/2026 mô tả 88 Dealer ở lần kiểm thử cũ/máy cũ: không dùng làm số liệu hiện tại. Kiểm kê mới trên database local đang cấu hình chỉ có 2 Dealer; chưa xác định nguyên nhân thay đổi dataset giữa các thời điểm và không suy rằng production cũng như vậy.

## Kết quả kiểm thử
- **PASS mới:** `php .laravel-tools/dealer-province-readonly-audit.php`; actual local MySQL toyota, READ ONLY transaction; kiểm kê 2 Dealer thiếu province và exact source ID/code candidates đều rỗng, options B14 rỗng.
- **PASS mới:** integrity cơ bản snapshot Toyota (87 dòng/source ID/code duy nhất, 0 thiếu province); snapshot hành chính (34 tỉnh/mã khác nhau, 3.321 xã); so sánh nhãn tỉnh chỉ để audit, không ghi dữ liệu.
- **PASS mới:** hash toàn bộ Dealer trước/sau truy vấn giống nhau: `03a2ec4e7a43ca3fd1ec651483b96f38be673b74a35feb181d5546656205d900`, cũng trùng evidence task trước. SHA256 snapshot Toyota `4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205`; snapshot hành chính `5dd8175d5d0c90e3c168a543606729a870f6e827533893dabe8cc434367e6b77`.
- **PASS mới:** `git check-ignore` xác nhận helper/JSON được ignore; `git diff --check` không có lỗi whitespace. Sandbox Git có lúc báo dubious ownership; chỉ chạy lại lệnh đọc bằng quyền được duyệt, không chỉnh safe.directory/chính sách.
- Chỉ đọc các test hiện có để hiểu contract, **không chạy lại** PHPUnit/build/browser: task chỉ audit/tài liệu, không đổi source/UI hoặc ghi DB. Kết quả 284 test/54 visual/42 regression trong tracking trước là **lịch sử**, không tính là test mới của task này.
- **Chưa kiểm chứng:** snapshot còn khớp nguồn online hiện tại, dữ liệu production, tính đúng của mapping cụ thể và cập nhật/rollback thực tế; chưa có backfill để kiểm thử.

## Những lỗi còn tồn tại
Hai Dealer local vẫn thiếu tỉnh/thành có chủ đích vì không có bằng chứng xác nhận. Chưa có Dealer local nào khớp snapshot bằng source ID/code; không thể đề xuất tỉnh cụ thể một cách an toàn. Chênh lệch 4 nhãn tỉnh/5 dòng trong snapshot Toyota cần review trước khi dùng làm mapping chuẩn. Không phát hiện lỗi mới của bộ lọc B14 trong phạm vi đọc source; không coi đây là nghiệm thu giao diện mới.

## Task hoặc bước tiếp theo
Chủ dữ liệu cung cấp mapping Dealer–province được xác nhận cùng evidence và chọn phiên bản tên tỉnh. Sau đó mở task riêng cho dry-run/import hẹp hoặc nhập Admin có kiểm soát trên môi trường được phép. Chưa có phê duyệt backfill hoặc production; audit này hoàn thành, không tự thực hiện bước cập nhật.
