<?php
/** @var array<int,array<string,mixed>> $links */
/** @var string $search */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var int $cleaned */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$base = rtrim(\App\base_url(), '/');
$rows = '';
foreach ($links as $l) {
    $owner = $l['user_id'] !== null ? $l['username'] : 'Khách (ẩn danh)';
    $short = $base . '/' . $l['slug'];
    $rows .= '<tr>'
        . '<td class="a-pixels">' . \App\escape($owner) . '</td>'
        . '<td class="a-pixels" title="' . \App\escape($l['target_url']) . '">' . \App\escape($l['target_url']) . '</td>'
        . '<td class="a-pixels"><code>' . \App\escape($l['slug']) . '</code> <small class="a-sub-text">' . \App\escape($short) . '</small></td>'
        . '<td class="a-date">' . \App\escape(substr((string) $l['created_at'], 0, 16)) . '</td>'
        . '<td class="a-date">' . \App\escape(!empty($l['ends_at']) ? substr((string) $l['ends_at'], 0, 10) : '—') . '</td>'
        . '<td>' . \App\escape(number_format((int) $l['click_count'])) . '</td>'
        . '<td class="a-date" style="color:var(--aok);font-weight:800;">&#10003;</td>'
        . '<td class="a-actions">'
        . '<button type="button" class="a-btn a-btn-soft js-link-edit" data-id="' . (int) $l['id'] . '" data-action="' . \App\escape(\App\url_for('admin/links/' . (int) $l['id'] . '/update')) . '">Sửa</button>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/links/' . (int) $l['id'] . '/toggle') . '">' . $csrf->field() . '<button type="submit" class="a-btn ' . ((int) $l['is_active'] === 1 ? 'a-btn-soft' : 'a-btn-danger') . '">' . ((int) $l['is_active'] === 1 ? 'Vô hiệu' : 'Hiện') . '</button></form>'
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
<?php if ($cleaned > 0): ?>
    <div class="alert alert-warn" role="alert">Đã tự xoá <b><?= $cleaned ?></b> link khách không được chỉnh sửa quá 15 ngày.</div>
<?php endif; ?>

<section class="a-card">
    <div class="a-card-head">
        <h2>Quản lý Link</h2>
        <span class="a-hint" style="padding:0;border:0;"><?= $total ?> link</span>
    </div>
    <div class="a-toolbar">
        <form method="get" action="<?= \App\url_for('admin/links') ?>" class="dash-inline-form">
            <input type="search" name="q" class="a-input" value="<?= \App\escape($search) ?>" placeholder="Tìm theo username / slug / URL..." autocomplete="off">
            <button type="submit" class="a-btn a-btn-soft">Tìm</button>
        </form>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>User</th><th>URL</th><th>URL Short</th><th>Time Create</th><th>Time End</th><th>Click</th><th>QR Code</th><th>Action</th></tr></thead>
            <tbody><?= $rows ?></tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="report-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/links') . '?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&#8592; Trước</a>
            <?php endif; ?>
            <span class="report-pager-info">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/links') . '?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Sau &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script id="admin-links-data" type="application/json"><?= json_encode($links, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="a-modal" id="a-modal-link" hidden>
    <div class="a-modal-card a-modal-card-lg" role="dialog" aria-modal="true" aria-label="Sửa link">
        <div class="a-card-head"><h2>Sửa link</h2><button type="button" class="a-modal-close" data-close="a-modal-link" aria-label="Đóng">&#10005;</button></div>
        <form method="post" action="" class="a-form" id="link-edit-form">
            <?= $csrf->field() ?>
            <div class="a-form-row"><label>Slug</label><span id="le-slug" class="a-pixels" style="font-weight:700;"></span></div>
            <div class="a-form-row"><label>URL đích</label><input name="target_url" id="le-target" type="url" required maxlength="2048"></div>
            <div class="a-form-row"><label>Tiêu đề</label><input name="title" id="le-title" type="text" maxlength="255"></div>
            <div class="a-form-row"><label>Mô tả</label><input name="description" id="le-desc" type="text" maxlength="500"></div>
            <div class="a-form-row"><label>Ngày hết hạn</label><input name="ends_at" id="le-ends" type="date"></div>
            <div class="a-form-row"><label class="a-switch">Đang hoạt động <input type="checkbox" name="is_active" id="le-active" checked><span></span></label></div>
            <div class="a-modal-actions" style="border-top:none;padding:0.4rem 0 0;">
                <button type="button" class="a-btn a-btn-soft" data-close="a-modal-link">Huỷ</button>
                <button type="submit" class="a-btn a-btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
