# Task: 002 Landing page bán dịch vụ rút gọn + tracking link

## Objective
Biến trang chủ thành landing page độc đáo (art direction “spec-sheet / relay
station”), tool rút gọn vẫn chạy thật ngay trong hero, kèm tracking panel đọc số
click thật qua endpoint `/stats/{slug}` và ticker số liệu tổng thật từ DB.

## Scope / non-goals
- Trong: hero tool thật, tracking panel live, ticker tổng thật, Cách dùng, Theo dõi,
  FAQ, 404/500 đồng bộ style, endpoint `/stats/{slug}`, CSS/JS mới, test.
- Ngoài: đăng nhập, custom slug, dashboard link cá nhân, biểu đồ nhiều ngày, i18n.

## Acceptance criteria
- AC-012: GET `/` trả landing 200, có tool thật (form rút gọn hoạt động).
- AC-013: sau khi tạo link, tracking panel hiển thị số click thật và tự tăng khi
  mở link; poll `GET /stats/{slug}` JSON đúng contract.
- AC-014: ticker hiển thị tổng link + tổng click từ DB (không số giả).
- AC-015: `/stats/{slug}` trả JSON đúng; slug lạ 404; slug sai định dạng 400.
- AC-016: trang không chứa gradient xanh/tím, icon emoji, stock image; tôn trọng
  reduced motion; keyboard/contrast đạt (theo specs/ux.md).

## Current behavior and evidence
- Trang chủ hiện tại: form trung tâm đơn giản (v1). 44/44 test PASS. Cần thay
  view + assets + thêm endpoint stats + `UrlRepository::totals()`.

## Exact files / dependencies
- Đổi: `app/View/{header,footer,home,notfound,error}.php`, `assets/css/style.css`,
  `assets/js/app.js`, `app/Router.php`, `index.php`, `app/Container.php`,
  `app/Controller/HomeController.php`, `app/Repository/UrlRepository.php`.
- Thêm: `app/Controller/StatsController.php`.
- Test: `tests/unit/RouterTest.php`, `tests/http/SmokeTest.php`.

## Test-first action
- RouterTest: `GET /stats/{slug}` -> stats; reserved `stats`; slug lạ -> 404.
- SmokeTest: cập nhật copy trang chủ; POST rút gọn -> trích slug -> `GET
  /stats/{slug}` JSON có click_count >= 1 sau khi mở link; slug lạ stats -> 404.
- Chạy trước khi đổi view để chắc chắn đỏ -> sau đó xanh.

## Implementation steps
1. `UrlRepository::totals()` (COUNT + SUM); `StatsController::show()` JSON.
2. Router thêm route `/stats/{slug}` + reserved `stats`; Container + index.php wire.
3. Views mới theo art direction; hero form vẫn dùng POST /shorten + CSRF.
4. style.css mới (tokens paper/ink/orange, serif+mono); app.js: submit/loading,
   copy, poll stats mỗi 3s (dừng khi slug không còn trên trang).
5. Cập nhật test + chạy PASS; smoke E2E MySQL.

## Review risks
- Poll `/stats` mỗi 3s => nhẹ (read-only), nhưng tránh poll khi không có slug.
- Không fake số liệu: ribbon phải đọc DB; tracking phải đọc click_count thật.
- Xung đột route: `stats` (5 ký tự) thêm vào RESERVED để không thành slug.
- Base URL trong JS: dùng data-attribute sinh từ server, không nối chuỗi tay.

## Verification commands
- `powershell scripts\lint.ps1`
- `php tests/run-tests.php --all`
- `php database/migrate.php` + smoke curl trên MySQL: GET /, POST /shorten,
  GET /{slug} 301, GET /stats/{slug} JSON.

## Recovery / rollback
- Git không dùng; giữ bản cũ của view/assets trong đầu: khôi phục = revert nội dung
  từ task 001 (đã ghi trong plan). Endpoint mới không đổi schema, không cần rollback DB.

## Evidence
- LINT PASS (39 files); security scan PASS; verify-structure PASS.
- Full suite 47/47 PASS (`php tests/run-tests.php --all`) gồm 9 http smoke
  (landing 200, CSRF 403, luồng tạo link + 301 + stats JSON, stats 404/400,
  404, Unicode, 400, CSS, chặn thư mục nội bộ).
- E2E MySQL: GET / 200; ticker hiển thị tổng thật (4 link / 5 click);
  POST /shorten -> tracking panel kèm `data-stats-url`; GET /stats/GMzDJI
  trả `{"slug":"GMzDJI","click_count":2,...}`.
