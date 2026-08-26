# UX specification

## Design direction — "Soft & friendly service"

Trang chủ là landing page **bán dịch vụ rút gọn + theo dõi link**. Phong cách
**mềm mại, thân thiện**, tập trung cảm giác đáng tin và dễ dùng — không cứng
nhắc, không kỹ thuật. Loại bỏ hoàn toàn ngôn ngữ lập trình trong nội dung.

- **Colour** (ấm áp, dịu nhẹ — không xanh/tím đại trà):
  - `--bg: #FBF6F0` (kem ấm), `--surface: #FFFFFF`
  - `--ink: #33292B` (nâu than ấm), `--muted: #7C6F6F`
  - `--accent: #FF6B4A` (san hô), gradient accent `#FF8E6E → #FF5E62`
  - `--peach: #FFD9C7` (đào nhạt cho blob/backdrop)
  - `--ok: #2E9E6B`, `--bad: #C24949`
- **Typography** (đồng bộ một họ duy nhất, hỗ trợ tiếng Việt có dấu đầy đủ):
  - `Lexend` variable (self-hosted woff2: latin + latin-ext + vietnamese subset),
    dùng cho tiêu đề, body, nút và số liệu.
  - `--font: 'Lexend', system-ui, sans-serif` — không còn serif/mono rời rạc.
- **Signature elements**:
  - Hero chia hai cột mềm: headline + công cụ rút gọn thật (card bo tròn,
    đổ bóng nhẹ) — không screenshot, không mock.
  - Blob gradient đào nhạt phía sau hero, float nhẹ; không stock image.
  - Card tính năng bo tròn 20px, hover nhấc nhẹ + đổ bóng.
  - Scroll reveal nhẹ (fade + translate) qua IntersectionObserver, tôn trọng
    `prefers-reduced-motion`.
- **Motion**: mềm, ngắn (150-300ms), không parallax, không hiệu ứng thừa.

## Journeys and information architecture

1. Hero: dán URL -> "Rút gọn" -> nhận link ngắn + tracking panel (số lượt mở
   thật, tự cập nhật mỗi 3s qua `GET /stats/{slug}`).
2. "Vì sao chọn": 3 card tính năng bằng ngôn ngữ người dùng.
3. "Cách hoạt động": 3 bước đánh số, câu từ tự nhiên.
4. FAQ: `<details>` native accessible, câu hỏi thực tế.
5. 404/500: cùng phong cách, có đường về trang chủ.

## Component inventory

- Header (logo + nav + CTA), hero (headline + tool card), tool card
  (input + button + kết quả + tracking panel), feature cards,
  steps, spec bảng nhẹ (nếu cần), FAQ `<details>`, alert, footer.

## States

- Loading: nút "Đang rút gọn…" disabled khi submit.
- Success: tracking panel xanh lá, link copy được.
- Validation error: alert dịu (nền đỏ nhạt) gần form, giữ input.
- Server error: alert chung, không lộ chi tiết.
- Empty: form rỗng mặc định; số liệu = số thật từ DB.
- Disabled: nút disabled khi đang xử lý.
- Offline: dừng poll stats khi fetch lỗi liên tục.

## Accessibility

- `<label for>` ràng buộc input; focus ring accent rõ (`:focus-visible`).
- Keyboard đầy đủ; `aria-live` cho alert + số lượt mở.
- Contrast: ink trên bg >= 7:1; accent trên trắng >= 4.5:1.
- FAQ `<details>`/`<summary>` native.
- Reduced motion: tắt blink, float, reveal.

## Responsive matrix

| Component/page | Mobile | Tablet | Desktop | Long/empty/error data |
|---|---|---|---|---|
| Hero | dựng dọc, tool card dưới headline | 1 cột rộng | 2 cột | URL dài `word-break` |
| Tool card | full-width | full-width | theo cột | output wrap |
| Feature cards | 1 cột | 2 cột | 3 cột | text wrap |
| FAQ | full-width | full-width | max-width 760px | nội dung wrap |

Không duyệt layout chỉ bằng mock đẹp; phải kiểm tra dữ liệu thật và thao tác thật.
