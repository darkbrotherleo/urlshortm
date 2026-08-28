# Task: 017 GĐ3 Báo cáo nâng cao — chi tiết lượt mở + Export CSV

## Objective
Nâng cấp tab Báo cáo: bảng "Chi tiết lượt mở" (phân trang 50/trang) và nút
"Tải CSV" xuất toàn bộ lượt mở theo filter (link + khoảng thời gian), UTF-8 BOM
cho Excel.

## Scope / non-goals
- Trong: reportEvents/countReportEvents, exportReport (route), bảng chi tiết +
  phân trang, country_label helper.
- Ngoài (GĐ sau): bản đồ quốc gia, nhân khẩu học, dashboard admin.

## Acceptance criteria
- AC-058: bảng chi tiết (phân trang) + export CSV giữ filter, đúng user.

## Current behavior and evidence
- GĐ2 xong (114/114). Thêm chi tiết + CSV.

## Exact files / dependencies
- Đổi: `app/Repository/ClickEventRepository.php`, `app/Controller/DashboardController.php`,
  `app/Router.php`, `index.php`, `app/helpers.php`+`helpers_global.php` (country_label),
  `app/View/dashboard.php`, `assets/css/style.css`, tests.

## Test-first action
- Integration: reportEvents (thứ tự desc + slug/title), countReportEvents,
  phân trang (offset).
- Smoke: baocao có "Chi tiết lượt mở"/"Tải CSV"; GET export -> text/csv + header + dòng.

## Implementation steps
1. repo reportEvents/countReportEvents (join slug/title).
2. DashboardController: baocao nạp events + page; exportReport() (fputcsv + BOM);
   reportFilters() dùng chung.
3. Router/index route /dashboard/bao-cao/export.
4. View: nút Tải CSV + bảng chi tiết + phân trang; country_label helper.
5. Test; E2E MySQL.

## Review risks
- CSV escape qua fputcsv; BOM UTF-8 cho Excel; scope theo user_id.
- Phân trang chỉ hiện khi >50.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: export trả text/csv + header + dữ liệu.

## Recovery / rollback
- Bỏ route/export/table. Không đổi schema.

## Evidence
- LINT PASS (78 files); security scan PASS; verify-structure PASS.
- Full suite 114/114 PASS (`php tests/run-tests.php --all`): reportEvents/count/
  pagination integration; smoke chi tiết + CSV (text/csv, header, dữ liệu).
- E2E MySQL: baocao có "Chi tiết lượt mở"/"Tải CSV"; export CSV 200 + content-type
  text/csv + header Thời gian; report-pager chỉ hiện khi >50 click.
