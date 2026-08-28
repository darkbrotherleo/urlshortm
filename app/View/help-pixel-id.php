<?php
/** @var string $title */
echo \App\render('header', ['title' => $title]);
?>
<section class="section">
    <div class="container help-page">
        <span class="pill">Hướng dẫn</span>
        <h1 class="hero-title">Cách lấy Pixel ID của từng nền tảng</h1>
        <p class="hero-sub">
            Mỗi nền tảng quảng cáo cấp một mã Pixel riêng. Dưới đây là các bước
            tìm &amp; copy mã đó, rồi dán vào mục <strong>Tạo Pixel</strong> trong
            bảng điều khiển của bạn.
        </p>

        <?php
        $guides = [
            'Facebook / Meta' => [
                'Đăng nhập Facebook và vào tài khoản <strong>Ads Manager</strong>.',
                'Mở tab <strong>Measure &amp; Report</strong>, bấm <strong>Events Manager</strong>.',
                'Copy <strong>Pixel ID</strong> (chuỗi 15-16 chữ số). Chưa có Pixel? Tạo Pixel mới ngay trong Events Manager.',
            ],
            'Instagram' => [
                'Instagram dùng chung Pixel với Facebook/Meta.',
                'Làm theo các bước của Facebook ở trên, sau đó dùng đúng Pixel ID đó cho cả Instagram.',
            ],
            'Google Ads' => [
                'Đăng nhập tài khoản Google Ads.',
                'Chọn tab <strong>Tools</strong> (Công cụ), bấm <strong>Audience Manager</strong>.',
                'Bấm <strong>Audience Sources</strong>. Chưa có mã? Bấm <strong>Set up an audience source</strong> để tạo.',
                'Trong mục <strong>Adwords Tag</strong>, bấm <strong>set up tag</strong> rồi lưu.',
                'Copy mã số từ <strong>Global Site Tag - AdWords</strong> (định dạng AW-XXXXXX).',
            ],
            'Google Analytics 4' => [
                'Đăng nhập Google Analytics.',
                'Chọn <strong>Admin</strong> ở cuối trang, rồi chọn tài sản (Property).',
                'Vào <strong>Data Streams</strong> (Luồng dữ liệu), chọn luồng web của bạn.',
                'Copy <strong>Measurement ID</strong> (định dạng G-XXXXXXX).',
            ],
            'Google Tag Manager' => [
                'Đăng nhập <strong>Google Tag Manager</strong>.',
                'Mở container (thùng chứa) của bạn.',
                'Copy <strong>Container ID</strong> có tiền tố GTM (định dạng GTM-XXXXXXX).',
            ],
            'TikTok' => [
                'Đăng nhập <strong>TikTok Ads Manager</strong>.',
                'Chọn tab <strong>Assets</strong>, bấm <strong>Event</strong>.',
                'Trong mục <strong>Website Pixel</strong>, bấm <strong>Manage</strong>.',
                'Copy mã số Pixel. Chưa có? Bấm <strong>Create Pixel</strong> để tạo.',
            ],
            'Zalo' => [
                'Đăng nhập <strong>Zalo Ads</strong> (quảng cáo Zalo) bằng tài khoản Zalo OA.',
                'Vào mục <strong>Pixel</strong> / <strong>Events</strong> trong tài khoản Ads.',
                'Tạo pixel cho website của bạn rồi copy <strong>Pixel ID</strong> được cấp.',
            ],
            'Pinterest' => [
                'Đăng nhập <strong>Pinterest Ads Manager</strong>.',
                'Chọn tab <strong>Ads</strong>, rồi chọn <strong>Conversion</strong>.',
                'Bấm <strong>Create a tag</strong> để tạo, rồi bấm <strong>Done</strong>.',
                'Copy <strong>Pinterest Pixel ID</strong> (mã số dài).',
            ],
            'Snapchat' => [
                'Đăng nhập <strong>Snapchat Ads Manager</strong>.',
                'Vào mục <strong>Event Manager</strong>, chọn <strong>Snap Pixel</strong>.',
                'Tạo pixel cho website rồi copy <strong>Snap Pixel ID</strong>.',
            ],
        ];
        ?>

        <div class="help-grid">
            <?php foreach ($guides as $platform => $steps): ?>
                <article class="help-card">
                    <h2><?= \App\escape($platform) ?></h2>
                    <ol>
                        <?php foreach ($steps as $step): ?>
                            <li><?= $step ?></li>
                        <?php endforeach; ?>
                    </ol>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="dash-note help-note">
            Sau khi có Pixel ID, vào <strong>Cài đặt &#8594; Thiết lập Pixels</strong>, chọn
            nền tảng tương ứng, nhập tên và dán mã, rồi tick chọn Pixel khi tạo link.
            Nội dung hướng dẫn tham khảo từ trung tâm hỗ trợ Switchy (bộ sưu tập
            "Find &amp; add retargeting Pixels ID"), được biên dịch sang tiếng Việt.
        </aside>
    </div>
</section>
<?php echo \App\render('footer'); ?>
