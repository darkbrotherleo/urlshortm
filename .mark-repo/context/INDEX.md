# Context Router

| Task | Luôn đọc | Chỉ đọc thêm khi liên quan |
|---|---|---|
| Start/resume | `PROJECT.md`, `context/CURRENT.md` | `specs/product.md`, roadmap trong CURRENT |
| Feature | active plan, acceptance | architecture, API/data/UX tương ứng |
| Bug | bug-fix plan, `quality/KNOWN_FAILURES.md` | log ngắn và source/test tìm bằng `rg` |
| UI/layout | `specs/ux.md`, `playbooks/ui.md` | component/style liên quan |
| Backend/API | `specs/api.md`, `playbooks/backend.md` | data model, security |
| Database | `specs/data-model.md`, `playbooks/database.md` | migration/query liên quan |
| Upload/media | `playbooks/uploads.md`, security | storage/schema liên quan |
| Admin | `playbooks/admin.md`, authorization | exact module/API |
| Release | deployment playbook, checklist | env/CI/observability |
| Handoff | CURRENT, active plan, handoff template | diff summary |

Luôn bỏ qua `vendor`, `node_modules`, binary, uploads, build output, full logs và
git history khi chưa có lý do cụ thể. Mỗi lượt chỉ giữ một active task.
