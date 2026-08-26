# Backend playbook

- Route/controller mỏng; service giữ use case; repository/query tách khi có giá trị.
- Input DTO/validator chuẩn hóa enum, date, length và optional field một lần.
- Response contract nhất quán; JSON API không redirect HTML khi session hết hạn.
- Authorization nằm trước mutation và query phải giới hạn đúng scope.
- Multi-table mutation dùng transaction và audit metadata cần thiết.
- Job/email/upload có idempotency key, retry bounded và trạng thái giao nhận riêng.
- Không nuốt exception quan trọng; log correlation ID và message không chứa secret.
- Tránh global mutable state; dependency inject để test được.
