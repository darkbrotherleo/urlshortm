# Debugging playbook

1. Ghi exact URL, actor/role, input, expected và actual.
2. Tái hiện với request nhỏ nhất; lưu status, content type và response prefix ngắn.
3. Theo luồng UI → route → controller → service → query → database → response.
4. So schema thật với query; không tin migration hoặc mock đã cũ.
5. Kiểm tra base URL, rewrite, cachebuster, session, CSRF và authorization.
6. Viết regression test trước khi sửa.
7. Sửa nguồn sai đầu tiên; tránh try/catch che lỗi hoặc fallback dữ liệu giả.
8. Chạy lại hành trình và test lân cận.

Nếu frontend báo lỗi parse JSON, kiểm tra HTTP status/content type/body trước;
`Unexpected token '<'` thường là HTML redirect, warning hoặc fatal error.
