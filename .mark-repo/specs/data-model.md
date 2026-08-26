# Data model specification

| Entity | Owner | Sensitive fields | Invariants | Retention |
|---|---|---|---|---|
| short_links | app | target_url (public, không PII) | slug unique, slug regex `[0-9a-zA-Z]{6}`, target_url http(s) sau chuẩn hoá, user_id NULL = link ẩn danh | vĩnh viễn (chưa có xoá v1) |
| rate_limits | app | ip_hash (hash SHA-256, không phải IP thô) | unique (ip_hash, bucket_key, window_start), count >= 0 | xoá window cũ > 24h khi migrate |
| users | app (user tự tạo) | email (PII), password_hash | email unique + chuẩn form, password_hash dùng bcrypt/argon2, status ∈ {active, disabled} | khi user yêu cầu xoá hoặc chính sách |
| plans | app (catalog bán gói) | - | code unique, price >= 0, features JSON theo allowlist | giữ catalog |
| user_subscriptions | app | - | unique active subscription mỗi user (một gói hiệu lực tại một thời điểm), FK user/plan | giữ lịch sử, xoá theo user |
| pixels | app (danh sách Pixel ID cho droplist) | - | code unique, is_active ∈ {0,1} | giữ catalog |
| uploads/ | app (thumbnail) | - | chỉ ảnh (JPG/PNG/WEBP/GIF), tên ngẫu nhiên, .htaccess chặn thực thi | theo chính sách lưu trữ |

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
