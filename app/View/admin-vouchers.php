<?php
/** @var array<int,array<string,mixed>> $vouchers */
/** @var string $search */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$priceLabel = static fn (float $p): string => number_format($p, 0, ',', '.') . '₫';
$rows = '';
foreach ($vouchers as $v) {
    $used = (int) $v['used_count'];
    $before = (float) ($v['last_before'] ?? 0);
    $after = (float) ($v['last_after'] ?? 0);
    $status = (string) ($v['last_status'] ?? '');
    $statusCell = $status === 'success'
        ? '<span class="a-pill ok">Thành công</span>'
        : ($status === 'failed' ? '<span class="a-pill bad">Thất bại</span>' : '<span class="a-pill">Chưa dùng</span>');
    $rows .= '<tr>'
        . '<td><code>' . \App\escape($v['code']) . '</code></td>'
        . '<td>' . \App\escape((int) $v['used_count'] . '/' . (int) $v['usage_limit']) . '</td>'
        . '<td class="a-pixels">' . \App\escape($v['last_order_code'] ?? '—') . '</td>'
        . '<td>' . ($before > 0 ? \App\escape($priceLabel($before)) : '—') . '</td>'
        . '<td>' . ($after > 0 ? \App\escape($priceLabel($after)) : '—') . '</td>'
        . '<td>' . $statusCell . '</td>'
        . '<td class="a-actions">'
        . '<button type="button" class="a-btn a-btn-soft js-voucher-edit" data-id="' . (int) $v['id'] . '" data-action="' . \App\escape(\App\url_for('admin/vouchers/' . (int) $v['id'] . '/update')) . '">Sửa</button>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/vouchers/' . (int) $v['id'] . '/toggle') . '">' . $csrf->field() . '<button type="submit" class="a-btn ' . ((int) $v['is_active'] === 1 ? 'a-btn-soft' : 'a-btn-danger') . '">' . ((int) $v['is_active'] === 1 ? 'Ngừng chạy' : 'Chạy') . '</button></form>'
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
        <h2>Quản lý Voucher</h2>
        <button type="button" class="a-btn a-btn-primary" id="a-voucher-create">+ Tạo voucher</button>
    </div>
    <div class="a-toolbar">
        <form method="get" action="<?= \App\url_for('admin/vouchers') ?>" class="dash-inline-form">
            <input type="search" name="q" class="a-input" value="<?= \App\escape($search) ?>" placeholder="Tìm mã voucher / chiến dịch..." autocomplete="off">
            <button type="submit" class="a-btn a-btn-soft">Tìm</button>
        </form>
        <span class="a-hint" style="padding:0;border:0;"><?= $total ?> voucher</span>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Mã Voucher</th><th>Số lượng</th><th>Đơn hàng áp dụng</th><th>Giá gói</th><th>Giá giảm sau voucher</th><th>Trạng thái áp dụng</th><th>Action</th></tr></thead>
            <tbody><?= $rows ?></tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="report-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/vouchers') . '?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&#8592; Trước</a>
            <?php endif; ?>
            <span class="report-pager-info">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/vouchers') . '?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Sau &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script id="admin-vouchers-data" type="application/json"><?= json_encode($vouchers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="a-modal" id="a-modal-voucher" hidden>
    <div class="a-modal-card a-modal-card-lg" role="dialog" aria-modal="true" aria-label="Tạo / Sửa voucher">
        <div class="a-card-head"><h2 id="a-voucher-title">Tạo voucher</h2><button type="button" class="a-modal-close" data-close="a-modal-voucher" aria-label="Đóng">&#10005;</button></div>
        <form method="post" action="<?= \App\url_for('admin/vouchers/store') ?>" class="a-form" id="voucher-form" data-store="<?= \App\url_for('admin/vouchers/store') ?>">
            <?= $csrf->field() ?>
            <div class="a-form-row"><label>Tên chiến dịch (admin quản lý)</label><input name="campaign_name" id="vf-campaign" type="text" maxlength="190" placeholder="VD: KM 8.8"></div>
            <div class="a-form-row"><label>Mã Voucher <span class="a-req">*</span></label><input name="code" id="vf-code" type="text" maxlength="50" required placeholder="VD: GIAM10" style="text-transform:uppercase;"></div>
            <div class="a-form-row"><label>Số lần sử dụng</label><input name="usage_limit" id="vf-limit" type="number" min="1" value="1"></div>
            <div class="a-form-row"><label>Áp dụng cho khách</label>
                <select name="per_user" id="vf-peruser">
                    <option value="once">1 user / 1 lần</option>
                    <option value="multiple">1 user / nhiều lần</option>
                </select>
            </div>
            <div class="a-form-row"><label>Hình thức</label>
                <select name="discount_type" id="vf-type">
                    <option value="percent">Giảm theo %</option>
                    <option value="fixed">Giảm theo số tiền</option>
                </select>
            </div>
            <div class="a-form-row"><label>Giá trị giảm</label><input name="discount_value" id="vf-value" type="number" min="0.01" step="0.01" required></div>
            <div class="a-form-row"><label>Ngày bắt đầu</label><input name="starts_at" id="vf-start" type="date"></div>
            <div class="a-form-row"><label>Ngày kết thúc</label><input name="ends_at" id="vf-end" type="date"></div>
            <div class="a-form-row"><label>Note (ghi chú nội bộ)</label><textarea name="note" id="vf-note" class="a-input" rows="2"></textarea></div>
            <div class="a-form-row"><label class="a-switch">Đang chạy <input type="checkbox" name="is_active" id="vf-active" checked><span></span></label></div>
            <div class="a-modal-actions" style="border-top:none;padding:0.4rem 0 0;">
                <button type="button" class="a-btn a-btn-soft" data-close="a-modal-voucher">Huỷ</button>
                <button type="submit" class="a-btn a-btn-primary">Lưu voucher</button>
            </div>
        </form>
    </div>
</div>
