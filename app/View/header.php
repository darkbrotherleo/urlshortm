<?php
/** @var string $title */
?><!DOCTYPE html>
<html lang="<?= \App\escape(\App\site_seo_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <?= \App\site_seo_head() ?>
    <title><?= escape($title) ?></title>
    <link rel="stylesheet" href="<?= url_for('assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preload" href="<?= url_for('assets/fonts/lexend-vietnamese.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= url_for('assets/fonts/lexend-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
</head>
<body>
<?= \App\site_seo_body() ?>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= url_for('/') ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            UrlShortM
        </a>
        <nav class="nav-links" aria-label="Điều hướng chính">
            <a href="<?= url_for('tinh-nang') ?>">Tính năng</a>
            <a href="<?= url_for('') ?>#cach-hoat-dong">Cách hoạt động</a>
            <a href="<?= url_for('') ?>#cau-hoi">Câu hỏi</a>
            <a href="<?= url_for('bang-gia') ?>">Bảng giá</a>
        </nav>
        <?php $user = \App\current_user(); ?>
        <div class="nav-auth">
            <?php if ($user !== null): ?>
                <a class="nav-user" href="<?= url_for('dashboard') ?>" title="Mở bảng điều khiển">
                    Xin chào, <strong><?= escape($user['display_name'] ?: $user['email']) ?></strong>
                </a>
                <form class="logout-form" method="post" action="<?= url_for('dang-xuat') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Thoát</button>
                </form>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= url_for('dang-nhap') ?>">Đăng nhập</a>
                <a class="btn btn-primary btn-sm" href="<?= url_for('dang-ky') ?>">Đăng ký</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="site-main">
