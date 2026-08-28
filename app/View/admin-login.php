<?php
/** @var string $title */
/** @var array{email:string} $values */
/** @var string|null $error */
/** @var \App\Security\Csrf $csrf */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= \App\escape($title) ?> — UrlShortM</title>
    <link rel="stylesheet" href="<?= \App\url_for('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= \App\url_for('assets/css/admin.css') ?>">
</head>
<body class="alogin-body">
    <main class="alogin">
        <section class="alogin-card">
            <div class="alogin-brand">
                <span class="a-logo-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
                <div class="a-logo-text">UrlShortM<small>Admin Panel</small></div>
            </div>
            <h1 class="alogin-title">Quản trị hệ thống</h1>
            <p class="alogin-sub">Đăng nhập để quản lý người dùng, link, gói dịch vụ...</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= \App\escape($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= \App\url_for('admin/dang-nhap') ?>" class="alogin-form">
                <?= $csrf->field() ?>
                <div class="alogin-field">
                    <label for="alogin-email">Email</label>
                    <input id="alogin-email" name="email" type="email" required autocomplete="username" value="<?= \App\escape($values['email']) ?>">
                </div>
                <div class="alogin-field">
                    <label for="alogin-password">Mật khẩu</label>
                    <input id="alogin-password" name="password" type="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary alogin-submit">Đăng nhập</button>
            </form>

            <p class="alogin-back"><a href="<?= \App\url_for('') ?>">&#8592; Về trang chủ</a></p>
        </section>
    </main>
</body>
</html>
