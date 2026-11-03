# Phase 3A.2 — Lookup states & manual Facebook Group verification

Ngày 03/11/2026. Đã triển khai backend, Admin UI, customer supplementation có
receipt authorization và các nhánh lookup D07/D08/D10–D15. Không deploy, commit,
push, kết nối toyota/production hoặc backfill dữ liệu lịch sử.

## Căn cứ và khác biệt với Phase 3A

Đã đọc báo cáo Phase 3A, 2C.4A/B/C và đối chiếu routes, policy/middleware, model,
normalizer, intake mutex, approval/rejection/edit, moderation audit, approved
identity, draft/receipt, views và tests. Source là căn cứ khi tài liệu khác nhau.

Phase 3A chỉ trả DTO tối thiểu với kind=found, không có bằng chứng Group/bổ sung.
Phase này bổ sung dữ liệu nghiệp vụ riêng theo yêu cầu mới; không suy ra từ
pending/approved, approved_facebook_identity, rejection_reason, Facebook URL hoặc
click CTA. D13 trong Figma là **Chờ duyệt yêu cầu Group**, không phải moderation
approved. D08 chỉ xuất hiện sau dữ liệu bổ sung được commit thành công.

## Figma đã kiểm tra

File `yKmWQI6BYJ6dponEnidlsq`, MCP session
`56f11b5a-fb9e-4da4-8af8-5388a30e1a3b`. Đã đọc skill figma-design-to-code,
get_design_context (có screenshot) và get_screenshot đủ 16 frame:

| Desktop | Node | Mobile | Node |
| --- | --- | --- | --- |
| D07 | 213:533 | M07 | 57:733 |
| D08 | 212:647 | M08 | 57:941 |
| D10 | 213:636 | M10 | 204:366 |
| D11 | 213:702 | M11 | 204:446 |
| D12 | 213:797 | M12 | 204:541 |
| D13 | 213:894 | M13 | 204:638 |
| D14 | 213:989 | M14 | 204:736 |
| D15 | 213:1086 | M15 | 204:833 |

[Evidence MCP](qa/phase-3a-2-figma-evidence.json). Ảnh tham chiếu đã có tại
[screenshots/phase-3a](qa/screenshots/phase-3a/README.md), không phải ảnh ứng dụng.
Không triển khai D09. Không có SLA 48 giờ trong nội dung lookup mới.

Tái sử dụng Dealer layout/field/link/button/timeline/tokens và local logo/success
assets. Desktop giữ card hai cột, D13 một cột, timeline ngang; mobile xếp dọc,
D15 đặt lý do trong panel, D08 dùng success header và CTA xếp dọc. CSS có nhánh
390/mobile <=767, tablet <=900 và timeline desktop >=768. Inter/Arial/sans-serif
kế thừa layout; không thay typography Admin/Sales.

Khác thiết kế có chủ đích: tên che mọi từ; không trả thông tin Dealer sở hữu hồ
sơ hoặc PII trong kết quả; chưa có bằng chứng Group thì không vẽ bước Group đã
hoàn tất; D07 không có receipt là hướng dẫn liên hệ; unknown giữ kết quả trung
tính; bỏ SLA và hotline placeholder. Phone/plate trên form là dữ liệu khách vừa
nhập, không phải hồ sơ đầy đủ trả từ database.

**Visual QA chưa hoàn tất:** không có browser connector; CDP Chrome đã thất bại
ở Phase 2C.4C. Chưa đo overflow, rendered fonts, focus/keyboard/screen reader hoặc
ảnh crop ở 390/768/1440. Figma SVG download tiếp tục lỗi curl 7; chưa lấy được
đúng decorative search/CTA icon từ Phase 3A và variants timeline mới. Timeline dùng
component hiện có với chỉ báo trạng thái CSS; D08 dùng local success PNG hiện có,
chưa nghiệm thu hình học/độ nét so SVG Figma. Không tuyên bố pixel-perfect hoặc
mọi asset khớp tuyệt đối; không dùng temporary Figma URLs làm runtime assets.

## Ba trục dữ liệu độc lập

Migration mới `2026_11_03_000024_add_submission_verification_workflow.php` tạo:

- `submission_verification_states`: một current row/submission; nullable Group
  progress, actor/time xác minh; nullable blocker và actor/time; supplementation
  UUID, state, reason, requested actor/time, response timestamp; integer version.
- `submission_verification_audits`: append history theo service, submission ID,
  actor Admin hoặc customer đã chứng minh receipt, action, old/new structured
  values và timestamp. Không lưu raw phone/name/Facebook URL, session key hoặc
  credentials. Group audit lưu HMAC fingerprint của profile tại lúc xác minh,
  không phải raw URL. Customer response audit ghi sự kiện nhận bổ sung cùng state
  transition; không lưu bản sao URL cũ/mới.

Không ALTER customer_submissions, không backfill, không thêm unique phone/plate.
Không có row hoặc group_progress=NULL là unknown. FK restrict giữ submission và
Admin attribution khi có lịch sử; down() từ chối khi bảng mới còn dữ liệu.
Migration chỉ chạy theo path trên **toyota_testing**, sau khi kiểm tra cấu hình
và SELECT DATABASE() trả đúng toyota_testing. Không chạy migrate:fresh hoặc
RefreshDatabase. Schema toyota/production chưa được áp dụng.

Release/local activation phải áp dụng migration mới trước khi chạy code đọc bảng
mới. Chưa chạy trên local toyota theo giới hạn nhiệm vụ. Operator phải review
ledger/backup và chạy migration trên target được xác nhận; không dùng .env local
để suy ra production connection. Không tự rollback lịch sử audit.

## Mapping và thứ tự ưu tiên

| Bằng chứng thực tế | Kết quả |
| --- | --- |
| Không match, nhiều match, inconsistent phone aliases | D10, không chọn bản ghi mới nhất |
| Không có submission match; draft đúng session/Dealer còn hạn, phone + saved plate khớp | D11 |
| Có supplementation state=open | D07; có quyền mới mở form Facebook |
| Supplementation đã submitted và receipt còn hạn thuộc session tra cứu | D08 |
| Admin đặt blocker code có cấu trúc | D15 |
| Bằng chứng Group member còn khớp profile, moderation không rejected | D14 |
| Bằng chứng requested còn khớp profile, moderation không rejected | D13 |
| Bằng chứng not_requested còn khớp profile, moderation không rejected | D12 |
| Unknown, thiếu evidence, profile đổi, rejected chung | Kết quả trung tính found |

Ambiguous luôn D10, không rơi về draft. D11 tiếp tục yêu cầu draft có saved plate
từ Step Two; bare Step One không đủ cặp dữ liệu để tra cứu. D07/D08/blocker thắng
Group progress, đúng thứ tự yêu cầu. Session khác không được D08 từ response của
người sở hữu receipt; chỉ nhận kết quả tối thiểu theo bằng chứng còn lại.

Policy mâu thuẫn: Admin có thể ghi nhận sự thật Group độc lập kể cả submission
rejected. Không sửa moderation. Với rejected + requested/member/not_requested,
lookup không tuyên bố thành viên chính thức; trả neutral nếu không có yêu cầu
bổ sung/blocker có ưu tiên cao hơn. Admin detail cảnh báo dữ liệu cần đối chiếu.
Không tạo reactivation hoặc tự approved/rejected. Policy này có regression test.

Nếu Facebook profile được sửa sau xác minh, HMAC bằng chứng cũ không khớp;
lookup trở về neutral cho trục Group cho đến khi Admin xác minh lại. Giữ nguyên
progress/audit cũ, không cho thao tác khách tự chuyển Group progress. Không tự
coi approved identity là bằng chứng Group.

## Admin workflow

Admin detail có phần Trạng thái Facebook Group riêng với ba form POST:

1. Xác nhận/correct requested/member/not_requested hoặc đưa về unknown.
2. Yêu cầu bổ sung reason `facebook_unavailable` hoặc `missing_information`.
3. Đặt/xóa blocker `duplicate_plate`, `identity_conflict`, `verification_unavailable`.

Có thao tác đóng yêu cầu open/submitted sau khi xử lý; mọi thay đổi có audit.
Current state, người xác minh/thời điểm và 20 sự kiện gần nhất được hiển thị.
Lịch sử dùng nhãn tiếng Việt, không đưa HMAC/session/customer credentials vào UI.
Không có internal free-text note mới; public không nhận Admin note/audit/actor.

Authorization dùng policy review active Admin và middleware auth/session/role/
account.active; CSRF và admin-writes throttle giữ nguyên. Version HMAC kết hợp
state version, submission ID/status/profile/update time. Lỗi stale hiển thị tại
phần xác minh, giữ lựa chọn vừa nhập; field errors dùng named bag verification,
không thêm summary dài vào review. Không thay form approve/reject/inline edit.

Pending/approved/rejected, approved identity, moderation reviews và leaderboard
không đổi khi cập nhật Group/blocker/request. Supplement request trực tuyến chỉ
tạo cho pending để tránh sửa approved identity hoặc mở workflow tái kích hoạt.

## Customer supplementation authorization

Chỉ hỗ trợ cập nhật online Facebook profile cho reason facebook_unavailable khi:

- Receipt DB còn hạn 24 giờ, owner HMAC khớp session + Dealer và submission ID.
- Dealer active; submission thuộc Dealer đó, vẫn pending.
- Request UUID đúng current open request; version còn khớp; CSRF hợp lệ.
- URL là personal Facebook profile theo FacebookUrlNormalizer hiện có.

UUID nằm trong POST body, **không phải bearer grant**: biết UUID hoặc phone + plate
không đủ quyền ghi. Không phát hành token/OTP từ cặp lookup. Không token/PII trên
URL, không đưa raw input vào shared flash old input. Receipt quyền truy cập không
được gia hạn bởi lookup hoặc gửi bổ sung. Request được đổi UUID khi Admin mở lại;
request cũ không dùng để gửi lần nữa.

Ghi URL bằng guarded CustomerSubmission save, rồi chuyển request open→submitted
và ghi audit trong cùng transaction. Không sửa phone/plate/status/attribution,
không xác nhận Group, không tạo moderation review. Failure trước commit rollback
cả URL/state/audit. Sau commit redirect GET D08 theo PRG, chỉ lưu UUID tham chiếu
không có PII trong session. Refresh GET không POST lại. Retry/concurrent cùng
request chỉ có một response audit; lần stale trả 422, không ghi lại URL.

Không có receipt, hết hạn, session/Dealer khác hoặc historical registration:
D07 hướng dẫn liên hệ, không form sửa. `missing_information` cũng contact-only:
chưa có yêu cầu/schema trường bổ sung được chốt ngoài Facebook URL, không tự
mở quyền sửa tên/identity/địa chỉ. Admin có thể xử lý qua editor có audit hiện có
và đóng yêu cầu. Không giả lập customer response/D08 cho thao tác liên hệ.

Validation URL cụ thể ngay dưới field, giữ dữ liệu vừa nhập trong response 422
chỉ sau receipt authorization. JS không tự chuyển D08: chỉ native POST sau
validation, loading/disable chống double click, focus/scroll/aria theo component
chung; không hiển thị lỗi untouched. Backend vẫn là nguồn validation cuối.

## Transactions và security

Write order: SubmissionIntake mutex → submission FOR UPDATE → workflow state
FOR UPDATE → receipt FOR UPDATE khi customer response. Tái sử dụng transaction
retry 3 lần; không thêm outer transaction khác. Admin action kiểm tra version và
authorization lại sau lock. Receipt locator trước lock không được coi là quyền
ghi; tất cả điều kiện được kiểm tra lại bên trong transaction.

Lookup cũ giữ quota IP + canonical phone HMAC, CSRF, honeypot, allowlist input,
masked DTO và canonical matching lịch sử toàn hệ thống. New supplement endpoint
thêm 5/phút/IP + 15/giờ/session, HMAC keys; từ chối honeypot/system fields. Mọi
lookup/supplement/confirmation response và errors là private/no-store,
noindex/nofollow, no-referrer. Không public serialize model, Facebook URL, địa chỉ,
evidence, Admin/Sales hoặc dữ liệu bản ghi khác. Không query string ép UI state.
GET không ghi submission, workflow hoặc Group progress.

## Routes và files

Routes mới, giữ nguyên canonical Dealer/QR và D01–D06:

```text
POST /admin/submissions/{submission}/verification  admin.submissions.verification
POST /{slug}/registrations/lookup/supplement       dealers.lookup.supplement
GET  /{slug}/registrations/lookup/supplemented      dealers.lookup.supplemented
```

Tạo:

- app/Services/SubmissionVerification.php.
- app/Services/RegistrationLookupStateResolver.php.
- app/Http/Controllers/Admin/SubmissionVerificationController.php.
- app/Http/Controllers/RegistrationSupplementController.php.
- database/migrations/2026_11_03_000024_add_submission_verification_workflow.php.
- resources/views/components/submission-verification.blade.php.
- resources/views/components/dealer-registration/{lookup-state,lookup-next}.blade.php.
- tests/Feature/RegistrationLookupStatesTest.php.
- docs/figma/qa/{supplement-validation-check.mjs,phase-3a-2-figma-evidence.json}.
- docs/figma/phase-3a-2-lookup-states.md.

Chỉnh:

- app/Services/RegistrationLookup.php: callback nội bộ sau khi loại ambiguity,
  không đưa submission ID vào public DTO; find mặc định giữ contract cũ.
- app/Http/Controllers/RegistrationLookupController.php: gọi resolver.
- app/Http/Controllers/Admin/SubmissionReviewController.php: authorized state/audit.
- app/Providers/RouteServiceProvider.php; routes/web.php: routes/throttle.
- resources/views/admin/submissions/show.blade.php: compose phần xác minh.
- resources/views/dealers/registration/lookup.blade.php: các state thật.
- resources/css/dealer-registration.css; resources/js/dealer-registration.js.
- tests/Feature/RegistrationHttpConcurrencyTest.php: hai race mới và fixture cleanup.
- docs/figma/qa/render-check.php: coverage các Blade lookup states.
- public/build (ignored) rebuild; không thay lockfiles, .env, APP_KEY hoặc backend
  approval/duplicate/leaderboard/draft/receipt hiện có.

Working tree có nhiều thay đổi từ phase trước; danh sách trên chỉ là lượt này.

## Kiểm thử

Regression chọn lọc **PASS 274 tests / 2.419 assertions**, 1 phút 52,166 giây,
peak 100 MB. Không tuyên bố đã chạy toàn bộ legacy Feature suite có destructive
database traits. Targets gồm RegistrationLookupStatesTest, RegistrationLookupTest,
DemoIsolationTest, ToyotaRealisticDemoSeederTest, ControlledDuplicateApprovalTest,
RegistrationHttpConcurrencyTest, SubmissionIntegrityUpdateTest,
SubmissionIntakeRetryTest, RegistrationReceiptCleanupTest,
RegistrationBusinessIntegrationTest, DealerRegistrationFinalTest,
DealerRegistrationDraftTest, DealerRegistrationSuccessPresentationTest,
DealerRegistrationStepTwoValidationTest và tests/Unit.

Sau điều chỉnh cuối của guard đóng yêu cầu và phần history UI, chạy lại riêng
RegistrationLookupStatesTest cùng hai HTTP race mới: **23 tests / 258 assertions
PASS**, 13,553 giây. Read-only post-check trên toyota_testing xác nhận submissions,
workflow states/audits, receipts, moderation reviews, users và dealers đều 0 rows;
không có fixtures còn sót sau rollback/owned cleanup.

HTTP suite có 12 cases, gồm hai case mới: hai Admin ghi cùng version chỉ có một
audit/version increment và một stale warning; hai customer POST cùng request
chỉ một response được commit, request còn lại trả 422. Các race intake/approval/
legacy/Sales/idempotency trước đó tiếp tục PASS. Không có kết luận an toàn tuyệt
đối cho mọi interleaving hoặc deadlock timing.

- npm run build PASS, Vite 6.4.3 / 62 modules. Production manifest trỏ
  `dealer-registration-BOc59hHq.css` và `dealer-registration-CRiDIgFV.js`.
- Pint toàn repo PASS; git diff --check PASS, chỉ warning CRLF→LF có sẵn.
- 54 Blade render checks PASS; 9 production boot/route-cache preflight checks
  PASS với DB disabled. Không clear application cache/draft.
- Bốn frontend harness PASS: Step One, Step Two, lookup, supplementation; kiểm
  tra message/focus/aria/correction/retention/loading/double-submit bằng mock DOM,
  không phải browser automation.
- 22 assets PNG không rỗng; không temporary Figma URLs trong views. Kết quả này
  không xác nhận các SVG variants còn thiếu hoặc geometry browser.

Test DB được xác minh là toyota_testing; transaction fixtures rollback. HTTP concurrency dùng hai PHP
servers, connection và cookie/session độc lập; test bổ sung double-submit dùng
hai HTTP connections cùng cookie để chứng minh cùng owner. Chỉ dọn fixtures do
test tạo, không chạm dữ liệu toyota hoặc historical production.

Coverage mới: unknown, progress mapping, status/points/identity bất biến, active
Admin/guest/Sales/Dealer auth, version stale, request/response ownership/expiry,
invalid URL/retention/no flash, actual D08 PRG/replay, structured blocker priority,
generic rejected, profile-change evidence, audit rollback, honeypot/throttle,
real CSRF thiếu/sai/hợp lệ. Phase 3A giữ ambiguity/canonical historical/draft tests.

## Điểm chưa kiểm chứng và operator decisions

1. Browser/mobile/tablet/font/assets/screen-reader acceptance còn mở như trên;
   render checks và HTTP tests không chứng minh pixel-perfect/không overflow.
2. Historical/session mất hoặc receipt >24h không được sửa online. Muốn mở quyền
   cần quy trình xác minh/distribution token được duyệt; chưa có SMS provider.
3. Missing-information ngoài Facebook URL chưa có form ghi dữ liệu mới; hướng dẫn
   liên hệ là deliberate safe fallback, không tuyên bố hoàn tất online.
4. Retention: FK restrict bảo toàn workflow audit. Hard-delete pending submission
   có lịch sử mới (ví dụ Sales delete) sẽ bị DB từ chối; chưa thay delete policy
   hoặc xóa audit để né FK. Cần operator chốt UX/retention của trường hợp này trước
   release; không mở rộng nghiệp vụ xóa ngoài phạm vi. Hồ sơ không có workflow
   mới vẫn theo hành vi cũ. Không chạy cleanup workflow/audit thật.
5. Bằng chứng Group do Admin kiểm tra thủ công; không có tích hợp Facebook API,
   external sync hay bảo đảm thực tế Group không đổi sau lần kiểm tra. Admin cần
   cập nhật corrections. Không coi CTA là đã gửi yêu cầu.
6. Lookup canonical scan vẫn O(N), mutex chung serialize writes; chưa benchmark
   dataset lớn/production load/deadlock exhaustion. HTTP race PASS chỉ chứng minh
   các trường hợp được thử, không mọi interleaving hoặc writers ngoài repo.
7. Production schema/ledger/data/demo/VM/dependency audit/Linux no-dev artifact,
   shared session/file draft persistence/TLS và backup/restore chưa được kiểm
   chứng; blockers 2C.4C không tự đóng. Artifact/checksum cũ không đại diện code mới.

Không commit, push, deploy hoặc chạy migration trên toyota/production.
