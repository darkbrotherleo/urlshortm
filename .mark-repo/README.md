# Mark_Repo

Bộ khung nhẹ dành cho AI chuyên coding triển khai dự án thật với bất kỳ chủ đề
và công nghệ nào. Bộ khung quản lý cách làm; code sản phẩm nằm riêng trong
`project/` và không phụ thuộc vào tài liệu hỗ trợ sau khi hoàn thành.

## Giá trị cốt lõi

- Yêu cầu đo được trước khi code.
- Lát cắt end-to-end nhỏ, test-first và review dựa trên bằng chứng.
- Database, backend, frontend, UI/UX, bảo mật và vận hành được thiết kế đồng bộ.
- Context router và checkpoint ngắn giúp giảm token API.
- Failure catalog ngăn AI lặp lại lỗi đã biết.
- Cleanup theo allowlist, mặc định dry-run, luôn giữ code sản phẩm.

## Cấu trúc

- `project/`: source code, tests và runtime config của sản phẩm.
- `context/`: trạng thái hiện tại và bản đồ file cần đọc.
- `specs/`: product, acceptance, architecture, data, API và UX.
- `playbooks/`: cách triển khai theo lĩnh vực kỹ thuật.
- `quality/`: quality gate và lỗi thường gặp.
- `templates/`: task, bug fix, ADR, handoff và test evidence.
- `prompts/`: câu lệnh ngắn cho AI.
- `scripts/`: init, context, checkpoint, verify và cleanup.

Xem `START_HERE.md` để bắt đầu.
