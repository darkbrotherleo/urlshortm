<?php
/**
 * Trang nhập mật khẩu cho link được bảo vệ.
 *
 * @var string             $title
 * @var string             $slug
 * @var \App\Security\Csrf $csrf
 * @var string|null        $error
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="section">
    <div class="container error-page">
        <span class="pill">Link được bảo vệ</span>
        <h1 class="hero-title">Link này có mật khẩu.</h1>
        <p class="hero-sub">Nhập mật khẩu để tiếp tục đến đích.</p>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive"><?= \App\escape($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= \App\escape(\App\url_for($slug . '/unlock')) ?>" class="auth-card unlock-form">
            <?= $csrf->field() ?>
            <div class="form-field">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Nhập mật khẩu">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Mở link</button>
        </form>
    </div>
</section>
<?php echo \App\render('footer'); ?>
