# Current checkpoint

- Updated: 2026-08-26 11:55:54
- Completed: Link form nâng cao: thumbnail upload file, droplist pixels (tick chọn nhiều), toggle bật/tắt mật khẩu (bỏ tick xoá); 89/89 PASS; migrate v5 + E2E MySQL OK
- In progress: Không
- Exact next action: Có thể thêm: quản trị pixels/domain trong admin, đổi mật khẩu, analytics
- Active plan: plans/010-link-form-upload.md
- Blockers: Không
- Latest verification: PASS: full 89 (upload multipart, droplist, toggle), lint 61, security scan, verify-structure, migrate v5 idempotent
- Risks to watch: Upload chưa có resize/retention; test để file trong uploads/
