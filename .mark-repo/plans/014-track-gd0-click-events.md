# Task: 014 GĐ0 Tracking — nền tảng dữ liệu click_events

## Objective
Tạo nền tảng dữ liệu tracking: bảng `click_events` ghi 1 dòng mỗi lần redirect
với opened_at, ip_hash (SHA-256 + salt, không lưu IP thô), user_agent, referrer,
user_id. Đây là nền tảng cho mọi báo cáo (GĐ1-5).

## Scope / non-goals
- Trong: migration v9, ClickEventRepository, ghi event trong RedirectController,
  hash_ip helper, test.
- Ngoài (GĐ sau): parse UA thành device/browser/os, GeoIP country, trang Báo cáo,
  nhân khẩu học, retention/consent.

## Acceptance criteria
- AC-055: mỗi redirect ghi 1 click_events (opened_at, ip_hash, UA, referrer, user_id).

## Current behavior and evidence
- Hiện chỉ đếm click_count. 102/102 PASS (sau cải tiến).

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v9), `app/config.php` (tracking.ip_salt),
  `app/helpers.php` (hash_ip), `app/Container.php`,
  `app/Controller/RedirectController.php`, tests.
- Thêm: `app/Repository/ClickEventRepository.php`.

## Test-first action
- Unit: hash_ip 64 hex, deterministic, khác IP thô.
- Smoke: mở link 3 lần -> click_events = 3; mở với UA+Referer -> row lưu ip_hash/UA/referrer/user_id.

## Implementation steps
1. Migration v9 click_events + schema SQLite test.
2. config ip_salt + hash_ip; ClickEventRepository; Container.
3. RedirectController ghi event sau incrementClicks.
4. Test; E2E MySQL.

## Review risks
- Không lưu IP thô (hash + salt); user_id nullable cho link ẩn danh.
- Không mở reader SQLite trước write trong test.
- FK link_id cascade: xoá link -> xoá event (mong muốn).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: mở link -> `SELECT * FROM click_events`.

## Recovery / rollback
- Rollback v9: DROP click_events. Code revert.

## Evidence
- LINT PASS (73 files); security scan PASS; verify-structure PASS.
- Full suite 102/102 PASS (`php tests/run-tests.php --all`): hash_ip unit,
  smoke click_events đếm = lượt mở, row đủ ip_hash/UA/referrer/user_id.
- Migrate v9 idempotent (2 lần): bảng click_events.
- E2E MySQL: mở `track-e2e` -> click_events có user_id=1, ip_hash 64 hex,
  user_agent "E2EChrome/120...", referrer "https://facebook.com/x", opened_at.
