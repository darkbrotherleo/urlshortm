# Task: 003 Landing bán dịch vụ — nội dung marketing + font Việt + thiết kế mềm

## Objective
Biến trang chủ thành landing bán dịch vụ tự nhiên: (1) viết lại toàn bộ nội dung
theo giọng marketing, loại bỏ ngôn từ AI và mọi thuật ngữ kỹ thuật hiển thị;
(2) đồng bộ font Lexend (variable, self-hosted, đủ subset tiếng Việt);
(3) thiết kế mềm mại, thân thiện kèm hiệu ứng nhẹ.

## Scope / non-goals
- Trong: nội dung mới cho header/home/notfound/error/footer, font self-host,
  CSS soft mới, JS hiệu ứng (reveal, đếm số), tracking/stats giữ nguyên.
- Ngoài: đăng nhập, đổi tên thương hiệu, đa ngôn ngữ, dashboard, thay backend.

## Acceptance criteria
- AC-017: Trang chủ không chứa thuật ngữ kỹ thuật (slug, relay, spec-sheet,
  utf8mb4, base62, prepared statements, PHP/MySQL, HTTP 301, API…).
- AC-018: Nội dung tự nhiên, giọng bán dịch vụ; không cụm từ đại trà kiểu AI
  ("giải pháp tối ưu", "trải nghiệm liền mạch").
- AC-019: Font đồng bộ một họ (Lexend), tiếng Việt có dấu render đúng (subset
  vietnamese được nạp local).
- AC-020: Thiết kế mềm: bo tròn, đổ bóng nhẹ, không khung cứng/spec-sheet;
  có hiệu ứng scroll reveal + đếm số; reduced motion được tôn trọng.
- AC-021: Chức năng cũ không vỡ: rút gọn, redirect 301, /stats, CSRF, 404 vẫn
  hoạt động; toàn bộ test PASS.

## Current behavior and evidence
- Landing v2 (spec-sheet) đã PASS 47/47. Cần thay view/assets/js/test copy.

## Exact files / dependencies
- Đổi: `app/View/{header,footer,home,notfound,error}.php`,
  `assets/css/style.css`, `assets/js/app.js`, `tests/http/SmokeTest.php`.
- Thêm: `assets/fonts/{lexend-latin,latin-ext,vietnamese}.woff2` (đã tải).
- Font: Lexend variable 300..700, subset latin/latin-ext/vietnamese, self-host
  (không phụ thuộc CDN khi deploy).

## Test-first action
- Cập nhật SmokeTest: vẫn trích CSRF theo `name="csrf_token"`; đổi assert nội dung
  sang chuỗi mới (headline, placeholder, tên section); thêm assert không chứa từ
  kỹ thuật (vd `utf8mb4`). Chạy để PASS.

## Implementation steps
1. CSS mới: tokens soft, @font-face Lexend (3 subset), component mềm, hiệu ứng.
2. JS: reveal-on-scroll + đếm số băng số liệu + giữ form/copy/poll stats.
3. Views: header/footer/home/notfound/error viết lại bằng nội dung marketing.
4. Cập nhật SmokeTest + chạy full suite; smoke E2E MySQL.

## Review risks
- Font không nạp được nếu quên subset vietnamese -> kiểm tra bytes + @font-face.
- Hiệu ứng phải tắt khi reduced motion.
- Không để lộ dev terms trong HTML/alt/aria-label.
- Băng số liệu vẫn phải là số thật từ DB, không fake.

## Verification commands
- `powershell scripts\lint.ps1`
- `php tests/run-tests.php --all`
- smoke: `curl -s /` kiểm tra nội dung + font; tạo link, mở, `GET /stats` JSON.

## Recovery / rollback
- Khôi phục view/assets từ task 002; không đổi schema. Font xoá được nếu cần.

## Evidence
- LINT PASS (39 files); security scan PASS; verify-structure PASS.
- Full suite 48/48 PASS (`php tests/run-tests.php --all`) gồm smoke
  "nội dung không lộ thuật ngữ kỹ thuật".
- E2E MySQL: GET / 200; POST /shorten sinh slug; GET /{slug} 301 tới target;
  GET /stats/{slug} trả `{"click_count":1,...}`.
- Font: 3 woff2 Lexend (latin/latin-ext/vietnamese) phục vụ HTTP 200; @font-face
  có unicode-range vietnamese; không còn `relay`/`spec-sheet` trong HTML.
