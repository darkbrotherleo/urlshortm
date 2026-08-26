# Acceptance specification

Mỗi tiêu chí phải quan sát hoặc đo được.

| ID | Journey/requirement | Given | When | Then | Evidence |
|---|---|---|---|---|---|
| AC-001 | Trang chủ | Mở GET `/` | Trả về HTML 200 | Hiện form nhập URL, có label, đúng UTF-8 | automated (router test) + manual |
| AC-002 | Tạo link hợp lệ | GET `/` rồi POST `/shorten` với `target=https://example.com/a/b` | Submit | Nhận link ngắn `{base}/{slug}` slug đúng `[0-9a-zA-Z]{6}`, target lưu nguyên | automated + manual |
| AC-003 | Tự thêm scheme | POST với `target=example.com/x` | Submit | Chuẩn hoá thành `https://example.com/x`, tạo link thành công | automated |
| AC-004 | Từ chối URL sai | POST với `target=javascript:alert(1)` hoặc `ftp://x` hoặc chuỗi rác | Submit | Lỗi inline tiếng Việt, không tạo record | automated + manual |
| AC-005 | Redirect | Tạo link với `{slug}`, sau đó GET `/{slug}` | Mở slug | HTTP 301 + `Location` trỏ đúng target | automated (smoke HTTP) |
| AC-006 | Đếm click | Tạo link, mở `/{slug}` 3 lần | Sau 3 lần | click_count = 3, không giảm khi mở lại | automated (integration) |
| AC-007 | 404 | GET `/khongTonTai123` | Mở slug lạ | HTTP 404, trang HTML thân thiện | automated + manual |
| AC-008 | CSRF | POST `/shorten` không có token | Submit | 403, không tạo record | automated |
| AC-009 | Rate limit | Gửi > 50 POST `/shorten` từ cùng IP trong 1 giờ | Vượt ngưỡng | 429, message tiếng Việt, không tạo thêm | automated (integration) |
| AC-010 | UTF-8 | Nhập target chứa ký tự tiếng Việt/Unicode | Tạo + mở | Redirect giữ nguyên URL, không bị hỏng encoding | automated |
| AC-011 | Error 500 an toàn | DB down | Bất kỳ request | 500 không lộ stack/query/SQL | automated (unit) |
| AC-012 | Landing trang chủ | Mở GET `/` | Render | 200, tool rút gọn thật trong hero, không gradient xanh/tím/emoji/stock image | automated + manual |
| AC-013 | Tracking panel live | Tạo link rồi mở link 1 lần | Sau poll | Số click trong tracking panel tăng theo dữ liệu thật | automated (http smoke) |
| AC-015 | `/stats/{slug}` | GET với slug đúng | - | 200 JSON `{slug,click_count,created_at}`; slug lạ 404; sai định dạng 400 | automated |
| AC-016 | Accessibility | Tab qua toàn trang | - | focus visible rõ; FAQ dùng `<details>`; reduced motion được tôn trọng | manual |
| AC-017 | Không lộ thuật ngữ kỹ thuật | GET `/` | Render | Không chứa slug, relay, spec-sheet, utf8mb4, base62, prepared statement, HTTP 301, API… | automated (smoke) |
| AC-018 | Nội dung marketing tự nhiên | GET `/` | Render | Giọng bán dịch vụ, không cụm từ đại trà kiểu AI ("giải pháp tối ưu", "trải nghiệm liền mạch") | manual + smoke |
| AC-019 | Font tiếng Việt | Load CSS + font | - | Lexend self-host (latin/latin-ext/vietnamese) nạp được; dấu tiếng Việt render đúng | automated (smoke http 200) + manual |
| AC-020 | Thiết kế mềm + hiệu ứng | GET `/` | - | Bo tròn, đổ bóng nhẹ, reveal khi cuộn; reduced motion tắt hiệu ứng | manual + code review |
| AC-021 | Chức năng cũ không vỡ | Toàn bộ suite | - | Rút gọn/301/stats/CSRF/404 vẫn hoạt động, 48/48 test PASS | automated |
| AC-022 | Trang đăng ký/đăng nhập | GET `/dang-ky`, `/dang-nhap` | - | 200, layout marketing hiện đại, form đủ field | automated (smoke) |
| AC-023 | Đăng ký hợp lệ | POST `/dang-ky` với CSRF | Submit | 302 về `/`, đã đăng nhập; email trùng 409; mật khẩu < 8 -> 400; thiếu CSRF 403; quá 10/IP/giờ 429 | automated (unit + smoke) |
| AC-024 | Đăng nhập/Đăng xuất | POST `/dang-nhap` / `/dang-xuat` | Submit | Đúng -> 302 `/`; sai -> 401 message chung; logout -> 302 + hết session | automated |
| AC-025 | Gán link về user | Đăng nhập rồi tạo link | - | `short_links.user_id` = id user; link ẩn danh = NULL | automated (smoke + MySQL) |
| AC-026 | Migrate v2 | `php database/migrate.php` chạy 2 lần | - | Tạo users/plans/user_subscriptions + cột user_id + seed 3 gói, idempotent | automated (MySQL) |
| AC-029 | Dashboard yêu cầu đăng nhập | GET `/dashboard` khi chưa đăng nhập | - | 302 về `/dang-nhap` | automated (smoke) |
| AC-030 | Dashboard layout + tab active | GET `/dashboard?tab=X` đã đăng nhập | - | Sidebar trái + nội dung phải; tab X có `is-active`/`aria-current`; nội dung đổi đúng tab | automated (smoke) |
| AC-031 | Link của user | Đăng nhập, tạo link, mở `?tab=links` | - | Link mới hiển thị (slug, lượt mở, ngày, copy) | automated (smoke) + MySQL |
| AC-033 | Menu phân cấp | Sidebar đã đăng nhập | - | Nhóm "Quản lý link" có "All Link" + "Folder"; mục đang chọn active | automated (smoke) |
| AC-034 | Thư mục | POST create/delete folder + CSRF | Submit | Tạo -> hiển thị trong tab Folder; xoá -> link về "Không thư mục" | automated (smoke) + MySQL |
| AC-035 | Gán link vào thư mục | POST link-folder + CSRF | Submit | Mở folder thấy link; scope đúng user sở hữu | automated (smoke) |
| AC-036 | Cài đặt | POST /dashboard/settings | Submit | Đổi tên hiển thị thành công, hiển thị ngay | automated (smoke) |
| AC-037 | All Link quản lý | GET `?tab=links` | - | Có nút "Tạo Link Mới"; bảng tick/title/clicks/date/pixels/url/url short/action; tick chọn -> bulk bar | automated (smoke) |
| AC-038 | Tạo link nâng cao | POST /dashboard/link | Submit | Lưu đủ link_type/title/description/thumbnail/pixels/utm_*/custom_slug/folder/password/thời gian | automated (smoke + unit) |
| AC-039 | Custom slug | Tạo với custom_slug | - | Slug 3-16 `[0-9a-zA-Z-_]`, trùng/reserved -> lỗi | automated (unit) |
| AC-040 | Link bảo vệ + thời gian | GET /{slug} | - | Có password -> form nhập, unlock đúng -> redirect; ngoài thời gian -> 410 | automated (smoke) |
| AC-041 | Sửa/xoá/bulk | POST update/delete/bulk | Submit | Sửa cập nhật, xoá link, xoá/chuyển hàng loạt hoạt động | automated (smoke) |
| AC-042 | QR Designer | Click "QR" trong All Link | - | Modal có Shape style/Corner style/Shape color/Corner color; preview JS live; lưu ý quét thử; nút tải SVG + PNG | automated (markup) + manual |
| AC-043 | Share popup | Click "Share" trong All Link | - | Popup nhỏ với Facebook/Linkedin/X/Messenger/Zalo; click mở share modal của trang kèm tiêu đề link | automated (markup) + manual |
| AC-044 | Thumbnail upload | Form tạo/sửa link | - | Upload ảnh (JPG/PNG/WEBP/GIF ≤5MB), validate MIME, tên ngẫu nhiên, lưu `/uploads/...`, chặn thực thi | automated (smoke) + MySQL |
| AC-045 | Pixel droplist | Form tạo/sửa link | - | Droplist liệt kê Pixel ID kèm ô tick; chọn bao nhiêu thì lưu bấy nhiêu | automated (smoke) + MySQL |
| AC-046 | Bảo vệ link = toggle | Form tạo/sửa link | - | Bật + nhập pass -> đặt; bật + bỏ trống -> giữ; tắt -> xoá mật khẩu; không còn tick "Xoá mật khẩu" | automated (unit) |

## Global gates

- Không còn BLOCKER/HIGH finding.
- Focused tests và full suite PASS.
- Security/privacy/accessibility/performance gate áp dụng đã PASS.
- Migration, rollback, backup/restore và deployment được kiểm tra khi liên quan.