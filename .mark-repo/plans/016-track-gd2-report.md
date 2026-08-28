# Task: 016 GĐ2 Tracking — trang Báo cáo

## Objective
Thêm tab "Báo cáo" trong dashboard: tổng hợp dữ liệu `click_events` của user
thành summary + biểu đồ (Chart.js self-host) theo ngày, thiết bị, trình duyệt,
OS, quốc gia, referrer, top link; bộ lọc theo link + khoảng thời gian.

## Scope / non-goals
- Trong: tab baocao, ClickEventRepository report methods, Chart.js + report.js,
  filter, empty state.
- Ngoài (GĐ sau): nhân khẩu học, export CSV, dashboard admin.

## Acceptance criteria
- AC-057: tab Báo cáo có summary + biểu đồ Chart.js; filter link/ngày; dữ liệu
  scope theo user.

## Current behavior and evidence
- GĐ0+GĐ1 xong (113/113). Báo cáo cần dữ liệu từ click_events.

## Exact files / dependencies
- Đổi: `app/Repository/ClickEventRepository.php`, `app/Controller/DashboardController.php`,
  `app/Container.php`, `app/View/dashboard.php`, `assets/css/style.css`, tests.
- Thêm: `assets/js/vendor/chart.umd.min.js`, `assets/js/report.js`.

## Test-first action
- Integration ClickEventReportTest: summary/byDay/byFactor/topLinks đúng theo user.
- Smoke: tab baocao 200 có chart-day/report-data/Thiết bị/Referrer + load Chart.js.

## Implementation steps
1. Chart.js UMD self-host + report.js (đọc JSON, dựng line/pie/bar).
2. ClickEventRepository: reportSummary, reportByDay, reportByFactor, reportTopLinks (filter link + from/to).
3. DashboardController tab baocao + data + filter; sidebar "Báo cáo".
4. View: filter form, summary gauges, chart canvases, embed JSON.
5. Test; E2E MySQL.

## Review risks
- Embed JSON trong script dùng JSON_HEX_TAG để tránh vỡ `</script>`.
- Scope theo user_id; private/local không có country.
- Chart.js chỉ tải khi tab baocao (giảm tải).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: mở link -> tab baocao có summary + canvas.

## Recovery / rollback
- Bỏ tab + report.js + chart. Không đổi schema.

## Evidence
- LINT PASS (78 files); security scan PASS; verify-structure PASS.
- Full suite 114/114 PASS (`php tests/run-tests.php --all`): ClickEventReportTest,
  smoke baocao (chart-day/report-data/Thiết bị/Referrer + Chart.js).
- E2E MySQL: mở link (iPhone x2 + Chrome x1) -> tab baocao summary gauge=5,
  đủ chart-day/chart-device/chart-country/report-data, load chart.umd.min.js.
