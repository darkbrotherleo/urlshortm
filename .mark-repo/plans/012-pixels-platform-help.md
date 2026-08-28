# Task: 012 Thiết lập Pixels — Platform + bảng + trang hướng dẫn Việt hoá

## Objective
Nâng cấp "Thiết lập Pixels": form Tạo Pixel gồm Select the Platform, Name of
Pixel, Pixel ID; bảng quản lý gồm Name | Platform | Value | Creation date | Action
(Xoá); tạo trang hướng dẫn lấy Pixel ID (Việt hoá từ bộ sưu tập help.switchy.io)
gắn vào note trong khung Tạo Pixel.

## Scope / non-goals
- Trong: cột platform + created_at trong pixels, form/table mới, trang
  `/tro-giup/pixel-id`.
- Ngoài: xác minh pixel, tích hợp theo dõi thật, QR cho pixel.

## Acceptance criteria
- AC-049: Form có platform/name/pixel id; bảng 5 cột; note link tới trang hướng dẫn.

## Current behavior and evidence
- Pixels tab cũ: form code+name, bảng 4 cột. 92/92 PASS (sau cải tiến).

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v7 platform), `app/Repository/PixelRepository.php`,
  `app/Controller/{SettingsController,DashboardController}.php`, `app/View/dashboard.php`,
  `tests/{unit/RouterTest.php,http/SmokeTest.php}`.
- Thêm: `app/Security/PixelPlatform.php`, `app/Controller/HelpController.php`,
  `app/View/help-pixel-id.php`.

## Test-first action
- RouterTest: GET /tro-giup/pixel-id -> help_pixel.
- SmokeTest: form có name="platform"/Name of Pixel/Pixel ID; bảng 5 cột; link
  hướng dẫn; trang help 200 chứa nội dung Việt.

## Implementation steps
1. Migration v7 (platform + seed + gán cho pixel cũ) + schema SQLite.
2. PixelPlatform; PixelRepository platform+created_at; SettingsController platform.
3. Dashboard pixels tab (form + bảng 5 cột + note link); HelpController + help page.
4. Test; E2E MySQL.

## Review risks
- Migrate v7 idempotent (guard column + gán platform cho NULL).
- Chú ý encoding khi sửa file test (tránh PowerShell Set-Content làm hỏng UTF-8).

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: tab pixels đủ form/bảng/link; /tro-giup/pixel-id 200.

## Recovery / rollback
- Rollback v7: DROP cột platform. Code revert.

## Evidence
- LINT PASS (67 files); security scan PASS; verify-structure PASS.
- Full suite 92/92 PASS (`php tests/run-tests.php --all`): droplist platform,
  Action Sửa/Xoá pixel, edit flow (đổi code/name/platform), bảng 5 cột, help 200.
- Đã xoá Pixel mặc định: bỏ seed, migrate xoá `pixels WHERE user_id IS NULL`;
  `findByUser` chỉ trả pixel của user; bảng/droplist không còn "Mặc định".
- E2E MySQL: bảng chỉ còn pixel user; droplist form link không còn pixel mặc định,
  vẫn có `site-pixel`.
