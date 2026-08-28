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
 * @var array<int, array{id:int,code:string,name:?string,platform:?string,created_at:string}> $pixels
 * @var array{id:int,code:string,name:?string,platform:?string}|null $pixelEdit
 * @var array<int, array{id:int,domain:string,is_verified:int,verification_token:?string,verified_at:?string,dns_checked_at:?string,last_error:?string}> $domains
 * @var array<int, array<string,mixed>> $utmProfiles
 * @var array<string,mixed>|null $utmEdit
 * @var array<string,string>  $platforms
 * @var bool                  $flashOk
 * @var string|null           $flashError
 * @var \App\Security\Csrf    $csrf
 */
$displayName = $user['display_name'] ?: $user['email'];
$initial = mb_substr($displayName, 0, 1, 'UTF-8');

$labels = [
    'tong-quan' => 'Tổng quan',
    'links'     => 'All Link',
    'folder'    => 'Folder',
    'baocao'    => 'Báo cáo',
    'tai-khoan' => 'Tài khoản',
    'cai-dat'   => 'Cài đặt tài khoản',
    'pixels'    => 'Thiết lập Pixels',
    'domains'   => 'Custom domain',
    'utms'      => 'UTMs tracking',
    'demographics' => 'Nhân khẩu học (Meta)',
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
        . '<td class="dash-short" title="' . \App\escape($url) . '"><a class="dash-slug" href="' . \App\escape($url) . '" target="_blank" rel="noopener">' . \App\escape($url) . '</a></td>'
        . '<td class="dash-target" title="' . \App\escape($link['target_url']) . '">' . \App\escape($link['target_url']) . '</td>'
        . '<td class="dash-clicks num">' . (int) $link['click_count'] . '</td>'
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
        . '<a class="dash-link-title" href="' . \App\escape($editHref) . '" title="' . \App\escape($title) . '">' . \App\escape($title) . '</a>'
        . '<span class="dash-link-type">' . \App\escape(\App\Security\LinkType::LABELS[$link['link_type']] ?? $link['link_type']) . '</span>'
        . '</td>'
        . '<td class="dash-clicks num">' . (int) $link['click_count'] . '</td>'
        . '<td class="dash-date">' . \App\escape($formatDate($link['created_at'])) . '</td>'
        . '<td class="dash-pixels" title="' . \App\escape($pixelsLabel((string) $link['pixels'])) . '">' . \App\escape($pixelsLabel((string) $link['pixels'])) . '</td>'
        . '<td class="dash-target" title="' . \App\escape($link['target_url']) . '">' . \App\escape($link['target_url']) . '</td>'
        . '<td class="dash-short" title="' . \App\escape($shortUrl) . '"><a class="dash-slug" href="' . \App\escape($shortTarget) . '" target="_blank" rel="noopener">' . \App\escape($shortUrl) . '</a></td>'
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

    <header class="dash-bar">
        <div class="dash-bar-left">
            <button id="menu-btn" class="dash-menu-btn" aria-label="Mở menu" aria-expanded="false" aria-controls="dash-menu">
                <span class="dash-menu-icon" aria-hidden="true"></span>
            </button>
            <a class="dash-brand" href="<?= \App\url_for('/') ?>">
                <span class="brand-mark" aria-hidden="true"></span> UrlShortM
            </a>
        </div>
        <div class="dash-bar-right">
            <span class="dash-bar-user" title="<?= \App\escape($displayName) ?>"><?= \App\escape($initial) ?></span>
            <form class="dash-logout" method="post" action="<?= \App\url_for('dang-xuat') ?>">
                <?= $csrf->field() ?>
                <button type="submit">Thoát</button>
            </form>
        </div>
    </header>

    <div class="dash-overlay" id="dash-overlay" hidden></div>

    <aside class="dash-menu" id="dash-menu" aria-label="Menu bảng điều khiển">
        <div class="dash-menu-head">
            <a class="dash-brand" href="<?= \App\url_for('/') ?>">
                <span class="brand-mark" aria-hidden="true"></span> UrlShortM
            </a>
            <button id="menu-close" class="dash-menu-close" aria-label="Đóng menu">&#10005;</button>
        </div>

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

            <?= $navItem('baocao', 'Báo cáo', '03', $tab === 'baocao') ?>

            <?= $navItem('tai-khoan', 'Tài khoản', '04', $tab === 'tai-khoan') ?>

            <div class="dash-nav-group">
                <span class="dash-nav-group-label"><span class="dash-nav-num">05</span>Cài đặt</span>
                <?= $navSub('cai-dat', 'Cài đặt tài khoản', $tab === 'cai-dat') ?>
                <?= $navSub('pixels', 'Thiết lập Pixels', $tab === 'pixels') ?>
                <?= $navSub('domains', 'Custom domain', $tab === 'domains') ?>
                <?= $navSub('utms', 'UTMs tracking', $tab === 'utms') ?>
                <?= $navSub('demographics', 'Nhân khẩu học (Meta)', $tab === 'demographics') ?>
            </div>
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
                <span class="dash-plan-badge"><?= \App\escape($planInfo['plan']['name'] ?? 'Miễn phí') ?></span>
                <span>///</span>
                <span class="dash-tape-live"><span class="pulse-dot" aria-hidden="true"></span> đang chạy</span>
            </div>
        </header>

        <?php if ($flashOk): ?>
            <div class="dash-flash" role="status">Đã lưu thay đổi.</div>
        <?php endif; ?>

        <?php if ($flashError !== null): ?>
            <div class="alert alert-error" role="alert"><?= \App\escape($flashError) ?></div>
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
                                <tr><th>Link ngắn</th><th>Đích đến</th><th class="num">Lượt mở</th><th>Tạo lúc</th><th></th></tr>
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
                                    <th class="dash-tick tick"><input type="checkbox" id="check-all" aria-label="Chọn tất cả"></th>
                                    <th>Title</th>
                                    <th class="num">Clicks</th>
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
                                        <tr><th>Link ngắn</th><th>Đích đến</th><th class="num">Lượt mở</th><th>Tạo lúc</th><th>Thư mục</th><th></th></tr>
                                    </thead>
                                    <tbody><?= $tableBody($links, true) ?></tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

            <?php elseif ($tab === 'baocao'): ?>

                <?php
                $exportUrl = \App\url_for('dashboard/bao-cao/export') . '?tab=baocao';
                if ($reportLinkId !== null) {
                    $exportUrl .= '&link_id=' . $reportLinkId;
                }
                if ($reportFrom !== '') {
                    $exportUrl .= '&from=' . $reportFrom;
                }
                if ($reportTo !== '') {
                    $exportUrl .= '&to=' . $reportTo;
                }
                if ($reportData !== null) {
                    foreach ($reportData['byCountry'] as &$c) {
                        $c['label'] = \App\country_label($c['label']);
                    }
                    unset($c);
                }
                ?>

                <form method="get" action="<?= \App\url_for('dashboard') ?>" class="report-filter">
                    <input type="hidden" name="tab" value="baocao">
                    <div class="report-filter-row">
                        <label for="rep-link">Link</label>
                        <select id="rep-link" name="link_id">
                            <option value="">Tất cả link</option>
                            <?php foreach ($allLinks as $l): ?>
                                <option value="<?= (int) $l['id'] ?>" <?= $reportLinkId === (int) $l['id'] ? 'selected' : '' ?>>
                                    <?= \App\escape(($l['title'] ?: $l['slug']) . ' — ' . $l['slug']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="report-filter-row">
                        <label for="rep-from">Từ ngày</label>
                        <input id="rep-from" name="from" type="date" value="<?= \App\escape($reportFrom) ?>">
                    </div>
                    <div class="report-filter-row">
                        <label for="rep-to">Đến ngày</label>
                        <input id="rep-to" name="to" type="date" value="<?= \App\escape($reportTo) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Xem báo cáo</button>
                    <a class="btn btn-soft btn-sm" href="<?= \App\escape($exportUrl) ?>">Tải CSV</a>
                </form>

                <?php if ($reportData === null || $reportData['summary']['total_clicks'] === 0): ?>
                    <section class="dash-panel">
                        <div class="dash-panel-head"><h2>Báo cáo</h2></div>
                        <div class="dash-empty" style="padding: 3rem 1rem;">
                            Chưa có dữ liệu. Báo cáo bắt đầu ghi từ khi có người mở link của bạn.
                        </div>
                    </section>
                <?php else: ?>
                    <div class="dash-grid report-summary">
                        <article class="gauge">
                            <span class="gauge-label">Tổng lượt mở</span>
                            <strong class="gauge-num"><?= $reportData['summary']['total_clicks'] ?></strong>
                        </article>
                        <article class="gauge gauge-accent">
                            <span class="gauge-label">TB lượt mở / ngày</span>
                            <strong class="gauge-num"><?= $reportData['summary']['avg_per_day'] ?></strong>
                        </article>
                        <article class="gauge">
                            <span class="gauge-label">Link có lượt mở</span>
                            <strong class="gauge-num"><?= $reportData['summary']['total_links'] ?></strong>
                        </article>
                    </div>

                    <div class="report-charts">
                        <section class="dash-panel report-chart report-chart-wide">
                            <div class="dash-panel-head"><h2>Lượt mở theo ngày</h2></div>
                            <div class="report-canvas"><canvas id="chart-day" height="220"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart">
                            <div class="dash-panel-head"><h2>Thiết bị</h2></div>
                            <div class="report-canvas"><canvas id="chart-device"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart">
                            <div class="dash-panel-head"><h2>Trình duyệt</h2></div>
                            <div class="report-canvas"><canvas id="chart-browser"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart">
                            <div class="dash-panel-head"><h2>Hệ điều hành</h2></div>
                            <div class="report-canvas"><canvas id="chart-os"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart">
                            <div class="dash-panel-head"><h2>Quốc gia</h2></div>
                            <div class="report-canvas"><canvas id="chart-country"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart report-chart-wide">
                            <div class="dash-panel-head"><h2>Nguồn vào (Referrer)</h2></div>
                            <div class="report-canvas"><canvas id="chart-referrer"></canvas></div>
                        </section>

                        <section class="dash-panel report-chart report-chart-wide">
                            <div class="dash-panel-head"><h2>Top link</h2></div>
                            <div class="report-canvas"><canvas id="chart-top" height="200"></canvas></div>
                        </section>
                    </div>

                    <section class="dash-panel">
                        <div class="dash-panel-head"><h2>Chi tiết lượt mở</h2></div>
                        <div class="dash-table-wrap">
                            <table class="dash-table">
                                <thead>
                                    <tr><th>Thời gian</th><th>Link</th><th>Quốc gia</th><th>Thiết bị</th><th>Trình duyệt</th><th>Hệ điều hành</th><th>IP</th><th>Nguồn vào</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($reportEvents === []): ?>
                                        <tr><td colspan="8" class="dash-empty">Không có lượt mở trong phạm vi này.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reportEvents as $ev): ?>
                                            <tr>
                                                <td class="dash-date"><?= \App\escape($ev['opened_at']) ?></td>
                                                <td class="dash-pixels" title="<?= \App\escape($ev['slug']) ?>"><?= \App\escape($ev['title']) ?></td>
                                                <td class="dash-pixels"><?= \App\escape(\App\country_label($ev['country'] ?? null)) ?></td>
                                                <td class="dash-pixels"><?= \App\escape($ev['device'] ?? '—') ?></td>
                                                <td class="dash-pixels"><?= \App\escape($ev['browser'] ?? '—') ?></td>
                                                <td class="dash-pixels"><?= \App\escape($ev['os'] ?? '—') ?></td>
                                                <td class="dash-pixels"><?= \App\escape($ev['ip_address'] ?? '—') ?></td>
                                                <td class="dash-pixels" title="<?= \App\escape((string) ($ev['referrer'] ?? '')) ?>"><?= \App\escape($ev['referrer'] ?: '(trực tiếp)') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        $perPage = 50;
                        $totalPages = (int) ceil($reportTotal / $perPage);
                        if ($totalPages > 1):
                            $pageUrl = function (int $p) use ($reportLinkId, $reportFrom, $reportTo): string {
                                $u = \App\url_for('dashboard') . '?tab=baocao&page=' . $p;
                                if ($reportLinkId !== null) {
                                    $u .= '&link_id=' . $reportLinkId;
                                }
                                if ($reportFrom !== '') {
                                    $u .= '&from=' . $reportFrom;
                                }
                                if ($reportTo !== '') {
                                    $u .= '&to=' . $reportTo;
                                }
                                return $u;
                            };
                        ?>
                            <div class="report-pager">
                                <?php if ($reportPage > 1): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= \App\escape($pageUrl($reportPage - 1)) ?>">&#8592; Trước</a>
                                <?php endif; ?>
                                <span class="report-pager-info">Trang <?= $reportPage ?> / <?= $totalPages ?></span>
                                <?php if ($reportPage < $totalPages): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= \App\escape($pageUrl($reportPage + 1)) ?>">Sau &#8594;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($demoSnapshot !== null): ?>
                    <section class="dash-panel">
                        <div class="dash-panel-head">
                            <h2>Nhân khẩu học (Meta)</h2>
                            <span class="lform-hint">Cập nhật: <?= \App\escape($demoSnapshot['fetched_at']) ?></span>
                        </div>
                        <div class="report-demo-grid">
                            <div>
                                <div class="report-canvas"><canvas id="chart-demo-age" height="200"></canvas></div>
                            </div>
                            <div>
                                <div class="report-canvas"><canvas id="chart-demo-gender" height="200"></canvas></div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <script id="report-data" type="application/json"><?= json_encode($reportData ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

            <?php elseif ($tab === 'tai-khoan'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Gói & giới hạn sử dụng</h2></div>
                    <dl class="dash-profile">
                        <?php
                        $limitLabel = static fn (?int $v): string => $v === null ? 'Không giới hạn' : number_format($v);
                        $usageRow = static function (string $label, int $used, ?int $max) use ($limitLabel): string {
                            $pct = $max !== null && $max > 0 ? (int) round($used / $max * 100) : 0;
                            $over = $max !== null && $used > $max;
                            $bar = $over ? 100 : min(100, $pct);
                            return '<div><dt>' . \App\escape($label) . '</dt><dd>'
                                . '<span class="dash-usage-num">' . number_format($used) . ' / ' . \App\escape($limitLabel($max)) . '</span>'
                                . '<span class="dash-usage-bar"><span style="width:' . $bar . '%" class="' . ($over ? 'is-over' : '') . '"></span></span>'
                                . '</dd></div>';
                        };
                        ?>
                        <div><dt>Gói hiện tại</dt><dd><span class="dash-plan-badge"><?= \App\escape($planInfo['plan']['name'] ?? 'Miễn phí') ?></span> <a class="btn btn-primary btn-sm" href="<?= \App\url_for('thanh-toan') ?>">Nâng cấp gói</a></dd></div>
                        <?= $usageRow('Số link', $planInfo['usage']['links'], $planInfo['limits']['max_links']) ?>
                        <?= $usageRow('Custom domain', $planInfo['usage']['domains'], $planInfo['limits']['max_custom_domains']) ?>
                        <?= $usageRow('Pixel', $planInfo['usage']['pixels'], $planInfo['limits']['max_pixels']) ?>
                        <?= $usageRow('Click (tháng này)', $planInfo['usage']['clicks_month'], $planInfo['limits']['max_clicks']) ?>
                    </dl>
                    <aside class="dash-note">Đạt giới hạn? Hãy xoá bớt hoặc nâng cấp gói để dùng nhiều hơn.</aside>
                </section>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Thông tin tài khoản</h2></div>
                    <dl class="dash-profile">
                        <div><dt>Tên hiển thị</dt><dd><?= \App\escape($displayName) ?></dd></div>
                        <div><dt>Email</dt><dd><?= \App\escape($user['email']) ?></dd></div>
                        <div><dt>Số điện thoại</dt><dd><?= \App\escape($user['phone'] ?? '—') ?></dd></div>
                        <div><dt>Địa chỉ</dt><dd><?= \App\escape($user['address'] ?? '—') ?><?= ($user['city'] ?? '') !== '' ? ', ' . \App\escape($user['city']) : '' ?></dd></div>
                        <div><dt>Loại khách hàng</dt><dd><?= match ($user['tax_type'] ?? '') { 'business' => 'Doanh nghiệp', 'individual' => 'Cá nhân', default => '—' } ?></dd></div>
                        <div><dt>Mã số thuế</dt><dd><?= \App\escape($user['tax_id'] ?? '—') ?></dd></div>
                        <div><dt>Tên trên hoá đơn</dt><dd><?= \App\escape(($user['invoice_name'] ?? '') ?: ($user['company_name'] ?? '—')) ?></dd></div>
                        <div><dt>Ngày tham gia</dt><dd><?= \App\escape($formatDate($user['created_at'])) ?></dd></div>
                        <div><dt>Trạng thái</dt><dd><span class="dash-status"><span class="pulse-dot" aria-hidden="true"></span> Hoạt động</span></dd></div>
                        <div><dt>Gói dịch vụ</dt><dd>Miễn phí</dd></div>
                    </dl>
                </section>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Đổi mật khẩu</h2></div>
                    <form method="post" action="<?= \App\url_for('dashboard/account/password') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <div class="dash-settings-row">
                            <label for="pw-current">Mật khẩu hiện tại</label>
                            <input id="pw-current" name="current_password" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="dash-settings-row">
                            <label for="pw-new">Mật khẩu mới</label>
                            <input id="pw-new" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="dash-settings-row">
                            <label for="pw-new-confirm">Nhập lại mật khẩu mới</label>
                            <input id="pw-new-confirm" name="new_password_confirm" type="password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                    </form>
                </section>

                <section class="dash-panel dash-panel-danger">
                    <div class="dash-panel-head"><h2>Vô hiệu hoá tài khoản</h2></div>
                    <form method="post" action="<?= \App\url_for('dashboard/account/deactivate') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <p class="lform-hint" style="padding: 0 0 0.8rem;">
                            Tài khoản sẽ bị ẩn (không đăng nhập được) nhưng dữ liệu vẫn được giữ lại — không xoá.
                            Nhập mật khẩu hiện tại rồi bấm xác nhận.
                        </p>
                        <div class="dash-settings-row">
                            <label for="pw-deactivate">Mật khẩu hiện tại</label>
                            <input id="pw-deactivate" name="current_password" type="password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn chắc chắn muốn vô hiệu hoá tài khoản? Dữ liệu sẽ được giữ lại nhưng bạn không thể đăng nhập lại.')">Vô hiệu hoá tài khoản</button>
                    </form>
                </section>

            <?php elseif ($tab === 'cai-dat'): ?>

                <form method="post" action="<?= \App\url_for('dashboard/settings') ?>" class="dash-settings-form">
                    <?= $csrf->field() ?>

                    <section class="dash-panel">
                        <div class="dash-panel-head"><h2>Cài đặt tài khoản</h2></div>
                        <div class="dash-settings">
                            <div class="dash-settings-row">
                                <label for="display_name">Tên hiển thị</label>
                                <input id="display_name" name="display_name" type="text" maxlength="100" value="<?= \App\escape($displayName) ?>">
                            </div>
                            <div class="dash-settings-row dash-settings-muted">
                                <span>Email</span>
                                <span><?= \App\escape($user['email']) ?></span>
                            </div>
                            <div class="dash-settings-row">
                                <label for="phone">Số điện thoại</label>
                                <input id="phone" name="phone" type="tel" maxlength="20" value="<?= \App\escape($user['phone'] ?? '') ?>" placeholder="0901234567">
                            </div>
                            <div class="dash-settings-row">
                                <label for="address">Địa chỉ</label>
                                <input id="address" name="address" type="text" maxlength="255" value="<?= \App\escape($user['address'] ?? '') ?>" placeholder="Số nhà, đường, phường/xã, quận/huyện">
                            </div>
                            <div class="dash-settings-row">
                                <label for="city">Tỉnh / Thành phố</label>
                                <input id="city" name="city" type="text" maxlength="100" value="<?= \App\escape($user['city'] ?? '') ?>" placeholder="Hồ Chí Minh">
                            </div>
                        </div>
                    </section>

                    <section class="dash-panel">
                        <div class="dash-panel-head"><h2>Thông tin xuất hoá đơn</h2></div>
                        <p class="lform-hint" style="padding: 0.4rem 1.4rem 0.2rem;">
                            Điền khi bạn cần xuất hoá đơn cho giao dịch nâng cấp. Mã số thuế có thể là
                            mã số thuế cá nhân hoặc mã số thuế doanh nghiệp (10-14 chữ số).
                        </p>
                        <div class="dash-settings">
                            <div class="dash-settings-row">
                                <label for="tax_type">Loại khách hàng</label>
                                <select id="tax_type" name="tax_type">
                                    <option value="" <?= ($user['tax_type'] ?? '') === '' ? 'selected' : '' ?>>Chưa chọn</option>
                                    <option value="individual" <?= ($user['tax_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Cá nhân</option>
                                    <option value="business" <?= ($user['tax_type'] ?? '') === 'business' ? 'selected' : '' ?>>Doanh nghiệp</option>
                                </select>
                            </div>
                            <div class="dash-settings-row">
                                <label for="invoice_name">Tên trên hoá đơn</label>
                                <input id="invoice_name" name="invoice_name" type="text" maxlength="190" value="<?= \App\escape($user['invoice_name'] ?? '') ?>" placeholder="Họ tên / tên người mua">
                            </div>
                            <div class="dash-settings-row">
                                <label for="company_name">Tên công ty / đơn vị</label>
                                <input id="company_name" name="company_name" type="text" maxlength="190" value="<?= \App\escape($user['company_name'] ?? '') ?>" placeholder="Công ty TNHH ...">
                            </div>
                            <div class="dash-settings-row">
                                <label for="tax_id">Mã số thuế</label>
                                <input id="tax_id" name="tax_id" type="text" maxlength="30" value="<?= \App\escape($user['tax_id'] ?? '') ?>" placeholder="1234567890" autocomplete="off">
                            </div>
                        </div>
                        <div class="dash-settings-actions">
                            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                        </div>
                    </section>
                </form>

            <?php elseif ($tab === 'pixels'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head">
                        <h2><?= $pixelEdit !== null ? 'Sửa Pixel' : 'Tạo Pixel' ?></h2>
                        <?php if ($pixelEdit !== null): ?>
                            <a href="<?= \App\url_for('dashboard') . '?tab=pixels' ?>">&#8592; Hủy sửa</a>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?= \App\url_for($pixelEdit !== null ? 'dashboard/pixel/update' : 'dashboard/pixel/create') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <?php if ($pixelEdit !== null): ?><input type="hidden" name="pixel_id" value="<?= (int) $pixelEdit['id'] ?>"><?php endif; ?>
                        <div class="dash-settings-row">
                            <label for="pixel-platform">Select the Platform</label>
                            <select id="pixel-platform" name="platform" required>
                                <option value="">— Chọn nền tảng —</option>
                                <?php foreach ($platforms as $key => $label): ?>
                                    <option value="<?= \App\escape($key) ?>" <?= ($pixelEdit['platform'] ?? '') === $key ? 'selected' : '' ?>><?= \App\escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dash-settings-row">
                            <label for="pixel-name">Name of Pixel</label>
                            <input id="pixel-name" name="name" type="text" maxlength="100" required placeholder="vd: Chiến dịch tháng 9"
                                   value="<?= \App\escape((string) ($pixelEdit['name'] ?? '')) ?>">
                        </div>
                        <div class="dash-settings-row">
                            <label for="pixel-code">Pixel ID</label>
                            <input id="pixel-code" name="code" type="text" maxlength="32" required placeholder="vd: 1234567890123456"
                                   value="<?= \App\escape((string) ($pixelEdit['code'] ?? '')) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><?= $pixelEdit !== null ? 'Lưu thay đổi' : 'Thêm Pixel' ?></button>
                        <p class="lform-hint">Muốn biết cách lấy Pixel ID cho từng nền tảng, xem
                            <a href="<?= \App\url_for('tro-giup/pixel-id') ?>" target="_blank" rel="noopener">hướng dẫn lấy Pixel ID</a>.</p>
                    </form>
                </section>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Bảng quản lý Pixels</h2></div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead><tr><th>Name</th><th>Platform</th><th>Value</th><th>Creation date</th><th class="num">Action</th></tr></thead>
                            <tbody>
                                <?php if ($pixels === []): ?>
                                    <tr><td colspan="5" class="dash-empty">Chưa có Pixel nào.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pixels as $pixel): ?>
                                        <tr>
                                            <td class="dash-title-cell">
                                                <span class="dash-link-title" title="<?= \App\escape($pixel['name'] ?: $pixel['code']) ?>"><?= \App\escape($pixel['name'] ?: $pixel['code']) ?></span>
                                            </td>
                                            <td class="dash-pixels"><?= \App\escape(\App\Security\PixelPlatform::label($pixel['platform'])) ?></td>
                                            <td class="dash-pixels"><?= \App\escape($pixel['code']) ?></td>
                                            <td class="dash-date"><?= \App\escape($formatDate($pixel['created_at'])) ?></td>
                                            <td class="dash-actions">
                                                <a class="btn btn-ghost btn-sm" href="<?= \App\url_for('dashboard') . '?tab=pixels&edit=' . (int) $pixel['id'] ?>">Sửa</a>
                                                <form method="post" action="<?= \App\url_for('dashboard/pixel/delete') ?>" class="dash-inline-form" onsubmit="return confirm('Xoá Pixel này?')">
                                                    <?= $csrf->field() ?>
                                                    <input type="hidden" name="pixel_id" value="<?= (int) $pixel['id'] ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm dash-del">Xoá</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="lform-hint" style="padding: 0.6rem 1.2rem;">Pixel bạn tạo sẽ xuất hiện trong droplist khi tạo/sửa link.</p>
                </section>

            <?php elseif ($tab === 'domains'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Custom domain</h2></div>
                    <form method="post" action="<?= \App\url_for('dashboard/domain/create') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <div class="dash-settings-row">
                            <label for="domain-input">Tên miền</label>
                            <input id="domain-input" name="domain" type="text" maxlength="190" required placeholder="vd: link.viducongty.com">
                        </div>
                        <button type="submit" class="btn btn-primary">Thêm domain</button>
                    </form>

                    <details class="dash-domain-guide">
                        <summary>Làm thế nào để thêm tên miền của bạn? (xem hướng dẫn)</summary>
                        <ol>
                            <li>Đăng nhập vào nhà cung cấp tên miền / hosting của bạn.</li>
                            <li>Trong phần cài đặt tên miền, tìm cài đặt DNS.</li>
                            <li>Tạo bản ghi CNAME tuỳ chọn trỏ về <code><?= \App\escape(\App\Config::get('app.domains.relay_host', 'links.urlshortm.com')) ?></code>.</li>
                            <li>Chờ quá trình lan truyền DNS hoàn tất.</li>
                            <li>Thêm tên miền phụ của bạn ở trên rồi bấm Xác minh.</li>
                        </ol>
                        <p class="lform-hint">
                            Xem hướng dẫn đầy đủ tại
                            <a href="<?= \App\url_for('tro-giup/custom-domain') ?>" target="_blank" rel="noopener">Cách thêm tên miền tuỳ chỉnh</a>.
                            Trong môi trường thử nghiệm, <code>localhost</code> và domain
                            <code>*.test</code> (vd <code>link.leo.test</code>) được tự xác minh.
                        </p>
                    </details>
                </section>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Danh sách domain</h2></div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead><tr><th>Domain</th><th>Trạng thái</th><th class="num">Action</th></tr></thead>
                            <tbody>
                                <?php if ($domains === []): ?>
                                    <tr><td colspan="3" class="dash-empty">Chưa có domain nào.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($domains as $domain): ?>
                                        <tr>
                                            <td class="dash-pixels"><?= \App\escape($domain['domain']) ?></td>
                                            <td>
                                                <?php if ((int) $domain['is_verified'] === 1): ?>
                                                    <span class="dash-status"><span class="pulse-dot" aria-hidden="true"></span> Đã xác minh</span>
                                                <?php else: ?>
                                                    <div class="dash-domain-unverified">
                                                        <span class="dash-muted">Chưa xác minh</span>
                                                        <?php if (!empty($domain['verification_token'])): ?>
                                                            <div class="dash-txt-row">
                                                                <span class="dash-txt-label">Thêm bản ghi TXT:</span>
                                                                <code class="dash-txt"><?= \App\escape(\App\Service\DomainService::TXT_PREFIX . $domain['verification_token']) ?></code>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($domain['last_error'])): ?>
                                                            <span class="dash-err"><?= \App\escape((string) $domain['last_error']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="dash-actions">
                                                <?php if ((int) $domain['is_verified'] !== 1): ?>
                                                    <form method="post" action="<?= \App\url_for('dashboard/domain/verify') ?>" class="dash-inline-form">
                                                        <?= $csrf->field() ?>
                                                        <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                                                        <button type="submit" class="btn btn-soft btn-sm">Xác minh</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="<?= \App\url_for('dashboard/domain/delete') ?>" class="dash-inline-form" onsubmit="return confirm('Xoá domain này?')">
                                                    <?= $csrf->field() ?>
                                                    <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm dash-del">Xoá</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="lform-hint" style="padding: 0.6rem 1.2rem;">
                        Để xác minh: thêm bản ghi <strong>TXT</strong> như trên vào DNS của domain rồi bấm
                        <strong>Xác minh</strong> (DNS có thể mất vài phút). Domain đã xác minh sẽ xuất hiện
                        trong mục "Choose domain name" khi tạo link. <code>localhost</code>, <code>127.0.0.1</code>
                        và domain kết thúc bằng <code>.test</code>/<code>.localhost</code> được tự xác minh
                        để thử nghiệm (vd <code>link.mark.test</code>).
                    </p>
                </section>

            <?php elseif ($tab === 'utms'): ?>

                <div class="utm-layout">
                    <section class="dash-panel utm-form-panel">
                        <div class="dash-panel-head">
                            <h2><?= $utmEdit !== null ? 'Sửa profile UTM' : 'Tạo profile UTM' ?></h2>
                            <?php if ($utmEdit !== null): ?>
                                <a class="dash-panel-head-link" href="<?= \App\url_for('dashboard') . '?tab=utms' ?>">&#8592; Hủy sửa</a>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= \App\url_for('dashboard/utm/store') ?>" class="dash-settings">
                            <?= $csrf->field() ?>
                            <?php if ($utmEdit !== null): ?><input type="hidden" name="id" value="<?= (int) $utmEdit['id'] ?>"><?php endif; ?>
                            <div class="dash-settings-row">
                                <label for="utm-name">Tên profile</label>
                                <input id="utm-name" name="name" type="text" maxlength="100" required placeholder="vd: Quảng cáo Facebook"
                                       value="<?= \App\escape($utmEdit['name'] ?? '') ?>">
                            </div>
                            <?php foreach (['utm_campaign' => 'UTM Campaign', 'utm_medium' => 'UTM Medium', 'utm_source' => 'UTM Source', 'utm_term' => 'UTM Term', 'utm_content' => 'UTM Content'] as $field => $label): ?>
                                <div class="dash-settings-row">
                                    <label for="utm-<?= $field ?>"><?= \App\escape($label) ?></label>
                                    <input id="utm-<?= $field ?>" name="<?= $field ?>" type="text" value="<?= \App\escape((string) ($utmEdit[$field] ?? '')) ?>">
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary"><?= $utmEdit !== null ? 'Lưu thay đổi' : 'Tạo profile' ?></button>
                        </form>
                    </section>

                    <aside class="utm-help">
                        <h3>UTM tags là gì?</h3>
                        <p class="utm-help-intro">
                            UTM là các tham số gắn thêm vào URL để Google Analytics biết lượt truy cập
                            đến từ đâu và thuộc chiến dịch nào. Điền đúng sẽ giúp báo cáo của bạn rõ ràng.
                        </p>
                        <dl class="utm-help-list">
                            <div><dt>UTM Campaign</dt><dd>Tên chiến dịch — vd: quang-cao-thang-9, khuyen-mai-tet</dd></div>
                            <div><dt>UTM Medium</dt><dd>Phương tiện — vd: cpc, social, email, banner</dd></div>
                            <div><dt>UTM Source</dt><dd>Nguồn gửi — vd: facebook, google, zalo, newsletter</dd></div>
                            <div><dt>UTM Term</dt><dd>Từ khoá trả phí — vd: rua-mat, gia-re</dd></div>
                            <div><dt>UTM Content</dt><dd>Phân biệt nội dung cùng chiến dịch — vd: banner-top, button-xanh</dd></div>
                        </dl>
                        <p class="utm-help-example">
                            Ví dụ:<br>
                            <code>https://…/x?utm_source=facebook&amp;utm_medium=social&amp;utm_campaign=thang-9</code>
                        </p>
                        <p class="lform-hint">Tạo profile UTM để tự điền nhanh các tham số này khi tạo link.</p>
                    </aside>
                </div>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Danh sách profile</h2></div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead><tr><th>Tên</th><th>Campaign</th><th>Medium</th><th>Source</th><th class="num">Action</th></tr></thead>
                            <tbody>
                                <?php if ($utmProfiles === []): ?>
                                    <tr><td colspan="5" class="dash-empty">Chưa có profile UTM nào.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($utmProfiles as $profile): ?>
                                        <tr>
                                            <td class="dash-link-title"><?= \App\escape($profile['name']) ?></td>
                                            <td class="dash-pixels"><?= \App\escape((string) $profile['utm_campaign']) ?></td>
                                            <td class="dash-pixels"><?= \App\escape((string) $profile['utm_medium']) ?></td>
                                            <td class="dash-pixels"><?= \App\escape((string) $profile['utm_source']) ?></td>
                                            <td class="dash-actions">
                                                <a class="btn btn-ghost btn-sm" href="<?= \App\url_for('dashboard') . '?tab=utms&edit=' . (int) $profile['id'] ?>">Edit</a>
                                                <form method="post" action="<?= \App\url_for('dashboard/utm/delete') ?>" class="dash-inline-form" onsubmit="return confirm('Xoá profile UTM này?')">
                                                    <?= $csrf->field() ?>
                                                    <input type="hidden" name="utm_id" value="<?= (int) $profile['id'] ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm dash-del">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="lform-hint" style="padding: 0.6rem 1.2rem;">Profile UTM xuất hiện trong mục "Add UTM tags" khi tạo/sửa link — chọn là tự điền nhanh.</p>
                </section>

            <?php elseif ($tab === 'demographics'): ?>

                <section class="dash-panel">
                    <div class="dash-panel-head"><h2>Kết nối Meta (nhân khẩu học)</h2></div>
                    <form method="post" action="<?= \App\url_for('dashboard/demographics/save') ?>" class="dash-settings">
                        <?= $csrf->field() ?>
                        <div class="dash-settings-row">
                            <label for="meta-ad">Ad Account ID</label>
                            <input id="meta-ad" name="meta_ad_account" type="text" value="<?= \App\escape($metaConfig['ad_account'] ?? '') ?>" placeholder="act_1234567890">
                        </div>
                        <div class="dash-settings-row">
                            <label for="meta-token">Access Token</label>
                            <input id="meta-token" name="meta_token" type="password" placeholder="<?= \App\escape($metaConfig['token_mask'] ?? 'Chưa có token') ?>" autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                    </form>
                    <p class="lform-hint" style="padding: 0.6rem 1.2rem;">
                        Token chỉ lưu cho tài khoản của bạn; không hiển thị đầy đủ. Lấy dữ liệu sau khi lưu.
                    </p>
                </section>

                <section class="dash-panel">
                    <div class="dash-panel-head">
                        <h2>Lấy dữ liệu nhân khẩu học</h2>
                        <form method="post" action="<?= \App\url_for('dashboard/demographics/fetch') ?>" class="dash-inline-form">
                            <?= $csrf->field() ?>
                            <button type="submit" class="btn btn-primary btn-sm">Lấy dữ liệu (90 ngày)</button>
                        </form>
                    </div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead><tr><th>Độ tuổi</th><th class="num">Lượt hiển thị</th><th>Giới tính</th><th class="num">Lượt hiển thị</th></tr></thead>
                            <tbody>
                                <?php if ($demoSnapshot === null || ($demoSnapshot['payload']['age'] ?? []) === []): ?>
                                    <tr><td colspan="4" class="dash-empty">Chưa có dữ liệu. Bấm "Lấy dữ liệu (90 ngày)" sau khi cấu hình.</td></tr>
                                <?php else: ?>
                                    <?php
                                    $age = $demoSnapshot['payload']['age'] ?? [];
                                    $gender = $demoSnapshot['payload']['gender'] ?? [];
                                    $rows = max(count($age), count($gender));
                                    for ($i = 0; $i < $rows; $i++):
                                        $a = $age[$i] ?? null;
                                        $g = $gender[$i] ?? null;
                                    ?>
                                        <tr>
                                            <td class="dash-pixels"><?= \App\escape($a['label'] ?? '—') ?></td>
                                            <td class="dash-clicks num"><?= isset($a) ? (int) $a['count'] : '—' ?></td>
                                            <td class="dash-pixels"><?= \App\escape($g['label'] ?? '—') ?></td>
                                            <td class="dash-clicks num"><?= isset($g) ? (int) $g['count'] : '—' ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($demoSnapshot !== null): ?>
                        <div class="dash-panel-head">
                            <span class="lform-hint">Cập nhật lần cuối: <?= \App\escape($demoSnapshot['fetched_at']) ?></span>
                            <form method="post" action="<?= \App\url_for('dashboard/demographics/clear') ?>" class="dash-inline-form">
                                <?= $csrf->field() ?>
                                <button type="submit" class="btn btn-ghost btn-sm dash-del" onclick="return confirm('Xoá cấu hình và dữ liệu nhân khẩu học?')">Xoá dữ liệu</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="dash-note">
                    Nhân khẩu học lấy từ Meta (phân bổ độ tuổi/giới tính của đối tượng quảng cáo trong 90 ngày) — chỉ khi bạn cấu hình
                    Ad Account có quyền và token hợp lệ. Dữ liệu tổng hợp, không chứa thông tin cá nhân. Cần tuân thủ chính sách Meta và quy định về dữ liệu.
                </aside>

            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?= \App\url_for('assets/js/app.js') ?>" defer></script>
<?php if ($tab === 'baocao'): ?>
<script src="<?= \App\url_for('assets/js/vendor/chart.umd.min.js') ?>" defer></script>
<script src="<?= \App\url_for('assets/js/report.js') ?>" defer></script>
<?php endif; ?>
<script src="<?= \App\url_for('assets/js/vendor/qrcode.js') ?>" defer></script>
<script src="<?= \App\url_for('assets/js/qr.js') ?>" defer></script>
</body>
</html>
