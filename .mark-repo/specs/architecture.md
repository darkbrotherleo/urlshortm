# Architecture specification

## System context and trust boundaries

- Public entry: docroot `C:\laragon\www\UrlShortM` (Apache Laragon) hoặc
  built-in server `-t public` cho test. Apache rewrite mọi request tới
  `index.php` trừ file/dir thật (assets).
- Trust boundary: mọi input từ web là untrusted; chỉ có DB là nguồn sự thật.

## Entry points

- `GET /` -> HomeController: render form (read-only).
- `POST /shorten` -> HomeController@shorten: validate, tạo link, render result.
- `GET /{slug}` -> RedirectController: redirect 301 + click_count.
- Mọi path lạ -> 404.

## Module boundaries and dependency direction

```
index.php (front controller, chỉ điều phối)
  -> app/Router.php (parse path, gọi controller)
  -> app/Controller/* (mỏng: nhận request, gọi service, render view)
  -> app/Service/ShortUrlService.php (use case: create + resolve + click)
  -> app/Repository/UrlRepository.php, app/Repository/RateLimitRepository.php (PDO)
  -> app/Security/SlugGenerator.php, UrlNormalizer.php, Csrf.php, RateLimiter.php
  -> app/View/* (render, chỉ HTML)
```

Validation/domain rules/persistence/rendering tách module, không nằm trong
controller. Phụ thuộc chỉ theo chiều trên xuống (controller -> service ->
repository -> DB).

## Data flow

1. Create: form POST -> CSRF check -> URL normalize + validate -> rate limit ->
   sinh slug (retry trùng) -> INSERT -> render kết quả.
2. Resolve: GET slug -> SELECT by slug -> nếu tồn tại UPDATE click_count+1 ->
   301 Location:target; nếu không -> 404.

## Authentication and authorization

- Không có tài khoản. Không có tài nguyên private ở v1; không cần authorization
  phân quyền. Mutation chỉ được phép qua POST có CSRF.

## External services and failure modes

- MySQL: nếu mất kết nối -> bắt lỗi PDO, trả 500 an toàn, log lỗi server-only.
- Không gọi dịch vụ ngoài khác.

## Caching, queues and scheduled jobs

- Không cache/queue/job ở v1. Header `Cache-Control: no-store` cho form;
  `Cache-Control: max-age=86400` cho asset tĩnh (đủ cho v1, không dùng cachebuster).

## Observability

- Log error qua PHP error_log (server-only), message không chứa secret/token/URL
  người dùng. Không log mỗi request.

## Deployment, rollback and recovery

- Copy file + chạy `database/migrate.php` (idempotent). Rollback = chạy `DROP
  TABLE` tay theo migration cũ. Backup = `mysqldump urlshortm`; restore drill
  dùng DB tách biệt khi deploy production.

Quy tắc: entry point chỉ điều phối; validation, domain rules, persistence và
rendering phải tách thành module có thể kiểm thử.