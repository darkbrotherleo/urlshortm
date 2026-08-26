<?php
/**
 * Bảng điều khiển user — sidebar trái + khu hiển thị phải.
 * Tab giữ active qua query `?tab=` (server-render, URL là trạng thái thật).
 * Menu: Tổng quan / Quản lý link (All Link, Folder) / Tài khoản / Cài đặt.
 *
 * @var string                $title
 * @var array{id:int,email:string,display_name:?string,status:string,created_at:string} $user
 * @var string                $tab
 * @var array{total_links:int,total_clicks:int} $totals
 * @var array<int, array{slug:string,target_url:string,click_count:int,folder_id:?int,created_at:string}> $links
 * @var array<int, array{id:int,name:string,created_at:string,total_links:int}> $folders
 * @var int|null              $folderId
 * @var array{id:int,name:string}|null $activeFolder
 * @var bool                  $flashOk
 * @var \App\Security\Csrf    $csrf
 */
$displayName = $user['display_name'] ?: $user['email'];
$initial = mb_substr($displayName, 0, 1, 'UTF-8');

$labels = [
    'tong-quan' => 'Tổng quan',
    'links'     => 'All Link',
    'folder'    => 'Folder',
    'tai-khoan' => 'Tài khoản',
    'cai-dat'   => 'Cài đặt',
];

$formatDate = function (string $raw): string {
    $ts = strtotime($raw);
    return $ts === false ? '' : date('d/m/Y', $ts);
};

$navItem = function (string $key, string $label, string $num, bool $active, ?int $badge = null) use ($labels): string {
    $href = \App\url_for('dashboard') . '?tab=' . $key;
    $badgeHtml = $badge !== null && $badge > 0
        ? '<span class="dash-badge">' . $badge . '</span>'
        : '';
    return '<a class="dash-nav-item' . ($active ? ' is-active' : '') . '" href="' . \App\escape($href) . '"'
        . ($active ? ' aria-current="page"' : '') . '>'
        . '<span class="dash-nav-num">' . \App\escape($num) . '</span>'
        . \App\escape($label) . $badgeHtml . '</a>';
};

$navSub = function (string $key, string $label, bool $active, ?int $badge = null): string {
    $href = \App\url_for('dashboard') . '?tab=' . $key;
    $badgeHtml = $badge !== null && $badge > 0
        ? '<span class="dash-badge">' . $badge . '</span>'
        : '';
    return '<a class="dash-nav-sub' . ($active ? ' is-active' : '') . '" href="' . \App\escape($href) . '"'
        . ($active ? ' aria-current="page"' : '') . '>'
        . '<span class="dash-nav-bullet" aria-hidden="true"></span>'
        . \App\escape($label) . $badgeHtml . '</a>';
};

$shortUrlFor = function (string $slug): string {
    return \App\url_for($slug);
};

$renderRow = function (array $link, bool $withAssign) use ($shortUrlFor, $formatDate, $folders, $folderId, $csrf): string {
    $url = $shortUrlFor($link['slug']);
    $assign = '';
    if ($withAssign) {
        $options = '<option value="">Không thư mục</option>';
        foreach ($folders as $f) {
            $selected = (int) $link['folder_id'] === (int) $f['id'] ? ' selected' : '';
            $options .= '<option value="' . (int) $f['id'] . '"' . $selected . '>' . \App\escape($f['name']) . '</option>';
        }
        $assign = '<td class="dash-assign-cell">'
            . '<form method="post" action="' . \App\escape(\App\url_for('dashboard/link-folder')) . '" class="dash-assign">'
            . $csrf->field()
            . '<input type="hidden" name="link_id" value="' . (int) $link['id'] . '">'
            . '<input type="hidden" name="return_tab" value="' . \App\escape($_GET['tab'] ?? 'links') . '">'
            . '<input type="hidden" name="return_folder" value="' . ($folderId ?? '') . '">'
            . '<select name="folder_id" aria-label="Thư mục">' . $options . '</select>'
            . '<button type="submit" class="btn btn-ghost btn-sm">Lưu</button>'
            . '</form></td>';
    }

    return '<tr>'
        . '<td><a class="dash-slug" href="' . \App\escape($url) . '" target="_blank" rel="noopener">' . \App\escape($url) . '</a></td>'
        . '<td class="dash-target">' . \App\escape($link['target_url']) . '</td>'
        . '<td class="dash-clicks">' . (int) $link['click_count'] . '</td>'
        . '<td class="dash-date">' . \App\escape($formatDate($link['created_at'])) . '</td>'
        . $assign
        . '<td class="dash-actions">'
        . '<button type="button" class="btn btn-soft btn-sm js-copy" data-copy="' . \App\escape($url) . '">Sao chép</button>'
        . '<a class="btn btn-ghost btn-sm" href="' . \App\escape($url) . '" target="_blank" rel="noopener">Mở</a>'
        . '</td>'
        . '</tr>';
};

$tableBody = function (array $list, bool $withAssign) use ($renderRow, $csrf): string {
    if ($list === []) {
        return '<tr><td colspan="6" class="dash-empty">Chưa có link nào. <a href="' . \App\url_for('/') . '">Về trang chủ rút gọn link đầu tiên</a>.</td></tr>';
    }
    return implode('', array_map(fn (array $link) => $renderRow($link, $withAssign), $list));
};

$showAssign = in_array($tab, ['links', 'folder'], true);

$pixelsLabel = function (string $raw): string {
    $decoded = json_decode($raw, true);
    return is_array($decoded) && $decoded !== [] ? implode(', ', $decoded) : '—';
};

$renderManagerRow = function (array $link) use ($shortUrlFor, $formatDate, $pixelsLabel, $csrf): string {
    $shortUrl = \App\short_url_for($link);
    $shortTarget = $shortUrlFor($link['slug']);
    $title = ($link['title'] !== null && $link['title'] !== '') ? $link['title'] : $link['slug'];
    $deleteAction = \App\url_for('dashboard/link/' . (int) $link['id'] . '/delete');
    $editHref = \App\url_for('dashboard/link/' . (int) $link['id'] . '/edit');

    return '<tr>'
        . '<td class="dash-tick"><input type="checkbox" class="row-check" value="' . (int) $link['id'] . '" aria-label="Chọn link ' . \App\escape($title) . '"></td>'
        . '<td class="dash-title-cell">'
        . '<a class="dash-link-title" href="' . \App\escape($editHref) . '">' . \App\escape($title) . '</a>'
        . '<span class="dash-link-type">' . \App\escape(\App\Security\LinkType::LABELS[$link['link_type']] ?? $link['link_type']) . '</span>'
        . '</td>'
        . '<td class="dash-clicks">' . (int) $link['click_count'] . '</td>'
        . '<td class="dash-date">' . \App\escape($formatDate($link['created_at'])) . '</td>'
        . '<td class="dash-pixels">' . \App\escape($pixelsLabel((string) $link['pixels'])) . '</td>'
        . '<td class="dash-target">' . \App\escape($link['target_url']) . '</td>'
        . '<td class="dash-short"><a class="dash-slug" href="' . \App\escape($shortTarget) . '" target="_blank" rel="noopener">' . \App\escape($shortUrl) . '</a></td>'
        . '<td class="dash-actions">'
        . '<button type="button" class="btn btn-soft btn-sm js-copy" data-copy="' . \App\escape($shortUrl) . '" title="Copy Link">Copy</button>'
        . '<button type="button" class="btn btn-ghost btn-sm js-share" data-url="' . \App\escape($shortUrl) . '" data-title="' . \App\escape($title) . '" title="Share">Share</button>'
        . '<button type="button" class="btn btn-ghost btn-sm js-qr" data-url="' . \App\escape($shortUrl) . '" title="QR Code">QR</button>'
        . '<a class="btn btn-ghost btn-sm" href="' . \App\escape($editHref) . '" title="Edit">Edit</a>'
        . '<form method="post" action="' . \App\escape($deleteAction) . '" class="dash-inline-form" onsubmit="return confirm(\'Xoá link này?\')">'
        . $csrf->field()
        . '<button type="submit" class="btn btn-ghost btn-sm dash-del" title="Delete">Delete</button>'
        . '</form>'
        . '</td>'
        . '</tr>';
};

$managerBody = $links === []
    ? '<tr><td colspan="8" class="dash-empty">Chưa có link nào. Bấm <strong>Tạo Link Mới</strong> để bắt đầu.</td></tr>'
    : implode('', array_map($renderManagerRow, $links));

$folderListItems = '';
foreach ($folders as $f) {
    $isActive = $folderId !== null && (int) $f['id'] === $folderId;
    $href = \App\url_for('dashboard') . '?tab=folder&folder=' . (int) $f['id'];
    $folderListItems .= '<div class="dash-folder-row">'
        . '<a class="dash-folder' . ($isActive ? ' is-active' : '') . '" href="' . \App\escape($href) . '"'
        . ($isActive ? ' aria-current="page"' : '') . '>'
        . '<span class="dash-folder-glyph" aria-hidden="true">&#9673;</span>'
        . '<span class="dash-folder-name">' . \App\escape($f['name']) . '</span>'
        . '<span class="dash-folder-count">' . (int) $f['total_links'] . '</span>'
        . '</a>'
        . '<form method="post" action="' . \App\escape(\App\url_for('dashboard/folder/delete')) . '" class="dash-folder-del" onsubmit="return confirm(\'Xoá thư mục này? Link bên trong sẽ trở về \\"Không thư mục\\".\')">'
        . $csrf->field()
        . '<input type="hidden" name="folder_id" value="' . (int) $f['id'] . '">'
        . '<button type="submit" aria-label="Xoá thư mục">&#10005;</button>'
        . '</form></div>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= \App\escape($title) ?> — UrlShortM</title>
    <link rel="stylesheet" href="<?= \App\url_for('assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="dash-body">
<div class="dash">

    <aside class="dash-side">
        <a class="dash-brand" href="<?= \App\url_for('/') ?>">
            <span class="brand-mark" aria-hidden="true"></span> UrlShortM
        </a>

        <div class="dash-user">
            <span class="dash-user-avatar" aria-hidden="true"><?= \App\escape($initial) ?></span>
            <div class="dash-user-meta">
                <strong><?= \App\escape($displayName) ?></strong>
                <small><?= \App\escape($user['email']) ?></small>
            </div>
        </div>

        <nav class="dash-nav" aria-label="Menu bảng điều khiển">
            <?= $navItem('tong-quan', 'Tổng quan', '01', $tab === 'tong-quan') ?>

            <div class="dash-nav-group">
                <span class="dash-nav-group-label"><span class="dash-nav-num">02</span>Quản lý link</span>
                <?= $navSub('links', 'All Link', $tab === 'links', $totals['total_links']) ?>
                <?= $navSub('folder', 'Folder', $tab === 'folder', count($folders)) ?>
            </div>

            <?= $navItem('tai-khoan', 'Tài khoản', '03', $tab === 'tai-khoan') ?>
            <?= $navItem('cai-dat', 'Cài đặt', '04', $tab === 'cai-dat') ?>
        </nav>

        <div class="dash-side-foot">
            <a class="dash-side-link" href="<?= \App\url_for('/') ?>">&#8592; Về trang chủ</a>
            <form class="dash-logout" method="post" action="<?= \App\url_for('dang-xuat') ?>">
                <?= $csrf->field() ?>
                <button type="submit">Thoát</button>
            </form>
        </div>
    </aside>

    <main class="dash-main">
        <header class="dash-top">
            <div>
                <p class="dash-crumb">// <?= \App\escape($labels[$tab]) ?></p>
                <h1 class="dash-title"><?= \App\escape($labels[$tab]) ?></h1>
            </div>
            <div class="dash-tape" aria-hidden="true">
                <span><?= $totals['total_links'] ?> link</span>
                <span>///</span>
                <span><?= $totals['total_clicks'] ?> lượt mở</span>
                <span>///</span>
                <span class="dash-tape-live"><span class="pulse-dot" aria-hidden="true"></span> đang chạy</span>
            </div>
        </header>

        <?php if ($flashOk): ?>
            <div class="dash-flash" role="status">Đã lưu thay đổi.</div>
        <?php endif; ?>

        <div class="dash-content">

            <?php if ($tab === 'tong-quan'): ?>

                <div class="dash-grid">
                    <article class="gauge">
                        <span class="gauge-label">Link đã tạo</span>
                        <strong class="gauge-num"><?= $totals['total_links'] ?></strong>
                        <span class="gauge-note">tổng của riêng bạn</span>
                    </article>
                    <article class="gauge gauge-accent">
                        <span class="gauge-label">Tổng lượt mở</span>
                        <strong class="gauge-num"><?= $totals['total_clicks'] ?></strong>
                        <span class="gauge-note">mỗi lượt mở được ghi lại</span>
                    </article>
                    <article class="gauge">
                        <span class="gauge-label">Gói hiện tại</span>
                        <strong class="gauge-num gauge-plan">Miễn phí</strong>
                        <span class="gauge-note">nâng cấp sắp ra mắt</span>
                    </article>
                </div>

                <section class="dash-panel">
                    <div class="dash-panel-head">
                        <h2>Link gần đây</h2>
                        <a href="<?= \App\url_for('dashboard') . '?tab=links' ?>">Xem tất cả &#8594;</a>
                    </div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr><th>Link ngắn</th><th>Đích đến</th><th>Lượt mở</th><th>Tạo lúc</th><th></th></tr>
                            </thead>
                            <tbody><?= $tableBody($links, false) ?></tbody>
                        </table>
                    </div>
                </section>

                <aside class="dash-note">
                    Sắp có: biểu đồ lượt mở theo ngày, cảnh báo khi link bị lỗi, và mục rút gọn theo gói dịch vụ.
                </aside>

            <?php elseif ($tab === 'links'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head">
                        <h2>All Link</h2>
                        <a class="btn btn-primary btn-sm" href="<?= \App\url_for('dashboard/link/new') ?>">+ Tạo Link Mới</a>
                    </div>

                    <form method="post" action="<?= \App\escape(\App\url_for('dashboard/link/bulk')) ?>" class="dash-bulk" id="bulk-form" hidden>
                        <?= $csrf->field() ?>
                        <input type="hidden" name="ids" id="bulk-ids" value="">
                        <span class="dash-bulk-count">Đã chọn: <b id="bulk-count">0</b></span>
                        <select name="folder_id" id="bulk-folder" aria-label="Chuyển vào thư mục">
                            <option value="">— Không thư mục —</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?= (int) $folder['id'] ?>"><?= \App\escape($folder['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="bulk_action" value="move" class="btn btn-ghost btn-sm">Chuyển vào thư mục</button>
                        <button type="submit" name="bulk_action" value="delete" class="btn btn-danger btn-sm">Xoá đã chọn</button>
                    </form>

                    <div class="dash-table-wrap">
                        <table class="dash-table" id="link-table">
                            <thead>
                                <tr>
                                    <th class="dash-tick"><input type="checkbox" id="check-all" aria-label="Chọn tất cả"></th>
                                    <th>Title</th>
                                    <th>Clicks</th>
                                    <th>Date</th>
                                    <th>Pixels</th>
                                    <th>Url</th>
                                    <th>Url Short</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody><?= $managerBody ?></tbody>
                        </table>
                    </div>

                    <div class="share-menu" id="share-menu" hidden>
                        <span class="share-menu-label">Chia sẻ link</span>
                        <a class="share-opt share-fb" data-share="fb" href="#" target="_blank" rel="noopener"><span class="share-dot share-dot-fb" aria-hidden="true"></span>Facebook</a>
                        <a class="share-opt share-in" data-share="in" href="#" target="_blank" rel="noopener"><span class="share-dot share-dot-in" aria-hidden="true"></span>Linkedin</a>
                        <a class="share-opt share-x" data-share="x" href="#" target="_blank" rel="noopener"><span class="share-dot share-dot-x" aria-hidden="true"></span>X</a>
                        <a class="share-opt share-msg" data-share="msg" href="#" target="_blank" rel="noopener"><span class="share-dot share-dot-msg" aria-hidden="true"></span>Messenger</a>
                        <a class="share-opt share-zalo" data-share="zalo" href="#" target="_blank" rel="noopener"><span class="share-dot share-dot-zalo" aria-hidden="true"></span>Zalo</a>
                    </div>
                </section>

                <div class="dash-modal" id="qr-modal" hidden>
                    <div class="dash-modal-card qr-designer" role="dialog" aria-modal="true" aria-label="Thiết kế mã QR">
                        <div class="dash-modal-head">
                            <h2>Thiết kế mã QR</h2>
                            <button type="button" class="dash-modal-close" id="qr-close" aria-label="Đóng">&#10005;</button>
                        </div>
                        <div class="qr-designer-body">
                            <div class="qr-controls">
                                <h3 class="qr-group-title">Shape</h3>
                                <div class="form-field">
                                    <label for="qr-shape-style">Shape style</label>
                                    <select id="qr-shape-style">
                                        <option value="square">Square</option>
                                        <option value="rounded">Rounded</option>
                                        <option value="dots">Dots</option>
                                        <option value="extra-rounded">Extra-rounded</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="qr-corner-style">Corner style</label>
                                    <select id="qr-corner-style">
                                        <option value="square">Square</option>
                                        <option value="rounded">Rounded</option>
                                        <option value="extra-rounded">Extra-rounded</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="qr-shape-color">Shape color</label>
                                    <input type="color" id="qr-shape-color" value="#16150F">
                                </div>
                                <div class="form-field">
                                    <label for="qr-corner-color">Corner color</label>
                                    <input type="color" id="qr-corner-color" value="#FF4B00">
                                </div>
                            </div>
                            <div class="qr-preview-side">
                                <div class="qr-preview-wrap">
                                    <canvas id="qr-canvas"></canvas>
                                </div>
                                <p class="qr-note">&#128161; Luôn quét mã QR bằng điện thoại trước khi in để kiểm tra khả năng đọc được của mã.</p>
                                <div class="qr-download">
                                    <button type="button" id="qr-save-svg" class="btn btn-ghost btn-block">Tải về file SVG</button>
                                    <button type="button" id="qr-save-png" class="btn btn-primary btn-block">Tải về file PNG</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($tab === 'folder'): ?>

                <div class="dash-folder-layout">
                    <aside class="dash-folder-side">
                        <div class="dash-folder-head">
                            <h2>Thư mục</h2>
                            <span class="dash-folder-head-count"><?= count($folders) ?></span>
                        </div>

                        <form method="post" action="<?= \App\url_for('dashboard/folder/create') ?>" class="dash-folder-new">
                            <?= $csrf->field() ?>
                            <input type="text" name="name" placeholder="Tên thư mục mới…" maxlength="100" required aria-label="Tên thư mục mới">
                            <button type="submit" class="btn btn-primary btn-sm">Tạo</button>
                        </form>

                        <nav class="dash-folder-list" aria-label="Danh sách thư mục">
                            <a class="dash-folder<?= $folderId === null ? ' is-active' : '' ?>" href="<?= \App\url_for('dashboard') . '?tab=folder' ?>"
                               <?= $folderId === null ? 'aria-current="page"' : '' ?>>
                                <span class="dash-folder-glyph" aria-hidden="true">&#9734;</span>
                                <span class="dash-folder-name">Tất cả link</span>
                                <span class="dash-folder-count"><?= $totals['total_links'] ?></span>
                            </a>
                            <?= $folderListItems ?>
                        </nav>
                    </aside>

                    <section class="dash-folder-main">
                        <div class="dash-panel">
                            <div class="dash-panel-head">
                                <h2><?= \App\escape($activeFolder ? $activeFolder['name'] : 'Tất cả link') ?></h2>
                                <?php if ($activeFolder !== null): ?>
                                    <span class="dash-folder-current"><?= $activeFolder['name'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dash-table-wrap">
                                <table class="dash-table">
                                    <thead>
                                        <tr><th>Link ngắn</th><th>Đích đến</th><th>Lượt mở</th><th>Tạo lúc</th><th>Thư mục</th><th></th></tr>
                                    </thead>
                                    <tbody><?= $tableBody($links, true) ?></tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

            <?php elseif ($tab === 'tai-khoan'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Thông tin tài khoản</h2></div>
                    <dl class="dash-profile">
                        <div><dt>Tên hiển thị</dt><dd><?= \App\escape($displayName) ?></dd></div>
                        <div><dt>Email</dt><dd><?= \App\escape($user['email']) ?></dd></div>
                        <div><dt>Ngày tham gia</dt><dd><?= \App\escape($formatDate($user['created_at'])) ?></dd></div>
                        <div><dt>Trạng thái</dt><dd><span class="dash-status"><span class="pulse-dot" aria-hidden="true"></span> Hoạt động</span></dd></div>
                        <div><dt>Gói dịch vụ</dt><dd>Miễn phí</dd></div>
                    </dl>
                </section>

                <aside class="dash-note">
                    Đổi mật khẩu, thông báo và quản lý gói sẽ có trong bản cập nhật tới.
                </aside>

            <?php else: ?>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Cài đặt tài khoản</h2></div>
                    <form method="post" action="<?= \App\url_for('dashboard/settings') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <div class="dash-settings-row">
                            <label for="display_name">Tên hiển thị</label>
                            <input id="display_name" name="display_name" type="text" maxlength="100" value="<?= \App\escape($displayName) ?>">
                        </div>
                        <div class="dash-settings-row dash-settings-muted">
                            <span>Email</span>
                            <span><?= \App\escape($user['email']) ?></span>
                        </div>
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </form>
                </section>

                <aside class="dash-note">
                    Đổi mật khẩu, thông báo và quản lý gói sẽ có trong bản cập nhật tới.
                </aside>

            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?= \App\url_for('assets/js/app.js') ?>" defer></script>
<script src="<?= \App\url_for('assets/js/vendor/qrcode.js') ?>" defer></script>
<script src="<?= \App\url_for('assets/js/qr.js') ?>" defer></script>
</body>
</html>
