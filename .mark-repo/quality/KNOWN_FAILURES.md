# Known failure catalog

| Symptom | Likely cause | Preventive check |
|---|---|---|
| JSON parse báo ký tự `<` | Redirect/warning/fatal HTML từ API | Kiểm status + content type; API error luôn JSON |
| Save một enum nhưng đọc ra enum khác | Mapping UI/backend lệch hoặc checkbox phụ chi phối | Một enum contract; integration round-trip |
| Admin UI lưu nhưng website không đổi | Runtime không đọc settings database | Test write → public/runtime read |
| CTV thấy record nhưng không sửa được | List scope và mutation authorization dùng rule khác | Dùng chung scope policy và test role |
| Parent public nhưng ảnh không hiện | Media visibility/status chưa cascade | Transaction cập nhật parent + child |
| Refresh nhanh gây SQL lỗi | Mutation chạy trong GET hoặc race/idempotency thiếu | GET read-only; lock/version/idempotency |
| CSS/JS sửa nhưng trình duyệt vẫn cũ | Cachebuster không đổi | Asset hash/version trong deployment |
| URL local sai hoặc dính `/public` | Base path nối chuỗi/hardcode | Base URL helper + route smoke dưới subfolder |
| SQL unknown column/type error | Query lệch schema thật | Integration chạy trên current schema |
| Form edit thiếu dữ liệu hoặc trộn mẫu | Hardcode/fallback không phân biệt | Database source of truth; neutral empty state |
| Ảnh méo/bóng lệch | Thiếu aspect ratio/object-fit/shared component | Visual test nhiều kích thước |
| Icon che ảnh | Absolute overlay sai hierarchy | Dành vùng riêng cho decoration/icon |
| Upload thành công nhưng không hiển thị | Processing/scan/link status hoặc route quyền sai | End-to-end upload → serve test |
| completed nhưng email chưa tới | Business status trộn delivery status | Queue/delivery status riêng + worker evidence |
| Modal mở khi click mọi nơi | Listener gắn container quá rộng | Listener chỉ gắn trigger semantic |
| Font tiếng Việt lỗi | Encoding/font thiếu glyph | UTF-8 end-to-end + font Vietnamese subset |

Khi gặp triệu chứng mới, thêm regression test và một dòng ngắn vào bảng; không
chép log dài hoặc workaround riêng dự án.
