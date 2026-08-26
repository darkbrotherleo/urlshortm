# Admin playbook

- Admin và collaborator là scope khác; backend không chỉ ẩn nút mà phải kiểm quyền.
- Bảng có search/filter/pagination, nhãn tiếng người dùng và action rõ ràng.
- Thay đổi trạng thái dùng select giới hạn enum, giải thích tác động và audit.
- Trạng thái nhạy cảm bắt buộc lý do/xác nhận; không dùng prompt nhập mã.
- Settings phải ghi database và runtime phải đọc database; tránh UI chỉ để xem.
- Custom code giới hạn super-admin, sanitize nghiêm và không chèn vào admin page.
- Export/backup/email có trạng thái job riêng; completed không đồng nghĩa delivered.
- Mọi action có loading, lỗi inline và reload đúng section/tab đang chọn.
