<?php
/** @var array<int,array<string,mixed>> $plans */
$periodLabel = static fn (string $p): string => match ($p) {
    'yearly' => 'năm', 'lifetime' => 'trọn đời', default => 'tháng',
};
$limitLabel = static fn (int $v): string => $v < 0 ? 'Không giới hạn' : number_format($v);
$priceLabel = static fn (float $price, string $currency): string => $price <= 0 ? 'Miễn phí' : number_format($price, $currency === 'VND' ? 0 : 2, ',', '.') . '₫';
$features = static function (array $p) use ($limitLabel): array {
    $list = [];
    $list[] = $limitLabel((int) $p['max_links']) . ' link';
    $list[] = $limitLabel((int) $p['max_clicks']) . ' click / tháng';
    $list[] = $limitLabel((int) $p['max_custom_domains']) . ' custom domain';
    $list[] = $limitLabel((int) $p['max_pixels']) . ' pixel';
    $list[] = (int) $p['max_users'] . ' thành viên (team)';
    $flags = [
        'has_analytics' => 'Thống kê chi tiết',
        'has_qr_code' => 'QR Code',
        'has_password_protection' => 'Mật khẩu cho link',
        'has_link_expiration' => 'Thời hạn link',
        'has_utm_builder' => 'UTM Builder',
        'has_api_access' => 'Truy cập API',
    ];
    foreach ($flags as $key => $label) {
        $list[] = ($p[$key] ?? 0) ? $label : '';
    }

    return array_values(array_filter($list));
};
echo \App\render('header', ['title' => $title]);
?>
<section class="pricing section">
    <div class="container">
        <span class="pill">Bảng giá</span>
        <h1 class="pricing-title">Chọn gói phù hợp với bạn</h1>
        <p class="pricing-sub">Bắt đầu miễn phí, nâng cấp bất cứ lúc nào. Thanh toán qua PayPal, gói kích hoạt ngay sau khi thanh toán.</p>

        <div class="pricing-grid">
            <?php foreach ($plans as $p): if ((int) $p['is_active'] !== 1) continue; ?>
                <article class="pricing-card<?= $p['is_popular'] ? ' is-popular' : '' ?>">
                    <?php if ($p['is_popular']): ?>
                        <span class="pricing-badge">Được chọn nhiều</span>
                    <?php endif; ?>
                    <h2 class="pricing-card-name"><?= \App\escape($p['name']) ?></h2>
                    <p class="pricing-card-price"><?= \App\escape($priceLabel((float) $p['price'], (string) $p['currency'])) ?><small>/ <?= \App\escape($periodLabel((string) $p['billing_period'])) ?></small></p>
                    <p class="pricing-card-desc"><?= \App\escape((string) ($p['description'] ?? '')) ?></p>
                    <ul class="pricing-card-features">
                        <?php foreach ($features($p) as $f): ?>
                            <li><?= \App\escape($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ((float) $p['price'] <= 0): ?>
                        <a class="btn btn-ghost btn-block" href="<?= \App\url_for('dang-ky') ?>">Bắt đầu miễn phí</a>
                    <?php else: ?>
                        <a class="btn <?= $p['is_popular'] ? 'btn-primary' : 'btn-soft' ?> btn-block" href="<?= \App\url_for('thanh-toan') . '?plan=' . (int) $p['id'] ?>">Mua ngay</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="pricing-note">Cần thêm thành viên hoặc yêu cầu riêng? <a href="<?= \App\url_for('tro-giup') ?>">Liên hệ hỗ trợ</a>.</p>
    </div>
</section>
<?php echo \App\render('footer'); ?>
