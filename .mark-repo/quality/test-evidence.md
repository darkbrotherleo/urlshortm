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
| Khung xem trước link | Preview live thumbnail/title/desc/url/type | PASS | 2026-08-26 | smoke markup + E2E MySQL (form đủ) |
| Cài đặt con | Pixels/Domain/UTM + tích hợp form link | PASS (91/91) | 2026-08-26 | smoke + E2E MySQL (site-pixel) |
| Pixels nâng cao | Platform + bảng 5 cột + trang hướng dẫn Việt | PASS (92/92) | 2026-08-26 | smoke + E2E MySQL (/tro-giup/pixel-id) |
| Pixels droplist + Sửa | Select platform styled + Edit/Delete pixel | PASS | 2026-08-26 | smoke edit flow + E2E MySQL |
| Xoá Pixel mặc định | Bỏ seed, xoá user_id NULL, chỉ pixel của user | PASS | 2026-08-26 | migrate + E2E MySQL (droplist không còn mặc định) |
| Xác minh domain | DNS TXT + localhost auto-verify + chỉ verified dùng được | PASS (92/92) | 2026-08-26 | smoke + E2E MySQL (localhost=1, fake=0) |
| Domain local .test | link.mark.test tự xác minh + vào select domain | PASS | 2026-08-26 | smoke + E2E MySQL (is_verified=1) |
| Hướng dẫn thêm tên miền | Guide 5 bước + trang /tro-giup/custom-domain | PASS (93/93) | 2026-08-26 | smoke + E2E MySQL |
| Wiki trợ giúp | Footer "Trợ giúp" + /tro-giup (6 mục) | PASS (94/94) | 2026-08-26 | smoke + E2E MySQL |
| Wiki tài liệu | /tro-giup: Mục lục + 13 bài viết nội dung thật | PASS (94/94) | 2026-08-26 | smoke + E2E MySQL |
| UTMs tracking | Form thu hẹp + khung giải thích UTM bên phải | PASS (94/94) | 2026-08-27 | smoke + E2E MySQL (utm-layout/utm-help) |
| UTM tích hợp redirect | append_utm nối utm_* vào URL đích | PASS (99/99) | 2026-08-27 | unit + smoke + E2E MySQL (e2e-utm?utm_...) |
| GĐ0 tracking | click_events mỗi redirect (ip_hash/UA/referrer/user) | PASS (102/102) | 2026-08-27 | smoke + E2E MySQL (track-e2e) |
| GĐ1 làm giàu | device/browser/os (UA) + country (GeoIP) | PASS (113/113) | 2026-08-27 | unit + smoke + E2E MySQL (desktop/Chrome/Windows) |
| GĐ2 Báo cáo | Tab Báo cáo: summary + Chart.js đa chiều + filter | PASS (114/114) | 2026-08-27 | integration + smoke + E2E MySQL (gauge 5) |
| Responsive dashboard | Sidebar->top bar ≤1024, mobile ≤640 (table scroll, compact) | PASS | 2026-08-27 | CSS + E2E MySQL |
| Menu nút bấm | Sidebar thành drawer mở khi bấm (bar + overlay + ESC) | PASS (114/114) | 2026-08-27 | CSS/JS + E2E MySQL |
| Menu theo kích thước | PC: sidebar cố định (≥1025px); Mobile/Tablet: nút ☰ + drawer | PASS (114/114) | 2026-08-27 | CSS + E2E MySQL |
| GĐ3 Báo cáo nâng cao | Chi tiết lượt mở (phân trang) + Export CSV | PASS (114/114) | 2026-08-27 | integration + smoke + E2E MySQL |
| GĐ4 Nhân khẩu học (Meta) | Cấu hình Meta + fetch age/gender + snapshot + bảng/biểu đồ | PASS (119/119) | 2026-08-27 | unit (fake fetcher) + integration (repos) + smoke (tab/save/masked token) + E2E MySQL (2 bảng mới) |
| Lưu IP thật | click_events.ip_address (không mã hoá, toggle store_raw_ip) + cột IP trong bảng/CSV | PASS (119/119) | 2026-08-27 | integration (reportEvents trả IP) + smoke (lưu 127.0.0.1, <th>IP</th>, CSV) + E2E MySQL (mở link -> ip_address=127.0.0.1) |
| Tài khoản: đổi mật khẩu + vô hiệu hoá | Form đổi mật khẩu + soft delete (status=disabled, không xoá dữ liệu); login từ chối | PASS (124/124) | 2026-08-27 | unit (changePassword/deactivate/ACCOUNT_DISABLED) + smoke (đổi pass, disabled=1, login 400) |
| Hồ sơ user + hoá đơn | Cài đặt: phone/address/city + tax_type/company_name/tax_id/invoice_name (MST 10-14 số); hiển thị ở Tài khoản | PASS (126/126) | 2026-08-27 | unit (updateProfile round-trip) + smoke (lưu hồ sơ, MST sai -> error, giữ giá trị) + E2E MySQL (v12 columns) |
| ADMIN local | admins (v13) + seed admin; /admin/dang-nhap + /admin; đăng nhập → trang quản trị, logout chặn | PASS (132/132) | 2026-08-27 | unit (AdminAuth) + smoke (redirect chưa đăng nhập, login sai 401, đúng 302, dashboard 13 nav, logout) + E2E MySQL (darkbrotherleo login -> /admin) |
| Admin: Quản lý người dùng | /admin/users bảng (Username|Email|Gói|Ngày mua|Ngày hết hạn|Trạng thái) + modal info + sửa full/gói/trạng thái | PASS (138/138) | 2026-08-27 | integration (findAllForAdmin/setSubscription/setStatus) + smoke (list+modal+update, email trùng -> error) + E2E MySQL (Starter badge + 6 rows) |
| Admin: Quản lý gói dịch vụ | plans mở rộng (v14) + CRUD /admin/packages: list/search/paginate, form new/edit (giới hạn -1, cờ tính năng, auto-slug), toggle, delete chặn khi đang dùng | PASS (143/143) | 2026-08-27 | integration (PackageRepository CRUD/toggle/count-sub) + smoke (thêm/sửa/toggle/xoá chặn) + E2E MySQL (4 gói VND: free/starter/pro/team) |
| Tích hợp gói vào user | UserPlanService + chặn tạo link/pixel/domain/click tháng theo giới hạn gói; badge gói + panel Gói & giới hạn ở Tài khoản | PASS (150/150) | 2026-08-27 | integration (planOf/canCreateLink/canClick/limits/LinkService chặn) + smoke (panel gói + Miễn phí) + E2E MySQL (user mới thấy 0/20 link, 0/5 domain) |
| Tài khoản mới gắn gói Free | register tự tạo subscription Free (nếu có plan free) | PASS (152/152) | 2026-08-27 | integration (register tạo sub free / không free thì bỏ qua) + E2E MySQL (user mới có sub Miễn phí active) |
| Đơn hàng + Thanh toán + Hoá đơn | Cổng thanh toán admin (PayPal sandbox/live + mock), /thanh-toan (chọn gói/checkout), pay -> success kích hoạt gói, hoá đơn GTGT chuẩn VN (in/xuất chữ) | PASS (159/159) | 2026-08-27 | integration (OrderRepository + PayPalService fake) + smoke (mua mock -> success -> hoá đơn -> sub active -> cổng thanh toán) + E2E MySQL (order paid 149k + sub Starter active) |
| Bảng giá | /bang-gia public: 4 gói + badge phổ biến + nút Mua ngay -> /thanh-toan?plan=X; menu header "Bảng giá" | PASS (160/160) | 2026-08-27 | smoke (public + nút + link) + RouterTest + E2E MySQL (render 4 gói) |
| Admin Quản lý Link | /admin/links (User|URL|URL Short|Time Create|Time End|Click|QR|Action Sửa/Vô hiệu) + tìm username/slug + tự xoá link khách 15 ngày + chặn link vô hiệu | PASS (166/166) | 2026-08-27 | integration (findAllForAdmin/cleanup/toggle/update) + smoke (bảng + cleanup + vô hiệu 410 + sửa) + E2E MySQL |
| Quản lý Voucher + áp dụng | /admin/vouchers (bảng + modal tạo/sửa + Chạy/Ngừng) + áp voucher checkout (giảm %/tiền, per_user, giới hạn, ghi nhận usage) | PASS (171/171) | 2026-08-27 | integration (VoucherRepository/VoucherService) + smoke (tạo -> áp 10% 149k->134,1k -> đơn/usage/used_count -> ngừng) + E2E MySQL |
| Admin Quản lý Domain | Domain Hệ Thống (thêm/mặc định/bật-tắt/xoá, link dùng domain mặc định) + Domain của Users (bảng + Số lượng + Trạng thái + Tạm dừng/Xoá) | PASS (174/174) | 2026-08-27 | integration (DomainRepository sys+user) + smoke (add default + tạm dừng + link dùng urlshortm.test) + E2E MySQL |
| URL hệ thống tự nhận diện | /admin/settings (system_url) -> base_url dùng domain cấu hình, chuẩn hoá https/http .test | PASS (175/175) | 2026-08-27 | smoke (save -> landing tự dùng domain) + RouterTest + E2E MySQL (urlshortm.com tự áp dụng) |
| Tự nhận diện domain + Thông tin website | Bỏ system_url; base_url tự dùng host đang chạy; Cài đặt Website -> sub "Thông tin website" (read-only host/scheme/base) | PASS (175/175) | 2026-08-27 | smoke (page + host hiện tại) + RouterTest + E2E MySQL |
| Cài đặt Website (6 submenu) | Hệ thống read-only, Website, Hoá đơn, SMTP, Media (định dạng/nén/chuyển đổi/quản lý) + SEO (inject meta/GA4/custom code) | PASS (178/178) | 2026-08-27 | integration (SiteSettings/ImageProcessor) + smoke (6 submenu + lưu SEO -> inject head) + E2E MySQL |
| Fix Cài đặt Website | img media/logo tuyệt đối, Media/SEO compact, Robots Meta select, og value, sitemap.xml + robots.txt tự tạo | PASS (179/179) | 2026-08-27 | smoke (sitemap/robots + robots select + inject noindex) + E2E MySQL |
| SEO áp dụng website | Áp dụng toàn bộ thiết lập SEO: site title, meta, OG, verification, GA4/GTM/pixel/tiktok/indexnow, custom code, hreflang, AI meta + robots.txt tuỳ chỉnh | PASS (180/180) | 2026-08-27 | smoke (30 tag inject verify) + lint/security |
| Hoá đơn dùng cài đặt | Người bán trên hoá đơn (tên/loại/MST/địa chỉ/điện thoại) lấy từ Cài đặt website -> Hoá đơn | PASS (181/181) | 2026-08-27 | smoke (admin set -> mua -> invoice hiển thị) + E2E MySQL |
| Email SMTP + gửi thử | Mailer (STARTTLS/SSL, AUTH LOGIN) + nút Gửi thử email (test_to/subject/body), lỗi rõ khi chưa cấu hình | PASS (183/183) | 2026-08-27 | integration (isConfigured + send chưa cấu hình) + smoke (form + error path) + E2E MySQL |
| Email Template | Khung HTML + 5 template (mua thành công/hoá đơn/đăng ký/lấy lại mật khẩu/kích hoạt) + /admin/emails preview & gửi thử + áp dụng gửi email thật (mua, hoá đơn, chào mừng) | PASS (185/185) | 2026-08-27 | unit (render 5 template) + smoke (trang/preview/gửi thử) + lint/security |
| Admin đơn hàng | /admin/orders (danh sách + search/status/paginate + modal chi tiết + cập nhật trạng thái -> paid kích hoạt gói) + /admin/payment-history (lịch sử thanh toán, payer/paid_at) | PASS (162/162) | 2026-08-27 | integration (findAllForAdmin filter/count) + smoke (list/history/status update + sub active) + E2E MySQL (render cả 2 trang) |
| Migration v4 | short_links metadata + password + thời gian | PASS | 2026-08-26 | idempotent 2 lần |
| Stats API | `GET /stats/{slug}` JSON; 404 lạ; 400 sai format | PASS | 2026-08-25 | smoke + curl `{"click_count":2}` |
| Security | CSRF 403, chặn /app, guard config, rate limit 429 | PASS | 2026-08-25 | qua http smoke + test đơn vị |
| UX/accessibility | `<details>` FAQ, focus-visible, reduced-motion, aria-live | PASS (manual) | 2026-08-25 | chưa có test visual tự động |
| Performance | lookup theo UNIQUE(slug); không N+1; không đo LCP | n/a | 2026-08-25 | v1 nhẹ, không đo benchmark |
| UAT | luồng tạo link -> mở 301 -> click đếm trên MySQL thật | PASS | 2026-08-25 | `mysql -e "SELECT ... click_count"` = 2 |
| Migration | `php database/migrate.php` (MySQL, idempotent) | PASS | 2026-08-25 | tạo db + 2 bảng |
| Kích hoạt tài khoản + quên mật khẩu (v20) | register -> PENDING + token 24h + email (KHÔNG tự login); /kich-hoat kích hoạt + tự login; login pending bị từ chối; /quen-mat-khau -> reset token 30 phút -> /dat-lai-mat-khau đổi mật khẩu, token dùng 1 lần, hết hạn 400 | PASS (187/187) | 2026-08-28 | integration (register PENDING/login pending bị chặn/activate) + smoke (quên mật khẩu -> reset -> login mật khẩu mới + expired token) + RouterTest + lint/security + E2E MySQL (users.status enum + 4 cột token) |