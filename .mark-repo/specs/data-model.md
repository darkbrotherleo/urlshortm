# Data model specification

| Entity | Owner | Sensitive fields | Invariants | Retention |
|---|---|---|---|---|
| short_links | app | target_url (public, không PII) | slug unique, slug regex `[0-9a-zA-Z]{6}`, target_url http(s) sau chuẩn hoá, user_id NULL = link ẩn danh | vĩnh viễn (chưa có xoá v1) |
| rate_limits | app | ip_hash (hash SHA-256, không phải IP thô) | unique (ip_hash, bucket_key, window_start), count >= 0 | xoá window cũ > 24h khi migrate |
| users | app (user tự tạo) | email (PII), password_hash, phone (PII), address (PII), tax_id (PII) | email unique + chuẩn form, password_hash dùng bcrypt/argon2, status ∈ {pending, active, disabled} (pending = chờ kích hoạt qua email, disabled = soft delete/hide, KHÔNG xoá dòng dữ liệu); activation_token + activation_expires_at (24h); reset_token + reset_expires_at (30 phút, dùng 1 lần); tax_type ∈ {NULL, individual, business}; tax_id 10-14 chữ số | khi user yêu cầu xoá hoặc chính sách — xoá thật tay bởi admin nếu cần |
| plans | app (catalog bán gói) | - | code unique (slug), price >= 0, billing_period ∈ {monthly,yearly,lifetime}, max_* (int, -1 = không giới hạn), cờ tính năng has_*, is_popular (label "Được chọn nhiều"), features JSON theo allowlist | giữ catalog; xoá phải check user_subscriptions đang dùng |
| user_subscriptions | app | - | unique active subscription mỗi user (một gói hiệu lực tại một thời điểm), FK user/plan | giữ lịch sử, xoá theo user |
| pixels | app (danh sách Pixel ID cho droplist) | - | code unique, is_active ∈ {0,1} | giữ catalog |
| uploads/ | app (thumbnail) | - | chỉ ảnh (JPG/PNG/WEBP/GIF), tên ngẫu nhiên, .htaccess chặn thực thi | theo chính sách lưu trữ |
| click_events | app (tracking GĐ0) | ip_hash (SHA-256 + salt, không phải IP thô); ip_address (IP thật, PII — chỉ lưu khi có quyền thu thập, bật qua `tracking.store_raw_ip`, mặc định true) | 1 dòng mỗi lần redirect; link_id FK cascade; user_id nullable (link ẩn danh) | theo retention (chưa đặt, sẽ bổ sung GĐ5); ip_address là PII — cần chính sách lưu giữ giới hạn |
| user_settings | app (cấu hình GĐ4) | meta_token (PII/secrete) | PK (user_id, skey); FK user_id ON DELETE CASCADE; giá trị text; KHÔNG render token trong HTML — chỉ hiển thị 4 ký tự cuối | xoá khi user xoá tài khoản |
| demographic_snapshots | app (Meta GĐ4) | - (payload tổng hợp, không PII) | user_id FK CASCADE; payload JSON (age/gender aggregated); fetched_at ghi thời điểm lấy | 1 snapshot mới nhất thay thế snapshot cũ; xoá khi user xoá dữ liệu |
| admins | app (quản trị) | email (PII), password_hash | email unique; role ∈ {super_admin, admin}; status ∈ {active, disabled}; chỉ 1 super_admin (toàn quyền) | theo chính sách — seed admin mặc định qua migrate (idempotent) |

## Relationships and cascade policy

- `short_links.user_id` → `users.id` (nullable, ON DELETE SET NULL): link ẩn danh được phép.
- `user_subscriptions.user_id` → `users.id` ON DELETE CASCADE; `plan_id` → `plans.id`.
- `users` hiện có thể không có subscription (ngầm hiểu gói free).
- Một user có nhiều subscription theo thời gian (lịch sử), nhưng chỉ một subscription
  `active`/`trial` tại một thời điểm.

## Index and query plan

- `short_links.slug` UNIQUE -> lookup redirect O(1).
- `users.email` UNIQUE -> lookup login.
- `user_subscriptions` KEY (user_id), KEY (plan_id) -> truy vấn gói hiện tại.
- `rate_limits` UNIQUE (ip_hash, bucket_key, window_start) -> upsert counter.
- `user_settings` PK (user_id, skey) -> get/set cấu hình nhanh, không cần index phụ.
- `demographic_snapshots` KEY (user_id, fetched_at) -> lấy snapshot mới nhất mỗi user.
- Cần theo dõi link của user -> thêm KEY (user_id) trên short_links khi build dashboard
  (chưa thêm ở v2 vì chưa có truy vấn đó).

## Transactions and concurrency

- INSERT slug phụ thuộc UNIQUE constraint, retry tối đa 5.
- UPDATE click_count atomic.
- Register: kiểm tra email unique + INSERT trong transaction; bắt duplicate -> báo email đã tồn tại.
- Subscription khi bán gói (sau này): cập nhật subscription cũ + tạo mới trong transaction.

## Encryption and key rotation

- `password_hash(PASSWORD_DEFAULT)` (bcrypt/argon2); không mã hoá hai chiều.
- CSRF token trong session, không lưu DB.

## Migration and rollback

- `database/migrate.php` idempotent, gồm v1 + v2:
  - v2 thêm `users`, `plans`, `user_subscriptions`, cột `short_links.user_id` (NULL),
    seed 3 gói mặc định (free/starter/pro).
  - v10 (GĐ4) thêm `user_settings` + `demographic_snapshots`; upsert driver-aware
    (MySQL ON DUPLICATE KEY / SQLite ON CONFLICT).
  - v11 thêm cột `click_events.ip_address` (IP thật, có ALTER cho DB cũ).
  - v12 thêm hồ sơ user + hoá đơn: `users.phone/address/city/tax_type/company_name/tax_id/invoice_name`.
  - v13 thêm bảng `admins` + seed admin mặc định (`darkbrotherleo@gmail.com`, super_admin).
  - v14 mở rộng `plans` thành gói dịch vụ đầy đủ (price/currency/billing_period/max_*/has_*/is_popular)
    + upsert 4 gói mặc định (free/starter/pro/team, giá VND).
  - v20 đổi `users.status` thành ENUM('active','pending','disabled') + thêm
    `users.activation_token/activation_expires_at` (24h) + `users.reset_token/reset_expires_at` (30 phút).
- Rollback: DROP bảng mới + ALTER bỏ cột theo thứ tự ngược, hoặc dùng DB mới.

## Backup and restore drill

- Backup: `mysqldump --single-transaction urlshortm`. Restore drill trên DB tách biệt
  trước khi coi là đạt (khi deploy production).

## Hướng thương mại hoá (định hướng schema)

- `plans.features` JSON cho phép thêm giới hạn theo gói (max_links, custom_slug,
  stats_retention_days…) mà không đổi schema.
- `user_subscriptions.status` dự phòng trạng thái thanh toán (trial/active/past_due/
  canceled/expired) cho cổng thanh toán (VnPay/Stripe…) sau này.
- Quota được tính từ gói active; nếu không có subscription -> gói free mặc định.
