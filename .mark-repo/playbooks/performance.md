# Performance playbook

- Đặt budget cho LCP/INP/CLS, API latency, query count, memory và bundle.
- Đo trước/sau bằng dữ liệu đại diện; không tối ưu theo cảm giác.
- Index theo query; pagination/cursor; tránh SELECT thừa và N+1.
- Cache có owner, key, TTL và invalidation rõ.
- Ảnh tối ưu định dạng/kích thước, lazy loading và cache immutable.
- Background job cho xử lý nặng; timeout/retry/circuit breaker cho external service.
- Load test critical path và kiểm tra degradation khi service phụ lỗi.
