# Sửa bốn regression test theo contract hiện hành

## Mục tiêu
Giải quyết4test FAIL trong website-competition-leaderboard-final-qa.md bằng assertion/fixture có căn cứ, không đổi source runtime, điểm, privacy, uniqueness hoặc giao diện Leaderboard. Chạy đủ16class trước khi kết luận.

## Trạng thái
DONE — 2026-11-04, đủ16class PASS168tests/3675assertions,0FAIL/ERROR/SKIP. Tracking tạo trước khi sửa tests; kết quả chi tiết mới bên dưới.

## Hiện trạng trước khi chỉnh sửa
- Đọc AGENTS.md, tracking final-QA/sync, dealer-call-button-refinement.md, PUBLIC_PRIVACY_REPORT.md, MONTHLY_DASHBOARD_REPORT.md; đối chiếu source thực tế.
- Git status: tracking sync và các QA/artifact từ task trước đang thay đổi/untracked; không ghi đè hoặc quy chúng cho task này.
- Báo cáo trước có168tests/4FAIL, đây là kết quả lịch sử. Sẽ preflight toyota_testing và tái hiện trước sửa trong task này.

## Các file source code liên quan
- Sẽ chỉ sửa tests/Feature/LeaderboardServiceTest.php, DealerProfileTest.php, DealerHistoryTest.php.
- Chỉ đọc app/Services/{LeaderboardService,DemoSubmissionScope,ToyotaRealisticDemo,DealerCallPhone,SubmissionIntake}.php; app/Models/CustomerSubmission.php; PublicDealerController; Blade dealers/show và components/dealer-registration/dealer-card; config/dealer-registration.php.
- Đọc bootstrap/test DB guard hiện có; tracking này và liên kết kết quả bổ sung vào final-QA, không xóa lịch sử.

## Những thay đổi đã thực hiện
Chưa sửa tests/runtime; xác nhận contract trước khi chỉnh.

### Cập nhật sau xác minh
- Preflight mới PASS toyota_testing/loopback15tables, không configcache. Tái hiện đúng4FAIL mới trước sửa; log/XML `.laravel-tools/competition/regression-contract-refinement/before.*`, không coi kết quả QA cũ là mới.
- LeaderboardServiceTest: giữ2aggregate, đếm riêng đúng2ownership lookups bằng SQL chính xác và binding ToyotaRealisticDemo::EMAIL; tổng4query. Kiểm tra COUNT/GROUP BY ở cả8/16participants, fixtureplate tường minh duy nhất. Không nới budget hoặc loại query không biết.
- DealerProfileTest privacy: fixture phone hợp lệ và approval trong config test đúng slug/số; trước approval assert phone/tel ẩn, sau approval assert tel chính xác cho cả HTML/JSON. Giữ toàn bộ assertions không lộ Sales/customer/admin/avatar và standing score1/view projection. Label “Khách hàng đã được duyệt” cũ không còn trong Blade nên thay bằng label card hiện hữu, giữ assertion approved count ở viewData thay vì thêm text vào runtime.
- DealerProfileTest missingphoto: assert dr-dealer-symbol fallback, không dr-dealer-photo/URL ảnh hỏng; giữ toàn bộ kiểm tra validation upload/authorization/disk rỗng/photo_path null/endpoint404.
- DealerHistoryTest: chỉ plate history.second đổi thành29D-428.13 bằng array_replace (tránh PHP array union giữ giá trị cũ), thêm assert hai plate khác nhau. Giữ toàn bộ tests attribution/snapshot/dealer_id giả/điểm A1+B1/Sales2/award2.
- Lần focused mới sau sửa: PASS4tests/120assertions. Không sửa runtime, template, config thật, schema hoặc guard.

## Quyết định kỹ thuật quan trọng
| Failure | Contract/căn cứ hiện hành | Sửa dự kiến, bảo vệ phải giữ |
|---|---|---|
| Service query budget | Mỗi salesScores/dealerScores chạy1lookup ownership từ ToyotaRealisticDemo::ownedSubmissionIds qua DemoSubmissionScope và1aggregate COUNT/GROUP BY; diagnostic QA đã xác minh, sẽ kiểm tra mới. | Giữ2aggregate, đếm riêng chính xác2lookup SQL+binding; tăng8→16participants và vẫn4query. Không bỏ query thừa hoặc dùng bound lỏng. |
| Dealer public phone/privacy | DealerCallPhone chỉ công khai số Dealer active đã lưu, hợp lệ và approval đúng slug/số; mặc định ẩn, không lấy phone Sales. Tracking dealer-call thay thế giả định cũ rằng mọi phone Dealer luôn hiện. | Fixture số test hợp lệ + config approval cách ly; thêm kiểm tra chưa approved thì ẩn, approved thì tel đúng. Giữ toàn bộ assertions không lộ Sales/customer/admin/evidence và standing score1. |
| Missing Dealer photo | Blade card hiện dùng dr-dealer-symbol khi không có photo; endpoint photo404. Copy placeholder/label thống kê cũ không còn trong view. | Kiểm tra icon fallback, không img photo/href ảnh hỏng; giữ validation upload, disk rỗng/photo_path null/endpoint404 và privacy. Không sửa UI để khôi phục copy cũ. |
| Dealer transfer fixture | CustomerSubmission + SubmissionIntake khóa/chặn trùng plate ở pending/approved; snapshot submissions.dealer_id không chuyển theo Sales. Hai khách khác nhau phải dùng plate khác nhau. | Chỉ đổi plate của history.second; giữ cố tình gửi dealer_id sai, snapshot A/B, score Dealer1+1/Sales2 và award preview2. Không bypass guard. |

## Kết quả kiểm thử
Chưa chạy mới. Chỉ sử dụng MySQL toyota_testing loopback sau preflight; bootstrap chặn migrate có sẵn. Không migrate/seed/reset DB thật. Không build/screenshot vì không sửa runtime/asset/UI.

### Kết quả cuối mới — 2026-11-04
- `php docs/figma/qa/competition-db-check.php`: PASS, MySQL loopback toyota_testing/15tables, configcache absent, actual SELECT DATABASE() khớp. Bootstrap hiện có đặt RefreshDatabaseState::$migrated=true để không migrate; tests dùng transaction/rollback và guard đã đọc. Không migrate/seed/reset DB thật.
- Baseline focused4tests trước chỉnh: exit1,4FAIL đúng4tên trong báo cáo. XML/log ở `.laravel-tools/competition/regression-contract-refinement/before.xml|log`; không lấy kết quả lịch sử làm baseline mới.
- Focused sau sửa đúng4test, không bỏ assertion nghiệp vụ: **PASS4tests/120assertions**. XML `focused.xml` cùng thư mục. Bỏ riêng copy UI lỗi thời trong cùng test không bỏ kiểm tra approved score1.
- **Đầy đủ16class, không filter:** **PASS168tests/3675assertions,0failures/0errors/0skipped**,10.034s, PHP8.5.11/PHPUnit12.5.40. XML `regression.xml` cùng thư mục xác nhận16class. Không dùng kết quả focused để thay cho full16class, không cộng rerun thành tổngcoverage.
- `php vendor/bin/pint tests/Feature/LeaderboardServiceTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerHistoryTest.php --test`: PASS, không cần autofix.
- `git diff --check`: PASS. Git diff/status chỉ thêm3file test của task này và docs; thay đổi sync/QA untracked cũ được giữ nguyên, không quy cho sửa regression. Không có thay đổi app/, routes/, resources/, config/, migrations hoặc asset/runtime.

Lệnh chạy đầy đủ:
```powershell
php vendor/bin/phpunit --bootstrap docs/figma/qa/competition-test-bootstrap.php --do-not-cache-result --log-junit .laravel-tools/competition/regression-contract-refinement/regression.xml tests/Feature/LeaderboardServiceTest.php tests/Feature/CompetitionLeaderboardTest.php tests/Feature/PublicLeaderboardTest.php tests/Feature/MonthlyDashboardTest.php tests/Feature/LandingPageTest.php tests/Feature/CompetitionHomepageTest.php tests/Feature/PublicPrivacyTest.php tests/Feature/DealerProfileTest.php tests/Feature/DealerHistoryTest.php tests/Feature/DealerDashboardTest.php tests/Feature/DealerAuthorizationTest.php tests/Feature/DealerRegistrationDraftTest.php tests/Feature/DealerRegistrationFinalTest.php tests/Feature/RegistrationLookupTest.php tests/Feature/RegistrationLookupStatesTest.php tests/Feature/CampaignRulesTest.php
```

## Những lỗi còn tồn tại
4FAIL theo lịch sử đang xác minh mới; không đánh dấu DONE khi chưa chạy đủ16class PASS. Có thể lộ thêm assertion cũ trong cùng test sau khi vượt assertion đầu, phải đối chiếu source trước khi thay.

### Kết luận cuối
Không còn test FAIL trong16class bắt buộc. Mục baseline và ghi chép4FAIL ở final-QA là lịch sử đã được thay thế bằng kết quả mới có căn cứ ở task này, không xóa lịch sử.
Giới hạn: không chạy full suite toàn ứng dụng/load/concurrency/browser mới vì chỉ sửa tests, không nghiệm thu lại pixel-perfect hoặc thay các giới hạn visual trong tracking UI. Không phát hiện lỗi runtime cần sửa.

## Task hoặc bước tiếp theo
Preflight, tái hiện4FAIL, sửa3file tests có căn cứ, chạy đầy đủ16class và Pint/diff, cập nhật kết quả mới và liên kết tracking.

### Sau hoàn tất
Không còn bước bắt buộc cho task4regression. Báo cáo final-QA và sync được bổ sung liên kết task/kết quả mới để không tiếp tục coi4FAIL là hiện trạng. Không đổi schema/guard/logic hoặc ghi dữ liệu ngoài database testing cách ly.
