# Task: 008 Quản lý link nâng cao (All Link + Tạo Link Mới + bảo vệ link)

## Objective
Nâng cấp tab "All Link": nút "Tạo Link Mới", bảng quản lý (tick hàng loạt, Title,
Clicks, Date, Pixels, Url, Url Short, Action: Copy/Share/QR/Edit/Delete), bulk
(xoá/chuyển thư mục). Trang tạo/sửa link với: loại link, địa chỉ, custom link
(thumbnail/title/description), pixels, UTM tags, domain + back-half, folder,
password (toggle), thời gian bắt đầu/kết thúc. Redirect hỗ trợ password + thời gian.

## Scope / non-goals
- Trong: CRUD link mở rộng, bulk, QR/share/copy, password gate + unlock, time
  window, slug custom 3-16, migration v4.
- Ngoài: thiết lập pixels (admin), danh sách domain từ admin, analytics biểu đồ,
  vCard tạo file thật.

## Acceptance criteria
- AC-037: All Link có nút "Tạo Link Mới"; bảng gồm tick/title/clicks/date/pixels/
  url/url short/action; tick chọn -> bulk bar (xoá, chuyển thư mục).
- AC-038: Tạo link với đầy đủ trường; lưu được link_type/title/description/
  thumbnail/pixels/utm_*/domain/custom_slug/folder/password/thời gian.
- AC-039: Link custom slug (3-16, `[0-9a-zA-Z-_]`), không trùng, không reserved.
- AC-040: Link có mật khẩu -> GET /{slug} hiện form nhập; unlock đúng -> redirect,
  sai -> lỗi; link ngoài khung thời gian -> 410 + thông báo.
- AC-041: Sửa link (prefill, cập nhật), xoá link, xoá/chuyển hàng loạt hoạt động.

## Current behavior and evidence
- Dashboard v7 (folder/settings) 88/88 PASS trước task này.

## Exact files / dependencies
- Đổi: `database/migrate.php`+`schema.sql` (v4), `app/Repository/UrlRepository.php`,
  `app/Controller/{RedirectController,StatsController,LinkController}.php`,
  `app/Router.php`, `app/Container.php`, `index.php`, `app/View/dashboard.php`,
  `assets/css/style.css`, `assets/js/app.js`, tests.
- Thêm: `app/Security/{LinkType,SlugValidator}.php`,
  `app/Service/{LinkService,LinkValidationException}.php`,
  `app/View/{link-form,link-password,link-expired}.php`.

## Test-first action
- LinkTypeTest (build destination theo loại), LinkServiceTest (metadata/custom
  slug/password/thời gian/delete scope), RouterTest (link routes + unlock + slug
  3-16), SmokeTest (form đủ trường, tạo -> bảng, password unlock, sửa, xoá, hết hạn).

## Implementation steps
1. Migration v4 + schema SQLite test.
2. LinkType + SlugValidator + LinkService + UrlRepository CRUD mở rộng.
3. LinkController (create/edit/delete/bulk) + routes + container.
4. RedirectController password + time window + unlock.
5. View link-form (đầy đủ field) + link-password + link-expired + dashboard All Link.
6. CSS/JS (bulk, QR, share, toggle, slug preview); test; E2E MySQL.

## Review risks
- Mọi thao tác link scope theo user_id.
- Không mở reader SQLite trước write trong test.
- QR dùng dịch vụ ngoài (qrserver.com) chỉ cho hiển thị client-side.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: tạo link WhatsApp -> redirect wa.me; custom slug; bảng hiển thị.

## Recovery / rollback
- Rollback v4: DROP cột mới. Code revert views/controller.

## Evidence
- LINT PASS (60 files); security scan PASS; verify-structure PASS.
- Full suite 88/88 PASS (`php tests/run-tests.php --all`): LinkType, LinkService,
  Router (link routes/unlock/slug 3-16), smoke link manager
  (tạo -> bảng -> password unlock -> sửa -> xoá -> hết hạn 410).
- Migrate v4 idempotent (2 lần) trên MySQL: link_type/title/description/thumbnail/
  pixels/utm_*/domain/password_hash/starts_at/ends_at/updated_at.
- E2E MySQL: login -> form tạo đủ trường; tạo link WhatsApp `0912 345 678` ->
  redirect `https://wa.me/0912345678`; title + custom slug `wa-ban` hiển thị trong
  bảng All Link.
