# Start here

1. Chạy:

```powershell
.\scripts\init-project.ps1 -Name "Tên dự án" -Stack "Công nghệ chính"
```

2. Điền `PROJECT.md`, sau đó hoàn thiện `specs/product.md` và
   `specs/acceptance.md`.
3. Mở AI Agent tại thư mục `Mark_Repo` và gửi nội dung `prompts/start.txt`.
4. Chỉ tạo code, test và cấu hình runtime trong `project/`.
5. Mỗi task dùng một plan từ `templates/task-plan.md`.
6. Trước khi đổi model hoặc thiếu context, chạy `scripts/checkpoint.ps1`.
7. Chỉ đánh dấu done khi quality gate và test evidence đạt.
8. Sau nghiệm thu, chạy `scripts/cleanup.ps1` để xem trước phần sẽ loại bỏ.

Không yêu cầu AI đọc toàn bộ repo. `context/INDEX.md` quyết định file tối thiểu.
