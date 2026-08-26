# Upload and media playbook

1. Kiểm tra quyền và quota trước khi nhận file.
2. Giới hạn size/count; phát hiện MIME từ nội dung, không tin extension.
3. Random storage key; original name mã hóa hoặc lưu metadata an toàn.
4. Ảnh chuẩn hóa định dạng/kích thước/quality; tạo thumbnail phù hợp.
5. Private file ngoài document root; phục vụ qua route kiểm tra quyền.
6. Scan/process có trạng thái pending/ready/failed và worker idempotent.
7. Database + filesystem có rollback/cleanup khi một bước lỗi.
8. Khi quyền entity cha đổi, media visibility phải đồng bộ cùng transaction.
9. Frontend dùng lazy loading, width/height, srcset/cache headers khi phù hợp.
