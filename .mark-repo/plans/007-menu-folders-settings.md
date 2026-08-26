# Task: 007 Menu sidebar phân cấp + Thư mục + Cài đặt

## Objective
Mở rộng menu sidebar dashboard: Tổng quan (giữ), Quản lý link (nhóm) → All Link,
Folder; Tài khoản (giữ); thêm Cài đặt. Triển khai feature Thư mục (tạo/xoá/gán
link) và trang Cài đặt (đổi tên hiển thị).

## Scope / non-goals
- Trong: sidebar phân cấp, tab Folder (folder table + CRUD cơ bản + gán link),
  tab Cài đặt (đổi display_name), All Link đổi nhãn, flash lưu thành công.
- Ngoài: đổi mật khẩu, gói thật, folder lồng nhau, drag&drop, dashboard admin.

## Acceptance criteria
- AC-033: Sidebar có nhóm "Quản lý link" với 2 mục con "All Link" và "Folder";
  mục con đang chọn luôn active (`is-active` + `aria-current`).
- AC-034: Tạo thư mục (POST) -> 302 về tab Folder, thư mục hiển thị; xoá thư mục
  trả link về "Không thư mục".
- AC-035: Gán link vào thư mục; mở folder thấy link; link chỉ hiển thị với user sở hữu.
- AC-036: Tab Cài đặt đổi tên hiển thị thành công; tiếng Việt có dấu.

## Current behavior and evidence
- Dashboard v6 có 3 tab (tong-quan/links/tai-khoan). 70/70 PASS.

## Exact files / dependencies
- Đổi: `database/migrate.php` + `schema.sql` (folders + short_links.folder_id),
  `app/Repository/{FolderRepository,UrlRepository,UserRepository}.php`,
  `app/Controller/DashboardController.php`, `app/Router.php`, `app/Container.php`,
  `index.php`, `app/View/dashboard.php`, `assets/css/style.css`,
  `tests/{support/bootstrap.php,http/SmokeTest.php,unit/RouterTest.php}`.
- Thêm: `app/Repository/FolderRepository.php`.

## Test-first action
- RouterTest: POST /dashboard/folder/{create,delete}, /dashboard/link-folder,
  /dashboard/settings -> handler đúng.
- SmokeTest: sidebar phân cấp; tạo folder -> hiển thị; gán link -> vào folder thấy
  link; cài đặt đổi tên -> hiển thị tên mới.

## Implementation steps
1. Migration v3 (folders + folder_id) + schema SQLite test.
2. FolderRepository (create/findByUser/findById/delete transaction);
   UrlRepository (findByFolder/assignFolder/folder_id trong findByUser);
   UserRepository::updateDisplayName.
3. DashboardController: TABS mở rộng + POST handlers (create/delete folder, assign,
   settings) có CSRF + guard login + scope user.
4. View dashboard: sidebar nhóm, tab Folder (danh sách + bảng + gán), tab Cài đặt.
5. CSS submenu/folder/assign/settings; test; E2E MySQL.

## Review risks
- Mọi thao tác thư mục phải scope theo user_id (không đụng dữ liệu người khác).
- Test không giữ reader SQLite mở trước write (đóng connection).
- Xoá thư mục dùng transaction (unassign + delete).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: tạo folder -> 302, hiển thị, lưu đúng user.

## Recovery / rollback
- Rollback v3: DROP folders + ALTER short_links DROP folder_id. Code revert.

## Evidence
- LINT PASS (50 files); security scan PASS; verify-structure PASS.
- Full suite 72/72 PASS (`php tests/run-tests.php --all`), smoke: sidebar phân
  cấp (Quản lý link → All Link/Folder, Cài đặt); tạo folder -> hiển thị; gán link
  -> vào folder thấy link; cài đặt đổi tên -> hiển thị tên mới.
- Migrate v3 idempotent (2 lần) trên MySQL: bảng `folders` + `short_links.folder_id`.
- E2E MySQL: login testuser -> sidebar đầy đủ; tạo folder "Marketing" -> 302, hiển
  thị trong tab Folder, lưu đúng `user_id` (testuser@vidu.vn).
