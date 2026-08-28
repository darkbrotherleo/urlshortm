<?php
declare(strict_types=1);

namespace App\Controller;

final class AdminDashboardController
{
    public function index(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        // Data cứng tạm để kiểm tra layout — sẽ thay bằng thống kê thật ở bước sau.
        $stats = [
            'users'   => ['value' => '1.284', 'delta' => '+12%'],
            'links'   => ['value' => '4.910', 'delta' => '+8%'],
            'clicks'  => ['value' => '286.540', 'delta' => '+23%'],
            'revenue' => ['value' => '86,5M₫', 'delta' => '+31%'],
        ];
        $activity = [
            ['time' => '2026-08-27 09:12', 'actor' => 'nguyenphatseo@gmail.com', 'action' => 'Nâng gói Pro', 'target' => 'user #12'],
            ['time' => '2026-08-27 08:47', 'actor' => 'admin@vidu.vn', 'action' => 'Xoá Link', 'target' => 'slug Ab3x9Q'],
            ['time' => '2026-08-27 08:20', 'actor' => 'user@shop.vn', 'action' => 'Đăng ký tài khoản', 'target' => 'user #1284'],
            ['time' => '2026-08-27 07:55', 'actor' => 'admin@vidu.vn', 'action' => 'Ban User (vi phạm)', 'target' => 'user #977'],
            ['time' => '2026-08-27 07:31', 'actor' => 'thanh@ads.vn', 'action' => 'Tạo Link', 'target' => 'slug tT9zK4'],
        ];
        $chart = [
            'users'   => [12, 18, 15, 22, 27, 31, 29, 40, 38, 45],
            'links'   => [30, 42, 51, 48, 66, 72, 80, 75, 90, 96],
            'clicks'  => [420, 510, 480, 620, 710, 690, 820, 940, 1010, 1120],
            'revenue' => [['label' => 'Starter', 'value' => 42], ['label' => 'Pro', 'value' => 31], ['label' => 'Trial', 'value' => 27]],
        ];

        $rows = '';
        foreach ($activity as $a) {
            $rows .= '<tr><td class="a-date">' . \App\escape($a['time']) . '</td><td class="a-pixels">' . \App\escape($a['actor']) . '</td><td>' . \App\escape($a['action']) . '</td><td>' . \App\escape($a['target']) . '</td></tr>';
        }

        $content = '<div class="a-grid a-stats">'
            . '<article class="a-card a-stat"><span class="a-stat-label">Người dùng</span><strong class="a-stat-num">' . \App\escape($stats['users']['value']) . '</strong><span class="a-stat-delta up">' . \App\escape($stats['users']['delta']) . '</span></article>'
            . '<article class="a-card a-stat"><span class="a-stat-label">Link rút gọn</span><strong class="a-stat-num">' . \App\escape($stats['links']['value']) . '</strong><span class="a-stat-delta up">' . \App\escape($stats['links']['delta']) . '</span></article>'
            . '<article class="a-card a-stat"><span class="a-stat-label">Lượt mở (clicks)</span><strong class="a-stat-num">' . \App\escape($stats['clicks']['value']) . '</strong><span class="a-stat-delta up">' . \App\escape($stats['clicks']['delta']) . '</span></article>'
            . '<article class="a-card a-stat"><span class="a-stat-label">Doanh thu</span><strong class="a-stat-num">' . \App\escape($stats['revenue']['value']) . '</strong><span class="a-stat-delta up">' . \App\escape($stats['revenue']['delta']) . '</span></article>'
            . '</div>'
            . '<div class="a-grid a-charts">'
            . '<section class="a-card a-chart a-chart-wide"><div class="a-card-head"><h2>Người dùng mới</h2></div><div class="a-canvas"><canvas id="chart-users-new"></canvas></div></section>'
            . '<section class="a-card a-chart"><div class="a-card-head"><h2>Link được tạo</h2></div><div class="a-canvas"><canvas id="chart-links-created"></canvas></div></section>'
            . '<section class="a-card a-chart"><div class="a-card-head"><h2>Click theo ngày</h2></div><div class="a-canvas"><canvas id="chart-clicks-day"></canvas></div></section>'
            . '<section class="a-card a-chart"><div class="a-card-head"><h2>Doanh thu theo gói</h2></div><div class="a-canvas"><canvas id="chart-revenue-plan"></canvas></div></section>'
            . '<section class="a-card a-chart a-chart-wide"><div class="a-card-head"><h2>Hoạt động gần đây</h2></div><div class="a-table-wrap"><table class="a-table"><thead><tr><th>Thời gian</th><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>'
            . '</div>'
            . '<script id="admin-chart-data" type="application/json">' . json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';

        \App\render_admin_page($admin, 'Admin — Tổng quan', 'tong-quan', $content);
    }
}
