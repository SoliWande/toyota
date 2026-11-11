# P09A — Phân tích và kế hoạch C08 Kiểm tra ngẫu nhiên

## Mục tiêu

Đặc tả ranh giới kỹ thuật, dữ liệu tối thiểu và các quyết định TMV phải chốt trước khi xây C08; bảo vệ điểm, lịch sử duyệt và snapshot đã công bố. **Chỉ phân tích: tên bảng, trạng thái, quyền và giao dịch đề xuất dưới đây chưa phải contract đã được duyệt. Không có code/migration được triển khai.**

## Trạng thái

DONE phạm vi phân tích/lập kế hoạch — 2026-11-11. Việc triển khai C08, xử lý điểm và publication còn chờ các quyết định TMV; không xem hoàn thành tài liệu là phê duyệt nghiệp vụ.

## Hiện trạng trước khi chỉnh sửa

- Đã đọc AGENTS, riêng C08 trong [inventory](admin-ui-inventory.md), P09/P10 trong [implementation plan](admin-ui-implementation-plan.md), [P10A](phase-p10a-admin-awards.md) và source liên quan. Working tree đã có P04/P08/P10A/AdminShell chưa commit; giữ nguyên.
- Đã đọc context và xem ảnh đúng ba node [C08 165:832](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=165-832), [tabs 173:1809](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=173-1809), [toast 173:2097](https://www.figma.com/design/yKmWQI6BYJ6dponEnidlsq/Toyota-Community?node-id=173-2097), session `08e5319a-8a82-41dc-b6ef-c100a9e55654`. Không quét lại C01–C07 hoặc tải/lưu assets.
- Bằng chứng là static frame/context và source, **không** phải toàn bộ prototype reactions hoặc kiểm tra database/runtime. Không suy click, tự lưu, thời gian toast hay quyền chỉ từ style/nodename. Kết quả tests trong P10A là lịch sử, không chạy lại ở P09A.

## Các file source code liên quan

| Nhóm đã đọc | Source theo repository | Ý nghĩa |
|---|---|---|
| Hồ sơ/duyệt | `app/Models/CustomerSubmission.php`, `app/Enums/SubmissionStatus.php`, `app/Actions/ApproveCustomerSubmission.php`, `app/Actions/RejectCustomerSubmission.php`, `app/Services/SubmissionIntegrity.php` | Status, nguồn, attribution, duplicate và điều kiện duyệt |
| Sửa/lịch sử | `app/Actions/UpdateCustomerSubmission.php`, `app/Models/SubmissionEdit.php`, `app/Models/ModerationReview.php`, `app/Services/SubmissionIntake.php` | Version, mutex/row locks, lịch sử riêng và audit che PII |
| Group | `app/Services/SubmissionVerification.php`, migration `database/migrations/2026_11_03_000024_add_submission_verification_workflow.php`, `app/Policies/CustomerSubmissionPolicy.php` | State/audit group có version và bằng chứng gắn profile, không scoring |
| Điểm | `app/Services/LeaderboardService.php`, `app/Services/DemoSubmissionScope.php`, `app/Services/SalesPublicProfile.php`, `app/Services/DealerRegistrationReport.php`, controllers Admin/Sales/Dealer Dashboard | Nhiều projection đọc approved; không có một cột điểm để trừ trực tiếp |
| Kỳ/snapshot | `app/Enums/AwardPeriod.php`, `app/Actions/CreateAwardDraft.php`, `app/Services/AwardResults.php`, `app/Services/PublicAwardResults.php`, `app/Models/Award.php`, `app/Models/AwardWinner.php`, `app/Policies/AwardPolicy.php`, `app/Http/Controllers/Admin/AwardController.php` | Draft sống, fingerprint, weekly/monthly, snapshot/public projection |
| Hạ tầng | `routes/web.php`, `config/app.php`, migrations tạo submissions/awards/award_winners, `app/Services/SubmissionCollisionAudit.php`, `app/Services/DealerCallPhone.php` | Quyền/clock/constraints; hai service có tên audit/call không lưu cuộc gọi C08 |
| Tests chỉ đọc | `tests/Feature/AwardWorkflowTest.php`, `tests/Feature/RegistrationLookupStatesTest.php`, `tests/Feature/SubmissionApprovalRaceTest.php` | Contract và trường hợp cần bảo vệ; race test hiện dùng DatabaseMigrations |

## Những thay đổi đã thực hiện

Chỉ tạo `docs/figma/phase-p09a-admin-call-audit-plan.md`. Không sửa source, routes, migrations, tests, tài liệu lịch sử hoặc database. Không tạo bảng, mẫu kiểm tra, hồ sơ, cuộc gọi hay kỳ công bố thực tế.

## Quyết định kỹ thuật quan trọng

### 1. C08 có gì và chưa chứng minh điều gì

| Vùng thiết kế | Nội dung đã đọc | Contract còn thiếu |
|---|---|---|
| Heading/progress 165:922/916 | “Kiểm tra ngẫu nhiên”; kỳ tháng, “60 khách được chọn ngẫu nhiên từ các đại lý”; “Đã gọi”, mẫu18/60 | 60 cố định hay cấu hình; cohort; thế nào là đã gọi; snapshot nào |
| Toolbar 165:926, tabs173:1809 | “Cần gọi”, “Gọi lại”, “Đã xong”; active variants173:1786/1797/1808, counts mẫu36/6/18 | Tab có rời nhau không, retry chưa tới hạn nằm đâu, filter period/Dealer nào được hỗ trợ. Không thấy select Dealer/search riêng trong frame; không tự thêm |
| Queue165:939 | Tên, Dealer, SĐT che; selected row; badges “Đang gọi”, “Chưa gọi”, “Gọi lại lần 2”, “Sai thông tin”, “Đúng” | Badge từ claim hay kết quả; thứ tự/next/pagination; ai được thấy full phone |
| Tỷ lệ165:980 | “TỈ LỆ SAI THEO ĐẠI LÝ”, đã gọi/tổng mẫu, số sai, tỷ lệ và progress bar | Mẫu số, kết quả được xét, thời gian/phạm vi và ngưỡng |
| Workspace165:1003/1008 | Khách, Dealer, xe/năm/biển, “Đã xác minh [ngày]”; CTA số điện thoại đầy đủ | Ngày duyệt hay ngày xác minh group; dữ liệu lúc freeze hay live; CTA mở tel hay tích hợp nào. Click không chứng minh đã gọi/đã nghe máy |
| Script165:1013 | “KỊCH BẢN GỌI, KHOẢNG 30 GIÂY”; lời chào xin phép hỏi ba câu | Cách xưng hô và điền tên từ dữ liệu thật; không lấy tên mẫu làm runtime |
| Câu hỏi165:1016 | (1) “Anh có đăng ký tham gia cộng đồng không ạ?”; (2) “Anh đang đi Toyota Hilux, biển số 51K-123.45 phải không ạ?”; (3) “Anh đăng ký qua đại lý Toyota An Sương đúng không ạ?”; mỗi câu Đúng/Sai | Tên xe/biển/Dealer là dữ liệu mẫu. Cần rule cho null/chưa trả lời, từ chối trả lời, sai số, sai một phần |
| Kết quả165:1059 | “Đúng thông tin” / “Sai thông tin” / “Không nghe máy”; “Ghi chú (tuỳ chọn)” cao64; “Lưu kết quả”165:1080 | Quan hệ ba đáp án với outcome; required/length/quyền đọc note; validation/sửa kết quả |
| Lưu ý165:1078 | “Sai thông tin: khách bị huỷ xác minh và đại lý bị trừ 1 điểm, sẽ hỏi xác nhận trước. Không nghe máy: tự hẹn gọi lại sau 1 ngày; 2 lần không nghe thì bỏ qua, không tính là sai.” | Đây là intent hiển thị, chưa có action/confirmation/clock/scoring contract để thực thi |
| Toast173:2097 | “Đã lưu: Đúng thông tin · chuyển sang khách tiếp theo” | Chỉ sau commit kết quả đúng. Last item, không còn khách nhận được, lỗi, sai thông tin và no-answer chưa có wording/flow tương ứng |

Layout tham chiếu: frame 1440×1046; content 1128; queue/ratios 420 + workspace 692, gap 16; ba câu hỏi; result 642×225. Thiết kế confirmation khi sai, empty/loading/error/claim conflict, retry chưa tới hạn, hoàn thành batch và Admin Mobile **chưa được tìm thấy trong inventory**; P09A không tìm rộng lại C01–C07 để tự dựng thay thế.

**Mâu thuẫn phải giữ lại để chốt:**

- 36+6+18=60 nhưng 6 retry đã từng được gọi; “Đã gọi18/60” có thể là số hoàn tất, không phải số khách từng có attempt. Không chốt công thức từ phép cộng này.
- Ở active “Cần gọi”, queue minh họa vẫn có cả Đúng/Sai đã xử lý; chưa đủ chứng minh predicate thực của tab.
- Câu 1/2 đang Đúng, câu3 chưa chọn nhưng overall Đúng thông tin được chọn. Không dùng mock này để mặc định câu trả lời, bỏ required hoặc quyết định cho phép save.
- Mẫu Dealer có2 sai/5 đã gọi=40%, nhưng chưa rõ5 có bao gồm no-answer. Thanh đỏ152/300 không phải40%; bar0 sai vẫn có bề rộng6. Không lấy pixel width thành công thức hoặc tỷ lệ tối thiểu.
- Footer C08 nói trừ 1 điểm; C07 đã được inventory ghi mẫu thu hồi 2 điểm. Không suy ra trừ theo attempt, theo câu sai hay tự trừ toàn Dealer. Các con số này không thay quyết định TMV.

### 2. Backend hiện có: ranh giới không được nhập nhằng

| Thành phần thực tế | Kết luận từ source | Hệ quả cho C08 |
|---|---|---|
| CustomerSubmission | Chỉ pending/approved/rejected; Dealer snapshot khi tạo, Sales attribution có guard; pending-only reassignment qua action có version; lịch sử cũ không chuyển theo Sales | Lấy Dealer từ submission/ảnh chụp mẫu, không từ Dealer hiện tại của Sales; không thêm outcome vào enum moderation |
| ApproveCustomerSubmission | Admin review, intake mutex → submission row lock, pending-only, duplicate phone/plate/Facebook, QR cần verified profile, ghi ModerationReview | Không gọi approve để ghi Đúng hoặc tăng thêm 1 điểm |
| RejectCustomerSubmission | Admin review, row lock, chỉ pending→rejected và audit | Không dùng reject để thu hồi approved. Bulk SQL đổi approved→rejected là bỏ qua contract, không là giải pháp C08 |
| SubmissionVerification | Group/blocker/supplement, version và profile_key; audit độc lập; supplement online chỉ pending | group unknown/member không đổi moderation/điểm. “Huỷ xác minh” không được tự hiểu là xóa group evidence |
| Điểm/rank | COUNT approved theo submitted_at với [start,end); Dealer theo submission.dealer_id; Sales theo sales_id và Dealer hiện tại của user cho tên/nhóm Sales; rank ROW_NUMBER score DESC, achieved_at ASC, id ASC; DemoSubmissionScope | Không có ledger trừ điểm. Không lấy reviewed_at làm kỳ, không viết score vào submission, không nhân penalty cho tuần/tháng/toàn kỳ. Query hiện không lọc Dealer is_active |
| Audit hiện tại | ModerationReview/SubmissionEdit có guard append-only; edit audit mask; verification states/audits có actor/version/profile evidence | Không nhét notes/attempt vào admin_note, updated_at hoặc dùng audit group làm chứng cứ đã gọi |
| AwardPeriod/AwardResults | Weekly/tháng; CreateAwardDraft firstOrCreate canonical; preview top3 Sales+Dealer sống, fingerprint tính từ kỳ và cả hai nhóm; chưa freeze hoặc publish | Kỳ tồn tại/đã qua period_end không có nghĩa đã chốt; hash preview đơn thuần chưa là publication gate |
| Published | Award/AwardWinner model chặn sửa/xóa sau published_at; unique kỳ và rank/type; PublicAwardResults chỉ published Dealer projection | Không sửa snapshot, tên/điểm/hạng cũ hoặc public Sales/PII. Guard Eloquent không thay quyền DB; không dùng bulk SQL vượt guard |
| P10A/routes | C07 read-only, call/publish disabled; Admin show giữ Sales nội bộ; không route/action C08 hoặc publish thực | Phải có module/quyền/commands mới được duyệt. AwardPolicy::publish tồn tại không cấp sẵn quyền khấu trừ hay chứng minh có workflow |

Tìm source `app`, routes và migrations chưa thấy sampling batch, claim, call attempt, lịch retry, audit adjudication hoặc deduction ledger. `SubmissionCollisionAudit` là đối chiếu trùng identity; `DealerCallPhone` là kiểm tra số công khai đã được duyệt của Dealer, không phải gọi khách C08. Kết luận ở mức source, chưa kiểm tra schema/server thực tế.

### 3. Mô hình dữ liệu tối thiểu đề xuất — chưa tạo migration

Tách **hàng đợi/claim**, **quan sát cuộc gọi**, **quyết định tác động nghiệp vụ** và **eligibility kỳ thưởng**. Một outcome sai không tự đồng nghĩa đã revoke/trừ điểm. Bốn thực thể sau đủ cho lấy mẫu và ghi kết quả; các phép xử lý điểm ở giai đoạn sau cần model riêng.

| Thực thể đề xuất | Dữ liệu tối thiểu | Bất biến/constraint đề xuất |
|---|---|---|
| `call_audit_batches` | Purpose/scope, award_id nullable, loại kỳ và bounds canonical, timezone/calendar_version, eligibility_as_of/cutoff; eligibility/sampling policy version; requested/actual sample size; algorithm version, seed mã hóa/key_version; manifest ứng viên cố định + digest; baseline AwardResults fingerprint nếu thuộc C07; người tạo/frozen_at/status/version | Generation key unique theo scope/purpose/wave đã được TMV chốt; command freeze có idempotency. Batch frozen không thay manifest/seed/member. Không mặc định60/5 hoặc tự tạo batch khi GET |
| `call_audit_items` | batch_id, submission_id và id_snapshot, Dealer/Sales attribution snapshot, selection order; source fingerprint/version lúc chọn; snapshot tối thiểu phục vụ ba câu hỏi và liên hệ; queue state, latest outcome/attempt counter; retry_due_at; claim actor/token hash/lease_until/version | Unique(batch, submission), unique(batch, selection_order). Chống trùng theo customer/phone nếu TMV chọn đơn vị khách, không chỉ theo id. Snapshot chỉ chứa PII được phê duyệt, mã hóa, không public |
| `call_audit_attempts` | item_id, attempt_no, actor; thời điểm bắt đầu/ghi nhận, outcome; ba đáp án true/false/null; note riêng tư; policy/questionnaire version và dữ liệu đã dùng; request/idempotency key + payload digest; thời điểm retry phát sinh nếu có | Unique(item, attempt_no), unique idempotency trong scope thích hợp. Attempt hoàn tất bất biến; không ghi mỗi lần render/click tel; no-answer không được biến thành ba câu Sai |
| `call_audit_events` | batch/item/attempt reference; actor/action/reason/time; version trước/sau, claim/retry/correction/freeze event, correlation và idempotency key | Append-only; có thể dựng lại lịch sử. Không nhân đôi raw phone/plate/name/note vào JSON audit hoặc log. Correction là event mới có reference, không xóa attempt |

- Manifest phải giữ **toàn bộ tập ứng viên đủ điều kiện tại lúc freeze**, không chỉ selected ids hoặc một count/hash không thể tái dựng. MVP có thể lưu manifest bảo vệ trong batch; nếu lớn, tách bảng candidate thứ5 hoặc artifact private bất biến kèm digest. Đánh giá kích thước/thống nhất storage trước migration; nếu dùng artifact, cần publish trạng thái frozen chỉ khi DB và artifact đã được xác nhận đầy đủ, không tham chiếu file chưa ghi xong.
- Snapshot lúc lấy mẫu và source hiện tại là hai dữ liệu khác nhau. Fingerprint phải bao phủ phone/plate/xe/Dealer/Sales/status và evidence liên quan, không chỉ updated_at có độ chính xác giây. Dữ liệu sửa sau freeze phải được phát hiện, không ghi đè snapshot rồi giả như đã đối chiếu dữ liệu mới.
- Không cascade xóa batch→item→attempt/event đã có lịch sử; với FK sang submissions/users cần chọn restrict hoặc nullable FK + snapshot phù hợp lifecycle hiện tại. Không tự thêm FK làm cấm xóa ngoài contract; không xóa lịch sử khi khóa account.
- Index dự kiến cho batch/state/retry_due_at, lease_until, item/attempt_no và actor/idempotency. Lưu thời điểm tuyệt đối nhất quán; không đồng nhất created_at với thời gian cuộc gọi thực.
- **Chưa thuộc MVP:** decision/adjudication record, eligibility/exclusion revision và score adjustment/reversal ledger. Nếu TMV duyệt xử lý sai lệch, cần lưu finding/case id ổn định, căn cứ/actor phê duyệt, phạm vi hiệu lực, phiên bản policy và kết quả áp dụng. Không tận dụng attempt_id làm khóa phạt duy nhất: cùng lỗi có thể được gọi lại hoặc xuất hiện trong hai batch.

### 4. Lấy mẫu, cố định và tái lập

Quy trình kỹ thuật đề xuất sau khi TMV chốt cohort:

1. Chốt purpose, kỳ/bounds, thời điểm eligibility, tập Dealer và rule loại dữ liệu demo/identity bất nhất. Nếu kiểm tra thành tích, **đề xuất** cohort lấy từ đúng các contribution tạo điểm; cần TMV xác nhận approved/group/nguồn/phone/late approvals, không tự chọn pending hoặc toàn database.
2. Lập manifest theo submission id, submitted_at, status/source, Dealer attribution snapshot, Sales/role có ảnh hưởng eligibility và các version/policy có liên quan, nhất quán với thời điểm đọc. Không query riêng Top 3 và cohort ở hai trạng thái dữ liệu khác nhau. Đề xuất transaction/snapshot hoặc intake barrier ngắn để chốt manifest; chuẩn bị dữ liệu dài không giữ row lock lúc Admin gọi điện.
3. Sinh seed ngẫu nhiên an toàn phía server, giữ private; canonicalize manifest, dùng thuật toán deterministic có version, ví dụ xếp thứ tự bằng HMAC(seed, purpose + candidate id), tie-break id. Lấy đủ quota theo thiết kế mẫu TMV chọn, không ORDER BY RAND mỗi lần mở trang. Seed/algorithm/manifest/policy cho phép tái lập; digest chỉ kiểm tra tính toàn vẹn, không tự chứng minh phương pháp là không thiên lệch.
4. Tính mẫu một lần và persist toàn batch/items/order/count; transaction + unique generation key bảo đảm hai Admin không tạo hai batch cùng scope. Retry cùng key trả batch cũ. requested_count và actual_count khác nhau phải có nguyên nhân, không bù bằng record lặp.
5. Batch frozen không thêm bớt âm thầm. Thiếu khách, dữ liệu lỗi, thay Dealer Top 3 hoặc cần gọi bổ sung phải có wave/revision mới và liên kết lý do; ai được regenerate và việc wave nào thay thế wave nào cần TMV chốt. Không “random lại” để chọn kết quả đẹp.

Uniform theo khách toàn hệ thống, chia đều theo Dealer và tỷ lệ theo điểm là **ba thiết kế khác nhau**; không tự chọn. Nếu phân tầng/quota, phải lưu strata/quota và không gọi tỷ lệ gộp đơn giản là tỷ lệ sai đại diện toàn hệ thống. N nhỏ hơn 60 hoặc Dealer ít hơn 5 không thể lấy mẫu không lặp đủ số; rule lấy hết/thay thế/chờ/không đủ điều kiện phải được duyệt. Khách thiếu số hợp lệ, số trùng hoặc từ chối liên hệ không tự bị tính Sai.

### 5. Nhiều Admin, ghi lặp và lưu/chuyển khách

- GET chỉ đọc. “Đang gọi” chỉ từ quyền nhận việc còn hiệu lực hoặc thông tin cuộc gọi có căn cứ, không do selected row/updated_at. Thời điểm và trigger nhận việc theo UI hiện có cần chốt, không tự thêm nút nhận việc.
- Claim là command có policy và transaction; compare-and-set/row lock với item chưa terminal, không thuộc người khác và retry đã tới hạn. Lease TTL/heartbeat/force takeover phải có rule, token opaque gắn actor/session; lease hết không chứng minh cuộc gọi chưa diễn ra.
- Save nhận item id, expected version, claim token, idempotency key, ba đáp án/outcome và note. Server xác thực Admin active, ownership, lease/batch state, source fingerprint và policy version; không tin actor/time/Dealer/score client gửi.
- Transaction ghi một attempt + event + projection queue/retry/claim/counters. Request trùng key/cùng payload trả kết quả đã ghi, không tăng attempts; cùng key/khác payload bị từ chối. Hai tab/người ghi một version chỉ một bên thành công. Lỗi409/claim conflict không làm mất input hoặc bật toast thành công.
- Đề xuất lock order thống nhất batch → items theo id → attempt/event; nếu command đụng submission/verification thì phải đi theo mutex → submission → verification như protocol hiện có trước các lock mới. Cần rà soát toàn bộ writer và thử deadlock trước code, không trộn hai lock order. Publication/correction cũng phải tham gia cùng protocol/version để không race với freeze/save.
- Command lưu/chuyển có thể trả next_item_id đã được nhận việc hợp lệ cùng các count mới; chọn tiếp theo trong batch/tab/filter và retry tới hạn. Không tự cộng counter hoặc chọn hàng đầu HTML cũ. Nếu tách claim-next, phải xử lý đối thủ nhận trước; không dùng GET để ghi claim.
- Mạng đứt sau commit phải retry bằng key cũ; chỉ sau nhận response đã commit mới toast. Không còn khách thì next=null; toast “chuyển sang khách tiếp theo” và màn cuối cần thiết kế riêng, không tạo khách/mẫu để đáp ứng wording.
- Row locks chỉ bảo vệ dữ liệu, không ngăn được hai cuộc gọi vật lý ngoài ứng dụng. Click `tel:`/mở thiết bị không là chứng cứ nghe máy hoặc kết quả; không tự ghi no-answer bằng timer.

### 6. Ba kết quả và gọi lại — state machine dự kiến

| Sự kiện | Lưu gì nếu rule được duyệt | Không được suy đoán |
|---|---|---|
| Đúng thông tin | Attempt có actor/time/ba câu trả lời; item hoàn tất; event; count/next từ server; toast173:2097 sau commit khi phù hợp | Không gọi approval, không cộng điểm, không tự set group member |
| Sai thông tin | Thông tin đối chiếu và finding có căn cứ; sau confirmation/authority hợp lệ mới ghi kết quả và chuyển sang chờ xử lý sai lệch | Không đồng nghĩa đã áp dụng phạt. Không dùng outcome radio làm quyền revoke/deduct |
| Không nghe máy lần đầu | Attempt no-answer, đáp án null, lập retry_due_at theo clock/policy được duyệt; item sang retry | Không ba câu Sai, không cộng số sai, không tự lấy khách thay mẫu |
| Retry tới hạn | Item được claim lại; attempt_no mới khi thực sự ghi nhận lần gọi theo contract | Cron chỉ làm eligible/nhắc việc, không tạo cuộc gọi hoặc kết quả giả |
| Đạt giới hạn no-answer | Kết quả riêng unreachable/skipped theo rule, giữ mọi attempt | “Không tính là sai” có trong Figma; không suy thành Đúng/Đạt, đủ 5 hoặc cho phép công bố |
| Huỷ confirmation sai | Giữ input/phiên làm việc; không commit attempt sai, decision, penalty hoặc lịch sử hoàn tất | Claim đã nhận trước đó là giao dịch riêng; không hứa toàn phiên chưa từng có write |
| Sửa dữ liệu nguồn/lease hết | Phát hiện version drift hoặc ownership hết; dừng save, giữ mẫu và lịch sử, yêu cầu quy trình xử lý rõ | Không tự đổi mẫu, reset attempts hay chấp nhận câu trả lời trên dữ liệu khác |
| Sửa kết quả đã lưu | Correction/reopen event có quyền/lý do, dựng lại projection theo policy | Không update/delete attempt cũ hoặc tự phục hồi điểm |

“Sau 1 ngày” có thể là 24giờ kể từ saved_at, cùng giờ ngày hôm sau tại VN, hoặc ngày làm việc tiếp theo trong khung giờ cho phép; chưa chốt lựa chọn. Phải lưu rule version, base timestamp, due instant và timezone, không dùng ngày trên máy Admin. “2 lần không nghe” là tổng 2 attempt hay 2 lần gọi lại, có cần liên tiếp không, và gọi sau lần 2 có được phép không đều phải hỏi TMV.

Đề xuất trạng thái queue nội bộ: pending, claimed, retry_scheduled, completed; outcome và pending_decision/unreachable là trục riêng. Ánh xạ tab/badge phải chốt: retry chưa tới hạn có nằm trong “Gọi lại” không; completed có bao gồm unreachable hoặc sai đang chờ xét không. Counts phải cùng batch/scope và không đếm nhiều attempts thành nhiều khách.

Tỷ lệ sai phải có tử số/mẫu số cụ thể: số item sai đã xác nhận hay finding đang chờ; số người đã trả lời hợp lệ, số người từng gọi hay toàn mẫu; có loại no-answer/voided/corrected không. Khi mẫu số0 không tính0% giả. So ngưỡng bằng số đếm chính xác trước khi format phần trăm; > hay ≥, min sample và rounding phải do TMV chốt, không suy từ40% mẫu.

### 7. “Huỷ xác minh”, trừ điểm, loại Dealer và đôn hạng phải tách riêng

| Cách hiểu có thể có | Tác động hiện tại/thiết kế cần mới | Quyết định chưa có |
|---|---|---|
| Bỏ evidence group | SubmissionVerification có thể ghi group unknown độc lập | Không làm giảm COUNT approved. Mất evidence group có đúng nghĩa khách sai khi gọi không? Ai được thay đổi, receipt/public status nào bị ảnh hưởng? |
| Thu hồi approval | Không có action approved→revoked; enum hiện không có revoked; Reject chỉ pending | Có cần trạng thái mới hay giữ approved và thêm decision/contribution eligibility? Có giải phóng identity phone/plate/Facebook đang chống trùng không? |
| Trừ một contribution | Muốn điểm giảm phải đổi nguồn tính điểm hoặc dùng adjustment/exclusion contract mới | Chỉ Dealer hay cả Sales; tuần/tháng/all-time; kỳ của submitted_at hay kỳ xử lý; khôi phục/appeal và mức sàn |
| Loại khỏi kỳ thưởng | Eligibility của Dealer đối với một award/cycle, không phải khóa Dealer toàn hệ thống | Có làm mất điểm leaderboard thường không; scope/hiệu lực, adjudicator, quy trình khiếu nại |
| Đôn hạng | Chọn ứng viên kế tiếp từ ranking đã chốt và tạo wave kiểm tra của ứng viên đó | Tie-break giữ quy tắc hiện có hay policy mới; gọi bao nhiêu; làm gì khi ứng viên thay thế tiếp tục bị loại hoặc không đủ khách |

Hai hướng xử lý điểm cần được so sánh trước thiết kế migration: (A) thay eligibility/moderation contribution có action chuyên dụng + history; (B) giữ hồ sơ/approval và dùng exclusion/adjustment ledger theo scope. **Không chọn A hoặc B trong P09A.** Không vừa bỏ contribution khỏi COUNT vừa ghi thêm−1 cho cùng lỗi. Một ledger mới chưa được mọi projection đọc thì chưa thực sự trừ điểm; phải kiểm kê Leaderboard/AwardResults, dashboards, profile và reports đang COUNT approved trực tiếp, cả DemoSubmissionScope và tie-break achieved_at. Không sửa lẻ C07 để hiển thị điểm khác các nơi khác.

Penalty phải idempotent theo finding/case ổn định và phạm vi được duyệt, không theo từng attempt/batch. Cùng hồ sơ gọi lại hoặc được dùng cho hai mục đích không bị phạt hai lần cho cùng lỗi. Reversal là quyết định bù trừ có reference, không xóa ledger/audit; không cho client gửi điểm âm tùy ý. Lưu lý do riêng tư, người xác nhận, source version, thời điểm hiệu lực và preview tác động trước khi áp dụng. Tách quyền ghi cuộc gọi khỏi quyền xác nhận finding, điều chỉnh điểm, loại Dealer và công bố; existing review/publish ability không tự mở các quyền này.

`AwardResults::preview` hiện chỉ trả Top 3, không đủ để suy ra ứng viên hạng 4 trở xuống. Nếu TMV chọn đôn hạng từ baseline frozen, cần read model lưu ranking ứng viên đủ phạm vi, gồm rank/score/achieved_at/tie-break/Dealer snapshot tại lúc chốt; không lấy hạng 4 từ collection chỉ có 3 phần tử hoặc query live rồi gọi là dữ liệu đã chốt.

**Snapshot đã công bố là ranh giới cứng:** không sửa Award/AwardWinner đã published, không recompute lịch sử public từ live score mới. Nếu TMV yêu cầu thông báo đính chính, cần mô hình amendment/revision công khai riêng được duyệt; không tạo Award trùng type/bounds vì unique kỳ hiện tại cũng không cho phép. Snapshot Sales nội bộ vẫn bảo vệ như Dealer. Với draft chưa công bố, bất kỳ penalty/late approval/chỉnh sửa nào làm khác baseline phải invalidate gate hoặc tạo revision minh bạch, không âm thầm đôn hạng rồi giữ chữ Đạt cũ.

### 8. C08 ↔ C07 và mốc giờ Việt Nam

| Phương án để TMV lựa chọn | Cách hiểu60 và 5×Top 3 | Hệ quả kỹ thuật |
|---|---|---|
| A: Hai chương trình | C08 kiểm tra định kỳ 60; C07 batch riêng 5 cho từng Dealer Top 3 | Purpose/scope/batch khác nhau; có được dùng lại attempt không phải chốt. Không mặc định kiểm tra60 đủ điều kiện công bố |
| B: Một batch có phân tầng | Tổng60 bao gồm quota 5 cho mỗi Dealer Top 3 và phần còn lại | Freeze cả strata/quota; tránh chọn trùng giữa quota và phần chung; tỷ lệ toàn mẫu cần hiểu sampling design |
| C: Một pool, các tập con nghiệp vụ | 60 là pool/master, C07 chọn/đọc tập con đủ điều kiện theo từng vòng | Cần liên kết membership/wave, phiên bản Top 3 và freshness; không lặp attempt hoặc penalty khi dùng chung. Có thể cần bảng bridge ngoài 4 thực thể tối thiểu |

Chưa có bằng chứng chọn A/B/C. Tên màn và mock counts không quyết định. Phải chốt có kiểm tra Dealer hạng4 khi thay hạng3, có tăng tổng mẫu, freshness của kết quả giữa tuần/tháng và có dùng lại câu trả lời khi thông tin nguồn đổi không. “Top 3” đã audit phải gắn với một **revision/fingerprint cụ thể**, không chỉ award_id.

Clock trong `config/app.php` là UTC; `LeaderboardService::bounds` dùng app timezone. Theo calendar UTC trong source, khoảng tháng 10/2026 bắt đầu 01/10 00:00 UTC và kết thúc exclusive 01/11 00:00 UTC, tức 07:00 VN. P10A hiển thị thời điểm thật được đổi timezone, không sửa clock. Nếu TMV muốn tháng 10 theo VN 00:00, instant tương ứng là 30/09 17:00 UTC đến 31/10 17:00 UTC. Đây là **khác cohort**, không chỉ đổi format; đổi config toàn cục còn có thể làm AwardResults từ chối bounds draft lịch sử. Cần calendar_version/effective period, mapping và tác động lịch sử được duyệt; giữ nguyên snapshot cũ và weekly Monday bounds, không tự viết lại DATETIME. Không kiểm tra timezone kết nối hoặc dữ liệu production trong P09A.

Tách ít nhất bốn mốc: kỳ của submitted_at; hạn cuối nhận approval cho kỳ; lúc freeze cohort/ranking; lúc công bố. Grace period/late approval, dữ liệu sửa khi đang gọi và ai được mở lại kỳ đều chưa có contract. Publication tương lai phải kiểm tra server-side quyền, kỳ đã close hợp lệ, revision không stale, batch đúng purpose, số kết quả hợp lệ, không decision/claim còn treo và Top 3 thay thế đã đạt rule. Chính xác gate nào bắt buộc là quyết định TMV, không xem disabled CSS hoặc fingerprint chưa khóa là đủ.

### 9. Danh sách quyết định cần TMV xác nhận

Không mục nào dưới đây có giá trị mặc định từ P09A; cần ghi người duyệt, ngày, policy version và kỳ bắt đầu áp dụng.

| ID | Câu hỏi/quyết định | Chặn giai đoạn |
|---|---|---|
| TMV01 | 60ngẫu nhiên và 5/Dealer Top 3: A/B/C ở mục 8?60/5 cố định hay cấu hình; xét theo tháng, tuần hay chiến dịch? | Backend lấy mẫu |
| TMV02 | Đơn vị mẫu là submission, khách hay phone? Cohort chỉ approved, group member hay điều kiện khác; QR/Sales được tham gia thế nào? | Backend lấy mẫu |
| TMV03 | Chọn theo submitted_at hay thời điểm duyệt; approval deadline/grace period/late approval và record bị sửa sau freeze xử lý ra sao? | Lấy mẫu, chốt kỳ |
| TMV04 | Uniform toàn hệ thống, quota đều hay phân tầng theo Dealer/điểm? Có Dealer/khách được ưu tiên hoặc loại trước không? | Backend lấy mẫu |
| TMV05 | Thiếu 60 hoặc Dealer có ít hơn 5 khách hợp lệ: lấy hết, thay, gọi tiếp kỳ khác hay không đủ điều kiện? Thiếu/không hợp lệ phone không được tính Sai mặc định | Backend, C07 |
| TMV06 | Dealer/Sales inactive/blocked, demo, alias trùng và khách đã được audit kỳ trước có trong cohort không? Phạm vi chống trùng và dùng lại bằng chứng giữa batch/tuần/tháng? | Backend, privacy |
| TMV07 | Ai freeze/regenerate/void batch, có được xem seed hoặc chọn lại; cần reviewer thứ hai không? Lý do và giới hạn wave? | Backend, quyền |
| TMV08 | Mốc VN 00:00 có được áp dụng không, effective period nào; xử lý draft/boundsUTC cũ, weekly và số liệu đã công bố thế nào? | Chốt kỳ, publication |
| TMV09 | “Đã xác minh [ngày]” lấy reviewed_at, group_verified_at hay một sự kiện khác? Script lấy tên/xe/biển/Dealer lúc freeze hay live? | C08 dữ liệu |
| TMV10 | Ba câu bắt buộc thế nào, tất cả Đúng mới overall Đúng hay cho override? Một câu Sai có tự là Sai không? Câu 3 trống trong mock có được save? | C08 validation |
| TMV11 | Sai số, từ chối trả lời, không nhớ, thiếu dữ liệu hoặc câu trả lời mâu thuẫn map vào ba outcome nào? Có cần thiết kế bổ sung thay vì ép Sai/no-answer? | C08 outcome |
| TMV12 | Click phone có phải nhận việc/start attempt; dùng tel hay có hệ thống gọi được duyệt? “Đang gọi” từ lease hay telephony evidence? | C08/claim |
| TMV13 | Ai nhận việc, lease TTL/heartbeat, thao tác khi claim hết hạn, takeover và hai Admin cùng gọi; có giới hạn scope được xem/ghi? | Concurrency |
| TMV14 | Gọi lại sau 1 ngày là 24h, ngày lịch VN hay ngày làm việc; tính từ start hay save; có khung giờ/ngày nghỉ và nhắc việc không? | Retry |
| TMV15 | 2 lần là tổng số attempt hay 2 retry, có cần liên tiếp; sau giới hạn có được gọi thêm? Ai được mở lại và lưu correction? | Retry/history |
| TMV16 | Bỏ qua sau no-answer có nằm Đã xong, có tính đủ 5, mẫu số tỷ lệ hay thay mẫu không? Có chặn công bố không? | Counts, C07 |
| TMV17 | “Đã gọi”, counts ba tab và tỷ lệ Dealer đếm khách hay attempts; pending decision/retry chưa tới hạn nằm tab nào? Vì sao mock Cần gọi có Đúng/Sai? | Read model/UX |
| TMV18 | Ngưỡng[x]% làbao nhiêu; > hay≥; tử số/mẫu số, min sample, scope/wave, rounding và mẫu số0? Không lấy40% mẫu làm threshold | Sai lệch, gate |
| TMV19 | “Huỷ xác minh” là group, approval hay award eligibility; có thông báo/public receipt và ảnh hưởng duplicate identity occupancy không? | Sai lệch |
| TMV20 |−1 điểm cho mỗi khách, câu sai hay lần gọi? Chỉ Dealer hay Sales; tuần/tháng/all-time; kỳ hiệu lực, mức sàn, double-count và hoàn lại khi appeal? | Điểm |
| TMV21 | Loại Dealer khỏi kỳ hay khỏi ranking toàn hệ thống; giữ điểm cũ thế nào; ai phê duyệt, có đối soát/kháng nghị và cơ chế đính chính không? | Sai lệch/C07 |
| TMV22 | Đôn ứng viên theo ranking frozen hay live; tie-break hiện hữu giữ không; gọi ứng viên thay thế bao nhiêu; khi không còn đủ 3 Dealer thì dừng hay xử lý khác? | C07 |
| TMV23 | Người ghi Sai có được tự trừ điểm/loại Dealer/công bố không; Admin nào hoặc TMV nào có quyền approve/reverse? Manager có quyền gì không? | Quyền, sai lệch |
| TMV24 | Note có cần bắt buộc cho Sai/override; ai được xem; độ dài, lưu trữ/retention, masking, quyền thấy full phone/plate và snapshotPII? Có ghi âm không — hiện không có contract | Privacy/UX |
| TMV25 | Thiết kế confirmation Sai, Huỷ, validation/lỗi/lease conflict/loading/empty/last item/no-answer và toast ngoài Đúng do ai cung cấp? | C08 giao diện |
| TMV26 | Có cho sửa kết quả cuối, reopen batch hoặc hủy attempt; hiệu lực trên counts/decisions/điểm đã áp dụng và audit cần như thế nào? | History, sai lệch |
| TMV27 | Một finding dùng chung60/Top 3 hoặc nhiều wave được ghi/áp dụng một lần ra sao; freshness và version nguồn có cho tái sử dụng attempt không? | Liên kết batch, idempotency |
| TMV28 | C07 đủ điều kiện khi nào: 5 đã gọi hay 5 có câu trả lời hợp lệ, còn retry/pending decision có chặn không; late approval làm stale có phải gọi lại? | Publication gate |
| TMV29 | Sau publication phát hiện sai: giữ snapshot bất biến và xuất amendment thế nào? Không cho phép ghi đè hoặc tạo kỳ trùng bounds; public Dealer vs internal Sales khác quyền ra sao? | Publication/history |
| TMV30 | Có gửi nhắc Admin/thông báo khách/Sales/Dealer không; kênh, người nhận, retry delivery và câu chữ? Không giả “đã gửi” bằng việc ghiDB | Retry/notification |

### 10. Kế hoạch triển khai chia bốn phần

| Giai đoạn | Phạm vi và điều kiện vào | Điều kiện hoàn thành/kiểm tra dự kiến |
|---|---|---|
| 1 — Backend lấy mẫu | TMV chốt purpose/cohort/quota/calendar/dedupe/privacy/claim; duyệt model 4 thực thể và manifest. Commands tạo/freeze/nhận việc, policy riêng, read model tab/count; không scoring | Seed+manifest tái lập; không trùng, số nhỏ/không phone được xử lý đúng; concurrent generation/claim chỉ một kết quả hợp lệ; freeze không thay đổi qua reload; expired claim và source drift an toàn |
| 2 — C08 UI/ghi kết quả | Backend 1 đã có; chốt mapping câu trả lời, retry/denominator/UX. Bám đúng C08/tabs/toast; lưu version/idempotency và next; không mutate group/approval/điểm | 1440 đối chiếu frame; dữ liệu/count thật, masking đúng; save/error/replay/claim race/next-last; fake clock cho due; no-answer không là sai. Nếu confirmation/scoring contract Sai còn thiếu thì giữ phần thao tác đó chưa kích hoạt, không hiển thị lời hứa đã trừ điểm sai thực tế |
| 3 — Xử lý sai lệch | TMV duyệt huỷ/điểm/scope/threshold/authority/appeal và thiết kế confirmation; chọn chiến lược contribution và ledger thống nhất trước migration | Dry-run tác động trên fixture cách ly; commit decision/effect/audit nguyên tử; cancel không phạt; cùng finding không trừ 2 lần; reverse có history; projection toàn hệ thống nhất quán; published snapshot không đổi |
| 4 — Kết nối C07/công bố | Chốt quan hệ60/15, baseline ranking, thay thế, cutoff/gate và publication workflow; phê duyệt backend/confirmation/toast publication còn thiếu | Revision C08/Top 3 khớp; thiếu5/no-answer/late change chặn đúng; freeze/publish/save race; publish idempotent một lần, fingerprint được enforce trong transaction; snapshot Dealer/Sales riêng và public chỉ Dealer; kiểm tra lịch sử/weekly |

Thứ tự này không cho phép pha 2 hoặc radio tự mở pha 3. P10A vẫn read-only/disabled cho tới khi đủ backend/gate; không dùng “Đã xong” của queue làm proxy “Đạt” của C07.

Kiểm thử tương lai cần thêm suites sampling/claim/attempt/retry/decision/publication và chạy chọn lọc tests approval/verification/Leaderboard/Award bị tác động. Bao gồm double-click, timeout sau commit, cùng key khác payload, source đổi cùng giây, đúng thời điểm due, tối thiểu mẫu/denominator0, cutoffsVN với exclusive end, correction/cross-batch double penalty, snapshot/private projection. Concurrency phải dùng nhiều connection thật tới **database test cách ly**; test hiện dùng DatabaseMigrations không được chạy dưới lệnh cấm migrate/reset. Thiết kế harness transaction/schema sẵn trước; không mặc định transaction của PHPUnit một connection mô phỏng được race nhiều process.

## Kết quả kiểm thử

Không chạy test ứng dụng, build, migration, seed, reset hoặc kết nối database; cũng không chạy preflight DB/runtime GET/POST hay screenshot ứng dụng vì đây là task tài liệu. Các bảng test ở trên là kế hoạch tương lai, không phải PASS đã thực hiện. Lịch sử PASS của P10A không thay xác minh runtime hiện tại.

Kiểm tra tài liệu **PASS**: đủ 9 mục tracking bắt buộc, 30 dòng quyết định TMV01–TMV30 liên tiếp, đúng 3 node C08/tabs/toast, 3 reference tài liệu local tồn tại, không trailing whitespace. Đối chiếu Git status trước/sau tạo tài liệu chỉ tăng đúng file được yêu cầu; không sửa các file đang có thay đổi. Không có kiểm thử nghiệp vụ mới hoặc nghiệm thu giao diện được tuyên bố ở P09A.

## Những lỗi còn tồn tại

Các TMV01–TMV30, prototype reactions và trạng thái giao diện thiếu chưa được xác nhận. Chưa đánh giá cardinality/hiệu năng manifest, actual schema/server, quy trình vận hành hoặc quyền mới. Không có blocking code bug được sửa trong P09A; các gap call/audit/close/publication là công việc chưa triển khai. Không cho phép dùng tài liệu này làm quyết định tự động khấu trừ hoặc công bố.

## Task hoặc bước tiếp theo

TMV trả lời bảng quyết định và duyệt policy version/calendar/scope; thiết kế bổ sung xác nhận Sai và các trạng thái thiếu. Sau đó mới tách task backend lấy mẫu → C08 ghi kết quả → quyết định sai lệch → C07/publication theo gate. Mỗi task cần tracking, approval cho thay đổi schema/nghiệp vụ và test cách ly phù hợp; không có quyền thực hiện những việc đó được suy ra từ P09A.
