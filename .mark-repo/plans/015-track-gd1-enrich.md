# Task: 015 GĐ1 Tracking — làm giàu dữ liệu (UA + GeoIP)

## Objective
Làm giàu `click_events`: parse User-Agent -> device/browser/os; tra quốc gia từ IP
(GeoIP CSV). Lưu vào các cột country/device/browser/os khi ghi mỗi click.

## Scope / non-goals
- Trong: `UserAgentParser`, `CountryLookup` (CSV ip_start,ip_end,country), tích hợp
  RedirectController, seed CSV nhỏ.
- Ngoài (GĐ sau): dataset GeoIP đầy đủ, trang Báo cáo, nhân khẩu học.

## Acceptance criteria
- AC-056: click_events điền device/browser/os + country; private/local -> country null.

## Current behavior and evidence
- GĐ0 xong (102/102). GĐ1 thêm parse + geoip.

## Exact files / dependencies
- Đổi: `app/config.php` (tracking.geoip_file), `app/Container.php`,
  `app/Controller/RedirectController.php`, tests.
- Thêm: `app/Tracking/{UserAgentParser,CountryLookup}.php`, `data/geo/ip-country.csv`.

## Test-first action
- Unit UserAgentParserTest (iPhone/Android/iPad/Windows/Firefox/Edge/Mac),
  CountryLookupTest (US/VN/CN, private null, invalid null).
- Smoke: mở link với UA iPhone Safari -> device=mobile/browser=Safari/os=iOS,
  country null (IP local).

## Implementation steps
1. UserAgentParser (device/browser/os regex).
2. CountryLookup (CSV + private ranges); config geoip_file; seed CSV.
3. RedirectController tính UA/geoip rồi record; Container wire.
4. Test; E2E MySQL.

## Review risks
- Private IP (127/10/192.168...) -> country null (không lưu vị trí nội bộ).
- CSV đầy đủ cần thay khi deploy; dataset này là seed.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: mở link với UA -> device/browser/os đúng.

## Recovery / rollback
- Rollback: bỏ 2 class + revert redirect. Không đổi schema.

## Evidence
- LINT PASS (77 files); security scan PASS; verify-structure PASS.
- Full suite 113/113 PASS (`php tests/run-tests.php --all`): UserAgentParserTest,
  CountryLookupTest, smoke click_events device/browser/os + country null (IP local).
- E2E MySQL: mở `track-e2e` với UA Chrome/Windows -> device=desktop, browser=Chrome,
  os="Windows 10/11", country=NULL (127.0.0.1 private).
