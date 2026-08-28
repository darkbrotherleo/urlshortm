<?php
/** @var string $mode */
/** @var string $title */
/** @var array<int,array<string,mixed>> $orders */
/** @var string $search */
/** @var string|null $status */
/** @var array<int,string> $statuses */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$statusLabel = static fn (string $s): string => match ($s) {
    'paid' => 'Đã thanh toán', 'canceled' => 'Đã huỷ', 'failed' => 'Thất bại', default => 'Chờ thanh toán',
};
$statusPill = static function (string $s) use ($statusLabel): string {
    return match ($s) {
        'paid' => '<span class="a-pill ok">' . \App\escape($statusLabel($s)) . '</span>',
        'canceled' => '<span class="a-pill warn">' . \App\escape($statusLabel($s)) . '</span>',
        'failed' => '<span class="a-pill bad">' . \App\escape($statusLabel($s)) . '</span>',
        default => '<span class="a-pill">' . \App\escape($statusLabel($s)) . '</span>',
    };
};
$priceLabel = static fn (float $p, string $c): string => number_format($p, $c === 'VND' ? 0 : 2, ',', '.') . ' ' . $c;

$rows = '';
foreach ($orders as $o) {
    $rows .= '<tr>'
        . '<td><code>' . \App\escape($o['order_code']) . '</code></td>'
        . '<td class="a-pixels"><button type="button" class="a-user-link js-order-view" data-id="' . (int) $o['id'] . '">' . \App\escape($o['username']) . '</button><small class="a-sub-text">' . \App\escape($o['user_email']) . '</small></td>'
        . '<td>' . \App\escape($o['plan_name']) . '</td>'
        . '<td>' . \App\escape($priceLabel((float) $o['amount'], (string) $o['currency'])) . '</td>'
        . '<td>' . \App\escape(ucfirst((string) $o['payment_method'])) . '</td>'
        . '<td>' . $statusPill((string) $o['status']) . '</td>'
        . '<td class="a-date">' . \App\escape(substr((string) $o['created_at'], 0, 16)) . '</td>'
        . '<td class="a-actions">'
        . '<button type="button" class="a-btn a-btn-soft js-order-view" data-id="' . (int) $o['id'] . '">Xem</button>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/orders/' . (int) $o['id'] . '/status') . '">' . $csrf->field()
        . '<select name="status" class="a-input" style="padding:0.3rem 0.6rem;font-size:0.76rem;width:auto;">'
        . implode('', array_map(static fn (string $s): string => '<option value="' . $s . '"' . ($s === (string) $o['status'] ? ' selected' : '') . '>' . \App\escape($statusLabel($s)) . '</option>', $statuses))
        . '</select><button type="submit" class="a-btn a-btn-soft">Cập nhật</button></form>'
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
        <h2><?= \App\escape($title) ?></h2>
        <span class="a-hint" style="padding:0;border:0;"><?= $total ?> đơn</span>
    </div>
    <div class="a-toolbar">
        <form method="get" action="<?= \App\url_for('admin/orders') ?>" class="dash-inline-form">
            <input type="search" name="q" class="a-input" value="<?= \App\escape($search) ?>" placeholder="Tìm mã đơn / email khách..." autocomplete="off">
            <select name="status" class="a-input" style="width:auto;">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= \App\escape($statusLabel($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="a-btn a-btn-soft">Lọc</button>
        </form>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Gói</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
            <tbody><?= $rows ?></tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="report-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/orders') . '?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '') . ($status !== null ? '&status=' . $status : '')) ?>">&#8592; Trước</a>
            <?php endif; ?>
            <span class="report-pager-info">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/orders') . '?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '') . ($status !== null ? '&status=' . $status : '')) ?>">Sau &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script id="admin-orders-data" type="application/json"><?= json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="a-modal" id="a-modal-order" hidden>
    <div class="a-modal-card" role="dialog" aria-modal="true" aria-label="Chi tiết đơn hàng">
        <div class="a-card-head"><h2>Chi tiết đơn hàng</h2><button type="button" class="a-modal-close" data-close="a-modal-order" aria-label="Đóng">&#10005;</button></div>
        <dl class="a-modal-info" id="a-modal-order-body"></dl>
        <div class="a-modal-actions">
            <button type="button" class="a-btn a-btn-soft" data-close="a-modal-order">Đóng</button>
        </div>
    </div>
</div>
