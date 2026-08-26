# Project Profile

- Name: URL Shortener Micro (UrlShortM)
- One-line outcome: Nhập URL dài -> nhận link ngắn ổn định, redirect 301 về đích và đếm được số click
- Primary users: Người cần chia sẻ link ngắn; không cần tài khoản
- Main stack: PHP 8.3 thuần (PDO) + MySQL 8.4
- Runtime environments: local / staging / production
- Database: MySQL 8.4, schema `urlshortm`, UTF-8 (utf8mb4)
- Public entry point: `C:\laragon\www\UrlShortM` (docroot Laragon)
- Deployment target: Apache (Laragon local); có thể chạy built-in PHP server cho test
- Data sensitivity: Thấp - chỉ chứa target URL công khai, không có PII ngoài IP dùng cho rate limit (lưu hash)
- Definition of done: Mọi AC trong specs/acceptance.md có evidence, focused + full test PASS, redirect + click đếm hoạt động thật trên MySQL

## Commands

- Install: copy `config.local.example.php` -> `config.local.php`; `php database/migrate.php`
- Develop: docroot `C:\laragon\www\UrlShortM` (Apache Laragon); hoặc `php -S localhost:8000 -t . tests\router.php`
- Test focused: `php tests/run-tests.php`
- Test full: `php tests/run-tests.php --all`
- Lint/typecheck: `powershell scripts\lint.ps1`
- Build: không có (PHP thuần, không bundle)
- Security scan: `powershell scripts\security-scan.ps1`
- Database migrate/rollback: `php database/migrate.php` (idempotent) / `DROP TABLE` theo migration cũ

## Non-goals

- Không có tài khoản/đăng nhập ở v1.
- Không có dashboard admin, không có API JSON công khai, không có custom slug.
- Không preview/screenshot target URL, không dò tìm URL nội bộ.

