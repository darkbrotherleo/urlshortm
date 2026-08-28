<?php
declare(strict_types=1);

/**
 * Sinh bộ preview ADMIN (quản trị hệ thống) — layout + data cứng để kiểm tra.
 * Thiết kế khác biệt với Dashboard User (sidebar tối, accent indigo),
 * nhưng cùng cấu trúc: sidebar trái + top bar + content.
 * CLI: php scripts/generate-admin-preview.php
 */

if (PHP_SAPI !== 'cli') {
    exit('cli only');
}

$root = dirname(__DIR__);
$out = $root . '/preview/admin';

if (!is_dir($out)) {
    mkdir($out, 0777, true);
}
foreach (glob($out . '/*.html') ?: [] as $f) {
    unlink($f);
}
if (!is_dir($out . '/assets')) {
    mkdir($out . '/assets', 0777, true);
}
copy($root . '/assets/js/vendor/chart.umd.min.js', $out . '/assets/chart.umd.min.js');
copy($root . '/assets/css/admin.css', $out . '/admin.css');
copy($root . '/assets/js/admin.js', $out . '/assets/admin.js');

$nav = [
    ['num' => '01', 'label' => 'Tổng quan', 'file' => 'index.html', 'subs' => ['Tổng quan hệ thống', 'Thống kê nhanh (Users, Links, Clicks, Doanh thu)', 'Biểu đồ người dùng mới', 'Biểu đồ link được tạo', 'Biểu đồ click theo ngày/tháng/năm', 'Biểu đồ doanh thu']],
    ['num' => '02', 'label' => 'Quản lý Người dùng', 'file' => 'users.html', 'subs' => ['Danh sách Users', 'Thêm User', 'Chi tiết / Sửa User', 'Ban / Unban User', 'Nâng / Hạ gói cho User', 'Lịch sử hoạt động User']],
    ['num' => '03', 'label' => 'Quản lý Admin', 'file' => 'admins.html', 'subs' => ['Danh sách Admin', 'Thêm Admin', 'Phân quyền Admin', 'Super Admin (duy nhất)']],
    ['num' => '04', 'label' => 'Quản lý Link (URL)', 'file' => 'links.html', 'subs' => ['Tất cả Link rút gọn', 'Tìm kiếm / Lọc Link', 'Chi tiết Link', 'Sửa Link', 'Ẩn / Hiện Link', 'Xoá Link', 'Thống kê click của Link']],
    ['num' => '05', 'label' => 'Quản lý Folder', 'file' => 'folders.html', 'subs' => ['Danh sách Folder', 'Tạo / Sửa / Xoá Folder']],
    ['num' => '06', 'label' => 'Gói dịch vụ', 'file' => 'packages.html', 'subs' => ['Danh sách Gói', 'Thêm / Sửa / Xoá Gói', 'Cấu hình tính năng từng gói']],
    ['num' => '07', 'label' => 'Đơn hàng / Thanh toán', 'file' => 'orders.html', 'subs' => ['Danh sách đơn hàng', 'Chi tiết đơn hàng', 'Lịch sử thanh toán', 'Biểu đồ doanh thu theo gói']],
    ['num' => '08', 'label' => 'Quản lý Voucher', 'file' => 'vouchers.html', 'subs' => ['Danh sách Voucher', 'Tạo Voucher (% hoặc giảm tiền)', 'Số lượng & thời hạn', 'Thống kê sử dụng Voucher']],
    ['num' => '09', 'label' => 'Quản lý Domain', 'file' => 'domains.html', 'subs' => ['Domain hệ thống (mặc định)', 'Domain của User (Custom Domain)', 'Kiểm tra trạng thái Domain', 'Xác minh Domain']],
    ['num' => '10', 'label' => 'Pixel & UTM', 'file' => 'pixels.html', 'subs' => ['Danh sách Pixel của User', 'Danh sách UTM Preset']],
    ['num' => '11', 'label' => 'Cài đặt Website', 'file' => 'settings.html', 'subs' => ['Thông tin chung', 'SEO', 'Mã xác minh', 'Mã theo dõi', 'Chèn mã tùy chỉnh', 'Mạng xã hội', 'Footer', 'SMTP / Email', 'Thanh toán']],
    ['num' => '12', 'label' => 'Nhật ký hệ thống', 'file' => 'logs.html', 'subs' => ['Activity Log', 'Lịch sử đăng nhập', 'Lỗi hệ thống']],
    ['num' => '13', 'label' => 'Thông báo & Hỗ trợ', 'file' => 'notifications.html', 'subs' => ['Gửi thông báo đến User', 'Quản lý Ticket hỗ trợ']],
];

foreach (pageBodies() as $file => $body) {
    file_put_contents($out . '/' . $file, shell($file, $nav, $body));
    echo "ok  $file\n";
}

echo "DONE: preview/admin generated\n";

function shell(string $file, array $nav, string $body): string
{
    $activeNum = '';
    foreach ($nav as $item) {
        if ($item['file'] === $file) {
            $activeNum = $item['num'];
            break;
        }
    }

    $items = '';
    foreach ($nav as $item) {
        $isActive = $item['file'] === $file;
        $subs = '';
        foreach ($item['subs'] as $s) {
            $subs .= '<a class="a-nav-sub" href="#">' . $s . '</a>';
        }
        $items .= '<div class="a-nav-group">'
            . '<a class="a-nav-item' . ($isActive ? ' is-active' : '') . '" href="' . $item['file'] . '"><span class="a-nav-num">' . $item['num'] . '</span>' . $item['label'] . '</a>'
            . '<div class="a-nav-subwrap">' . $subs . '</div>'
            . '</div>';
    }

    $crumbs = 'Quản trị hệ thống';
    $title = 'Tổng quan';
    foreach ($nav as $item) {
        if ($item['file'] === $file) {
            $title = $item['label'];
            break;
        }
    }

    return '<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin — ' . $title . ' | UrlShortM</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="adash">

    <aside class="adash-side">
        <div class="a-side-head">
            <span class="a-logo-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
            <div class="a-logo-text">UrlShortM<small>Admin Panel</small></div>
        </div>
        <div class="a-admin">
            <span class="a-admin-avatar" aria-hidden="true">Q</span>
            <div class="a-admin-meta">
                <strong>Quản trị viên</strong>
                <span class="a-admin-role">admin@vidu.vn</span>
                <span class="a-admin-status"><span class="a-admin-dot" aria-hidden="true"></span> Đang trực</span>
            </div>
            <span class="a-badge-super">Super</span>
        </div>
        <nav class="a-nav" aria-label="Menu quản trị">
            ' . $items . '
        </nav>
        <div class="a-side-foot">
            <a class="a-side-link" href="../index.html">&#8592; Về trang chủ</a>
            <a class="a-side-link" href="../landing.html">&#8592; Xem Landing</a>
        </div>
    </aside>

    <div class="adash-main">
        <header class="a-top">
            <div>
                <p class="a-crumb">// ' . $crumbs . '</p>
                <h1 class="a-title">' . $title . '</h1>
            </div>
            <div class="a-tape" aria-hidden="true">
                <span>1.284 user</span><span>///</span><span>4.910 link</span><span>///</span>
                <span class="a-tape-live"><span class="a-pulse" aria-hidden="true"></span> hệ thống đang chạy</span>
            </div>
        </header>

        <div class="a-content">
            ' . $body . '
        </div>
    </div>
</div>
<script src="assets/chart.umd.min.js"></script>
<script src="assets/admin.js"></script>
</body>
</html>';
}

function pageBodies(): array
{
    return [
        'index.html' => <<<'HTML'
<div class="a-grid a-stats">
    <article class="a-card a-stat"><span class="a-stat-label">Người dùng</span><strong class="a-stat-num">1.284</strong><span class="a-stat-delta up">+12%</span></article>
    <article class="a-card a-stat"><span class="a-stat-label">Link rút gọn</span><strong class="a-stat-num">4.910</strong><span class="a-stat-delta up">+8%</span></article>
    <article class="a-card a-stat"><span class="a-stat-label">Lượt mở (clicks)</span><strong class="a-stat-num">286.540</strong><span class="a-stat-delta up">+23%</span></article>
    <article class="a-card a-stat"><span class="a-stat-label">Doanh thu</span><strong class="a-stat-num">86,5M₫</strong><span class="a-stat-delta up">+31%</span></article>
</div>

<div class="a-grid a-charts">
    <section class="a-card a-chart a-chart-wide">
        <div class="a-card-head"><h2>Người dùng mới</h2></div>
        <div class="a-canvas"><canvas id="chart-users-new"></canvas></div>
    </section>
    <section class="a-card a-chart">
        <div class="a-card-head"><h2>Link được tạo</h2></div>
        <div class="a-canvas"><canvas id="chart-links-created"></canvas></div>
    </section>
    <section class="a-card a-chart">
        <div class="a-card-head"><h2>Click theo ngày</h2></div>
        <div class="a-canvas"><canvas id="chart-clicks-day"></canvas></div>
    </section>
    <section class="a-card a-chart">
        <div class="a-card-head"><h2>Doanh thu theo gói</h2></div>
        <div class="a-canvas"><canvas id="chart-revenue-plan"></canvas></div>
    </section>
    <section class="a-card a-chart a-chart-wide">
        <div class="a-card-head"><h2>Hoạt động gần đây</h2></div>
        <table class="a-table">
            <thead><tr><th>Thời gian</th><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th></tr></thead>
            <tbody>
                <tr><td class="a-date">2026-08-27 09:12</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td>Nâng gói Pro</td><td>user #12</td></tr>
                <tr><td class="a-date">2026-08-27 08:47</td><td class="a-pixels">admin@vidu.vn</td><td>Xoá Link</td><td>slug <code>Ab3x9Q</code></td></tr>
                <tr><td class="a-date">2026-08-27 08:20</td><td class="a-pixels">user@shop.vn</td><td>Đăng ký tài khoản</td><td>user #1284</td></tr>
                <tr><td class="a-date">2026-08-27 07:55</td><td class="a-pixels">admin@vidu.vn</td><td>Ban User (vi phạm)</td><td>user #977</td></tr>
                <tr><td class="a-date">2026-08-27 07:31</td><td class="a-pixels">thanh@ads.vn</td><td>Tạo Link</td><td>slug <code>tT9zK4</code></td></tr>
            </tbody>
        </table>
    </section>
</div>
<script id="admin-chart-data" type="application/json">{"users":[12,18,15,22,27,31,29,40,38,45],"links":[30,42,51,48,66,72,80,75,90,96],"clicks":[420,510,480,620,710,690,820,940,1010,1120],"revenue":[{"label":"Starter","value":42},{"label":"Pro","value":31},{"label":"Free","value":0},{"label":"Trial","value":27}]}</script>
HTML,

        'users.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head">
        <h2>Danh sách Users</h2>
        <a class="a-btn a-btn-primary" href="#">+ Thêm User</a>
    </div>
    <div class="a-toolbar">
        <input type="search" placeholder="Tìm kiếm email / tên..." class="a-input">
        <select class="a-input"><option>Gói: Tất cả</option><option>Free</option><option>Starter</option><option>Pro</option></select>
        <select class="a-input"><option>Trạng thái: Tất cả</option><option>Hoạt động</option><option>Bị ban</option></select>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Người dùng</th><th>Gói</th><th>Links</th><th>Clicks</th><th>Trạng thái</th><th>Tham gia</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td>1284</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td><span class="a-badge">Pro</span></td><td>12</td><td>4.210</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-date">2026-08-27</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Ban</a><a href="#">Gói</a></td></tr>
                <tr><td>1283</td><td class="a-pixels">thanh@ads.vn</td><td><span class="a-badge">Starter</span></td><td>6</td><td>980</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-date">2026-08-26</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Ban</a><a href="#">Gói</a></td></tr>
                <tr><td>1282</td><td class="a-pixels">shop@happy.vn</td><td><span class="a-badge">Pro</span></td><td>34</td><td>12.450</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-date">2026-08-25</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Ban</a><a href="#">Gói</a></td></tr>
                <tr><td>977</td><td class="a-pixels">spam@example.com</td><td><span class="a-badge">Free</span></td><td>1</td><td>5</td><td><span class="a-pill bad">Bị ban</span></td><td class="a-date">2026-06-11</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Unban</a><a href="#">Gói</a></td></tr>
                <tr><td>976</td><td class="a-pixels">cty@demo.vn</td><td><span class="a-badge">Starter</span></td><td>9</td><td>1.120</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-date">2026-06-10</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Ban</a><a href="#">Gói</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'admins.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Danh sách Admin</h2><a class="a-btn a-btn-primary" href="#">+ Thêm Admin</a></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Admin</th><th>Vai trò</th><th>Quyền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td>1</td><td class="a-pixels">admin@vidu.vn</td><td><span class="a-badge super">Super Admin</span></td><td>Tất cả</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-actions"><a href="#">Phân quyền</a></td></tr>
                <tr><td>2</td><td class="a-pixels">mod1@vidu.vn</td><td><span class="a-badge">Quản trị viên</span></td><td>User, Link, Folder</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-actions"><a href="#">Phân quyền</a></td></tr>
                <tr><td>3</td><td class="a-pixels">mod2@vidu.vn</td><td><span class="a-badge">Quản trị viên</span></td><td>Đơn hàng, Voucher</td><td><span class="a-pill bad">Bị khoá</span></td><td class="a-actions"><a href="#">Phân quyền</a></td></tr>
            </tbody>
        </table>
    </div>
    <p class="a-hint">Lưu ý: <b>Super Admin là duy nhất</b> — chỉ 1 tài khoản có toàn quyền hệ thống.</p>
</section>
HTML,

        'links.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Tất cả Link rút gọn</h2></div>
    <div class="a-toolbar">
        <input type="search" placeholder="Tìm kiếm slug / URL đích..." class="a-input">
        <select class="a-input"><option>Trạng thái: Tất cả</option><option>Hiển thị</option><option>Bị ẩn</option></select>
        <select class="a-input"><option>Người sở hữu: Tất cả</option></select>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Slug</th><th>URL đích</th><th>Chủ sở hữu</th><th>Clicks</th><th>Trạng thái</th><th>Tạo lúc</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td><code>fB8xk2</code></td><td class="a-pixels">https://banhang.shopee.vn/...</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td>4.210</td><td><span class="a-pill ok">Hiển thị</span></td><td class="a-date">2026-08-10</td><td class="a-actions"><a href="#">Chi tiết</a><a href="#">Sửa</a><a href="#">Ẩn</a><a href="#">Xoá</a></td></tr>
                <tr><td><code>tT9zK4</code></td><td class="a-pixels">https://shopee.vn/flash-sale...</td><td class="a-pixels">thanh@ads.vn</td><td>980</td><td><span class="a-pill ok">Hiển thị</span></td><td class="a-date">2026-08-12</td><td class="a-actions"><a href="#">Chi tiết</a><a href="#">Sửa</a><a href="#">Ẩn</a><a href="#">Xoá</a></td></tr>
                <tr><td><code>Ab3x9Q</code></td><td class="a-pixels">https://example.com/link-vip</td><td class="a-pixels">shop@happy.vn</td><td>12.450</td><td><span class="a-pill bad">Bị ẩn</span></td><td class="a-date">2026-07-01</td><td class="a-actions"><a href="#">Chi tiết</a><a href="#">Sửa</a><a href="#">Hiện</a><a href="#">Xoá</a></td></tr>
                <tr><td><code>wC2pN5</code></td><td class="a-pixels">https://wa.me/84912345678</td><td class="a-pixels">cty@demo.vn</td><td>1.120</td><td><span class="a-pill ok">Hiển thị</span></td><td class="a-date">2026-08-05</td><td class="a-actions"><a href="#">Chi tiết</a><a href="#">Sửa</a><a href="#">Ẩn</a><a href="#">Xoá</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'folders.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Danh sách Folder</h2><a class="a-btn a-btn-primary" href="#">+ Tạo Folder</a></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Folder</th><th>Chủ sở hữu</th><th>Số link</th><th>Tạo lúc</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td>1</td><td class="a-pixels">Facebook</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td>12</td><td class="a-date">2026-08-01</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Xoá</a></td></tr>
                <tr><td>2</td><td class="a-pixels">TikTok</td><td class="a-pixels">thanh@ads.vn</td><td>6</td><td class="a-date">2026-08-02</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Xoá</a></td></tr>
                <tr><td>3</td><td class="a-pixels">Google Ads</td><td class="a-pixels">shop@happy.vn</td><td>18</td><td class="a-date">2026-07-20</td><td class="a-actions"><a href="#">Sửa</a><a href="#">Xoá</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'packages.html' => <<<'HTML'
<div class="a-grid a-stats">
    <section class="a-card a-plan">
        <div class="a-card-head"><h2>Free</h2></div>
        <p class="a-plan-price">0₫<small>/tháng</small></p>
        <ul class="a-plan-features">
            <li>10 link / tháng</li><li>1.000 click / tháng</li><li>1 custom domain</li><li>1 pixel</li>
        </ul>
        <a class="a-btn a-btn-soft" href="#">Sửa gói</a>
    </section>
    <section class="a-card a-plan">
        <div class="a-card-head"><h2>Starter</h2><span class="a-badge">Phổ biến</span></div>
        <p class="a-plan-price">49.000₫<small>/tháng</small></p>
        <ul class="a-plan-features">
            <li>500 link / tháng</li><li>50.000 click / tháng</li><li>3 custom domain</li><li>5 pixel</li><li>Xuất hoá đơn</li>
        </ul>
        <a class="a-btn a-btn-soft" href="#">Sửa gói</a>
    </section>
    <section class="a-card a-plan">
        <div class="a-card-head"><h2>Pro</h2></div>
        <p class="a-plan-price">149.000₫<small>/tháng</small></p>
        <ul class="a-plan-features">
            <li>Không giới hạn link</li><li>Không giới hạn click</li><li>10 custom domain</li><li>Không giới hạn pixel</li><li>Xuất hoá đơn + mã số thuế</li>
        </ul>
        <a class="a-btn a-btn-soft" href="#">Sửa gói</a>
    </section>
</div>
<section class="a-card">
    <div class="a-card-head"><h2>Cấu hình tính năng từng gói</h2><a class="a-btn a-btn-primary" href="#">+ Thêm Gói</a></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Tính năng</th><th>Free</th><th>Starter</th><th>Pro</th></tr></thead>
            <tbody>
                <tr><td class="a-pixels">Số link tối đa</td><td>10</td><td>500</td><td>Không giới hạn</td></tr>
                <tr><td class="a-pixels">Số click tối đa</td><td>1.000</td><td>50.000</td><td>Không giới hạn</td></tr>
                <tr><td class="a-pixels">Custom domain</td><td>1</td><td>3</td><td>10</td></tr>
                <tr><td class="a-pixels">Pixel / UTM</td><td>1</td><td>5</td><td>Không giới hạn</td></tr>
                <tr><td class="a-pixels">Xuất hoá đơn</td><td>&#10005;</td><td>&#10003;</td><td>&#10003;</td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'orders.html' => <<<'HTML'
<div class="a-grid a-stats">
    <article class="a-card a-stat"><span class="a-stat-label">Đơn hàng</span><strong class="a-stat-num">346</strong><span class="a-stat-delta up">+9%</span></article>
    <article class="a-card a-stat"><span class="a-stat-label">Đã thanh toán</span><strong class="a-stat-num">298</strong><span class="a-stat-delta up">+11%</span></article>
    <article class="a-card a-stat"><span class="a-stat-label">Doanh thu tháng</span><strong class="a-stat-num">86,5M₫</strong><span class="a-stat-delta up">+31%</span></article>
</div>
<section class="a-card">
    <div class="a-card-head"><h2>Lịch sử thanh toán</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Mã đơn</th><th>Người dùng</th><th>Gói</th><th>Giá</th><th>Phương thức</th><th>Trạng thái</th><th>Ngày</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td><code>DH-1042</code></td><td class="a-pixels">nguyenphatseo@gmail.com</td><td><span class="a-badge">Pro</span></td><td>149.000₫</td><td>VNPay</td><td><span class="a-pill ok">Thành công</span></td><td class="a-date">2026-08-27</td><td class="a-actions"><a href="#">Chi tiết</a></td></tr>
                <tr><td><code>DH-1041</code></td><td class="a-pixels">shop@happy.vn</td><td><span class="a-badge">Starter</span></td><td>49.000₫</td><td>MoMo</td><td><span class="a-pill ok">Thành công</span></td><td class="a-date">2026-08-26</td><td class="a-actions"><a href="#">Chi tiết</a></td></tr>
                <tr><td><code>DH-1040</code></td><td class="a-pixels">cty@demo.vn</td><td><span class="a-badge">Starter</span></td><td>49.000₫</td><td>Chuyển khoản</td><td><span class="a-pill warn">Chờ duyệt</span></td><td class="a-date">2026-08-26</td><td class="a-actions"><a href="#">Chi tiết</a></td></tr>
                <tr><td><code>DH-1039</code></td><td class="a-pixels">thanh@ads.vn</td><td><span class="a-badge">Pro</span></td><td>149.000₫</td><td>PayPal</td><td><span class="a-pill bad">Thất bại</span></td><td class="a-date">2026-08-25</td><td class="a-actions"><a href="#">Chi tiết</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'vouchers.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Danh sách Voucher</h2><a class="a-btn a-btn-primary" href="#">+ Tạo Voucher</a></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Mã</th><th>Loại</th><th>Giá trị</th><th>Số lượng</th><th>Đã dùng</th><th>Hạn dùng</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td><code>GIAM10</code></td><td>Giảm %</td><td>10%</td><td>500</td><td>312</td><td class="a-date">2026-12-31</td><td><span class="a-pill ok">Đang chạy</span></td><td class="a-actions"><a href="#">Sửa</a><a href="#">Thống kê</a></td></tr>
                <tr><td><code>50KDAU</code></td><td>Giảm tiền</td><td>50.000₫</td><td>200</td><td>87</td><td class="a-date">2026-09-30</td><td><span class="a-pill ok">Đang chạy</span></td><td class="a-actions"><a href="#">Sửa</a><a href="#">Thống kê</a></td></tr>
                <tr><td><code>HE2008</code></td><td>Giảm %</td><td>20%</td><td>100</td><td>100</td><td class="a-date">2026-08-10</td><td><span class="a-pill bad">Hết hạn</span></td><td class="a-actions"><a href="#">Sửa</a><a href="#">Thống kê</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'domains.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Domain hệ thống (mặc định)</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Domain</th><th>Loại</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td><code>urlshortm.com</code></td><td>Hệ thống</td><td><span class="a-pill ok">Đang chạy</span></td><td class="a-actions"><a href="#">Cấu hình</a></td></tr>
                <tr><td><code>links.urlshortm.com</code></td><td>Relay</td><td><span class="a-pill ok">Đang chạy</span></td><td class="a-actions"><a href="#">Cấu hình</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Custom Domain của User</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Domain</th><th>Chủ sở hữu</th><th>Trạng thái</th><th>Kiểm tra DNS</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td><code>link.mark.test</code></td><td class="a-pixels">nguyenphatseo@gmail.com</td><td><span class="a-pill ok">Đã xác minh</span></td><td class="a-date">2026-08-27 09:00</td><td class="a-actions"><a href="#">Xác minh</a><a href="#">Kiểm tra</a></td></tr>
                <tr><td><code>short.cty.vn</code></td><td class="a-pixels">cty@demo.vn</td><td><span class="a-pill warn">Chờ xác minh</span></td><td class="a-date">—</td><td class="a-actions"><a href="#">Xác minh</a><a href="#">Kiểm tra</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'pixels.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Pixel của User</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Pixel</th><th>Platform</th><th>Chủ sở hữu</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td>1</td><td><code>fB8xk2pix</code></td><td>Facebook</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-actions"><a href="#">Xem</a><a href="#">Vô hiệu</a></td></tr>
                <tr><td>2</td><td><code>tt-trend</code></td><td>TikTok</td><td class="a-pixels">thanh@ads.vn</td><td><span class="a-pill ok">Hoạt động</span></td><td class="a-actions"><a href="#">Xem</a><a href="#">Vô hiệu</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>UTM Preset</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Tên preset</th><th>Campaign</th><th>Source</th><th>Medium</th><th>Chủ sở hữu</th></tr></thead>
            <tbody>
                <tr><td>1</td><td class="a-pixels">FB Quảng cáo tháng 8</td><td>fb-camp-08</td><td>facebook</td><td>cpc</td><td class="a-pixels">nguyenphatseo@gmail.com</td></tr>
                <tr><td>2</td><td class="a-pixels">TikTok Trending</td><td>tt-sale-99</td><td>tiktok</td><td>paid</td><td class="a-pixels">thanh@ads.vn</td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'settings.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Thông tin chung</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Tên website</label><input class="a-input" value="UrlShortM"></div>
        <div class="a-form-row"><label>Mô tả website</label><textarea class="a-input">Rút gọn link dễ dàng, biết rõ ai đã bấm vào — nhẹ nhàng, miễn phí.</textarea></div>
        <div class="a-form-row"><label>Logo / Favicon</label><input class="a-input" type="file"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>SEO</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Meta Title</label><input class="a-input" value="UrlShortM — Rút gọn & theo dõi link"></div>
        <div class="a-form-row"><label>Meta Description</label><textarea class="a-input">Rút gọn URL dài thành link ngắn, theo dõi lượt mở, nhân khẩu học.</textarea></div>
        <div class="a-form-row"><label class="a-switch">Index / NoIndex<input type="checkbox" checked><span></span></label></div>
        <div class="a-form-row"><label class="a-switch">Cho phép AI Crawler (GPTBot, ClaudeBot...) <input type="checkbox" checked><span></span></label></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Mã xác minh</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Google Search Console</label><input class="a-input" placeholder="&lt;meta name=&quot;google-site-verification&quot;...&gt;"></div>
        <div class="a-form-row"><label>Bing Webmaster Tools</label><input class="a-input" placeholder="&lt;meta name=&quot;msvalidate.01&quot;...&gt;"></div>
        <div class="a-form-row"><label>Yandex</label><input class="a-input" placeholder="&lt;meta name=&quot;yandex-verification&quot;...&gt;"></div>
        <div class="a-form-row"><label>Baidu</label><input class="a-input" placeholder="&lt;meta name=&quot;baidu-site-verification&quot;...&gt;"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Mã theo dõi</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Google Analytics 4 (GA4)</label><input class="a-input" value="G-XXXXXXXXXX"></div>
        <div class="a-form-row"><label>Google Tag Manager</label><input class="a-input" value="GTM-XXXXXXX"></div>
        <div class="a-form-row"><label>Meta Pixel</label><input class="a-input" value="123456789012345"></div>
        <div class="a-form-row"><label class="a-switch">IndexNow (bật/tắt) <input type="checkbox" checked><span></span></label></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Chèn mã tùy chỉnh</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Code trong &lt;head&gt;</label><textarea class="a-input a-code" rows="3">&lt;!-- head --&gt;</textarea></div>
        <div class="a-form-row"><label>Code trong &lt;body&gt;</label><textarea class="a-input a-code" rows="3">&lt;!-- body --&gt;</textarea></div>
        <div class="a-form-row"><label>Code trong &lt;footer&gt;</label><textarea class="a-input a-code" rows="3">&lt;!-- footer --&gt;</textarea></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Mạng xã hội</h2></div>
    <form class="a-form a-form-grid" onsubmit="return false;">
        <div class="a-form-row"><label>Facebook</label><input class="a-input" value="https://facebook.com/urlshortm"></div>
        <div class="a-form-row"><label>YouTube</label><input class="a-input" value="https://youtube.com/@urlshortm"></div>
        <div class="a-form-row"><label>TikTok</label><input class="a-input" value="https://tiktok.com/@urlshortm"></div>
        <div class="a-form-row"><label>Instagram</label><input class="a-input" value="https://instagram.com/urlshortm"></div>
        <div class="a-form-row"><label>Zalo</label><input class="a-input" value="https://zalo.me/urlshortm"></div>
        <div class="a-form-row"><label>X (Twitter)</label><input class="a-input" value="https://x.com/urlshortm"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Footer</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Nội dung footer</label><textarea class="a-input">Dịch vụ rút gọn &amp; theo dõi link</textarea></div>
        <div class="a-form-row"><label>Copyright</label><input class="a-input" value="© 2026 UrlShortM"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>SMTP / Email</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Host</label><input class="a-input" value="smtp.gmail.com"></div>
        <div class="a-form-row"><label>Port</label><input class="a-input" value="587"></div>
        <div class="a-form-row"><label>Username</label><input class="a-input" value="no-reply@vidu.vn"></div>
        <div class="a-form-row"><label>Mật khẩu / App Password</label><input class="a-input" type="password" value="••••••••"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Thanh toán</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>PayPal</label><input class="a-input" value="merchant@vidu.vn"></div>
        <div class="a-form-row"><label>VNPay</label><input class="a-input" value="Terminal: VNPAYKQRR"></div>
        <div class="a-form-row"><label>MoMo</label><input class="a-input" value="Partner: MOMOxxxx"></div>
        <div class="a-form-row"><label>Số tài khoản ngân hàng (chuyển khoản)</label><input class="a-input" value="Vietcombank 0123456789 - CTY VIDU"></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Lưu</button></div>
    </form>
</section>
HTML,

        'logs.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Activity Log (hành động của User & Admin)</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Vai trò</th><th>Hành động</th><th>IP</th></tr></thead>
            <tbody>
                <tr><td class="a-date">2026-08-27 09:12</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td>User</td><td>Nâng gói Pro</td><td>113.160.1.2</td></tr>
                <tr><td class="a-date">2026-08-27 08:47</td><td class="a-pixels">admin@vidu.vn</td><td>Super Admin</td><td>Xoá link Ab3x9Q</td><td>127.0.0.1</td></tr>
                <tr><td class="a-date">2026-08-27 08:20</td><td class="a-pixels">user@shop.vn</td><td>User</td><td>Đăng ký tài khoản</td><td>157.240.2.3</td></tr>
            </tbody>
        </table>
    </div>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Lịch sử đăng nhập</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Thời gian</th><th>Email</th><th>Kết quả</th><th>IP</th></tr></thead>
            <tbody>
                <tr><td class="a-date">2026-08-27 09:10</td><td class="a-pixels">admin@vidu.vn</td><td><span class="a-pill ok">Thành công</span></td><td>127.0.0.1</td></tr>
                <tr><td class="a-date">2026-08-27 08:55</td><td class="a-pixels">spam@example.com</td><td><span class="a-pill bad">Sai mật khẩu</span></td><td>27.1.1.1</td></tr>
            </tbody>
        </table>
    </div>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Lỗi hệ thống</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Thời gian</th><th>Cấp độ</th><th>Nội dung</th></tr></thead>
            <tbody>
                <tr><td class="a-date">2026-08-27 06:02</td><td><span class="a-pill warn">WARN</span></td><td class="a-pixels">CountryLookup: geoip dataset thiếu dải IP</td></tr>
                <tr><td class="a-date">2026-08-26 22:40</td><td><span class="a-pill bad">ERROR</span></td><td class="a-pixels">Meta API: Invalid OAuth token</td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,

        'notifications.html' => <<<'HTML'
<section class="a-card">
    <div class="a-card-head"><h2>Gửi thông báo đến User</h2></div>
    <form class="a-form" onsubmit="return false;">
        <div class="a-form-row"><label>Đối tượng</label><select class="a-input"><option>Tất cả user</option><option>User gói Pro</option><option>User gói Starter</option><option>Chọn riêng...</option></select></div>
        <div class="a-form-row"><label>Tiêu đề</label><input class="a-input" value=""></div>
        <div class="a-form-row"><label>Nội dung</label><textarea class="a-input" rows="4"></textarea></div>
        <div class="a-form-row"><label class="a-switch">Gửi qua Email <input type="checkbox" checked><span></span></label></div>
        <div class="a-form-row"><label class="a-switch">Gửi qua Thông báo trong trang <input type="checkbox" checked><span></span></label></div>
        <div class="a-form-row"><button class="a-btn a-btn-primary" type="submit">Gửi thông báo</button></div>
    </form>
</section>
<section class="a-card">
    <div class="a-card-head"><h2>Ticket hỗ trợ</h2></div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>#</th><th>Người gửi</th><th>Chủ đề</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr><td>T-42</td><td class="a-pixels">nguyenphatseo@gmail.com</td><td class="a-pixels">Không xuất được hoá đơn</td><td><span class="a-pill warn">Đang xử lý</span></td><td class="a-date">2026-08-27 08:40</td><td class="a-actions"><a href="#">Trả lời</a></td></tr>
                <tr><td>T-41</td><td class="a-pixels">thanh@ads.vn</td><td class="a-pixels">Custom domain không verify</td><td><span class="a-pill ok">Đã xong</span></td><td class="a-date">2026-08-26 17:20</td><td class="a-actions"><a href="#">Trả lời</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
HTML,
    ];
}

function admin_css(): string
{
    return <<<'CSS'
:root {
    --abg: #EEF2F7;
    --asurface: #FFFFFF;
    --aink: #0F172A;
    --amuted: #64748B;
    --aline: rgba(15, 23, 42, 0.08);
    --aaccent: #6366F1;
    --aaccent2: #8B5CF6;
    --aok: #10B981;
    --abad: #EF4444;
    --awarn: #F59E0B;
    --aside-bg: #0F172A;
    --aside-ink: #94A3B8;
    --afont: "Segoe UI", system-ui, -apple-system, sans-serif;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--abg); color: var(--aink); font-family: var(--afont); }
.adash { display: flex; min-height: 100vh; }

/* Sidebar */
.adash-side { width: 276px; background: var(--aside-bg); color: var(--aside-ink); display: flex; flex-direction: column; position: fixed; inset: 0 auto 0 0; overflow-y: auto; z-index: 20; }
.a-side-head { display: flex; align-items: center; gap: 0.7rem; padding: 1.15rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.a-logo-mark { width: 30px; height: 30px; border-radius: 9px; background: linear-gradient(135deg, var(--aaccent), var(--aaccent2)); display: inline-block; }
.a-logo-text { font-weight: 700; color: #fff; font-size: 1.02rem; display: flex; flex-direction: column; line-height: 1.1; }
.a-logo-text small { color: var(--aaccent2); font-size: 0.7rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; }
.a-admin { display: flex; align-items: center; gap: 0.7rem; padding: 1rem 1.25rem; }
.a-admin-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--aaccent); color: #fff; display: grid; place-items: center; font-weight: 700; }
.a-admin-meta { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.a-admin-meta strong { color: #fff; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.a-admin-meta small { font-size: 0.75rem; }
.a-badge-super { margin-left: auto; font-size: 0.62rem; font-weight: 700; padding: 0.18rem 0.45rem; border-radius: 99px; background: linear-gradient(135deg, var(--aaccent), var(--aaccent2)); color: #fff; text-transform: uppercase; }

.a-nav { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.5rem 0.85rem 1rem; }
.a-nav-group { display: flex; flex-direction: column; }
.a-nav-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.7rem; border-radius: 10px; color: var(--aside-ink); text-decoration: none; font-size: 0.88rem; font-weight: 600; }
.a-nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
.a-nav-item.is-active { background: var(--aaccent); color: #fff; box-shadow: 0 6px 18px -6px rgba(99,102,241,0.6); }
.a-nav-num { font-size: 0.66rem; font-weight: 700; opacity: 0.55; min-width: 1.5rem; }
.a-nav-item.is-active .a-nav-num { opacity: 0.85; }
.a-nav-subwrap { display: flex; flex-direction: column; padding: 0.15rem 0 0.35rem 2.4rem; gap: 0.1rem; }
.a-nav-sub { padding: 0.28rem 0.4rem; font-size: 0.78rem; color: #64748B; text-decoration: none; border-radius: 6px; }
.a-nav-sub:hover { color: #fff; }
.a-side-foot { margin-top: auto; padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,0.06); display: flex; flex-direction: column; gap: 0.4rem; }
.a-side-link { color: #64748B; font-size: 0.8rem; text-decoration: none; }
.a-side-link:hover { color: #fff; }

/* Main */
.adash-main { margin-left: 276px; flex: 1; min-width: 0; display: flex; flex-direction: column; }
.a-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.3rem 1.8rem 1rem; }
.a-crumb { margin: 0; color: var(--amuted); font-size: 0.78rem; letter-spacing: 0.03em; }
.a-title { margin: 0.15rem 0 0; font-size: 1.5rem; font-weight: 700; }
.a-tape { display: flex; align-items: center; gap: 0.7rem; color: var(--amuted); font-size: 0.8rem; }
.a-tape-live { display: inline-flex; align-items: center; gap: 0.4rem; }
.a-pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--aok); box-shadow: 0 0 0 0 rgba(16,185,129,0.5); animation: aPulse 1.6s infinite; }
@keyframes aPulse { 0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); } 70% { box-shadow: 0 0 0 8px rgba(16,185,129,0); } 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); } }

.a-content { padding: 0.5rem 1.8rem 2.2rem; display: flex; flex-direction: column; gap: 1.4rem; }

/* Cards */
.a-card { background: var(--asurface); border: 1px solid var(--aline); border-radius: 14px; box-shadow: 0 1px 2px rgba(15,23,42,0.04); overflow: hidden; min-width: 0; }
.a-card-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.3rem; border-bottom: 1px solid var(--aline); }
.a-card-head h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.a-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.4rem; }
.a-grid.a-stats { grid-template-columns: repeat(4, 1fr); }
.a-chart-wide { grid-column: 1 / -1; }
.a-stat { padding: 1.2rem 1.3rem; }
.a-stat-label { color: var(--amuted); font-size: 0.8rem; font-weight: 600; }
.a-stat-num { display: block; font-size: 1.7rem; font-weight: 800; margin: 0.3rem 0 0.15rem; }
.a-stat-delta { font-size: 0.78rem; font-weight: 700; }
.a-stat-delta.up { color: var(--aok); }
.a-stat-delta.down { color: var(--abad); }
.a-canvas { position: relative; height: 240px; padding: 1rem; }
.a-canvas canvas { display: block; width: 100% !important; height: 100% !important; }

/* Table */
.a-table-wrap { overflow-x: auto; }
.a-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.a-table th { text-align: left; padding: 0.7rem 1.3rem; color: var(--amuted); font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--aline); background: #F8FAFC; }
.a-table td { padding: 0.75rem 1.3rem; border-bottom: 1px solid var(--aline); vertical-align: middle; }
.a-table tr:last-child td { border-bottom: none; }
.a-pixels { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.a-date { color: var(--amuted); white-space: nowrap; }
.a-actions a { color: var(--aaccent); text-decoration: none; font-size: 0.8rem; margin-right: 0.6rem; font-weight: 600; }
.a-actions a:hover { text-decoration: underline; }
.a-table code { background: #EEF2FF; color: var(--aaccent); padding: 0.12rem 0.4rem; border-radius: 6px; font-size: 0.8rem; }

/* Badges / pills */
.a-badge { font-size: 0.68rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 99px; background: #EEF2FF; color: var(--aaccent); }
.a-badge.super { background: linear-gradient(135deg, var(--aaccent), var(--aaccent2)); color: #fff; }
.a-pill { font-size: 0.72rem; font-weight: 700; padding: 0.22rem 0.55rem; border-radius: 99px; }
.a-pill.ok { background: #D1FAE5; color: #047857; }
.a-pill.bad { background: #FEE2E2; color: #B91C1C; }
.a-pill.warn { background: #FEF3C7; color: #B45309; }

/* Buttons / inputs */
.a-btn { display: inline-block; border: none; cursor: pointer; font-family: inherit; font-size: 0.84rem; font-weight: 700; padding: 0.55rem 1rem; border-radius: 9px; text-decoration: none; }
.a-btn-primary { background: var(--aaccent); color: #fff; }
.a-btn-primary:hover { background: #4F46E5; }
.a-btn-soft { background: #EEF2FF; color: var(--aaccent); }
.a-input { font-family: inherit; font-size: 0.86rem; color: var(--aink); background: #F8FAFC; border: 1px solid var(--aline); border-radius: 9px; padding: 0.55rem 0.8rem; }
.a-input:focus { outline: none; border-color: var(--aaccent); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.a-toolbar { display: flex; flex-wrap: wrap; gap: 0.6rem; padding: 0.9rem 1.3rem; border-bottom: 1px solid var(--aline); }
.a-toolbar .a-input { min-width: 220px; }
.a-hint { margin: 0; padding: 0.9rem 1.3rem; color: var(--amuted); font-size: 0.82rem; border-top: 1px solid var(--aline); }

/* Forms */
.a-form { padding: 1.1rem 1.3rem; display: flex; flex-direction: column; gap: 0.9rem; max-width: 640px; }
.a-form-grid { max-width: none; display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; align-items: center; }
.a-form-row { display: grid; grid-template-columns: 220px 1fr; gap: 1rem; align-items: center; }
.a-form-row label { color: var(--amuted); font-size: 0.86rem; font-weight: 600; }
.a-form-row .a-input { width: 100%; }
.a-form-row textarea.a-input { resize: vertical; }
.a-input.a-code { font-family: Consolas, monospace; font-size: 0.8rem; }

/* Switch */
.a-switch { position: relative; display: inline-flex; align-items: center; gap: 0.6rem; cursor: pointer; color: var(--aink) !important; }
.a-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.a-switch span { width: 38px; height: 21px; border-radius: 99px; background: #CBD5E1; position: relative; transition: 0.2s; flex: none; }
.a-switch span::after { content: ""; position: absolute; top: 2px; left: 2px; width: 17px; height: 17px; border-radius: 50%; background: #fff; transition: 0.2s; }
.a-switch input:checked + span { background: var(--aaccent); }
.a-switch input:checked + span::after { transform: translateX(17px); }

/* Plans */
.a-plan { padding: 1.2rem 1.3rem; }
.a-plan-price { font-size: 1.6rem; font-weight: 800; margin: 0.6rem 0; }
.a-plan-price small { font-size: 0.8rem; color: var(--amuted); font-weight: 500; }
.a-plan-features { list-style: none; margin: 0 0 1rem; padding: 0; color: var(--amuted); font-size: 0.86rem; display: flex; flex-direction: column; gap: 0.4rem; }
.a-plan-features li::before { content: "✓  "; color: var(--aok); font-weight: 700; }

@media (max-width: 1024px) {
    .adash-side { width: 230px; }
    .adash-main { margin-left: 230px; }
    .a-grid.a-stats { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 720px) {
    .adash-side { position: static; width: 100%; }
    .adash-main { margin-left: 0; }
    .adash { flex-direction: column; }
    .a-grid { grid-template-columns: 1fr; }
    .a-grid.a-stats { grid-template-columns: 1fr; }
    .a-form-row, .a-form-grid { grid-template-columns: 1fr; }
    .a-top { flex-direction: column; align-items: flex-start; padding: 1rem 1rem 0.5rem; }
    .a-content { padding: 0.5rem 1rem 1.6rem; }
}
CSS;
}

function admin_js(): string
{
    return <<<'JS'
(function () {
    'use strict';
    var dataEl = document.getElementById('admin-chart-data');
    if (!dataEl || !window.Chart) return;
    var d;
    try { d = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
    var colors = ['#6366F1', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9'];
    function mk(id, type, labels, values, opts) {
        var el = document.getElementById(id);
        if (!el) return;
        var dataset = { data: values, backgroundColor: colors, borderColor: colors[0], fill: true, tension: 0.3 };
        if (type === 'line') dataset.borderColor = colors[0]; else dataset.backgroundColor = colors;
        if (type === 'pie') { dataset.borderColor = '#fff'; dataset.borderWidth = 2; }
        new Chart(el.getContext('2d'), {
            type: type,
            data: { labels: labels || [], datasets: [dataset] },
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: type === 'pie' ? { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } : { display: false } }
            }, opts || {})
        });
    }
    var days = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN', 'T2', 'T3', 'T4'];
    var weeks = ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4', 'Tuần 5', 'Tuần 6', 'Tuần 7', 'Tuần 8', 'Tuần 9', 'Tuần 10'];
    mk('chart-users-new', 'line', weeks, d.users);
    mk('chart-links-created', 'bar', weeks, d.links);
    mk('chart-clicks-day', 'line', days, d.clicks);
    mk('chart-revenue-plan', 'pie', (d.revenue || []).map(function (r) { return r.label; }), (d.revenue || []).map(function (r) { return r.value; }));
})();
JS;
}
