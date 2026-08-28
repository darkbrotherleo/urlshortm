<?php
/**
 * @var string             $title
 * @var \App\Security\Csrf $csrf
 * @var array{email:string} $values
 * @var string|null        $error
 * @var bool               $sent
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="auth">
    <div class="auth-blob" aria-hidden="true"></div>
    <div class="container auth-grid">
        <div class="auth-pitch">
            <span class="pill pill-light">Quên mật khẩu</span>
            <h1 class="auth-pitch-title">Đặt lại mật khẩu an toàn.</h1>
            <p class="auth-pitch-sub">
                Nhập email đăng ký, chúng tôi gửi liên kết đặt lại mật khẩu (có hiệu lực trong 30 phút).
            </p>
            <ul class="auth-list">
                <li><span aria-hidden="true">&#10003;</span> Liên kết gửi riêng qua email của bạn</li>
                <li><span aria-hidden="true">&#10003;</span> Chỉ đổi mật khẩu khi bấm liên kết trong email</li>
                <li><span aria-hidden="true">&#10003;</span> Hết hạn sau 30 phút vì lý do bảo mật</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2 class="auth-title">Quên mật khẩu</h2>
            <p class="auth-sub">Nhập email tài khoản của bạn.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= escape($error) ?></div>
            <?php endif; ?>
            <?php if ($sent): ?>
                <div class="alert alert-ok" role="status">Nếu email tồn tại, chúng tôi đã gửi liên kết đặt lại mật khẩu. Hãy kiểm tra hộp thư (cả mục Spam).</div>
            <?php endif; ?>

            <?php if (!$sent): ?>
                <form method="post" action="<?= url_for('quen-mat-khau') ?>" novalidate>
                    <?= $csrf->field() ?>
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required placeholder="ban@vidu.com" value="<?= escape($values['email']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Gửi liên kết đặt lại</button>
                </form>
            <?php endif; ?>

            <p class="auth-switch">Nhớ mật khẩu? <a href="<?= url_for('dang-nhap') ?>">Đăng nhập</a></p>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
