# Frontend playbook

- Base URL sinh từ server hoặc một helper chuẩn; không nối chuỗi làm mất slash.
- Fetch kiểm tra status/content type rồi mới parse JSON; hiển thị lỗi hữu ích.
- UI state lấy từ server/database sau mutation, không giả định save đã thành công.
- Không dùng prompt/confirm thô cho workflow phức tạp; dùng modal/form accessible.
- Button có loading/disabled và chống gửi lặp.
- Cache JS/CSS dùng version/hash đổi cùng deployment.
- Event listener gắn đúng trigger; modal thư viện không mở khi click toàn editor.
- Render dữ liệu động phải escape/sanitize; rich text theo allowlist.
