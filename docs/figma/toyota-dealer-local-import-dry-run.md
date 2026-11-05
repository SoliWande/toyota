# Dry-run Dealer Toyota local — 2026-11-05

Chỉ đọc MySQL local toyota tại 127.0.0.1:3306. Không thực thi import. Source: database/data/toyota_dealers.json, snapshot 2026-10-04; SHA256 4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205. Không xác nhận dữ liệu production hiện hành.

Kết quả: INSERT 82; UPDATE 0; SKIP 0; CONFLICT 5. Đối chiếu định danh có 87 ứng viên INSERT; 5 dòng bị chuyển CONFLICT vì chưa xác nhận chính sách dùng nhãn tỉnh trong snapshot. Không có collision mã/source ID/name với Dealer local. Không chạy phần 82 dòng trong task này hoặc tự động bỏ qua 5 conflict.

is_active không có trong cả 87 dòng nguồn. Giá trị đề xuất cho bản ghi mới là true theo default model/schema, cần được phê duyệt để phát triển local; không phải trạng thái hoạt động đã xác minh online. Tỉnh giữ nguyên nguồn, không đổi tỉnh cũ sang tỉnh mới.

Proposed values đầy đủ gồm địa chỉ/contact/URLs/giờ mở cửa và slug nằm trong evidence ignored .laravel-tools/toyota-dealer-import-dry-run-20261105.json, trường plan[].proposed_values. Đối chiếu row với snapshot cố định hash ở trên; không copy dữ liệu khách hàng hoặc credentials vào báo cáo. Các dòng INSERT có existing_dealer_id=null, không có before row; UPDATE/SKIP rỗng. Slug là dự kiến, phải kiểm tra lại uniqueness khi execute.

| Dòng nguồn | Phân loại | Mã | Source ID | Tên Dealer | Tỉnh giữ nguyên | Slug dự kiến | Ghi chú |
|---:|---|---|---:|---|---|---|---|
| 1 | INSERT | TMV-574 | 574 | Toyota An Sương | Hồ Chí Minh | toyota-an-suong | Mới; chưa thực thi |
| 2 | INSERT | TMV-968 | 968 | Toyota An Suong- Chi nhánh Trường Chinh | Hồ Chí Minh | toyota-an-suong-chi-nhanh-truong-chinh | Mới; chưa thực thi |
| 3 | INSERT | TMV-404 | 404 | Toyota An Thành Fukushima | Hồ Chí Minh | toyota-an-thanh-fukushima | Mới; chưa thực thi |
| 4 | INSERT | TMV-984 | 984 | Toyota Bắc Giang | Bắc Ninh | toyota-bac-giang | Mới; chưa thực thi |
| 5 | INSERT | TMV-845 | 845 | Toyota Bắc Ninh | Bắc Ninh | toyota-bac-ninh | Mới; chưa thực thi |
| 6 | INSERT | TMV-408 | 408 | Toyota Bến Thành | Hồ Chí Minh | toyota-ben-thanh | Mới; chưa thực thi |
| 7 | INSERT | TMV-1007 | 1007 | Toyota Bến Tre | Vĩnh Long | toyota-ben-tre | Mới; chưa thực thi |
| 8 | INSERT | TMV-406 | 406 | Toyota Biên Hòa | Đồng Nai | toyota-bien-hoa | Mới; chưa thực thi |
| 9 | INSERT | TMV-665 | 665 | Toyota Biên Hòa - CN Cầu Đồng Nai | Đồng Nai | toyota-bien-hoa-cn-cau-dong-nai | Mới; chưa thực thi |
| 10 | INSERT | TMV-563 | 563 | Toyota Bình Định | Gia Lai | toyota-binh-dinh | Mới; chưa thực thi |
| 11 | CONFLICT | TMV-889 | 889 | Toyota Bình Dương | Bình Dương | toyota-binh-duong | Chưa duyệt nhãn tỉnh; định danh: INSERT |
| 12 | INSERT | TMV-1030 | 1030 | Toyota Bình Dương – CN Mỹ Phước | Hồ Chí Minh | toyota-binh-duong-cn-my-phuoc | Mới; chưa thực thi |
| 13 | CONFLICT | TMV-1011 | 1011 | Toyota Bình Phước | Bình Phước | toyota-binh-phuoc | Chưa duyệt nhãn tỉnh; định danh: INSERT |
| 14 | INSERT | TMV-966 | 966 | Toyota Bình Thuận | Lâm Đồng | toyota-binh-thuan | Mới; chưa thực thi |
| 15 | INSERT | TMV-426 | 426 | Toyota Buôn Ma Thuột | Đắk Lắk | toyota-buon-ma-thuot | Mới; chưa thực thi |
| 16 | INSERT | TMV-1009 | 1009 | Toyota Cà Mau | Cà Mau | toyota-ca-mau | Mới; chưa thực thi |
| 17 | INSERT | TMV-564 | 564 | Toyota Cần Thơ | Cần Thơ | toyota-can-tho | Mới; chưa thực thi |
| 18 | INSERT | TMV-986 | 986 | TOYOTA CẦN THƠ - CHI NHÁNH AN GIANG | An Giang | toyota-can-tho-chi-nhanh-an-giang | Mới; chưa thực thi |
| 19 | INSERT | TMV-989 | 989 | Toyota Đắk Lắk | Đắk Lắk | toyota-dak-lak | Mới; chưa thực thi |
| 20 | INSERT | TMV-410 | 410 | Toyota Đà Nẵng | Đà Nẵng | toyota-da-nang | Mới; chưa thực thi |
| 21 | INSERT | TMV-785 | 785 | Toyota Đà Nẵng chi nhánh Lê Đình Lý | Đà Nẵng | toyota-da-nang-chi-nhanh-le-dinh-ly | Mới; chưa thực thi |
| 22 | INSERT | TMV-976 | 976 | Toyota Doanh Thu - Thanh Hóa | Thanh Hóa | toyota-doanh-thu-thanh-hoa | Mới; chưa thực thi |
| 23 | INSERT | TMV-412 | 412 | Toyota Đông Sài Gòn | Hồ Chí Minh | toyota-dong-sai-gon | Mới; chưa thực thi |
| 24 | INSERT | TMV-573 | 573 | Toyota Đông Sài Gòn - Chi nhánh Gò Vấp | Hồ Chí Minh | toyota-dong-sai-gon-chi-nhanh-go-vap | Mới; chưa thực thi |
| 25 | INSERT | TMV-965 | 965 | Toyota Đông Sài Gòn - Chi nhánh Nguyễn Văn Lượng | Hồ Chí Minh | toyota-dong-sai-gon-chi-nhanh-nguyen-van-luong | Mới; chưa thực thi |
| 26 | INSERT | TMV-969 | 969 | Toyota Đông Sài Gòn - Chi nhánh Quận 9 | Hồ Chí Minh | toyota-dong-sai-gon-chi-nhanh-quan-9 | Mới; chưa thực thi |
| 27 | INSERT | TMV-995 | 995 | Toyota Đông Sài Gòn - Chi nhánh Thủ Đức | Hồ Chí Minh | toyota-dong-sai-gon-chi-nhanh-thu-duc | Mới; chưa thực thi |
| 28 | INSERT | TMV-975 | 975 | Toyota Giải Phóng - Chi Nhánh Nam Định | Ninh Bình | toyota-giai-phong-chi-nhanh-nam-dinh | Mới; chưa thực thi |
| 29 | INSERT | TMV-865 | 865 | Toyota Gia Lai | Gia Lai | toyota-gia-lai | Mới; chưa thực thi |
| 30 | INSERT | TMV-565 | 565 | Toyota Hà Đông | Hà Nội | toyota-ha-dong | Mới; chưa thực thi |
| 31 | INSERT | TMV-826 | 826 | Toyota Hải Dương | Hải Phòng | toyota-hai-duong | Mới; chưa thực thi |
| 32 | INSERT | TMV-1028 | 1028 | Toyota Hải Dương - Chí Linh | Hải Phòng | toyota-hai-duong-chi-linh | Mới; chưa thực thi |
| 33 | INSERT | TMV-424 | 424 | Toyota Hải Phòng | Hải Phòng | toyota-hai-phong | Mới; chưa thực thi |
| 34 | INSERT | TMV-979 | 979 | Toyota Hà Tĩnh | Hà Tĩnh | toyota-ha-tinh | Mới; chưa thực thi |
| 35 | INSERT | TMV-994 | 994 | Toyota Hiroshima Long Phước | Hồ Chí Minh | toyota-hiroshima-long-phuoc | Mới; chưa thực thi |
| 36 | INSERT | TMV-432 | 432 | Toyota Hiroshima Tân Cảng | Hồ Chí Minh | toyota-hiroshima-tan-cang | Mới; chưa thực thi |
| 37 | INSERT | TMV-1002 | 1002 | Toyota Hòa Bình | Phú Thọ | toyota-hoa-binh | Mới; chưa thực thi |
| 38 | INSERT | TMV-1019 | 1019 | Toyota Hòa Bình Minh Yên Bái | Lào Cai | toyota-hoa-binh-minh-yen-bai | Mới; chưa thực thi |
| 39 | INSERT | TMV-416 | 416 | Toyota Hoàn Kiếm | Hà Nội | toyota-hoan-kiem | Mới; chưa thực thi |
| 40 | CONFLICT | TMV-987 | 987 | Toyota Huế | Thừa Thiên - Huế | toyota-hue | Chưa duyệt nhãn tỉnh; định danh: INSERT |
| 41 | INSERT | TMV-1020 | 1020 | Toyota Hùng Hải Hà Nam | Ninh Bình | toyota-hung-hai-ha-nam | Mới; chưa thực thi |
| 42 | INSERT | TMV-422 | 422 | Toyota Hùng Vương | Hồ Chí Minh | toyota-hung-vuong | Mới; chưa thực thi |
| 43 | INSERT | TMV-1006 | 1006 | Toyota Hưng Yên | Hưng Yên | toyota-hung-yen | Mới; chưa thực thi |
| 44 | INSERT | TMV-1024 | 1024 | Toyota Hưng Yên - Chi nhánh Văn Lâm | Hưng Yên | toyota-hung-yen-chi-nhanh-van-lam | Mới; chưa thực thi |
| 45 | INSERT | TMV-993 | 993 | Toyota IDMC Hoài Đức | Hà Nội | toyota-idmc-hoai-duc | Mới; chưa thực thi |
| 46 | INSERT | TMV-1016 | 1016 | Toyota Kiên Giang | An Giang | toyota-kien-giang | Mới; chưa thực thi |
| 47 | INSERT | TMV-1015 | 1015 | Toyota Lạng Sơn | Lạng Sơn | toyota-lang-son | Mới; chưa thực thi |
| 48 | INSERT | TMV-998 | 998 | Toyota Lào Cai | Lào Cai | toyota-lao-cai | Mới; chưa thực thi |
| 49 | INSERT | TMV-988 | 988 | Toyota Long An | Tây Ninh | toyota-long-an | Mới; chưa thực thi |
| 50 | INSERT | TMV-663 | 663 | Toyota Long Biên | Hà Nội | toyota-long-bien | Mới; chưa thực thi |
| 51 | INSERT | TMV-1014 | 1014 | Toyota Long Thành | Đồng Nai | toyota-long-thanh | Mới; chưa thực thi |
| 52 | INSERT | TMV-420 | 420 | Toyota Lý Thường Kiệt | Hồ Chí Minh | toyota-ly-thuong-kiet | Mới; chưa thực thi |
| 53 | INSERT | TMV-684 | 684 | Toyota Lý Thường Kiệt - Chi nhánh Tân Phú | Hồ Chí Minh | toyota-ly-thuong-kiet-chi-nhanh-tan-phu | Mới; chưa thực thi |
| 54 | INSERT | TMV-430 | 430 | Toyota Mỹ Đình | Hà Nội | toyota-my-dinh | Mới; chưa thực thi |
| 55 | INSERT | TMV-972 | 972 | Toyota Nankai Hải Phòng | Hải Phòng | toyota-nankai-hai-phong | Mới; chưa thực thi |
| 56 | INSERT | TMV-745 | 745 | Toyota Nankai Vĩnh Phúc | Phú Thọ | toyota-nankai-vinh-phuc | Mới; chưa thực thi |
| 57 | INSERT | TMV-1027 | 1027 | Toyota Ngọc Anh Lâm Đồng | Lâm Đồng | toyota-ngoc-anh-lam-dong | Mới; chưa thực thi |
| 58 | INSERT | TMV-765 | 765 | TOYOTA Nha Trang | Khánh Hòa | toyota-nha-trang | Mới; chưa thực thi |
| 59 | INSERT | TMV-1017 | 1017 | Toyota Nhung Hồng Tuyên Quang | Tuyên Quang | toyota-nhung-hong-tuyen-quang | Mới; chưa thực thi |
| 60 | INSERT | TMV-1018 | 1018 | Toyota Ninh Bình | Ninh Bình | toyota-ninh-binh | Mới; chưa thực thi |
| 61 | INSERT | TMV-805 | 805 | Toyota Ninh Kiều | Cần Thơ | toyota-ninh-kieu | Mới; chưa thực thi |
| 62 | INSERT | TMV-996 | 996 | Toyota Okayama Đà Nẵng | Đà Nẵng | toyota-okayama-da-nang | Mới; chưa thực thi |
| 63 | INSERT | TMV-1013 | 1013 | Toyota PGS Quảng Trị | Quảng Trị | toyota-pgs-quang-tri | Mới; chưa thực thi |
| 64 | INSERT | TMV-643 | 643 | Toyota Pháp Vân | Hà Nội | toyota-phap-van | Mới; chưa thực thi |
| 65 | INSERT | TMV-990 | 990 | Toyota Phú Mỹ Hưng | Hồ Chí Minh | toyota-phu-my-hung | Mới; chưa thực thi |
| 66 | INSERT | TMV-992 | 992 | Toyota Phú Thọ | Phú Thọ | toyota-phu-tho | Mới; chưa thực thi |
| 67 | INSERT | TMV-997 | 997 | Toyota Phú Yên | Đắk Lắk | toyota-phu-yen | Mới; chưa thực thi |
| 68 | INSERT | TMV-945 | 945 | Toyota Quảng Bình | Quảng Trị | toyota-quang-binh | Mới; chưa thực thi |
| 69 | INSERT | TMV-602 | 602 | Toyota Quảng Ninh | Quảng Ninh | toyota-quang-ninh | Mới; chưa thực thi |
| 70 | INSERT | TMV-974 | 974 | Toyota Quảng Ninh - Cẩm Phả | Quảng Ninh | toyota-quang-ninh-cam-pha | Mới; chưa thực thi |
| 71 | INSERT | TMV-973 | 973 | Toyota Sông Lam | Nghệ An | toyota-song-lam | Mới; chưa thực thi |
| 72 | INSERT | TMV-991 | 991 | Toyota Tây Bắc Sơn La | Sơn La | toyota-tay-bac-son-la | Mới; chưa thực thi |
| 73 | INSERT | TMV-971 | 971 | Toyota Tây Ninh | Tây Ninh | toyota-tay-ninh | Mới; chưa thực thi |
| 74 | INSERT | TMV-1008 | 1008 | Toyota Thái Bình | Hưng Yên | toyota-thai-binh | Mới; chưa thực thi |
| 75 | INSERT | TMV-978 | 978 | Toyota Thái Hòa Từ Liêm | Hà Nội | toyota-thai-hoa-tu-liem | Mới; chưa thực thi |
| 76 | INSERT | TMV-724 | 724 | Toyota Thái Nguyên | Thái Nguyên | toyota-thai-nguyen | Mới; chưa thực thi |
| 77 | INSERT | TMV-566 | 566 | Toyota Thăng Long | Hà Nội | toyota-thang-long | Mới; chưa thực thi |
| 78 | INSERT | TMV-685 | 685 | Toyota Thanh Hóa | Thanh Hóa | toyota-thanh-hoa | Mới; chưa thực thi |
| 79 | INSERT | TMV-725 | 725 | Toyota Thanh Xuân | Hà Nội | toyota-thanh-xuan | Mới; chưa thực thi |
| 80 | INSERT | TMV-1001 | 1001 | Toyota Thập Nhất Phong Vĩnh Long | Vĩnh Long | toyota-thap-nhat-phong-vinh-long | Mới; chưa thực thi |
| 81 | CONFLICT | TMV-567 | 567 | TOYOTA THUẬN AN | Bình Dương | toyota-thuan-an | Chưa duyệt nhãn tỉnh; định danh: INSERT |
| 82 | INSERT | TMV-977 | 977 | Toyota Tiền Giang | Đồng Tháp | toyota-tien-giang | Mới; chưa thực thi |
| 83 | INSERT | TMV-1012 | 1012 | Toyota Tiến Thu Quảng Ngãi | Quảng Ngãi | toyota-tien-thu-quang-ngai | Mới; chưa thực thi |
| 84 | INSERT | TMV-1021 | 1021 | Toyota Trang Lê Quảng Nam | Đà Nẵng | toyota-trang-le-quang-nam | Mới; chưa thực thi |
| 85 | INSERT | TMV-999 | 999 | Toyota Từ Sơn | Bắc Ninh | toyota-tu-son | Mới; chưa thực thi |
| 86 | INSERT | TMV-434 | 434 | Toyota Vinh | Nghệ An | toyota-vinh | Mới; chưa thực thi |
| 87 | CONFLICT | TMV-623 | 623 | Toyota Vũng Tàu | Bà Rịa - Vũng Tàu | toyota-vung-tau | Chưa duyệt nhãn tỉnh; định danh: INSERT |

## Dealer hiện hữu ngoài snapshot — giữ nguyên, không tính vào 87 dòng nguồn

| ID | Mã | Tên | Active | Province | Source ID |
|---:|---|---|---|---|---|
| 1 | DEMO-P2A-ACTIVE | Demo Phase 2A Active | True | NULL | NULL |
| 2 | DEMO-P2A-INACTIVE | Demo Phase 2A Inactive | False | NULL | NULL |

Hai Dealer này là KEEP_UNTOUCHED ngoài danh sách nguồn; không gọi là SKIP của 87 dòng. Không xóa, không ghép, không đổi province hay status. Không xác định 7 hồ sơ khách hàng đang có là demo hay thật; giữ nguyên toàn bộ.

## Bằng chứng

Generated: 2026-11-05T06:54:10+00:00; Dealer SHA256 trước/sau: 03a2ec4e7a43ca3fd1ec651483b96f38be673b74a35feb181d5546656205d900; các count bảng không đổi. 13 bảng InnoDB, 4 khóa ngoại tới Dealer không có orphan. Full schema/index/FK/counts được lưu trong JSON evidence ignored.

Backup mới: CHƯA TẠO. Restore: CHƯA KIỂM CHỨNG. Không đủ điều kiện execute. Xem [kế hoạch](toyota-dealer-local-import-plan.md).
