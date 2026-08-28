# Task: 011 Cài đặt — Thiết lập Pixels + Custom domain + UTMs tracking

## Objective
Mở rộng menu sidebar "Cài đặt" thành nhóm 4 con: Cài đặt tài khoản (giữ),
Thiết lập Pixels, Custom domain, UTMs tracking. Mỗi tính năng có trang quản lý
riêng và dữ liệu xuất hiện trong form tạo/sửa link (droplist pixels, select
domain, profile UTM tự điền).

## Scope / non-goals
- Trong: per-user pixels (thêm/xoá), domains (thêm/xoá), utm_profiles
  (tạo/sửa/xoá), tích hợp vào form link.
- Ngoài: xác minh domain thật, thanh toán, quản trị admin.

## Acceptance criteria
- AC-048: Sidebar Cài đặt có 4 con; mỗi tab hoạt động; pixel/domain/profile UTM
  tạo ra xuất hiện trong form tạo link.

## Current behavior and evidence
- Cài đặt chỉ có 1 tab (tài khoản). 91/91 PASS (sau cải tiến).

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v6), `app/Repository/PixelRepository.php`,
  `app/Controller/{DashboardController,LinkController}.php`, `app/Container.php`,
  `app/Router.php`, `index.php`, `app/View/{dashboard,link-form}.php`,
  `assets/js/app.js`, `assets/css/style.css`, tests.
- Thêm: `app/Repository/{DomainRepository,UtmProfileRepository}.php`,
  `app/Controller/SettingsController.php`.

## Test-first action
- RouterTest: routes pixel/domain/utm.
- SmokeTest: sidebar nhóm Cài đặt + 4 con; tạo pixel -> droplist; tạo domain ->
  select; tạo profile UTM -> select có data-campaign.

## Implementation steps
1. Migration v6 (pixels.user_id + uq_user_code, domains, utm_profiles) + schema SQLite.
2. Repos; SettingsController POST handlers; Router/Container/index.
3. Dashboard: sidebar group + 3 tab nội dung + flash error.
4. Link form: pixels theo user, domain select, UTM profile auto-fill JS.
5. Test; E2E MySQL (thêm pixel -> droplist).

## Review risks
- Mọi thao tác scope theo user_id; không xoá pixel mặc định.
- Migrate v6 idempotent (guard UNIQUE uq_user_code).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: thêm pixel -> form link có `value="site-pixel"`.

## Recovery / rollback
- Rollback v6: DROP utm_profiles, domains; ALTER pixels drop user_id. Code revert.

## Evidence
- LINT PASS (64 files); security scan PASS; verify-structure PASS.
- Full suite 91/91 PASS (`php tests/run-tests.php --all`): sidebar nhóm Cài đặt +
  4 con; pixel/domain/UTM tạo -> xuất hiện trong form link.
- Migrate v6 idempotent (2 lần): pixels.user_id + uq_user_code, domains, utm_profiles.
- E2E MySQL: sidebar đủ; thêm pixel `site-pixel` -> 302, vào droplist form link,
  lưu đúng user_id=1.
