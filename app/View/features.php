<?php
/** @var array<int,array<string,mixed>> $plans */
$periodLabel = static fn (string $p): string => match ($p) {
    'yearly' => 'năm', 'lifetime' => 'trọn đời', default => 'tháng',
};
$limitLabel = static fn (int $v): string => $v < 0 ? 'Không giới hạn' : number_format($v);
$priceLabel = static fn (float $price, string $currency): string => $price <= 0 ? 'Miễn phí' : number_format($price, $currency === 'VND' ? 0 : 2, ',', '.') . '₫';
$planFeatures = static function (array $p) use ($limitLabel): array {
    $list = [];
    $list[] = $limitLabel((int) $p['max_links']) . ' link';
    $list[] = $limitLabel((int) $p['max_clicks']) . ' click / tháng';
    $list[] = $limitLabel((int) $p['max_custom_domains']) . ' custom domain';
    $list[] = $limitLabel((int) $p['max_pixels']) . ' pixel';
    $list[] = (int) $p['max_users'] . ' thành viên (team)';
    foreach ([
        'has_analytics' => 'Thống kê chi tiết',
        'has_qr_code' => 'QR Code',
        'has_password_protection' => 'Mật khẩu cho link',
        'has_link_expiration' => 'Thời hạn link',
        'has_utm_builder' => 'UTM Builder',
        'has_api_access' => 'Truy cập API',
    ] as $key => $label) {
        $list[] = ($p[$key] ?? 0) ? $label : '';
    }

    return array_values(array_filter($list));
};
$features = [
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>', 'title' => 'Rút gọn link tức thì', 'text' => 'Dán URL dài là có link ngắn gọn trong vài giây. Tuỳ chọn slug tuỳ chỉnh, không cần mở tài khoản để dùng thử.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>', 'title' => 'Theo dõi từng lượt mở', 'text' => 'Biết rõ ai bấm link của bạn: thiết bị, trình duyệt, hệ điều hành, quốc gia, nguồn vào và IP — mọi thứ trong một báo cáo.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>', 'title' => 'Báo cáo đa chiều + biểu đồ', 'text' => 'Bảng điều khiển trực quan: lượt mở theo ngày, thiết bị, quốc gia, top link. Xuất CSV, lọc theo link và khoảng thời gian bất kỳ.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 12h10M12 7v10"/></svg>', 'title' => 'Nhân khẩu học Meta', 'text' => 'Kết nối Ad Account Meta, xem phân bổ độ tuổi – giới tính của đối tượng quảng cáo ngay trong báo cáo để tối ưu chiến dịch.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>', 'title' => 'QR Code đẹp mắt', 'text' => 'Tạo mã QR cho mọi link ngay tại chỗ, tuỳ chỉnh màu sắc và logo. In lên bao bì, tờ rơi, bảng hiệu dễ dàng.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg>', 'title' => 'Bảo vệ & kiểm soát link', 'text' => 'Đặt mật khẩu cho link riêng tư, giới hạn thời gian hoạt động (bắt đầu/kết thúc). Chỉ người được phép mới mở được.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v12H7l-3 4V4z"/><path d="M8 9h8M8 12h5"/></svg>', 'title' => 'UTM Builder thông minh', 'text' => 'Gắn thẻ UTM chuẩn cho từng chiến dịch, lưu sẵn bộ cấu hình để áp dụng nhanh — Google Analytics đo chuẩn từng nguồn.'],
    ['icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg>', 'title' => 'Custom domain thương hiệu', 'text' => 'Dùng domain của bạn cho link ngắn (link.congty.vn). Tăng độ tin cậy và tỷ lệ bấm so với link rút gọn thông thường.'],
];
echo \App\render('header', ['title' => $title]);
?>
<!-- HERO -->
<section class="feat-hero">
    <div class="feat-hero-blob" aria-hidden="true"></div>
    <div class="container feat-hero-inner">
        <span class="pill">Tính năng</span>
        <h1 class="feat-hero-title">Mọi thứ bạn cần để biến mỗi link thành <span class="feat-accent">kênh bán hàng</span></h1>
        <p class="feat-hero-sub">Rút gọn link chuyên nghiệp, theo dõi từng lượt mở, hiểu khách hàng và tối ưu chiến dịch — tất cả trong một nền tảng nhẹ nhàng, nhanh và an toàn.</p>
        <div class="feat-hero-actions">
            <a class="btn btn-primary" href="#bang-gia">Chọn gói ngay</a>
            <a class="btn btn-ghost" href="<?= \App\url_for('dang-ky') ?>">Bắt đầu miễn phí</a>
        </div>
        <div class="feat-stats">
            <div><strong>4.900+</strong><span>link được tạo</span></div>
            <div><strong>286K</strong><span>lượt mở theo dõi</span></div>
            <div><strong>&lt;1s</strong><span>tạo link</span></div>
            <div><strong>99,9%</strong><span>uptime</span></div>
        </div>
    </div>
</section>

<!-- PROBLEM -->
<section class="feat-section">
    <div class="container feat-problem">
        <div>
            <span class="pill">Bạn đang gặp phải?</span>
            <h2>Link dài lê thê, quảng cáo "cháy tiền" mà không biết vì sao?</h2>
        </div>
        <p>Bạn chia sẻ link khắp nơi nhưng không biết ai bấm, từ đâu, thiết bị gì. Không biết chiến dịch nào đang hiệu quả, đối tượng nào đang quan tâm. Kết quả là chi phí quảng cáo bị đốt mà tỷ lệ chuyển đổi vẫn dậm chân tại chỗ.</p>
    </div>
</section>

<!-- FEATURES -->
<section class="feat-section feat-alt">
    <div class="container">
        <div class="feat-heading">
            <span class="pill">Giải pháp của UrlShortM</span>
            <h2>Tính năng giúp bạn bán hàng tốt hơn</h2>
            <p>Từ khâu chia sẻ đến đo lường, UrlShortM lo trọn vẹn để bạn tập trung vào kinh doanh.</p>
        </div>
        <div class="feat-grid">
            <?php foreach ($features as $f): ?>
                <article class="feat-card">
                    <span class="feat-card-icon" aria-hidden="true"><?= $f['icon'] ?></span>
                    <h3><?= \App\escape($f['title']) ?></h3>
                    <p><?= \App\escape($f['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- WHY -->
<section class="feat-section">
    <div class="container feat-why">
        <div class="feat-heading">
            <span class="pill">Vì sao chọn UrlShortM?</span>
            <h2>Không chỉ rút gọn link</h2>
        </div>
        <div class="feat-why-grid">
            <div><strong>Dữ liệu thuộc về bạn</strong><span>Báo cáo chi tiết đến từng lượt mở, xuất CSV bất cứ lúc nào.</span></div>
            <div><strong>An toàn & riêng tư</strong><span>CSRF, chuẩn hoá URL, giới hạn tốc độ; IP chỉ lưu khi bạn cho phép.</span></div>
            <div><strong>Kích hoạt ngay</strong><span>Thanh toán xong là gói mở ngay, không chờ duyệt.</span></div>
            <div><strong>Xuất hoá đơn chuẩn</strong><span>Hoá đơn GTGT điện tử theo chuẩn Việt Nam, kèm mã số thuế.</span></div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="feat-section feat-alt">
    <div class="container">
        <div class="feat-heading">
            <span class="pill">Khách hàng nói gì</span>
            <h2>Được tin dùng bởi những người làm marketing</h2>
        </div>
        <div class="feat-testi">
            <figure>
                <blockquote>"Nhờ báo cáo theo quốc gia và thiết bị, tôi tối ưu được chiến dịch Facebook ngay trong tuần đầu — tỷ lệ bấm tăng 34%."</blockquote>
                <figcaption>Nguyễn Minh, Chủ shop online</figcaption>
            </figure>
            <figure>
                <blockquote>"Custom domain giúp link trông chuyên nghiệp hơn hẳn. Khách hàng tin tưởng và bấm nhiều hơn rõ rệt."</blockquote>
                <figcaption>Trần Hà, Agency quảng cáo</figcaption>
            </figure>
            <figure>
                <blockquote>"Xuất hoá đơn có mã số thuế đúng chuẩn, kế toán của tôi không còn phải chỉnh tay nữa."</blockquote>
                <figcaption>Lê Vân, Quản lý thương mại điện tử</figcaption>
            </figure>
        </div>
    </div>
</section>

<!-- PRICING -->
<section class="feat-section" id="bang-gia">
    <div class="container">
        <div class="feat-heading">
            <span class="pill">Chọn gói phù hợp</span>
            <h2>Đầu tư ít, lợi ích rõ ràng</h2>
            <p>Nâng cấp bất cứ lúc nào, gói kích hoạt ngay sau thanh toán.</p>
        </div>
        <div class="pricing-grid">
            <?php foreach ($plans as $p): if ((int) $p['is_active'] !== 1) continue; ?>
                <article class="pricing-card<?= $p['is_popular'] ? ' is-popular' : '' ?>">
                    <?php if ($p['is_popular']): ?><span class="pricing-badge">Được chọn nhiều</span><?php endif; ?>
                    <h2 class="pricing-card-name"><?= \App\escape($p['name']) ?></h2>
                    <p class="pricing-card-price"><?= \App\escape($priceLabel((float) $p['price'], (string) $p['currency'])) ?><small>/ <?= \App\escape($periodLabel((string) $p['billing_period'])) ?></small></p>
                    <p class="pricing-card-desc"><?= \App\escape((string) ($p['description'] ?? '')) ?></p>
                    <ul class="pricing-card-features">
                        <?php foreach ($planFeatures($p) as $f): ?><li><?= \App\escape($f) ?></li><?php endforeach; ?>
                    </ul>
                    <?php if ((float) $p['price'] <= 0): ?>
                        <a class="btn btn-ghost btn-block" href="<?= \App\url_for('dang-ky') ?>">Bắt đầu miễn phí</a>
                    <?php else: ?>
                        <a class="btn <?= $p['is_popular'] ? 'btn-primary' : 'btn-soft' ?> btn-block" href="<?= \App\url_for('thanh-toan') . '?plan=' . (int) $p['id'] ?>">Mua ngay</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="feat-cta">
    <div class="container">
        <h2>Sẵn sàng biến link thành kênh bán hàng?</h2>
        <p>Bắt đầu miễn phí trong 30 giây. Không cần thẻ, không ràng buộc.</p>
        <a class="btn btn-primary" href="<?= \App\url_for('dang-ky') ?>">Tạo tài khoản miễn phí</a>
    </div>
</section>
<?php echo \App\render('footer'); ?>
