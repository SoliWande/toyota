# P09S — Penalty Ledger Schema V1

## Mục tiêu

Source migrations/models/policy cho ba bảng ledger Dealer V1, bất biến/append-only, evidence/idempotency/encryption và constraints; chuẩn bị nghiệm thu riêng toyota_testing. Không DDL/writer/scoring integration.

## Trạng thái

DONE source P09S — 12/11/2026: **60 pure tests / 273 assertions PASS**; syntax/Pint/diff PASS. **DDL và schema tests MySQL NOT_RUN**, chưa nghiệm thu schema hoạt động. Khởi tạo IN PROGRESS trước source.

USER_APPROVED chính sách confirmed hợp lệ,1 điểm/submission/tháng không trùng C07/C08, Dealer gốc, Sales không trừ, display floor0, rank theo display và tie-break hiện hành; không đổi approval/verification/published snapshots. Blocker thiếu approval P09R được thay thế trong task này; giữ nguyên tài liệu lịch sử. Approval không được diễn giải thành quyền chạy DDL hoặc viết writer trong P09S.

## Hiện trạng trước khi chỉnh sửa

Đã đọc AGENTS/P09R/P09Q/P09P/nghiệm thu E2 P09O, source migrations/models/policies/tests A1 và scoring/Awards liên quan. Chưa có schema ledger thật. P09Q vẫn SIMULATION_ONLY, P09R vẫn preparation theo proposal lịch sử; không sửa chúng để kích hoạt runtime. Working tree có nhiều phase chưa commit, giữ nguyên.

## Các file source code liên quan

Chỉ tạo11 file, không sửa source có trước task:

- `database/migrations/2026_11_12_000201_create_dealer_penalty_entries_table.php`.
- `database/migrations/2026_11_12_000202_create_dealer_penalty_evidence_table.php`.
- `database/migrations/2026_11_12_000203_create_dealer_penalty_events_table.php`.
- `app/Models/DealerPenaltyEntry.php`, `DealerPenaltyEvidence.php`, `DealerPenaltyEvent.php`.
- `app/Policies/DealerPenaltyEntryPolicy.php`.
- `tests/Unit/DealerPenaltyModelTest.php`, `DealerPenaltyPolicyTest.php`.
- `tests/Feature/DealerPenaltySchemaTest.php` (source để nghiệm thu DDL riêng, không chạy P09S).
- Tracking này.

## Những thay đổi đã thực hiện

Tạo tracking trước source. Ba migrations theo dependency entries → evidence → events, InnoDB, explicit dates/actors, không trigger/default tạo chế tài. down() chỉ là source rollback theo chiều ngược dependency; không thực thi trong P09S.

| Bảng | Dữ liệu chính |
|---|---|
| entries | Submission/tháng/bounds/calendar; Dealer lịch sử + encrypted attribution proof; points/policy/projection/ranking/schema versions; encrypted policy snapshot; source submitted/observed time + fingerprint; applied_by/applied_at/effective_at/created_at; encryption metadata |
| evidence | Entry/batch/item/attempt/call event/current resolution event và version/item version; verified_by + call/resolution/verified timestamps; encrypted evidence snapshot + digest/key/encryption versions |
| events | Entry/evidence optional/actor; per-entry sequence/version/counts; command/key/ordinal/correlation/reason/policy/schema/time; hidden metadata; encrypted intent và receipt riêng; payload/previous/event digest + key/encryption versions |

Models guarded `['*']`, timestamps=false, immutable updates/deletes và evidence/events append-only qua model hooks; encrypted:array casts và hidden private fields/keys/digests/receipts. Evidence model khai báo đúng table singular `dealer_penalty_evidence`. Quan hệ tới source/Dealer/actor/entry/batch/item/attempt/call event/resolution event; không sửa model A1/E2.

Policy auto-discovered riêng trên DealerPenaltyEntry: class ability `applyDealerPenalty` chỉ active Admin, không gọi/suy từ resolveFinding. viewAny/view cùng active Admin; generic create=false để không thay thế capability chuyên biệt; update/delete/correct/reverse/publish/viewSensitiveData=false. Chưa có route/writer để thực thi capability.

## Quyết định kỹ thuật quan trọng

Unique business key không chứa Dealer/purpose/policy để không thể phạt lại cùng submission/tháng. Policy applyDealerPenalty riêng, không suy từ resolveFinding. Schema chỉ lưu immutable debit/evidence/events; raw/net/display/rank sẽ là projection sau này, không lưu điểm tổng có thể stale hoặc sửa LeaderboardService. FK event IDs chưa chứng minh chữ ký/confirmed; writer tương lai phải xác minh E1/E2 dưới khóa.

### Constraints source

| Constraint | Khai báo |
|---|---|
| Debit duy nhất | `dpe_submission_month_uq`: UNIQUE(submission_id,monthly_scope_key) |
| Evidence duy nhất | `dpv_resolution_event_uq`: UNIQUE(resolution_event_id); `dpv_entry_item_version_uq`: UNIQUE(entry_id,item_id,resolution_version) |
| Ledger event sequence | `dpn_entry_sequence_uq`: UNIQUE(entry_id,event_sequence) |
| Idempotency | `dpn_command_ordinal_uq`: UNIQUE(actor_id,command_name,command_key,event_ordinal), ASCII binary case-sensitive key; intent/receipt encrypted, không tự coi unique là replay đã được verify |
| Composite FKs | evidence(item_id,batch_id) → A1 items(id,batch_id); evidence(attempt_id,item_id) → A1 attempts(id,item_id); events(evidence_id,entry_id) → new evidence(id,entry_id), có unique phụ dpv_id_entry_uq |
| FK actor/history | Source/Dealer/applied_by, verified_by, actor_id, entry/batch/event IDs đều FK RESTRICT; không CASCADE/SET NULL, không xóa history khi xóa tài khoản |
| Entry CHECKs | points>0; month YYYY-MM; end>start và source_submitted_at trong [start,end); applied_at>=attribution_observed_at; created_at>=applied_at |
| Evidence CHECKs | resolution_version>0, item_version_used>0; verified_at>=resolution_recorded_at>=call_recorded_at |
| Event CHECKs | sequence>0, after_version=before_version+1=event_sequence; after count>0 và >=before count; first sequence previous digest NULL/count before0, later sequence previous digest NOT NULL/count before>0 |

Có9 CHECK source, named FK/unique hỗ trợ chẩn đoán. Numeric versions/counts unsigned; event ordinal0 hợp lệ. Không hard-code points=1 trong DDL để giữ lịch sử policy version linh hoạt; writer V1 sau này bắt buộc enforce1 theo policy đã duyệt, không nhận mức phạt tùy ý. Policy version strings phải validate server-side. Evidence_count là ảnh chụp tại event, không cập nhật entry bất biến khi đính kèm evidence mới; event versions thuộc stream ledger, không tăng item.row_version C1.

### Baseline và giới hạn

- Constants approved mới: `dealer_minus_one_floor_zero_v1`, `dealer_display_floor_zero_v1`, `display_desc_achieved_asc_id_asc_v1`. Khác namespace PROPOSED P09R và SIMULATION_ONLY P09Q, không kích hoạt hai service cũ hoặc đổi lịch sử. Effective_at không bị CHECK áp đặt deadline/VN00 hoặc cấm retroactive; task writer phải áp contract hiệu lực cụ thể.
- Raw từ scoring hiện hành, penalty SUM ledger entries, net signed raw−penalty, display=max(0,net); rank theo display DESC/achieved_at ASC/id ASC chuẩn bị bằng version metadata/policy snapshot. Không lưu rank/raw/display tổng hoặc tie timestamp live vào debit, không thêm provider/query vào LeaderboardService/AwardResults. Sales không có cột debit hoặc subject_type cho phép chọn Sales.
- FK đảm bảo existence và một số membership; chưa chứng minh entry.submission/month/Dealer khớp item hoặc event là current confirmed đúng chữ ký. Không sửa A1 thêm composite FK event IDs. Writer tương lai phải check đầy đủ dưới khóa bằng E1/E2, atomic entry/evidence/events/receipt, không client verified boolean; attribution khác nhau phải conflict, không sửa nguồn để ép FK.
- Actor FK chỉ chứng minh user tồn tại, không role/status. Capability riêng được unit test; writer tương lai phải đọc lại active Admin từ DB và authorize server-side trong transaction. Không tự cấp correction/reversal/publication.
- Model hooks không ngăn raw SQL/DBA. Key versions là metadata, chưa có key rotation/retention engine. Encrypted cast test trong memory không thay kiểm chứng MySQL round-trip. Không có action/router/scheduler/UI hoặc tác động điểm thật.

## Kết quả kiểm thử

| Kiểm tra thực tế | Kết quả |
|---|---|
| `php vendor/bin/phpunit tests/Unit/DealerPenaltyModelTest.php tests/Unit/DealerPenaltyPolicyTest.php tests/Unit/ProposedPenaltyLedgerTest.php tests/Unit/ProposedPenaltyProjectionTest.php --no-progress --display-skipped` | **60 PASS / 273 assertions**,0 skipped/fail.21 ca mới models/policy +39 ca preparation cũ, đều PHPUnit TestCase không tạo Laravel application |
| `php -l` | PASS đúng10 PHP mới (3 migrations,3 models,1 policy,2 unit,1 schema test) |
| `php vendor/bin/pint --test` đúng10 file | PASS |
| Diff/tracking | git diff --check + whitespace file mới/tracking9 mục kiểm riêng; không format/sửa source ngoài task |
| Migration SQL/DDL/engine/FK/CHECK/unique thực | **NOT_RUN** — không migrate, không pretend/DDL, không mở connection |
| `tests/Feature/DealerPenaltySchemaTest.php` | **NOT_RUN**, không gọi file trong lệnh PHPUnit và không báo skipped như thay nghiệm thu. Source đã viết5 ca engine/FK/CHECK, debit/evidence/event uniques, composite FK, version/count, restrictive deletion, actors và encrypted DB round-trip |

Unit model tests chỉ kiểm hooks đã đăng ký (dispatch updating/deleting trực tiếp, không save), guarded/hidden fields, encrypted cast round-trip trong memory, date/version metadata; policy test toàn role/status, guest, independent class capability và auto-discovery. Chưa có SQL/query/fixture hoặc test concurrency thật trong P09S.

Schema test để task sau: mặc định skip trước bootstrap nếu chưa opt-in `TOYOTA_RUN_DEALER_PENALTY_SCHEMA_TESTS=1`; flag test này không cấp quyền DDL/activation. Khi được cho phép, source yêu cầu explicit DB_HOST127.0.0.1/DB_DATABASEtoyota_testing/test config-cache chưa tồn tại trước bootstrap; config URL/socket/read/write bị từ chối trước PDO, verify actual database/port3306/FK checks1/TCP/MySQL>=8.0.16, tables đã tồn tại và A1 audit rỗng, không tự clear/migrate. Parent fixtures dùng A3 tagged journal+cleanup, target penalty rows chỉ trong rollback transaction; SQL guard cấm DDL và business parent writes trong ca schema. Cần task riêng cấp phép fixtures/preflight/schema backup/DDL rồi mới chạy, không tự bật flag.

Không kết nối DB/production, không migrate/fresh/seed/reset/full regression. Không có preflight/row counts/cleanup DB mới để báo PASS vì task này không mở DB. Không build/browser vì không sửa UI/assets.

## Những lỗi còn tồn tại

Không còn FAIL source/unit. **Không còn BLOCKED vì thiếu approval chính sách đã nêu.** Phần chưa nghiệm thu là DDL/constraints/ciphertext DB thực và atomic writer/concurrency/scoring integration, tách task theo phạm vi. Nếu MySQL DDL lỗi giữa chừng ở task sau, phải dừng/báo từng bảng, không tự drop/rollback dữ liệu. Không tự xử lý attribution mâu thuẫn, không mở reversal/correction hoặc sửa published snapshots.

## Task hoặc bước tiếp theo

Task riêng: preflight loopback toyota_testing/schema snapshot → review SQL/dependency → áp dụng đúng3 migrations nếu được phép → schema tests/fixtures cleanup → báo trạng thái thực. Sau nghiệm thu mới giao writer/idempotency/concurrency và provider điểm Dealer tháng/rank display. P09S không phải approval DDL, deploy hoặc kích hoạt phạt.
