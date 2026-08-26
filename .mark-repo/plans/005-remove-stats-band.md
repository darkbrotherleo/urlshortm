# Task: 005 Loại bỏ băng số liệu (stats band) khỏi trang chủ

## Objective
Gỡ phần "Bảng Số Liệu" (stats-band) khỏi trang chủ và dọn code phụ trợ không còn dùng.

## Scope / non-goals
- Trong: xoá section stats-band khỏi home, bỏ `totals` khỏi HomeController,
  xoá `UrlRepository::totals()` (dead code), xoá hiệu ứng đếm số + CSS stats.
- Ngoài: không đổi tracking panel live, không đổi `/stats/{slug}`, không đổi DB.

## Acceptance criteria
- AC-027: GET `/` không còn chứa "Bảng Số Liệu"/stats-band; các section khác giữ nguyên.
- AC-028: Toàn bộ suite vẫn PASS; không còn mã/query `totals` dùng trên trang chủ.

## Current behavior and evidence
- Trang chủ đang có section stats-band (tổng link/lượt mở/tình trạng). 68/68 PASS.

## Exact files / dependencies
- `app/View/home.php`, `app/Controller/HomeController.php`, `app/Container.php`,
  `app/Repository/UrlRepository.php`, `assets/js/app.js`, `assets/css/style.css`.

## Test-first action
- Smoke "GET / trả 200 có tool thật" giữ nguyên; thêm assert trang chủ không chứa
  `stats-band` / `link đã rút gọn`. Chạy trước -> đỏ, sau -> xanh.

## Implementation steps
1. Xoá section stats-band trong home.php + docblock `$totals`.
2. HomeController bỏ `totals` và tham số `UrlRepository` (cập nhật Container).
3. Xoá `UrlRepository::totals()`.
4. JS: bỏ block đếm số; CSS: bỏ khối stats + responsive.

## Review risks
- Đừng đụng tracking panel (đang dùng `data-stats-url`).
- `UrlRepository` vẫn được StatsController dùng -> giữ class.

## Verification commands
- `php tests/run-tests.php --all`
- `powershell scripts\lint.ps1`; smoke curl: `GET /` không còn "Bảng Số Liệu".

## Recovery / rollback
- Revert view/CSS/JS từ task 003; không đổi DB.

## Evidence
- Full suite 68/68 PASS (`php tests/run-tests.php --all`), có smoke assert
  trang chủ không còn `stats-band` / "link đã rút gọn".
- LINT PASS; curl MySQL: GET / 200, section stats-band và text số liệu đã gỡ,
  các section khác (Tính năng, Cách hoạt động, Theo dõi, FAQ, CTA) giữ nguyên.
- Đã xoá `UrlRepository::totals()` (dead code) + hiệu ứng đếm số + CSS stats.
