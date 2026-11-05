# QA artifacts và baseline

Chạy script từ repository root. Các lệnh QA và tham số before/after giữ nguyên;
output mới mặc định vào `docs/figma/qa/artifacts/latest/`, đã được Git ignore.
Không ghi đè `screenshots/` và các report lịch sử đã lưu.

Nên đặt chung một `QA_RUN_ID` cho các lệnh của cùng một lượt kiểm tra:

```powershell
$env:QA_RUN_ID = '20261105-footer'
node docs/figma/qa/awards-sticky-footer-visual.mjs before
node docs/figma/qa/awards-sticky-footer-visual.mjs after
```

`qaInput` tìm đầu vào do chính run đó sinh trước, sau đó mới fallback sang
`docs/figma/qa/screenshots/`. Vì vậy lệnh after vẫn dùng được baseline lịch sử
khi chưa tạo before trong run mới. JSON matrix do PHP QA sinh dùng cùng run ID
với script browser; guard/database isolation không đổi. Thiếu cả hai input thì
script vẫn báo lỗi, không tự tạo dữ liệu để làm test PASS.

Run ID chỉ nhận chữ/số, dấu chấm, gạch ngang và gạch dưới, bắt đầu bằng chữ/số.
Không dùng cùng run ID cho hai phiên QA chạy đồng thời. Trước khi lập baseline
mới, phải hiểu phiên bản source/build/data được dùng; không tự coi baseline mới
là bằng chứng của task lịch sử.

Hai script competition-visual/font-audit vẫn giữ override `COMPETITION_QA_OUTPUT`
đã có. Đường dẫn override do người chạy chọn; nên đặt nó trong artifacts/.
Generated gallery tự trỏ về ảnh Figma/baseline lịch sử khi cần. Report và archive
mới của release-manifest.py cũng nằm trong artifacts/<run>/release; không phải
gói deploy và không ghi đè checksum/manifest cũ.

Không blanket-ignore hoặc xóa scripts, fixtures, before baselines, reference,
matrix/evidence JSON hay production assets. Các thư mục test-results,
playwright-report, blob-report và log/video/trace tạm cũng đã được ignore.
Nếu cần giữ bằng chứng mới trong Git, chọn file có chủ đích và cập nhật tracking;
không add nguyên artifacts/.

Task dọn duplicate chỉ được xóa allowlist đã duyệt, sau kiểm tra hash/backup/restore.
Xem `docs/qa-artifact-storage-plan-a.md`; không chạy dọn wildcard hoặc Git clean/reset.
