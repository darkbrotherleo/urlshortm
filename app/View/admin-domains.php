<?php
/** @var array<int,array<string,mixed>> $systemDomains */
/** @var array<int,array<string,mixed>> $userDomains */
/** @var array<int,array{active:int,remaining:string}> $usageByUser */
/** @var string $search */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $currentHost */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$statusCell = static function (array $d): string {
    if ((int) $d['is_active'] === 0) {
        return '<span class="a-pill warn">Tạm dừng</span>';
    }
    if ((int) $d['is_verified'] === 1) {
        return '<span class="a-pill ok">Hoạt động</span>';
    }

    return '<span class="a-pill bad">Lỗi kết nối</span>';
};

$sysRows = '';
foreach ($systemDomains as $sd) {
    $sysRows .= '<tr>'
        . '<td><code>' . \App\escape($sd['domain']) . '</code>' . ((int) $sd['is_default'] === 1 ? ' <span class="a-badge">Mặc định</span>' : '') . '</td>'
        . '<td>' . ((int) $sd['is_default'] === 1 ? '<span class="a-pill ok">Mặc định</span>' : '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/domains/system/' . (int) $sd['id'] . '/default') . '">' . $csrf->field() . '<button type="submit" class="a-btn a-btn-soft">Đặt mặc định</button></form>') . '</td>'
        . '<td>' . ((int) $sd['is_active'] === 1 ? '<span class="a-pill ok">Hoạt động</span>' : '<span class="a-pill warn">Tắt</span>') . '</td>'
        . '<td class="a-actions">'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/domains/system/' . (int) $sd['id'] . '/toggle') . '">' . $csrf->field() . '<button type="submit" class="a-btn ' . ((int) $sd['is_active'] === 1 ? 'a-btn-soft' : 'a-btn-danger') . '">' . ((int) $sd['is_active'] === 1 ? 'Tắt' : 'Bật') . '</button></form>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/domains/system/' . (int) $sd['id'] . '/delete') . '" onsubmit="return confirm(\'Xoá domain hệ thống này?\')">' . $csrf->field() . '<button type="submit" class="a-btn a-btn-danger">Xoá</button></form>'
        . '</td>'
        . '</tr>';
}

$userRows = '';
foreach ($userDomains as $d) {
    $uid = (int) $d['user_id'];
    $usage = $usageByUser[$uid] ?? ['active' => 0, 'remaining' => '—'];
    $userRows .= '<tr>'
        . '<td class="a-pixels">' . \App\escape($d['username']) . '<small class="a-sub-text">' . \App\escape($d['user_email']) . '</small></td>'
        . '<td><code>' . \App\escape($d['domain']) . '</code></td>'
        . '<td class="a-date">' . \App\escape(substr((string) $d['created_at'], 0, 10)) . '</td>'
        . '<td>' . \App\escape($usage['active'] . ' / ' . $usage['remaining']) . '</td>'
        . '<td>' . $statusCell($d) . '</td>'
        . '<td class="a-actions">'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/domains/user/' . (int) $d['id'] . '/toggle') . '">' . $csrf->field() . '<button type="submit" class="a-btn ' . ((int) $d['is_active'] === 1 ? 'a-btn-soft' : 'a-btn-danger') . '">' . ((int) $d['is_active'] === 1 ? 'Tạm dừng' : 'Hoạt động') . '</button></form>'
        . '<form class="dash-inline-form" method="post" action="' . \App\url_for('admin/domains/user/' . (int) $d['id'] . '/delete') . '" onsubmit="return confirm(\'Xoá domain của user?\')">' . $csrf->field() . '<button type="submit" class="a-btn a-btn-danger">Xoá</button></form>'
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
    <div class="a-card-head"><h2>Domain Hệ Thống</h2></div>
    <p class="lform-hint" style="padding: 0.7rem 1.3rem 0;">
        Domain dùng để hiển thị link rút gọn. Khi chạy localhost qua <b>Laragon</b> dùng <code>urlshortm.test</code>;
        khi chạy online dùng domain kết nối hosting (VD: <code><?= \App\escape($currentHost) ?: 'your-domain.com' ?></code>).
        Domain mặc định được áp dụng cho mọi link chưa có custom domain.
    </p>
    <p class="lform-hint" style="padding: 0.3rem 1.3rem 0.6rem;">
        <b>Đang dùng:</b> <code><?= \App\escape($effectiveBase) ?></code> — link rút gọn sẽ có dạng <code><?= \App\escape($effectiveBase) ?>/slug</code>.
        Nếu chưa đặt domain hệ thống, hệ thống tự dùng host hiện tại (vẫn hoạt động) nhưng nên đặt mặc định để ổn định.
    </p>
    <form method="post" action="<?= \App\url_for('admin/domains/system/add') ?>" class="a-toolbar" style="border:none;">
        <?= $csrf->field() ?>
        <input type="text" name="domain" class="a-input" placeholder="VD: urlshortm.test hoặc link.congty.com" autocomplete="off" required>
        <button type="submit" class="a-btn a-btn-primary">+ Thêm domain hệ thống</button>
    </form>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Domain</th><th>Mặc định</th><th>Trạng thái</th><th>Action</th></tr></thead>
            <tbody><?= $sysRows ?></tbody>
        </table>
    </div>
</section>

<section class="a-card">
    <div class="a-card-head"><h2>Domain của Users</h2><span class="a-hint" style="padding:0;border:0;"><?= $total ?> domain</span></div>
    <div class="a-toolbar">
        <form method="get" action="<?= \App\url_for('admin/domains') ?>" class="dash-inline-form">
            <input type="search" name="q" class="a-input" value="<?= \App\escape($search) ?>" placeholder="Tìm domain / username / email..." autocomplete="off">
            <button type="submit" class="a-btn a-btn-soft">Tìm</button>
        </form>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Username</th><th>Domain</th><th>Ngày thêm</th><th>Số lượng</th><th>Trạng thái</th><th>Action</th></tr></thead>
            <tbody><?= $userRows ?></tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="report-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/domains') . '?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&#8592; Trước</a>
            <?php endif; ?>
            <span class="report-pager-info">Trang <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= \App\escape(\App\url_for('admin/domains') . '?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Sau &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
