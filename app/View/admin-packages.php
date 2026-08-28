<?php
/** @var array<int,array<string,mixed>> $packages */
/** @var string $search */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$periodLabel = static fn (string $p): string => match ($p) {
    'yearly' => 'Năm',
    'lifetime' => 'Trọn đời',
    default => 'Tháng',
};
$limitLabel = static fn (int $v): string => $v < 0 ? 'Không giới hạn' : number_format($v);
$priceLabel = static function (float $price, string $currency): string {
    $decimals = $currency === 'VND' ? 0 : 2;
    return number_format($price, $decimals, ',', '.') . ' ' . $currency;
};

$rows = '';
foreach ($packages as $p) {
    $rows .= '<tr>'
        . '<td class="a-pixels"><strong>' . \App\escape($p['name']) . '</strong>' . ($p['is_popular'] ? ' <span class="a-badge">Phổ biến</span>' : '') . '</td>'
        . '<td>' . \App\escape($priceLabel((float) $p['price'], (string) $p['currency'])) . '</td>'
        . '<td>' . \App\escape($periodLabel((string) $p['billing_period'])) . '</td>'
        . '<td>' . \App\escape($limitLabel((int) $p['max_links'])) . '</td>'
        . '<td>' . \App\escape($limitLabel((int) $p['max_custom_domains'])) . '</td>'
        . '<td>' . ($p['is_active'] ? '<span class="a-pill ok">Hoạt động</span>' : '<span class="a-pill bad">Tắt</span>') . '</td>'
        . '<td class="a-actions">'
        . '<a class="a-btn a-btn-soft" href="' . \App\url_for('admin/packages/' . (int) $p['id'] . '/edit') . '">Sửa</a>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/packages/' . (int) $p['id'] . '/toggle') . '">' . $csrf->field() . '<button type="submit" class="a-btn a-btn-soft">' . ($p['is_active'] ? 'Tắt' : 'Bật') . '</button></form>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/packages/' . (int) $p['id'] . '/delete') . '" onsubmit="return confirm(\'Xoá gói này?\')">' . $csrf->field() . '<button type="submit" class="a-btn a-btn-danger">Xoá</button></form>'
        . '</td>'
        . '</tr>';
}
?>
<?php if ($ok): ?>
    <div class="dash-flash" role="status">Đã lưu thay đổi.</div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div>
<?php endif; ?>

<section class="a-card">
    <div class="a-card-head">
        <h2>Danh sách gói dịch vụ</h2>
        <a class="a-btn a-btn-primary" href="<?= \App\url_for('admin/packages/new') ?>">+ Thêm gói mới</a>
    </div>
    <div class="a-toolbar">
        <form method="get" action="<?= \App\url_for('admin/packages') ?>" class="dash-inline-form">
            <input type="search" name="q" class="a-input" value="<?= \App\escape($search) ?>" placeholder="Tìm kiếm theo tên gói..." autocomplete="off">
            <button type="submit" class="a-btn a-btn-soft">Tìm</button>
        </form>
        <span class="a-hint" style="padding:0;border:0;"><?= $total ?> gói</span>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Tên gói</th><th>Giá</th><th>Chu kỳ</th><th>Số link</th><th>Số domain</th><th>Trạng thái</th><th>Hành động</th></tr></thead>
            <tbody><?= $rows ?></tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="report-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/packages') . '?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&#8592; Trước</a>
            <?php endif; ?>
            <span class="report-pager-info">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/packages') . '?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Sau &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
