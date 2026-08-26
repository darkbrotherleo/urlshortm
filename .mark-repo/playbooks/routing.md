# Routing and URL playbook

- Một public entry point và một base-path helper dùng cho mọi link/asset/API.
- Local subfolder và production domain phải chạy cùng code, không hardcode `/public`.
- Slug ngắn, ổn định, lowercase, có redirect khi đổi và canonical đúng.
- Route động đứng sau route tĩnh để tránh nuốt URL.
- 404/403/401 đúng status; API trả JSON, page route trả HTML.
- Sitemap/robots/canonical/index-noindex lấy từ cấu hình thật.
- Test URL có/không trailing slash, query, Unicode slug và refresh trực tiếp.
