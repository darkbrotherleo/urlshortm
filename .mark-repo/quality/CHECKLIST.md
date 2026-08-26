# Quality gate

## Product
- [ ] Acceptance criteria có evidence.
- [ ] Không còn dữ liệu demo/hardcode trong runtime.

## Engineering
- [ ] Architecture boundary và convention được giữ.
- [ ] Focused/integration/full tests PASS.
- [ ] Lint/typecheck/build PASS.

## Data and security
- [ ] Authorization, validation, CSRF/rate limit áp dụng đúng.
- [ ] Migration/transaction/rollback/backup được kiểm tra.
- [ ] Không secret, PII, debug output hoặc unsafe upload.

## UX
- [ ] Loading/error/empty/success/disabled đầy đủ.
- [ ] Keyboard/mobile/contrast/long data đạt.
- [ ] Header/footer/component đồng bộ.

## Operations
- [ ] Config local/staging/production tách biệt.
- [ ] Logs/metrics/alerts/worker/cron và rollback sẵn sàng.
- [ ] Handoff và test evidence cập nhật.
