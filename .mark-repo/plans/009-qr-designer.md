# Task: 009 QR Designer cho link rút gọn

## Objective
Nút "QR" trong All Link mở modal thiết kế mã QR: bên trái chọn Shape style
(các chấm bên trong), Corner style (3 điểm chính), Shape color, Corner color;
bên phải khung preview JS live, lưu ý quét thử bằng điện thoại, 2 nút tải
"SVG" và "PNG".

## Scope / non-goals
- Trong: modal designer, render canvas + SVG tự viết, tải file, self-host
  qrcode-generator.
- Ngoài: logo giữa mã, gradient màu, khung đổi shape cổ điển (frame), in.

## Acceptance criteria
- AC-042: Click "QR" -> modal có Shape/Corner style + màu, preview live, lưu ý,
  nút tải SVG/PNG.
- QR render offline (self-host), không phụ thuộc dịch vụ ngoài.

## Current behavior and evidence
- QR cũ dùng ảnh ngoài (qrserver). 88/88 PASS.

## Exact files / dependencies
- Thêm: `assets/js/vendor/qrcode.js` (self-host), `assets/js/qr.js`.
- Đổi: `app/View/dashboard.php` (modal designer), `assets/js/app.js` (bỏ QR cũ),
  `assets/css/style.css`, `tests/http/SmokeTest.php`.

## Test-first action
- SmokeTest: All Link chứa đủ controls QR + lưu ý + nút tải; qrcode.js và qr.js
  phục vụ 200.

## Implementation steps
1. Tải qrcode-generator self-host.
2. qr.js: buildMatrix -> render canvas (PNG) + build SVG; shape/corner styles.
3. Modal designer trong dashboard (2 cột) + CSS.
4. Smoke + E2E MySQL.

## Review risks
- Module finder (3 góc) vẽ riêng màu/kiểu; dữ liệu khác dùng Shape.
- Canvas nhân scale cho nét.

## Verification commands
- `php tests/run-tests.php --all`; `powershell scripts\lint.ps1`.
- smoke curl MySQL: trang All Link chứa controls QR; assets 200.

## Recovery / rollback
- Bỏ modal + 2 file js; không đổi DB.

## Evidence
- LINT PASS (60 files); security scan PASS; verify-structure PASS.
- Full suite 88/88 PASS (`php tests/run-tests.php --all`), smoke: All Link chứa
  đủ controls QR (shape/corner style + màu, canvas, nút tải SVG/PNG) + lưu ý;
  `assets/js/vendor/qrcode.js` và `assets/js/qr.js` phục vụ 200.
- E2E MySQL: trang All Link hiển thị đầy đủ QR Designer; qr.js + qrcode.js 200.
