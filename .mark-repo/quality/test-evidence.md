# Test evidence

| Gate | Command/scenario | Result | Date | Notes |
|---|---|---|---|---|
| Focused | `php tests/run-tests.php` | PASS (36/36) | 2026-08-25 | unit + integration trên SQLite |
| Integration | `php tests/run-tests.php` | PASS | 2026-08-25 | repository/service trên SQLite; upsert rate limit |
| Full suite | `php tests/run-tests.php --all` | PASS (44/44) | 2026-08-25 | v1 cũ |
| Full suite | `php tests/run-tests.php --all` | PASS (47/47) | 2026-08-25 | landing v2: 9 http smoke mới |
| Landing | GET / 200 có tool thật, ticker số thật | PASS | 2026-08-25 | smoke + curl MySQL (4 link / 5 click) |
| Landing v3 | Nội dung marketing + không thuật ngữ kỹ thuật | PASS (48/48) | 2026-08-25 | smoke assert dev-terms vắng mặt |
| Font | Lexend self-host 3 subset HTTP 200, @font-face vietnamese | PASS | 2026-08-25 | curl status 200; wOF2 magic bytes |
| Thiết kế mềm + hiệu ứng | Bo tròn, shadow, reveal, đếm số, reduced-motion | PASS (manual) | 2026-08-25 | chưa có test visual tự động |
| Auth v4 | Full suite 68/68 (3 lần ổn định) | PASS | 2026-08-25 | đăng ký/đăng nhập/đăng xuất/gán user_id |
| Auth | E2E MySQL: register/login/logout 302, link gắn user_id | PASS | 2026-08-25 | `IPvzhx` -> user_id=1 (testuser@vidu.vn) |
| Migration v2 | users/plans/user_subscriptions + user_id + seed 3 gói | PASS | 2026-08-25 | idempotent 2 lần, UTF-8 đúng |
| Dashboard | Full suite 70/70; guest 302, tab active, link user hiển thị | PASS | 2026-08-25 | smoke + E2E MySQL (IPvzhx) |
| Menu/Folder/Settings | Full suite 72/72; sidebar phân cấp, folder CRUD, gán link, đổi tên | PASS | 2026-08-26 | smoke + E2E MySQL (Marketing) |
| Link Manager | Full suite 88/88; CRUD, password gate, time window, bulk, QR/share | PASS | 2026-08-26 | smoke + E2E MySQL (wa-ban -> wa.me) |
| QR Designer | Modal shape/corner/màu, preview live, tải SVG/PNG, self-host qrcode | PASS | 2026-08-26 | smoke markup + E2E MySQL (controls + assets 200) |
| Share popup | Popup FB/Linkedin/X/Messenger/Zalo + data-title | PASS | 2026-08-26 | smoke + E2E MySQL (Whatsapp Ban) |
| Link form nâng cao | Thumbnail upload, droplist pixels, toggle mật khẩu | PASS (89/89) | 2026-08-26 | smoke multipart + E2E MySQL (png -> /uploads) |
| Migration v4 | short_links metadata + password + thời gian | PASS | 2026-08-26 | idempotent 2 lần |
| Stats API | `GET /stats/{slug}` JSON; 404 lạ; 400 sai format | PASS | 2026-08-25 | smoke + curl `{"click_count":2}` |
| Security | CSRF 403, chặn /app, guard config, rate limit 429 | PASS | 2026-08-25 | qua http smoke + test đơn vị |
| UX/accessibility | `<details>` FAQ, focus-visible, reduced-motion, aria-live | PASS (manual) | 2026-08-25 | chưa có test visual tự động |
| Performance | lookup theo UNIQUE(slug); không N+1; không đo LCP | n/a | 2026-08-25 | v1 nhẹ, không đo benchmark |
| UAT | luồng tạo link -> mở 301 -> click đếm trên MySQL thật | PASS | 2026-08-25 | `mysql -e "SELECT ... click_count"` = 2 |
| Migration | `php database/migrate.php` (MySQL, idempotent) | PASS | 2026-08-25 | tạo db + 2 bảng |