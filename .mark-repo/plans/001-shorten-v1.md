# Task: v1 Rút gọn URL + redirect 301 + đếm click

## Objective
Xây lát cắt end-to-end tối thiểu tại docroot `C:\laragon\www\UrlShortM`: tạo link
ngắn từ URL dài (tự thêm https nếu thiếu), redirect 301, đếm click. Không tài
khoản/admin/API JSON.

## Scope / non-goals
- Trong: trang chủ form, POST /shorten (CSRF + validate + rate limit), GET /{slug}
  301 + click++, 404, test.
- Ngoài: custom slug, xoá/sửa link, dashboard, QR, referrer tracking, allowlist domain.

## Acceptance criteria
Đạt toàn bộ AC-001..AC-011 trong `specs/acceptance.md`.

## Current behavior and evidence
- `project/` rỗng (`.gitkeep`). Docroot chưa có code. PHP 8.3.30 sẵn sàng.

## Exact files / dependencies
- Docroot: `index.php`, `.htaccess`, `robots.txt`, `assets/{css/style.css,js/app.js}`.
- App: `app/{bootstrap.php,config.php,db.php,helpers.php,Router.php}`,
  `app/{Controller,Service,Repository,Security,View}` (chi tiết mục implementation).
- DB: `database/{schema.sql,migrate.php}`; config `config.local.example.php`.
- Test: `tests/{run-tests.php,router.php,support/**,unit/**,integration/**,http/**}`.
- Deps: chỉ PHP 8.3 + PDO (MySQL/SQLite) + mbstring; không composer.

## Test-first action
Viết test trước cho: UrlNormalizer (scheme auto-add, reject non-http), SlugGenerator
(format/độ dài), Router (route + slug + 404), UrlRepository (SQLite: insert/find/
increment), RateLimiter (vượt ngưỡng), CSRF (verify), rồi mới implement để PASS.

## Implementation steps
1. `config.php` + `config.local.example.php` + `db.php` (PDO singleton) + `helpers.php`.
2. `bootstrap.php`: autoload `App\`, session cookie params, error handler an toàn.
3. Security: `SlugGenerator`, `UrlNormalizer`, `Csrf`, `RateLimiter`.
4. Repository: `UrlRepository`, `RateLimitRepository` (SQL portable MySQL+SQLite).
5. Service: `ShortUrlService` (create: normalize->rate limit->slug retry->insert;
   resolve: find->increment).
6. `Router.php` + controllers `HomeController`, `RedirectController`.
7. Views: `layout.php`, `home.php`, `notfound.php`, `error.php`.
8. Assets CSS/JS (loading state, copy button, alert, responsive).
9. `.htaccess` (rewrite + deny app dirs), `robots.txt`.
10. `database/migrate.php` + `schema.sql`.
11. Tests + `tests/router.php` + scripts `lint.ps1`, `security-scan.ps1`.

## Review risks
- Đường dẫn rewrite nuốt asset -> .htaccess ưu tiên file/dir thật; router test dùng router.php.
- URL Unicode khi redirect -> dùng raw target sau normalize (không urlencode toàn bộ).
- Rate limit race -> upsert 2 bước (insert, catch duplicate -> update); chấp nhận v1.
- Lộ lỗi -> error handler ẩn chi tiết khi debug off.

## Verification commands
- `php tests/run-tests.php --all` (focused + integration + http smoke).
- Smoke: start built-in server, `curl -i /`, POST /shorten, GET /{slug} -> 301,
  GET lạ -> 404, click_count tăng.

## Recovery / rollback
- DB: chạy `DROP TABLE short_links; DROP TABLE rate_limits;` rồi migrate lại.
- Code: xoá file docroot; không đụng `.mark-repo`.

## Evidence
- LINT PASS (38 files) via `scripts/lint.ps1`.
- Focused: 36/36 PASS (`php tests/run-tests.php`).
- Full suite (kèm http smoke): 44/44 PASS (`php tests/run-tests.php --all`).
- E2E trên MySQL thật: GET / 200; POST /shorten 200 sinh slug; GET /{slug} 301 tới target; click_count tăng đúng (2 lượt -> 2).
- Security scan: PASS (`scripts/security-scan.ps1`).
- Migrate idempotent chạy OK trên MySQL (`php database/migrate.php`).