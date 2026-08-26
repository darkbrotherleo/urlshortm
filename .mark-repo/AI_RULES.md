# AI Coding Rules

## Mission

Tạo thay đổi nhỏ nhất đáp ứng đúng yêu cầu, bảo mật, dễ bảo trì và có bằng chứng.

## Workflow

`DISCOVER -> SPECIFY -> PLAN -> IMPLEMENT -> REVIEW -> VERIFY -> DOCUMENT`

- DISCOVER: đọc context tối thiểu, tìm bằng `rg`, xác lập baseline.
- SPECIFY: outcome, scope, non-goal, rủi ro và acceptance criteria.
- PLAN: liệt kê exact files, test-first, recovery và verification commands.
- IMPLEMENT: tạo test tái hiện trước, sửa tối thiểu, không cleanup ngoài scope.
- REVIEW: correctness, edge cases, security, privacy, UX, performance, migration.
- VERIFY: focused test rồi full suite; không suy đoán PASS.
- DOCUMENT: cập nhật plan evidence và `context/CURRENT.md`.

## Coding boundaries

- Code sản phẩm chỉ trong `project/`.
- Không hardcode dữ liệu runtime, secret, URL môi trường hoặc trạng thái UI giả.
- Entry point điều phối; business logic nằm trong module/service tái sử dụng.
- Server luôn kiểm tra authorization, validation và invariant; UI không phải biên bảo mật.
- Mọi bug phải có regression test. Mọi thay đổi nhiều bảng phải xem xét transaction.
- Không đổi schema đã áp dụng; tạo migration mới và recovery path.
- Không báo hoàn tất khi chưa chạy verification mới.

## Token contract

- Đọc theo `context/INDEX.md`; không quét vendor/build/upload/log.
- Dùng `rg` lấy đoạn liên quan thay vì mở file lớn toàn bộ.
- Không chép tool transcript vào tài liệu.
- Plan tối đa 120 dòng, CURRENT 150 dòng, handoff 100 dòng.
- Dẫn file/line thay vì lặp code dài trong phản hồi.
