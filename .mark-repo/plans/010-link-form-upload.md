# Task: 010 Form tạo/sửa link — thumbnail upload + droplist pixels + toggle mật khẩu

## Objective
Nâng cấp form link: (1) Thumbnail dùng tải ảnh lên thay vì nhập link; (2) Pixel ID
dạng droplist có ô tick chọn nhiều; (3) Bỏ tick "Xoá mật khẩu", dùng toggle
bật/tắt để cài/xoá mật khẩu.

## Scope / non-goals
- Trong: upload thumbnail (validate MIME/size, tên ngẫu nhiên, chặn thực thi),
  bảng pixels + droplist, toggle password (password_enabled).
- Ngoài: quản trị pixels trong admin, resize/crop ảnh, xoá ảnh tự động theo retention.

## Acceptance criteria
- AC-044: Upload thumbnail hợp lệ -> lưu `/uploads/{random}.{ext}`; file sai/thiếu -> báo lỗi.
- AC-045: Droplist pixels có ô tick; chọn N -> lưu N pixel (JSON).
- AC-046: Toggle bật+pass -> đặt/đổi; bật+bỏ trống -> giữ; tắt -> xoá; không còn password_clear.

## Current behavior and evidence
- Form link cũ dùng thumbnail dạng URL, pixels dạng text, có tick xoá mật khẩu.
  89/89 PASS (sau cải tiến).

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v5 pixels + seed), `app/Container.php`,
  `app/Controller/LinkController.php`, `app/Service/LinkService.php`,
  `app/View/link-form.php`, `assets/js/app.js`, `assets/css/style.css`,
  `tests/{integration/LinkServiceTest.php,http/SmokeTest.php}`.
- Thêm: `app/Repository/PixelRepository.php`, thư mục `uploads/` + `.htaccess`.

## Test-first action
- LinkServiceTest: toggle bật giữ pass, tắt xoá pass.
- SmokeTest: form multipart + file input + droplist + không password_clear;
  POST multipart upload PNG + pixels -> DB thumbnail/pixels + file tồn tại.

## Implementation steps
1. Migration v5 (pixels + seed 6 mã); SQLite test schema.
2. PixelRepository; LinkController truyền pixels + xử lý upload thumbnail.
3. LinkService password theo toggle.
4. View: file input + preview, droplist pixels, toggle (hidden password_enabled).
5. JS droplist; CSS; test; E2E MySQL (curl -F).

## Review risks
- Upload: validate getimagesize, MIME allowlist, size ≤5MB, tên ngẫu nhiên,
  .htaccess chặn PHP trong uploads.
- Không giữ reader SQLite trước write trong test.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: `-F thumbnail=@x.png` -> 302, DB có `/uploads/...`, pixels JSON.

## Recovery / rollback
- Rollback v5: DROP pixels. Code revert form/service.

## Evidence
- LINT PASS (61 files); security scan PASS; verify-structure PASS.
- Full suite 89/89 PASS (`php tests/run-tests.php --all`): form multipart + file
  input + droplist pixels + không password_clear; upload PNG qua multipart ->
  DB thumbnail `/uploads/...` + pixels `["fb-pixel","ga4"]` + file tồn tại.
- Migrate v5 idempotent (2 lần): bảng pixels + seed 6 mã.
- E2E MySQL: form đủ multipart/file/droplist/toggle; upload PNG -> 302, thumbnail
  `/uploads/7f713c423ef6708c.png`, pixels `["fb-pixel","zalo-pixel"]`.
