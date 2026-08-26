# Security playbook

- Threat model theo asset, actor, entry point và trust boundary.
- Secret từ environment/secret manager; rotate được; không log hoặc commit.
- Password dùng password hashing hiện đại, không mã hóa hai chiều.
- Validate server-side; encode output theo context; prepared statements bắt buộc.
- Authorization kiểm tra role + ownership/scope trên từng mutation.
- Session cookie Secure/HttpOnly/SameSite; CSRF; regeneration; rate limit.
- Sensitive data/private media mã hóa xác thực và lưu ngoài public.
- Upload dùng MIME thực, size limit, random name, scanning và safe serving route.
- Production tắt debug; CSP/security headers; lỗi fail closed.
- Audit sự kiện nhạy cảm nhưng không ghi secret/PII thừa.
