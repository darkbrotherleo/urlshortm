# Task: 006 Bảng điều khiển user (sidebar trái + khu hiển thị phải)

## Objective
Trang dashboard cho user đã đăng nhập: sidebar trái (tấm mực ấm) + khu hiển thị
phải; tab giữ active qua `?tab=` (server-render, URL là trạng thái). Tiếng Việt
có dấu, thiết kế sáng tạo "ink & paper", không yếu tố mẫu AI. Menu sidebar sẽ bổ
sung chi tiết sau.

## Scope / non-goals
- Trong: route `/dashboard` (auth-guard), 3 tab (Tổng quan, Link của tôi, Tài
  khoản), danh sách link của user, gauge số liệu thật, copy đa nút.
- Ngoài: dashboard admin, xoá/sửa link, biểu đồ theo ngày, đổi mật khẩu, gói
  thật, menu sidebar chi tiết (sẽ bổ sung sau).

## Acceptance criteria
- AC-029: `/dashboard` yêu cầu đăng nhập; khách -> 302 `/dang-nhap`.
- AC-030: User thấy sidebar trái + nội dung phải; tab đang chọn luôn active
  (`is-active` + `aria-current="page"`) và chuyển nội dung đúng theo `?tab=`.
- AC-031: Tab "Link của tôi" liệt kê link của user (slug, lượt mở, ngày, copy);
  link mới tạo xuất hiện ngay.
- AC-032: Giao diện tiếng Việt có dấu; không chứa thuật ngữ kỹ thuật; responsive.

## Current behavior and evidence
- Có auth v4 (đăng nhập, link gắn user_id). 68/68 PASS trước task này.

## Exact files / dependencies
- Thêm: `app/Controller/DashboardController.php`, `app/View/dashboard.php`.
- Đổi: `app/Repository/UrlRepository.php` (findByUser, userTotals),
  `app/Repository/UserRepository.php` (findById thêm created_at), `app/Router.php`,
  `app/Container.php`, `index.php`, `assets/css/style.css`, `assets/js/app.js`,
  `tests/unit/RouterTest.php`, `tests/http/SmokeTest.php`.

## Test-first action
- RouterTest: `/dashboard` GET -> dashboard, POST -> notfound.
- SmokeTest: guest /dashboard 302; đăng ký -> /dashboard 200 + active tổng quan;
  tạo link -> ?tab=links hiển thị slug + active links; ?tab=tai-khoan hiện email.

## Implementation steps
1. UrlRepository::findByUser/userTotals; UserRepository::findById + created_at.
2. DashboardController (auth-guard, tab whitelist, data theo tab).
3. Router + Container + index.php wire `/dashboard`.
4. View dashboard.php (sidebar + main, tab active server-side, copy nút).
5. CSS ink & paper; JS copy đa nút (.js-copy + #copy-btn).
6. Test + full suite; E2E MySQL.

## Review risks
- Auth-guard phải chạy trước khi render; không lộ dữ liệu user khác
  (mọi query theo user_id).
- Tab mặc định về tong-quan khi `?tab` lạ.
- Không mở read-DB trong test trước write (lock SQLite — đã ghi nhận).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: /dashboard guest 302; login -> 200; ?tab=links thấy link user.

## Recovery / rollback
- Bỏ route + view + CSS; không đổi DB.

## Evidence
- LINT PASS (40 files); full suite 70/70 PASS (`php tests/run-tests.php --all`),
  smoke: guest /dashboard 302; /dashboard 200 + active tổng quan; tạo link ->
  ?tab=links hiển thị slug + active links; ?tab=tai-khoan hiện email.
- E2E MySQL: guest /dashboard 302; login testuser -> /dashboard 200 (Tổng quan,
  Link gần đây); ?tab=links hiển thị link `IPvzhx` của user.
