# Database playbook

- Schema dùng constraint/index thể hiện invariant và query thật.
- UTF-8 đầy đủ; timestamp UTC; identifier công khai khó đoán khi cần.
- Runtime/migration/backup dùng tài khoản least privilege riêng.
- Migration đã áp dụng là bất biến; thay đổi bằng migration mới.
- Transaction cho thay đổi liên quan nhiều bảng; lock/version cho race condition.
- Khi field cha thay đổi ảnh hưởng record con, cập nhật cùng transaction.
- Đo query plan, pagination và N+1 trước khi cache/denormalize.
- Backup không được xem là đạt nếu chưa restore drill database tách biệt.
- Cấu hình kết nối tách source, hỗ trợ local/staging/production và không lộ secret.
