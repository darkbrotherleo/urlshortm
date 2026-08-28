<?php
/** @var array<string,mixed> $user */
/** @var array<string,mixed> $order */
/** @var array<string,mixed>|null $plan */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= \App\escape($title) ?></title>
    <link rel="stylesheet" href="<?= \App\url_for('assets/css/style.css') ?>">
</head>
<body class="dash-body">
<div class="checkout-wrap">
    <header class="checkout-top">
        <a class="dash-brand" href="<?= \App\url_for('') ?>"><span class="brand-mark" aria-hidden="true"></span> UrlShortM</a>
        <a class="btn btn-ghost btn-sm" href="<?= \App\url_for('dashboard') ?>">&#8592; Về bảng điều khiển</a>
    </header>

    <main class="checkout">
        <div class="checkout-success">
            <div class="checkout-success-icon" aria-hidden="true">&#10003;</div>
            <h1>Thanh toán thành công!</h1>
            <p>Gói <strong><?= \App\escape($order['plan_name']) ?></strong> đã được kích hoạt cho tài khoản của bạn và sẵn sàng sử dụng ngay.</p>

            <table class="checkout-order checkout-success-order">
                <tr><td>Mã đơn hàng</td><td><code><?= \App\escape($order['order_code']) ?></code></td></tr>
                <tr><td>Gói</td><td><?= \App\escape($order['plan_name']) ?></td></tr>
                <tr><td>Số tiền</td><td><strong><?= \App\escape(number_format((float) $order['amount'], 0, ',', '.') . ' ' . $order['currency']) ?></strong></td></tr>
                <tr><td>Ngày thanh toán</td><td><?= \App\escape($order['paid_at']) ?></td></tr>
            </table>

            <div class="checkout-success-actions">
                <a class="btn btn-primary" href="<?= \App\url_for('hoa-don/' . rawurlencode($order['order_code'])) ?>" target="_blank" rel="noopener">Xem / In hoá đơn</a>
                <a class="btn btn-ghost" href="<?= \App\url_for('dashboard') ?>">Về bảng điều khiển</a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
