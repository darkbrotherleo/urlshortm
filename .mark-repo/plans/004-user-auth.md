# Task: 004 Tài khoản người dùng (đăng ký/đăng nhập) + schema hướng thương mại hoá

## Objective
Cho user (không phải admin) đăng ký, đăng nhập, đăng xuất với layout hiện đại
mang tính marketing. Thiết kế lại schema DB cho user và chuẩn bị thương mại hoá
(gói dịch vụ): bảng users, plans, user_subscriptions, gán link về user.

## Scope / non-goals
- Trong: trang /dang-ky, /dang-nhap, POST /dang-xuat, header trạng thái login,
  gán user_id khi tạo link, migrate v2 + seed gói, test.
- Ngoài: admin, dashboard link cá nhân, thanh toán thật, quên mật khẩu, xác thực
  email, custom slug, quota enforcement UI.

## Acceptance criteria
- AC-022: GET /dang-ky và /dang-nhap trả 200, layout marketing, không kiểu AI.
- AC-023: Đăng ký thành công -> 302 /, đã đăng nhập; email trùng -> 409; mật khẩu
  < 8 -> 400; thiếu CSRF -> 403; quá 10 lần/IP/giờ -> 429.
- AC-024: Đăng nhập đúng -> 302 /; sai mật khẩu -> 401 (message chung);
  session mới sau login (regenerate); logout -> 302 / và hết session.
- AC-025: Khi đã đăng nhập, link tạo mới có user_id tương ứng; link ẩn danh NULL.
- AC-026: Migrate v2 idempotent: users/plans/user_subscriptions + cột
  short_links.user_id + seed 3 gói (free/starter/pro); chạy lại không lỗi.

## Current behavior and evidence
- v1+v2 hiện có: landing, shorten, redirect, stats. 48/48 test PASS. Chưa có user.

## Exact files / dependencies
- Đổi: `database/migrate.php`, `database/schema.sql`, `app/Router.php`,
  `app/index.php`, `app/Container.php`, `app/helpers.php`,
  `app/helpers_global.php`, `app/Repository/UrlRepository.php`,
  `app/Service/ShortUrlService.php`, `app/Controller/HomeController.php`,
  `app/View/header.php`, `tests/support/bootstrap.php`,
  `tests/http/SmokeTest.php`, `tests/unit/RouterTest.php`.
- Thêm: `app/Repository/UserRepository.php`, `app/Service/AuthService.php`,
  `app/Service/AuthException.php`, `app/Controller/AuthController.php`,
  `app/View/auth-register.php`, `app/View/auth-login.php`,
  `tests/integration/UserRepositoryTest.php`, `tests/integration/AuthServiceTest.php`.

## Test-first action
- UserRepository (SQLite): insert/findByEmail/unique violation/findById.
- AuthService (SQLite): register hash + login đúng/sai, email trùng,
  rate limit register/login, regenerate session id.
- Router: /dang-ky, /dang-nhap, /dang-xuat.
- Smoke: GET form 200; đăng ký -> login -> link có user_id -> logout.

## Implementation steps
1. migrate v2 + schema.sql + seed plans.
2. UserRepository; AuthService (password_hash, rate limit, session regenerate).
3. AuthController + router + container + index.php; current_user() helper;
   HomeController/ShortUrlService gán user_id.
4. Views auth-register/auth-login marketing + header trạng thái login.
5. Test + chạy full suite; E2E MySQL migrate + auth.

## Review risks
- Lộ enumeration email: đăng nhập sai trả message chung; rate limit theo email.
- Session fixation: regenerate khi đăng nhập; use_strict_mode.
- SQLite schema trong test phải đồng bộ với migrate v2.
- Không để password trong log/session.

## Verification commands
- `php database/migrate.php` (chạy 2 lần, idempotent).
- `powershell scripts\lint.ps1`; `php tests/run-tests.php --all`.
- smoke curl MySQL: GET /dang-ky 200; POST đăng ký -> 302 + session;
  tạo link khi login -> user_id đúng.

## Recovery / rollback
- Rollback v2: DROP user_subscriptions, plans, users; ALTER short_links DROP user_id.
- Code: xoá các file auth; header revert.

## Evidence
- LINT PASS (39 files); security scan PASS; verify-structure PASS.
- Full suite 68/68 PASS (`php tests/run-tests.php --all`, chạy 3 lần ổn định)
  gồm smoke: GET /dang-ky, /dang-nhap 200; CSRF 403; đăng ký -> đăng nhập ->
  link gắn user_id -> thoát; link ẩn danh user_id NULL.
- E2E MySQL: register 302 + tự đăng nhập; login 302; shorten khi login sinh slug
  `IPvzhx` gắn `user_id=1` cho `testuser@vidu.vn`; logout 302.
- Migrate v2 idempotent: chạy 2 lần OK; short_links.user_id + users/plans/
  user_subscriptions + seed free/starter/pro đúng UTF-8.
