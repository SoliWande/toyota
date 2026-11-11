# P09B — Dự thảo đặc tả lấy mẫu C08/C07 để trình TMV

> **Bản hiện hành: cập nhật 2026-11-11 tại mục 9.** Mục 1–8 được giữ nguyên làm lịch sử dự thảo ban đầu. Khi có khác biệt về tháng lấy mẫu, giới hạn số lượng, đơn vị mẫu, shortage, overlap, quyền Admin và audit, dùng mục 9 cùng register UC01–UC10; không dùng các câu “chưa có đề xuất/chưa chốt phía người dùng” trong mục 1–8 làm trạng thái hiện hành. Các quyết định mới là **USER_CONFIRMED / PENDING_TMV**, không phải TMV_APPROVED hay quyền tạo/chạy migration.

## Mục tiêu

Chốt **bản dự thảo**, không chốt thay TMV: mô tả hai purpose lấy mẫu, cách freeze/tái lập, mô hình dữ liệu tối thiểu và kế hoạch A–E có điều kiện bắt đầu. Không triển khai source, migration, cuộc gọi, thay đổi điểm hoặc công bố.

## Trạng thái

DONE phạm vi phân tích/lập bản dự thảo — 2026-11-11; kiểm tra tài liệu PASS. **Triển khai BLOCKED, chưa có phê duyệt TMV.** Năm đề xuất của người dùng chỉ là đầu vào dự thảo; không phải authorization chạy migration. Hoàn tất P09B không có nghĩa đã hoàn tất hoặc được phép triển khai A–E.

Cập nhật cùng ngày — DONE phần cập nhật tài liệu, kiểm tra cấu trúc/nội dung PASS: đã bổ sung mười quyết định USER_CONFIRMED / PENDING_TMV và rà soát source phone/duplicate/quyền. Dòng DONE phía trên là kết quả bản đầu, được giữ làm lịch sử. Triển khai/migration vẫn BLOCKED đến khi có approval TMV và task riêng.

## Hiện trạng trước khi chỉnh sửa

- Đã đọc `AGENTS.md`, [P09A](phase-p09a-admin-call-audit-plan.md), riêng phần C08 của [inventory](admin-ui-inventory.md) và P09/P10 của [implementation plan](admin-ui-implementation-plan.md); đối chiếu thêm [P10A](phase-p10a-admin-awards.md) và source hiện tại. Working tree đã có thay đổi từ các task trước; không ghi đè hoặc tính chúng vào P09B.
- P09A để mở ba phương án quan hệ 60/15. Người dùng nay **đề xuất phương án hai batch độc lập**, thay thế phần “chưa có đề xuất lựa chọn” của P09A cho bản dự thảo này; chưa thay thế trạng thái chờ TMV duyệt. Giữ nguyên P09A và mọi lịch sử cũ.
- C08 có ba câu hỏi, ba outcome, queue/tabs, tỷ lệ theo Dealer và toast lưu Đúng. Dòng “sau 1 ngày”, “2 lần” và “trừ 1 điểm” trong Figma chưa đủ contract clock/validation/điểm. Các mâu thuẫn counts, câu 3 bỏ trống, mẫu số tỷ lệ và thiếu confirmation Sai trong P09A vẫn còn.
- Không đọc lại toàn bộ Figma C01–C07. P09B dùng bằng chứng C08 đã kiểm kê và đã đọc tại P09A: `165:832`, tabs `173:1809`, toast `173:2097`; không tuyên bố kiểm tra trực quan mới, prototype mới hoặc nghiệm thu UI.
- Chỉ đọc filesystem/source; không boot Laravel, không đọc runtime database/config cache, không kết nối production. Kết luận UTC là từ source, không phải kiểm chứng môi trường đang chạy.

## Các file source code liên quan

Không source nào được sửa. Các đường dẫn dưới đây là căn cứ rà soát; file dự kiến cho triển khai tương lai được liệt kê riêng tại kế hoạch A–E.

| Nhóm | Source hiện có | Kết luận liên quan |
|---|---|---|
| Cohort/attribution | `app/Models/CustomerSubmission.php`, `app/Enums/SubmissionStatus.php`, `app/Actions/UpdateCustomerSubmission.php` | Moderation là `status`; Dealer trên hồ sơ được snapshot khi tạo, reassignment được guard theo nghiệp vụ pending |
| Chuẩn hóa/chống trùng | `app/Services/RegistrationPhone.php`, `app/Services/SubmissionIntake.php`, `app/Services/SubmissionIntegrity.php`, `app/Actions/ApproveCustomerSubmission.php` | Chuẩn hóa khác với xác nhận số có thể gọi; mutex/validation không chứng minh mọi dữ liệu legacy duy nhất |
| Group/history | `app/Services/SubmissionVerification.php`, `app/Actions/RejectCustomerSubmission.php` | Verification độc lập moderation/điểm; reject chỉ pending, không phải thu hồi approved |
| Cohort điểm/demo | `app/Services/LeaderboardService.php`, `app/Services/DemoSubmissionScope.php`, `app/Services/DemoDataGuard.php`, `app/Services/ResetDemoRetirement.php` | Approved, submitted_at, attribution và demo có contract hiện hữu; không tự chuyển thành contract lấy mẫu |
| Kỳ/xếp hạng | `app/Enums/AwardPeriod.php`, `app/Actions/CreateAwardDraft.php`, `app/Services/AwardResults.php`, `app/Models/Award.php`, `app/Models/AwardWinner.php`, `app/Services/PublicAwardResults.php` | Weekly/monthly, preview live có fingerprint, snapshot công bố bất biến, public chỉ Dealer |
| Quyền/clock/UI hiện tại | `routes/web.php`, `app/Policies/CustomerSubmissionPolicy.php`, `app/Policies/AwardPolicy.php`, `config/app.php`, `app/Http/Controllers/Admin/AwardController.php`, `resources/views/admin/awards/index.blade.php` | Admin middleware hiện hữu; C07 read-only và disabled; clock source UTC |
| Tham chiếu test, không chạy | `tests/Feature/AwardWorkflowTest.php`, `tests/Feature/SubmissionApprovalRaceTest.php`, `tests/Feature/RegistrationLookupStatesTest.php` | Contract cần bảo vệ; không mặc định harness có migration phù hợp lệnh cấm hiện tại |

## Những thay đổi đã thực hiện

Chỉ tạo tài liệu `docs/figma/phase-p09b-sampling-spec.md`. Nội dung gồm decision register, hai batch, cohort/edge cases, schema/commands dự kiến, sampling tái lập, concurrency/privacy, kế hoạch phụ thuộc A–E và checklist TMV. Không sửa source, routes, policies, views, tests, migrations, `.env`, P09A hoặc dữ liệu.

Cập nhật 2026-11-11: chỉ sửa **chính file tài liệu này**, thêm mục 9 làm bản hiện hành; giữ nguyên mục 1–8 và kết quả kiểm tra lần đầu để không xóa lịch sử. Rà soát bổ sung các requests/service phone/duplicate thực tế được nêu tại mục 9.3; không sửa chúng.

## Quyết định kỹ thuật quan trọng

### 1. Decision register và cách đọc trạng thái — lịch sử bản đầu

**USER_PROPOSED / PENDING_TMV:** người dùng đã có đề xuất, TMV chưa duyệt. **OPEN / PENDING_TMV:** chưa có quy tắc được chốt. **TECH_DRAFT:** phương án kỹ thuật để review, không tự làm thành policy nghiệp vụ. Không có mục nào trong P09B được đánh dấu TMV_APPROVED. Khi duyệt phải bổ sung người có thẩm quyền, bằng chứng/reference, ngày, policy version, phạm vi/kỳ hiệu lực và các ngoại lệ; chưa có các thông tin đó ở thời điểm lập bản này.

| ID | Đề xuất từ người dùng | Liên hệ P09A | Trạng thái |
|---|---|---|---|
| UP01 | C08 batch 60 khách toàn hệ thống | Một phần TMV01 | USER_PROPOSED / PENDING_TMV |
| UP02 | C07 batch độc lập, 5 khách mỗi Dealer Top 3, tối đa 15 khi đủ điều kiện | Một phần TMV01, TMV04, TMV27 | USER_PROPOSED / PENDING_TMV |
| UP03 | Chỉ `CustomerSubmission.status = approved` | Một phần TMV02, chưa quyết định đơn vị khách và các điều kiện bổ sung | USER_PROPOSED / PENDING_TMV |
| UP04 | C08 ngẫu nhiên đều toàn hệ thống, không quota Dealer | Một phần TMV04 | USER_PROPOSED / PENDING_TMV |
| UP05 | C07 ngẫu nhiên riêng trong từng Dealer Top 3 | Một phần TMV04 | USER_PROPOSED / PENDING_TMV |

Phân loại gate: **S** bắt buộc trước schema/lấy mẫu (schema cần những phần quyết định cấu trúc; mọi phần S phải xong trước tạo batch thật); **C** trước ghi kết quả gọi, một số phải sớm hơn tại API counts hoặc UI; **E** trước xử lý sai lệch/điểm/công bố. Một ID có nhiều nhánh được tách theo thời điểm, không xem đề xuất một phần là đã giải quyết cả ID. Tất cả dòng còn PENDING_TMV.

| P09A ID | Đã có đề xuất, vẫn chờ TMV | S — trước schema/lấy mẫu | C — trước đọc counts/ghi cuộc gọi/UI | E — trước sai lệch/công bố |
|---|---|---|---|---|
| TMV01 | UP01/02: hai purpose, 60 và 5×Top 3 | Duyệt số lượng; kỳ/chiến dịch/cadence, wave và khóa chống batch trùng | — | — |
| TMV02 | UP03: approved là điều kiện cần | Đơn vị submission/khách/phone; QR/Sales; có cần group member hoặc điều kiện khác | — | — |
| TMV03 | Chưa có | submitted_at/reviewed_at, approval cutoff/grace/late approval, freeze và chính sách source drift | Cách xử lý hồ sơ stale trước/khi ghi gọi theo chính sách S | Gate stale trước công bố |
| TMV04 | UP04/05: đều toàn hệ thống và riêng từng Dealer | Duyệt sampling design; có điều kiện ưu tiên/loại nào khác không | — | — |
| TMV05 | Chưa có; “tối đa 15” chưa chốt shortage | Thiếu 60/5/Top 3, phone không hợp lệ: partial/dừng/thay và căn cứ | — | Thiếu mẫu có đủ điều kiện công bố không |
| TMV06 | Chưa có | Inactive/blocked/demo; alias trùng; chống trùng qua kỳ/wave và phạm vi overlap | Freshness/tái sử dụng bằng chứng nếu có | — |
| TMV07 | Chưa có | Ai tạo/freeze/void/regenerate, reviewer, seed, giới hạn wave; trạng thái kỳ được tạo batch | — | — |
| TMV08 | Chưa có | Calendar/version, hiệu lực, bounds UTC cũ/weekly; không tự chọn VN 00:00 | — | Xử lý lịch sử công bố theo calendar đã duyệt |
| TMV09 | Chưa có | Tập trường PII cần snapshot nếu chọn dùng snapshot | Ngày “Đã xác minh”, script snapshot/live và cảnh báo drift | — |
| TMV10 | Chưa có | — | Ba đáp án required/null, mapping overall, override và câu 3 bỏ trống | — |
| TMV11 | Chưa có | — | Sai số/từ chối/không nhớ/thiếu dữ liệu và outcome hợp lệ | — |
| TMV12 | Chưa có | — | tel/telephony, nhận việc/start attempt; click không chứng minh cuộc gọi | — |
| TMV13 | Chưa có | Mô hình ownership/lease, scope người xem/ghi để thiết kế constraint | TTL/heartbeat/hết hạn/takeover và race cụ thể | — |
| TMV14 | Chưa có | — | Sau một ngày nghĩa gì, mốc tính, khung giờ/ngày nghỉ, nhắc việc | — |
| TMV15 | Chưa có | Cần giữ history/correction, không overwrite attempt | Hai lần tổng hay retry, liên tiếp, mở lại và người có quyền | — |
| TMV16 | Chưa có | Có thay mẫu hay giữ mẫu khi no-answer, liên quan S/TMV05 | Terminal/Đã xong/mẫu số sau no-answer | Đủ 5 và gate publication hay không |
| TMV17 | Chưa có | — | Đếm khách/attempt, predicate tab/retry chưa đến hạn; chốt trước task B counts | — |
| TMV18 | Chưa có | — | Tử số/mẫu số, rounding, zero và scope tỷ lệ trước hiển thị metric D | Ngưỡng [x]%, > hay ≥, min sample, phạm vi áp chế tài |
| TMV19 | Chưa có | — | — | Huỷ group/approval/eligibility, receipt, thông báo, identity occupancy |
| TMV20 | Chưa có | — | — | Trừ theo khách/câu/attempt, Dealer/Sales, kỳ, sàn, chống trùng và reversal |
| TMV21 | Chưa có | — | — | Loại khỏi kỳ hay toàn hệ thống, quyền duyệt, appeal/history |
| TMV22 | Chưa có | Điểm chốt và phiên bản Top 3 ban đầu liên quan TMV03/07/28 phải rõ trước award_top3 | — | Ranking frozen/live để đôn hạng, tie-break, mẫu thay thế, không đủ 3 |
| TMV23 | Chưa có | Tách quyền đọc/tạo batch/ghi gọi; không tự cấp Manager | Quyền caller/corrector cụ thể | Ai adjudicate/deduct/exclude/publish/reverse; có cần reviewer khác |
| TMV24 | Chưa có | Trường snapshot, mã hóa/khóa, masking, retention, full phone access và phạm vi xem | Note required/length/visibility, có ghi âm hay không (chưa có contract) | Retention/chia sẻ hồ sơ decision nếu bổ sung |
| TMV25 | Chưa có | — | Confirmation Sai, huỷ, lỗi, claim conflict, loading/empty/last item và toast thiếu; gate D | Confirmation tác động điểm nếu tách riêng E |
| TMV26 | Chưa có | Batch void/revision và lịch sử bất biến | Sửa/reopen/void attempt và ảnh hưởng counts | Correction sau decision/penalty và bù trừ |
| TMV27 | UP02 chỉ đề xuất tách batch | Cho phép cùng khách ở hai purpose/kỳ, khóa dedupe và schema liên kết | Có dùng lại attempt không, freshness/source version | Cùng finding không bị phạt hai lần |
| TMV28 | Chưa có | Award baseline, kỳ/trạng thái và mốc tạo batch cần xác nhận trước freeze | — | 5 đã gọi hay 5 hợp lệ, retry/pending/late approval, gate và stale |
| TMV29 | Chưa có | — | — | Amendment sau publish, unique kỳ, bất biến và public Dealer/internal Sales |
| TMV30 | Chưa có | — | Nếu có nhắc gọi: kênh, recipient, consent, delivery/retry/wording | Thông báo sai lệch/điểm/công bố theo contract riêng |

Các quyết định thuần E (đặc biệt TMV19–21, phần đôn hạng TMV22, TMV29) không cần được bịa ra để làm A–D. Nhưng A–D không được hiển thị cam kết đã huỷ/trừ điểm hoặc cho phép công bố. Nếu wording/confirmation Figma không thể thể hiện trung thực khi E chưa có, interaction đó ở D phải BLOCKED, chờ thiết kế/contract; không tự sửa chữ hoặc thêm UI.

### 2. Cohort: sự thật source và các điều kiện còn thiếu

| Trường hợp | Source hiện tại và ảnh hưởng | Quy tắc dự thảo / việc phải TMV chốt |
|---|---|---|
| Approved | Enum có pending/approved/rejected; UP03 đề xuất chỉ approved. Approval là state hiện tại, không phải bằng chứng đã nghe máy/group member | Freeze phải ghi policy/cutoff và tập approved tại snapshot đọc; pending/rejected không lọt vào nếu UP03 được duyệt. Không gọi approve để lưu Đúng |
| Dealer attribution | Model gán `submission.dealer_id` khi tạo; Dealer scores join chính cột này. Sales hiện chuyển Dealer không chuyển hồ sơ cũ | Dùng submission.dealer_id cho pool/stratum/ảnh chụp Dealer, không dùng users.dealer_id hiện tại; source không có Dealer hợp lệ cần rule lỗi/ngoại lệ, không gán Dealer khác |
| Nguồn QR/Sales | Leaderboard nhận user role Sales hoặc dealer_qr có sales_id null; không lọc Dealer active/Sales blocked | Approved chưa tự bao hàm đúng cohort tính điểm. TMV02/06 quyết định lấy mọi approved hợp lệ hay chính cohort đóng góp của Leaderboard. Không sao chép filter activity/group hoặc bỏ QR theo suy đoán |
| DemoSubmissionScope | Service ghi rõ chỉ cho metrics, không phải auth/identity. Bình thường loại owned demo IDs; include_in_metrics cần guard môi trường; retired gọi assertRetired rồi không loại IDs theo nhánh cũ | Đề xuất C07 align demo scope của ranking và C08 dùng cùng scope đã duyệt; đây vẫn là đề xuất TMV06. Freeze lưu policy version, nhánh demo, digest tập loại và manifest sau lọc. Guard lỗi phải dừng tạo batch, không biến thành pool rỗng. Không sửa config/retirement hoặc đưa demo production vào mẫu để đủ số |
| Phone thiếu/không hợp lệ | Phone nullable/legacy có thể thiếu normalized. RegistrationPhone loại separator, đổi +84/0084 thành 0; normalize không xác minh khả năng gọi | Cần contract số đủ điều kiện gọi, alias disagreement và snapshot/contact hiện tại. Không tự tính Sai, điền số mẫu hoặc bỏ hồ sơ rồi coi đủ cohort |
| Trùng phone | SubmissionIntake serialize guard pending/approved; so cả hai alias. Không được suy ra unique toàn DB/khách từ guard ứng dụng, đặc biệt legacy/import | Chốt đơn vị “khách”: uniform theo submission khác uniform theo người/phone. Nếu chọn phone/person, phải có rule gom nhóm và chọn hồ sơ đại diện/Dealer khi nhiều hồ sơ; không tự chọn mới nhất/ID đầu. Lưu manifest sau resolution đã duyệt, không normalize lại theo code mới khi tái lập |
| Sai trạng thái/đổi sau chọn | Nguồn có guard nhưng trạng thái/dữ liệu liên hệ/verification vẫn có thể đổi qua nghiệp vụ hoặc sửa lỗi | Fingerprint phát hiện drift, giữ membership và ảnh chụp cũ. Đề xuất đánh dấu stale và chặn ghi/gọi dựa trên thông tin không còn xác nhận cho tới resolution được duyệt; không reroll, chuyển stratum hay tự cập nhật điểm. Rule giữ/void/thay/reverify phải chốt TMV03/05/26 |
| Kỳ theo thời gian | Leaderboard dùng submitted_at trong [start,end), không reviewed_at. Approval hiện tại mới làm contribution đủ điều kiện; không có cohort freeze sẵn | “Toàn hệ thống” chỉ xác định phạm vi Dealer, chưa xác định all-time/tháng/tuần/chiến dịch. Cần tách kỳ submitted_at, approval cutoff, freeze_as_of và publication_at. Late approval không tự nhập vào manifest đã freeze |
| Trùng giữa purpose | Hai pool có thể giao nhau; batch độc lập không đồng nghĩa hai tập chắc chắn rời nhau | TMV06/27 chốt có cho overlap hay không. Nếu cho phép: cùng submission có hai item thuộc hai batch, không tự reuse attempt. Nếu cấm: phải định nghĩa thứ tự freeze/exclusion, làm thay đổi phân phối lấy mẫu; không còn diễn giải đơn giản như hai lần rút độc lập cùng pool |
| Thiếu 60 / thiếu 5 / dưới 3 Dealer | “Tối đa 15” chưa xác nhận lấy hết pool nhỏ hoặc đủ điều kiện công bố | Cần chọn rõ: dừng do thiếu mẫu, partial có requested/actual riêng, hay chính sách thay/đợt khác. Không tự dùng min(N,k), lấy lặp, bù Dealer khác, kéo hồ sơ ngoài kỳ hoặc tự loại Dealer Top 3. Pool rỗng cũng phải có quyết định; không coi là audit hoàn tất |

**Calendar:** `config/app.php` hiện UTC; `LeaderboardService::bounds` tuần từ thứ Hai, tháng từ đầu tháng, end exclusive. Ví dụ source tháng 10/2026: `[01/10 00:00 UTC, 01/11 00:00 UTC)` tương ứng VN 07:00. VN 00:00 sẽ là `[30/09 17:00 UTC, 31/10 17:00 UTC)`, là cohort khác. Không tự chọn VN 00:00, đổi global timezone hoặc viết lại DATETIME. Batch cần bounds/zone/calendar_version/cutoff/effective period được duyệt, tương thích Award hiện có; không mặc định cadence C08 giống kỳ C07. `AwardResults::preview` kiểm tra canonical bounds và từ chối published; global timezone change còn có thể làm draft cũ không preview được.

### 3. Hai batch, không tự gộp quy trình

| Thuộc tính | C08 — general_audit | C07 — award_top3 |
|---|---|---|
| Purpose kỹ thuật dự kiến | `general_audit` | `award_top3` |
| Scope cần được duyệt | Toàn hệ thống trong kỳ/chiến dịch và cutoff cụ thể; không tự hiểu là all-time | Award/kỳ và revision Top 3 cụ thể, không chỉ award_id |
| Requested theo UP01/02 | 60 đơn vị khách sau khi định nghĩa unit | 5 mỗi Dealer thuộc Top 3, tổng tối đa 15 nếu đủ ba Dealer và đủ hồ sơ |
| Sampling theo UP04/05 | Một pool, không quota Dealer, mỗi unit hợp lệ xác suất như nhau | Các stratum Dealer riêng, ngẫu nhiên đều trong mỗi stratum; không lấy Top 15 toàn hệ thống |
| Freeze phải lưu | Manifest toàn pool, policy/clock/seed/selection/fingerprint | Manifest từng Dealer, thứ tự/rank/score/Dealer snapshot của Top 3 và AwardResults baseline fingerprint |
| Tác dụng | Queue kiểm tra chung; không tự làm C07 đạt | Bằng chứng kiểm tra gắn baseline; không tự công bố hoặc thay Top 3 |

Top 3 draft hiện lấy từ `AwardResults`, qua `LeaderboardService`: score là COUNT approved, tie-break score DESC → MAX(submitted_at) ASC → ID ASC. Fingerprint hiện bao gồm cả kết quả Sales và Dealer cùng kỳ; phải lưu đúng fingerprint và danh sách Dealer đã dùng, không gọi một hash Dealer rút gọn là fingerprint hiện hữu. Không snapshot Sales PII vào public hoặc dùng kết quả Sales thay Dealer.

Mốc freeze ranking, điều kiện kỳ được tạo audit và cách xử lý baseline thay đổi còn chờ TMV03/07/28. Nếu muốn dùng kỳ đã published phải có contract audit lịch sử riêng; không gọi preview draft trên published hoặc mutate snapshot để làm được. `AwardResults` chỉ trả Top 3, không cung cấp rank 4 để đôn hạng. Cơ chế thay Dealer/ranking đầy đủ thuộc E, không nằm trong mẫu ban đầu A. Hai purpose không tự chia sẻ tiến độ/đạt/attempt; bằng chứng C08 không tự mở nút Công bố kỳ của C07.

### 4. Lấy mẫu có thể tái lập — TECH_DRAFT

1. **Đầu vào đã duyệt:** purpose, scope_key/kỳ/cutoff/calendar, generation hợp lệ, đơn vị mẫu, eligibility policy/version, phone/dedupe/demo policy và shortage policy. Từ chối lệnh khi contract bắt buộc còn thiếu; không điền default nghiệp vụ. Filter UI không được tùy ý đổi manifest của batch.
2. **Snapshot nhất quán:** cohort, phone resolution, Dealer attribution và baseline Top 3 phải được đọc từ cùng snapshot nhất quán trên connection được kiểm soát. Đề xuất consistent read transaction với isolation được chỉ định/kiểm chứng khi triển khai, không giả định default MySQL; kiểm tra phiên bản Award trong cùng protocol. Không trộn paginated query live qua nhiều request hoặc locking-current-read với snapshot cũ rồi gọi là cùng thời điểm. Nếu phát hiện baseline/source xung đột phải abort/review theo policy; không thay manifest âm thầm.
3. **Manifest đầy đủ:** canonical encoding/version cố định, unit key ổn định theo TMV02, candidate source ID(s), Dealer/stratum, source fingerprint, eligibility metadata tối thiểu; thứ tự canonical theo key. Lưu toàn bộ manifest private cùng digest, không chỉ eligible_count/hash hoặc selected IDs. Lưu policy/cutoff, demo branch/exclusion digest và baseline đủ để kiểm chứng về sau mà không query cohort live. Snapshot candidates không cần chứa toàn bộ PII gọi.
4. **Seed:** đề xuất 32 byte từ CSPRNG server cho mỗi batch, độc lập giữa hai purpose; encrypt với key/version được quản lý, không cho client nhập seed, không trả seed/manifest trên list API, không ghi log seed. Người giữ quyền tái lập phải được TMV07/24 duyệt. Giữ key đọc lịch sử theo retention/rotation đã duyệt; không đổi seed vì kết quả “không đẹp”.
5. **Thuật toán dự kiến `hmac_sha256_sort_v1`:** với mỗi candidate, tính khóa sắp xếp HMAC-SHA256 dùng seed, message canonical có version/domain, purpose, scope/generation, stratum key và unit key. Encoding phải phân tách không mơ hồ; lưu vector kiểm thử. Sort toàn bộ digest binary tăng dần, tie-break unit key; lấy k đầu, không hoàn lại. C08 dùng một stratum toàn pool. C07 dùng ba stratum theo Dealer snapshot, domain tách biệt từng Dealer, mỗi stratum requested 5. Đây là phép rút đều theo giả định PRF của HMAC trên **unit đã được duyệt**, không phải quota Dealer cho C08. Không dùng SQL RAND(), seed timestamp, modulo có bias, iteration order DB hoặc random mỗi refresh.
6. **Persist atomically:** một command tạo batch **và freeze** manifest/seed/selected items/Dealer snapshot/source fingerprint/frozen_at/event trong cùng transaction. Không expose batch đã tạo nhưng manifest còn sống; trạng thái preparing nếu có chỉ nội bộ transaction. `eligibility_as_of` là mốc snapshot đọc, `frozen_at` là mốc đóng gói, không dùng hai mốc này thay approval deadline. Kiểm tra actual_count/stratum counts khớp selected rows trước commit. Nếu shortage policy chưa duyệt thì không freeze; không tự rút ít hơn hoặc đổi seed.
7. **Tái lập:** phiên bản algorithm + seed đã giải mã + manifest canonical frozen + config sampling phải ra đúng selected keys/order/strata/digest. Không cần cohort hiện tại còn như cũ. Sau freeze, source drift tạo dấu vết stale; không sửa manifest, seed hoặc snapshot cho “khớp lại”. Reproduce là kiểm chứng read-only, không tạo wave/batch mới.

Một snapshot đọc nhất quán bảo đảm dữ liệu hợp lệ **as-of**, không bảo đảm hồ sơ không đổi trước/giữa cuộc gọi. Drift được kiểm tra lại server-side khi claim/ghi và theo rule đã duyệt; hash của dữ liệu hiện tại không thể tái dựng manifest cũ. Source fingerprint đề xuất version riêng/HMAC có key_id cho trạng thái, submitted/reviewed timestamps, source, Dealer/Sales IDs, hai phone aliases và trường script/verification được duyệt; tránh dựa duy nhất updated_at có độ phân giải giây. Có thể tham khảo cơ chế version trong UpdateCustomerSubmission, không gắn quyền sửa hồ sơ vào quyền gọi và không lưu thừa address/PII vì helper cũ có sẵn.

### 5. Mô hình dữ liệu tối thiểu: giữ bốn thực thể P09A

Đây là **schema dự kiến để review**, chưa có migration hay định nghĩa enum nghiệp vụ cuối cùng. Tên trạng thái/outcome phải chốt tại S/C; không mở rộng `SubmissionStatus`. Bốn thực thể vẫn đủ cho hai purpose độc lập; không cần bridge dùng chung item/attempt khi chưa có rule tái sử dụng.

| Thực thể | Nhóm dữ liệu dự kiến | Constraints / lưu ý |
|---|---|---|
| `call_audit_batches` | ID; purpose; scope_key không null; award_id tùy purpose; generation/revision; policy/calendar/manifest/algorithm versions; period bounds/timezone và approval cutoff/as_of/frozen_at; requested/actual counts và config từng stratum; seed encrypted/key_id; manifest private encrypted + digest; Top 3/baseline snapshot/fingerprint; creator; lifecycle/version; creation idempotency key/payload digest | Unique business family+generation và idempotency scope. Family phải bao gồm cadence/scope được duyệt, không chỉ random request key. Purpose award_top3 tham chiếu Award và baseline; general không tự gắn Award. Không dựa unique nullable award_id vì MySQL cho nhiều NULL. Manifest/frozen fields bất biến sau commit |
| `call_audit_items` | Batch, unit key, submission ID (hoặc source IDs nếu unit gom nhóm được duyệt), selection order/stratum, Dealer/Sales attribution snapshot; source fingerprint/version; PII script/contact tối thiểu encrypted theo retention; queue state/derived latest outcome; attempt counter/retry_due; owner/claim token hash/lease expiry/version | Unique(batch,unit), unique(batch,selection_order), unique(batch,submission) khi unit là submission. Không unique submission toàn hệ thống. Attribution không cascade theo Sales chuyển Dealer; không cascade delete làm mất lịch sử. PII không mặc định serialize vào API |
| `call_audit_attempts` | Item, attempt_no, actor, start/record instants; 3 câu trả lời nullable + questionnaire version; outcome/note private; source version used; idempotency key/payload digest; retry_due snapshot nếu có | Unique(item,attempt_no), idempotency scope cho save. Completed attempt bất biến, correction có reference/history. No-answer không ép ba đáp án thành false; final mapping theo TMV10–16 |
| `call_audit_events` | Batch/item/attempt refs; loại event, actor, timestamp, before/after version, reason/reference; correlation/idempotency; freeze/claim/retry/save/stale/void/correction history | Append-only, không log raw phone/plate/note/seed; giữ dấu vết replay và takeover, không dùng thay effect ledger điểm |

Manifest encrypted trong batch là phương án tối thiểu, nhưng query/index không dựa vào encrypted JSON. Số candidate thực tế, dung lượng, memory sort và atomicity chưa đo vì không truy cập DB. Nếu manifest quá lớn, chỉ sau review kỹ thuật mới tách candidate table hoặc private immutable artifact có version/digest/atomic commit-reference. Đó là mở rộng vật lý có lý do hiệu năng/toàn vẹn, không đổi đơn vị/chính sách lấy mẫu; phải duyệt lại schema/backup/retention và không âm thầm thêm thực thể thứ năm.

**Concurrency và chống tạo lại:** business scope/generation do server xác thực, unique constraint xử lý hai Admin tạo cùng lúc; request key khác không được vượt khóa một generation hợp lệ. Cùng key/cùng payload trả lại batch đã commit, cùng key/khác payload là conflict. Timeout sau commit không tạo seed mới. Refresh/list GET chỉ đọc. Không có auto-resample khi đổi tab, mở trang hoặc đổi nguồn. Void/replacement generation cần quyền/lý do/wave được duyệt, giữ batch gốc và link revision, không xóa để né uniqueness.

Claim/save dùng transaction, row lock hoặc compare-and-swap version; ràng buộc owner/token/lease và source version tại server. Hai Admin không được cùng commit attempt hợp lệ; expired owner không ghi đè kết quả mới. Trình tự lock khi cần đọc hồ sơ phải thống nhất với intake mutex → submission row lock hiện có, rồi audit rows theo thứ tự cố định; không thêm đường khóa ngược gây deadlock. Protocol cụ thể/isolation/retry transaction cần concurrency test ở A/C, không chỉ unit test một connection. Event và kết quả commit cùng nhau; toast chỉ dựa trên response thành công của commit thực.

**Privacy/quyền:** giữ auth/auth.session, role:admin, account.active và CSRF/throttle theo convention. Policy batch/item mới phân biệt read/generate/claim/record/correct/void/reproduce, kiểm tra batch scope cho mọi ID. `CustomerSubmissionPolicy::viewAny` hiện dành Sales, không phải quyền Admin mới; review/publish hiện có cũng không tự cấp quyền penalty. Không thêm Manager. List che phone, full contact chỉ ở workspace/động tác được duyệt; không public URL, export, raw seed, artifact manifest hoặc logs/analytics chứa PII. Encryption phải kèm kiểm soát serialization, private storage, no-store/cache và retention/key rotation; mã hóa không thay authorization. Chính sách snapshot PII/history retention phải dung hòa khả năng tái lập và xóa dữ liệu theo quyết định TMV24.

### 6. Commands/API dự kiến, chưa có route hoặc lệnh Artisan

| Command/query logic | Tác dụng được đề xuất | Gate |
|---|---|---|
| `PlanSampling` | Dry-run private: validate policy/capacity/baseline, không persist/không seed mới mỗi GET; không dùng output live làm manifest đã freeze | S; chỉ tool Admin được duyệt, không thêm nút C08 ngoài thiết kế |
| `CreateAndFreezeBatch` | Mutation explicit qua POST/command được duyệt, server scope+idempotency, transaction tạo cả bốn nhóm dữ liệu cần thiết (attempt chỉ khi gọi) | A và toàn bộ S; không nhận client tự chọn candidate/Top 3/seed |
| `GetBatch/ListItems/GetCounts` | GET batch có sẵn, tab/order/page/count theo TMV17, chỉ dữ liệu được phép | B; không tạo/freeze/claim qua GET |
| `VerifyReproduction` | Read-only tái lập và so digest/order nội bộ; không đổi selected rows | A, quyền đặc biệt TMV07/24 |
| `ClaimItem/ReleaseClaim` | Mutation explicit, version/token/lease, ownership và event | C; không claim do mở row hoặc click GET |
| `RecordCallResult` | POST, validate questionnaire/outcome, append attempt/event và schedule retry theo policy; next item theo quyền/queue thực | C; không gọi approve/reject/group update/score/publish |
| `CorrectAttempt/VoidBatch/CreateReplacementGeneration` | Chỉ sau contract riêng; append reference/history, không overwrite frozen manifest hoặc completed attempt | TMV07/15/26, gate tương ứng; không tự bật trong A–D |

Tên trên là contract logic dự kiến, **không phải CLI có thể chạy**, không chứa lệnh migration. Gọi lại sau một ngày/tối đa số lần chưa được chuyển thành 24h/2 attempts mặc định. Retry_due do server tính từ policy đã duyệt, instant lưu nhất quán, hiển thị đúng zone; refresh không dời due. Counts và tỷ lệ đều từ items/attempts/events theo định nghĩa được duyệt, không lấy con số Figma làm dữ liệu runtime hoặc suy “không nghe máy = sai”.

### 7. Kế hoạch A–E theo phụ thuộc

Mọi đường dẫn “mới” dưới đây chỉ là **dự kiến**, không file source nào được tạo ở P09B. Mỗi task triển khai tương lai cần tracking và phạm vi ủy quyền riêng. Thứ tự: phê duyệt S/schema → A → B → C → D; E là nhánh riêng sau phê duyệt tác động. B có thể review read contract song song trên giấy, nhưng không ship counts giả khi TMV17 chưa rõ. Có thể chia nhỏ A thành review schema rồi services; C thành claim rồi record/retry để review dễ hơn.

#### A. Schema và services tạo/freeze batch

- **Files dự kiến:** một migration mới `database/migrations/<timestamp>_create_call_audit_tables.php` cho bốn bảng; `app/Models/CallAuditBatch.php`, `CallAuditItem.php`, `CallAuditAttempt.php`, `CallAuditEvent.php`; mới `app/Services/CallAudit/CohortBuilder.php`, `SamplingAlgorithm.php`, `SourceFingerprint.php`; `app/Actions/CallAudit/CreateAndFreezeBatch.php`; `app/Policies/CallAuditBatchPolicy.php`; mới `tests/Feature/CallAuditSamplingTest.php`, `CallAuditFreezeTest.php`. Các tên rút gọn cùng nhóm nằm trong thư mục vừa nêu. Đọc/reuse contract Leaderboard/Demo/AwardResults, không đổi business source đó để đủ mẫu.
- **Database changes:** chỉ bốn bảng/index/FK theo schema đã được duyệt; không đổi submissions/awards/award_winners/scoring. Chốt FK deletion/retention, ciphertext/key version, max manifest và idempotency trước migration; draft schema không phải quyền chạy migrate.
- **Quyền/validation:** S đã chốt, người tạo/freeze/reproduce và scope hợp lệ; active Admin được cấp ability rõ ràng; bounds/cutoff/baseline match, source unit/phone resolution/demo guard; shortage chưa duyệt phải dừng, không chọn bù. Không cho client seed/reroll.
- **Tests dự kiến:** deterministic vectors/canonical encoding; cùng seed+manifest cùng selection; khác purpose/stratum không lẫn; không duplicate; Dealer snapshot khi Sales transfer; approved/pending/rejected/QR/demo guard; phone missing/duplicate/alias; empty/shortage theo policy; cutoff boundaries và late approval; baseline mismatch; hai creator cùng business key, request replay/payload conflict/timeout, rollback không batch dở; source snapshot consistency và seed/PII không lộ. Dùng fixture test, không runtime sample data.
- **Nghiệm thu:** một generation hợp lệ tạo đúng một manifest frozen và selected items/count/order; tái lập không cần DB nguồn hiện tại; refresh không mutation; giữ bốn thực thể trừ khi có review mở rộng rõ lý do; không thay điểm/verification/approval/Award snapshots. A chưa được bắt đầu migration khi checklist cuối chưa đạt.

#### B. API đọc danh sách, counts và quyền Admin

- **Files dự kiến:** mới `app/Http/Controllers/Admin/CallAuditController.php`, `app/Http/Requests/Admin/ListCallAuditItemsRequest.php`, `app/Services/CallAudit/BatchReadModel.php`; routes Admin mới trong `routes/web.php` chỉ khi được giao task; bổ sung read abilities ở policy A; mới `tests/Feature/AdminCallAuditReadTest.php`.
- **Database changes:** không thêm bảng; chỉ cân nhắc index đã review trên batch/queue/due/selection order khi có nhu cầu đo thực tế, không query encrypted phone bằng plaintext index.
- **Quyền/validation:** auth/session/active Admin và policy từng batch/item, không IDOR qua ID hợp lệ của scope khác; tab/purpose/batch/page allowlist. GET không generate/claim; masking/PII projection và no-store. TMV17 phải chốt trước ship counts, TMV09/18 trước hiển thị trường/metric tương ứng.
- **Tests dự kiến:** guest/Sales/Dealer/Manager/blocked Admin bị từ chối theo convention; Admin allowed/forbidden scope; masked list/no raw manifest/seed/PII; empty batch chưa tạo không tự sinh; tab/count/order/page nhất quán theo outcome/lease/retry đã duyệt; cross-purpose không cộng gộp; refresh DB unchanged.
- **Nghiệm thu:** API chỉ đọc dữ liệu batch thật; count là projection có định nghĩa rõ, không hard-code 60/36/6/18 hoặc tỷ lệ; không suy “chưa có dữ liệu = đã đạt”. Quyền được kiểm tra ở server cho từng đường truy cập.

#### C. Ghi lượt gọi, kết quả và lịch gọi lại

- **Files dự kiến:** mới `app/Actions/CallAudit/ClaimItem.php`, `RecordCallResult.php`, `app/Services/CallAudit/RetryPolicy.php`; request/controller mutation riêng trong `app/Http/Requests/Admin/` và `app/Http/Controllers/Admin/`; claim/record routes và policy abilities được review; mới `tests/Feature/CallAuditAttemptTest.php`, `CallAuditConcurrencyTest.php`, `CallAuditRetryTest.php`.
- **Database changes:** dùng attempts/events/claim/retry/version đã duyệt ở A; không migration vào submissions/verification/score. Nếu quy tắc mới làm thay schema A phải review migration riêng, không ép giá trị nghiệp vụ vào field tùy tiện.
- **Quyền/validation:** TMV09–17, 23–27 và nhánh reminder TMV30 cần thiết đã chốt; request CSRF/owner/token/lease/source+item version/idempotency; mapping ba câu và overall theo contract. Quyền ghi gọi không cấp quyền huỷ xác minh/trừ điểm. No-answer giữ đáp án null theo rule, không tự convert thành Sai. Correction/reopen chưa duyệt vẫn khóa.
- **Tests dự kiến:** ba outcome/mapping/required/null/note; double-click, same key different payload, timeout sau commit; hai caller/expired claim/takeover; cùng giây source đổi; callback retry đúng boundary theo fake clock và calendar đã duyệt; giới hạn hai lần theo định nghĩa TMV, không tính sai; counts sau retry/correction; next item hợp lệ không lấy item người khác đang claim. Assert moderation/group/điểm/snapshot không đổi và logs không chứa PII.
- **Nghiệm thu:** một lần ghi hợp lệ → một attempt + event và queue transition atomically; next/response đúng dữ liệu đã commit; không toast success trước response hoặc từ tel click; không cron/reminder/kênh thông báo tự phát sinh. Chưa có rule gọi lại thì phần ghi no-answer/scheduler vẫn BLOCKED.

#### D. C08 theo Figma, đọc/ghi trên backend A–C

- **Files dự kiến:** mới `resources/views/admin/call-audits/index.blade.php`, `resources/css/admin-call-audits.css`, JS tương tác tương ứng nếu cần; nối asset trong `vite.config.js`, navigation/sidebar hiện hữu đúng entry C08; controller/read model B–C; mới `tests/Feature/AdminCallAuditUiTest.php` và kiểm tra browser theo harness đã duyệt. Không chỉnh các màn khác ngoài tích hợp navigation cần thiết.
- **Database changes:** không có; không seed để đủ 60 hoặc mô phỏng Dealer runtime.
- **Quyền/validation:** chỉ hiển thị dữ liệu theo policy B; full contact theo TMV24, ba tab và queue selection dựa state thật; form C kiểm tra server. Đọc đúng C08 `165:832`, variants `173:1809`, toast `173:2097` khi triển khai; bổ sung thiết kế thiếu trước interaction liên quan, không tự tạo modal/filter/badge/button/wording.
- **Tests dự kiến:** HTTP/DOM dữ liệu/count/masking, tab/pagination/selection và old source; browser Desktop 1440 đối chiếu ảnh Figma cho ba tab, claim, record, retry, errors/last item theo thiết kế được bổ sung. Build và tests chọn lọc chỉ trong task D tương lai; không dùng screenshot fixture như bằng chứng đã gọi/lưu thật.
- **Nghiệm thu:** layout/spacing/icon/nội dung đúng thiết kế, số liệu từ batch thật; toast Đúng chỉ sau commit và next hợp lệ, trạng thái last item phải có design. Không activate Sai với lời hứa huỷ/trừ điểm chưa có E. Nếu partial shortage được duyệt nhưng Figma chỉ có 60 cố định, phải chốt cách thể hiện trước nghiệm thu, không tự thêm thông báo/wording. C07 publish vẫn disabled.

#### E. Sai lệch, điểm và kết nối C07/công bố — task riêng, chưa triển khai

- **Files dự kiến:** sau quyết định TMV mới thiết kế actions trong `app/Actions/CallAudit/` cho adjudication/reversal, score eligibility/adjustment service thống nhất nếu được chọn; call-gate/publication action riêng và tích hợp `app/Services/AwardResults.php`, `app/Http/Controllers/Admin/AwardController.php`, `app/Policies/AwardPolicy.php` khi được giao scope. Tests mới decision/penalty/publication, chọn lọc `AwardWorkflowTest`, `LeaderboardServiceTest`, `CompetitionAwardsTest` và projections bị ảnh hưởng. Đây không phải danh sách file sẽ sửa trong A–D.
- **Database changes:** chưa chọn. TMV phải chọn thu hồi eligibility/moderation hay adjustment/exclusion ledger, finding key, appeal/reversal và ranking revision/công bố. Có thể cần entity ngoài bốn bảng call audit; không giả vờ call events là ledger hoặc tạo cột âm điểm tùy ý. Migration E phải review riêng.
- **Quyền/validation:** mọi nhánh E trong register được duyệt, có confirmation đúng Figma bổ sung; tách caller/adjudicator/publisher và scope. Ngưỡng [x]%, > hay ≥, mẫu số/min sample, quyền thu hồi điểm, thời điểm hiệu lực không có default. Không mở Manager qua task audit.
- **Tests dự kiến:** dry-run tác động; cùng finding ở C08/C07/nhiều attempts không phạt hai lần; cancel không mutation; decision/effect/audit atomic; reversal append-only; Dealer/Sales/weekly/monthly/all-time và mọi projection nhất quán; thay Dealer từ ranking được duyệt, sample wave thay thế, insufficient/no-answer; stale baseline/late approval/save–publish race; publish idempotent; snapshot đã published và public Dealer/private Sales giữ nguyên, amendment riêng nếu được duyệt.
- **Nghiệm thu:** server gate kiểm đủ purpose/revision/cutoff/bằng chứng, không dựa CSS hoặc Đã xong; không âm thầm rerank/live-recompute lịch sử. Chỉ mở Công bố kỳ sau workflow/backend/design được duyệt và tests chọn lọc pass. Nếu TMV chưa duyệt E, A–D không được tự tạo effect điểm/thu hồi/công bố.

### 8. Checklist trình TMV trước khi thực hiện migration

Các ô sau **chưa được đánh dấu đạt**; xác nhận của người dùng về bản dự thảo không thay chữ ký/thẩm quyền TMV.

- [ ] TMV phê duyệt UP01–UP05 bằng reference/người duyệt/ngày/policy version và effective period; hai purpose có scope/cadence/generation rõ ràng.
- [ ] Chốt đơn vị mẫu, QR/Sales/group/activity/demo và Dealer attribution theo submission; duplicate phone/alias và hồ sơ thiếu/không gọi được có rule, không chọn đại diện ngầm.
- [ ] Chốt submitted_at/reviewed cutoff/grace, calendar/zone/bounds end-exclusive, effective period/UTC cũ/weekly; xác nhận không tự dùng VN 00:00.
- [ ] Chốt underfill/empty/Top 3 thiếu Dealer, overlap C08/C07 và cross-wave dedupe; không mặc định min(N,k), cấm overlap hoặc thay Dealer.
- [ ] Chốt mốc và trạng thái kỳ để freeze Top 3, fingerprint/baseline/source drift/void/revision; không thay published snapshots hoặc tự derive rank 4.
- [ ] Duyệt bốn thực thể, FK/unique non-null business key, idempotency/version/claim history; review rõ các phần TMV13/15/26 ảnh hưởng schema. Nếu chưa chốt, tách migration phần chưa ổn thay vì coi placeholder là contract.
- [ ] Duyệt manifest format/version, HMAC-sort/seed lifecycle/reproduction permission và key management; có thiết kế snapshot-read/concurrency/atomic commit cùng kế hoạch đo dung lượng.
- [ ] Duyệt trường PII tối thiểu, encryption/masking/full contact/retention/backup/private access; xác nhận quyền Admin read/generate/caller và không tự mở quyền Manager/điểm.
- [ ] Ghi rõ phần C/E còn chờ duyệt và không đưa semantics chưa chốt vào constraint không thể dùng được; không có dependency E ngầm trong A như auto-revoke hoặc auto-publish.
- [ ] Reviewer duyệt migration cụ thể và kế hoạch rollback an toàn không mất history; có task triển khai riêng, môi trường test cách ly `toyota_testing` và guard được kiểm chứng **trước** mọi test ghi dữ liệu.
- [ ] Duyệt harness concurrency nhiều connection/schema test đã chuẩn bị; không mặc định cho phép DatabaseMigrations, migrate/seed/reset/full regression. Quyền thực hiện migration/test phải nằm trong task tương lai, không suy ra từ P09B.

### 9. Cập nhật 2026-11-11 — đặc tả tháng hiện hành để trình TMV

#### 9.1. Decision register hiện hành

Nguồn xác nhận là **yêu cầu cập nhật P09B của người dùng trong phiên làm việc này**, không phải biên bản TMV. Tất cả UC bên dưới có trạng thái **USER_CONFIRMED / PENDING_TMV**; chưa có người duyệt TMV, reference phê duyệt, ngày duyệt hoặc effective period từ TMV. TECH_DRAFT vẫn là phương án kỹ thuật cần review, không phải quyết định người dùng đã xác nhận.

| ID | Quy tắc được người dùng xác nhận | Liên hệ TMV/P09B cũ | Trạng thái |
|---|---|---|---|
| UC01 | C08 hằng tháng, tối đa 60 CustomerSubmission approved thuộc tháng đó | TMV01; thay UP01 về cadence và số lượng cố định | USER_CONFIRMED / PENDING_TMV |
| UC02 | C07 batch riêng, tối đa 5 hồ sơ approved mỗi Dealer Top 3 của cùng tháng | TMV01/04; cập nhật UP02/05 | USER_CONFIRMED / PENDING_TMV |
| UC03 | Phân kỳ submitted_at, dùng bounds tháng hiện hành của LeaderboardService, không đổi timezone/calendar | TMV03/08; thay lựa chọn kỳ/clock còn mở phía người dùng | USER_CONFIRMED / PENDING_TMV |
| UC04 | Chỉ approved, có SĐT chuẩn hóa hợp lệ, loại demo theo DemoSubmissionScope hiện hành | TMV02/06/24; cập nhật UP03; chi tiết validation/source mismatch tại 9.3 | USER_CONFIRMED / PENDING_TMV |
| UC05 | Thiếu 60/5 thì lấy số hợp lệ thực tế, lưu requested_count và actual_count riêng | TMV05; thay phần shortage còn mở, không bù hồ sơ/Dealer | USER_CONFIRMED / PENDING_TMV |
| UC06 | Mỗi CustomerSubmission là một unit; kiểm duplicate identity/phone alias trong cohort, không tự chọn đại diện khi mâu thuẫn | TMV02/06; thay lựa chọn gom người/phone ở schema cũ | USER_CONFIRMED / PENDING_TMV |
| UC07 | Không trùng trong batch; hai purpose cùng tháng được trùng submission, không tự reuse attempt/kết quả | TMV06/27; thay lựa chọn overlap còn mở | USER_CONFIRMED / PENDING_TMV |
| UC08 | Mọi Admin active được tạo/chốt/xử lý batch, qua middleware/policy và kiểm tra server | TMV07/13/23; không mở quyền correction/penalty/publication theo suy diễn | USER_CONFIRMED / PENDING_TMV |
| UC09 | Batch lưu created_by, frozen_by; mỗi attempt/event lưu actor_id; audit append-only, updated_by không thay history | TMV07/15/24/26; bổ sung field rõ ràng so với “creator/actor” bản cũ | USER_CONFIRMED / PENDING_TMV |
| UC10 | Danh sách frozen bất biến khi reload/filter/source thay đổi | TMV03/26; xác nhận frozen membership, không chốt thay rule xử lý stale | USER_CONFIRMED / PENDING_TMV |

Phương án rút đều C08 không quota và rút riêng từng Dealer C07 của UP04/05 vẫn được giữ trong dự thảo thuật toán ở mục 4; không thêm trọng số/quota/điều kiện activity/group mới. Không nâng các lựa chọn kỹ thuật hoặc quy tắc chưa được nhắc lại thành approval TMV. UP01–UP05 và bảng TMV tại mục 1 là lịch sử; trạng thái phần đã được xác nhận nay đọc theo UC tương ứng.

#### 9.2. Cohort theo tháng, số lượng và underfill

- **Period:** server lấy bounds tháng qua `LeaderboardService::bounds(LeaderboardPeriod::Month, at)`; cùng tháng nghĩa là cùng cặp bounds canonical `[period_start, period_end)`. C08 có period_type monthly; C07 tham chiếu Award monthly đúng bounds đó. Không dùng ngày gọi/ngày duyệt thay submitted_at, không kéo hồ sơ sang tháng khác để bù. Weekly của hệ thống vẫn giữ nguyên và không tự tạo batch award_top3 tuần trong phạm vi này.
- **Cohort gốc:** CustomerSubmission có submitted_at trong bounds tháng, `status = approved` ở snapshot đọc khi freeze, phone đủ điều kiện, demo scope đã kiểm tra và Dealer attribution từ submission.dealer_id. Đơn vị luôn là submission ID, không gom phone thành một khách hoặc lấy đại diện. QR/Sales không bị loại riêng chỉ vì nguồn; không tự thêm group-member, active Dealer/Sales hay filter user.role của leaderboard vào điều kiện mẫu. Integrity nguồn/quan hệ không hợp lệ là lỗi dữ liệu cần resolution, không gán Dealer khác. Điều kiện eligibility cụ thể cần được TMV phê duyệt trước A.
- **C08:** với N ứng viên hợp lệ, không mâu thuẫn trong cohort tháng, `requested_count = 60`, `actual_count = min(60, N)`. Chọn actual_count không hoàn lại, rút đều trong pool theo draft mục 4; khi N < 60 lấy toàn bộ tập hợp lệ, vẫn giữ selection order tái lập. Không lấy lặp để đạt 60.
- **C07:** freeze danh sách Dealer Top 3 đúng tháng/baseline đã chọn; mỗi Dealer d có `requested_count_d = 5`, `actual_count_d = min(5, N_d)` trong cohort của submission.dealer_id = d. Lưu đầy đủ requested/actual theo stratum. Đề xuất roll-up kỹ thuật: batch `requested_count = 5 × m`, `actual_count = tổng actual_count_d`, trong đó m là số Dealer thực sự có trong baseline, tối đa 3; lưu target cap 15 và số Dealer baseline riêng để không giấu trường hợp baseline thiếu Dealer. Công thức roll-up khi baseline dưới 3 là TECH_DRAFT cần TMV review, không mặc định đã có đủ Top 3 hoặc tự thêm Dealer hạng sau. Với đủ ba Dealer requested_count là 15, dù một Dealer chỉ có 2 hồ sơ hợp lệ.
- **Underfill:** actual_count nhỏ hơn requested_count là batch nhỏ hợp lệ theo UC05, không phải sampling failure và không được đổi seed/bù kỳ/bù Dealer/tự reroll. Lưu eligible_count, requested_count, actual_count, per-Dealer counts và metadata loại thiếu/invalid phone an toàn, không log PII. Không dùng underfill để bỏ qua conflict identity: N chỉ xác định sau bước kiểm tra/resolution hợp lệ.
- **Zero:** công thức cho actual_count = 0 khi không có hồ sơ đủ điều kiện; không có row để claim/call, không tạo attempt/toast “đã gọi”. Đề xuất lưu freeze manifest rỗng cùng counts/event để tránh refresh liên tục thử mẫu mới; trạng thái lifecycle/wording empty cần TMV/design duyệt. Zero hoặc underfill không đồng nghĩa đã audit Đạt, tỷ lệ sai 0%, hoàn tất yêu cầu C07 hay đủ quyền công bố. Baseline không có Dealer cũng cần quyết định lifecycle riêng, không gọi là đủ Top 3.
- **Read/UI:** tổng mẫu đang xử lý dùng actual_count của batch; target 60/5 giữ riêng, không đếm các slot thiếu như khách chưa gọi hoặc không nghe máy. Công thức tab/progress/tỷ lệ chờ TMV17/18; mẫu số 0 phải thể hiện trạng thái không có dữ liệu theo thiết kế được duyệt. Nếu Figma chưa có underfill/empty, task D chờ thiết kế phần đó, không thêm câu chữ/nút/section. Thiếu mẫu không tự đạt gate công bố; TMV16/28 vẫn mở.

#### 9.3. Rà soát phone/duplicate và demo theo source

Đã đọc lại `app/Services/RegistrationPhone.php`, `SubmissionIntake.php`, `AdminGroupLookup.php`, `SubmissionIntegrity.php`, `ApprovedSubmissionFinder.php`, `app/Actions/ApproveCustomerSubmission.php`; các requests `app/Http/Requests/Sales/SubmissionRequest.php`, `Admin/UpdateSubmissionRequest.php`, `Admin/GroupLookupRequest.php`, `RegistrationLookupRequest.php`, `DealerRegistrationRequest.php`. Đây là kiểm tra source tĩnh, không xác nhận dữ liệu thực đã sạch.

| Vấn đề | Bằng chứng source | Áp dụng dự thảo / còn cần review |
|---|---|---|
| Normalize | RegistrationPhone bỏ whitespace, dấu chấm/ngoặc/gạch nối; đổi +84/0084 sang 0 | Dùng helper hiện có cho cả phone và phone_normalized; normalize không kiểm độ dài, đầu số, ownership, consent hoặc khả năng nghe máy |
| Các validator khác nhau | QR registration, public lookup và C03 chấp nhận `^0[1-9][0-9]{8,9}$/D` sau normalize; Admin edit dùng `^0[0-9]{9,10}$/D`; Sales customer_phone chỉ nullable/string/max:30 | Không thể suy “approved = phone hợp lệ”. **TECH_DRAFT:** dùng validator chặt đang có ở QR/C03 cho phone sampling, version hóa rule và trình TMV duyệt (10–11 chữ số, bắt đầu 0, chữ số thứ hai khác 0). Không lấy regex user-account register làm chuẩn khách. Khác biệt legacy/11 số/đầu số không được âm thầm sửa ở source |
| Alias trong một hồ sơ | C03 normalize cả raw/normalized; nhiều alias không rỗng khác nhau coi ambiguous, không chọn một alias | Đề xuất cần đúng một canonical value không mâu thuẫn từ các alias không rỗng, value đó qua validator; normalized thiếu nhưng raw hợp lệ có thể canonicalize trong manifest, không cập nhật hồ sơ. Alias còn lại invalid hoặc khác dù một số gọi được vẫn là conflict, không tự bỏ alias để chọn số còn lại. Rule cụ thể còn TECH_DRAFT / PENDING_TMV |
| Duplicate phone/plate | SubmissionIntake so alias chuẩn hóa trên pending/approved; plate bỏ dấu phân cách/upcase; mutex tránh race trên đường ghi được guard | Kiểm tập cohort approved tháng theo canonical alias và plate identity hiện hữu, phát hiện cả các nhóm trùng. Không gọi write/intake assertion để “sửa” cohort hoặc coi service đó là read filter hoàn chỉnh. Approved/DB guard không đảm bảo legacy không trùng |
| Facebook identity | Approve dùng ApprovedSubmissionFinder với FacebookUrlNormalizer; unique approved identity là guard nghiệp vụ riêng, không chứng minh phone unique | Kiểm consistency/trùng identity bằng canonicalizer/identity hiện có, không chỉ so raw URL hoặc suy hai submission chắc chắn khác người. Thiếu/mâu thuẫn identity/nguồn cần ghi lỗi theo policy, không xóa hồ sơ, huỷ approved hoặc chọn first/newest |
| Phạm vi so trùng | C03 tìm cả rejected; intake xét pending/approved; sampling hiện lấy cohort approved trong tháng | UC06 yêu cầu kiểm trong cohort, không copy phạm vi C03 thành lệnh loại cả rejected/ngoài tháng. Tác động mâu thuẫn ngoài cohort nếu cần là quyết định riêng; không âm thầm mở rộng/quyết định occupancy |
| Demo scope | DemoSubmissionScope mặc định loại owned demo IDs; include_in_metrics có thể trả cả demo nếu guard cho phép; retired gọi assertRetired rồi return query | UC04 yêu cầu loại demo theo scope hiện hành: gọi đúng service/guard, ghi branch/policy/exclusion digest, không đổi config. Không được tuyên bố mọi nhánh luôn loại demo. Nếu include branch đang cho demo tham gia, hoặc retirement guard lỗi, đề xuất chặn freeze để review mâu thuẫn UC04; không override scope, thêm demo để đủ số hoặc nuốt lỗi thành pool rỗng. Xác minh runtime nằm ở task A được ủy quyền sau, không ở P09B |

**Xử lý conflict — TECH_DRAFT:** thu thập nhóm ID/loại mâu thuẫn dưới quyền Admin, không public phone/plate/URL; chặn freeze cohort bị mâu thuẫn tới khi nghiệp vụ resolution được duyệt, thay vì tự giữ một đại diện hoặc loại ngầm toàn nhóm để làm nhỏ N. Rule này cần TMV duyệt trước A. Underfill do thiếu/invalid phone theo policy đã duyệt khác với conflict chưa giải quyết. Không cần đổi unit, thêm bảng gom người hoặc unique phone toàn hệ thống cho schema sampling.

#### 9.4. Approval muộn, source drift và baseline

**Đề xuất xử lý approval muộn — TECH_DRAFT / PENDING_TMV:** eligibility_as_of là snapshot đọc coherent khi tạo/freeze batch. Hồ sơ submitted trong tháng nhưng còn pending tại snapshot sẽ không có trong manifest. Được approved sau freeze không tự bổ sung C08/C07, không tăng actual_count và không chiếm slot thiếu; cũng không chuyển sang mẫu tháng sau vì submitted_at vẫn thuộc tháng cũ. Giữ contribution điểm hiện hành của nó theo LeaderboardService: sampling freeze không đóng băng hoặc loại contribution khỏi điểm.

Nếu cần audit thêm hồ sơ duyệt muộn, phải có quyết định explicit về generation/wave bổ sung của **cùng tháng**, người thực hiện/lý do/reference và khóa chống trùng; không tự cho mọi Admin reroll vì có hồ sơ mới hoặc còn thiếu mẫu. Approval cutoff/grace và thời điểm tạo batch trong/đầu/cuối tháng chưa được người dùng ấn định, phải trình TMV; không tự chọn 00:00 hoặc một deadline cố định. Khi A dùng transaction snapshot, định nghĩa “muộn” theo visibility của snapshot, không chỉ so timestamp giây với frozen_at.

Hồ sơ đã chọn đổi phone/identity/status/Dealer sau freeze giữ nguyên item, snapshot và selected IDs; kiểm tra source fingerprint để đánh dấu stale/không dùng số liên hệ mâu thuẫn. Resolution/revalidation/correction là workflow riêng, không auto-refresh PII rồi coi là mẫu cũ nguyên vẹn. UC10 xác nhận bất biến danh sách, chưa cho phép gọi số cũ/ghi đè kết quả hoặc tự thay hồ sơ stale.

Approval muộn cũng có thể đổi điểm/Top 3 draft mà không thêm vào batch. So baseline fingerprint AwardResults hiện hữu trước gate C07, không giữ trạng thái Đạt trên baseline đã khác. Cách re-baseline/thay Dealer/mẫu bổ sung thuộc TMV22/28 và E. Không đổi approval, SubmissionVerification, cách tính điểm, Award/AwardWinner đã published hoặc public/internal projections để làm khớp mẫu. Preview live vẫn live; published snapshots vẫn bất biến.

#### 9.5. Schema delta, actor history và unique business key — TECH_DRAFT

Giữ bốn thực thể tại mục 5; mục này thay những lựa chọn unit/scope/actor còn mở trong bảng cũ. Không thêm migration hay entity thực tế.

- Batch: `period_type = monthly`; `period_start`, `period_end` canonical, calendar/zone snapshot; `monthly_scope_key` không null do server suy từ tháng/bounds hợp lệ (ví dụ identity tháng YYYY-MM dưới calendar hiện hành), purpose, generation; thêm rõ **created_by**, **frozen_by**, created_at/frozen_at, eligibility_as_of, requested_count/actual_count, eligible_count và stratum counts. Tạo/freeze atomically thì hai actor có thể cùng Admin nhưng vẫn lưu hai cột và freeze event. Không lấy client actor hoặc updated_by làm bằng chứng người chốt.
- Item: unit_key là submission ID, submission_id bắt buộc; unique(batch_id, submission_id), unique(batch_id, selection_order). Không còn phương án source IDs nhóm người/phone hoặc FK representative ở bản hiện hành. Dealer snapshot từ submission.dealer_id và nguồn fingerprint bất biến. Cùng submission có thể có item khác batch/purpose, không global unique submission/phone.
- Attempt/event: **actor_id** bắt buộc là người thực hiện đã authorize, do server lấy từ session; attempts giữ item/outcome/questionnaire/source version/idempotency, events giữ chi tiết hành động, before/after version, reason/reference và timestamp. Quyền hiện tại của actor có thể đổi nhưng không viết lại attribution lịch sử. Nếu có system event sau này cần contract actor_type/system identity riêng; không giả mạo Admin hoặc bỏ actor_id âm thầm.
- History append-only: completed attempts/events không overwrite/delete; correction tương lai tạo record/event có reference và quyền riêng. updated_by nếu dùng ở projection chỉ là metadata gần nhất, không thay audit. Ngay cả chính người gọi cũng chưa được tự sửa kết quả đã chốt khi chưa có rule correction.
- **Business uniqueness đề xuất:** unique `(purpose, monthly_scope_key, generation)`; bounds lưu không null và server validate map đúng scope/month của LeaderboardService. Award_top3 thêm invariant FK Award monthly đúng bounds, một Award reference hợp lệ cho scope; general_audit không tự tham chiếu Award. Không dùng unique nullable award_id hoặc chỉ request key. Không đưa created_by, seed, fingerprint, Dealer, filter, policy version hay algorithm version vào key để né duplicate cùng kỳ/generation.
- Generation do server kiểm soát. Hai Admin cùng yêu cầu tháng/purpose/generation, kể cả hai request key khác nhau, chỉ được một batch commit; request hợp lệ còn lại trả batch cũ hoặc conflict theo read permission. Khác payload với cùng idempotency key phải conflict. Chưa có workflow generation mới thì không nhận client tự tăng generation; void không xóa bản cũ/giải phóng key. Cùng tháng hai purpose là hai key riêng, được overlap; đổi Top 3 fingerprint không tự cho tạo thêm batch cùng generation. Calendar không được tự thay để tạo “tháng mới” khác key.

Thuật toán/manifest/seed/version ở mục 4 giữ nguyên TECH_DRAFT, nhưng unit nay là submission, C08 k = min(60,N), C07 k_d = min(5,N_d), không còn chặn vì **chỉ thiếu số lượng**. Vẫn chặn khi thiếu approval cần thiết, demo guard/policy conflict, duplicate chưa resolution, bounds/baseline/permission sai. Manifest đầy đủ được cố định tại tạo/freeze, GET chỉ đọc; source thay đổi không regenerate hoặc thay selected rows.

#### 9.6. Ma trận quyền hiện hành và các quyết định vẫn mở

| Năng lực | Trạng thái phía người dùng / giới hạn server-side |
|---|---|
| Đọc/tạo/chốt/xử lý batch, nhận việc và ghi attempt mới | UC08: mọi active Admin, vẫn auth/auth.session, role:admin, account.active, CSRF/throttle và policy. Không giới hạn chỉ creator; claim/version/lease bảo vệ thao tác đồng thời, không phải cấp thêm quyền nghiệp vụ |
| Sửa/reopen/void kết quả đã chốt hoặc batch, tạo generation mới | Chưa được suy ra từ “xử lý batch”. Chờ TMV07/15/26: ai, lý do, correction/revision, takeover và effect history; chưa có contract thì không cung cấp mutation đó |
| Xem full phone/note/seed, tái lập manifest | Không tự cấp do có quyền tạo; cần privacy/retention và disclosure policy TMV07/24. Tạo batch có thể dùng seed nội bộ mà không trả seed cho Admin |
| Thu hồi xác minh/approval, trừ điểm, loại Dealer, đôn hạng | Chưa có contract/quyền mới; thuộc E, không mở vì actor là Admin hoặc lưu Sai |
| Công bố kỳ | Không mở từ UC08. AwardPolicy::publish hiện trả active Admin nhưng chưa có workflow/gate publication được duyệt; ability hiện hữu không thay backend kiểm tra và approval. C07 publish vẫn disabled; không sửa policy trong task tài liệu |
| Manager/Sales/Dealer hoặc Admin inactive | Không mở quyền sampling; active check phải được kiểm lại khi xử lý request, không chỉ lúc tạo batch hoặc cấp claim |

Đối chiếu lại toàn bộ register TMV01–TMV30 ở mục 1: **TMV01/02/03/04/05/06/07/08/13/15/23/26/27** có phần đã được UC xác nhận, không còn là “chưa có quyết định phía người dùng” cho những phần đó, nhưng tất cả vẫn PENDING_TMV. Chi tiết còn mở: freeze/cutoff/generation/seed/void; validator/conflict/demo branch; scope disclosure, lease/takeover và correction. **TMV09–12/14/16–18/24–25/28/30** tiếp tục chờ phần script/mapping/clock/count/UX/privacy/gate/notification chưa được xác nhận (giữ các nhánh S/C/E bản cũ, áp dụng UC nếu có giao nhau). **TMV19–22/29** và các nhánh effect/publication của TMV23/26/27/28/30 vẫn thuộc E; không tự đặt [x]%, thu hồi điểm, retry 24h hoặc quyền công bố. Sampling monthly không thay calendar hệ thống; TMV08 nay là duyệt dùng calendar hiện hành, không phải yêu cầu chọn lại VN 00:00.

#### 9.7. Cập nhật kế hoạch A–E và checklist trước A

Mục 7 tiếp tục là danh sách files/task dự kiến, với các delta dưới đây thay các điều kiện cũ tương ứng. Các test bên dưới là **test cases tương lai**, không chạy ở lần cập nhật tài liệu.

| Task | Delta bắt buộc / điều kiện nghiệm thu bổ sung |
|---|---|
| A — schema/freeze | Monthly canonical scope, submission unit, min(60,N)/min(5,N_d), counts riêng; created_by/frozen_by/actor_id; uniqueness theo purpose/tháng/generation. Không chặn chỉ do underfill. Test N=0/1/59/60/61 và N_d=0/1/4/5/6, Dealer transfer, duplicate alias/plate/Facebook, demo branches, late approval snapshot boundary, two Admin generation race/timeout/replay, hai purpose overlap nhưng item riêng; không sửa điểm/snapshot |
| B — API/counts | Mọi active Admin được read theo policy, không chỉ creator. Counts theo actual items, không còn slot giả 60/15; batch zero/underfill và per-Dealer counts, seed/PII không serialize. Tests blocked Admin sau freeze, GET không mutation, alias conflict không tự ra một customer, cross-purpose không cộng gộp |
| C — attempt/retry | Active Admin có thể xử lý batch người khác tạo nhưng phải claim/owner/version/lease; attempt và event cùng actor_id/transaction. Tests quyền ghi khác quyền correction/publish/penalty, không reuse attempt khi overlap, stale data và late approval không thay manifest. Chưa chốt retry/mapping/correction thì interaction tương ứng vẫn BLOCKED |
| D — UI | Target và actual tách biệt; progress/tabs theo actual và contract counts. Empty/partial/last item phải có thiết kế, không hard-code 60 hoặc tự thêm chữ. Toast chỉ sau commit thật; không biến underfill thành Đạt C07 |
| E — effects/publication | Giữ riêng, không đổi scoring/approval/group/published snapshots. Underfill/zero/overlap không tự cho phép công bố hoặc áp penalty; mẫu số/ngưỡng/finding dedupe và gate cần approval riêng |

Checklist mục 8 được giữ là lịch sử; **checklist hiện hành trước A/migration** là:

- [ ] TMV duyệt UC01–UC10, phân phối lấy mẫu và policy version/effective month bằng người duyệt/reference/ngày; không coi USER_CONFIRMED là TMV approval.
- [ ] Review validator chuẩn hóa, alias/duplicate identity resolution, integrity, demo scope branches; mọi điều kiện ứng viên phải tái lập, không tự sửa dữ liệu hoặc chọn representative.
- [ ] Duyệt thời điểm freeze/cutoff/approval muộn và baseline Top 3 cùng tháng; giữ bounds Leaderboard hiện hành, không chọn lại timezone, không auto-reroll.
- [ ] Duyệt counts khi thiếu/zero/Top 3 dưới ba Dealer, roll-up requested/actual và empty lifecycle. Xác nhận partial không tạo gate Đạt/publication; design empty/underfill để riêng task D.
- [ ] Review bốn bảng/constraints, non-null monthly business key/generation/idempotency, actor fields, append-only correction references và private manifest/seed/encryption/retention. Chưa có phép correction/generation mới thì endpoint đó vẫn khóa.
- [ ] Duyệt snapshot isolation/concurrency protocol, lease và test harness nhiều connection; preview/ranking fingerprint và source drift không thay manifest frozen.
- [ ] Xác nhận policy cho mọi active Admin tạo/chốt/ghi gọi; correction, full PII/seed, penalty và publish có gate riêng, không mở Manager.
- [ ] Giao task A/schema review/migration riêng sau TMV approval, kiểm chứng guard `toyota_testing` trước test có ghi; review backup/rollback giữ history. P09B không cho phép tạo migration, chạy migrate/seed/reset, tests hoặc kết nối production.

## Kết quả kiểm thử

Kiểm tra tài liệu bằng PowerShell/regex và Git status **PASS**: đủ 9 mục tracking, 5 đề xuất UP01–UP05, 30 dòng TMV01–TMV30 liên tiếp, 5 task A–E; các liên kết Markdown local tồn tại, không trailing whitespace. So sánh `git status --porcelain=v1 --untracked-files=all` với baseline trước task chỉ thêm đúng file P09B, không đổi trạng thái các file có sẵn.

Không chạy PHPUnit, build, Artisan, migrate, seed, reset, preflight database, runtime HTTP hoặc kết nối production. Các test cases tại A–E hoàn toàn là kế hoạch tương lai, không phải PASS thực tế. Không có nghiệm thu giao diện mới trong task tài liệu này.

Cập nhật 2026-11-11: kết quả PASS phía trên thuộc lần tạo bản đầu. Lần cập nhật này kiểm tra PowerShell/regex **PASS**: đủ 9 mục tracking, 10 dòng UC01–UC10 liên tiếp đều USER_CONFIRMED / PENDING_TMV, giữ 30 dòng TMV lịch sử, các liên kết Markdown local tồn tại và không trailing whitespace. Git status trước/sau không tăng/giảm file vì P09B đã có sẵn trong working tree; công cụ chỉnh sửa chỉ được dùng cho file P09B. Rà soát nội dung đã bao phủ monthly bounds, partial counts, phone/alias conflict, demo branch, late approval, unique purpose/kỳ/generation, actor audit và quyền tách biệt. Không chạy ứng dụng hoặc database; không coi kiểm tra tài liệu là test nghiệp vụ đã PASS.

## Những lỗi còn tồn tại

Triển khai còn BLOCKED bởi phê duyệt UP01–UP05 và các nhánh S; C/E có gate riêng như register. Chưa biết cardinality/collision/phone coverage thực tế, DB isolation/schema runtime, dung lượng manifest hoặc quyền vận hành; không suy ra từ source là dữ liệu sạch/đủ 60/15. Các thiết kế confirmation Sai/empty/error/last item và lời hứa tác động điểm còn thiếu, không được tự dựng. Không có lỗi source nào được sửa hoặc kết quả test ứng dụng nào được xác nhận mới trong P09B.

Cập nhật hiện hành: các phần shortage/submission unit/overlap/monthly/active Admin/audit đã USER_CONFIRMED tại UC01–UC10, **không còn BLOCKED vì thiếu lựa chọn từ người dùng**. Triển khai vẫn BLOCKED bởi TMV approval/schema authorization và các chi tiết kỹ thuật/nghiệp vụ còn mở tại 9.3–9.7. Cardinality/phone/demo runtime chưa xác minh; không có lỗi source được sửa.

## Task hoặc bước tiếp theo

Trình TMV decision register/checklist, ghi approval cụ thể và các câu trả lời còn thiếu vào revision mới giữ lịch sử. Sau khi S và schema được duyệt mới giao task A, rồi B → C → D theo gates; E giữ riêng tới khi đầy đủ contract về sai lệch/điểm/công bố. Không thực hiện migration hoặc thay đổi backend chỉ vì bản dự thảo này đã hoàn tất.

Bước tiếp theo sau cập nhật: trình **mục 9 và checklist 9.7** cho TMV, dùng mười UC làm quyết định người dùng hiện hành; không hỏi lại các lựa chọn đã được xác nhận. Chốt riêng validator/conflict/demo, freeze/cutoff/baseline, privacy và generation/correction còn thiếu, rồi mới chuẩn bị task A được phê duyệt; không chạy migration từ tài liệu này.
