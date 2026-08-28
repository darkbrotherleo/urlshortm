# Task: 013 Xác minh Custom Domain (DNS TXT) + localhost auto-verify

## Objective
Triển khai xác minh domain: mỗi domain được cấp `verification_token`; user thêm
bản ghi DNS TXT `urlshortm-verify=<token>` rồi bấm Xác minh — hệ thống kiểm tra
bản ghi TXT (dns_get_record). Domain `localhost`/`127.0.0.1` tự xác minh để thử
nghiệm. Chỉ domain đã xác minh mới xuất hiện trong "Choose domain name".

## Scope / non-goals
- Trong: token + verified_at + last_error, DomainService (register/verify),
  nút Xác minh, chỉ verified trong form link.
- Ngoài: kiểm tra CNAME/trỏ về thật, Let's Encrypt, quota domain, cron re-check.

## Acceptance criteria
- AC-051: domain thật chưa xác minh -> hiện TXT + nút Xác minh; verify không có
  TXT -> lỗi; localhost auto-verify; form link chỉ chọn domain verified.

## Current behavior and evidence
- Domains tab cũ chỉ lưu domain (is_verified mặc định 0). 92/92 PASS.

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v8), `app/Repository/DomainRepository.php`,
  `app/Controller/{SettingsController,LinkController}.php`, `app/Container.php`,
  `app/Router.php`, `index.php`, `app/config.php` (dns_check), `app/helpers.php`
  (short_url_for localhost), `app/View/dashboard.php`, `assets/css/style.css`, tests.
- Thêm: `app/Service/DomainService.php`.

## Test-first action
- RouterTest: POST /dashboard/domain/verify.
- SmokeTest: env URLSHORTM_DOMAINS_DNS_CHECK=0; localhost -> Đã xác minh; domain
  thật -> TXT + nút Xác minh; verify -> lỗi TXT; form link chỉ có localhost.

## Implementation steps
1. Migration v8 (4 cột domains) + SQLite test schema.
2. DomainRepository (token/verified/last_error); DomainService.
3. SettingsController create qua service + verifyDomain; route/container/index.
4. Domains tab (status, TXT, Xác minh, last_error); link form chỉ verified.
5. Test; E2E MySQL.

## Review risks
- dns_get_record trong test môi trường có thể chậm -> config dns_check tắt qua env.
- Token không hiện cho người khác; verify scope theo user_id.
- Encoding test file: chỉ dùng Edit tool.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: localhost verified; fake domain TXT + Xác minh; form chỉ localhost.

## Recovery / rollback
- Rollback v8: DROP 4 cột domains. Code revert.

## Evidence
- LINT PASS (68 files); security scan PASS; verify-structure PASS.
- Full suite 92/92 PASS (`php tests/run-tests.php --all`): localhost auto-verify,
  domain thật TXT + Xác minh, verify lỗi TXT, form link chỉ domain verified.
- Migrate v8 idempotent (2 lần): 4 cột domains.
- E2E MySQL: localhost is_verified=1; link.viducongty.com is_verified=0 + token;
  form link chọn được localhost, không chọn domain chưa xác minh.
