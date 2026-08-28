<?php
/** @var array<string,mixed> $user */
/** @var array<int,array<string,mixed>> $plans */
/** @var array<string,mixed>|null $plan */
/** @var \App\Security\Csrf $csrf */
/** @var bool $paypalConfigured */
$periodLabel = static fn (string $p): string => match ($p) {
    'yearly' => 'năm', 'lifetime' => 'trọn đời', default => 'tháng',
};
$limitLabel = static fn (int $v): string => $v < 0 ? 'Không giới hạn' : number_format($v);
$priceLabel = static fn (float $price, string $currency): string => number_format($price, $currency === 'VND' ? 0 : 2, ',', '.') . ' ' . $currency;
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
        <a class="btn btn-ghost btn-sm" href="<?= \App\url_for('dashboard?tab=tai-khoan') ?>">&#8592; Về tài khoản</a>
    </header>

    <main class="checkout">
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" role="alert"><?= \App\escape((string) $_GET['error']) ?></div>
        <?php endif; ?>

        <?php if ($plan === null): ?>
            <h1 class="checkout-title">Nâng cấp gói dịch vụ</h1>
            <p class="checkout-sub">Chọn gói phù hợp — thanh toán tự động qua PayPal. Dùng ngay sau khi thanh toán thành công.</p>
            <div class="checkout-plans">
                <?php foreach ($plans as $p): if ((int) $p['is_active'] !== 1 || (float) $p['price'] <= 0) continue; ?>
                    <article class="checkout-plan">
                        <h2><?= \App\escape($p['name']) ?><?= $p['is_popular'] ? ' <span class="badge">Phổ biến</span>' : '' ?></h2>
                        <p class="checkout-plan-price"><?= \App\escape($priceLabel((float) $p['price'], (string) $p['currency'])) ?><small>/ <?= \App\escape($periodLabel((string) $p['billing_period'])) ?></small></p>
                        <ul class="checkout-plan-features">
                            <li><?= \App\escape($limitLabel((int) $p['max_links'])) ?> link</li>
                            <li><?= \App\escape($limitLabel((int) $p['max_clicks'])) ?> click</li>
                            <li><?= \App\escape($limitLabel((int) $p['max_custom_domains'])) ?> custom domain</li>
                            <li><?= \App\escape($limitLabel((int) $p['max_pixels'])) ?> pixel</li>
                            <li><?= (int) $p['max_users'] ?> thành viên</li>
                        </ul>
                        <a class="btn btn-primary btn-block" href="<?= \App\url_for('thanh-toan') . '?plan=' . (int) $p['id'] ?>">Chọn gói</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <h1 class="checkout-title">Thanh toán đơn hàng</h1>
            <div class="checkout-grid">
                <section class="checkout-card">
                    <h2>Đơn hàng của bạn</h2>
                    <table class="checkout-order">
                        <tr><td>Gói</td><td><strong><?= \App\escape($plan['name']) ?></strong></td></tr>
                        <tr><td>Chu kỳ</td><td><?= \App\escape($periodLabel((string) $plan['billing_period'])) ?></td></tr>
                        <?php if (isset($voucherInfo['error'])): ?>
                            <tr><td>Thành tiền</td><td><strong><?= \App\escape($priceLabel((float) $plan['price'], (string) $plan['currency'])) ?></strong></td></tr>
                            <tr><td colspan="2"><div class="alert alert-error" role="alert"><?= \App\escape($voucherInfo['error']) ?></div></td></tr>
                        <?php elseif (isset($voucherInfo['voucher'])): ?>
                            <tr><td>Thành tiền (trước giảm)</td><td><?= \App\escape($priceLabel((float) $plan['price'], (string) $plan['currency'])) ?></td></tr>
                            <tr><td>Mã <?= \App\escape($voucherInfo['voucher']['code']) ?></td><td style="color:var(--ok);font-weight:700;">-<?= \App\escape($priceLabel((float) $voucherInfo['discount'], (string) $plan['currency'])) ?></td></tr>
                            <tr><td>Phải thanh toán</td><td><strong><?= \App\escape($priceLabel((float) $voucherInfo['amount_after'], (string) $plan['currency'])) ?></strong></td></tr>
                        <?php else: ?>
                            <tr><td>Thành tiền</td><td><strong><?= \App\escape($priceLabel((float) $plan['price'], (string) $plan['currency'])) ?></strong></td></tr>
                        <?php endif; ?>
                    </table>

                    <h2>Mã giảm giá</h2>
                    <form method="get" action="<?= \App\url_for('thanh-toan') ?>" class="checkout-voucher">
                        <input type="hidden" name="plan" value="<?= (int) $plan['id'] ?>">
                        <input type="text" name="voucher" value="<?= \App\escape((string) ($_GET['voucher'] ?? '')) ?>" placeholder="Nhập mã voucher..." autocomplete="off" style="text-transform:uppercase;">
                        <button type="submit" class="btn btn-ghost btn-sm">Áp dụng</button>
                    </form>

                    <h2>Thông tin xuất hoá đơn</h2>
                    <div class="checkout-billing">
                        <p><span>Tên người mua:</span> <?= \App\escape(($user['invoice_name'] ?? '') ?: ($user['company_name'] ?: ($user['display_name'] ?: $user['email']))) ?></p>
                        <p><span>Mã số thuế:</span> <?= \App\escape($user['tax_id'] ?? '—') ?></p>
                        <p><span>Địa chỉ:</span> <?= \App\escape(($user['address'] ?? '') . ($user['city'] ? ', ' . $user['city'] : '')) ?></p>
                        <p class="checkout-billing-hint">Cập nhật thông tin hoá đơn tại <a href="<?= \App\url_for('dashboard?tab=cai-dat') ?>">Cài đặt tài khoản</a>.</p>
                    </div>
                </section>
                <section class="checkout-card">
                    <h2>Chọn phương thức thanh toán</h2>
                    <label class="checkout-method">
                        <input type="radio" name="pay-method" value="paypal" checked>
                        <span class="checkout-method-body">
                            <strong>PayPal</strong>
                            <small>Thanh toán qua tài khoản PayPal (sandbox/live theo cấu hình)</small>
                        </span>
                    </label>
                    <?php if (!$paypalConfigured): ?>
                        <p class="alert alert-warn" role="alert">Cổng thanh toán chưa cấu hình — đang ở <b>chế độ test (giả lập)</b>. Bấm thanh toán để hoàn tất luồng.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= \App\url_for('thanh-toan/pay') ?>">
                        <?= $csrf->field() ?>
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                        <?php if (isset($voucherInfo['voucher'])): ?>
                            <input type="hidden" name="voucher" value="<?= \App\escape($voucherInfo['voucher']['code']) ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-block">Thanh toán ngay</button>
                    </form>
                </section>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
