# Product specification

## Problem

Link dài khó chia sẻ, dễ gãy ở ứng dụng nhắn tin. Người dùng cần một dịch vụ tối
giản: dán URL dài, nhận link ngắn, người khác mở link ngắn thì được chuyển đúng
đến đích và link phải đếm được số lần mở.

## Desired outcome

Người dùng mở trang chủ, dán URL, submit -> nhận slug ngắn (VD: `https://host/Ab3x9Q`).
Mở slug bất kỳ lúc nào -> HTTP 301 redirect đến target URL, click_count tăng 1.
Trang kết quả hiện link ngắn để copy và số click hiện tại.

## Users and permissions

- Khách (chưa đăng nhập): tạo link ngắn, mở link ngắn, xem số click (link ẩn danh).
- User (đã đăng ký): đăng nhập để link được gắn về tài khoản, chuẩn bị quản lý
  link và nâng cấp gói dịch vụ.
- Không có admin ở phiên bản hiện tại.

## Core journeys

1. Tạo link: GET `/` -> điền URL -> POST `/shorten` -> hiển thị link ngắn + copy.
2. Mở link: GET `/{slug}` -> 301 redirect đến target, click_count +1.
3. Link không tồn tại: GET `/{slug}` -> 404 trang thân thiện.
4. Tạo tài khoản: GET `/dang-ky` -> điền thông tin -> POST `/dang-ky` -> tự đăng nhập.
5. Đăng nhập/Đăng xuất: GET `/dang-nhap` + POST `/dang-xuat`.
6. Bảng điều khiển: GET `/dashboard` -> xem tổng quan, link của tôi, tài khoản
   (sidebar trái + nội dung phải, tab active qua `?tab=`).

## Functional requirements

- FR-1: Nhận URL HTTP/HTTPS; tự thêm `https://` nếu người dùng không gõ scheme.
- FR-2: Slug sinh ngẫu nhiên `[0-9a-zA-Z]{6}`, chống trùng bằng retry (tối đa 5).
- FR-3: Redirect dùng HTTP 301 (permanent), kèm `Cache-Control` phù hợp.
- FR-4: click_count tăng đúng 1 mỗi lần redirect thành công.
- FR-5: Rate limit tạo link: tối đa 50 link/IP/giờ (lưu hash IP).
- FR-6: GET `/` không thay đổi dữ liệu (read-only); mutation chỉ qua POST `/shorten`.
- FR-7: Đăng ký (email + mật khẩu + tên hiển thị), đăng nhập, đăng xuất bằng session.
- FR-8: Mật khẩu dùng `password_hash`; đăng nhập giới hạn 10 lần/IP/giờ và 10 lần/email/giờ.
- FR-9: Khi đã đăng nhập, link tạo mới được gắn `user_id` (để sau này quản lý/gói).

## Non-functional requirements

- Security: prepared statement mọi query; CSRF token trên form POST; validate URL
  server-side; không log target URL hoặc token; `htmlspecialchars` mọi output.
- Privacy: không thu thập PII; IP chỉ lưu dạng hash cho rate limit.
- Accessibility: form có label, focus rõ, đủ độ tương phản, thao tác được bằng bàn phím.
- Performance: query có index trên `slug` (unique); không N+1; 301 redirect nhẹ.
- Reliability: lỗi DB hiển thị trang lỗi 500 an toàn, không lộ chi tiết.
- Observability: log lỗi server-only; không log dữ liệu người dùng.
- Localization: giao diện tiếng Việt (UTF-8), font hỗ trợ tiếng Việt.

## Non-goals

- Tài khoản, dashboard admin, API JSON, custom slug, hạn chế target URL theo
  allowlist domain, theo dõi referrer/UA, QR code, xoá link.

> Ghi chú cập nhật: tài khoản user (đăng ký/đăng nhập) đã triển khai ở task 004;
> dashboard admin và custom slug vẫn là non-goal. Chuẩn bị thương mại hoá bằng
> bảng `plans` + `user_subscriptions` (chưa bán gói).

## Assumptions and open questions

- MySQL chạy local qua Laragon (`root`, không password) là mặc định; cho phép
  override qua `config.local.php`/env.
- Slug 6 ký tự base62 đủ an toàn cho v1 (≈56 tỷ tổ hợp); nếu cần rút ngắn hơn
  có thể dùng 7 ký tự sau này (ADR khi thay đổi).