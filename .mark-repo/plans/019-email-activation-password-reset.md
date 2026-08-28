# Task: 019 Kích hoạt tài khoản + khôi phục mật khẩu qua email

## Objective
Tài khoản mới đăng ký ở trạng thái PENDING, hệ thống gửi email kích hoạt
(`/kich-hoat`), chỉ sau khi kích hoạt mới đăng nhập được. Bổ sung luồng quên mật
khẩu: `/quen-mat-khau` gửi liên kết đặt lại qua email (token 30 phút, dùng 1 lần),
`/dat-lai-mat-khau` đổi mật khẩu.

## Scope / non-goals
- Trong: migration v20 (`users.status` ENUM active/pending/disabled + 4 cột token),
  `UserRepository` token/activate, `AuthService` register(pending)/activate/
  requestPasswordReset/resetPassword, `AuthController` activate/forgot/reset,
  views auth-activate-sent/forgot/reset, routes, badge "Chờ kích hoạt" admin,
  helper smoke `register_and_activate`, test smoke forgot/reset.
- Ngoài: resend email kích hoạt, tự xoá tài khoản pending hết hạn, 2FA,
  captcha.

## Acceptance criteria
- AC-060: register tạo user PENDING + token 24h + gửi email, KHÔNG tự đăng nhập;
  login pending bị từ chối "chưa được kích hoạt".
- AC-061: `/kich-hoat?token=` kích hoạt + tự đăng nhập; token sai/hết hạn -> 400.
- AC-062: `/quen-mat-khau` gửi liên kết (token 30 phút), `/dat-lai-mat-khau`
  đổi mật khẩu thành công, token dùng 1 lần, hết hạn bị từ chối, đăng nhập được
  bằng mật khẩu mới.

## Current behavior and evidence
- Trước v20: register tạo user ACTIVE + tự đăng nhập (185/185 PASS). Sau v20 đã
  đổi toàn bộ smoke site đăng ký sang `register_and_activate`; 187/187 PASS.

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v20), `app/Repository/UserRepository.php`
  (findByEmail thêm token columns, setActivation/findByActivationToken/activate/
  setResetToken/findByResetToken/clearResetToken), `app/Service/AuthService.php`,
  `app/Controller/AuthController.php`, `app/Router.php`, `index.php`,
  `app/View/auth-activate-sent.php`+`auth-forgot.php`+`auth-reset.php`,
  `app/View/login.php` (link Quên mật khẩu?), `app/View/admin-users.php` (badge),
  `assets/css/style.css` (alert-ok/alert-warn),
  `tests/support/bootstrap.php` (users schema + `register_and_activate`),
  `tests/http/SmokeTest.php`, `tests/integration/AuthServiceTest.php`,
  `tests/unit/RouterTest.php`.

## Test-first action
- Integration: register PENDING + không session; activate đúng/sai/hết hạn;
  login từ chối pending; reset thành công + dùng 1 lần + hết hạn.
- Smoke: forgot -> reset -> login mật khẩu mới + token expired bị từ chối.

## Implementation steps
1. Migration v20 + schema SQLite test (status enum + 4 cột token).
2. UserRepository token methods + findByEmail bổ sung cột token.
3. AuthService: register pending + sendActivationEmail; activate; request/reset.
4. AuthController + views + routes (/kich-hoat, /quen-mat-khau, /dat-lai-mat-khau).
5. Login page link + admin badge + alert CSS.
6. Smoke helper `register_and_activate` + refactor toàn bộ site đăng ký cũ +
   thêm smoke forgot/reset; RouterTest + AuthServiceTest cập nhật.

## Review risks
- SMTP chưa cấu hình: email kích hoạt gửi fail nhưng phải giữ user PENDING
  (graceful, không throw làm hỏng register); smoke dùng error path.
- Token tuyệt đối không đưa token raw vào form (giữ trong session khi reset);
  CSRF mọi POST; reset token tự xoá sau khi dùng.
- Migration enum đổi giá trị cũ ('active' giữ nguyên) — idempotent.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`;
  `powershell scripts\security-scan.ps1`.
- E2E: `php database\migrate.php` -> SHOW COLUMNS users: status enum +
  activation_token/reset_token/activation_expires_at/reset_expires_at.

## Recovery / rollback
- Bỏ routes/handlers/views; ALTER users status về enum cũ + DROP 4 cột token;
  register về tự đăng nhập. Không đổi bảng khác.

## Evidence
- LINT PASS (145 files); security scan PASS.
- Full suite 187/187 PASS: register PENDING/activate/login-pending bị chặn
  (integration), smoke quên mật khẩu -> reset (token 30 phút, dùng 1 lần, hết
  hạn 400, đăng nhập mật khẩu mới), RouterTest route mới.
- E2E MySQL: `SHOW COLUMNS FROM users` có status enum('active','pending','disabled')
  + activation_token + activation_expires_at + reset_token + reset_expires_at.
