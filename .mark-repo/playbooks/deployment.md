# Deployment playbook

1. Environment inventory và secret/config validation.
2. Backup + restore drill; migration dry-run trên staging clone.
3. Build reproducible; dependency lock; security scan.
4. Smoke auth, core journey, API, upload, email/job và admin.
5. Deploy có health check và rollback command đã thử.
6. Migrate theo chiến lược tương thích ngược khi zero-downtime.
7. Bật HTTPS, security headers, cron/worker, log/metrics/alerts.
8. Theo dõi error rate, latency, queue, database và storage sau release.
9. Ghi evidence và incident owner; không xóa backup trước retention.
