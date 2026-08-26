# API specification

Không có API JSON công khai ở v1; giao diện là HTML page.

| Method | Path | Auth/role | Request | Success | Errors | Idempotency |
|---|---|---|---|---|---|---|
| GET | `/` | public | - | 200 HTML landing | - | GET read-only |
| POST | `/shorten` | public + CSRF | `target`, `csrf_token` | 200 HTML kết quả kèm slug + click (gán user_id nếu đã đăng nhập) | 400 invalid URL, 403 CSRF, 429 rate limit | Không |
| GET | `/{slug}` | public | - | 301 Location target | 404 slug lạ | GET chỉ tăng click |
| GET | `/stats/{slug}` | public | - | 200 JSON `{slug, click_count, created_at}` | 404 slug lạ, 400 slug sai định dạng | GET read-only |
| GET | `/dang-ky` | guest | - | 200 HTML form đăng ký | - | GET read-only |
| POST | `/dang-ky` | guest + CSRF | `name`, `email`, `password`, `password_confirm`, `csrf_token` | 302 về `/` (đã đăng nhập) | 400 validation, 409 email tồn tại, 403 CSRF, 429 rate limit | Không |
| GET | `/dang-nhap` | guest | - | 200 HTML form đăng nhập | - | GET read-only |
| POST | `/dang-nhap` | guest + CSRF | `email`, `password`, `csrf_token` | 302 về `/` (đã đăng nhập) | 401 sai email/mật khẩu, 403 CSRF, 429 rate limit | Không |
| POST | `/dang-xuat` | user + CSRF | `csrf_token` | 302 về `/` | 403 CSRF | Có (logout lặp không hại) |

| GET | `/dashboard` | user | - | 200 HTML dashboard (tab qua `?tab=`) | - | GET read-only |
| GET | `/dashboard/link/new` | user | - | 200 HTML form tạo link | - | GET read-only |
| POST | `/dashboard/link` | user + CSRF | `link_type,target,title,description,thumbnail,pixels,utm_*,custom_slug,domain,folder_id,password,starts_at,ends_at` | 302 về All Link | 400 validation, 409 slug trùng | Không |
| GET | `/dashboard/link/{id}/edit` | user | - | 200 HTML form sửa | 404 | GET read-only |
| POST | `/dashboard/link/{id}/update` | user + CSRF | như create | 302 về All Link | 400/409 | Không |
| POST | `/dashboard/link/{id}/delete` | user + CSRF | - | 302 về All Link | - | Có |
| POST | `/dashboard/link/bulk` | user + CSRF | `ids` (comma), `bulk_action` (delete/move), `folder_id` | 302 về All Link | - | Có |
| POST | `/{slug}/unlock` | public + CSRF | `password` | 302 về `/{slug}` | 400 sai mật khẩu | Có |
| GET | `/dashboard/folder/{create,delete}` | user + CSRF | - | 302 | - | Có |

## Contract rules

- Page route trả HTML; `/stats/{slug}` trả JSON (content type `application/json`).
- Validation error trả 400 và message tiếng Việt an toàn, không chứa input thô.
- Không trả stack trace, SQL, secret hoặc dữ liệu ngoài quyền.
- Mutation có CSRF (token trong session), authorization server-side (scope user_id).
- Slug `[0-9a-zA-Z-_]{3,16}`; custom slug không trùng, không reserved.
- Link có `password_hash` -> GET `/{slug}` trả form nhập mật khẩu (200) thay vì
  redirect; unlock thành công đặt flag trong session rồi mới redirect.
- Link ngoài khung thời gian (`starts_at`/`ends_at`) -> 410 + thông báo.
- Rate limit 50 request/IP/giờ cho POST `/shorten`; đăng ký/đăng nhập giới hạn
  10 lần/IP/giờ và đăng nhập thêm 10 lần/email/giờ.
- Session cookie Secure/HttpOnly/SameSite=Lax; `session_regenerate_id` khi đăng nhập.