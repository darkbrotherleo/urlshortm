# Task: 018 GĐ4 Nhân khẩu học (Meta) — age/gender breakdown + báo cáo

## Objective
Bổ sung nhân khẩu học cho tài khoản: user cấu hình Ad Account ID + Access Token
(Meta Marketing API), hệ thống lấy phân bổ độ tuổi + giới tính (breakdown
age,gender, 90 ngày), lưu snapshot, hiển thị bảng + biểu đồ trong tab
Nhân khẩu học và Báo cáo. Tuân thủ chính sách Meta: dữ liệu tổng hợp, không PII,
kèm cảnh báo pháp lý.

## Scope / non-goals
- Trong: `user_settings`, `demographic_snapshots`, `UserSettingsRepository`,
  `DemographicRepository`, `MetaAudienceService`, SettingsController handlers
  (saveMeta/fetchDemographics/clearDemographics), DashboardController tab
  `demographics` + snapshot trong baocao, view/JS chart, tests.
- Ngoài (GĐ sau): đa snapshot lịch sử theo thời gian, refresh tự động theo lịch,
  phân quyền admin, dashboard admin.

## Acceptance criteria
- AC-059: cấu hình Meta (lưu Ad Account + token, token che dấu chỉ lộ 4 ký tự
  cuối, không render token vào HTML); fetch age/gender 90 ngày; lưu snapshot +
  bảng + biểu đồ trong Báo cáo; dữ liệu tổng hợp không PII + cảnh báo chính sách.

## Current behavior and evidence
- GĐ3 xong (119/119 trước GĐ4). Thêm bảng GĐ4 + service + repo + UI.

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v10), `app/Repository/*` (mới),
  `app/Service/MetaAudienceService.php` (mới), `app/Container.php`,
  `app/Controller/{SettingsController,DashboardController}.php`, `app/Router.php`,
  `index.php`, `app/View/dashboard.php`, `assets/js/report.js`, `assets/css/style.css`,
  tests (unit/integration/smoke), `tests/support/bootstrap.php` (schema test).

## Test-first action
- Unit: MetaAudienceService với fake fetcher (parse age/gender, error API,
  JSON rác) — KHÔNG gọi mạng thật.
- Integration: user_settings set/get/upsert/delete; snapshot save/latest/deleteAll.
- Smoke: tab demographics render + lưu cấu hình 302 + token che dấu; KHÔNG fetch
  qua mạng.

## Implementation steps
1. Migration v10 + schema SQLite test (user_settings, demographic_snapshots).
2. Repos + MetaAudienceService (Graph v21.0 insights breakdown age,gender).
3. Container wiring + SettingsController save/fetch/clear + Router/index routes.
4. DashboardController tab demographics + metaConfig/demoSnapshot cho baocao.
5. View: tab cấu hình + bảng snapshot + section Báo cáo; report.js charts
   (age bar, gender pie); CSS report-demo-grid.
6. Tests + E2E MySQL migrate.

## Review risks
- Token KHÔNG được render vào HTML (chỉ placeholder 4 ký tự cuối).
- Fetch gọi API ngoài — đặt timeout 20s, catch RuntimeException, không tự
  động trong smoke; test qua fake fetcher.
- Upsert driver-aware (MySQL ON DUPLICATE KEY / SQLite ON CONFLICT).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`;
  `powershell scripts\security-scan.ps1`.
- E2E: `php database\migrate.php` trên MySQL -> SHOW TABLES có 2 bảng mới.

## Recovery / rollback
- Bỏ route/handlers/tab; DROP 2 bảng mới + ALTER ngược. Không đổi bảng cũ.

## Evidence
- LINT PASS (83 files); security scan PASS.
- Full suite 119/119 PASS (`php tests/run-tests.php --all`): MetaAudience
  (parse/error/rác), user_settings + snapshot (integration), smoke demographics
  (render, save 302, token che dấu, không lộ token).
- E2E MySQL: `SHOW TABLES` có `user_settings` + `demographic_snapshots`; `DESCRIBE`
  đúng cột/PK/FK.
